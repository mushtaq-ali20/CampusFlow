<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\RequestStatus;
use App\Enums\VoucherItemKind;
use App\Filament\Pages\ChangePassword;
use App\Filament\Pages\SchoolDashboard;
use App\Filament\Resources\FeeRefunds\Pages\ListFeeRefunds;
use App\Filament\Resources\LeaveRequests\Pages\ManageLeaveRequests;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Staff\Pages\ManageStaff;
use App\Filament\Resources\Staff\StaffResource;
use App\Models\Admission;
use App\Models\Campus;
use App\Models\EmployeeProfile;
use App\Models\FeeRefund;
use App\Models\FeeVoucher;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Section;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Year;
use App\Services\AccessModules;
use App\Services\FeeService;
use App\Services\UserAccessService;
use Database\Seeders\PermissionsSeeder;
use DomainException;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    private function actingAsRole(string $role): User
    {
        $user = $this->userWithRole($role);
        $this->actingAs($user);

        return $user;
    }

    public function test_the_administrator_has_every_right_even_ones_added_later(): void
    {
        $admin = $this->userWithRole('admin');
        Permission::findOrCreate('some.future.permission', 'web');

        $this->assertTrue($admin->can('some.future.permission'));
        $this->assertTrue($admin->can('payroll.manage'));
        $this->assertFalse($this->userWithRole('secretary')->can('payroll.manage'));
    }

    public function test_any_role_opens_the_panel_but_a_deactivated_user_cannot(): void
    {
        $panel = Filament::getPanel('admin');
        $accountant = $this->userWithRole('accountant');
        $custom = $this->userWithRole(Role::create(['name' => 'Exam controller', 'guard_name' => 'web'])->name);

        $this->assertTrue($accountant->canAccessPanel($panel));
        $this->assertTrue($custom->canAccessPanel($panel));
        $this->assertFalse(User::factory()->create()->canAccessPanel($panel));

        app(UserAccessService::class)->deactivate($accountant, $this->userWithRole('admin'));

        $this->assertFalse($accountant->refresh()->canAccessPanel($panel));
    }

    public function test_a_new_teacher_gets_the_teacher_role(): void
    {
        $teacher = Teacher::factory()->create();

        $this->assertSame(['teacher'], $teacher->user->getRoleNames()->all());
    }

    public function test_signing_in_records_the_time(): void
    {
        $user = $this->userWithRole('secretary');

        event(new Login('web', $user, false));

        $this->assertNotNull($user->refresh()->accessState->last_login_at);
    }

    public function test_levels_and_extra_rights_become_permissions_and_read_back(): void
    {
        $role = Role::create(['name' => 'Exam controller', 'guard_name' => 'web']);
        $role->givePermissionTo('calendars.view');

        AccessModules::apply($role, ['exams' => AccessModules::FULL, 'students' => AccessModules::READ, 'fees' => AccessModules::NONE], ['fees.approve', 'discipline.record']);
        $role->refresh();

        $this->assertSame(AccessModules::FULL, AccessModules::levelsOf($role)['exams']);
        $this->assertSame(AccessModules::READ, AccessModules::levelsOf($role)['students']);
        $this->assertSame(AccessModules::NONE, AccessModules::levelsOf($role)['fees']);
        $this->assertSame([], AccessModules::extrasOf($role), 'Extra rights of modules the role cannot open are dropped.');
        $this->assertTrue($role->hasPermissionTo('calendars.view'), 'Permissions outside the modules are kept.');
        $this->assertTrue($role->hasPermissionTo('evaluation.edit'));
        $this->assertFalse($role->hasPermissionTo('enrollments.edit'));
    }

    public function test_the_default_roles_match_the_agreed_access(): void
    {
        $accountant = AccessModules::levelsOf(Role::findByName('accountant'));
        $principal = Role::findByName('principal');

        $this->assertEquals(
            ['fees' => 'full', 'payroll' => 'full', 'payables' => 'full', 'students' => 'read', 'admissions' => 'read', 'employees' => 'read', 'exams' => 'none'],
            array_intersect_key($accountant, array_flip(['fees', 'payroll', 'payables', 'students', 'admissions', 'employees', 'exams'])),
        );
        $this->assertFalse(Role::findByName('accountant')->hasPermissionTo('fees.approve'));
        $this->assertSame(['read', 'full', 'full', 'none'], array_values(array_intersect_key(AccessModules::levelsOf($principal), array_flip(['fees', 'recruitment', 'appraisals', 'users']))));
        $this->assertEqualsCanonicalizing(['fees.approve', 'leaves.approve', 'enquiries.approve'], AccessModules::extrasOf($principal));
        $this->assertFalse($principal->hasPermissionTo('employees.manage'), 'Only the administrator hires.');
    }

    public function test_the_accountant_requests_refunds_and_the_principal_approves_them(): void
    {
        $section = Section::factory()->create(['year_id' => Year::factory()->create()->id]);
        $voucher = FeeVoucher::factory()->create(['admission_id' => Admission::factory()->create(['section_id' => $section->id])->id, 'voucher_number' => 'V-1']);
        $voucher->items()->create(['kind' => VoucherItemKind::Fee, 'description' => 'Tuition Fee', 'amount' => 1000]);
        $fees = app(FeeService::class);
        $payment = $fees->recordPayment($fees->recalculate($voucher), 1000, PaymentMethod::Cash, now());
        $refund = FeeRefund::factory()->create(['fee_payment_id' => $payment->id, 'status' => RequestStatus::Pending]);

        $this->actingAs($this->userWithRole('accountant'));
        Livewire::test(ListFeeRefunds::class)->assertActionHidden(TestAction::make('approve')->table($refund));

        $this->actingAs($this->userWithRole('principal'));
        Livewire::test(ListFeeRefunds::class)->assertActionVisible(TestAction::make('approve')->table($refund));
    }

    public function test_the_principal_approves_leave_and_a_role_without_the_right_does_not(): void
    {
        $branch = Campus::factory()->create();
        $employee = User::factory()->create(['campus_id' => null]);
        EmployeeProfile::factory()->create(['user_id' => $employee->id, 'campus_id' => $branch->id]);
        $request = LeaveRequest::factory()->create(['user_id' => $employee->id, 'campus_id' => $branch->id, 'leave_type_id' => LeaveType::factory()->create()->id]);

        $hr = User::factory()->create(['campus_id' => null]);
        $hr->givePermissionTo(['employees.view', 'employees.manage']);
        $this->actingAs($hr);
        Livewire::test(ManageLeaveRequests::class)->assertActionHidden(TestAction::make('approve')->table($request));

        $this->actingAs($this->userWithRole('principal', ['campus_id' => null]));
        Livewire::test(ManageLeaveRequests::class)
            ->callAction(TestAction::make('approve')->table($request));

        $this->assertSame(RequestStatus::Approved, $request->refresh()->status);
    }

    public function test_a_user_with_a_temporary_password_must_choose_their_own_first(): void
    {
        $user = $this->userWithRole('secretary');
        $user->accessState()->create(['must_change_password' => true]);
        $this->actingAs($user);

        $this->get(SchoolDashboard::getUrl())->assertRedirect(ChangePassword::getUrl());

        Livewire::test(ChangePassword::class)
            ->fillForm(['password' => 'my-own-password', 'password_confirmation' => 'my-own-password'])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertFalse($user->mustChangePassword());
        $this->assertTrue(Hash::check('my-own-password', $user->password));
        $this->get(SchoolDashboard::getUrl())->assertOk();
    }

    public function test_the_school_keeps_at_least_one_active_administrator(): void
    {
        $admin = $this->actingAsRole('admin');
        $other = $this->userWithRole('admin');
        $service = app(UserAccessService::class);

        $service->deactivate($other, $admin);

        $this->expectException(DomainException::class);
        $service->save($admin, ['firstname' => 'A', 'lastname' => 'B', 'email' => $admin->email, 'role' => 'secretary'], $other);
    }

    public function test_nobody_changes_their_own_role_or_deactivates_themselves(): void
    {
        $admin = $this->actingAsRole('admin');
        $this->userWithRole('admin');

        foreach ([
            fn () => app(UserAccessService::class)->deactivate($admin, $admin),
            fn () => app(UserAccessService::class)->save($admin, ['firstname' => 'A', 'lastname' => 'B', 'email' => $admin->email, 'role' => 'secretary'], $admin),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('The change should have been refused.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_only_an_administrator_makes_or_changes_an_administrator(): void
    {
        $manager = $this->userWithRole(Role::create(['name' => 'Office manager', 'guard_name' => 'web'])->name);
        AccessModules::apply(Role::findByName('Office manager'), ['users' => AccessModules::FULL]);
        $admin = $this->userWithRole('admin');
        $this->actingAs($manager->refresh());

        Livewire::test(ManageStaff::class)
            ->assertActionHidden(TestAction::make('edit')->table($admin))
            ->assertActionHidden(TestAction::make('deactivate')->table($admin));

        $this->expectException(DomainException::class);
        app(UserAccessService::class)->save(new User, ['firstname' => 'New', 'lastname' => 'Admin', 'email' => 'new@school.test', 'password' => 'secret-pass', 'role' => 'admin'], $manager);
    }

    public function test_a_custom_role_is_made_on_the_roles_screen(): void
    {
        $this->actingAsRole('admin');

        Livewire::test(CreateRole::class)
            ->fillForm([
                'name' => 'Exam controller',
                'levels' => ['exams' => 'full', 'students' => 'read', 'school' => 'read'],
                'extras' => ['discipline' => ['discipline.record']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $role = Role::findByName('Exam controller');
        $this->assertEquals(['exams' => 'full', 'students' => 'read', 'school' => 'read', 'fees' => 'none'], array_intersect_key(AccessModules::levelsOf($role), array_flip(['exams', 'students', 'school', 'fees'])));
        $this->assertSame([], AccessModules::extrasOf($role));
    }

    public function test_a_role_is_edited_and_set_all_fills_every_module(): void
    {
        $this->actingAsRole('admin');
        $role = Role::findByName('accountant');

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->assertSchemaStateSet(['levels.fees' => 'full', 'levels.exams' => 'none', 'extras.fees' => ['fees.collect']])
            ->callAction('allRead')
            ->assertSchemaStateSet(['levels.fees' => 'read', 'levels.documents' => 'full'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('read', AccessModules::levelsOf($role->refresh())['payroll']);
    }

    public function test_the_administrator_role_is_locked_and_system_or_used_roles_are_not_deleted(): void
    {
        $this->actingAsRole('admin');
        $used = Role::create(['name' => 'Librarian', 'guard_name' => 'web']);
        $this->userWithRole('Librarian');
        $unused = Role::create(['name' => 'Coordinator', 'guard_name' => 'web']);

        Livewire::test(ListRoles::class)
            ->assertActionHidden(TestAction::make('edit')->table(Role::findByName('admin')))
            ->assertActionHidden(TestAction::make('delete')->table(Role::findByName('teacher')))
            ->assertActionHidden(TestAction::make('delete')->table($used))
            ->callAction(TestAction::make('delete')->table($unused));

        $this->assertModelMissing($unused);
        $this->get(RoleResource::getUrl('edit', ['record' => Role::findByName('admin')]))->assertForbidden();
    }

    public function test_users_and_roles_are_only_for_staff_with_the_rights(): void
    {
        $this->actingAsRole('principal');

        $this->assertFalse(RoleResource::canAccess());
        $this->get(RoleResource::getUrl())->assertForbidden();
        $this->get(StaffResource::getUrl())->assertForbidden();
    }
}
