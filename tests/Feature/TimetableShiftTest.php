<?php

namespace Tests\Feature;

use App\Enums\Shift;
use App\Filament\Pages\TimetableManager;
use App\Filament\Resources\TimetablePeriods\Pages\ManageTimetablePeriods;
use App\Filament\Widgets\TimetableWeekly;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TimetableShiftTest extends TestCase
{
    use RefreshDatabase;

    private TimetablePeriod $morningPeriod;

    private TimetablePeriod $afternoonPeriod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $this->morningPeriod = TimetablePeriod::factory()->create(['name' => 'Period 1', 'shift' => Shift::Morning, 'start_time' => '07:30', 'end_time' => '08:10', 'order' => 1]);
        $this->afternoonPeriod = TimetablePeriod::factory()->create(['name' => 'Period 1', 'shift' => Shift::Afternoon, 'start_time' => '12:00', 'end_time' => '12:40', 'order' => 2]);
    }

    public function test_a_section_timetable_shows_only_the_periods_of_its_shift(): void
    {
        $morning = Section::factory()->create(['shift' => Shift::Morning]);
        $afternoon = Section::factory()->create(['shift' => Shift::Afternoon]);

        $this->assertSame(
            [$this->morningPeriod->id],
            Livewire::test(TimetableManager::class, ['sectionId' => $morning->id])->instance()->periods()->pluck('id')->all(),
        );
        $this->assertSame(
            [$this->afternoonPeriod->id],
            Livewire::test(TimetableManager::class, ['sectionId' => $afternoon->id])->instance()->periods()->pluck('id')->all(),
        );
    }

    public function test_sections_are_chosen_by_shift_then_class(): void
    {
        $morning = Section::factory()->create(['shift' => Shift::Morning, 'name' => 'A']);
        $afternoon = Section::factory()->create(['shift' => Shift::Afternoon, 'name' => 'A', 'school_class_id' => $morning->school_class_id, 'campus_id' => $morning->campus_id, 'year_id' => $morning->year_id]);
        $otherClass = Section::factory()->create(['shift' => Shift::Morning, 'campus_id' => $morning->campus_id]);

        $page = Livewire::test(TimetableManager::class)
            ->set('shift', Shift::Afternoon->value);

        $this->assertSame([$morning->school_class_id], array_keys($page->instance()->classOptions()));
        $this->assertSame([$afternoon->id], array_keys($page->instance()->sectionOptions()));

        $page->set('shift', Shift::Morning->value)
            ->set('classId', $morning->school_class_id);

        $this->assertSame([$morning->id], array_keys($page->instance()->sectionOptions()));
        $this->assertContains($otherClass->school_class_id, array_keys($page->instance()->classOptions()));
    }

    public function test_opening_a_section_selects_its_shift(): void
    {
        $section = Section::factory()->create(['shift' => Shift::Afternoon]);

        Livewire::test(TimetableManager::class, ['sectionId' => $section->id])
            ->assertSet('shift', Shift::Afternoon->value)
            ->assertSet('classId', $section->school_class_id);
    }

    public function test_a_teacher_timetable_shows_the_periods_of_both_shifts(): void
    {
        $teacher = Teacher::factory()->create();

        $this->assertSame(
            [$this->morningPeriod->id, $this->afternoonPeriod->id],
            Livewire::test(TimetableManager::class, ['mode' => TimetableManager::MODE_TEACHER, 'teacherId' => $teacher->id])->instance()->periods()->pluck('id')->all(),
        );
    }

    public function test_a_section_timetable_shows_only_the_group_of_its_shift(): void
    {
        $section = Section::factory()->create(['shift' => Shift::Afternoon]);

        Livewire::test(TimetableManager::class, ['sectionId' => $section->id])
            ->assertSee('Afternoon shift')
            ->assertSee('12:00 - 12:40')
            ->assertDontSee('Morning shift')
            ->assertDontSee('07:30 - 08:10');
    }

    public function test_a_teacher_timetable_groups_the_shifts_with_lessons_and_offers_to_add_the_others(): void
    {
        [$section, $subject, $teacher] = $this->sectionWithSubject(Shift::Morning);
        TimetableEntry::factory()->create([
            'section_id' => $section->id,
            'timetable_period_id' => $this->morningPeriod->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);

        $page = Livewire::test(TimetableManager::class, ['mode' => TimetableManager::MODE_TEACHER, 'teacherId' => $teacher->id]);

        $this->assertSame(['morning'], $page->instance()->periodsByShift()->keys()->all());
        $this->assertSame([Shift::Afternoon], $page->instance()->missingShifts());

        $page->assertSee('Morning shift')
            ->assertDontSee('Afternoon shift')
            ->assertSee('Add afternoon timetable')
            ->call('addTimetableForShift', Shift::Afternoon->value)
            ->assertSet('mode', TimetableManager::MODE_SECTION)
            ->assertSet('shift', Shift::Afternoon->value)
            ->assertSet('sectionId', null);
    }

    public function test_the_shift_groups_are_in_morning_then_afternoon_order(): void
    {
        $teacher = Teacher::factory()->create();

        foreach ([Shift::Afternoon, Shift::Morning] as $shift) {
            [$section, $subject] = $this->sectionWithSubject($shift);
            TimetableEntry::factory()->create([
                'section_id' => $section->id,
                'timetable_period_id' => $shift === Shift::Morning ? $this->morningPeriod->id : $this->afternoonPeriod->id,
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
            ]);
        }

        $page = Livewire::test(TimetableManager::class, ['mode' => TimetableManager::MODE_TEACHER, 'teacherId' => $teacher->id]);

        $this->assertSame(['morning', 'afternoon'], $page->instance()->periodsByShift()->keys()->all());
        $this->assertSame([], $page->instance()->missingShifts());
        $page->assertSeeInOrder(['Morning shift', 'Afternoon shift']);
    }

    public function test_the_dashboard_timetable_shows_only_the_shift_of_the_section_and_offers_to_add_one(): void
    {
        [$section, $subject, $teacher] = $this->sectionWithSubject(Shift::Afternoon);
        TimetableEntry::factory()->create([
            'section_id' => $section->id,
            'timetable_period_id' => $this->afternoonPeriod->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);

        Livewire::test(TimetableWeekly::class, ['pageFilters' => ['section_id' => $section->id]])
            ->assertSee('Afternoon shift')
            ->assertSee($subject->name)
            ->assertDontSee('Morning shift')
            ->assertDontSee('07:30')
            ->assertSee('Add timetable for a section');
    }

    public function test_the_dashboard_timetable_of_a_teacher_offers_to_add_the_missing_shift(): void
    {
        [$section, $subject, $teacher] = $this->sectionWithSubject(Shift::Afternoon);
        TimetableEntry::factory()->create([
            'section_id' => $section->id,
            'timetable_period_id' => $this->afternoonPeriod->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);

        Livewire::test(TimetableWeekly::class, ['pageFilters' => ['teacher_id' => $teacher->id]])
            ->assertSee('Afternoon shift')
            ->assertDontSee('Morning shift')
            ->assertSee('Add morning timetable');
    }

    public function test_a_lesson_is_set_in_a_period_of_the_section_shift(): void
    {
        [$section, $subject, $teacher] = $this->sectionWithSubject(Shift::Morning);

        Livewire::test(TimetableManager::class, ['sectionId' => $section->id])
            ->callAction(
                TestAction::make('editCell')->arguments(['period' => $this->morningPeriod->id, 'day' => 6]),
                ['subject_id' => $subject->id, 'teacher_id' => $teacher->id],
            )
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('timetable_entries', [
            'section_id' => $section->id,
            'timetable_period_id' => $this->morningPeriod->id,
            'day_of_week' => 6,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);
    }

    public function test_a_lesson_cannot_be_set_in_a_period_of_the_other_shift(): void
    {
        [$section, $subject, $teacher] = $this->sectionWithSubject(Shift::Morning);

        Livewire::test(TimetableManager::class, ['sectionId' => $section->id])
            ->callAction(
                TestAction::make('editCell')->arguments(['period' => $this->afternoonPeriod->id, 'day' => 1]),
                ['subject_id' => $subject->id, 'teacher_id' => $teacher->id],
            )
            ->assertNotFound();

        $this->assertSame(0, TimetableEntry::query()->count());
    }

    public function test_a_teacher_can_teach_the_morning_shift_and_the_afternoon_shift_on_the_same_day(): void
    {
        [$morningSection, $subject, $teacher] = $this->sectionWithSubject(Shift::Morning);
        $afternoonSection = Section::factory()->create(['shift' => Shift::Afternoon, 'school_class_id' => $morningSection->school_class_id]);

        TimetableEntry::factory()->create([
            'section_id' => $morningSection->id,
            'timetable_period_id' => $this->morningPeriod->id,
            'day_of_week' => 1,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);

        Livewire::test(TimetableManager::class, ['sectionId' => $afternoonSection->id])
            ->callAction(
                TestAction::make('editCell')->arguments(['period' => $this->afternoonPeriod->id, 'day' => 1]),
                ['subject_id' => $subject->id, 'teacher_id' => $teacher->id],
            )
            ->assertHasNoFormErrors();

        $this->assertSame(2, TimetableEntry::query()->where('teacher_id', $teacher->id)->where('day_of_week', 1)->count());
    }

    public function test_periods_are_listed_and_filtered_by_shift(): void
    {
        Livewire::test(ManageTimetablePeriods::class)
            ->assertCanSeeTableRecords([$this->morningPeriod, $this->afternoonPeriod])
            ->filterTable('shift', Shift::Afternoon->value)
            ->assertCanSeeTableRecords([$this->afternoonPeriod])
            ->assertCanNotSeeTableRecords([$this->morningPeriod]);
    }

    /**
     * @return array{0: Section, 1: Subject, 2: Teacher}
     */
    private function sectionWithSubject(Shift $shift): array
    {
        $section = Section::factory()->create(['shift' => $shift]);
        $subject = Subject::factory()->create();
        $section->schoolClass->subjects()->attach($subject->id, ['total_marks' => 100, 'passing_marks' => 33]);

        return [$section, $subject, Teacher::factory()->create()];
    }
}
