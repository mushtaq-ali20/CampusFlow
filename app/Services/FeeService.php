<?php

namespace App\Services;

use App\Enums\AdmissionStatus;
use App\Enums\DiscountMode;
use App\Enums\FeeFrequency;
use App\Enums\PaymentMethod;
use App\Enums\VoucherItemKind;
use App\Enums\VoucherStatus;
use App\Models\Admission;
use App\Models\FeeDiscount;
use App\Models\FeeHead;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\FeeVoucher;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fee collection: monthly vouchers from the fee structure of the class, discounts, late fines and payments.
 *
 * Settings: fees.due_day (day of the month the fee is due, default 10), fees.late_fine (fine added once
 * to an overdue voucher, default 0) and fees.annual_fee_month (month in which the annual fees are charged).
 */
class FeeService
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * Formats an amount in the currency of the institution, such as "Rs 1,500".
     */
    public static function money(int|float $amount): string
    {
        $formatted = number_format($amount);
        $symbol = (string) config('campusflow.currency_symbol');

        return config('campusflow.currency_position') === 'after' ? $formatted.' '.$symbol : $symbol.' '.$formatted;
    }

    /**
     * The amount of a fee head for the class of the admission: the amount of the branch if there is one,
     * otherwise the amount for all branches.
     */
    public function amountFor(Admission $admission, FeeHead $head): int
    {
        $structures = FeeStructure::query()
            ->where('school_class_id', $admission->school_class_id)
            ->where('year_id', $admission->year_id)
            ->where('fee_head_id', $head->id)
            ->where(fn ($query) => $query->whereNull('campus_id')->orWhere('campus_id', $admission->campus_id))
            ->get();

        return (int) ($structures->firstWhere('campus_id', $admission->campus_id)?->amount ?? $structures->first()?->amount ?? 0);
    }

    /**
     * Creates the voucher of the admission for the month, or returns null when there is nothing to charge,
     * the admission is not active, or it started after the month. An existing voucher is never changed.
     */
    public function generateVoucher(Admission $admission, CarbonInterface $month): ?FeeVoucher
    {
        $month = $month->copy()->startOfMonth();

        if ($admission->status !== AdmissionStatus::Active || $admission->admitted_on->copy()->startOfMonth()->greaterThan($month)) {
            return null;
        }

        if (FeeVoucher::query()->where('admission_id', $admission->id)->whereDate('month', $month)->exists()) {
            return null;
        }

        $charges = $this->chargesFor($admission, $month);

        if ($charges->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($admission, $month, $charges): FeeVoucher {
            $voucher = FeeVoucher::query()->create([
                'voucher_number' => 'V-'.$month->format('Ym').'-'.str_pad((string) $admission->id, 5, '0', STR_PAD_LEFT),
                'admission_id' => $admission->id,
                'campus_id' => $admission->campus_id,
                'school_class_id' => $admission->school_class_id,
                'section_id' => $admission->section_id,
                'year_id' => $admission->year_id,
                'month' => $month->toDateString(),
                'due_date' => $month->copy()->day(min($this->dueDay(), $month->daysInMonth))->toDateString(),
            ]);

            foreach ($charges as $charge) {
                $voucher->items()->create($charge);
            }

            return $this->recalculate($voucher);
        });
    }

    /**
     * Creates the vouchers of the month for all the active admissions, or of one branch or section.
     *
     * @return int The number of vouchers created.
     */
    public function generateForMonth(CarbonInterface $month, ?int $campusId = null, ?int $sectionId = null): int
    {
        $created = 0;

        Admission::query()
            ->active()
            ->with(['discounts', 'schoolClass'])
            ->when($campusId !== null, fn ($query) => $query->where('campus_id', $campusId))
            ->when($sectionId !== null, fn ($query) => $query->where('section_id', $sectionId))
            ->each(function (Admission $admission) use ($month, &$created): void {
                if ($this->generateVoucher($admission, $month) !== null) {
                    $created++;
                }
            });

        return $created;
    }

    /**
     * Adds the late payment fine, once, to the vouchers that are overdue.
     *
     * @return int The number of vouchers fined.
     */
    public function applyLateFines(?CarbonInterface $asOf = null, ?int $campusId = null): int
    {
        $fine = $this->lateFine();

        if ($fine <= 0) {
            return 0;
        }

        $fined = 0;

        FeeVoucher::query()
            ->where('status', '!=', VoucherStatus::Paid)
            ->whereDate('due_date', '<', ($asOf ?? now())->toDateString())
            ->whereDoesntHave('items', fn ($query) => $query->where('kind', VoucherItemKind::Fine))
            ->when($campusId !== null, fn ($query) => $query->where('campus_id', $campusId))
            ->each(function (FeeVoucher $voucher) use ($fine, &$fined): void {
                $voucher->items()->create([
                    'kind' => VoucherItemKind::Fine,
                    'description' => __('Late payment fine'),
                    'amount' => $fine,
                ]);
                $this->recalculate($voucher);
                $fined++;
            });

        return $fined;
    }

    /**
     * Records a payment, gives it a receipt number and updates the voucher.
     *
     * @throws DomainException
     */
    public function recordPayment(FeeVoucher $voucher, int $amount, PaymentMethod $method, CarbonInterface $paidOn, ?string $reference = null, ?User $collectedBy = null): FeePayment
    {
        return DB::transaction(function () use ($voucher, $amount, $method, $paidOn, $reference, $collectedBy): FeePayment {
            $voucher = FeeVoucher::query()->lockForUpdate()->findOrFail($voucher->id);

            if ($amount <= 0) {
                throw new DomainException(__('The amount must be greater than zero.'));
            }

            if ($amount > $voucher->balance) {
                throw new DomainException(__('The amount is greater than the balance of :balance.', ['balance' => static::money($voucher->balance)]));
            }

            $payment = $voucher->payments()->create([
                'campus_id' => $voucher->campus_id,
                'amount' => $amount,
                'paid_on' => $paidOn->toDateString(),
                'method' => $method,
                'reference' => $reference,
                'collected_by' => $collectedBy?->id,
            ]);

            $payment->update(['receipt_number' => 'RCP-'.$paidOn->format('ym').'-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT)]);

            $this->recalculate($voucher);

            return $payment;
        });
    }

    /**
     * Totals the items and payments of the voucher, and sets its status.
     */
    public function recalculate(FeeVoucher $voucher): FeeVoucher
    {
        $items = $voucher->items()->get();
        $gross = (int) $items->where('kind', VoucherItemKind::Fee)->sum('amount');
        $discount = (int) $items->where('kind', VoucherItemKind::Discount)->sum('amount');
        $fine = (int) $items->where('kind', VoucherItemKind::Fine)->sum('amount');
        $net = max(0, $gross - $discount + $fine);
        $paid = (int) $voucher->payments()->sum('amount');

        $voucher->update([
            'gross_amount' => $gross,
            'discount_amount' => $discount,
            'fine_amount' => $fine,
            'net_amount' => $net,
            'paid_amount' => $paid,
            'status' => match (true) {
                $paid >= $net => VoucherStatus::Paid,
                $paid > 0 => VoucherStatus::Partial,
                default => VoucherStatus::Unpaid,
            },
        ]);

        return $voucher;
    }

    public function dueDay(): int
    {
        return max(1, min(28, (int) $this->settings->get('fees.due_day', 10)));
    }

    public function lateFine(): int
    {
        return max(0, (int) $this->settings->get('fees.late_fine', 0));
    }

    public function annualFeeMonth(): ?int
    {
        $month = $this->settings->get('fees.annual_fee_month');

        return $month === null || $month === '' ? null : (int) $month;
    }

    /**
     * The fee and discount lines of the voucher of a month.
     *
     * @return Collection<int, array{fee_head_id: int|null, kind: VoucherItemKind, description: string, amount: int}>
     */
    private function chargesFor(Admission $admission, CarbonInterface $month): Collection
    {
        $fees = FeeHead::query()->where('is_active', true)->orderBy('id')->get()
            ->filter(fn (FeeHead $head): bool => $this->isChargedIn($admission, $head, $month))
            ->map(fn (FeeHead $head): array => [
                'fee_head_id' => $head->id,
                'kind' => VoucherItemKind::Fee,
                'description' => $head->name,
                'amount' => $this->amountFor($admission, $head),
            ])
            ->filter(fn (array $charge): bool => $charge['amount'] > 0)
            ->values();

        $discounts = $admission->discounts->flatMap(fn (FeeDiscount $discount): array => $this->discountLines($discount, $fees));

        return $fees->concat($discounts)->values();
    }

    private function isChargedIn(Admission $admission, FeeHead $head, CarbonInterface $month): bool
    {
        return match ($head->frequency) {
            FeeFrequency::Monthly => true,
            FeeFrequency::OneTime => ! $this->alreadyCharged($admission, $head),
            FeeFrequency::Annual => $this->annualFeeMonth() === $month->month && ! $this->alreadyCharged($admission, $head, $month->year),
        };
    }

    private function alreadyCharged(Admission $admission, FeeHead $head, ?int $year = null): bool
    {
        return FeeVoucher::query()
            ->where('admission_id', $admission->id)
            ->when($year !== null, fn ($query) => $query->whereYear('month', $year))
            ->whereHas('items', fn ($query) => $query->where('fee_head_id', $head->id)->where('kind', VoucherItemKind::Fee))
            ->exists();
    }

    /**
     * @param  Collection<int, array{fee_head_id: int|null, kind: VoucherItemKind, description: string, amount: int}>  $fees
     * @return list<array{fee_head_id: int|null, kind: VoucherItemKind, description: string, amount: int}>
     */
    private function discountLines(FeeDiscount $discount, Collection $fees): array
    {
        $applicable = $discount->fee_head_id === null
            ? $fees->filter(fn (array $fee): bool => FeeHead::query()->find($fee['fee_head_id'])?->frequency === FeeFrequency::Monthly)
            : $fees->where('fee_head_id', $discount->fee_head_id);

        $base = (int) $applicable->sum('amount');

        $amount = match ($discount->mode) {
            DiscountMode::Percent => (int) round($base * min($discount->value, 100) / 100),
            DiscountMode::Fixed => min($discount->value, $base),
        };

        return $amount > 0
            ? [['fee_head_id' => $discount->fee_head_id, 'kind' => VoucherItemKind::Discount, 'description' => $discount->name, 'amount' => $amount]]
            : [];
    }
}
