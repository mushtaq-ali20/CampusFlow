<?php

namespace Tests\Feature;

use App\Filament\Resources\Staff\Pages\ManageStaff;
use App\Filament\Resources\Staff\StaffResource;
use App\Models\Campus;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class StaffResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);
    }

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    public function test_admin_creates_a_branch_secretary(): void
    {
        $this->actingAsRole('admin');
        $branch = Campus::factory()->create();

        Livewire::test(ManageStaff::class)
            ->callAction(TestAction::make('create'), [
                'firstname' => 'Sara',
                'lastname' => 'Khan',
                'email' => 'Sara@School.test',
                'password' => 'secret-pass',
                'role' => 'secretary',
                'campus_id' => $branch->id,
            ])
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'sara@school.test')->sole();
        $this->assertTrue($user->hasRole('secretary'));
        $this->assertSame($branch->id, $user->campus_id);
        $this->assertTrue(Hash::check('secret-pass', $user->password));
        $this->assertTrue($user->mustChangePassword());
        $this->assertTrue($user->isActive());
    }

    public function test_an_administrator_is_never_limited_to_a_branch(): void
    {
        $this->actingAsRole('admin');

        Livewire::test(ManageStaff::class)
            ->callAction(TestAction::make('create'), [
                'firstname' => 'Head',
                'lastname' => 'Office',
                'email' => 'head@school.test',
                'password' => 'secret-pass',
                'role' => 'admin',
                'campus_id' => Campus::factory()->create()->id,
            ])
            ->assertHasNoFormErrors();

        $this->assertNull(User::query()->where('email', 'head@school.test')->sole()->campus_id);
    }

    public function test_password_and_role_are_required_for_a_new_staff_member(): void
    {
        $this->actingAsRole('admin');

        Livewire::test(ManageStaff::class)
            ->callAction(TestAction::make('create'), [
                'firstname' => 'No',
                'lastname' => 'Password',
                'email' => 'nopass@school.test',
            ])
            ->assertHasFormErrors(['password' => 'required', 'role' => 'required']);
    }

    public function test_admin_changes_the_role_and_branch_and_keeps_the_password_when_left_empty(): void
    {
        $this->actingAsRole('admin');
        $staff = User::factory()->create(['password' => Hash::make('old-password')]);
        $staff->assignRole('viewer');
        $branch = Campus::factory()->create();

        Livewire::test(ManageStaff::class)
            ->mountAction(TestAction::make('edit')->table($staff))
            ->assertSchemaStateSet(['role' => 'viewer'])
            ->unmountAction()
            ->callAction(TestAction::make('edit')->table($staff), [
                'role' => 'secretary',
                'campus_id' => $branch->id,
                'password' => '',
            ])
            ->assertHasNoFormErrors();

        $staff->refresh();
        $this->assertSame(['secretary'], $staff->getRoleNames()->all());
        $this->assertSame($branch->id, $staff->campus_id);
        $this->assertTrue(Hash::check('old-password', $staff->password));
    }

    public function test_users_with_a_role_and_teachers_are_listed_but_not_parents_or_students(): void
    {
        $this->actingAsRole('admin');
        $staff = User::factory()->create();
        $staff->assignRole('secretary');
        $teacher = Teacher::factory()->create()->user;
        $withoutRole = User::factory()->create();

        Livewire::test(ManageStaff::class)
            ->assertCanSeeTableRecords([$staff, $teacher])
            ->assertCanNotSeeTableRecords([$withoutRole]);
    }

    public function test_deactivating_keeps_the_user_and_their_teacher_profile(): void
    {
        $this->actingAsRole('admin');
        $teacher = Teacher::factory()->create();
        $user = $teacher->user;

        Livewire::test(ManageStaff::class)
            ->callAction(TestAction::make('deactivate')->table($user));

        $user->refresh();
        $this->assertFalse($user->isActive());
        $this->assertSame(['teacher'], $user->getRoleNames()->all());
        $this->assertTrue(Teacher::query()->whereKey($teacher->id)->exists());
    }

    public function test_admin_cannot_deactivate_themselves(): void
    {
        $admin = $this->actingAsRole('admin');

        Livewire::test(ManageStaff::class)
            ->assertActionHidden(TestAction::make('deactivate')->table($admin));
    }

    public function test_secretaries_cannot_manage_staff(): void
    {
        $this->actingAsRole('secretary');

        $this->assertFalse(StaffResource::canAccess());
        $this->get(StaffResource::getUrl('index'))->assertForbidden();
    }

    public function test_viewers_cannot_manage_staff(): void
    {
        $this->actingAsRole('viewer');

        $this->assertFalse(StaffResource::canAccess());
        $this->get(StaffResource::getUrl('index'))->assertForbidden();
    }
}
