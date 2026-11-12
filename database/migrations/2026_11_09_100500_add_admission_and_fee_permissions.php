<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions for admissions and fee collection. On a fresh install the roles do not exist yet:
 * the permissions seeder gives them these permissions.
 *
 * - admissions.view / admissions.manage: see admissions, and admit, move or promote students
 * - fees.view: see the fee structure, the vouchers, the receipts and the fee dashboard
 * - fees.collect: generate the monthly vouchers, collect payments and download receipts
 * - fees.manage: define fee heads, structures and discounts
 */
return new class extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private array $permissions = [
        'admissions.view' => ['admin', 'secretary'],
        'admissions.manage' => ['admin', 'secretary'],
        'fees.view' => ['admin', 'secretary'],
        'fees.collect' => ['admin', 'secretary'],
        'fees.manage' => ['admin'],
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
