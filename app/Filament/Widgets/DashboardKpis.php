<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\FeeAging;
use App\Filament\Resources\FeeVouchers\FeeVoucherResource;
use App\Models\Year;
use App\Services\FeeReportService;
use App\Services\FeeService;
use App\Services\SchoolOverviewService;
use Filament\Widgets\Widget;

/**
 * The row of numbers at the top of the dashboard: today's attendance and, for staff who see the fees, this month's
 * fees, the year's fees and what is overdue. Staff who do not see the fees get students, teachers and classes.
 * Every card has the same parts (label, number, bar, one line), so the row lines up whatever the numbers are.
 */
class DashboardKpis extends Widget
{
    protected static bool $isLazy = false;

    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.dashboard-kpis';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        return ($user?->can('school.view') ?? false) || ($user?->can('fees.view') ?? false);
    }

    /**
     * @return array{cards: list<array<string, mixed>>}
     */
    protected function getViewData(): array
    {
        $user = auth()->user();
        $overview = app(SchoolOverviewService::class);
        $seesSchool = $user?->can('school.view') ?? false;
        $seesFees = $user?->can('fees.view') ?? false;
        $cards = [];

        if ($seesSchool) {
            $attendance = $overview->attendance($user);
            $total = max(1, $attendance['total']);

            $cards[] = [
                'label' => __('Attendance'),
                'corner' => $attendance['date'] === null ? __('Not marked yet') : ($attendance['isToday'] ? __('Today') : $attendance['date']->translatedFormat('D, j M')),
                'value' => $attendance['rate'],
                'decimals' => 0,
                'prefix' => '',
                'suffix' => '%',
                'segments' => [
                    ['width' => $attendance['present'] / $total * 100, 'class' => 'bg-emerald-500'],
                    ['width' => $attendance['late'] / $total * 100, 'class' => 'bg-amber-500'],
                    ['width' => $attendance['absent'] / $total * 100, 'class' => 'bg-rose-600'],
                    ['width' => $attendance['leave'] / $total * 100, 'class' => 'bg-sky-400'],
                ],
                'note' => __(':present present · :late late · :absent absent · :leave leave', [
                    'present' => $attendance['present'],
                    'late' => $attendance['late'],
                    'absent' => $attendance['absent'],
                    'leave' => $attendance['leave'],
                ]),
                'url' => null,
            ];
        }

        if ($seesFees) {
            $reports = app(FeeReportService::class);
            $filters = ['year_id' => Year::current()?->id, 'campus_id' => $user?->activeBranchId()];
            $month = $reports->totals([...$filters, 'month' => now()->startOfMonth()->toDateString()], $user);
            $year = $reports->totals($filters, $user);

            $cards[] = $this->feeCard(
                __(':month fees', ['month' => now()->translatedFormat('F')]),
                $month,
                __('collected of :billed billed', ['billed' => FeeService::money($month['billed'])]),
                FeeVoucherResource::canAccess() ? FeeVoucherResource::getUrl() : null,
            );
            $cards[] = $this->feeCard(
                __('Fees this year'),
                $year,
                __('collected of :billed billed', ['billed' => FeeService::money($year['billed'])]),
                null,
            );
            $cards[] = [
                ...$this->money($year['overdue']),
                'label' => __('Overdue fees'),
                'corner' => trans_choice(':count defaulter|:count defaulters', $year['defaulters'], ['count' => number_format($year['defaulters'])]),
                'segments' => [['width' => $year['billed'] > 0 ? $year['overdue'] / $year['billed'] * 100 : 0, 'class' => 'bg-rose-600']],
                'note' => __('Past the due date'),
                'url' => FeeAging::canAccess() ? FeeAging::getUrl() : null,
            ];
        }

        if ($seesSchool && ! $seesFees) {
            $counts = $overview->counts($user);

            $cards[] = $this->countCard(__('Students'), $counts['students'], trans_choice('in :count section|in :count sections', $counts['sections'], ['count' => $counts['sections']]));
            $cards[] = $this->countCard(__('Teachers'), $counts['teachers'], __('Subject teachers'));
            $cards[] = $this->countCard(__('Classes'), $counts['classes'], $counts['firstClass'] !== null ? $counts['firstClass'].' – '.$counts['lastClass'] : '');
        }

        return ['cards' => $cards];
    }

    /**
     * @param  array{billed: int, collected: int, rate: float}  $totals
     * @return array<string, mixed>
     */
    private function feeCard(string $label, array $totals, string $note, ?string $url): array
    {
        return [
            ...$this->money($totals['collected']),
            'label' => $label,
            'corner' => __(':rate% collected', ['rate' => $totals['rate']]),
            'segments' => [['width' => $totals['rate'], 'class' => 'bg-(--primary-600) dark:bg-(--primary-400)']],
            'note' => $note,
            'url' => $url,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function countCard(string $label, int $value, string $note): array
    {
        return [
            'label' => $label,
            'corner' => '',
            'value' => $value,
            'decimals' => 0,
            'prefix' => '',
            'suffix' => '',
            'segments' => [],
            'note' => $note,
            'url' => null,
        ];
    }

    /**
     * An amount of money, with the currency symbol on the side the settings put it.
     *
     * @return array{value: int, decimals: int, prefix: string, suffix: string}
     */
    private function money(int $amount): array
    {
        $symbol = (string) config('campusflow.currency_symbol');
        $after = config('campusflow.currency_position') === 'after';

        return [
            'value' => $amount,
            'decimals' => 0,
            'prefix' => $after ? '' : $symbol.' ',
            'suffix' => $after ? ' '.$symbol : '',
        ];
    }
}
