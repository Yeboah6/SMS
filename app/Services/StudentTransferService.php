<?php

namespace App\Services;

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\Enrolment;
use App\Models\Student;
use App\Models\StudentTransfer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentTransferService
{
    public function __construct(private StudentActivityService $activities) {}

    /**
     * @param  array{enrolment_id: int, transfer_date: string, destination_school: string, reason: string}  $data
     */
    public function transfer(Student $student, array $data, User $actor): StudentTransfer
    {
        if (! $actor->can('students.transfer')) {
            throw new AuthorizationException('You are not allowed to transfer students.');
        }

        return DB::transaction(function () use ($student, $data, $actor): StudentTransfer {
            $student = Student::query()->lockForUpdate()->findOrFail($student->id);

            if ($student->status !== 'active') {
                throw ValidationException::withMessages([
                    'student' => 'Only active students can be transferred.',
                ]);
            }

            $enrolment = Enrolment::query()
                ->whereKey($data['enrolment_id'])
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->first();

            if (! $enrolment || $enrolment->status !== 'active') {
                throw ValidationException::withMessages([
                    'enrolment_id' => "Select this student's active academic-year enrolment.",
                ]);
            }

            $academicYear = AcademicYear::query()
                ->lockForUpdate()
                ->findOrFail($enrolment->academic_year_id);

            if ($academicYear->status !== AcademicYearStatus::Active) {
                throw ValidationException::withMessages([
                    'enrolment_id' => 'A transfer can only be recorded for an active academic year.',
                ]);
            }

            $transferDate = Carbon::parse($data['transfer_date'])->toDateString();
            if (
                $transferDate < $enrolment->enrolment_date->toDateString()
                || $transferDate > $academicYear->end_date->toDateString()
            ) {
                throw ValidationException::withMessages([
                    'transfer_date' => 'The transfer date must fall within this enrolment and academic year.',
                ]);
            }

            $transfer = StudentTransfer::query()->create([
                'student_id' => $student->id,
                'enrolment_id' => $enrolment->id,
                'academic_year_id' => $academicYear->id,
                'processed_by' => $actor->id,
                'transfer_date' => $transferDate,
                'destination_school' => $data['destination_school'],
                'reason' => $data['reason'],
            ]);

            $enrolment->update([
                'exit_date' => $transferDate,
                'status' => 'transferred',
                'outcome' => 'transferred',
            ]);
            $student->update(['status' => 'transferred']);
            $this->activities->record(
                $student,
                $actor,
                'student.transferred',
                "Transferred to {$transfer->destination_school}.",
                $academicYear,
                [
                    'transfer_id' => $transfer->id,
                    'transfer_date' => $transferDate,
                    'destination_school' => $transfer->destination_school,
                ],
            );

            return $transfer;
        });
    }
}
