<?php

namespace App\Http\Requests;

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\Staff;
use App\Models\TeacherSubjectAssignment;
use Illuminate\Foundation\Http\FormRequest;

class DeleteHomeworkAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $assignment = $this->route('homeworkAssignment');

        if (! $user || ! $user->can('homework.delete')) {
            return false;
        }

        $academicYear = AcademicYear::query()->find($assignment->academic_year_id);

        if ($academicYear?->status === AcademicYearStatus::Locked) {
            return false;
        }

        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Teachers may only delete homework for a section/subject they teach.
        $staff = Staff::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where('is_teacher', true)
            ->first();

        return $staff !== null && TeacherSubjectAssignment::query()
            ->where('staff_id', $staff->id)
            ->where('section_id', $assignment->section_id)
            ->where('subject_id', $assignment->subject_id)
            ->where('academic_year_id', $assignment->academic_year_id)
            ->exists();
    }

    public function rules(): array
    {
        return [];
    }
}