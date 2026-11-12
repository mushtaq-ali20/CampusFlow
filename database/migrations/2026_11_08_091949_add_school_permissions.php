<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions for the school structure: classes, sections, subjects and teaching assignments.
 * On a fresh install the roles do not exist yet: the permissions seeder gives them these permissions.
 */
return new class extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private array $permissions = [
        'school.view' => ['admin', 'secretary'],
        'school.manage' => ['admin'],
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
