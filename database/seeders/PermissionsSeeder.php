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
        Permission::findOrCreate('courses.view');
        Permission::findOrCreate('courses.edit');
        Permission::findOrCreate('courses.delete');

        // enrollments
        Permission::findOrCreate('enrollments.view');
        Permission::findOrCreate('enrollments.edit');
        Permission::findOrCreate('enrollments.delete');

        // attendance permissions
        Permission::findOrCreate('attendance.view');
        Permission::findOrCreate('attendance.edit');

        // grades, skills, etc.
        Permission::findOrCreate('evaluation.edit');
        Permission::findOrCreate('evaluation.view');

        // reports
        Permission::findOrCreate('reports.view');

        // calendars
        Permission::findOrCreate('calendars.view');

        // HR
        Permission::findOrCreate('hr.view');
        Permission::findOrCreate('hr.manage');

        // edit and delete the comments of other users
        Permission::findOrCreate('comments.edit');

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
        $role = Role::findOrCreate('admin');
        foreach (Permission::all() as $permission) {
            $role->givePermissionTo($permission->name);
        }

        // secretaries typically deal with enrollments, course information, etc.
        $role = Role::findOrCreate('secretary');
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

        // The tests use a role without any permission. A school has none: read only is a level of a module, chosen
        // for each role on the Roles screen.
        if (app()->runningUnitTests()) {
            Role::findOrCreate('viewer');
        }
    }
}
