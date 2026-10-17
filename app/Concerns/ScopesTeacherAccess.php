<?php

namespace App\Concerns;

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait ScopesTeacherAccess
{
    /**
     * null  => user is not a scoped teacher (sees everything)
     * int   => the teacher's staff id (0 when the teacher has no staff record)
     */
    protected function scopedStaffId(Request $request): ?int
    {
        $user = $request->user();

        if (! ($user->hasRole('Teacher') && ! $user->hasRole('Super Admin'))) {
            return null;
        }

        return (int) Staff::query()->where('user_id', $user->id)->value('id');
    }

    protected function yearIsLocked(?int $yearId): bool
    {
        return $yearId !== null
            && AcademicYear::query()->find($yearId)?->status === AcademicYearStatus::Locked;
    }

    /** Requires sections.teacherClassAssignments to be loaded. */
    protected function visibleSections(SchoolClass $schoolClass, ?int $staffId): Collection
    {
        return $schoolClass->sections
            ->when($staffId !== null, fn (Collection $sections) => $sections->filter(
                fn (Section $section): bool => $section->teacherClassAssignments->contains('staff_id', $staffId),
            ))
            ->values();
    }

    /** Single place to adapt the student columns shown in the tables. */
    protected function studentPayload($student, Section $section): array
    {
        return [
            'id' => $student->id,
            'studentNumber' => $student->admission_number,
            'name' => collect([$student->first_name, $student->middle_name, $student->last_name])
                ->filter()
                ->implode(' '),
            'gender' => $student->gender,
            'status' => $student->status instanceof \BackedEnum ? $student->status->value : $student->status,
            'sectionId' => $section->id,
            'sectionName' => $section->name,
        ];
    }
}