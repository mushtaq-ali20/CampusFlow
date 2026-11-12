<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // courses permissions
        Permission::create(['name' => 'courses.view']);
        Permission::create(['name' => 'courses.edit']);
        Permission::create(['name' => 'courses.delete']);

        // enrollments
        Permission::create(['name' => 'enrollments.view']);
        Permission::create(['name' => 'enrollments.edit']);
        Permission::create(['name' => 'enrollments.delete']);

        // attendance permissions
        Permission::create(['name' => 'attendance.view']);
        Permission::create(['name' => 'attendance.edit']);

        // grades, skills, etc.
        Permission::create(['name' => 'evaluation.edit']);
        Permission::create(['name' => 'evaluation.view']);

        // reports
        Permission::create(['name' => 'reports.view']);

        // calendars
        Permission::create(['name' => 'calendars.view']);

        // HR
        Permission::create(['name' => 'hr.view']);
        Permission::create(['name' => 'hr.manage']);

        // edit and delete the comments of other users
        Permission::create(['name' => 'comments.edit']);

        // school structure: classes, sections, subjects and teaching assignments (also created by a migration)
        Permission::findOrCreate('school.view');
        Permission::findOrCreate('school.manage');

        // admissions and fee collection (also created by a migration)
        foreach (['admissions.view', 'admissions.manage', 'fees.view', 'fees.collect', 'fees.manage'] as $permission) {
            Permission::findOrCreate($permission);
        }

        // guidance and violation, and WhatsApp messages to parents (also created by a migration)
        foreach (['discipline.view', 'discipline.record', 'discipline.manage', 'parents.message'] as $permission) {
            Permission::findOrCreate($permission);
        }

        // front desk: admission enquiries, complaints, parent teacher meetings, documents (also created by a migration)
        $frontDesk = ['enquiries.view', 'enquiries.manage', 'enquiries.approve', 'complaints.view', 'complaints.manage', 'complaints.configure', 'ptm.view', 'ptm.manage', 'documents.manage'];
        foreach ($frontDesk as $permission) {
            Permission::findOrCreate($permission);
        }

        // employee files, leave requests and payroll (also created by a migration)
        foreach (['employees.view', 'employees.manage', 'payroll.view', 'payroll.manage'] as $permission) {
            Permission::findOrCreate($permission);
        }

        // recruitment, performance evaluation and account payable (also created by a migration)
        foreach (['recruitment.view', 'recruitment.manage', 'appraisals.view', 'appraisals.manage', 'payables.view', 'payables.manage'] as $permission) {
            Permission::findOrCreate($permission);
        }

        // admins have all permissions
        $role = Role::create(['name' => 'admin']);
        foreach (Permission::all() as $permission) {
            $role->givePermissionTo($permission->name);
        }

        // secretaries typically deal with enrollments, course information, etc.
        $role = Role::create(['name' => 'secretary']);
        $role->givePermissionTo('calendars.view');
        $role->givePermissionTo('evaluation.view');
        $role->givePermissionTo('attendance.view');
        $role->givePermissionTo('attendance.edit');
        $role->givePermissionTo('enrollments.view');
        $role->givePermissionTo('enrollments.edit');
        $role->givePermissionTo('courses.view');
        $role->givePermissionTo('school.view');
        $role->givePermissionTo('admissions.view');
        $role->givePermissionTo('admissions.manage');
        $role->givePermissionTo('fees.view');
        $role->givePermissionTo('fees.collect');
        $role->givePermissionTo('discipline.view');
        $role->givePermissionTo('discipline.record');
        $role->givePermissionTo('parents.message');
        $role->givePermissionTo(['enquiries.view', 'enquiries.manage', 'complaints.view', 'complaints.manage', 'ptm.view', 'ptm.manage', 'documents.manage']);

        $role->givePermissionTo(['recruitment.view', 'recruitment.manage']);

        // viewers can access the panel but have no resource permissions
        Role::create(['name' => 'viewer']);
    }
}
