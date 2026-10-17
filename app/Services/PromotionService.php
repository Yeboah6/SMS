<?php

namespace App\Services;

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\Enrolment;
use App\Models\Promotion;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PromotionService
{
    public function __construct(private StudentActivityService $activities) {}

    /**
     * @return Builder<Student>
     */
    public function eligibleStudents(AcademicYear $sourceYear, AcademicYear $targetYear): Builder
    {
        return Student::query()
            ->where('status', 'active')
            ->whereHas('enrolments', fn (Builder $enrolments) => $enrolments
                ->forAcademicYear($sourceYear)
                ->where('status', 'active'))
            ->whereDoesntHave('enrolments', fn (Builder $enrolments) => $enrolments
                ->forAcademicYear($targetYear))
            ->with(['enrolments' => fn ($enrolments) => $enrolments
                ->forAcademicYear($sourceYear)
                ->with(['schoolClass:id,name', 'section:id,name'])])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function process(array $data, User $actor): void
    {
        if (! $actor->can('students.promote')) {
            throw new AuthorizationException('You are not allowed to process student promotions.');
        }

        $hasOverride = collect($data['decisions'])
            ->contains(fn (array $decision): bool => in_array($decision['outcome'], ['repeated', 'graduated'], true));

        if ($hasOverride && ! $actor->can('students.promote.override')) {
            throw new AuthorizationException('You are not allowed to override a promotion outcome.');
        }

        DB::transaction(function () use ($data, $actor): void {
            $decisions = collect($data['decisions']);
            $studentIds = $decisions->pluck('student_id')->map(fn ($id): int => (int) $id)->unique()->values();
            if ($studentIds->count() !== $decisions->count()) {
                throw ValidationException::withMessages([
                    'decisions' => 'A student may only appear once in a promotion batch.',
                ]);
            }

            $students = Student::query()
                ->whereKey($studentIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $sourceYearId = (int) $data['source_academic_year_id'];
            $targetYearId = (int) $data['target_academic_year_id'];
            $academicYears = AcademicYear::query()
                ->whereKey([$sourceYearId, $targetYearId])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $sourceYear = $academicYears->get($sourceYearId);
            $targetYear = $academicYears->get($targetYearId);

            if (! $sourceYear || ! $targetYear) {
                throw ValidationException::withMessages([
                    'source_academic_year_id' => 'Select valid source and target academic years.',
                ]);
            }

            if (
                $students->count() !== $studentIds->count()
                || $students->contains(fn (Student $student): bool => $student->status !== 'active')
            ) {
                throw ValidationException::withMessages([
                    'decisions' => 'One or more selected students are no longer eligible. Reload the list and try again.',
                ]);
            }

            if ($sourceYear->status !== AcademicYearStatus::Completed) {
                throw ValidationException::withMessages([
                    'source_academic_year_id' => 'Promotions can only be processed from a completed, unlocked academic year.',
                ]);
            }

            if (! in_array($targetYear->status, [AcademicYearStatus::Draft, AcademicYearStatus::Active], true)) {
                throw ValidationException::withMessages([
                    'target_academic_year_id' => 'The target academic year must be draft or active.',
                ]);
            }

            if ($targetYear->start_date->lessThanOrEqualTo($sourceYear->end_date)) {
                throw ValidationException::withMessages([
                    'target_academic_year_id' => 'Choose an academic year that starts after the source year ends.',
                ]);
            }

            $needsPlacement = $decisions->contains(fn (array $decision): bool => in_array(
                $decision['outcome'],
                ['promoted', 'repeated'],
                true,
            ));
            $targetClass = null;
            $targetSection = null;

            if ($needsPlacement) {
                $targetClass = SchoolClass::query()->lockForUpdate()->find($data['class_id']);
                if (! $targetClass || $targetClass->academic_year_id !== $targetYear->id) {
                    throw ValidationException::withMessages([
                        'class_id' => 'Select a class belonging to the target academic year.',
                    ]);
                }

                $targetSection = Section::query()->lockForUpdate()->find($data['section_id']);
                if (
                    ! $targetSection
                    || $targetSection->academic_year_id !== $targetYear->id
                    || $targetSection->class_id !== $targetClass->id
                ) {
                    throw ValidationException::withMessages([
                        'section_id' => 'Select a section belonging to the target class and academic year.',
                    ]);
                }
            }

            $sourceEnrolments = Enrolment::query()
                ->forAcademicYear($sourceYear)
                ->whereIn('student_id', $studentIds)
                ->where('status', 'active')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('student_id');
            $hasTargetEnrolment = Enrolment::query()
                ->forAcademicYear($targetYear)
                ->whereIn('student_id', $studentIds)
                ->exists();

            if (
                $hasTargetEnrolment
                || $sourceEnrolments->count() !== $studentIds->count()
            ) {
                throw ValidationException::withMessages([
                    'decisions' => 'One or more selected students are no longer eligible. Reload the list and try again.',
                ]);
            }

            foreach ($decisions as $decision) {
                $student = $students->get((int) $decision['student_id']);
                $sourceEnrolment = $sourceEnrolments->get((int) $decision['student_id']);
                $outcome = $decision['outcome'];

                if (
                    in_array($outcome, ['repeated', 'graduated'], true)
                    && blank($decision['reason'] ?? null)
                ) {
                    throw ValidationException::withMessages([
                        'decisions' => 'Every overridden outcome requires a reason.',
                    ]);
                }

                $sourceEnrolment->update([
                    'status' => 'completed',
                    'exit_date' => $sourceYear->end_date,
                    'outcome' => $outcome,
                ]);

                $targetEnrolment = $outcome === 'graduated'
                    ? null
                    : Enrolment::query()->create([
                        'academic_year_id' => $targetYear->id,
                        'student_id' => $student->id,
                        'class_id' => $targetClass->id,
                        'section_id' => $targetSection->id,
                        'enrolment_date' => $targetYear->start_date,
                        'status' => 'active',
                    ]);

                $promotion = Promotion::query()->create([
                    'student_id' => $student->id,
                    'source_enrolment_id' => $sourceEnrolment->id,
                    'target_enrolment_id' => $targetEnrolment?->id,
                    'source_academic_year_id' => $sourceYear->id,
                    'target_academic_year_id' => $targetYear->id,
                    'source_class_id' => $sourceEnrolment->class_id,
                    'source_section_id' => $sourceEnrolment->section_id,
                    'target_class_id' => $targetEnrolment ? $targetClass->id : null,
                    'target_section_id' => $targetEnrolment ? $targetSection->id : null,
                    'processed_by' => $actor->id,
                    'outcome' => $outcome,
                    'reason' => $decision['reason'] ?? null,
                ]);

                $this->activities->record(
                    $student,
                    $actor,
                    'student.promoted',
                    "Academic outcome recorded: {$outcome}.",
                    $sourceYear,
                    [
                        'promotion_id' => $promotion->id,
                        'outcome' => $outcome,
                        'target_academic_year_id' => $targetYear->id,
                    ],
                );
            }
        });
    }
}
