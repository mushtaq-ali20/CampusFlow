<?php

namespace Tests\Feature;

use App\Enums\AssessmentType;
use App\Enums\ExamType;
use App\Filament\Resources\Assessments\AssessmentResource;
use App\Filament\Resources\Assessments\Pages\ManageAssessments;
use App\Filament\Resources\GradeBands\GradeBandResource;
use App\Filament\Resources\GradeBands\Pages\ManageGradeBands;
use App\Models\Admission;
use App\Models\Assessment;
use App\Models\AssessmentMark;
use App\Models\Campus;
use App\Models\GradeBand;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Models\Year;
use App\Services\GradingService;
use Database\Seeders\PermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GradingAndAssessmentTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private Campus $branch;

    private Section $section;

    private Subject $english;

    private Subject $maths;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);

        $this->year = Year::factory()->create(['start' => now()->subMonths(6)->toDateString(), 'end' => now()->addMonths(6)->toDateString()]);
        $this->branch = Campus::factory()->create();
        $class = SchoolClass::factory()->create();
        $this->english = Subject::factory()->create(['name' => 'English']);
        $this->maths = Subject::factory()->create(['name' => 'Maths']);
        $class->subjects()->attach($this->english, ['total_marks' => 100, 'passing_marks' => 33]);
        $class->subjects()->attach($this->maths, ['total_marks' => 100, 'passing_marks' => 33]);
        $this->section = Section::factory()->create(['campus_id' => $this->branch->id, 'school_class_id' => $class->id, 'year_id' => $this->year->id]);
    }

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function teacherOf(Section $section, Subject $subject): Teacher
    {
        $teacher = Teacher::factory()->create();
        TeachingAssignment::factory()->create(['section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);
        $this->actingAs($teacher->user);

        return $teacher;
    }

    public function test_the_default_scale_gives_a_grade_from_the_minimum_of_each_band(): void
    {
        $grading = app(GradingService::class);

        $this->assertSame('A+', $grading->gradeFor(80.0));
        $this->assertSame('A', $grading->gradeFor(79.99));
        $this->assertSame('A', $grading->gradeFor(70.0));
        $this->assertSame('E', $grading->gradeFor(33.0));
        $this->assertSame('F', $grading->gradeFor(32.9));
        $this->assertSame('F', $grading->gradeFor(0.0));
        $this->assertNull($grading->gradeFor(null));
    }

    public function test_an_institution_can_use_its_own_scale(): void
    {
        GradeBand::query()->delete();
        GradeBand::factory()->create(['grade' => 'Distinction', 'min_percentage' => 75]);
        GradeBand::factory()->create(['grade' => 'Merit', 'min_percentage' => 50]);
        $grading = app(GradingService::class);

        $this->assertSame('Distinction', $grading->gradeFor(75.0));
        $this->assertSame('Merit', $grading->gradeFor(74.9));
        $this->assertNull($grading->gradeFor(49.0));
    }

    public function test_the_weight_of_the_class_assessments_is_kept_between_0_and_50(): void
    {
        $grading = app(GradingService::class);

        $this->assertSame(0, $grading->formativeWeight());

        $grading->setFormativeWeight(30);
        $this->assertSame(30, $grading->formativeWeight());

        $grading->setFormativeWeight(90);
        $this->assertSame(50, $grading->formativeWeight());

        $grading->setFormativeWeight(-5);
        $this->assertSame(0, $grading->formativeWeight());
    }

    public function test_admins_manage_the_scale_and_secretaries_only_see_it(): void
    {
        $this->actingAsRole('secretary');
        $band = GradeBand::query()->where('grade', 'A+')->sole();

        Livewire::test(ManageGradeBands::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$band])
            ->assertActionHidden('create')
            ->assertActionHidden('formativeWeight')
            ->assertActionHidden(TestAction::make('edit')->table($band));

        $this->actingAsRole('admin');

        Livewire::test(ManageGradeBands::class)
            ->callAction('create', data: ['grade' => 'A++', 'min_percentage' => 95, 'remarks' => 'Exceptional'])
            ->assertHasNoActionErrors()
            ->callAction('formativeWeight', data: ['weight' => 20])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('grade_bands', ['grade' => 'A++']);
        $this->assertSame(20, app(GradingService::class)->formativeWeight());
        $this->assertTrue(GradeBandResource::canAccess());
    }

    public function test_a_teacher_records_an_assessment_for_a_subject_they_teach(): void
    {
        $teacher = $this->teacherOf($this->section, $this->english);

        $this->assertSame([$this->english->id], array_keys(AssessmentResource::subjectOptions($this->section->id)));

        Livewire::test(ManageAssessments::class)
            ->callAction('create', data: [
                'section_id' => $this->section->id,
                'subject_id' => $this->english->id,
                'title' => 'Dictation',
                'type' => AssessmentType::Quiz->value,
                'exam' => ExamType::HalfYearly->value,
                'total_marks' => 10,
                'held_on' => now()->toDateString(),
            ])
            ->assertHasNoActionErrors();

        $assessment = Assessment::query()->sole();
        $this->assertSame($this->branch->id, $assessment->campus_id);
        $this->assertSame($teacher->id, $assessment->created_by);
        $this->assertSame(ExamType::HalfYearly, $assessment->exam);
    }

    public function test_a_teacher_cannot_record_an_assessment_for_a_subject_they_do_not_teach(): void
    {
        $this->teacherOf($this->section, $this->english);

        Livewire::test(ManageAssessments::class)
            ->callAction('create', data: [
                'section_id' => $this->section->id,
                'subject_id' => $this->maths->id,
                'title' => 'Algebra test',
                'type' => AssessmentType::ClassTest->value,
                'exam' => ExamType::Quarterly->value,
                'total_marks' => 20,
                'held_on' => now()->toDateString(),
            ])
            ->assertHasActionErrors(['subject_id']);

        $this->assertDatabaseCount('assessments', 0);
    }

    public function test_teachers_only_see_the_assessments_of_the_subjects_they_teach(): void
    {
        $teacher = $this->teacherOf($this->section, $this->english);
        $own = Assessment::factory()->create(['section_id' => $this->section->id, 'subject_id' => $this->english->id]);
        $other = Assessment::factory()->create(['section_id' => $this->section->id, 'subject_id' => $this->maths->id]);

        $this->assertTrue(AssessmentResource::canAccess());

        Livewire::test(ManageAssessments::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$other]);

        $this->assertSame($teacher->id, TeachingAssignment::query()->sole()->teacher_id);
    }

    public function test_marks_are_entered_for_the_active_students_and_cannot_exceed_the_total(): void
    {
        $this->actingAsRole('admin');
        $assessment = Assessment::factory()->create(['section_id' => $this->section->id, 'subject_id' => $this->english->id, 'total_marks' => 20]);
        $ali = Admission::factory()->create(['section_id' => $this->section->id]);
        $zara = Admission::factory()->create(['section_id' => $this->section->id]);
        $stranger = Admission::factory()->create();

        Livewire::test(ManageAssessments::class)
            ->callAction(TestAction::make('marks')->table($assessment), data: ['rows' => [
                ['admission_id' => $ali->id, 'marks' => 25, 'remarks' => null],
                ['admission_id' => $zara->id, 'marks' => 12, 'remarks' => null],
            ]])
            ->assertHasActionErrors();

        $this->assertDatabaseCount('assessment_marks', 0);

        Livewire::test(ManageAssessments::class)
            ->callAction(TestAction::make('marks')->table($assessment), data: ['rows' => [
                ['admission_id' => $ali->id, 'marks' => 18, 'remarks' => 'Neat work'],
                ['admission_id' => $zara->id, 'marks' => 12.5, 'remarks' => null],
                ['admission_id' => $stranger->id, 'marks' => 20, 'remarks' => null],
            ]])
            ->assertHasNoActionErrors();

        $this->assertSame([18.0, 12.5], $assessment->marks()->orderBy('id')->pluck('marks')->all());
        $this->assertSame('Neat work', $assessment->marks()->where('admission_id', $ali->id)->value('remarks'));
    }

    public function test_clearing_a_mark_removes_it(): void
    {
        $this->actingAsRole('admin');
        $assessment = Assessment::factory()->create(['section_id' => $this->section->id, 'subject_id' => $this->english->id]);
        $ali = Admission::factory()->create(['section_id' => $this->section->id]);
        AssessmentMark::factory()->create(['assessment_id' => $assessment->id, 'admission_id' => $ali->id, 'marks' => 10]);

        Livewire::test(ManageAssessments::class)
            ->callAction(TestAction::make('marks')->table($assessment), data: ['rows' => [['admission_id' => $ali->id, 'marks' => null, 'remarks' => null]]])
            ->assertHasNoActionErrors();

        $this->assertDatabaseCount('assessment_marks', 0);
    }

    public function test_viewers_cannot_open_the_assessments(): void
    {
        $this->actingAsRole('viewer');

        $this->assertFalse(AssessmentResource::canAccess());
        $this->get(AssessmentResource::getUrl())->assertForbidden();
    }
}
