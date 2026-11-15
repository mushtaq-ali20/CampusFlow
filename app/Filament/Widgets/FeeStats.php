<?php

namespace App\Filament\Widgets;

use App\Services\FeeReportService;
use App\Services\FeeService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * Total fee billed, collected, due and overdue, and the defaulters, for the filters of the Fees widget. One strip of
 * equal columns, so five figures fill the width with no gap whatever the screen size.
 */
class FeeStats extends Widget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected string $view = 'filament.widgets.fee-stats';

    public static function canView(): bool
    {
        return auth()->user()?->can('fees.view') ?? false;
    }

    /**
     * @return array{stats: list<array{label: string, value: string, note: string, tone: string}>}
     */
    protected function getViewData(): array
    {
        $totals = app(FeeReportService::class)->totals($this->pageFilters ?? [], auth()->user());

        return [
            'stats' => [
                [
                    'label' => __('Total fee billed'),
                    'value' => FeeService::money($totals['billed']),
                    'note' => trans_choice(':count voucher|:count vouchers', $totals['vouchers'], ['count' => $totals['vouchers']]),
                    'tone' => 'gray',
                ],
                [
                    'label' => __('Fee collected'),
                    'value' => FeeService::money($totals['collected']),
                    'note' => $totals['rate'].' % '.__('collected'),
                    'tone' => 'success',
                ],
                [
                    'label' => __('Dues'),
                    'value' => FeeService::money($totals['dues']),
                    'note' => trans_choice(':count voucher unpaid|:count vouchers unpaid', $totals['unpaid'], ['count' => $totals['unpaid']]),
                    'tone' => 'gray',
                ],
                [
                    'label' => __('Overdue'),
                    'value' => FeeService::money($totals['overdue']),
                    'note' => __('Past the due date'),
                    'tone' => 'danger',
                ],
                [
                    'label' => __('Fee defaulters'),
                    'value' => number_format($totals['defaulters']),
                    'note' => __('Students with overdue fees'),
                    'tone' => 'danger',
                ],
            ],
        ];
    }
}
