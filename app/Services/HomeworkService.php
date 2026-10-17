<?php

namespace App\Services;

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\Enrolment;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkSubmission;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HomeworkService
{
    public function __construct(private StudentActivityService $activities) {}

    /**
     * @param  array{section_id: int, subject_id: int, title: string, instructions: string, assigned_date: string, due_date: string, max_points: int|float}  $data
     */
    public function create(array $data, User $actor): HomeworkAssignment
    {
        return DB::transaction(function () use ($data, $actor): HomeworkAssignment {
            $section = Section::query()
                ->with('academicYear')
                ->lockForUpdate()
                ->findOrFail($data['section_id']);
            $year = AcademicYear::query()->lockForUpdate()->findOrFail($section->academic_year_id);

            if ($year->status === AcademicYearStatus::Locked) {
                throw ValidationException::withMessages([
                    'section_id' => 'Homework cannot be changed in a locked academic year.',
                ]);
            }

            $staff = $this->activeTeacherFor($actor, 'homework.create');
            $this->authorizeSubjectAssignment($staff, $section->academic_year_id, $section->id, $data['subject_id']);

            $assignedDate = Carbon::parse($data['assigned_date']);
            $dueDate = Carbon::parse($data['due_date']);
            if (
                $assignedDate->lt($year->start_date)
                || $assignedDate->gt($year->end_date)
                || $dueDate->lt($year->start_date)
                || $dueDate->gt($year->end_date)
            ) {
                throw ValidationException::withMessages([
                    'due_date' => 'The assignment dates must fall within the section academic year.',
                ]);
            }

            return HomeworkAssignment::query()->create([
                ...$data,
                'academic_year_id' => $year->id,
                'assigned_by' => $actor->id,
            ]);
        });
    }

    /**
     * @param  array{title: string, instructions: string, assigned_date: string, due_date: string, max_points: int|float}  $data
     */
    public function update(
        HomeworkAssignment $homeworkAssignment,
        array $data,
        User $actor,
    ): HomeworkAssignment {
        if (! $actor->can('homework.update')) {
            throw new AuthorizationException('You are not allowed to update homework.');
        }

        return DB::transaction(function () use ($homeworkAssignment, $data, $actor): HomeworkAssignment {
            $assignment = HomeworkAssignment::query()
                ->with('academicYear')
                ->lockForUpdate()
                ->findOrFail($homeworkAssignment->id);
            $year = AcademicYear::query()->lockForUpdate()->findOrFail($assignment->academic_year_id);

            if ($year->status === AcademicYearStatus::Locked) {
                throw ValidationException::withMessages([
                    'title' => 'Homework cannot be changed in a locked academic year.',
                ]);
            }

            $staff = $this->activeTeacherFor($actor, 'homework.update');
            $this->authorizeSubjectAssignment($staff, $assignment->academic_year_id, $assignment->section_id, $assignment->subject_id);

            $assignedDate = Carbon::parse($data['assigned_date']);
            $dueDate = Carbon::parse($data['due_date']);
            if (
                $assignedDate->lt($year->start_date)
                || $assignedDate->gt($year->end_date)
                || $dueDate->lt($year->start_date)
                || $dueDate->gt($year->end_date)
            ) {
                throw ValidationException::withMessages([
                    'due_date' => 'The assignment dates must fall within the section academic year.',
                ]);
            }

            $hasScoreAboveMaximum = $assignment->submissions()
                ->whereNotNull('score')
                ->where('score', '>', $data['max_points'])
                ->exists();
            if ($hasScoreAboveMaximum) {
                throw ValidationException::withMessages([
                    'max_points' => 'The maximum cannot be lower than a score already awarded.',
                ]);
            }

            $assignment->update($data);

            return $assignment->refresh();
        });
    }

    /**
     * @param  array{content: string}  $data
     */
    public function submit(
        HomeworkAssignment $homeworkAssignment,
        array $data,
        User $actor,
    ): HomeworkSubmission {
        if (! $actor->can('portal.homework.submit')) {
            throw new AuthorizationException('You are not allowed to submit homework.');
        }

        return DB::transaction(function () use ($homeworkAssignment, $data, $actor): HomeworkSubmission {
            $assignment = HomeworkAssignment::query()
                ->with('academicYear')
                ->lockForUpdate()
                ->findOrFail($homeworkAssignment->id);
            $year = AcademicYear::query()->lockForUpdate()->findOrFail($assignment->academic_year_id);

            if ($year->status === AcademicYearStatus::Locked) {
                throw ValidationException::withMessages([
                    'content' => 'Homework submissions are closed for this locked academic year.',
                ]);
            }

            $student = Student::query()
                ->where('user_id', $actor->id)
                ->lockForUpdate()
                ->first();
            if (! $student || $student->status !== 'active') {
                throw new AuthorizationException('An active student profile is required to submit homework.');
            }

            $activeYear = AcademicYear::active()->first();
            $isEnrolled = $activeYear?->id === $year->id
                && Enrolment::query()
                    ->forAcademicYear($year)
                    ->where('student_id', $student->id)
                    ->where('section_id', $assignment->section_id)
                    ->where('status', 'active')
                    ->whereDate('enrolment_date', '<=', today())
                    ->where(fn ($query) => $query
                        ->whereNull('exit_date')
                        ->orWhereDate('exit_date', '>=', today()))
                    ->exists();

            abort_unless($isEnrolled, 404);

            if (today()->lt($assignment->assigned_date) || today()->gt($assignment->due_date)) {
                throw ValidationException::withMessages([
                    'content' => 'This homework is not currently accepting submissions.',
                ]);
            }

            $submission = HomeworkSubmission::query()
                ->where('homework_assignment_id', $assignment->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->first();

            if ($submission?->graded_at) {
                throw ValidationException::withMessages([
                    'content' => 'A graded submission cannot be changed.',
                ]);
            }

            if ($submission) {
                $submission->update([
                    'content' => $data['content'],
                    'submitted_by' => $actor->id,
                    'submitted_at' => now(),
                ]);

                $eventType = 'homework.resubmitted';
            } else {
                $submission = HomeworkSubmission::query()->create([
                    'homework_assignment_id' => $assignment->id,
                    'student_id' => $student->id,
                    'submitted_by' => $actor->id,
                    'content' => $data['content'],
                    'submitted_at' => now(),
                ]);

                $eventType = 'homework.submitted';
            }

            $this->activities->record(
                $student,
                $actor,
                $eventType,
                ($eventType === 'homework.submitted' ? 'Homework submitted' : 'Homework resubmitted').' for '.$assignment->title.'.',
                $year,
                [
                    'homework_assignment_id' => $assignment->id,
                    'homework_submission_id' => $submission->id,
                ],
            );

            return $submission->refresh();
        });
    }

    /**
     * @param  array{score: int|float|string, feedback?: ?string}  $data
     */
    public function grade(
        HomeworkAssignment $homeworkAssignment,
        HomeworkSubmission $homeworkSubmission,
        array $data,
        User $actor,
    ): HomeworkSubmission {
        if (! $actor->can('homework.grade')) {
            throw new AuthorizationException('You are not allowed to grade homework.');
        }

        return DB::transaction(function () use ($homeworkAssignment, $homeworkSubmission, $data, $actor): HomeworkSubmission {
            $assignment = HomeworkAssignment::query()
                ->with('academicYear')
                ->lockForUpdate()
                ->findOrFail($homeworkAssignment->id);
            $year = AcademicYear::query()->lockForUpdate()->findOrFail($assignment->academic_year_id);

            if ($year->status === AcademicYearStatus::Locked) {
                throw ValidationException::withMessages([
                    'score' => 'Homework cannot be graded in a locked academic year.',
                ]);
            }

            $staff = $this->activeTeacherFor($actor, 'homework.grade');
            $this->authorizeSubjectAssignment($staff, $assignment->academic_year_id, $assignment->section_id, $assignment->subject_id);

            $submission = HomeworkSubmission::query()
                ->where('homework_assignment_id', $assignment->id)
                ->lockForUpdate()
                ->findOrFail($homeworkSubmission->id);

            if (! $submission->submitted_at) {
                throw ValidationException::withMessages([
                    'score' => 'Only submitted homework can be graded.',
                ]);
            }

            if ((float) $data['score'] > (float) $assignment->max_points) {
                throw ValidationException::withMessages([
                    'score' => 'The score cannot exceed the assignment maximum.',
                ]);
            }

            $previousScore = $submission->score;
            $submission->update([
                'score' => $data['score'],
                'feedback' => $data['feedback'] ?? null,
                'graded_by' => $actor->id,
                'graded_at' => now(),
            ]);

            $this->activities->record(
                $submission->student,
                $actor,
                'homework.graded',
                'Homework graded for '.$assignment->title.'.',
                $year,
                [
                    'homework_assignment_id' => $assignment->id,
                    'homework_submission_id' => $submission->id,
                    'previous_score' => $previousScore,
                    'score' => $data['score'],
                    'max_points' => $assignment->max_points,
                ],
            );

            return $submission->refresh();
        });
    }

    private function activeTeacherFor(User $actor, string $permission): Staff
    {
        $staff = Staff::query()
            ->where('user_id', $actor->id)
            ->where('status', 'active')
            ->where('is_teacher', true)
            ->first();

        if (! $staff || ! $actor->can($permission)) {
            throw new AuthorizationException('An active teacher profile is required for this action.');
        }

        return $staff;
    }

    private function authorizeSubjectAssignment(
        Staff $staff,
        int $academicYearId,
        int $sectionId,
        int $subjectId,
    ): void {
        $isAssigned = TeacherSubjectAssignment::query()
            ->where('academic_year_id', $academicYearId)
            ->where('staff_id', $staff->id)
            ->where('section_id', $sectionId)
            ->where('subject_id', $subjectId)
            ->exists();

        abort_unless($isAssigned, 404);
    }
}
