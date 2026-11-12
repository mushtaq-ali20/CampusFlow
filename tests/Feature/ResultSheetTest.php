<?php

namespace Tests\Feature;

use App\Enums\ExamType;
use App\Filament\Pages\ResultsMasterSheet;
use App\Models\Admission;
use App\Models\Assessment;
use App\Models\AssessmentMark;
use App\Models\Campus;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectResult;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Models\Year;
use App\Services\GradingService;
use App\Services\ResultService;
use Database\Seeders\PermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ResultSheetTest extends TestCase
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

        $this->year = Year::factory()->create(['name' => '2026-2027']);
        $this->branch = Campus::factory()->create();
        $class = SchoolClass::factory()->create(['name' => 'Class 5']);
        $this->english = Subject::factory()->create(['name' => 'English']);
        $this->maths = Subject::factory()->create(['name' => 'Maths']);
        $class->subjects()->attach($this->english, ['total_marks' => 100, 'passing_marks' => 33]);
        $class->subjects()->attach($this->maths, ['total_marks' => 50, 'passing_marks' => 17]);
        $this->section = Section::factory()->create(['campus_id' => $this->branch->id, 'school_class_id' => $class->id, 'year_id' => $this->year->id, 'name' => 'A']);
    }

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function student(string $firstname, ?float $english, ?float $maths, ExamType $exam = ExamType::HalfYearly): Admission
    {
        $student = Student::factory()->create(['id' => User::factory()->create(['firstname' => $firstname, 'lastname' => 'Khan'])->id]);
        $admission = Admission::factory()->create(['student_id' => $student->id, 'section_id' => $this->section->id]);

        foreach ([[$this->english, $english], [$this->maths, $maths]] as [$subject, $marks]) {
            if ($marks !== null) {
                SubjectResult::factory()->create(['admission_id' => $admission->id, 'subject_id' => $subject->id, 'exam' => $exam, 'obtained_marks' => $marks]);
            }
        }

        return $admission;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function rows(ExamType $exam = ExamType::HalfYearly): array
    {
        return collect(app(ResultService::class)->sheet($this->section, $exam)['rows'])->keyBy('name')->all();
    }

    public function test_the_sheet_works_out_totals_percentages_grades_and_results(): void
    {
        $this->student('Ali', 80, 40);
        $this->student('Bilal', 30, 40);
        $this->student('Danish', 90, null);
        $this->student('Zara', null, null);

        $sheet = app(ResultService::class)->sheet($this->section, ExamType::HalfYearly);
        $rows = $this->rows();

        $this->assertSame(['English', 'Maths'], array_column($sheet['subjects'], 'name'));
        $this->assertSame([120.0, 150, 80.0, 'A+', 'pass'], [$rows['Ali KHAN']['obtained'], $rows['Ali KHAN']['total'], $rows['Ali KHAN']['percentage'], $rows['Ali KHAN']['grade'], $rows['Ali KHAN']['result']]);
        $this->assertSame([46.7, 'D', 'fail'], [$rows['Bilal KHAN']['percentage'], $rows['Bilal KHAN']['grade'], $rows['Bilal KHAN']['result']]);
        $this->assertFalse($rows['Bilal KHAN']['subjects'][$this->english->id]['passed']);
        $this->assertSame([90.0, 'incomplete'], [$rows['Danish KHAN']['percentage'], $rows['Danish KHAN']['result']]);
        $this->assertSame([null, null, 'no_marks'], [$rows['Zara KHAN']['percentage'], $rows['Zara KHAN']['grade'], $rows['Zara KHAN']['result']]);
        $this->assertSame(['students' => 4, 'withMarks' => 3, 'average' => 72.2, 'passed' => 1, 'failed' => 1, 'top' => 'Danish KHAN', 'weakest' => 'English'], [...$sheet['summary']]);
    }

    public function test_students_with_the_same_percentage_share_a_position(): void
    {
        $this->student('Ali', 80, 40);
        $this->student('Bilal', 80, 40);
        $this->student('Danish', 60, 30);
        $this->student('Zara', null, null);

        $positions = collect($this->rows())->map(fn (array $row) => $row['position'])->all();

        $this->assertSame(['Ali KHAN' => 1, 'Bilal KHAN' => 1, 'Danish KHAN' => 3, 'Zara KHAN' => null], $positions);
    }

    public function test_the_subject_analysis_counts_who_passed_and_failed_each_subject(): void
    {
        $this->student('Ali', 80, 40);
        $this->student('Bilal', 30, 10);
        $this->student('Danish', 50, 25);

        $analysis = collect(app(ResultService::class)->sheet($this->section, ExamType::HalfYearly)['analysis'])->keyBy('name');

        $this->assertSame([2, 1, 80.0, 53.3], [$analysis['English']['passed'], $analysis['English']['failed'], $analysis['English']['highest'], $analysis['English']['average']]);
        $this->assertSame([2, 1, 40.0, 50.0], [$analysis['Maths']['passed'], $analysis['Maths']['failed'], $analysis['Maths']['highest'], $analysis['Maths']['average']]);
    }

    public function test_the_exams_are_kept_apart(): void
    {
        $this->student('Ali', 80, 40, ExamType::Annual);

        $this->assertSame('no_marks', $this->rows(ExamType::HalfYearly)['Ali KHAN']['result']);
        $this->assertSame('pass', $this->rows(ExamType::Annual)['Ali KHAN']['result']);
    }

    public function test_class_assessments_count_for_their_weight_in_the_subject(): void
    {
        $ali = $this->student('Ali', 80, 40);
        $bilal = $this->student('Bilal', 80, 40);
        $quiz = Assessment::factory()->create(['section_id' => $this->section->id, 'subject_id' => $this->english->id, 'exam' => ExamType::HalfYearly, 'total_marks' => 20]);
        $test = Assessment::factory()->create(['section_id' => $this->section->id, 'subject_id' => $this->english->id, 'exam' => ExamType::HalfYearly, 'total_marks' => 20]);
        $otherTerm = Assessment::factory()->create(['section_id' => $this->section->id, 'subject_id' => $this->english->id, 'exam' => ExamType::Quarterly, 'total_marks' => 20]);
        AssessmentMark::factory()->create(['assessment_id' => $quiz->id, 'admission_id' => $ali->id, 'marks' => 10]);
        AssessmentMark::factory()->create(['assessment_id' => $test->id, 'admission_id' => $ali->id, 'marks' => 20]);
        AssessmentMark::factory()->create(['assessment_id' => $otherTerm->id, 'admission_id' => $ali->id, 'marks' => 0]);

        $this->assertSame(80.0, $this->rows()['Ali KHAN']['subjects'][$this->english->id]['percentage']);

        app(GradingService::class)->setFormativeWeight(20);

        $ali = $this->rows()['Ali KHAN'];
        $bilal = $this->rows()['Bilal KHAN'];

        $this->assertSame(75.0, $ali['subjects'][$this->english->id]['formative']);
        $this->assertSame(79.0, $ali['subjects'][$this->english->id]['percentage']);
        $this->assertSame(80.0, $ali['subjects'][$this->maths->id]['percentage']);
        $this->assertSame(80.0, $bilal['subjects'][$this->english->id]['percentage']);
        $this->assertSame(79.3, $ali['percentage']);
    }

    public function test_the_weight_does_not_change_whether_a_subject_is_passed(): void
    {
        $ali = $this->student('Ali', 30, 40);
        $quiz = Assessment::factory()->create(['section_id' => $this->section->id, 'subject_id' => $this->english->id, 'exam' => ExamType::HalfYearly, 'total_marks' => 20]);
        AssessmentMark::factory()->create(['assessment_id' => $quiz->id, 'admission_id' => $ali->id, 'marks' => 20]);
        app(GradingService::class)->setFormativeWeight(50);

        $row = $this->rows()['Ali KHAN'];

        $this->assertSame('fail', $row['result']);
        $this->assertFalse($row['subjects'][$this->english->id]['passed']);
    }

    public function test_students_who_left_are_left_out_of_the_sheet(): void
    {
        $this->student('Ali', 80, 40);
        $left = $this->student('Bilal', 80, 40);
        $left->update(['status' => 'left']);

        $this->assertSame(['Ali KHAN'], array_keys($this->rows()));
    }

    public function test_staff_see_the_summary_and_the_sheet_of_a_section(): void
    {
        $this->actingAsRole('secretary');
        $this->student('Ali', 80, 40);
        $this->student('Bilal', 30, 40);

        Livewire::test(ResultsMasterSheet::class)
            ->assertSuccessful()
            ->assertSee('Choose a section')
            ->fillForm(['section_id' => $this->section->id, 'exam' => ExamType::HalfYearly->value])
            ->assertSee(['Ali KHAN', 'Bilal KHAN', 'Class average', 'Weakest subject'])
            ->assertSee('Fail');
    }

    public function test_the_sheet_and_the_result_cards_download(): void
    {
        $this->actingAsRole('secretary');
        $this->student('Ali', 80, 40);
        $this->student('Bilal', 30, 40);

        $page = Livewire::test(ResultsMasterSheet::class)
            ->fillForm(['section_id' => $this->section->id, 'exam' => ExamType::HalfYearly->value])
            ->callAction(TestAction::make('download')->table())
            ->assertFileDownloaded()
            ->callAction(TestAction::make('resultCards')->table())
            ->assertFileDownloaded();

        $rows = $page->instance()->rows();
        $this->assertSame(['Position', 'Student', 'Admission No.', 'English (100)', 'Maths (50)', 'Total', 'Out of', '%', 'Grade', 'Result'], $rows[0]);
        $this->assertSame([1, 'Ali KHAN'], [$rows[1][0], $rows[1][1]]);
        $this->assertCount(3, $rows);
    }

    public function test_the_result_card_of_one_student_is_a_pdf(): void
    {
        $ali = $this->student('Ali', 80, 40);
        $this->student('Bilal', 30, 40);

        $response = app(ResultService::class)->cards($this->section, ExamType::HalfYearly, $ali);

        ob_start();
        $response->sendContent();
        $pdf = ob_get_clean();

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('result-card-half-yearly-ali-khan.pdf', $response->headers->get('content-disposition'));
    }

    public function test_teachers_only_see_the_sections_they_teach(): void
    {
        $teacher = Teacher::factory()->create();
        TeachingAssignment::factory()->create(['section_id' => $this->section->id, 'subject_id' => $this->english->id, 'teacher_id' => $teacher->id]);
        $other = Section::factory()->create(['campus_id' => $this->branch->id, 'year_id' => $this->year->id, 'name' => 'B']);
        $this->actingAs($teacher->user);
        $this->student('Ali', 80, 40);

        $this->assertTrue(ResultsMasterSheet::canAccess());

        Livewire::test(ResultsMasterSheet::class)
            ->fillForm(['section_id' => $this->section->id, 'exam' => ExamType::HalfYearly->value])
            ->assertSee('Ali KHAN')
            ->fillForm(['section_id' => $other->id])
            ->assertDontSee('Ali KHAN');
    }

    public function test_viewers_cannot_open_the_master_sheet(): void
    {
        $this->actingAsRole('viewer');

        $this->get(ResultsMasterSheet::getUrl())->assertForbidden();
    }
}
