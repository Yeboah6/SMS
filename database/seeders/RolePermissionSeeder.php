<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    private const ADMIN_ONLY_PERMISSIONS = [
        'settings.view',
        'settings.update',
        'classes.create',
        'classes.update',
        'classes.delete',
        'sections.create',
        'sections.update',
        'sections.delete',
        'subjects.create',
        'subjects.update',
        'subjects.delete',
        'staff.create',
        'staff.update',
        'staff.delete',
        'class_subjects.assign',
        'teacher_assignments.manage',
        'departments.manage',
        'designations.manage',
        'students.update',
        'students.delete',
        'students.enrol',
        'guardians.delete',
        'students.documents.manage',
    ];

    private const ROLE_PERMISSIONS = [
        'Principal' => [
            'dashboard.view', 'academic_years.view', 'students.view', 'guardians.view',
            'student_notes.view', 'admissions.view', 'admissions.approve', 'attendance.view',
            'leave.view', 'leave.approve', 'classes.view', 'sections.view', 'subjects.view',
            'staff.view', 'exams.view', 'marks.view', 'marks.publish', 'marks.reopen',
            'results.view', 'results.publish', 'homework.view', 'notices.view', 'notices.create',
            'notices.publish', 'reports.view', 'reports.export', 'certificates.view',
        ],
        'Teacher' => [
            'dashboard.view', 'students.view', 'guardians.view', 'student_notes.view',
            'student_notes.create', 'classes.view', 'sections.view', 'subjects.view',
            'attendance.view', 'attendance.mark', 'attendance.update', 'leave.apply',
            'timetable.view', 'exams.view', 'marks.view', 'marks.enter', 'marks.update',
            'homework.view', 'homework.create', 'homework.update', 'homework.grade',
            'notices.view', 'reports.view',
        ],
        'Student' => [
            'portal.dashboard.view', 'portal.timetable.view', 'portal.attendance.view',
            'portal.results.view', 'portal.fees.view', 'portal.homework.view',
            'portal.homework.submit', 'portal.leave.apply', 'portal.certificates.request',
            'portal.notices.view',
        ],
        'Parent' => [
            'portal.dashboard.view', 'portal.children.view', 'portal.attendance.view',
            'portal.results.view', 'portal.fees.view', 'portal.homework.view',
            'portal.leave.apply', 'portal.certificates.request', 'portal.notices.view',
        ],
        'Accountant' => [
            'dashboard.view', 'fees.view', 'fees.create', 'fees.update', 'fees.record_payment',
            'fees.issue_receipt', 'fees.export', 'fee_heads.view', 'fee_heads.create',
            'fee_heads.update', 'fee_structures.view', 'fee_structures.create',
            'fee_structures.update', 'invoices.view', 'invoices.create', 'payments.view',
            'payments.create', 'concessions.manage', 'scholarships.manage', 'payroll.view',
            'payroll.process', 'payroll.export', 'expenses.view', 'expenses.create',
            'expenses.update', 'reports.view', 'reports.export',
        ],
        'Librarian' => [
            'dashboard.view', 'library.view', 'library.create', 'library.issue',
            'library.return', 'library.renew', 'reports.view',
        ],
        'Receptionist' => [
            'dashboard.view', 'students.view', 'students.create', 'guardians.view',
            'guardians.create', 'guardians.update', 'students.enrol', 'students.documents.manage', 'admissions.view', 'admissions.create',
            'admissions.update', 'admissions.review', 'admissions.enrol', 'classes.view',
            'sections.view',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionNames = collect(self::ROLE_PERMISSIONS)
            ->flatten()
            ->merge(self::ADMIN_ONLY_PERMISSIONS)
            ->unique()
            ->sort()
            ->values();

        $permissions = $permissionNames
            ->map(fn (string $name): Permission => Permission::findOrCreate($name, 'web'));

        $superAdmin = Role::firstOrCreate([
            'name' => 'Super Admin',
            'guard_name' => 'web',
        ]);

        $superAdmin->syncPermissions($permissions);

        foreach (self::ROLE_PERMISSIONS as $roleName => $rolePermissionNames) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            if ($role->wasRecentlyCreated) {
                $role->syncPermissions(
                    $permissions->whereIn('name', $rolePermissionNames),
                );
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
