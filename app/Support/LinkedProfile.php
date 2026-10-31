<?php

namespace App\Support;

use App\Models\AcademicYear;
use App\Models\Enrolment;
use App\Models\Guardian;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;

/**
 * The staff, student or guardian record a sign-in account belongs to, if any.
 * `locked` lists the account fields the person cannot change themselves because the profile is the source of truth.
 */
final class LinkedProfile
{
    /** @return array{type: string, label: string, href: ?string, locked: list<string>, details: list<array{label: string, value: ?string}>}|null */
    public static function for(User $user): ?array
    {
        if ($staff = Staff::query()->with(['department:id,name', 'designation:id,name'])->where('user_id', $user->id)->first()) {
            return [
                'type' => 'staff',
                'label' => 'Staff profile',
                'href' => null,
                'locked' => ['name', 'email'],
                'details' => [
                    ['label' => 'Staff number', 'value' => $staff->staff_number],
                    ['label' => 'Designation', 'value' => $staff->designation?->name],
                    ['label' => 'Department', 'value' => $staff->department?->name],
                    ['label' => 'Phone', 'value' => $staff->phone],
                    ['label' => 'Joined', 'value' => $staff->joining_date?->toDateString()],
                    ['label' => 'Teaching', 'value' => $staff->is_teacher ? 'Teacher' : 'Non-teaching staff'],
                    ['label' => 'Status', 'value' => ucfirst($staff->status)],
                ],
            ];
        }

        if ($student = Student::query()->where('user_id', $user->id)->first()) {
            $year = AcademicYear::active()->first();
            $enrolment = $year
                ? Enrolment::query()->with(['schoolClass:id,name', 'section:id,name'])->where('student_id', $student->id)->where('academic_year_id', $year->id)->where('status', 'active')->first()
                : null;

            return [
                'type' => 'student',
                'label' => 'Student profile',
                'href' => null,
                'locked' => ['name'],
                'details' => [
                    ['label' => 'Admission number', 'value' => $student->admission_number],
                    ['label' => 'Class', 'value' => $enrolment ? $enrolment->schoolClass?->name.' · Section '.$enrolment->section?->name : null],
                    ['label' => 'Academic year', 'value' => $year?->name],
                    ['label' => 'Status', 'value' => ucfirst($student->status)],
                ],
            ];
        }

        if (Guardian::query()->where('user_id', $user->id)->exists()) {
            $children = Student::query()->forGuardianAccount($user)->orderBy('first_name')->get(['students.id', 'first_name', 'middle_name', 'last_name', 'admission_number']);

            return [
                'type' => 'guardian',
                'label' => 'Parent / guardian profile',
                'href' => null,
                'locked' => [],
                'details' => $children->map(fn (Student $child): array => [
                    'label' => 'Child',
                    'value' => trim(implode(' ', array_filter([$child->first_name, $child->middle_name, $child->last_name]))).' · '.$child->admission_number,
                ])->values()->all(),
            ];
        }

        return null;
    }
}