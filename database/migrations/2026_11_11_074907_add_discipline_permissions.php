<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions for guidance and violation, and for the WhatsApp messages to parents. On a fresh install the roles
 * do not exist yet: the permissions seeder gives them these permissions.
 *
 * - discipline.view: see the discipline records of all the students of the branch
 * - discipline.record: record, change and delete discipline records
 * - discipline.manage: define the discipline categories and their points
 * - parents.message: prepare WhatsApp messages to the parents
 *
 * Teachers without these permissions record and see the records of the sections they teach.
 */
return new class extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private array $permissions = [
        'discipline.view' => ['admin', 'secretary'],
        'discipline.record' => ['admin', 'secretary'],
        'discipline.manage' => ['admin'],
        'parents.message' => ['admin', 'secretary'],
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
