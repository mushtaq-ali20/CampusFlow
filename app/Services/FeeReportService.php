<?php

namespace App\Services;

use App\Models\FeeVoucher;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fee figures for the dashboard: what was billed, collected and is still due, by branch, class and section,
 * and the students who have not paid by the due date. Staff attached to a branch only get the figures of
 * their branch.
 *
 * The summary and defaulters queries return one row per group, as a sub-query aliased fee_vouchers, so that
 * a table can page, sort and filter them like any other model query.
 */
class FeeReportService
{
    public const LEVEL_BRANCH = 'branch';

    public const LEVEL_CLASS = 'class';

    public const LEVEL_SECTION = 'section';

    /**
     * The vouchers matching the filters.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<FeeVoucher>
     */
    public function vouchers(array $filters, ?User $user): Builder
    {
        return FeeVoucher::query()
            ->visibleTo($user)
            ->when(filled($filters['campus_id'] ?? null), fn (Builder $query) => $query->where('campus_id', $filters['campus_id']))
            ->when(filled($filters['school_class_id'] ?? null), fn (Builder $query) => $query->where('school_class_id', $filters['school_class_id']))
            ->when(filled($filters['section_id'] ?? null), fn (Builder $query) => $query->where('section_id', $filters['section_id']))
            ->when(filled($filters['year_id'] ?? null), fn (Builder $query) => $query->where('year_id', $filters['year_id']))
            ->when(filled($filters['month'] ?? null), fn (Builder $query) => $query
                ->whereYear('month', substr((string) $filters['month'], 0, 4))
                ->whereMonth('month', substr((string) $filters['month'], 5, 2)));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<FeeVoucher>
     */
    private function overdue(array $filters, ?User $user): Builder
    {
        return $this->vouchers($filters, $user)
            ->whereColumn('paid_amount', '<', 'net_amount')
            ->whereDate('due_date', '<', now()->toDateString());
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{billed: int, collected: int, dues: int, overdue: int, rate: float, vouchers: int, unpaid: int, defaulters: int}
     */
    public function totals(array $filters, ?User $user): array
    {
        $totals = $this->vouchers($filters, $user)
            ->selectRaw('COALESCE(SUM(net_amount), 0) as billed, COALESCE(SUM(paid_amount), 0) as collected, COUNT(*) as vouchers')
            ->first();

        $overdue = (int) $this->overdue($filters, $user)
            ->selectRaw('COALESCE(SUM(net_amount - paid_amount), 0) as overdue')
            ->value('overdue');

        $unpaid = $this->vouchers($filters, $user)->whereColumn('paid_amount', '<', 'net_amount')->count();
        $defaulters = $this->overdue($filters, $user)->distinct()->count('admission_id');
        $billed = (int) $totals->billed;
        $collected = (int) $totals->collected;

        return [
            'billed' => $billed,
            'collected' => $collected,
            'dues' => max(0, $billed - $collected),
            'overdue' => max(0, $overdue),
            'rate' => $billed > 0 ? round($collected / $billed * 100, 1) : 0.0,
            'vouchers' => (int) $totals->vouchers,
            'unpaid' => $unpaid,
            'defaulters' => $defaulters,
        ];
    }

    /**
     * Billed, collected and dues by branch, class or section, one row per group.
     * Columns: vouchers, billed, collected, dues, rate (percentage collected).
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<FeeVoucher>
     */
    public function summary(string $level, array $filters, ?User $user): Builder
    {
        $columns = match ($level) {
            self::LEVEL_CLASS => ['school_class_id'],
            self::LEVEL_SECTION => ['campus_id', 'school_class_id', 'section_id'],
            default => ['campus_id'],
        };

        $rows = $this->vouchers($filters, $user)
            ->select($columns)
            ->selectRaw('MIN(fee_vouchers.id) as id, COUNT(*) as vouchers, SUM(net_amount) as billed, SUM(paid_amount) as collected, SUM(net_amount - paid_amount) as dues, ROUND(SUM(paid_amount) * 100.0 / NULLIF(SUM(net_amount), 0), 1) as rate')
            ->groupBy($columns);

        return FeeVoucher::query()->fromSub($rows, 'fee_vouchers');
    }

    /**
     * The students with fees not paid by the due date, one row per student.
     * Columns: pending_months (overdue vouchers), dues, oldest_due.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<FeeVoucher>
     */
    public function defaulters(array $filters, ?User $user): Builder
    {
        $columns = ['admission_id', 'campus_id', 'school_class_id', 'section_id'];

        $rows = $this->overdue($filters, $user)
            ->select($columns)
            ->selectRaw('MIN(fee_vouchers.id) as id, COUNT(*) as pending_months, SUM(net_amount - paid_amount) as dues, MIN(due_date) as oldest_due')
            ->groupBy($columns);

        return FeeVoucher::query()->fromSub($rows, 'fee_vouchers');
    }
}
