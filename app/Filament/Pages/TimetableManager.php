<?php

namespace App\Filament\Pages;

use App\Enums\Shift;
use App\Filament\Resources\Sections\SectionResource;
use App\Models\Section;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * The timetable configuration screen. Choose a branch, class and section, or a teacher, to see the weekly
 * timetable as a grid of periods and weekdays. Administrators click a period to set its subject and teacher.
 * The dashboard timetables lead here.
 */
class TimetableManager extends Page
{
    public const MODE_SECTION = 'section';

    public const MODE_TEACHER = 'teacher';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 45;

    protected string $view = 'filament.pages.timetable-manager';

    #[Url]
    public string $mode = self::MODE_SECTION;

    #[Url]
    public int|string|null $campusId = null;

    #[Url]
    public ?string $shift = null;

    #[Url]
    public int|string|null $classId = null;

    #[Url]
    public int|string|null $sectionId = null;

    #[Url]
    public int|string|null $teacherId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('school.view') ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('School');
    }

    public static function getNavigationLabel(): string
    {
        return __('Timetable manager');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Timetable manager');
    }

    /**
     * The address of this screen for a section or a teacher.
     *
     * @param  array{mode?: string, shift?: string|null, sectionId?: int|string|null, teacherId?: int|string|null}  $parameters
     */
    public static function urlFor(array $parameters): string
    {
        return static::getUrl($parameters);
    }

    public function mount(): void
    {
        $this->mode = $this->mode === self::MODE_TEACHER ? self::MODE_TEACHER : self::MODE_SECTION;

        if (auth()->user()?->activeBranchId() !== null) {
            $this->campusId = auth()->user()->activeBranchId();
        }

        $section = $this->selectedSection();

        if ($section !== null) {
            $this->campusId = $section->campus_id;
            $this->shift = $section->shift->value;
            $this->classId = $section->school_class_id;
        } else {
            $this->sectionId = null;
        }
    }

    public function canManage(): bool
    {
        return auth()->user()?->can('school.manage') ?? false;
    }

    public function updatedCampusId(): void
    {
        $this->classId = null;
        $this->sectionId = null;
    }

    public function updatedShift(): void
    {
        $this->classId = null;
        $this->sectionId = null;
    }

    public function updatedClassId(): void
    {
        $this->sectionId = null;
    }

    public function openSection(int $sectionId): void
    {
        $section = Section::query()->visibleTo(auth()->user())->findOrFail($sectionId);

        $this->mode = self::MODE_SECTION;
        $this->sectionId = $section->id;
        $this->campusId = $section->campus_id;
        $this->shift = $section->shift->value;
        $this->classId = $section->school_class_id;
    }

    public function selectedSection(): ?Section
    {
        return blank($this->sectionId)
            ? null
            : Section::query()->visibleTo(auth()->user())->with(['schoolClass.subjects', 'campus', 'teachingAssignments'])->find($this->sectionId);
    }

    /**
     * @return array<int, string>
     */
    public function campusOptions(): array
    {
        return SectionResource::branchOptions();
    }

    /**
     * @return array<string, string>
     */
    public function shiftOptions(): array
    {
        return collect(Shift::cases())->mapWithKeys(fn (Shift $shift): array => [$shift->value => $shift->getLabel()])->all();
    }

    /**
     * The classes that have sections in the chosen branch and shift.
     *
     * @return array<int, string>
     */
    public function classOptions(): array
    {
        return Section::query()
            ->visibleTo(auth()->user())
            ->when(filled($this->campusId), fn ($query) => $query->where('campus_id', $this->campusId))
            ->when(filled($this->shift), fn ($query) => $query->where('shift', $this->shift))
            ->with('schoolClass')
            ->get()
            ->pluck('schoolClass')
            ->unique('id')
            ->sortBy('order')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function sectionOptions(): array
    {
        return Section::query()
            ->visibleTo(auth()->user())
            ->when(filled($this->campusId), fn ($query) => $query->where('campus_id', $this->campusId))
            ->when(filled($this->shift), fn ($query) => $query->where('shift', $this->shift))
            ->when(filled($this->classId), fn ($query) => $query->where('school_class_id', $this->classId))
            ->with('schoolClass')
            ->get()
            ->sortBy(fn (Section $section): string => str_pad((string) $section->schoolClass->order, 4, '0', STR_PAD_LEFT).$section->name)
            ->mapWithKeys(fn (Section $section): array => [$section->id => $section->full_name])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function teacherOptions(): array
    {
        return SectionResource::teacherOptions();
    }

    /**
     * The periods of the shift of the chosen section. A teacher's timetable shows the periods of every shift,
     * since a teacher can teach in both.
     *
     * @return Collection<int, TimetablePeriod>
     */
    public function periods(): Collection
    {
        $section = $this->mode === self::MODE_SECTION ? $this->selectedSection() : null;

        return TimetablePeriod::query()
            ->when($section !== null, fn ($query) => $query->forShift($section->shift))
            ->ordered()
            ->get();
    }

    /**
     * The periods grouped by shift, morning first. A section shows only its own shift. A teacher shows only the
     * shifts in which the teacher has lessons, see missingShifts() for the others.
     *
     * @return Collection<string, Collection<int, TimetablePeriod>>
     */
    public function periodsByShift(): Collection
    {
        $shifts = $this->mode === self::MODE_TEACHER ? $this->teacherShifts() : null;

        return $this->periods()
            ->filter(fn (TimetablePeriod $period): bool => $shifts === null || in_array($period->shift, $shifts, true))
            ->groupBy(fn (TimetablePeriod $period): string => $period->shift->value)
            ->sortBy(fn (Collection $periods, string $shift): int => array_search(Shift::from($shift), Shift::cases(), true));
    }

    /**
     * The shifts in which the chosen teacher has no lessons yet, which the timetable leaves out.
     *
     * @return list<Shift>
     */
    public function missingShifts(): array
    {
        if ($this->mode !== self::MODE_TEACHER || blank($this->teacherId)) {
            return [];
        }

        return array_values(array_filter(Shift::cases(), fn (Shift $shift): bool => ! in_array($shift, $this->teacherShifts(), true)));
    }

    /**
     * Opens the section view on a shift, to choose one of its sections and add lessons.
     */
    public function addTimetableForShift(string $shift): void
    {
        $this->mode = self::MODE_SECTION;
        $this->shift = Shift::from($shift)->value;
        $this->classId = null;
        $this->sectionId = null;
    }

    /**
     * @return list<Shift>
     */
    private function teacherShifts(): array
    {
        if (blank($this->teacherId)) {
            return [];
        }

        $shiftValues = TimetablePeriod::query()
            ->whereIn('id', TimetableEntry::query()->where('teacher_id', $this->teacherId)->select('timetable_period_id'))
            ->distinct()
            ->pluck('shift')
            ->map(fn (Shift $shift): string => $shift->value)
            ->all();

        return array_values(array_filter(Shift::cases(), fn (Shift $shift): bool => in_array($shift->value, $shiftValues, true)));
    }

    /**
     * @return array<int, string>
     */
    public function days(): array
    {
        return collect(TimetableEntry::DAYS)->only([1, 2, 3, 4, 5, 6])->map(fn (string $day): string => __($day))->all();
    }

    /**
     * The lessons of the chosen section or teacher, by period and weekday.
     *
     * @return Collection<string, TimetableEntry>
     */
    public function entries(): Collection
    {
        $visibleSections = Section::query()->visibleTo(auth()->user())->select('id');

        $query = TimetableEntry::query()
            ->with(['subject', 'teacher', 'section.schoolClass'])
            ->whereIn('section_id', $visibleSections);

        if ($this->mode === self::MODE_TEACHER && filled($this->teacherId)) {
            $query->where('teacher_id', $this->teacherId);
        } elseif ($this->mode === self::MODE_SECTION && filled($this->sectionId)) {
            $query->where('section_id', $this->sectionId);
        } else {
            return collect();
        }

        return $query->get()->keyBy(fn (TimetableEntry $entry): string => $entry->timetable_period_id.'-'.$entry->day_of_week);
    }

    /**
     * Sets or clears the subject and teacher of a period of a weekday for the chosen section.
     */
    public function editCellAction(): Action
    {
        return Action::make('editCell')
            ->visible(fn (): bool => $this->canManage() && $this->selectedSection() !== null)
            ->modalHeading(fn (array $arguments): string => $this->cellHeading($arguments))
            ->modalDescription(__('Leave the subject empty to make the period free.'))
            ->modalSubmitActionLabel(__('Save'))
            ->fillForm(fn (array $arguments): array => [
                'subject_id' => $this->entryAt($arguments)?->subject_id,
                'teacher_id' => $this->entryAt($arguments)?->teacher_id,
            ])
            ->schema(fn (): array => [
                Select::make('subject_id')
                    ->label(__('Subject'))
                    ->options(fn (): array => $this->selectedSection()?->schoolClass->subjects->pluck('name', 'id')->all() ?? [])
                    ->placeholder(__('Free period'))
                    ->live()
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        $set('teacher_id', filled($state)
                            ? TeachingAssignment::query()->where('section_id', $this->sectionId)->where('subject_id', $state)->value('teacher_id')
                            : null);
                    }),
                Select::make('teacher_id')
                    ->label(__('Teacher'))
                    ->options(fn (): array => $this->teacherOptions())
                    ->searchable()
                    ->requiredWith('subject_id'),
            ])
            ->action(function (array $data, array $arguments, Action $action): void {
                abort_unless($this->canManage(), 403);

                $section = $this->selectedSection();
                abort_if($section === null, 404);

                $period = TimetablePeriod::query()->where('is_break', false)->forShift($section->shift)->findOrFail($arguments['period'] ?? null);
                $day = (int) ($arguments['day'] ?? 0);
                abort_unless(array_key_exists($day, TimetableEntry::DAYS), 404);

                $slot = ['section_id' => $section->id, 'timetable_period_id' => $period->id, 'day_of_week' => $day];

                if (blank($data['subject_id'] ?? null)) {
                    TimetableEntry::query()->where($slot)->delete();

                    Notification::make()->title(__('The period is now free.'))->success()->send();

                    return;
                }

                abort_unless($section->schoolClass->subjects->contains('id', (int) $data['subject_id']), 422);

                $busy = TimetableEntry::query()
                    ->where('teacher_id', $data['teacher_id'])
                    ->where('timetable_period_id', $period->id)
                    ->where('day_of_week', $day)
                    ->where('section_id', '!=', $section->id)
                    ->with('section.schoolClass')
                    ->first();

                if ($busy !== null) {
                    Notification::make()
                        ->title(__('This teacher already teaches :section in this period.', ['section' => $busy->section->full_name]))
                        ->danger()
                        ->send();

                    $action->halt();
                }

                TimetableEntry::query()->updateOrCreate($slot, [
                    'subject_id' => $data['subject_id'],
                    'teacher_id' => $data['teacher_id'],
                ]);

                Notification::make()->title(__('Timetable updated'))->success()->send();
            });
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function entryAt(array $arguments): ?TimetableEntry
    {
        return TimetableEntry::query()
            ->where('section_id', $this->sectionId)
            ->where('timetable_period_id', $arguments['period'] ?? null)
            ->where('day_of_week', $arguments['day'] ?? null)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function cellHeading(array $arguments): string
    {
        $period = TimetablePeriod::query()->find($arguments['period'] ?? null);

        return ($this->selectedSection()?->full_name ?? '').' — '.__(TimetableEntry::DAYS[(int) ($arguments['day'] ?? 1)] ?? '').', '.($period?->name ?? '');
    }
}
