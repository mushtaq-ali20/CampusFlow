<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions for recruitment, performance evaluation and account payable. On a fresh install the roles do not
 * exist yet: the permissions seeder gives them these permissions.
 *
 * - recruitment.view / recruitment.manage: see the job openings and applicants, and enter applicants and take them
 *   through the steps (the front desk does this). Hiring an applicant also needs employees.manage.
 * - appraisals.view / appraisals.manage: see all the evaluations of the branch, and set them up and evaluate
 *   (every employee sees and acknowledges their own evaluation once it is submitted)
 * - payables.view / payables.manage: see the vendors, bills and what is owed, and record bills and pay them
 */
return new class extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private array $permissions = [
        'recruitment.view' => ['admin', 'secretary'],
        'recruitment.manage' => ['admin', 'secretary'],
        'appraisals.view' => ['admin'],
        'appraisals.manage' => ['admin'],
        'payables.view' => ['admin'],
        'payables.manage' => ['admin'],
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
