<?php

namespace App\Services;

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Enrolment;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Models\Section;
use App\Models\Staff;
use App\Models\TeacherClassAssignment;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamService
{
    public function __construct(private StudentActivityService $activities) {}

    /**
     * @param  array{academic_year_id: int, term_id?: ?int, class_id: int, name: string, exam_type: string, start_date: string, end_date: string, subjects: list<array{subject_id: int, exam_date: string, max_marks: int|float, pass_marks: int|float}>}  $data
     */
    public function create(array $data, User $actor): Exam
    {
        if (! $actor->can('exams.create')) {
            throw new AuthorizationException('You are not allowed to create examinations.');
        }

        return DB::transaction(function () use ($data, $actor): Exam {
            $year = AcademicYear::query()->lockForUpdate()->findOrFail($data['academic_year_id']);
            if ($year->status === AcademicYearStatus::Locked) {
                throw ValidationException::withMessages([
                    'academic_year_id' => 'Examinations cannot be added to a locked academic year.',
                ]);
            }

            $schoolClass = $year->classes()->lockForUpdate()->find($data['class_id']);
            if (! $schoolClass) {
                throw ValidationException::withMessages([
                    'class_id' => 'Select a class in the selected academic year.',
                ]);
            }

            $startDate = Carbon::parse($data['start_date']);
            $endDate = Carbon::parse($data['end_date']);
            if ($startDate->lt($year->start_date) || $endDate->gt($year->end_date)) {
                throw ValidationException::withMessages([
                    'start_date' => 'Exam dates must be within the selected academic year.',
                ]);
            }

            if (! empty($data['term_id'])) {
                $term = $year->terms()->lockForUpdate()->find($data['term_id']);
                if (
                    ! $term
                    || $startDate->lt($term->start_date)
                    || $endDate->gt($term->end_date)
                ) {
                    throw ValidationException::withMessages([
                        'term_id' => 'Select a term in the academic year that contains the exam dates.',
                    ]);
                }
            }

            $subjectIds = collect($data['subjects'])->pluck('subject_id')->map(fn ($id): int => (int) $id);
            $classSubjectIds = ClassSubject::query()
                ->where('academic_year_id', $year->id)
                ->where('class_id', $schoolClass->id)
                ->whereIn('subject_id', $subjectIds)
                ->pluck('subject_id')
                ->map(fn ($id): int => (int) $id);

            if ($subjectIds->unique()->count() !== $subjectIds->count() || $classSubjectIds->sort()->values()->all() !== $subjectIds->sort()->values()->all()) {
                throw ValidationException::withMessages([
                    'subjects' => 'Choose only subjects assigned to the selected class.',
                ]);
            }

            $exam = Exam::query()->create([
                'academic_year_id' => $year->id,
                'term_id' => $data['term_id'] ?? null,
                'class_id' => $schoolClass->id,
                'created_by' => $actor->id,
                'name' => $data['name'],
                'exam_type' => $data['exam_type'],
                'status' => 'draft',
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

            foreach ($data['subjects'] as $subject) {
                ExamSubject::query()->create([
                    'exam_id' => $exam->id,
                    'subject_id' => $subject['subject_id'],
                    'exam_date' => $subject['exam_date'],
                    'max_marks' => $subject['max_marks'],
                    'pass_marks' => $subject['pass_marks'],
                ]);
            }

            return $exam->load('subjects.subject');
        });
    }

    /**
     * @param  array{section_id: int, records: list<array{student_id: int, status: string, marks_obtained?: ?float, remarks?: ?string}>}  $data
     */
    public function recordMarks(
        Exam $exam,
        ExamSubject $examSubject,
        array $data,
        User $actor,
    ): Collection {
        return DB::transaction(function () use ($exam, $examSubject, $data, $actor): Collection {
            $exam = Exam::query()->lockForUpdate()->findOrFail($exam->id);
            $subject = ExamSubject::query()
                ->with('subject:id,name')
                ->where('exam_id', $exam->id)
                ->lockForUpdate()
                ->findOrFail($examSubject->id);
            $year = AcademicYear::query()->lockForUpdate()->findOrFail($exam->academic_year_id);

            $this->ensureYearEditable($year);
            if ($exam->status !== 'draft') {
                throw ValidationException::withMessages([
                    'records' => 'Reopen the examination before changing published marks.',
                ]);
            }

            $section = Section::query()
                ->where('academic_year_id', $year->id)
                ->where('class_id', $exam->class_id)
                ->lockForUpdate()
                ->findOrFail($data['section_id']);
            $staff = $this->activeTeacherFor($actor, 'marks.enter', 'marks.update');
            if ($staff) {
                $this->authorizeSectionSubject($staff, $year->id, $section->id, $subject->subject_id);
            }

            $roster = $this->sectionRoster($year->id, $exam->class_id, $section, $subject->exam_date);
            $studentIds = collect($data['records'])->pluck('student_id')->map(fn ($id): int => (int) $id);
            $rosterIds = $roster->pluck('student_id')->map(fn ($id): int => (int) $id);

            if ($studentIds->unique()->count() !== $studentIds->count() || $studentIds->sort()->values()->all() !== $rosterIds->sort()->values()->all()) {
                throw ValidationException::withMessages([
                    'records' => 'Marks must include every student in the selected section exactly once.',
                ]);
            }

            $marksByStudent = ExamMark::query()
                ->where('exam_subject_id', $subject->id)
                ->whereIn('student_id', $studentIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('student_id');

            if ($marksByStudent->isNotEmpty() && ! $actor->can('marks.update')) {
                throw new AuthorizationException('You are not allowed to update recorded marks.');
            }
            if ($marksByStudent->count() < $studentIds->count() && ! $actor->can('marks.enter')) {
                throw new AuthorizationException('You are not allowed to enter marks.');
            }

            foreach ($data['records'] as $record) {
                $status = $record['status'];
                $score = $status === 'present' ? ($record['marks_obtained'] ?? null) : null;

                if ($status === 'present' && $score === null) {
                    throw ValidationException::withMessages([
                        'records' => 'Enter a score for every student marked present.',
                    ]);
                }
                if ($score !== null && (float) $score > (float) $subject->max_marks) {
                    throw ValidationException::withMessages([
                        'records' => 'A score cannot exceed the maximum marks for this subject.',
                    ]);
                }

                $mark = $marksByStudent->get($record['student_id']);
                $isUpdate = (bool) $mark;
                $previousStatus = $mark?->status;
                $previousScore = $mark?->marks_obtained;
                if ($mark) {
                    $mark->update([
                        'marks_obtained' => $score,
                        'status' => $status,
                        'remarks' => $record['remarks'] ?? null,
                        'updated_by' => $actor->id,
                    ]);
                } else {
                    $mark = ExamMark::query()->create([
                        'exam_subject_id' => $subject->id,
                        'student_id' => $record['student_id'],
                        'entered_by' => $actor->id,
                        'marks_obtained' => $score,
                        'status' => $status,
                        'remarks' => $record['remarks'] ?? null,
                    ]);
                }

                $student = $roster->firstWhere('student_id', $record['student_id'])->student;
                $this->activities->record(
                    $student,
                    $actor,
                    $isUpdate ? 'exam.mark_updated' : 'exam.mark_recorded',
                    ($isUpdate ? 'Exam mark updated' : 'Exam mark recorded').' for '.$exam->name.' - '.$subject->subject->name.'.',
                    $year,
                    [
                        'exam_id' => $exam->id,
                        'exam_subject_id' => $subject->id,
                        'exam_mark_id' => $mark->id,
                        'subject_id' => $subject->subject_id,
                        'previous_status' => $previousStatus,
                        'status' => $status,
                        'previous_marks_obtained' => $previousScore,
                        'marks_obtained' => $score,
                    ],
                );
            }

            return ExamMark::query()
                ->where('exam_subject_id', $subject->id)
                ->whereIn('student_id', $studentIds)
                ->get();
        });
    }

    public function publish(Exam $exam, User $actor): Exam
    {
        if (! $actor->can('marks.publish')) {
            throw new AuthorizationException('You are not allowed to publish examination results.');
        }

        return DB::transaction(function () use ($exam, $actor): Exam {
            $exam = Exam::query()->lockForUpdate()->findOrFail($exam->id);
            $year = AcademicYear::query()->lockForUpdate()->findOrFail($exam->academic_year_id);
            $this->ensureYearEditable($year);

            if ($exam->status !== 'draft') {
                throw ValidationException::withMessages([
                    'exam' => 'Only draft examinations can be published.',
                ]);
            }

            $subjects = $exam->subjects()
                ->with(['subject:id,name', 'marks.student:id'])
                ->lockForUpdate()
                ->get();
            if ($subjects->isEmpty()) {
                throw ValidationException::withMessages([
                    'exam' => 'Add at least one subject before publishing results.',
                ]);
            }

            foreach ($subjects as $subject) {
                $expectedStudentIds = $this->classRoster($year->id, $exam->class_id, $subject->exam_date)
                    ->pluck('student_id')
                    ->map(fn ($id): int => (int) $id)
                    ->sort()
                    ->values();
                $recordedStudentIds = $subject->marks()
                    ->pluck('student_id')
                    ->map(fn ($id): int => (int) $id)
                    ->sort()
                    ->values();

                if (
                    $expectedStudentIds->isEmpty()
                    || $recordedStudentIds->all() !== $expectedStudentIds->all()
                ) {
                    throw ValidationException::withMessages([
                        'exam' => 'Enter a mark status for every enrolled student in every subject before publishing.',
                    ]);
                }
            }

            $exam->update([
                'status' => 'published',
                'published_by' => $actor->id,
                'published_at' => now(),
            ]);

            foreach ($subjects as $subject) {
                foreach ($subject->marks as $mark) {
                    $this->activities->record(
                        $mark->student,
                        $actor,
                        'exam.result_published',
                        'Results published for '.$exam->name.' - '.$subject->subject->name.'.',
                        $year,
                        [
                            'exam_id' => $exam->id,
                            'exam_subject_id' => $subject->id,
                            'exam_mark_id' => $mark->id,
                            'subject_id' => $subject->subject_id,
                        ],
                    );
                }
            }

            return $exam->refresh();
        });
    }

    public function reopen(Exam $exam, User $actor): Exam
    {
        if (! $actor->can('marks.reopen')) {
            throw new AuthorizationException('You are not allowed to reopen published results.');
        }

        return DB::transaction(function () use ($exam, $actor): Exam {
            $exam = Exam::query()->lockForUpdate()->findOrFail($exam->id);
            $year = AcademicYear::query()->lockForUpdate()->findOrFail($exam->academic_year_id);
            $this->ensureYearEditable($year);

            if ($exam->status !== 'published') {
                throw ValidationException::withMessages([
                    'exam' => 'Only published examinations can be reopened.',
                ]);
            }

            $exam->update([
                'status' => 'draft',
                'reopened_by' => $actor->id,
                'reopened_at' => now(),
            ]);

            $subjects = $exam->subjects()
                ->with(['subject:id,name', 'marks.student:id'])
                ->get();
            foreach ($subjects as $subject) {
                foreach ($subject->marks as $mark) {
                    $this->activities->record(
                        $mark->student,
                        $actor,
                        'exam.result_reopened',
                        'Results reopened for '.$exam->name.' - '.$subject->subject->name.'.',
                        $year,
                        [
                            'exam_id' => $exam->id,
                            'exam_subject_id' => $subject->id,
                            'exam_mark_id' => $mark->id,
                            'subject_id' => $subject->subject_id,
                        ],
                    );
                }
            }

            return $exam->refresh();
        });
    }

    /**
     * @return Collection<int, Enrolment>
     */
    public function sectionRoster(int $yearId, int $classId, Section $section, Carbon $date): Collection
    {
        return Enrolment::query()
            ->where('academic_year_id', $yearId)
            ->where('class_id', $classId)
            ->where('section_id', $section->id)
            ->whereIn('status', ['active', 'completed'])
            ->whereDate('enrolment_date', '<=', $date)
            ->where(fn ($query) => $query
                ->whereNull('exit_date')
                ->orWhereDate('exit_date', '>=', $date))
            ->with('student:id,first_name,middle_name,last_name,admission_number')
            ->orderBy('student_id')
            ->get();
    }

    /**
     * @return Collection<int, Enrolment>
     */
    public function classRoster(int $yearId, int $classId, Carbon $date): Collection
    {
        return Enrolment::query()
            ->where('academic_year_id', $yearId)
            ->where('class_id', $classId)
            ->whereIn('status', ['active', 'completed'])
            ->whereDate('enrolment_date', '<=', $date)
            ->where(fn ($query) => $query
                ->whereNull('exit_date')
                ->orWhereDate('exit_date', '>=', $date))
            ->orderBy('student_id')
            ->get();
    }

    public function update(Exam $exam, array $data, User $actor): Exam
    {
        if (! $actor->can('exams.update')) {
            throw new AuthorizationException('You are not allowed to edit examinations.');
        }
    
        return DB::transaction(function () use ($exam, $data): Exam {
            $exam = Exam::query()->lockForUpdate()->findOrFail($exam->id);
            $year = AcademicYear::query()->lockForUpdate()->findOrFail($exam->academic_year_id);
            $this->ensureYearEditable($year);
    
            if ($exam->status !== 'draft') {
                throw ValidationException::withMessages([
                    'exam' => 'Only draft examinations can be edited. Reopen it first.',
                ]);
            }
    
            $startDate = Carbon::parse($data['start_date']);
            $endDate = Carbon::parse($data['end_date']);
            if ($startDate->lt($year->start_date) || $endDate->gt($year->end_date)) {
                throw ValidationException::withMessages([
                    'start_date' => 'Exam dates must be within the selected academic year.',
                ]);
            }
    
            if (! empty($data['term_id'])) {
                $term = $year->terms()->find($data['term_id']);
                if (! $term || $startDate->lt($term->start_date) || $endDate->gt($term->end_date)) {
                    throw ValidationException::withMessages([
                        'term_id' => 'Select a term in the academic year that contains the exam dates.',
                    ]);
                }
            }
    
            $incoming = collect($data['subjects'])->keyBy(fn (array $row): int => (int) $row['subject_id']);
            $classSubjectIds = ClassSubject::query()
                ->where('academic_year_id', $year->id)
                ->where('class_id', $exam->class_id)
                ->whereIn('subject_id', $incoming->keys())
                ->pluck('subject_id')
                ->map(fn ($id): int => (int) $id);
    
            if ($classSubjectIds->count() !== $incoming->count()) {
                throw ValidationException::withMessages([
                    'subjects' => 'Choose only subjects assigned to the selected class.',
                ]);
            }
    
            $exam->update([
                'term_id' => $data['term_id'] ?? null,
                'name' => $data['name'],
                'exam_type' => $data['exam_type'],
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);
    
            foreach ($exam->subjects()->with('subject:id,name')->withCount('marks')->get() as $examSubject) {
                $row = $incoming->get((int) $examSubject->subject_id);
    
                if ($row === null) {
                    if ($examSubject->marks_count > 0) {
                        throw ValidationException::withMessages([
                            'subjects' => "{$examSubject->subject->name} already has marks recorded and cannot be removed.",
                        ]);
                    }
                    $examSubject->delete();
    
                    continue;
                }
    
                if ($examSubject->marks_count > 0) {
                    // The roster depends on the exam date, so it is frozen once marks exist.
                    if ($examSubject->exam_date->toDateString() !== Carbon::parse($row['exam_date'])->toDateString()) {
                        throw ValidationException::withMessages([
                            'subjects' => "The exam date for {$examSubject->subject->name} cannot change after marks are recorded.",
                        ]);
                    }
                    if ($examSubject->marks()->where('marks_obtained', '>', $row['max_marks'])->exists()) {
                        throw ValidationException::withMessages([
                            'subjects' => "{$examSubject->subject->name} has recorded marks above the new maximum.",
                        ]);
                    }
                }
            }
    
            foreach ($incoming as $subjectId => $row) {
                ExamSubject::query()->updateOrCreate(
                    ['exam_id' => $exam->id, 'subject_id' => $subjectId],
                    [
                        'exam_date' => $row['exam_date'],
                        'max_marks' => $row['max_marks'],
                        'pass_marks' => $row['pass_marks'],
                    ],
                );
            }
    
            return $exam->load('subjects.subject');
        });
    }
    
    /** Deletes a draft examination that has no recorded marks. */
    public function delete(Exam $exam, User $actor): void
    {
        if (! $actor->can('exams.delete')) {
            throw new AuthorizationException('You are not allowed to delete examinations.');
        }
    
        DB::transaction(function () use ($exam): void {
            $exam = Exam::query()->lockForUpdate()->findOrFail($exam->id);
            $year = AcademicYear::query()->lockForUpdate()->findOrFail($exam->academic_year_id);
            $this->ensureYearEditable($year);
    
            if ($exam->status !== 'draft') {
                throw ValidationException::withMessages([
                    'exam' => 'Only draft examinations can be deleted.',
                ]);
            }
            if ($exam->subjects()->whereHas('marks')->exists()) {
                throw ValidationException::withMessages([
                    'exam' => 'This examination has recorded marks and cannot be deleted.',
                ]);
            }
    
            $exam->subjects()->delete();
            $exam->delete();
        });
    }

    private function activeTeacherFor(User $actor, string ...$permissions): ?Staff
    {
        if (! collect($permissions)->contains(fn (string $permission): bool => $actor->can($permission))) {
            throw new AuthorizationException('You are not allowed to enter or update marks.');
        }
        if ($actor->hasRole('Super Admin')) {
            return null;
        }

        $staff = Staff::query()
            ->where('user_id', $actor->id)
            ->where('status', 'active')
            ->where('is_teacher', true)
            ->first();

        if (! $staff) {
            throw new AuthorizationException('An assigned teacher profile is required for this action.');
        }

        return $staff;
    }

    private function authorizeSectionSubject(
        Staff $staff,
        int $yearId,
        int $sectionId,
        int $subjectId,
    ): void {
        $classAssigned = TeacherClassAssignment::query()
            ->where('academic_year_id', $yearId)
            ->where('staff_id', $staff->id)
            ->where('section_id', $sectionId)
            ->exists();
        $subjectAssigned = TeacherSubjectAssignment::query()
            ->where('academic_year_id', $yearId)
            ->where('staff_id', $staff->id)
            ->where('section_id', $sectionId)
            ->where('subject_id', $subjectId)
            ->exists();

        abort_unless($classAssigned && $subjectAssigned, 404);
    }

    private function ensureYearEditable(AcademicYear $year): void
    {
        if ($year->status === AcademicYearStatus::Locked) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'Results cannot be changed in a locked academic year.',
            ]);
        }
    }
}
