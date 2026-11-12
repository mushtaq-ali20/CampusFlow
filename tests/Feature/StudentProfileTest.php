<?php

namespace Tests\Feature;

use App\Enums\ClassAttendanceStatus;
use App\Enums\ExamType;
use App\Enums\VoucherStatus;
use App\Filament\Resources\Students\Pages\ViewStudent;
use App\Filament\Resources\Students\RelationManagers\ClassAttendancesRelationManager;
use App\Filament\Resources\Students\RelationManagers\DisciplineRecordsRelationManager;
use App\Filament\Resources\Students\RelationManagers\FeeVouchersRelationManager;
use App\Filament\Resources\Students\RelationManagers\ParentMessagesRelationManager;
use App\Filament\Resources\Students\RelationManagers\SubjectResultsRelationManager;
use App\Filament\Resources\Students\Widgets\StudentOverview;
use App\Models\Admission;
use App\Models\Campus;
use App\Models\ClassAttendance;
use App\Models\DisciplineCategory;
use App\Models\DisciplineRecord;
use App\Models\FeeVoucher;
use App\Models\ParentMessage;
use App\Models\Period;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectResult;
use App\Models\User;
use App\Models\Year;
use App\Services\StudentOverviewService;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StudentProfileTest extends TestCase
{
    use RefreshDatabase;

    private Campus $branch;

    private Student $student;

    private Admission $admission;

    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);
        Period::factory()->create();

        $year = Year::factory()->create(['name' => '2026-2027']);
        $this->branch = Campus::factory()->create(['name' => 'Gulshan']);
        $this->class = SchoolClass::factory()->create(['name' => 'Class 5']);
        $section = Section::factory()->create(['campus_id' => $this->branch->id, 'school_class_id' => $this->class->id, 'year_id' => $year->id, 'name' => 'A']);
        $this->student = Student::factory()->create();
        $this->admission = Admission::factory()->create(['student_id' => $this->student->id, 'section_id' => $section->id]);
    }

    private function actingAsRole(string $role, ?Campus $branch = null): User
    {
        $user = User::factory()->create(['campus_id' => $branch?->id]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function attendance(string $date, ClassAttendanceStatus $status, ?Admission $admission = null): ClassAttendance
    {
        return ClassAttendance::factory()->create(['admission_id' => ($admission ?? $this->admission)->id, 'date' => $date, 'status' => $status]);
    }

    public function test_the_summary_counts_the_attendance_of_the_current_admission(): void
    {
        $this->attendance('2026-11-02', ClassAttendanceStatus::Present);
        $this->attendance('2026-11-03', ClassAttendanceStatus::Late);
        $this->attendance('2026-11-04', ClassAttendanceStatus::Absent);
        $this->attendance('2026-11-05', ClassAttendanceStatus::Leave);

        $summary = app(StudentOverviewService::class)->summary($this->student, null);

        $this->assertTrue($summary['admission']->is($this->admission));
        $this->assertSame(['attended' => 2, 'total' => 4, 'percentage' => 50.0], $summary['attendance']);
    }

    public function test_the_summary_shows_the_latest_exam_with_marks_out_of_the_total_marks_of_the_class(): void
    {
        $english = Subject::factory()->create(['name' => 'English']);
        $maths = Subject::factory()->create(['name' => 'Maths']);
        $this->class->subjects()->attach($english, ['total_marks' => 100, 'passing_marks' => 33]);
        $this->class->subjects()->attach($maths, ['total_marks' => 50, 'passing_marks' => 17]);

        SubjectResult::factory()->create(['admission_id' => $this->admission->id, 'subject_id' => $english->id, 'exam' => ExamType::Quarterly, 'obtained_marks' => 40]);
        SubjectResult::factory()->create(['admission_id' => $this->admission->id, 'subject_id' => $english->id, 'exam' => ExamType::HalfYearly, 'obtained_marks' => 80]);
        SubjectResult::factory()->create(['admission_id' => $this->admission->id, 'subject_id' => $maths->id, 'exam' => ExamType::HalfYearly, 'obtained_marks' => 43]);

        $exam = app(StudentOverviewService::class)->summary($this->student, null)['exam'];

        $this->assertSame(ExamType::HalfYearly, $exam['exam']);
        $this->assertSame(82.0, $exam['percentage']);
    }

    public function test_the_summary_adds_up_the_fee_still_due_and_the_behaviour_points(): void
    {
        FeeVoucher::factory()->create(['admission_id' => $this->admission->id, 'voucher_number' => 'V-202610-1', 'month' => '2026-10-01', 'net_amount' => 6500, 'paid_amount' => 6500, 'status' => VoucherStatus::Paid]);
        FeeVoucher::factory()->create(['admission_id' => $this->admission->id, 'voucher_number' => 'V-202611-1', 'month' => '2026-11-01', 'net_amount' => 6500, 'paid_amount' => 2000, 'status' => VoucherStatus::Partial]);
        DisciplineRecord::factory()->create(['admission_id' => $this->admission->id, 'discipline_category_id' => DisciplineCategory::factory()->merit()->create(['points' => 2])->id]);
        DisciplineRecord::factory()->create(['admission_id' => $this->admission->id, 'discipline_category_id' => DisciplineCategory::factory()->create(['points' => 5])->id]);

        $summary = app(StudentOverviewService::class)->summary($this->student, null);

        $this->assertSame(4500, $summary['feeBalance']);
        $this->assertSame(-3, $summary['disciplinePoints']);
    }

    public function test_a_student_without_admission_has_an_empty_summary(): void
    {
        $summary = app(StudentOverviewService::class)->summary(Student::factory()->create(), null);

        $this->assertNull($summary['admission']);
        $this->assertNull($summary['attendance']['percentage']);
        $this->assertNull($summary['exam']['exam']);
        $this->assertSame(0, $summary['feeBalance']);
        $this->assertSame(0, $summary['disciplinePoints']);
    }

    public function test_branch_staff_only_count_the_admissions_of_their_branch(): void
    {
        $otherBranch = Campus::factory()->create();
        $nextYear = Year::factory()->create(['name' => '2027-2028']);
        $otherSection = Section::factory()->create(['campus_id' => $otherBranch->id, 'year_id' => $nextYear->id]);
        $laterAdmission = Admission::factory()->create(['student_id' => $this->student->id, 'section_id' => $otherSection->id]);
        FeeVoucher::factory()->create(['admission_id' => $laterAdmission->id, 'net_amount' => 9000]);
        FeeVoucher::factory()->create(['admission_id' => $this->admission->id, 'net_amount' => 1000]);
        $user = $this->actingAsRole('secretary', $this->branch);

        $summary = app(StudentOverviewService::class)->summary($this->student, $user);

        $this->assertTrue($summary['admission']->is($this->admission));
        $this->assertSame(1000, $summary['feeBalance']);
        $this->assertTrue(app(StudentOverviewService::class)->summary($this->student, null)['admission']->is($laterAdmission));
    }

    public function test_the_profile_shows_the_summary_and_the_record_tabs(): void
    {
        $this->actingAsRole('secretary');
        $this->attendance('2026-11-02', ClassAttendanceStatus::Present);

        $this->get(ViewStudent::getUrl(['record' => $this->student->id]))
            ->assertSuccessful()
            ->assertSeeLivewire(StudentOverview::class)
            ->assertSee(['Attendance this year', 'Fee balance', 'Behaviour points'])
            ->assertSee(['Results', 'Discipline', 'Messages to parents']);

        Livewire::test(StudentOverview::class, ['record' => $this->student])
            ->assertSee('Class 5 - A')
            ->assertSee('100%');
    }

    public function test_each_tab_lists_the_records_of_the_student(): void
    {
        $this->actingAsRole('admin');
        $otherAdmission = Admission::factory()->create();
        $attendance = $this->attendance('2026-11-02', ClassAttendanceStatus::Absent);
        $otherAttendance = $this->attendance('2026-11-02', ClassAttendanceStatus::Absent, $otherAdmission);
        $subject = Subject::factory()->create();
        $this->class->subjects()->attach($subject, ['total_marks' => 100, 'passing_marks' => 33]);
        $result = SubjectResult::factory()->create(['admission_id' => $this->admission->id, 'subject_id' => $subject->id, 'obtained_marks' => 30]);
        $voucher = FeeVoucher::factory()->create(['admission_id' => $this->admission->id]);
        $record = DisciplineRecord::factory()->create(['admission_id' => $this->admission->id]);
        $message = ParentMessage::factory()->create(['student_id' => $this->student->id, 'campus_id' => $this->branch->id]);
        $otherBranchMessage = ParentMessage::factory()->create(['student_id' => $this->student->id, 'campus_id' => Campus::factory()->create()->id]);
        $owner = ['ownerRecord' => $this->student, 'pageClass' => ViewStudent::class];

        Livewire::test(ClassAttendancesRelationManager::class, $owner)
            ->assertCanSeeTableRecords([$attendance])
            ->assertCanNotSeeTableRecords([$otherAttendance]);
        Livewire::test(SubjectResultsRelationManager::class, $owner)
            ->assertCanSeeTableRecords([$result])
            ->assertSee('30 / 100')
            ->assertSee('Fail');
        Livewire::test(FeeVouchersRelationManager::class, $owner)->assertCanSeeTableRecords([$voucher]);
        Livewire::test(DisciplineRecordsRelationManager::class, $owner)->assertCanSeeTableRecords([$record]);
        Livewire::test(ParentMessagesRelationManager::class, $owner)->assertCanSeeTableRecords([$message, $otherBranchMessage]);
    }

    public function test_branch_staff_see_the_messages_of_their_branch_only(): void
    {
        $this->actingAsRole('secretary', $this->branch);
        $ours = ParentMessage::factory()->create(['student_id' => $this->student->id, 'campus_id' => $this->branch->id]);
        $withoutBranch = ParentMessage::factory()->create(['student_id' => $this->student->id, 'campus_id' => null]);
        $theirs = ParentMessage::factory()->create(['student_id' => $this->student->id, 'campus_id' => Campus::factory()->create()->id]);

        Livewire::test(ParentMessagesRelationManager::class, ['ownerRecord' => $this->student, 'pageClass' => ViewStudent::class])
            ->assertCanSeeTableRecords([$ours, $withoutBranch])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_tabs_are_shown_only_to_staff_allowed_to_see_them(): void
    {
        $this->actingAsRole('viewer')->givePermissionTo(['enrollments.view', 'attendance.view']);

        $this->assertTrue(ClassAttendancesRelationManager::canViewForRecord($this->student, ViewStudent::class));
        $this->assertFalse(SubjectResultsRelationManager::canViewForRecord($this->student, ViewStudent::class));
        $this->assertFalse(FeeVouchersRelationManager::canViewForRecord($this->student, ViewStudent::class));
        $this->assertFalse(DisciplineRecordsRelationManager::canViewForRecord($this->student, ViewStudent::class));
        $this->assertFalse(ParentMessagesRelationManager::canViewForRecord($this->student, ViewStudent::class));

        $this->get(ViewStudent::getUrl(['record' => $this->student->id]))
            ->assertSuccessful()
            ->assertDontSee('Messages to parents');
    }
}
