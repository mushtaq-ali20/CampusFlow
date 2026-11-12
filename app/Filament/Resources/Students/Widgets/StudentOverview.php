<?php

namespace App\Filament\Resources\Students\Widgets;

use App\Models\Student;
use App\Services\FeeService;
use App\Services\StudentOverviewService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

/**
 * The student at a glance, at the top of their profile: class, attendance, latest exam, fee balance and behaviour.
 */
class StudentOverview extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected static bool $isLazy = false;

    protected int|array|null $columns = ['default' => 1, 'sm' => 2, 'lg' => 5];

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        if (! $this->record instanceof Student) {
            return [];
        }

        $summary = app(StudentOverviewService::class)->summary($this->record, auth()->user());
        $admission = $summary['admission'];
        $attendance = $summary['attendance'];
        $exam = $summary['exam'];
        $balance = $summary['feeBalance'];
        $points = $summary['disciplinePoints'];

        return [
            Stat::make(__('Class'), $admission?->section?->full_name ?? __('Not admitted'))
                ->description($admission ? $admission->campus->name.' · '.$admission->admission_number : null),
            Stat::make(__('Attendance this year'), $attendance['percentage'] !== null ? $attendance['percentage'].'%' : '—')
                ->description(__(':attended of :total days', ['attended' => $attendance['attended'], 'total' => $attendance['total']]))
                ->color(match (true) {
                    $attendance['percentage'] === null => 'gray',
                    $attendance['percentage'] >= 90 => 'success',
                    $attendance['percentage'] >= 75 => 'warning',
                    default => 'danger',
                }),
            Stat::make(__('Latest exam'), $exam['percentage'] !== null ? $exam['percentage'].'%' : '—')
                ->description($exam['exam']?->getLabel() ?? __('No marks yet')),
            Stat::make(__('Fee balance'), FeeService::money($balance))
                ->description($balance > 0 ? __('Due') : __('Nothing due'))
                ->color($balance > 0 ? 'danger' : 'success'),
            Stat::make(__('Behaviour points'), sprintf('%+d', $points))
                ->description(__('This year'))
                ->color($points < 0 ? 'danger' : 'success'),
        ];
    }
}
