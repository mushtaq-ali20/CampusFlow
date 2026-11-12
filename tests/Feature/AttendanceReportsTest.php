<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
use App\Enums\ClassAttendanceStatus;
use App\Filament\Pages\ClassAttendanceReports;
use App\Models\Admission;
use App\Models\Campus;
use App\Models\ClassAttendance;
use App\Models\Section;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Year;
use App\Services\AttendanceReportService;
use Database\Seeders\PermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceReportsTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private Campus $branch;

    private Section $sectionA;

    private Section $sectionB;

    /** @var list<Admission> */
    private array $studentsA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);
        $this->travelTo('2026-11-11 10:00');

        $this->year = Year::factory()->create(['name' => '2026-2027']);
        $this->branch = Campus::factory()->create(['name' => 'Gulshan']);
        $this->sectionA = Section::factory()->create(['campus_id' => $this->branch->id, 'year_id' => $this->year->id, 'name' => 'A']);
        $this->sectionB = Section::factory()->create(['campus_id' => $this->branch->id, 'year_id' => $this->year->id, 'name' => 'B']);
        $this->studentsA = Admission::factory()->count(4)->create(['section_id' => $this->sectionA->id])->all();
        Admission::factory()->count(2)->create(['section_id' => $this->sectionB->id]);
    }

    private function actingAsRole(string $role, ?Campus $branch = null): User
    {
        $user = User::factory()->create(['campus_id' => $branch?->id]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function mark(Admission $admission, string $date, ClassAttendanceStatus $status): ClassAttendance
    {
        return ClassAttendance::factory()->create(['admission_id' => $admission->id, 'date' => $date, 'status' => $status]);
    }

    /**
     * Today in section A: two present, one late, one absent. Section B has not submitted.
     */
    private function markToday(): void
    {
        $this->mark($this->studentsA[0], '2026-11-11', ClassAttendanceStatus::Present);
        $this->mark($this->studentsA[1], '2026-11-11', ClassAttendanceStatus::Present);
        $this->mark($this->studentsA[2], '2026-11-11', ClassAttendanceStatus::Late);
        $this->mark($this->studentsA[3], '2026-11-11', ClassAttendanceStatus::Absent);
    }

    public function test_the_class_wise_report_counts_each_section_on_the_day_and_finds_those_not_submitted(): void
    {
        $this->markToday();
        $this->mark($this->studentsA[3], '2026-11-10', ClassAttendanceStatus::Present);

        $sections = app(AttendanceReportService::class)->classWise(Carbon::parse('2026-11-11'), null)->get()->keyBy('id');

        $a = $sections[$this->sectionA->id];
        $this->assertSame([4, 4, 2, 1, 1, 0], [$a->students_count, $a->recorded_count, $a->present_count, $a->absent_count, $a->late_count, $a->leave_count]);
        $this->assertSame(75.0, AttendanceReportService::percentage($a->present_count, $a->late_count, $a->recorded_count));
        $this->assertSame(0, $sections[$this->sectionB->id]->recorded_count);
        $this->assertNull(AttendanceReportService::percentage(0, 0, 0));
    }

    public function test_sections_without_active_students_are_left_out(): void
    {
        $empty = Section::factory()->create(['campus_id' => $this->branch->id, 'year_id' => $this->year->id]);
        Admission::factory()->create(['section_id' => $empty->id, 'status' => AdmissionStatus::Left]);

        $ids = app(AttendanceReportService::class)->classWise(now(), null)->pluck('id');

        $this->assertNotContains($empty->id, $ids);
        $this->assertContains($this->sectionA->id, $ids);
    }

    public function test_the_absentees_and_late_comers_reports_list_the_period_and_the_section_asked(): void
    {
        $this->markToday();
        $earlier = $this->mark($this->studentsA[0], '2026-11-05', ClassAttendanceStatus::Absent);
        $tooEarly = $this->mark($this->studentsA[1], '2026-10-30', ClassAttendanceStatus::Absent);
        $sectionB = $this->mark(Admission::query()->where('section_id', $this->sectionB->id)->first(), '2026-11-11', ClassAttendanceStatus::Absent);
        $service = app(AttendanceReportService::class);

        $absent = $service->withStatus(ClassAttendanceStatus::Absent, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-11'), null)->pluck('id');
        $absentInA = $service->withStatus(ClassAttendanceStatus::Absent, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-11'), null, $this->sectionA->id)->pluck('id');
        $late = $service->withStatus(ClassAttendanceStatus::Late, Carbon::parse('2026-11-11'), Carbon::parse('2026-11-11'), null)->get();

        $this->assertContains($earlier->id, $absent);
        $this->assertContains($sectionB->id, $absent);
        $this->assertNotContains($tooEarly->id, $absent);
        $this->assertNotContains($sectionB->id, $absentInA);
        $this->assertCount(3, $absent);
        $this->assertCount(1, $late);
        $this->assertTrue($late->first()->admission->is($this->studentsA[2]));
    }

    public function test_the_monthly_summary_counts_the_days_of_each_student_in_the_month(): void
    {
        $student = $this->studentsA[0];
        $this->mark($student, '2026-11-02', ClassAttendanceStatus::Present);
        $this->mark($student, '2026-11-03', ClassAttendanceStatus::Late);
        $this->mark($student, '2026-11-04', ClassAttendanceStatus::Absent);
        $this->mark($student, '2026-11-05', ClassAttendanceStatus::Leave);
        $this->mark($student, '2026-10-31', ClassAttendanceStatus::Absent);

        $row = app(AttendanceReportService::class)->monthly(Carbon::parse('2026-11-01'), null, $this->sectionA->id)->get()->firstWhere('id', $student->id);

        $this->assertSame([4, 1, 1, 1, 1], [$row->recorded_count, $row->present_count, $row->absent_count, $row->late_count, $row->leave_count]);
        $this->assertSame(50.0, AttendanceReportService::percentage($row->present_count, $row->late_count, $row->recorded_count));
    }

    public function test_branch_staff_only_see_their_branch_in_every_report(): void
    {
        $otherBranch = Campus::factory()->create();
        $otherSection = Section::factory()->create(['campus_id' => $otherBranch->id, 'year_id' => $this->year->id]);
        $otherStudent = Admission::factory()->create(['section_id' => $otherSection->id]);
        $otherAbsence = $this->mark($otherStudent, '2026-11-11', ClassAttendanceStatus::Absent);
        $user = $this->actingAsRole('secretary', $this->branch);
        $service = app(AttendanceReportService::class);

        $this->assertNotContains($otherSection->id, $service->classWise(now(), $user)->pluck('id'));
        $this->assertNotContains($otherAbsence->id, $service->withStatus(ClassAttendanceStatus::Absent, now(), now(), $user)->pluck('id'));
        $this->assertNotContains($otherStudent->id, $service->monthly(now(), $user)->pluck('id'));
    }

    public function test_staff_switch_between_the_reports_on_the_page(): void
    {
        $this->actingAsRole('secretary');
        $this->markToday();
        $absence = ClassAttendance::query()->where('status', ClassAttendanceStatus::Absent)->sole();

        Livewire::test(ClassAttendanceReports::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$this->sectionA, $this->sectionB])
            ->assertSee(['Submitted', 'Not submitted', '75%'])
            ->set('filters.report', ClassAttendanceReports::ABSENTEES)
            ->assertCanSeeTableRecords([$absence])
            ->set('filters.report', ClassAttendanceReports::MONTHLY)
            ->set('filters.section_id', $this->sectionA->id)
            ->assertCanSeeTableRecords($this->studentsA)
            ->assertCountTableRecords(4);
    }

    public function test_a_report_downloads_as_a_csv_file_for_excel(): void
    {
        $this->actingAsRole('secretary');
        $this->markToday();

        $page = Livewire::test(ClassAttendanceReports::class)
            ->callAction(TestAction::make('download')->table())
            ->assertFileDownloaded('attendance-class-wise-2026-11-11.csv');

        $rows = $page->instance()->rows();
        $this->assertSame(['Branch', 'Section', 'Class teacher', 'Students', 'Present', 'Absent', 'Late', 'Leave', 'Attendance %', 'Submitted'], $rows[0]);
        $this->assertCount(3, $rows);
        $bySection = collect($rows)->keyBy(1);
        $sectionA = $this->sectionA->fresh()->full_name;
        $this->assertSame(['Gulshan', $sectionA, null, 4, 2, 1, 1, 0, 75.0, 'Submitted'], $bySection[$sectionA]);
        $this->assertSame('Not submitted', $bySection[$this->sectionB->fresh()->full_name][9]);
    }

    public function test_staff_with_the_attendance_permission_open_the_reports(): void
    {
        $this->actingAsRole('secretary');

        $this->get(ClassAttendanceReports::getUrl())->assertSuccessful();
    }

    public function test_viewers_cannot_open_the_reports(): void
    {
        $this->actingAsRole('viewer');

        $this->get(ClassAttendanceReports::getUrl())->assertForbidden();
    }

    public function test_teachers_cannot_open_the_reports(): void
    {
        $this->actingAs(Teacher::factory()->create()->user);

        $this->assertFalse(ClassAttendanceReports::canAccess());
        $this->get(ClassAttendanceReports::getUrl())->assertForbidden();
    }
}
