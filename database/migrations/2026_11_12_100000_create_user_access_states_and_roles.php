<?php

use App\Models\User;
use App\Services\AccessModules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * User management:
 * - user_access_states: whether a user may sign in, whether they must choose a new password, and their last sign-in;
 * - the permissions of the modules of the Roles screen (users.*, roles.manage, settings.manage, fees.approve,
 *   leaves.approve and the older ones a fresh database does not have yet);
 * - the roles Teacher, Accountant and Principal with their default access; every user with a teacher profile and
 *   no role becomes a Teacher;
 * - the empty "viewer" role is removed when nobody has it: read only is a level of a module, not a role.
 *
 * The administrator needs no permission: a rule gives that role every right (see AppServiceProvider).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_access_states', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->unique();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('must_change_password')->default(false);
            $table->dateTime('last_login_at')->nullable();
            $table->timestamps();
        });

        foreach (AccessModules::permissions() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (AccessModules::defaultRoles() as $name => $access) {
            $role = Role::query()->where('name', $name)->where('guard_name', 'web')->first();

            if ($role === null) {
                AccessModules::apply(Role::create(['name' => $name, 'guard_name' => 'web']), $access['levels'], $access['extras']);
            }
        }

        $this->makeTeachersTeachers();

        if (! app()->runningUnitTests()) {
            Role::query()->where('name', 'viewer')->whereDoesntHave('users')->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_access_states');

        Role::query()->whereIn('name', array_keys(AccessModules::defaultRoles()))->delete();
        Permission::query()->whereIn('name', ['users.view', 'users.manage', 'roles.manage', 'settings.manage', 'fees.approve', 'leaves.approve'])->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Gives the Teacher role to the users with a teacher profile who have no role.
     */
    private function makeTeachersTeachers(): void
    {
        $role = Role::findByName(AccessModules::TEACHER, 'web');
        $tables = config('permission.table_names');

        $withRole = DB::table($tables['model_has_roles'])->pluck('model_id');

        foreach (DB::table('teachers')->whereNull('deleted_at')->whereNotIn('id', $withRole)->pluck('id') as $userId) {
            DB::table($tables['model_has_roles'])->insert([
                'role_id' => $role->id,
                'model_type' => (new User)->getMorphClass(),
                'model_id' => $userId,
            ]);
        }
    }
};
