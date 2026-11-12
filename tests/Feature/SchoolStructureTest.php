<?php

namespace Tests\Feature;

use App\Enums\Shift;
use App\Filament\Resources\SchoolClasses\Pages\EditSchoolClass;
use App\Filament\Resources\SchoolClasses\RelationManagers\SubjectsRelationManager;
use App\Filament\Resources\SchoolClasses\SchoolClassResource;
use App\Filament\Resources\Sections\Pages\CreateSection;
use App\Filament\Resources\Sections\Pages\EditSection;
use App\Filament\Resources\Sections\Pages\ListSections;
use App\Filament\Resources\Sections\RelationManagers\TeachingAssignmentsRelationManager;
use App\Filament\Resources\Sections\SectionResource;
use App\Filament\Resources\Settings\Years\Pages\ManageYears;
use App\Filament\Resources\Subjects\SubjectResource;
use App\Filament\Resources\Teachers\Pages\EditTeacher;
use App\Models\Admission;
use App\Models\Campus;
use App\Models\Contact;
use App\Models\FeePayment;
use App\Models\FeeVoucher;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use App\Models\User;
use App\Models\Year;
use App\Services\Settings;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\SchoolDemoSeeder;
use Database\Seeders\SchoolSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SchoolStructureTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);

        $this->year = Year::factory()->create(['name' => '2026-2027', 'start' => '2026-04-01', 'end' => '2027-03-31']);
    }

    private function actingAsRole(string $role, ?Campus $branch = null): User
    {
        $user = User::factory()->create(['campus_id' => $branch?->id]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    public function test_classes_have_subjects_with_marks_and_are_promoted_in_order(): void
    {
        $nursery = SchoolClass::factory()->create(['name' => 'Nursery', 'order' => 2]);
        $preNursery = SchoolClass::factory()->create(['name' => 'Pre Nursery', 'order' => 1]);
        $kg = SchoolClass::factory()->create(['name' => 'KG I', 'order' => 3]);
        $english = Subject::factory()->create(['name' => 'English']);

        $nursery->subjects()->attach($english, ['total_marks' => 50, 'passing_marks' => 20]);

        $this->assertSame(50, $nursery->subjects->first()->pivot->total_marks);
        $this->assertSame(20, $nursery->subjects->first()->pivot->passing_marks);
        $this->assertTrue($preNursery->nextClass()->is($nursery));
        $this->assertTrue($nursery->nextClass()->is($kg));
        $this->assertNull($kg->nextClass());
    }

    public function test_the_current_academic_year_is_configurable(): void
    {
        $this->travelTo('2026-11-08');
        $nextYear = Year::factory()->create(['name' => '2027-2028', 'start' => '2027-04-01', 'end' => '2028-03-31']);

        $this->assertTrue(Year::current()->is($this->year));

        app(Settings::class)->set(['current_academic_year' => $nextYear->id]);

        $this->assertTrue(Year::current()->is($nextYear));
        $this->assertTrue($nextYear->isCurrent());
        $this->assertFalse($this->year->isCurrent());
    }

    public function test_the_latest_academic_year_is_current_when_none_includes_today(): void
    {
        $this->travelTo('2030-01-01');
        $latestYear = Year::factory()->create(['name' => '2028-2029']);

        $this->assertTrue(Year::current()->is($latestYear));
    }

    public function test_admin_can_set_the_current_academic_year(): void
    {
        $this->actingAsRole('admin');
        $nextYear = Year::factory()->create(['name' => '2027-2028', 'start' => '2027-04-01', 'end' => '2028-03-31']);

        Livewire::test(ManageYears::class)
            ->callAction(TestAction::make('setCurrent')->table($nextYear));

        $this->assertSame($nextYear->id, app(Settings::class)->get('current_academic_year'));
    }

    public function test_assigning_a_teacher_to_a_section_adds_the_branch_to_the_teacher(): void
    {
        $teacher = Teacher::factory()->create();
        $mainBranch = Campus::factory()->create();
        $cityBranch = Campus::factory()->create();

        TeachingAssignment::factory()->create(['teacher_id' => $teacher->id, 'section_id' => Section::factory()->create(['campus_id' => $mainBranch->id])->id]);
        TeachingAssignment::factory()->create(['teacher_id' => $teacher->id, 'section_id' => Section::factory()->create(['campus_id' => $cityBranch->id])->id]);
        TeachingAssignment::factory()->create(['teacher_id' => $teacher->id, 'section_id' => Section::factory()->create(['campus_id' => $mainBranch->id])->id]);

        $this->assertEqualsCanonicalizing([$mainBranch->id, $cityBranch->id], $teacher->campuses()->pluck('campuses.id')->all());
    }

    public function test_admin_creates_a_section(): void
    {
        $this->actingAsRole('admin');
        $branch = Campus::factory()->create();
        $class = SchoolClass::factory()->create();
        $teacher = Teacher::factory()->create();

        Livewire::test(CreateSection::class)
            ->assertSchemaStateSet(['year_id' => $this->year->id])
            ->fillForm([
                'campus_id' => $branch->id,
                'school_class_id' => $class->id,
                'name' => 'A',
                'capacity' => 30,
                'class_teacher_id' => $teacher->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $section = Section::query()->sole();
        $this->assertSame('A', $section->name);
        $this->assertSame(Shift::Morning, $section->shift);
        $this->assertTrue($section->classTeacher->is($teacher));
        $this->assertSame($class->name.' - A (Morning)', $section->full_name);
    }

    public function test_a_section_name_is_unique_for_a_class_branch_and_academic_year(): void
    {
        $this->actingAsRole('admin');
        $existing = Section::factory()->create(['name' => 'A', 'year_id' => $this->year->id]);

        Livewire::test(CreateSection::class)
            ->fillForm([
                'campus_id' => $existing->campus_id,
                'school_class_id' => $existing->school_class_id,
                'year_id' => $this->year->id,
                'name' => 'A',
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'unique']);

        Livewire::test(CreateSection::class)
            ->fillForm([
                'campus_id' => Campus::factory()->create()->id,
                'school_class_id' => $existing->school_class_id,
                'year_id' => $this->year->id,
                'name' => 'A',
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_a_class_has_sections_with_the_same_names_in_each_shift(): void
    {
        $this->actingAsRole('admin');
        $existing = Section::factory()->create(['name' => 'A', 'shift' => Shift::Morning, 'year_id' => $this->year->id]);

        Livewire::test(CreateSection::class)
            ->fillForm([
                'campus_id' => $existing->campus_id,
                'school_class_id' => $existing->school_class_id,
                'year_id' => $this->year->id,
                'shift' => Shift::Afternoon->value,
                'name' => 'A',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $sections = Section::query()->where('school_class_id', $existing->school_class_id)->with('schoolClass')->get();
        $className = $existing->schoolClass->name;

        $this->assertEqualsCanonicalizing([$className.' - A (Morning)', $className.' - A (Afternoon)'], $sections->pluck('full_name')->all());
    }

    public function test_sections_are_listed_by_shift_then_class(): void
    {
        $this->actingAsRole('admin');
        $branch = Campus::factory()->create();
        $classOne = SchoolClass::factory()->create(['name' => 'Class 1', 'order' => 1]);
        $classTwo = SchoolClass::factory()->create(['name' => 'Class 2', 'order' => 2]);

        $sections = collect([
            [$classTwo, Shift::Afternoon, 'A'], [$classOne, Shift::Afternoon, 'B'], [$classOne, Shift::Afternoon, 'A'],
            [$classTwo, Shift::Morning, 'A'], [$classOne, Shift::Morning, 'B'], [$classOne, Shift::Morning, 'A'],
        ])->map(fn (array $definition): Section => Section::factory()->create([
            'campus_id' => $branch->id, 'school_class_id' => $definition[0]->id, 'year_id' => $this->year->id, 'shift' => $definition[1], 'name' => $definition[2],
        ]));

        Livewire::test(ListSections::class)
            ->assertCanSeeTableRecords($sections->sortBy(fn (Section $section): string => ($section->shift === Shift::Morning ? 0 : 1).$section->schoolClass->order.$section->name)->values(), inOrder: true)
            ->assertSeeInOrder(['Morning › Class 1', 'Morning › Class 2', 'Afternoon › Class 1', 'Afternoon › Class 2']);
    }

    public function test_subjects_of_a_class_are_attached_with_their_marks(): void
    {
        $this->actingAsRole('admin');
        $class = SchoolClass::factory()->create();
        $urdu = Subject::factory()->create(['name' => 'Urdu']);
        $science = Subject::factory()->create(['name' => 'Science']);

        Livewire::test(SubjectsRelationManager::class, ['ownerRecord' => $class, 'pageClass' => EditSchoolClass::class])
            ->callAction(TestAction::make('attach')->table(), [
                'recordId' => [$urdu->id, $science->id],
                'total_marks' => 75,
                'passing_marks' => 25,
            ])
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing([$urdu->id, $science->id], $class->subjects()->pluck('subjects.id')->all());
        $this->assertEquals([75], $class->subjects()->pluck('total_marks')->unique()->values()->all());
    }

    public function test_passing_marks_cannot_exceed_total_marks(): void
    {
        $this->actingAsRole('admin');
        $class = SchoolClass::factory()->create();

        Livewire::test(SubjectsRelationManager::class, ['ownerRecord' => $class, 'pageClass' => EditSchoolClass::class])
            ->callAction(TestAction::make('attach')->table(), [
                'recordId' => [Subject::factory()->create()->id],
                'total_marks' => 50,
                'passing_marks' => 60,
            ])
            ->assertHasFormErrors(['passing_marks' => 'lte']);

        $this->assertSame(0, $class->subjects()->count());
    }

    public function test_only_subjects_of_the_class_can_be_assigned_to_a_teacher_once_per_section(): void
    {
        $this->actingAsRole('admin');
        $section = Section::factory()->create();
        $mathematics = Subject::factory()->create(['name' => 'Mathematics']);
        $otherSubject = Subject::factory()->create();
        $section->schoolClass->subjects()->attach($mathematics);
        $teacher = Teacher::factory()->create();

        $relationManager = fn () => Livewire::test(TeachingAssignmentsRelationManager::class, ['ownerRecord' => $section, 'pageClass' => EditSection::class]);

        $relationManager()
            ->callAction(TestAction::make('create')->table(), ['subject_id' => $otherSubject->id, 'teacher_id' => $teacher->id])
            ->assertHasFormErrors(['subject_id' => 'in']);

        $relationManager()
            ->callAction(TestAction::make('create')->table(), ['subject_id' => $mathematics->id, 'teacher_id' => $teacher->id])
            ->assertHasNoFormErrors();

        $relationManager()
            ->callAction(TestAction::make('create')->table(), ['subject_id' => $mathematics->id, 'teacher_id' => Teacher::factory()->create()->id])
            ->assertHasFormErrors(['subject_id' => 'unique']);

        $this->assertTrue($section->teachingAssignments()->sole()->teacher->is($teacher));
        $this->assertTrue($teacher->campuses->contains($section->campus));
    }

    public function test_admin_assigns_branches_to_a_teacher(): void
    {
        $this->actingAsRole('admin');
        $teacher = Teacher::factory()->create();
        $branches = Campus::factory()->count(2)->create();

        Livewire::test(EditTeacher::class, ['record' => $teacher->id])
            ->fillForm(['campuses' => $branches->pluck('id')->all()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing($branches->pluck('id')->all(), $teacher->campuses()->pluck('campuses.id')->all());
    }

    public function test_secretary_can_view_but_not_change_the_school_structure(): void
    {
        $this->actingAsRole('secretary');
        $section = Section::factory()->create();

        foreach ([SchoolClassResource::class, SectionResource::class, SubjectResource::class] as $resource) {
            $this->assertTrue($resource::canAccess());
            $this->assertFalse($resource::canCreate());
        }

        $this->assertFalse(SectionResource::canEdit($section));
        $this->assertFalse(SectionResource::canDelete($section));
        $this->get(SectionResource::getUrl('view', ['record' => $section]))->assertOk();
        $this->get(SectionResource::getUrl('edit', ['record' => $section]))->assertForbidden();
        $this->get(SchoolClassResource::getUrl('create'))->assertForbidden();
    }

    public function test_users_without_school_permissions_cannot_access_the_school_structure(): void
    {
        $this->actingAsRole('viewer');

        $this->assertFalse(SectionResource::canAccess());
        $this->get(SectionResource::getUrl('index'))->assertForbidden();
    }

    public function test_branch_staff_only_see_the_sections_of_their_branch(): void
    {
        $ownBranch = Campus::factory()->create();
        $ownSection = Section::factory()->create(['campus_id' => $ownBranch->id, 'year_id' => $this->year->id]);
        $otherSection = Section::factory()->create(['year_id' => $this->year->id]);
        $this->actingAsRole('secretary', $ownBranch);

        Livewire::test(ListSections::class)
            ->assertCanSeeTableRecords([$ownSection])
            ->assertCanNotSeeTableRecords([$otherSection]);

        $this->get(SectionResource::getUrl('view', ['record' => $otherSection]))->assertNotFound();
        $this->assertSame([$ownBranch->id], array_keys(SectionResource::branchOptions()));
    }

    public function test_staff_without_a_branch_see_the_sections_of_all_branches(): void
    {
        $sections = Section::factory()->count(2)->create(['year_id' => $this->year->id]);
        $this->actingAsRole('admin');

        Livewire::test(ListSections::class)
            ->assertCanSeeTableRecords($sections);
    }

    public function test_the_school_seeder_creates_the_classes_sections_and_teachers(): void
    {
        Campus::factory()->create(['id' => 1]);

        $this->seed(SchoolSeeder::class);

        $this->assertSame(
            ['Montessori', 'Pre Nursery', 'Nursery', 'KG I', 'KG II', 'Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5', 'Class 6', 'Class 7', 'Class 8', 'Class 9', 'Class 10'],
            SchoolClass::query()->ordered()->pluck('name')->all(),
        );
        $this->assertSame(['Branch 1', 'Branch 2'], Campus::query()->orderBy('id')->get()->pluck('name')->all());
        $this->assertSame(70, Section::query()->count());
        $this->assertSame(30, Section::query()->where('shift', Shift::Morning)->count());
        $this->assertSame(40, Section::query()->where('shift', Shift::Afternoon)->count());
        $this->assertSame(0, Section::query()->whereDoesntHave('teachingAssignments')->count());
        $this->assertSame(0, Section::query()->whereNull('class_teacher_id')->count());
        $classOne = SchoolClass::query()->where('name', 'Class 1')->sole();
        $this->assertEqualsCanonicalizing(
            ['morning A', 'morning B', 'afternoon A', 'afternoon B'],
            Section::query()->where('campus_id', 1)->whereBelongsTo($classOne)->get()->map(fn (Section $section): string => $section->shift->value.' '.$section->name)->all(),
        );
        $this->assertSame(45, Teacher::query()->count());
        $this->assertSame(6, Teacher::query()->has('campuses', '>', 1)->count());
        $this->assertSame(['07:30', '12:00'], [
            substr((string) TimetablePeriod::query()->forShift(Shift::Morning)->min('start_time'), 0, 5),
            substr((string) TimetablePeriod::query()->forShift(Shift::Morning)->max('end_time'), 0, 5),
        ]);
        $this->assertSame(['12:00', '17:00'], [
            substr((string) TimetablePeriod::query()->forShift(Shift::Afternoon)->min('start_time'), 0, 5),
            substr((string) TimetablePeriod::query()->forShift(Shift::Afternoon)->max('end_time'), 0, 5),
        ]);
        $this->assertSame('PKR', config('campusflow.currency_code'));
    }

    public function test_the_school_demo_seeder_admits_students_and_builds_a_clash_free_timetable(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        $this->seed(SchoolDemoSeeder::class);

        $this->assertSame(150, Student::query()->count());
        $this->assertSame(150, Admission::query()->count());
        $this->assertSame(150, Contact::query()->count());
        $this->assertSame(75, Student::query()->where('gender_id', 1)->count());
        $this->assertSame(0, Section::query()->where('year_id', Year::current()->id)->whereDoesntHave('admissions')->count());

        $entries = TimetableEntry::query()->with(['section', 'period'])->get();
        $this->assertSame(range(1, 6), $entries->pluck('day_of_week')->unique()->sort()->values()->all());
        $this->assertTrue($entries->every(fn (TimetableEntry $entry): bool => $entry->period->shift === $entry->section->shift));
        $this->assertSame($entries->count(), $entries->unique(fn (TimetableEntry $entry): string => $entry->teacher_id.'-'.$entry->timetable_period_id.'-'.$entry->day_of_week)->count());
        $this->assertGreaterThan(0.95, $entries->count() / ((30 * 36) + (40 * 42)));

        $this->assertTrue(FeeVoucher::query()->exists());
        $this->assertTrue(FeePayment::query()->exists());
    }

    public function test_each_section_has_a_shift_chosen_when_it_is_created(): void
    {
        $this->actingAsRole('admin');
        $branch = Campus::factory()->create();
        $class = SchoolClass::factory()->create();

        Livewire::test(CreateSection::class)
            ->fillForm([
                'campus_id' => $branch->id,
                'school_class_id' => $class->id,
                'year_id' => $this->year->id,
                'name' => 'B',
                'shift' => Shift::Afternoon->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(Shift::Afternoon, Section::query()->where('name', 'B')->sole()->shift);
        $this->assertSame(Shift::Morning, Section::factory()->create()->refresh()->shift);
    }
}
