<?php

namespace App\Concerns;

use App\Models\Exam;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
 
trait ManagesExamAccess
{
    /** @return array{0: bool, 1: bool, 2: ?Staff} [canManage, isTeacher, staff] */
    protected function examAccess(Request $request): array
    {
        $user = $request->user();
 
        $canManage = $user->can('exams.create');
        $isTeacher = $user->hasRole('Teacher')
            && ! $user->hasRole('Super Admin')
            && ! $user->can('exams.create');
        abort_unless($canManage || $user->can('exams.view'), 403);
 
        $staff = $isTeacher
            ? Staff::query()->where('user_id', $user->id)->where('status', 'active')->where('is_teacher', true)->first()
            : null;
 
        return [$canManage, $isTeacher, $staff];
    }
 
    /** Teachers only see exams that include a subject they teach in that class. */
    protected function restrictToTeacherExams(Builder $query, ?Staff $staff, int $yearId): Builder
    {
        return $query->whereHas('subjects', fn ($subjects) => $subjects
            ->whereHas('subject.teacherSubjectAssignments', fn ($assignments) => $assignments
                ->where('teacher_subject_assignments.academic_year_id', $yearId)
                ->where('teacher_subject_assignments.staff_id', $staff?->id ?? 0)
                ->whereHas('section', fn ($sections) => $sections
                    ->whereColumn('sections.class_id', 'exams.class_id')
                    ->whereColumn('sections.academic_year_id', 'exams.academic_year_id'))));
    }
 
    /** @return array{0: bool, 1: bool, 2: ?Staff} Aborts 403 when a teacher cannot see this exam. */
    protected function authorizeExam(Request $request, Exam $exam): array
    {
        [$canManage, $isTeacher, $staff] = $this->examAccess($request);
 
        if ($isTeacher) {
            abort_unless(
                $this->restrictToTeacherExams(Exam::query()->whereKey($exam->id), $staff, (int) $exam->academic_year_id)->exists(),
                403,
            );
        }
 
        return [$canManage, $isTeacher, $staff];
    }
}
