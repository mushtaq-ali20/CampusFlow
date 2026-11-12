<?php

namespace App\Services;

use App\Enums\BillStatus;
use App\Enums\PaymentMethod;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorPayment;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * What the school owes its vendors: bills, the payments made against them, and how old the unpaid amounts are.
 * Amounts are whole rupees.
 */
class PayableService
{
    /**
     * Records a bill of a vendor.
     *
     * @param  array{campus_id: int, vendor_id: int, expense_category_id?: ?int, bill_number?: ?string, bill_date: string, due_date: string, amount: int|string, description?: ?string}  $data
     *
     * @throws DomainException
     */
    public function recordBill(array $data, User $by): VendorBill
    {
        if ((int) $data['amount'] <= 0) {
            throw new DomainException(__('The amount must be more than zero.'));
        }

        if (CarbonImmutable::parse($data['due_date'])->lt(CarbonImmutable::parse($data['bill_date']))) {
            throw new DomainException(__('The due date cannot be before the date of the bill.'));
        }

        if (filled($data['bill_number'] ?? null) && VendorBill::query()->where('vendor_id', $data['vendor_id'])->where('bill_number', $data['bill_number'])->where('status', '!=', BillStatus::Cancelled)->exists()) {
            throw new DomainException(__('The bill :number of this vendor has been recorded already.', ['number' => $data['bill_number']]));
        }

        return VendorBill::query()->create([
            'campus_id' => $data['campus_id'],
            'vendor_id' => $data['vendor_id'],
            'expense_category_id' => $data['expense_category_id'] ?? null,
            'reference' => $this->nextNumber('BILL', VendorBill::class),
            'bill_number' => $data['bill_number'] ?? null,
            'bill_date' => $data['bill_date'],
            'due_date' => $data['due_date'],
            'amount' => (int) $data['amount'],
            'paid_amount' => 0,
            'status' => BillStatus::Unpaid,
            'description' => $data['description'] ?? null,
            'recorded_by' => $by->id,
        ]);
    }

    /**
     * Pays a bill, in full or in part.
     *
     * @throws DomainException
     */
    public function pay(VendorBill $bill, int $amount, PaymentMethod $method, CarbonInterface $on, User $by, ?string $reference = null): VendorPayment
    {
        if ($bill->status === BillStatus::Cancelled) {
            throw new DomainException(__('This bill was cancelled.'));
        }

        if ($amount <= 0) {
            throw new DomainException(__('The amount must be more than zero.'));
        }

        if ($amount > $bill->balance) {
            throw new DomainException(__('Only :amount is still owed on this bill.', ['amount' => FeeService::money($bill->balance)]));
        }

        return DB::transaction(function () use ($bill, $amount, $method, $on, $by, $reference): VendorPayment {
            $payment = $bill->payments()->create([
                'campus_id' => $bill->campus_id,
                'voucher_number' => $this->nextNumber('PV', VendorPayment::class),
                'amount' => $amount,
                'paid_on' => $on->toDateString(),
                'method' => $method,
                'reference' => $reference,
                'paid_by' => $by->id,
            ]);

            $this->recalculate($bill);

            return $payment;
        });
    }

    /**
     * Cancels a bill recorded by mistake. A bill with payments cannot be cancelled.
     *
     * @throws DomainException
     */
    public function cancel(VendorBill $bill): VendorBill
    {
        if ($bill->payments()->exists()) {
            throw new DomainException(__('This bill has payments. It cannot be cancelled.'));
        }

        $bill->update(['status' => BillStatus::Cancelled]);

        return $bill;
    }

    public function recalculate(VendorBill $bill): VendorBill
    {
        $paid = (int) $bill->payments()->sum('amount');

        $bill->update([
            'paid_amount' => $paid,
            'status' => match (true) {
                $bill->status === BillStatus::Cancelled => BillStatus::Cancelled,
                $paid >= $bill->amount => BillStatus::Paid,
                $paid > 0 => BillStatus::Partial,
                default => BillStatus::Unpaid,
            },
        ]);

        return $bill->refresh();
    }

    /**
     * What is owed to each vendor, split by how long the bills are overdue.
     *
     * @return list<array{vendor: Vendor, bills: int, current: int, days_30: int, days_60: int, days_90: int, over_90: int, total: int}>
     */
    public function aging(?User $user = null, ?CarbonInterface $asOf = null): array
    {
        $asOf = CarbonImmutable::parse($asOf ?? now())->startOfDay();

        return VendorBill::query()
            ->visibleTo($user)
            ->outstanding()
            ->with('vendor')
            ->get()
            ->groupBy('vendor_id')
            ->map(function ($bills) use ($asOf): array {
                $row = ['vendor' => $bills->first()->vendor, 'bills' => $bills->count(), 'current' => 0, 'days_30' => 0, 'days_60' => 0, 'days_90' => 0, 'over_90' => 0, 'total' => 0];

                foreach ($bills as $bill) {
                    $overdue = $bill->due_date->lt($asOf) ? (int) $bill->due_date->diffInDays($asOf) : 0;
                    $bucket = match (true) {
                        $overdue === 0 => 'current',
                        $overdue <= 30 => 'days_30',
                        $overdue <= 60 => 'days_60',
                        $overdue <= 90 => 'days_90',
                        default => 'over_90',
                    };

                    $row[$bucket] += $bill->balance;
                    $row['total'] += $bill->balance;
                }

                return $row;
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * The totals of the bills the user sees.
     *
     * @return array{owed: int, overdue: int, dueThisWeek: int, paidThisMonth: int}
     */
    public function summary(?User $user = null, ?CarbonInterface $asOf = null): array
    {
        $asOf = CarbonImmutable::parse($asOf ?? now())->startOfDay();
        $outstanding = VendorBill::query()->visibleTo($user)->outstanding()->get();

        return [
            'owed' => (int) $outstanding->sum('balance'),
            'overdue' => (int) $outstanding->filter(fn (VendorBill $bill): bool => $bill->due_date->lt($asOf))->sum('balance'),
            'dueThisWeek' => (int) $outstanding->filter(fn (VendorBill $bill): bool => $bill->due_date->betweenIncluded($asOf, $asOf->addDays(7)))->sum('balance'),
            'paidThisMonth' => (int) VendorPayment::query()->visibleTo($user)->whereBetween('paid_on', [$asOf->startOfMonth()->toDateString(), $asOf->endOfMonth()->toDateString()])->sum('amount'),
        ];
    }

    /**
     * The bills and payments of a vendor in the order they happened, with the running balance owed.
     *
     * @return list<array{date: CarbonInterface, type: string, reference: string, description: ?string, bill: int, payment: int, balance: int}>
     */
    public function ledger(Vendor $vendor, ?User $user = null): array
    {
        $bills = VendorBill::query()->visibleTo($user)->where('vendor_id', $vendor->id)->where('status', '!=', BillStatus::Cancelled)->with('payments')->get();

        $entries = collect();

        foreach ($bills as $bill) {
            $entries->push(['date' => $bill->bill_date, 'order' => 0, 'type' => 'bill', 'reference' => $bill->reference, 'description' => $bill->description, 'bill' => $bill->amount, 'payment' => 0]);

            foreach ($bill->payments as $payment) {
                $entries->push(['date' => $payment->paid_on, 'order' => 1, 'type' => 'payment', 'reference' => $payment->voucher_number, 'description' => $bill->reference, 'bill' => 0, 'payment' => $payment->amount]);
            }
        }

        $balance = 0;

        return $entries
            ->sortBy([['date', 'asc'], ['order', 'asc']])
            ->values()
            ->map(function (array $entry) use (&$balance): array {
                $balance += $entry['bill'] - $entry['payment'];
                unset($entry['order']);

                return [...$entry, 'balance' => $balance];
            })
            ->all();
    }

    /**
     * @param  class-string<VendorBill|VendorPayment>  $model
     */
    private function nextNumber(string $prefix, string $model): string
    {
        return sprintf('%s-%06d', $prefix, (int) $model::query()->max('id') + 1);
    }
}
