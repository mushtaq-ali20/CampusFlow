<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Filament\Resources\LeaveRequests\Pages\ManageLeaveRequests;
use App\Models\Campus;
use App\Models\EmployeeProfile;
use App\Models\Leave;
use App\Models\LeaveAllowance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Teacher;
use App\Models\User;
use App\Services\LeaveRequestService;
use Database\Seeders\PermissionsSeeder;
use DomainException;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class LeaveRequestTest extends TestCase
{
    use RefreshDatabase;

    private Campus $branch;

    private LeaveType $casual;

    private LeaveType $unpaid;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);

        $this->branch = Campus::factory()->create();
        $this->casual = LeaveType::factory()->create(['name' => 'Casual']);
        $this->unpaid = LeaveType::factory()->create(['name' => 'Unpaid']);
        LeaveAllowance::query()->create(['leave_type_id' => $this->unpaid->id, 'days_per_year' => 0, 'is_paid' => false]);
    }

    private function employee(string $firstname = 'Sana'): User
    {
        $user = User::factory()->create(['firstname' => $firstname, 'lastname' => 'Raza', 'campus_id' => null]);
        EmployeeProfile::factory()->create(['user_id' => $user->id, 'campus_id' => $this->branch->id]);

        return $user;
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        return $admin;
    }

    private function service(): LeaveRequestService
    {
        return app(LeaveRequestService::class);
    }

    public function test_days_off_do_not_count_as_leave(): void
    {
        $this->assertSame(6, $this->service()->workingDays(Carbon::parse('2026-03-09'), Carbon::parse('2026-03-15')));
        $this->assertSame(0, $this->service()->workingDays(Carbon::parse('2026-03-15'), Carbon::parse('2026-03-15')));

        config(['campusflow.weekly_off_days' => [0, 6]]);

        $this->assertSame(5, $this->service()->workingDays(Carbon::parse('2026-03-09'), Carbon::parse('2026-03-15')));
    }

    public function test_an_employee_asks_for_leave_and_it_waits_for_approval(): void
    {
        $sana = $this->employee();

        $request = $this->service()->request($sana, $this->casual, Carbon::parse('2026-03-09'), Carbon::parse('2026-03-14'), 'Wedding');

        $this->assertSame([RequestStatus::Pending, 6, $this->branch->id], [$request->status, $request->days, $request->campus_id]);
    }

    public function test_a_request_needs_an_employee_file_valid_dates_and_free_days(): void
    {
        $sana = $this->employee();
        $this->service()->request($sana, $this->casual, Carbon::parse('2026-03-10'), Carbon::parse('2026-03-11'));

        foreach ([
            [User::factory()->create(), '2026-03-10', '2026-03-11', 'no employee file'],
            [$sana, '2026-03-12', '2026-03-11', 'before the first'],
            [$sana, '2026-03-15', '2026-03-15', 'days off'],
            [$sana, '2026-03-11', '2026-03-13', 'already a leave'],
        ] as [$user, $from, $to, $message]) {
            try {
                $this->service()->request($user, $this->casual, Carbon::parse($from), Carbon::parse($to));
                $this->fail("A request should have been refused: {$message}");
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(1, LeaveRequest::query()->count());
    }

    public function test_a_leave_type_with_an_allowance_cannot_be_overdrawn(): void
    {
        $sana = $this->employee();
        LeaveAllowance::query()->create(['leave_type_id' => $this->casual->id, 'days_per_year' => 3, 'is_paid' => true]);
        $first = $this->service()->request($sana, $this->casual, Carbon::parse('2026-03-09'), Carbon::parse('2026-03-10'));
        $this->service()->approve($first, $this->admin());

        $this->assertSame(['allowed' => 3, 'used' => 2, 'remaining' => 1, 'paid' => true], $this->service()->balance($sana, $this->casual, 2026));

        $this->expectException(DomainException::class);
        $this->service()->request($sana, $this->casual, Carbon::parse('2026-03-16'), Carbon::parse('2026-03-17'));
    }

    public function test_approving_the_leave_of_a_teacher_puts_the_days_on_the_timetable(): void
    {
        $teacher = Teacher::factory()->create();
        EmployeeProfile::factory()->create(['user_id' => $teacher->id, 'campus_id' => $this->branch->id]);
        $request = $this->service()->request($teacher->user, $this->casual, Carbon::parse('2026-03-13'), Carbon::parse('2026-03-16'));

        $this->service()->approve($request, $this->admin());

        $this->assertSame(['2026-03-13', '2026-03-14', '2026-03-16'], Leave::query()->where('teacher_id', $teacher->id)->orderBy('date')->get()->map(fn (Leave $leave) => Carbon::parse($leave->date)->format('Y-m-d'))->all());
        $this->assertSame(RequestStatus::Approved, $request->refresh()->status);
    }

    public function test_approving_the_leave_of_other_staff_adds_nothing_to_the_timetable(): void
    {
        $request = $this->service()->request($this->employee(), $this->casual, Carbon::parse('2026-03-10'), Carbon::parse('2026-03-11'));

        $this->service()->approve($request, $this->admin());

        $this->assertSame(0, Leave::query()->count());
    }

    public function test_a_request_is_decided_once(): void
    {
        $request = $this->service()->request($this->employee(), $this->casual, Carbon::parse('2026-03-10'), Carbon::parse('2026-03-11'));
        $admin = $this->admin();

        $rejected = $this->service()->reject($request, $admin, 'Exams week');

        $this->assertSame([RequestStatus::Rejected, 'Exams week', $admin->id], [$rejected->status, $rejected->decision_note, $rejected->decided_by]);

        $this->expectException(DomainException::class);
        $this->service()->approve($rejected, $admin);
    }

    public function test_a_pending_request_can_be_withdrawn_but_not_a_decided_one(): void
    {
        $sana = $this->employee();
        $pending = $this->service()->request($sana, $this->casual, Carbon::parse('2026-03-10'), Carbon::parse('2026-03-10'));
        $approved = $this->service()->request($sana, $this->casual, Carbon::parse('2026-03-11'), Carbon::parse('2026-03-11'));
        $this->service()->approve($approved, $this->admin());

        $this->service()->cancel($pending);

        $this->assertModelMissing($pending);

        $this->expectException(DomainException::class);
        $this->service()->cancel($approved);
    }

    public function test_only_approved_unpaid_leave_counts_against_the_month(): void
    {
        $sana = $this->employee();
        $admin = $this->admin();
        $across = $this->service()->request($sana, $this->unpaid, Carbon::parse('2026-03-30'), Carbon::parse('2026-04-02'));
        $paid = $this->service()->request($sana, $this->casual, Carbon::parse('2026-03-02'), Carbon::parse('2026-03-03'));
        $waiting = $this->service()->request($sana, $this->unpaid, Carbon::parse('2026-03-05'), Carbon::parse('2026-03-05'));
        $this->service()->approve($across, $admin);
        $this->service()->approve($paid, $admin);

        $this->assertSame(2, $this->service()->unpaidDays($sana, Carbon::parse('2026-03-01')));
        $this->assertSame(2, $this->service()->unpaidDays($sana, Carbon::parse('2026-04-01')));
        $this->assertSame(0, $this->service()->unpaidDays($sana, Carbon::parse('2026-05-01')));
        $this->assertSame(RequestStatus::Pending, $waiting->refresh()->status);
    }

    public function test_employees_ask_for_leave_and_only_see_their_own_requests(): void
    {
        $sana = $this->employee();
        $other = $this->employee('Hina');
        LeaveRequest::factory()->create(['user_id' => $other->id, 'campus_id' => $this->branch->id, 'leave_type_id' => $this->casual->id]);
        $this->actingAs($sana);

        $this->assertTrue(LeaveRequestResource::canAccess());

        Livewire::test(ManageLeaveRequests::class)
            ->assertCountTableRecords(0)
            ->callAction('create', data: ['user_id' => $sana->id, 'leave_type_id' => $this->casual->id, 'from_date' => '2026-03-10', 'to_date' => '2026-03-11', 'reason' => 'Fever'])
            ->assertHasNoFormErrors()
            ->assertCountTableRecords(1);

        $this->assertDatabaseHas('leave_requests', ['user_id' => $sana->id, 'days' => 2, 'status' => 'pending']);
    }

    public function test_an_employee_cannot_ask_for_leave_for_somebody_else(): void
    {
        $sana = $this->employee();
        $other = $this->employee('Hina');
        $this->actingAs($sana);

        Livewire::test(ManageLeaveRequests::class)
            ->callAction('create', data: ['user_id' => $other->id, 'leave_type_id' => $this->casual->id, 'from_date' => '2026-03-10', 'to_date' => '2026-03-11']);

        $this->assertSame(0, LeaveRequest::query()->count());
    }

    public function test_a_refused_request_shows_the_reason(): void
    {
        $sana = $this->employee();
        LeaveAllowance::query()->create(['leave_type_id' => $this->casual->id, 'days_per_year' => 1, 'is_paid' => true]);
        $this->actingAs($sana);

        Livewire::test(ManageLeaveRequests::class)
            ->callAction('create', data: ['user_id' => $sana->id, 'leave_type_id' => $this->casual->id, 'from_date' => '2026-03-10', 'to_date' => '2026-03-11'])
            ->assertNotified(__('Only :days days of :type are left this year.', ['days' => 1, 'type' => 'Casual']));

        $this->assertSame(0, LeaveRequest::query()->count());
    }

    public function test_only_staff_who_manage_the_employees_decide_requests(): void
    {
        $sana = $this->employee();
        $request = LeaveRequest::factory()->create(['user_id' => $sana->id, 'campus_id' => $this->branch->id, 'leave_type_id' => $this->casual->id]);
        $this->actingAs($sana);

        Livewire::test(ManageLeaveRequests::class)
            ->assertActionHidden(TestAction::make('approve')->table($request))
            ->assertActionVisible(TestAction::make('cancel')->table($request));

        $this->admin();

        Livewire::test(ManageLeaveRequests::class)
            ->callAction(TestAction::make('approve')->table($request));

        $this->assertSame(RequestStatus::Approved, $request->refresh()->status);
    }

    public function test_staff_see_the_requests_of_the_active_branch_only(): void
    {
        $sana = $this->employee();
        LeaveRequest::factory()->create(['user_id' => $sana->id, 'campus_id' => $this->branch->id, 'leave_type_id' => $this->casual->id]);
        $otherBranch = Campus::factory()->create();
        $stranger = User::factory()->create(['campus_id' => null]);
        EmployeeProfile::factory()->create(['user_id' => $stranger->id, 'campus_id' => $otherBranch->id]);
        LeaveRequest::factory()->create(['user_id' => $stranger->id, 'campus_id' => $otherBranch->id, 'leave_type_id' => $this->casual->id]);
        $hr = User::factory()->create(['campus_id' => $this->branch->id]);
        $hr->givePermissionTo('employees.view');
        $this->actingAs($hr);

        Livewire::test(ManageLeaveRequests::class)->assertCountTableRecords(1);
    }

    public function test_people_without_an_employee_file_or_permission_cannot_open_the_screen(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertFalse(LeaveRequestResource::canAccess());
    }
}
