<?php

namespace App\Filament\Widgets;

use App\Enums\Shift;
use App\Filament\Actions\ReconfigureTimetableAction;
use App\Filament\Concerns\FiltersTimetableByDashboard;
use App\Filament\Pages\TimetableManager;
use App\Models\Section;
use App\Models\Teacher;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The weekly timetable as a grid: the periods of the day down, Monday to Saturday across, each lesson coloured
 * by its subject. It shows the teacher chosen in the filters, else the section chosen in the filters, else the
 * section picked in the widget among those the filters leave. Clicking a lesson asks whether to update the
 * timetable and leads to its configuration screen; clicking a free period leads there directly.
 */
class TimetableWeekly extends Widget implements HasActions, HasSchemas
{
    use FiltersTimetableByDashboard, InteractsWithSchemas {
        InteractsWithSchemas::__get insteadof FiltersTimetableByDashboard;
    }
    use InteractsWithActions;

    public const DAYS = [1, 2, 3, 4, 5, 6];

    protected static bool $isLazy = false;

    protected static ?int $sort = 7;

    protected string $view = 'filament.widgets.timetable-weekly';

    protected int|string|array $columnSpan = 'full';

    public ?int $sectionId = null;

    public function reconfigureAction(): Action
    {
        return ReconfigureTimetableAction::make('reconfigure');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $filters = $this->pageFilters ?? [];
        $teacher = filled($filters['teacher_id'] ?? null) ? Teacher::query()->find($filters['teacher_id']) : null;
        $sectionOptions = $teacher === null ? $this->sectionOptions() : collect();
        $section = null;

        if ($teacher === null) {
            $sectionId = filled($filters['section_id'] ?? null) ? (int) $filters['section_id'] : $this->sectionId;
            $sectionId = $sectionOptions->has($sectionId) ? $sectionId : $sectionOptions->keys()->first();
            $section = $sectionId === null ? null : Section::query()->with(['schoolClass', 'campus'])->find($sectionId);
        }

        $lessons = $teacher === null && $section === null
            ? collect()
            : $this->lessons()
                ->when($section !== null, fn (Builder $query) => $query->where('section_id', $section->id))
                ->with(['subject', 'teacher', 'section.schoolClass', 'section.campus'])
                ->get();

        $periods = TimetablePeriod::query()->ordered()->get();
        $shifts = $this->shiftsToShow($periods, $lessons, $section);
        $canManage = TimetableManager::canAccess();

        return [
            'teacher' => $teacher,
            'section' => $section,
            'sectionOptions' => $sectionOptions,
            'canPickSection' => $teacher === null && blank($filters['section_id'] ?? null) && $sectionOptions->count() > 1,
            'periodsByShift' => $periods
                ->filter(fn (TimetablePeriod $period): bool => in_array($period->shift, $shifts, true))
                ->groupBy(fn (TimetablePeriod $period): string => $period->shift->value)
                ->sortBy(fn (Collection $shiftPeriods, string $shift): int => array_search(Shift::from($shift), Shift::cases(), true)),
            'missingShifts' => $teacher === null ? [] : array_values(array_filter(Shift::cases(), fn (Shift $shift): bool => ! in_array($shift, $shifts, true))),
            'days' => self::DAYS,
            'lessonsBySlot' => $lessons->groupBy(fn (TimetableEntry $entry): string => $entry->timetable_period_id.'-'.$entry->day_of_week),
            'hasLessons' => $lessons->isNotEmpty(),
            'manageUrl' => ! $canManage ? null : TimetableManager::urlFor($teacher !== null
                ? ['mode' => TimetableManager::MODE_TEACHER, 'teacherId' => $teacher->id]
                : ['mode' => TimetableManager::MODE_SECTION, 'sectionId' => $section?->id]),
            'addUrls' => ! $canManage ? [] : collect(Shift::cases())->mapWithKeys(fn (Shift $shift): array => [
                $shift->value => TimetableManager::urlFor(['mode' => TimetableManager::MODE_SECTION, 'shift' => $shift->value]),
            ])->all(),
        ];
    }

    /**
     * A section shows only its own shift. A teacher shows only the shifts in which the teacher has lessons.
     *
     * @param  Collection<int, TimetablePeriod>  $periods
     * @param  Collection<int, TimetableEntry>  $lessons
     * @return list<Shift>
     */
    private function shiftsToShow(Collection $periods, Collection $lessons, ?Section $section): array
    {
        if ($section !== null) {
            return [$section->shift];
        }

        $shiftOfPeriod = $periods->mapWithKeys(fn (TimetablePeriod $period): array => [$period->id => $period->shift]);
        $taught = $lessons->map(fn (TimetableEntry $entry): ?Shift => $shiftOfPeriod[$entry->timetable_period_id] ?? null)->filter()->unique()->all();

        return array_values(array_filter(Shift::cases(), fn (Shift $shift): bool => in_array($shift, $taught, true)));
    }

    /**
     * The sections the dashboard filters leave, as "Class 5 - A, Main Branch".
     *
     * @return Collection<int, string>
     */
    private function sectionOptions(): Collection
    {
        return Section::query()
            ->whereIn('id', $this->lessons()->select('section_id'))
            ->with(['schoolClass', 'campus'])
            ->get()
            ->sortBy(fn (Section $section): string => sprintf('%05d-%05d-%s', $section->campus_id, $section->schoolClass?->order ?? 0, $section->name))
            ->mapWithKeys(fn (Section $section): array => [$section->id => $section->full_name.', '.$section->campus?->name]);
    }
}
