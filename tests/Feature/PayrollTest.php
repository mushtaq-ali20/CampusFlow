<?php

namespace Tests\Feature;

use App\Enums\EmployeeStatus;
use App\Enums\PayItemKind;
use App\Enums\PaymentMethod;
use App\Enums\PayrollStatus;
use App\Enums\RequestStatus;
use App\Filament\Resources\PayrollRuns\Pages\ListPayrollRuns;
use App\Filament\Resources\PayrollRuns\Pages\ViewPayrollRun;
use App\Filament\Resources\PayrollRuns\PayrollRunResource;
use App\Filament\Resources\PayrollRuns\RelationManagers\SlipsRelationManager;
use App\Models\Campus;
use App\Models\EmployeePayItem;
use App\Models\EmployeeProfile;
use App\Models\LeaveAllowance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollRun;
use App\Models\SalarySlip;
use App\Models\User;
use App\Services\LeaveRequestService;
use App\Services\PayrollService;
use Database\Seeders\PermissionsSeeder;
use DomainException;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    private Campus $branch;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);

        $this->branch = Campus::factory()->create();
        $this->admin = User::factory()->create(['campus_id' => null]);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
    }

    private function employee(string $firstname, int $basic = 30000, array $attributes = []): EmployeeProfile
    {
        $user = User::factory()->create(['firstname' => $firstname, 'lastname' => 'Raza', 'campus_id' => null]);

        return EmployeeProfile::factory()->create([...$attributes, 'user_id' => $user->id, 'campus_id' => $attributes['campus_id'] ?? $this->branch->id, 'basic_salary' => $basic]);
    }

    private function service(): PayrollService
    {
        return app(PayrollService::class);
    }

    private function generate(string $month = '2026-03'): PayrollRun
    {
        return $this->service()->generate($this->branch, $month, $this->admin);
    }

    private function slipOf(PayrollRun $run, EmployeeProfile $employee): SalarySlip
    {
        return $run->slips()->where('employee_profile_id', $employee->id)->firstOrFail();
    }

    public function test_a_slip_is_the_basic_salary_plus_the_allowances_minus_the_fixed_deductions(): void
    {
        $sana = $this->employee('Sana', 30000);
        EmployeePayItem::query()->create(['employee_profile_id' => $sana->id, 'kind' => PayItemKind::Earning, 'name' => 'House rent', 'amount' => 5000]);
        EmployeePayItem::query()->create(['employee_profile_id' => $sana->id, 'kind' => PayItemKind::Deduction, 'name' => 'Advance recovery', 'amount' => 1500]);
        EmployeePayItem::query()->create(['employee_profile_id' => $sana->id, 'kind' => PayItemKind::Deduction, 'name' => 'Welfare fund', 'amount' => 500]);

        $slip = $this->slipOf($this->generate(), $sana);

        $this->assertSame([30000, 5000, 2000, 0, 0, 33000], [$slip->basic_salary, $slip->allowances_total, $slip->deductions_total, $slip->unpaid_days, $slip->unpaid_deduction, $slip->net_salary]);
        $this->assertSame(['House rent', 'Advance recovery', 'Welfare fund'], $slip->items->pluck('name')->all());
        $this->assertSame('SL-202603-'.$this->branch->id.'-001', $slip->slip_number);
    }

    public function test_unpaid_leave_is_deducted_from_the_gross_salary_by_the_day(): void
    {
        $sana = $this->employee('Sana', 30000);
        EmployeePayItem::query()->create(['employee_profile_id' => $sana->id, 'kind' => PayItemKind::Earning, 'name' => 'House rent', 'amount' => 5000]);
        EmployeePayItem::query()->create(['employee_profile_id' => $sana->id, 'kind' => PayItemKind::Deduction, 'name' => 'Advance recovery', 'amount' => 1000]);
        $unpaid = LeaveType::factory()->create();
        LeaveAllowance::query()->create(['leave_type_id' => $unpaid->id, 'days_per_year' => 0, 'is_paid' => false]);
        $request = app(LeaveRequestService::class)->request($sana->user, $unpaid, Carbon::parse('2026-03-09'), Carbon::parse('2026-03-11'));
        app(LeaveRequestService::class)->approve($request, $this->admin);

        $slip = $this->slipOf($this->generate(), $sana);

        $this->assertSame([3, 3500, 30500], [$slip->unpaid_days, $slip->unpaid_deduction, $slip->net_salary]);
    }

    public function test_the_net_salary_never_goes_below_zero(): void
    {
        $sana = $this->employee('Sana', 10000);
        EmployeePayItem::query()->create(['employee_profile_id' => $sana->id, 'kind' => PayItemKind::Deduction, 'name' => 'Advance recovery', 'amount' => 15000]);

        $this->assertSame(0, $this->slipOf($this->generate(), $sana)->net_salary);
    }

    public function test_only_the_employees_of_the_branch_who_worked_that_month_get_a_slip(): void
    {
        $working = $this->employee('Working');
        $joinedLater = $this->employee('Later', 30000, ['joined_on' => '2026-04-01']);
        $leftBefore = $this->employee('Gone', 30000, ['status' => EmployeeStatus::Left, 'left_on' => '2026-02-28']);
        $leftDuring = $this->employee('Leaving', 30000, ['status' => EmployeeStatus::Left, 'left_on' => '2026-03-15']);
        $elsewhere = $this->employee('Other', 30000, ['campus_id' => Campus::factory()->create()->id]);

        $employees = $this->generate()->slips->pluck('employee_profile_id')->all();

        $this->assertEqualsCanonicalizing([$working->id, $leftDuring->id], $employees);
        $this->assertNotContains($joinedLater->id, $employees);
        $this->assertNotContains($leftBefore->id, $employees);
        $this->assertNotContains($elsewhere->id, $employees);
    }

    public function test_a_month_is_made_once_and_not_ahead_of_time(): void
    {
        $this->employee('Sana');
        $this->generate();

        foreach (['2026-03', '2999-01', 'march'] as $month) {
            try {
                $this->service()->generate($this->branch, $month, $this->admin);
                $this->fail("The month {$month} should have been refused.");
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(1, PayrollRun::query()->count());
    }

    public function test_a_branch_without_employees_has_nothing_to_pay(): void
    {
        $this->expectException(DomainException::class);

        $this->generate();
    }

    public function test_a_draft_takes_one_off_lines_and_works_out_the_totals_again(): void
    {
        $sana = $this->employee('Sana', 30000);
        $slip = $this->slipOf($this->generate(), $sana);

        $slip = $this->service()->addLine($slip, PayItemKind::Earning, 'Bonus', 2000);
        $slip = $this->service()->addLine($slip, PayItemKind::Deduction, 'Damaged projector', 500);

        $this->assertSame([2000, 500, 31500], [$slip->allowances_total, $slip->deductions_total, $slip->net_salary]);

        $slip = $this->service()->removeLine($slip->items->firstWhere('name', 'Damaged projector'));

        $this->assertSame([0, 32000], [$slip->deductions_total, $slip->net_salary]);
    }

    public function test_lines_of_the_employee_file_cannot_be_removed_from_a_slip_and_amounts_must_be_positive(): void
    {
        $sana = $this->employee('Sana');
        EmployeePayItem::query()->create(['employee_profile_id' => $sana->id, 'kind' => PayItemKind::Earning, 'name' => 'House rent', 'amount' => 5000]);
        $slip = $this->slipOf($this->generate(), $sana);

        foreach ([
            fn () => $this->service()->removeLine($slip->items->first()),
            fn () => $this->service()->addLine($slip, PayItemKind::Earning, 'Nothing', 0),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('The change should have been refused.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_a_finalized_payroll_is_fixed_and_can_be_paid(): void
    {
        $sana = $this->employee('Sana');
        $run = $this->generate();
        $slip = $this->slipOf($run, $sana);

        try {
            $this->service()->markPaid($slip, PaymentMethod::Cash, now(), $this->admin);
            $this->fail('A draft payroll cannot be paid.');
        } catch (DomainException) {
            $this->addToAssertionCount(1);
        }

        $this->service()->finalize($run);

        foreach ([
            fn () => $this->service()->addLine($slip, PayItemKind::Earning, 'Bonus', 100),
            fn () => $this->service()->discard($run),
            fn () => $this->service()->finalize($run),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('A finalized payroll cannot change.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }

        $paid = $this->service()->markPaid($slip, PaymentMethod::BankTransfer, Carbon::parse('2026-04-01'), $this->admin);

        $this->assertSame([true, PaymentMethod::BankTransfer, $this->admin->id], [$paid->isPaid(), $paid->method, $paid->paid_by]);
        $this->assertSame(PayrollStatus::Finalized, $run->refresh()->status);

        $this->expectException(DomainException::class);
        $this->service()->markPaid($paid, PaymentMethod::Cash, now(), $this->admin);
    }

    public function test_a_draft_can_be_discarded_and_made_again(): void
    {
        $this->employee('Sana');
        $run = $this->generate();

        $this->service()->discard($run);
        $this->generate();

        $this->assertSame(1, PayrollRun::query()->count());
        $this->assertSame(1, SalarySlip::query()->count());
    }

    public function test_the_summary_counts_the_payroll(): void
    {
        $sana = $this->employee('Sana', 30000);
        $this->employee('Hina', 20000);
        EmployeePayItem::query()->create(['employee_profile_id' => $sana->id, 'kind' => PayItemKind::Earning, 'name' => 'House rent', 'amount' => 5000]);
        $run = $this->service()->finalize($this->generate());
        $this->service()->markPaid($this->slipOf($run, $sana), PaymentMethod::Cash, now(), $this->admin);

        $this->assertSame(
            ['employees' => 2, 'basic' => 50000, 'allowances' => 5000, 'deductions' => 0, 'unpaidLeave' => 0, 'net' => 55000, 'paid' => 1, 'unpaid' => 1],
            $this->service()->summary($run),
        );

        $this->assertSame(1, $this->service()->markAllPaid($run, PaymentMethod::Cash, now(), $this->admin));
    }

    public function test_the_payroll_comes_as_rows_for_a_spreadsheet(): void
    {
        $sana = $this->employee('Sana', 30000, ['employee_code' => 'EMP-0002', 'bank_name' => 'HBL', 'bank_account' => '1234']);
        $this->employee('Hina', 20000, ['employee_code' => 'EMP-0001']);

        $rows = $this->service()->rows($this->generate());

        $this->assertCount(3, $rows);
        $this->assertSame(['EMP-0001', 'Hina RAZA'], [$rows[1][0], $rows[1][1]]);
        $this->assertSame(['EMP-0002', 'Sana RAZA', 'Clerk', 30000, 0, 0, 0, 0, 30000, '', 'HBL', '1234'], $rows[2]);
        $this->assertNotNull($sana);
    }

    public function test_a_salary_slip_is_a_pdf(): void
    {
        $sana = $this->employee('Sana');
        EmployeePayItem::query()->create(['employee_profile_id' => $sana->id, 'kind' => PayItemKind::Deduction, 'name' => 'Advance recovery', 'amount' => 1000]);
        $run = $this->generate();

        $response = $this->service()->slipsPdf($run, $this->slipOf($run, $sana));

        ob_start();
        $response->sendContent();
        $pdf = ob_get_clean();

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('salary-slip-2026-03-sana-raza.pdf', $response->headers->get('content-disposition'));
    }

    public function test_the_payroll_is_made_from_the_screen(): void
    {
        $this->employee('Sana');

        Livewire::test(ListPayrollRuns::class)
            ->callAction('generate', data: ['campus_id' => $this->branch->id, 'month' => '2026-03'])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('payroll_runs', ['campus_id' => $this->branch->id, 'month' => '2026-03', 'status' => 'draft']);
    }

    public function test_the_payroll_is_finalized_and_paid_from_the_screen(): void
    {
        $sana = $this->employee('Sana');
        $run = $this->generate();

        Livewire::test(ViewPayrollRun::class, ['record' => $run->id])
            ->assertSee('Payroll')
            ->callAction('finalize')
            ->assertNotified(__('Payroll finalized'));

        $this->assertSame(PayrollStatus::Finalized, $run->refresh()->status);

        Livewire::test(ViewPayrollRun::class, ['record' => $run->id])
            ->callAction('payAll', data: ['method' => PaymentMethod::Cash->value, 'paid_on' => '2026-04-01'])
            ->assertNotified(__(':count salaries paid', ['count' => 1]));

        $this->assertTrue($this->slipOf($run, $sana)->isPaid());
    }

    public function test_the_payroll_and_the_slips_download(): void
    {
        $this->employee('Sana');
        $run = $this->generate();

        Livewire::test(ViewPayrollRun::class, ['record' => $run->id])
            ->callAction('slips')
            ->assertFileDownloaded()
            ->callAction('download')
            ->assertFileDownloaded();
    }

    public function test_the_slips_are_adjusted_and_paid_from_the_slips_table(): void
    {
        $sana = $this->employee('Sana');
        $run = $this->generate();
        $slip = $this->slipOf($run, $sana);
        $table = ['ownerRecord' => $run, 'pageClass' => ViewPayrollRun::class];

        Livewire::test(SlipsRelationManager::class, $table)
            ->assertCanSeeTableRecords([$slip])
            ->assertActionHidden(TestAction::make('markPaid')->table($slip))
            ->callAction(TestAction::make('addLine')->table($slip), data: ['kind' => PayItemKind::Earning->value, 'name' => 'Bonus', 'amount' => 1000])
            ->callAction(TestAction::make('removeLine')->table($slip->refresh()), data: ['item' => $slip->items()->first()->id]);

        $this->assertSame(30000, $slip->refresh()->net_salary);

        $this->service()->finalize($run);

        Livewire::test(SlipsRelationManager::class, $table)
            ->assertActionHidden(TestAction::make('addLine')->table($slip))
            ->callAction(TestAction::make('markPaid')->table($slip), data: ['method' => PaymentMethod::Cheque->value, 'paid_on' => '2026-04-01'])
            ->callAction(TestAction::make('slip')->table($slip))
            ->assertFileDownloaded();

        $this->assertSame(PaymentMethod::Cheque, $slip->refresh()->method);
    }

    public function test_only_staff_who_see_the_payroll_open_it_and_only_managers_change_it(): void
    {
        $this->employee('Sana');
        $run = $this->generate();
        $secretary = User::factory()->create();
        $secretary->assignRole('secretary');
        $this->actingAs($secretary);

        $this->assertFalse(PayrollRunResource::canAccess());
        $this->get(PayrollRunResource::getUrl('view', ['record' => $run]))->assertForbidden();

        $viewer = User::factory()->create();
        $viewer->givePermissionTo('payroll.view');
        $this->actingAs($viewer);

        $this->assertTrue(PayrollRunResource::canAccess());

        Livewire::test(ListPayrollRuns::class)->assertActionHidden('generate');
        Livewire::test(ViewPayrollRun::class, ['record' => $run->id])
            ->assertActionHidden('finalize')
            ->assertActionHidden('discard');
    }

    public function test_branch_staff_only_see_the_payroll_of_their_branch(): void
    {
        $this->employee('Sana');
        $mine = $this->generate();
        $otherBranch = Campus::factory()->create();
        $this->employee('Hina', 20000, ['campus_id' => $otherBranch->id]);
        $theirs = $this->service()->generate($otherBranch, '2026-03', $this->admin);
        $staff = User::factory()->create(['campus_id' => $this->branch->id]);
        $staff->givePermissionTo('payroll.view');
        $this->actingAs($staff);

        Livewire::test(ListPayrollRuns::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_unpaid_leave_waiting_for_approval_is_not_deducted(): void
    {
        $sana = $this->employee('Sana');
        $unpaid = LeaveType::factory()->create();
        LeaveAllowance::query()->create(['leave_type_id' => $unpaid->id, 'days_per_year' => 0, 'is_paid' => false]);
        LeaveRequest::factory()->create(['user_id' => $sana->user_id, 'campus_id' => $this->branch->id, 'leave_type_id' => $unpaid->id, 'from_date' => '2026-03-09', 'to_date' => '2026-03-10', 'status' => RequestStatus::Pending]);

        $this->assertSame(30000, $this->slipOf($this->generate(), $sana)->net_salary);
    }
}
