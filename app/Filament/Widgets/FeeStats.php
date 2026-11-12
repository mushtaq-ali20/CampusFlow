<?php

namespace App\Filament\Widgets;

use App\Services\FeeReportService;
use App\Services\FeeService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Total fee billed, collected and due for the dashboard filters.
 */
class FeeStats extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('fees.view') ?? false;
    }

    protected function getStats(): array
    {
        $totals = app(FeeReportService::class)->totals($this->pageFilters ?? [], auth()->user());

        return [
            Stat::make(__('Total fee billed'), FeeService::money($totals['billed']))
                ->description(trans_choice(':count voucher|:count vouchers', $totals['vouchers'], ['count' => $totals['vouchers']]))
                ->icon('heroicon-o-document-text')
                ->color('primary'),
            Stat::make(__('Fee collected'), FeeService::money($totals['collected']))
                ->description($totals['rate'].' % '.__('collected'))
                ->icon('heroicon-o-banknotes')
                ->color('success'),
            Stat::make(__('Dues'), FeeService::money($totals['dues']))
                ->description(trans_choice(':count voucher unpaid|:count vouchers unpaid', $totals['unpaid'], ['count' => $totals['unpaid']]))
                ->icon('heroicon-o-exclamation-circle')
                ->color('danger'),
            Stat::make(__('Overdue'), FeeService::money($totals['overdue']))
                ->description(__('Past the due date'))
                ->icon('heroicon-o-clock')
                ->color('warning'),
            Stat::make(__('Fee defaulters'), number_format($totals['defaulters']))
                ->description(__('Students with overdue fees'))
                ->icon('heroicon-o-user-minus')
                ->color('danger'),
        ];
    }
}
