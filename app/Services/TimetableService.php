<?php

namespace App\Services;

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Section;
use App\Models\TeacherClassAssignment;
use App\Models\TeacherSubjectAssignment;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimetableService
{
    /**
     * @param  array{academic_year_id: int, teacher_subject_assignment_id: int, day_of_week: int, starts_at: string, ends_at: string, room?: ?string}  $data
     */
    public function create(array $data, User $actor): TimetableSlot
    {
        $this->authorizeManager($actor);

        return DB::transaction(function () use ($data): TimetableSlot {
            $year = AcademicYear::query()->lockForUpdate()->findOrFail($data['academic_year_id']);
            $this->ensureYearEditable($year);
            $assignment = $this->resolveAssignment($year, $data['teacher_subject_assignment_id']);
            $this->assertNoConflicts($year->id, $assignment, $data['day_of_week'], $data['starts_at'], $data['ends_at']);

            return TimetableSlot::query()->create([
                'academic_year_id' => $year->id,
                'teacher_subject_assignment_id' => $assignment->id,
                'day_of_week' => $data['day_of_week'],
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'room' => $data['room'] ?? null,
            ]);
        });
    }

    /**
     * @param  array{teacher_subject_assignment_id: int, day_of_week: int, starts_at: string, ends_at: string, room?: ?string}  $data
     */
    public function update(TimetableSlot $slot, array $data, User $actor): TimetableSlot
    {
        $this->authorizeManager($actor);

        return DB::transaction(function () use ($slot, $data): TimetableSlot {
            $lockedSlot = TimetableSlot::query()->lockForUpdate()->findOrFail($slot->id);
            $year = AcademicYear::query()->lockForUpdate()->findOrFail($lockedSlot->academic_year_id);
            $this->ensureYearEditable($year);
            $assignment = $this->resolveAssignment($year, $data['teacher_subject_assignment_id']);
            $this->assertNoConflicts(
                $year->id,
                $assignment,
                $data['day_of_week'],
                $data['starts_at'],
                $data['ends_at'],
                $lockedSlot->id,
            );

            $lockedSlot->update([
                'teacher_subject_assignment_id' => $assignment->id,
                'day_of_week' => $data['day_of_week'],
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'room' => $data['room'] ?? null,
            ]);

            return $lockedSlot->refresh();
        });
    }

    public function delete(TimetableSlot $slot, User $actor): void
    {
        $this->authorizeManager($actor);

        DB::transaction(function () use ($slot): void {
            $lockedSlot = TimetableSlot::query()->lockForUpdate()->findOrFail($slot->id);
            $year = AcademicYear::query()->lockForUpdate()->findOrFail($lockedSlot->academic_year_id);
            $this->ensureYearEditable($year);
            $lockedSlot->delete();
        });
    }

    private function resolveAssignment(AcademicYear $year, int $assignmentId): TeacherSubjectAssignment
    {
        $assignment = TeacherSubjectAssignment::query()
            ->with(['section', 'staff'])
            ->lockForUpdate()
            ->findOrFail($assignmentId);
        $section = $assignment->section;
        $staff = $assignment->staff;

        if (! $section || ! $staff) {
            throw ValidationException::withMessages([
                'teacher_subject_assignment_id' => 'Choose a valid active teacher and subject assignment for this academic year.',
            ]);
        }

        $isAssignmentValid = $assignment->academic_year_id === $year->id
            && $section->academic_year_id === $year->id
            && $staff->is_teacher
            && $staff->status === 'active'
            && TeacherClassAssignment::query()
                ->where('academic_year_id', $year->id)
                ->where('staff_id', $staff->id)
                ->where('section_id', $section->id)
                ->exists()
            && ClassSubject::query()
                ->where('academic_year_id', $year->id)
                ->where('class_id', $section->class_id)
                ->where('subject_id', $assignment->subject_id)
                ->exists()
            && Section::query()
                ->whereKey($section->id)
                ->whereHas('schoolClass', fn ($query) => $query->where('academic_year_id', $year->id))
                ->exists();

        if (! $isAssignmentValid) {
            throw ValidationException::withMessages([
                'teacher_subject_assignment_id' => 'Choose a valid active teacher and subject assignment for this academic year.',
            ]);
        }

        return $assignment;
    }

    private function assertNoConflicts(
        int $academicYearId,
        TeacherSubjectAssignment $assignment,
        int $dayOfWeek,
        string $startsAt,
        string $endsAt,
        ?int $exceptSlotId = null,
    ): void {
        $overlapping = TimetableSlot::query()
            ->where('academic_year_id', $academicYearId)
            ->where('day_of_week', $dayOfWeek)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->when($exceptSlotId, fn ($query) => $query->where('id', '!=', $exceptSlotId));

        if ((clone $overlapping)->whereHas('teachingAssignment', fn ($query) => $query
            ->where('section_id', $assignment->section_id))->exists()) {
            throw ValidationException::withMessages([
                'section_id' => 'The section already has a class scheduled during this time.',
            ]);
        }

        if ((clone $overlapping)->whereHas('teachingAssignment', fn ($query) => $query
            ->where('staff_id', $assignment->staff_id))->exists()) {
            throw ValidationException::withMessages([
                'teacher_subject_assignment_id' => 'The teacher is already scheduled during this time.',
            ]);
        }
    }

    private function ensureYearEditable(AcademicYear $year): void
    {
        if ($year->status === AcademicYearStatus::Locked) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'A locked academic year timetable cannot be changed.',
            ]);
        }
    }

    private function authorizeManager(User $actor): void
    {
        if (! $actor->can('timetable.manage')) {
            throw new AuthorizationException('You are not allowed to manage timetables.');
        }
    }
}
