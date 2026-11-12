<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions for the front desk. On a fresh install the roles do not exist yet: the permissions seeder gives them
 * these permissions.
 *
 * - enquiries.view / enquiries.manage: see admission enquiries, and record, follow up and take them through the steps
 * - enquiries.approve: approve the age of the child and the fee dues of the family, two policy decisions
 * - complaints.view / complaints.manage: see all the complaints of the branch, and log, assign and reopen them
 *   (anyone a complaint is assigned to sees it, comments on it and resolves it)
 * - complaints.configure: define the complaint categories, time limits and escalation
 * - ptm.view / ptm.manage: see the parent teacher meetings, and plan them (class teachers see and record the
 *   meetings of their sections)
 * - documents.manage: upload and share documents (everyone sees the documents shared with them)
 */
return new class extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private array $permissions = [
        'enquiries.view' => ['admin', 'secretary'],
        'enquiries.manage' => ['admin', 'secretary'],
        'enquiries.approve' => ['admin'],
        'complaints.view' => ['admin', 'secretary'],
        'complaints.manage' => ['admin', 'secretary'],
        'complaints.configure' => ['admin'],
        'ptm.view' => ['admin', 'secretary'],
        'ptm.manage' => ['admin', 'secretary'],
        'documents.manage' => ['admin', 'secretary'],
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
