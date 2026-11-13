<?php

namespace App\Services;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The modules of the application as the Roles screen shows them, and what each access level of a module means in
 * permissions. A role has one level per module: no access, read only (the "view" permissions) or full access (the
 * "view" and "manage" permissions), plus extra rights given one by one, such as approving refunds.
 *
 * A new module is added here: the Roles screen, the default roles and the tests read this list.
 */
class AccessModules
{
    public const NONE = 'none';

    public const READ = 'read';

    public const FULL = 'full';

    /**
     * The role of the super user, who has every right. It is never edited or deleted.
     */
    public const ADMINISTRATOR = 'admin';

    /**
     * The role every user with a teacher profile gets. Teachers work on their own classes without any permission;
     * the role adds module access on top.
     */
    public const TEACHER = 'teacher';

    /**
     * Roles whose name the code relies on: they cannot be renamed or deleted.
     *
     * @var list<string>
     */
    public const SYSTEM_ROLES = [self::ADMINISTRATOR, self::TEACHER, 'secretary'];

    /**
     * @return array<string, array{label: string, screens: string, view: list<string>, manage: list<string>, extras: array<string, string>}>
     */
    public static function all(): array
    {
        return [
            'students' => self::module(__('Students'), __('Students and their profile'), ['enrollments.view'], ['enrollments.edit']),
            'admissions' => self::module(__('Admissions'), __('Admissions'), ['admissions.view'], ['admissions.manage']),
            'school' => self::module(__('School structure'), __('Classes, sections, subjects, timetable'), ['school.view'], ['school.manage']),
            'attendance' => self::module(__('Attendance'), __('Class attendance, attendance reports'), ['attendance.view'], ['attendance.edit']),
            'exams' => self::module(__('Exams and results'), __('Marks entry, assessments, results sheet, grading scale'), ['evaluation.view'], ['evaluation.edit']),
            'fees' => self::module(__('Fees'), __('Vouchers, payments, structures, discounts, ledger, aging'), ['fees.view'], ['fees.manage'], [
                'fees.collect' => __('Collect payments'),
                'fees.approve' => __('Approve adjustments and refunds'),
            ]),
            'enquiries' => self::module(__('Admission enquiries'), __('Admission enquiries'), ['enquiries.view'], ['enquiries.manage'], [
                'enquiries.approve' => __('Approve age and fee dues'),
            ]),
            'complaints' => self::module(__('Complaints'), __('Complaints'), ['complaints.view'], ['complaints.manage'], [
                'complaints.configure' => __('Set up categories'),
            ]),
            'ptm' => self::module(__('Parent teacher meetings'), __('Meetings and invitations'), ['ptm.view'], ['ptm.manage']),
            'documents' => self::module(__('Documents'), __('Upload and share documents'), [], ['documents.manage']),
            'discipline' => self::module(__('Guidance and violation'), __('Records and categories'), ['discipline.view'], ['discipline.manage'], [
                'discipline.record' => __('Record incidents'),
            ]),
            'messages' => self::module(__('Messages to parents'), __('WhatsApp messages and their log'), [], ['parents.message']),
            'teachers' => self::module(__('Teachers'), __('Teachers and their leave days'), ['hr.view'], ['hr.manage']),
            'employees' => self::module(__('Employees and leave'), __('Employee files, leave requests, allowances'), ['employees.view'], ['employees.manage'], [
                'leaves.approve' => __('Approve leave'),
            ]),
            'payroll' => self::module(__('Payroll'), __('Salaries, payroll runs, salary slips'), ['payroll.view'], ['payroll.manage']),
            'recruitment' => self::module(__('Recruitment'), __('Job openings and applicants'), ['recruitment.view'], ['recruitment.manage']),
            'appraisals' => self::module(__('Performance evaluation'), __('Evaluations, cycles, criteria'), ['appraisals.view'], ['appraisals.manage']),
            'payables' => self::module(__('Account payable'), __('Vendors, bills, payments, aging'), ['payables.view'], ['payables.manage']),
            'users' => self::module(__('Users, roles and settings'), __('Users, roles, branches, years, settings'), ['users.view'], ['users.manage', 'roles.manage', 'settings.manage']),
        ];
    }

    /**
     * Every permission the Roles screen decides. A role keeps its other permissions (those of the older screens)
     * as they are.
     *
     * @return list<string>
     */
    public static function permissions(): array
    {
        return collect(self::all())
            ->flatMap(fn (array $module): array => [...$module['view'], ...$module['manage'], ...array_keys($module['extras'])])
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The levels a module offers: a module without "view" permissions is either off or fully on.
     *
     * @return array<string, string>
     */
    public static function levelOptions(string $module): array
    {
        return [
            self::NONE => __('No access'),
            ...(self::all()[$module]['view'] === [] ? [] : [self::READ => __('Read only')]),
            self::FULL => __('Full access'),
        ];
    }

    /**
     * The level a role has on each module.
     *
     * @return array<string, string>
     */
    public static function levelsOf(Role $role): array
    {
        $held = $role->permissions->pluck('name')->all();

        return collect(self::all())
            ->map(fn (array $module): string => match (true) {
                array_diff([...$module['view'], ...$module['manage']], $held) === [] => self::FULL,
                $module['view'] !== [] && array_diff($module['view'], $held) === [] => self::READ,
                default => self::NONE,
            })
            ->all();
    }

    /**
     * The extra rights a role holds.
     *
     * @return list<string>
     */
    public static function extrasOf(Role $role): array
    {
        $extras = collect(self::all())->flatMap(fn (array $module): array => array_keys($module['extras']))->all();

        return array_values(array_intersect($extras, $role->permissions->pluck('name')->all()));
    }

    /**
     * The permissions that a set of levels and extra rights stand for. An extra right of a module the role cannot
     * open is dropped.
     *
     * @param  array<string, string>  $levels  module key => level
     * @param  list<string>  $extras
     * @return list<string>
     */
    public static function permissionsFor(array $levels, array $extras = []): array
    {
        $permissions = [];

        foreach (self::all() as $key => $module) {
            $level = $levels[$key] ?? self::NONE;

            if ($level === self::FULL) {
                $permissions = [...$permissions, ...$module['view'], ...$module['manage']];
            } elseif ($level === self::READ && $module['view'] !== []) {
                $permissions = [...$permissions, ...$module['view']];
            } else {
                continue;
            }

            $permissions = [...$permissions, ...array_intersect(array_keys($module['extras']), $extras)];
        }

        return array_values(array_unique($permissions));
    }

    /**
     * Gives a role the levels and extra rights chosen on the Roles screen. Its permissions outside the modules
     * stay as they are.
     *
     * @param  array<string, string>  $levels
     * @param  list<string>  $extras
     */
    public static function apply(Role $role, array $levels, array $extras = []): Role
    {
        $kept = $role->permissions->pluck('name')->diff(self::permissions())->all();
        $wanted = self::permissionsFor($levels, $extras);

        foreach ($wanted as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $role->syncPermissions([...$kept, ...$wanted]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role->refresh();
    }

    /**
     * The roles a new installation starts with, besides the administrator and the front office staff: what each
     * can open, and its extra rights. An administrator changes them on the Roles screen.
     *
     * @return array<string, array{levels: array<string, string>, extras: list<string>}>
     */
    public static function defaultRoles(): array
    {
        $readEverything = collect(self::all())
            ->map(fn (array $module): string => $module['view'] === [] ? self::NONE : self::READ)
            ->all();

        return [
            self::TEACHER => ['levels' => [], 'extras' => []],
            'accountant' => [
                'levels' => ['fees' => self::FULL, 'payroll' => self::FULL, 'payables' => self::FULL, 'students' => self::READ, 'admissions' => self::READ, 'employees' => self::READ],
                'extras' => ['fees.collect'],
            ],
            'principal' => [
                'levels' => [...$readEverything, 'recruitment' => self::FULL, 'appraisals' => self::FULL, 'users' => self::NONE],
                'extras' => ['fees.approve', 'leaves.approve', 'enquiries.approve'],
            ],
        ];
    }

    /**
     * The name of a role as the screens show it.
     */
    public static function roleLabel(string $name): string
    {
        return match ($name) {
            self::ADMINISTRATOR => __('Administrator'),
            'secretary' => __('Staff'),
            self::TEACHER => __('Teacher'),
            default => ucfirst($name),
        };
    }

    /**
     * @param  list<string>  $view
     * @param  list<string>  $manage
     * @param  array<string, string>  $extras
     * @return array{label: string, screens: string, view: list<string>, manage: list<string>, extras: array<string, string>}
     */
    private static function module(string $label, string $screens, array $view, array $manage, array $extras = []): array
    {
        return ['label' => $label, 'screens' => $screens, 'view' => $view, 'manage' => $manage, 'extras' => $extras];
    }
}
