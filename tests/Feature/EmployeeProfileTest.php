<?php

namespace Tests\Feature;

use App\Enums\EmployeeStatus;
use App\Enums\PayItemKind;
use App\Filament\Resources\EmployeeProfiles\EmployeeProfileResource;
use App\Filament\Resources\EmployeeProfiles\Pages\ManageEmployeeProfiles;
use App\Filament\Resources\LeaveAllowances\LeaveAllowanceResource;
use App\Filament\Resources\LeaveAllowances\Pages\ManageLeaveAllowances;
use App\Models\Campus;
use App\Models\EmployeeProfile;
use App\Models\LeaveAllowance;
use App\Models\LeaveType;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeProfileTest extends TestCase
{
    use RefreshDatabase;

    private Campus $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);

        $this->branch = Campus::factory()->create();
    }

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create(['campus_id' => null]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function staffMember(): User
    {
        $user = User::factory()->create(['firstname' => 'Sana', 'lastname' => 'Raza', 'campus_id' => null]);
        $user->assignRole('secretary');

        return $user;
    }

    public function test_the_file_of_a_staff_member_is_made_with_the_salary_and_the_fixed_lines(): void
    {
        $this->actingAsRole('admin');
        $sana = $this->staffMember();

        Livewire::test(ManageEmployeeProfiles::class)
            ->callAction('create', data: [
                'user_id' => $sana->id,
                'campus_id' => $this->branch->id,
                'designation' => 'Receptionist',
                'employment_type' => 'permanent',
                'status' => 'active',
                'joined_on' => '2025-08-01',
                'basic_salary' => 28000,
                'payItems' => [
                    ['kind' => PayItemKind::Earning->value, 'name' => 'House rent', 'amount' => 4000],
                    ['kind' => PayItemKind::Deduction->value, 'name' => 'Advance recovery', 'amount' => 1000],
                ],
            ])
            ->assertHasNoFormErrors();

        $profile = EmployeeProfile::query()->where('user_id', $sana->id)->firstOrFail();

        $this->assertSame([28000, 'EMP-0001'], [$profile->basic_salary, $profile->employee_code]);
        $this->assertSame([['name' => 'House rent', 'amount' => 4000]], $profile->fixedLines(PayItemKind::Earning));
        $this->assertSame([['name' => 'Advance recovery', 'amount' => 1000]], $profile->fixedLines(PayItemKind::Deduction));
    }

    public function test_employee_numbers_follow_each_other(): void
    {
        $first = EmployeeProfile::factory()->create(['employee_code' => null]);
        $second = EmployeeProfile::factory()->create(['employee_code' => null]);

        $this->assertSame(['EMP-0001', 'EMP-0002'], [$first->employee_code, $second->employee_code]);
    }

    public function test_only_people_without_a_file_who_work_for_the_school_can_be_chosen(): void
    {
        $hasFile = $this->staffMember();
        EmployeeProfile::factory()->create(['user_id' => $hasFile->id]);
        $free = User::factory()->create(['firstname' => 'Free', 'campus_id' => null]);
        $free->assignRole('secretary');
        $teacher = Teacher::factory()->create();
        $parent = User::factory()->create();

        $options = array_keys(EmployeeProfileResource::availableUsers());

        $this->assertEqualsCanonicalizing([$free->id, $teacher->id], $options);
        $this->assertNotContains($hasFile->id, $options);
        $this->assertNotContains($parent->id, $options);
    }

    public function test_a_person_who_left_needs_the_date(): void
    {
        $this->actingAsRole('admin');
        $sana = $this->staffMember();

        Livewire::test(ManageEmployeeProfiles::class)
            ->callAction('create', data: [
                'user_id' => $sana->id,
                'campus_id' => $this->branch->id,
                'designation' => 'Receptionist',
                'employment_type' => 'contract',
                'status' => EmployeeStatus::Left->value,
                'joined_on' => '2025-08-01',
                'basic_salary' => 0,
            ])
            ->assertHasFormErrors(['left_on' => 'required']);
    }

    public function test_the_salary_is_hidden_from_staff_who_do_not_see_the_payroll(): void
    {
        $profile = EmployeeProfile::factory()->create(['basic_salary' => 41234]);
        $hr = User::factory()->create(['campus_id' => null]);
        $hr->givePermissionTo('employees.view');
        $this->actingAs($hr);

        Livewire::test(ManageEmployeeProfiles::class)
            ->assertCanSeeTableRecords([$profile])
            ->assertTableColumnHidden('basic_salary')
            ->assertDontSee('41,234');

        $hr->givePermissionTo('payroll.view');

        Livewire::test(ManageEmployeeProfiles::class)
            ->assertTableColumnVisible('basic_salary')
            ->assertSee('41,234');
    }

    public function test_staff_who_only_see_the_payroll_cannot_change_the_salary(): void
    {
        $profile = EmployeeProfile::factory()->create(['basic_salary' => 30000]);
        $hr = User::factory()->create(['campus_id' => null]);
        $hr->givePermissionTo(['employees.view', 'employees.manage', 'payroll.view']);
        $this->actingAs($hr);

        Livewire::test(ManageEmployeeProfiles::class)
            ->callAction(TestAction::make('edit')->table($profile), data: ['designation' => 'Senior Clerk', 'basic_salary' => 99000])
            ->assertHasNoFormErrors();

        $profile->refresh();

        $this->assertSame(['Senior Clerk', 30000], [$profile->designation, $profile->basic_salary]);
    }

    public function test_the_files_follow_the_active_branch(): void
    {
        $mine = EmployeeProfile::factory()->create(['campus_id' => $this->branch->id]);
        $theirs = EmployeeProfile::factory()->create();
        $hr = User::factory()->create(['campus_id' => $this->branch->id]);
        $hr->givePermissionTo('employees.view');
        $this->actingAs($hr);

        Livewire::test(ManageEmployeeProfiles::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_the_files_are_only_for_staff_who_see_the_employees(): void
    {
        $this->actingAsRole('secretary');

        $this->assertFalse(EmployeeProfileResource::canAccess());
        $this->get(EmployeeProfileResource::getUrl())->assertForbidden();
    }

    public function test_staff_who_only_view_the_employees_cannot_change_them(): void
    {
        $profile = EmployeeProfile::factory()->create();
        $hr = User::factory()->create(['campus_id' => null]);
        $hr->givePermissionTo('employees.view');
        $this->actingAs($hr);

        Livewire::test(ManageEmployeeProfiles::class)
            ->assertActionHidden('create')
            ->assertActionHidden(TestAction::make('edit')->table($profile))
            ->assertActionHidden(TestAction::make('delete')->table($profile));
    }

    public function test_the_leave_allowances_are_set_for_each_leave_type(): void
    {
        $this->actingAsRole('admin');
        $casual = LeaveType::factory()->create(['name' => 'Casual']);

        Livewire::test(ManageLeaveAllowances::class)
            ->callAction('create', data: ['leave_type_id' => $casual->id, 'days_per_year' => 10, 'is_paid' => true])
            ->assertHasNoFormErrors()
            ->callAction('create', data: ['leave_type_id' => $casual->id, 'days_per_year' => 5, 'is_paid' => false])
            ->assertHasFormErrors(['leave_type_id']);

        $this->assertSame(1, LeaveAllowance::query()->count());
        $this->assertTrue(LeaveAllowanceResource::canAccess());
    }
}
