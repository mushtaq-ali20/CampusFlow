<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\RequestStatus;
use App\Enums\VoucherItemKind;
use App\Enums\VoucherStatus;
use App\Models\FeeAdjustment;
use App\Models\FeeInstallment;
use App\Models\FeePayment;
use App\Models\FeeRefund;
use App\Models\FeeVoucher;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Installments, adjustments and refunds of the fee vouchers.
 *
 * - An installment plan splits the balance of a voucher into 2 to 6 parts with their own due dates. The voucher is
 *   then due on the date of its last part, so the late fine and the defaulters list follow the plan; the aging
 *   report follows each part.
 * - An adjustment charges more on a voucher or reduces it. Fee staff request it, staff who manage the fees approve
 *   it, and it then becomes a fee line (an increase) or a discount line (a reduction) of the voucher, so the voucher
 *   totals are worked out as before.
 * - A refund gives back money from a payment. Fee staff request it, staff who manage the fees approve it. The
 *   payment and its receipt are kept as they are; the refund shows in the student ledger.
 */
class FeeAccountService
{
    public const MAX_INSTALLMENTS = 6;

    public function __construct(private readonly FeeService $fees) {}

    /**
     * Splits the balance of the voucher into parts, replacing a plan it had.
     *
     * @param  list<array{amount: int|string, due_date: string|CarbonInterface}>  $parts
     */
    public function planInstallments(FeeVoucher $voucher, array $parts): FeeVoucher
    {
        if ($voucher->status === VoucherStatus::Paid) {
            throw new DomainException(__('This voucher is already paid.'));
        }

        if (count($parts) < 2 || count($parts) > self::MAX_INSTALLMENTS) {
            throw new DomainException(__('A plan has 2 to :max installments.', ['max' => self::MAX_INSTALLMENTS]));
        }

        $parts = collect($parts)->map(fn (array $part): array => ['amount' => (int) $part['amount'], 'due_date' => Carbon::parse($part['due_date'])->startOfDay()]);

        if ($parts->contains(fn (array $part): bool => $part['amount'] <= 0)) {
            throw new DomainException(__('Each installment must be greater than zero.'));
        }

        if ($parts->sum('amount') !== $voucher->balance) {
            throw new DomainException(__('The installments must add up to the balance of :balance.', ['balance' => FeeService::money($voucher->balance)]));
        }

        if ($parts->pluck('due_date')->sliding(2)->contains(fn (Collection $pair): bool => ! $pair->last()->greaterThan($pair->first()))) {
            throw new DomainException(__('Each installment must be due after the one before.'));
        }

        return DB::transaction(function () use ($voucher, $parts): FeeVoucher {
            $voucher->installments()->delete();

            foreach ($parts->values() as $index => $part) {
                $voucher->installments()->create(['sequence' => $index + 1, 'amount' => $part['amount'], 'due_date' => $part['due_date']->toDateString()]);
            }

            $voucher->update(['due_date' => $parts->last()['due_date']->toDateString()]);

            return $voucher;
        });
    }

    /**
     * The installments of the voucher with what is paid on each: the payments made since the plan pay the parts
     * in order.
     *
     * @return Collection<int, array{installment: FeeInstallment, paid: int, balance: int}>
     */
    public function installmentStatus(FeeVoucher $voucher): Collection
    {
        $installments = $voucher->installments()->get();

        if ($installments->isEmpty()) {
            return collect();
        }

        $available = (int) $voucher->payments()->where('created_at', '>=', $installments->min('created_at'))->sum('amount');

        return $installments->map(function (FeeInstallment $installment) use (&$available): array {
            $paid = min($available, $installment->amount);
            $available -= $paid;

            return ['installment' => $installment, 'paid' => $paid, 'balance' => $installment->amount - $paid];
        });
    }

    /**
     * Asks to charge more on a voucher (a positive amount) or to reduce it (a negative amount).
     */
    public function requestAdjustment(FeeVoucher $voucher, int $amount, string $reason, ?User $user): FeeAdjustment
    {
        $this->checkAdjustment($voucher, $amount);

        return $voucher->adjustments()->create([
            'campus_id' => $voucher->campus_id,
            'amount' => $amount,
            'reason' => $reason,
            'status' => RequestStatus::Pending,
            'requested_by' => $user?->id,
        ]);
    }

    /**
     * Approves an adjustment: it becomes a fee or a discount line of the voucher, and the voucher is totalled again.
     */
    public function approveAdjustment(FeeAdjustment $adjustment, ?User $user): FeeAdjustment
    {
        $this->checkPending($adjustment->status);

        return DB::transaction(function () use ($adjustment, $user): FeeAdjustment {
            $voucher = FeeVoucher::query()->lockForUpdate()->findOrFail($adjustment->fee_voucher_id);
            $this->checkAdjustment($voucher, $adjustment->amount);

            $item = $voucher->items()->create([
                'fee_head_id' => null,
                'kind' => $adjustment->amount > 0 ? VoucherItemKind::Fee : VoucherItemKind::Discount,
                'description' => __('Adjustment: :reason', ['reason' => $adjustment->reason]),
                'amount' => abs($adjustment->amount),
            ]);

            $this->fees->recalculate($voucher);

            $adjustment->update([
                'status' => RequestStatus::Approved,
                'decided_by' => $user?->id,
                'decided_at' => now(),
                'fee_voucher_item_id' => $item->id,
            ]);

            return $adjustment;
        });
    }

    public function rejectAdjustment(FeeAdjustment $adjustment, ?User $user): FeeAdjustment
    {
        $this->checkPending($adjustment->status);
        $adjustment->update(['status' => RequestStatus::Rejected, 'decided_by' => $user?->id, 'decided_at' => now()]);

        return $adjustment;
    }

    /**
     * What can still be refunded from a payment: its amount less the refunds approved or waiting for approval.
     */
    public function refundable(FeePayment $payment): int
    {
        return max(0, $payment->amount - (int) $payment->refunds()->where('status', '!=', RequestStatus::Rejected)->sum('amount'));
    }

    public function requestRefund(FeePayment $payment, int $amount, string $reason, PaymentMethod $method, ?User $user): FeeRefund
    {
        if ($amount <= 0) {
            throw new DomainException(__('The amount must be greater than zero.'));
        }

        if ($amount > $this->refundable($payment)) {
            throw new DomainException(__('At most :amount can be refunded from this payment.', ['amount' => FeeService::money($this->refundable($payment))]));
        }

        return $payment->refunds()->create([
            'campus_id' => $payment->campus_id,
            'amount' => $amount,
            'reason' => $reason,
            'method' => $method,
            'status' => RequestStatus::Pending,
            'requested_by' => $user?->id,
        ]);
    }

    /**
     * Approves a refund, given back to the family on the date.
     */
    public function approveRefund(FeeRefund $refund, ?User $user, ?CarbonInterface $refundedOn = null): FeeRefund
    {
        $this->checkPending($refund->status);
        $refund->update([
            'status' => RequestStatus::Approved,
            'refunded_on' => ($refundedOn ?? now())->toDateString(),
            'decided_by' => $user?->id,
            'decided_at' => now(),
        ]);

        return $refund;
    }

    public function rejectRefund(FeeRefund $refund, ?User $user): FeeRefund
    {
        $this->checkPending($refund->status);
        $refund->update(['status' => RequestStatus::Rejected, 'decided_by' => $user?->id, 'decided_at' => now()]);

        return $refund;
    }

    private function checkAdjustment(FeeVoucher $voucher, int $amount): void
    {
        if ($amount === 0) {
            throw new DomainException(__('The adjustment cannot be zero.'));
        }

        if ($amount < 0 && abs($amount) > $voucher->balance) {
            throw new DomainException(__('A reduction cannot be more than the balance of :balance. Refund a payment instead.', ['balance' => FeeService::money($voucher->balance)]));
        }
    }

    private function checkPending(RequestStatus $status): void
    {
        if ($status !== RequestStatus::Pending) {
            throw new DomainException(__('This request was already decided.'));
        }
    }
}
