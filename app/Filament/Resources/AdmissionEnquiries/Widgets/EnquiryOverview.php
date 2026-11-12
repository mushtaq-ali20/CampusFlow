<?php

namespace App\Filament\Resources\AdmissionEnquiries\Widgets;

use App\Models\Year;
use App\Services\AdmissionEnquiryService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The admission funnel of the current academic year at a glance.
 */
class EnquiryOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected int|array|null $columns = ['default' => 2, 'lg' => 5];

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $service = app(AdmissionEnquiryService::class);
        $year = Year::current();
        $summary = $service->summary(auth()->user(), $year);
        $seatPlan = $service->seatPlan(auth()->user(), $year);
        $seatsLeft = $seatPlan->every(fn (array $row): bool => $row['seatsLeft'] !== null) ? $seatPlan->sum('seatsLeft') : null;

        return [
            Stat::make(__('Enquiries'), $summary['enquiries'])->description($year?->name),
            Stat::make(__('Registered'), $summary['registered']),
            Stat::make(__('Admitted'), $summary['admitted'])->color('success'),
            Stat::make(__('Enquiry to admission'), $summary['conversion'] !== null ? $summary['conversion'].'%' : '—'),
            Stat::make(__('Seats left'), $seatsLeft ?? '—')
                ->description($seatsLeft === null ? __('Some sections have no capacity set') : null),
        ];
    }
}
