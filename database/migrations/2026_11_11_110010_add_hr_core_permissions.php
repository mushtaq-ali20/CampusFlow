<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions for the employee files, leave requests and payroll. They are separate from the older hr.* permissions,
 * which only cover the teacher leaves. On a fresh install the roles do not exist yet: the permissions seeder gives
 * them these permissions.
 *
 * - employees.view / employees.manage: see the employee files, and keep them; approve or reject leave requests
 * - payroll.view / payroll.manage: see the salaries and slips; generate, finalize and pay the payroll
 *
 * Every employee can ask for a leave and see their own requests without a permission.
 */
return new class extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private array $permissions = [
        'employees.view' => ['admin'],
        'employees.manage' => ['admin'],
        'payroll.view' => ['admin'],
        'payroll.manage' => ['admin'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->permissions as $permission => $roles) {
            Permission::findOrCreate($permission, 'web');

            foreach (Role::query()->whereIn('name', $roles)->get() as $role) {
                $role->givePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::query()->whereIn('name', array_keys($this->permissions))->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
