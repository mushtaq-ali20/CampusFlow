<?php

namespace App\Filament\Widgets;

use App\Filament\Actions\ReconfigureTimetableAction;
use App\Filament\Concerns\FiltersTimetableByDashboard;
use App\Models\TimetableEntry;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The timetable as a month calendar for the dashboard filters: each date shows the lessons of its weekday.
 * Clicking a lesson asks whether to update the timetable and leads to its configuration screen.
 */
class TimetableMonthly extends Widget implements HasActions, HasSchemas
{
    use FiltersTimetableByDashboard, InteractsWithSchemas {
        InteractsWithSchemas::__get insteadof FiltersTimetableByDashboard;
    }
    use InteractsWithActions;

    public const LESSONS_PER_DAY = 4;

    protected static bool $isLazy = false;

    protected static ?int $sort = 8;

    protected string $view = 'filament.widgets.timetable-monthly';

    protected int|string|array $columnSpan = 'full';

    public string $month = '';

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function previousMonth(): void
    {
        $this->month = Carbon::parse($this->month.'-01')->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = Carbon::parse($this->month.'-01')->addMonth()->format('Y-m');
    }

    public function currentMonth(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function reconfigureAction(): Action
    {
        return ReconfigureTimetableAction::make('reconfigure');
    }

    public function monthLabel(): string
    {
        return Carbon::parse($this->month.'-01')->translatedFormat('F Y');
    }

    /**
     * The weeks of the month, Monday to Sunday, with the lessons of each date.
     *
     * @return list<list<array{date: Carbon, inMonth: bool, isToday: bool, lessons: Collection<int, TimetableEntry>, more: int}>>
     */
    public function weeks(): array
    {
        $first = Carbon::parse($this->month.'-01')->startOfDay();
        $date = $first->copy()->startOfWeek(Carbon::MONDAY);
        $end = $first->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $byDay = $this->lessons()
            ->with(['period', 'subject', 'teacher', 'section.schoolClass'])
            ->get()
            ->sortBy(fn (TimetableEntry $entry): int => $entry->period->order)
            ->groupBy('day_of_week');

        $weeks = [];

        while ($date->lessThanOrEqualTo($end)) {
            $week = [];

            for ($i = 0; $i < 7; $i++) {
                $lessons = $date->isSunday() ? collect() : $byDay->get($date->dayOfWeekIso, collect())->values();

                $week[] = [
                    'date' => $date->copy(),
                    'inMonth' => $date->month === $first->month,
                    'isToday' => $date->isToday(),
                    'lessons' => $lessons->take(self::LESSONS_PER_DAY),
                    'more' => max(0, $lessons->count() - self::LESSONS_PER_DAY),
                ];

                $date->addDay();
            }

            $weeks[] = $week;
        }

        return $weeks;
    }
}
