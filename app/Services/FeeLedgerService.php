<?php

namespace App\Services;

use App\Enums\PaymentHabit;
use App\Enums\RequestStatus;
use App\Enums\VoucherItemKind;
use App\Enums\VoucherStatus;
use App\Models\Admission;
use App\Models\FeeRefund;
use App\Models\FeeVoucher;
use App\Models\FeeVoucherItem;
use App\Models\ParentMessage;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The fee account of the students: the ledger of a student, the aging of what is owed, and how each family pays.
 * Staff attached to a branch only see the vouchers of their branch.
 */
class FeeLedgerService
{
    /**
     * The days past due of each aging bucket, by key: not due yet, then up to 30, 60, 90 days, and more.
     *
     * @var array<string, array{0: int, 1: int}>
     */
    public const BUCKETS = [
        'current' => [PHP_INT_MIN, 0],
        'days_30' => [1, 30],
        'days_60' => [31, 60],
        'days_90' => [61, 90],
        'over_90' => [91, PHP_INT_MAX],
    ];

    public function __construct(private readonly FeeAccountService $accounts) {}

    /**
     * Every charge, discount, payment and refund of the student, oldest first, with the balance after each.
     *
     * @return list<array{date: Carbon, description: string, reference: string, debit: int, credit: int, balance: int}>
     */
    public function ledger(Student $student, ?User $user): array
    {
        $vouchers = FeeVoucher::query()
            ->visibleTo($user)
            ->whereIn('admission_id', Admission::query()->where('student_id', $student->id)->select('id'))
            ->with(['items', 'payments.refunds'])
            ->get();

        $entries = collect();

        foreach ($vouchers as $voucher) {
            foreach ($voucher->items as $item) {
                /** @var FeeVoucherItem $item */
                $addedLater = $item->created_at !== null && $voucher->created_at !== null && $item->created_at->greaterThan($voucher->created_at->copy()->addMinute());
                $amount = (int) $item->amount;

                $entries->push([
                    'date' => $addedLater ? $item->created_at->copy()->startOfDay() : $voucher->month->copy(),
                    'order' => 1,
                    'description' => $item->description.' · '.$voucher->month->translatedFormat('F Y'),
                    'reference' => $voucher->voucher_number,
                    'debit' => $item->kind === VoucherItemKind::Discount ? 0 : $amount,
                    'credit' => $item->kind === VoucherItemKind::Discount ? $amount : 0,
                ]);
            }

            foreach ($voucher->payments as $payment) {
                $entries->push([
                    'date' => $payment->paid_on->copy(),
                    'order' => 2,
                    'description' => __('Payment (:method)', ['method' => $payment->method->getLabel()]),
                    'reference' => (string) $payment->receipt_number,
                    'debit' => 0,
                    'credit' => (int) $payment->amount,
                ]);

                foreach ($payment->refunds->where('status', RequestStatus::Approved) as $refund) {
                    /** @var FeeRefund $refund */
                    $entries->push([
                        'date' => $refund->refunded_on->copy(),
                        'order' => 3,
                        'description' => __('Refund: :reason', ['reason' => $refund->reason]),
                        'reference' => (string) $payment->receipt_number,
                        'debit' => (int) $refund->amount,
                        'credit' => 0,
                    ]);
                }
            }
        }

        $balance = 0;

        return $entries
            ->sortBy([['date', 'asc'], ['order', 'asc']])
            ->values()
            ->map(function (array $entry) use (&$balance): array {
                $balance += $entry['debit'] - $entry['credit'];
                unset($entry['order']);

                return [...$entry, 'balance' => $balance];
            })
            ->all();
    }

    /**
     * What each student owes, by how long it is past due, with how the family pays and when they were last
     * reminded. Only the students who owe something are listed, those who owe most first.
     *
     * @return Collection<int, array{admission: Admission, buckets: array<string, int>, total: int, oldestDays: int, habit: PaymentHabit, lastReminder: ?Carbon}>
     */
    public function aging(?User $user, ?int $sectionId = null, ?Carbon $asOf = null): Collection
    {
        $today = ($asOf ?? now())->copy()->startOfDay();

        $vouchers = FeeVoucher::query()
            ->visibleTo($user)
            ->where('status', '!=', VoucherStatus::Paid)
            ->when($sectionId !== null, fn ($query) => $query->where('section_id', $sectionId))
            ->with(['admission.student', 'admission.section.schoolClass', 'installments'])
            ->get()
            ->filter(fn (FeeVoucher $voucher): bool => $voucher->balance > 0);

        $habits = $this->paymentHabits($vouchers->pluck('admission_id')->unique()->all(), $today);
        $reminders = ParentMessage::query()
            ->where('about_type', (new Admission)->getMorphClass())
            ->whereIn('about_id', $vouchers->pluck('admission_id')->unique())
            ->selectRaw('about_id, max(created_at) as last_sent')
            ->groupBy('about_id')
            ->pluck('last_sent', 'about_id');

        return $vouchers
            ->groupBy('admission_id')
            ->map(function (Collection $vouchers, int $admissionId) use ($today, $habits, $reminders): array {
                $buckets = array_fill_keys(array_keys(self::BUCKETS), 0);
                $oldest = PHP_INT_MIN;

                foreach ($vouchers as $voucher) {
                    foreach ($this->dueParts($voucher) as [$amount, $dueDate]) {
                        $days = (int) $dueDate->diffInDays($today, false);
                        $buckets[$this->bucketFor($days)] += $amount;
                        $oldest = max($oldest, $days);
                    }
                }

                return [
                    'admission' => $vouchers->first()->admission,
                    'buckets' => $buckets,
                    'total' => array_sum($buckets),
                    'oldestDays' => $oldest,
                    'habit' => $habits[$admissionId] ?? PaymentHabit::New,
                    'lastReminder' => isset($reminders[$admissionId]) ? Carbon::parse($reminders[$admissionId]) : null,
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    /**
     * How the families of the admissions pay, from the vouchers due in the six months before the date:
     * - a defaulter still owes on a voucher more than 60 days past due;
     * - otherwise, the share of vouchers paid in full by their due date: 90% or more pays on time, 60% or more is
     *   sometimes late, less is often late;
     * - with no voucher due yet, there is no history.
     *
     * @param  list<int>  $admissionIds
     * @return array<int, PaymentHabit>
     */
    public function paymentHabits(array $admissionIds, ?Carbon $asOf = null): array
    {
        $today = ($asOf ?? now())->copy()->startOfDay();

        return FeeVoucher::query()
            ->whereIn('admission_id', $admissionIds)
            ->whereDate('due_date', '<', $today->toDateString())
            ->whereDate('due_date', '>=', $today->copy()->subMonths(6)->toDateString())
            ->with('payments')
            ->get()
            ->groupBy('admission_id')
            ->map(function (Collection $vouchers) use ($today): PaymentHabit {
                if ($vouchers->contains(fn (FeeVoucher $voucher): bool => $voucher->balance > 0 && $voucher->due_date->diffInDays($today) > 60)) {
                    return PaymentHabit::Defaulter;
                }

                $onTime = $vouchers->filter(fn (FeeVoucher $voucher): bool => $voucher->status === VoucherStatus::Paid
                    && $voucher->payments->max('paid_on')?->lessThanOrEqualTo($voucher->due_date))->count();
                $share = $onTime / $vouchers->count();

                return match (true) {
                    $share >= 0.9 => PaymentHabit::OnTime,
                    $share >= 0.6 => PaymentHabit::SometimesLate,
                    default => PaymentHabit::OftenLate,
                };
            })
            ->all();
    }

    /**
     * The WhatsApp reminder of what the student owes.
     *
     * @param  array{admission: Admission, total: int, oldestDays: int}  $row
     */
    public function reminder(array $row): string
    {
        $admission = $row['admission'];

        $lines = [
            __('Dear parent,'),
            __('This is a reminder that the fee of :student (:section) of :amount is outstanding.', [
                'student' => $admission->student->name,
                'section' => $admission->section?->full_name,
                'amount' => FeeService::money($row['total']),
            ]),
        ];

        if ($row['oldestDays'] > 0) {
            $lines[] = __('The oldest amount is :days days past its due date.', ['days' => $row['oldestDays']]);
        }

        $lines[] = __('Please pay at the school office at your earliest convenience.');
        $lines[] = (string) config('campusflow.company_name');

        return implode("\n\n", $lines);
    }

    /**
     * The unpaid parts of the voucher with their due dates: the unpaid installments, or the whole balance at the
     * due date of the voucher.
     *
     * @return list<array{0: int, 1: Carbon}>
     */
    private function dueParts(FeeVoucher $voucher): array
    {
        if ($voucher->installments->isEmpty()) {
            return [[$voucher->balance, $voucher->due_date->copy()]];
        }

        return $this->accounts->installmentStatus($voucher)
            ->filter(fn (array $part): bool => $part['balance'] > 0)
            ->map(fn (array $part): array => [$part['balance'], $part['installment']->due_date->copy()])
            ->values()
            ->all();
    }

    private function bucketFor(int $daysPastDue): string
    {
        foreach (self::BUCKETS as $key => [$from, $to]) {
            if ($daysPastDue >= $from && $daysPastDue <= $to) {
                return $key;
            }
        }

        return 'over_90';
    }
}
