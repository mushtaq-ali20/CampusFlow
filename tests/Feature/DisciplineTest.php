<?php

namespace Tests\Feature;

use App\Enums\DisciplineConsequence;
use App\Enums\DisciplineKind;
use App\Filament\Resources\DisciplineCategories\Pages\ManageDisciplineCategories;
use App\Filament\Resources\DisciplineRecords\DisciplineRecordResource;
use App\Filament\Resources\DisciplineRecords\Pages\ManageDisciplineRecords;
use App\Models\Admission;
use App\Models\Campus;
use App\Models\Contact;
use App\Models\DisciplineCategory;
use App\Models\DisciplineRecord;
use App\Models\ParentMessage;
use App\Models\PhoneNumber;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Models\Year;
use Database\Seeders\PermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DisciplineTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private Campus $branch;

    private Section $section;

    private Admission $admission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);

        $this->year = Year::factory()->create(['name' => '2026-2027', 'start' => now()->subMonths(6)->toDateString(), 'end' => now()->addMonths(6)->toDateString()]);
        $this->branch = Campus::factory()->create(['name' => 'Gulshan']);
        $this->section = $this->section($this->branch);
        $this->admission = Admission::factory()->create(['section_id' => $this->section->id]);
    }

    private function section(Campus $branch, array $attributes = []): Section
    {
        return Section::factory()->create(['campus_id' => $branch->id, 'year_id' => $this->year->id, ...$attributes]);
    }

    private function actingAsRole(string $role, ?Campus $branch = null): User
    {
        $user = User::factory()->create(['campus_id' => $branch?->id]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function actingAsTeacher(): Teacher
    {
        $teacher = Teacher::factory()->create();
        $this->actingAs($teacher->user);

        return $teacher;
    }

    public function test_a_record_takes_the_branch_section_points_and_consequence_of_its_admission_and_category(): void
    {
        $violation = DisciplineCategory::factory()->create(['points' => 5, 'consequence' => DisciplineConsequence::ParentCalledIn]);
        $merit = DisciplineCategory::factory()->merit()->create(['points' => 2]);

        $misconduct = DisciplineRecord::factory()->create(['admission_id' => $this->admission->id, 'discipline_category_id' => $violation->id]);
        $goodConduct = DisciplineRecord::factory()->create(['admission_id' => $this->admission->id, 'discipline_category_id' => $merit->id]);

        $this->assertSame($this->branch->id, $misconduct->campus_id);
        $this->assertSame($this->section->id, $misconduct->section_id);
        $this->assertSame(-5, $misconduct->points);
        $this->assertSame(DisciplineConsequence::ParentCalledIn, $misconduct->consequence);
        $this->assertSame(2, $goodConduct->points);
        $this->assertNull($goodConduct->consequence);
        $this->assertSame(-3, (int) $this->admission->disciplineRecords()->sum('points'));
    }

    public function test_changing_a_category_does_not_change_the_points_of_past_records(): void
    {
        $category = DisciplineCategory::factory()->create(['points' => 3]);
        $record = DisciplineRecord::factory()->create(['admission_id' => $this->admission->id, 'discipline_category_id' => $category->id]);

        $category->update(['points' => 8]);
        $record->update(['remarks' => 'Third time this week']);

        $this->assertSame(-3, $record->fresh()->points);
    }

    public function test_staff_record_a_violation_for_a_student_of_their_branch(): void
    {
        $user = $this->actingAsRole('secretary');
        $category = DisciplineCategory::factory()->warning()->create(['points' => 2]);

        Livewire::test(ManageDisciplineRecords::class)
            ->callAction('create', data: [
                'section_id' => $this->section->id,
                'admission_id' => $this->admission->id,
                'discipline_category_id' => $category->id,
                'date' => now()->toDateString(),
                'remarks' => 'Homework missing for three days',
            ])
            ->assertHasNoActionErrors();

        $record = DisciplineRecord::query()->sole();
        $this->assertSame($this->admission->id, $record->admission_id);
        $this->assertSame(-2, $record->points);
        $this->assertSame(DisciplineConsequence::WarningToParent, $record->consequence);
        $this->assertSame($user->id, $record->recorded_by);
    }

    public function test_a_student_must_belong_to_the_chosen_section(): void
    {
        $this->actingAsRole('secretary');
        $otherSection = $this->section($this->branch);
        $category = DisciplineCategory::factory()->create();

        Livewire::test(ManageDisciplineRecords::class)
            ->callAction('create', data: [
                'section_id' => $otherSection->id,
                'admission_id' => $this->admission->id,
                'discipline_category_id' => $category->id,
                'date' => now()->toDateString(),
            ])
            ->assertHasActionErrors(['admission_id']);

        $this->assertDatabaseCount('discipline_records', 0);
    }

    public function test_branch_staff_only_see_the_records_of_their_branch(): void
    {
        $otherBranch = Campus::factory()->create();
        $otherAdmission = Admission::factory()->create(['section_id' => $this->section($otherBranch)->id]);
        $ours = DisciplineRecord::factory()->create(['admission_id' => $this->admission->id]);
        $theirs = DisciplineRecord::factory()->create(['admission_id' => $otherAdmission->id]);

        $this->actingAsRole('secretary', $this->branch);

        Livewire::test(ManageDisciplineRecords::class)
            ->assertCanSeeTableRecords([$ours])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_teachers_see_and_record_discipline_only_for_the_sections_they_teach(): void
    {
        $teacher = $this->actingAsTeacher();
        $classSection = $this->section($this->branch, ['class_teacher_id' => $teacher->id]);
        $subjectSection = $this->section($this->branch);
        TeachingAssignment::factory()->create(['section_id' => $subjectSection->id, 'teacher_id' => $teacher->id, 'subject_id' => Subject::factory()]);

        $ownStudent = Admission::factory()->create(['section_id' => $classSection->id]);
        $subjectStudent = Admission::factory()->create(['section_id' => $subjectSection->id]);
        $classRecord = DisciplineRecord::factory()->create(['admission_id' => $ownStudent->id]);
        $subjectRecord = DisciplineRecord::factory()->create(['admission_id' => $subjectStudent->id]);
        $otherRecord = DisciplineRecord::factory()->create(['admission_id' => $this->admission->id]);

        $this->assertTrue(DisciplineRecordResource::canAccess());
        $this->assertEqualsCanonicalizing([$classSection->id, $subjectSection->id], array_keys(DisciplineRecordResource::sectionOptions()));

        Livewire::test(ManageDisciplineRecords::class)
            ->assertCanSeeTableRecords([$classRecord, $subjectRecord])
            ->assertCanNotSeeTableRecords([$otherRecord])
            ->callAction('create', data: [
                'section_id' => $this->section->id,
                'admission_id' => $this->admission->id,
                'discipline_category_id' => DisciplineCategory::factory()->create()->id,
                'date' => now()->toDateString(),
            ])
            ->assertHasActionErrors(['section_id']);

        Livewire::test(ManageDisciplineRecords::class)
            ->callAction('create', data: [
                'section_id' => $classSection->id,
                'admission_id' => $ownStudent->id,
                'discipline_category_id' => DisciplineCategory::factory()->merit()->create()->id,
                'date' => now()->toDateString(),
            ])
            ->assertHasNoActionErrors();

        $this->assertSame($teacher->id, DisciplineRecord::query()->latest('id')->first()->recorded_by);
    }

    public function test_teachers_change_only_the_records_they_wrote(): void
    {
        $teacher = $this->actingAsTeacher();
        $this->section->update(['class_teacher_id' => $teacher->id]);
        $own = DisciplineRecord::factory()->create(['admission_id' => $this->admission->id, 'recorded_by' => $teacher->id]);
        $colleagues = DisciplineRecord::factory()->create(['admission_id' => $this->admission->id, 'recorded_by' => User::factory()]);

        Livewire::test(ManageDisciplineRecords::class)
            ->assertActionVisible(TestAction::make('edit')->table($own))
            ->assertActionHidden(TestAction::make('edit')->table($colleagues))
            ->assertActionHidden(TestAction::make('delete')->table($colleagues));
    }

    public function test_users_who_are_neither_staff_nor_teachers_cannot_open_the_records(): void
    {
        $this->actingAsRole('viewer');

        $this->assertFalse(DisciplineRecordResource::canAccess());
        $this->get(DisciplineRecordResource::getUrl())->assertForbidden();
    }

    public function test_the_parents_are_messaged_about_a_record_on_whatsapp(): void
    {
        $this->actingAsRole('secretary');
        $contact = Contact::factory()->create(['student_id' => $this->admission->student_id]);
        PhoneNumber::query()->create(['phoneable_id' => $contact->id, 'phoneable_type' => Contact::class, 'phone_number' => '0300-1234567']);
        $category = DisciplineCategory::factory()->create(['name' => 'Fighting', 'points' => 10, 'consequence' => DisciplineConsequence::ParentCalledIn]);
        $record = DisciplineRecord::factory()->create(['admission_id' => $this->admission->id, 'discipline_category_id' => $category->id, 'remarks' => 'In the playground']);

        $message = DisciplineRecordResource::parentMessage($record);
        $this->assertStringContainsString($this->admission->student->name, $message);
        $this->assertStringContainsString('"Fighting"', $message);
        $this->assertStringContainsString('Remarks: In the playground', $message);
        $this->assertStringContainsString('Please visit the school', $message);

        Livewire::test(ManageDisciplineRecords::class)
            ->callAction(TestAction::make('whatsappParent')->table($record), data: ['phone' => '923001234567', 'body' => $message])
            ->assertHasNoActionErrors();

        $sent = ParentMessage::query()->sole();
        $this->assertTrue($sent->about->is($record));
        $this->assertSame($this->branch->id, $sent->campus_id);
    }

    public function test_a_merit_message_congratulates_the_parents(): void
    {
        $record = DisciplineRecord::factory()->create([
            'admission_id' => $this->admission->id,
            'discipline_category_id' => DisciplineCategory::factory()->merit()->create(['name' => 'Helped a classmate'])->id,
            'remarks' => null,
        ]);

        $message = DisciplineRecordResource::parentMessage($record);

        $this->assertStringContainsString('pleased to inform you', $message);
        $this->assertStringNotContainsString('Remarks', $message);
        $this->assertStringNotContainsString('Please visit the school', $message);
    }

    public function test_admins_define_categories_and_merits_have_no_consequence(): void
    {
        $this->actingAsRole('admin');

        Livewire::test(ManageDisciplineCategories::class)
            ->callAction('create', data: [
                'name' => 'Class monitor duties',
                'kind' => DisciplineKind::Merit->value,
                'points' => 3,
                'consequence' => DisciplineConsequence::WarningToParent->value,
                'is_active' => true,
            ])
            ->assertHasNoActionErrors();

        $category = DisciplineCategory::query()->sole();
        $this->assertSame(DisciplineKind::Merit, $category->kind);
        $this->assertSame(3, $category->signedPoints());
        $this->assertNull($category->consequence);
    }

    public function test_secretaries_see_the_categories_but_do_not_change_them(): void
    {
        $this->actingAsRole('secretary');
        $category = DisciplineCategory::factory()->create();

        Livewire::test(ManageDisciplineCategories::class)
            ->assertCanSeeTableRecords([$category])
            ->assertActionHidden('create')
            ->assertActionHidden(TestAction::make('edit')->table($category));
    }

    public function test_inactive_categories_are_not_offered_for_new_records(): void
    {
        $active = DisciplineCategory::factory()->create(['name' => 'Late to class', 'points' => 1]);
        DisciplineCategory::factory()->create(['name' => 'Old rule', 'is_active' => false]);
        $merit = DisciplineCategory::factory()->merit()->create(['name' => 'Excellent homework', 'points' => 1]);

        $options = DisciplineRecordResource::categoryOptions();

        $this->assertSame([$active->id => 'Late to class (-1)'], $options['Violation']);
        $this->assertSame([$merit->id => 'Excellent homework (+1)'], $options['Merit']);
    }
}
