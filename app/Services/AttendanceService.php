<?php

namespace App\Services;

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\AttendanceRecord;
use App\Models\Enrolment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(private StudentActivityService $activities) {}

    /**
     * @return Collection<int, Enrolment>
     */
    public function roster(AcademicYear $year, Section $section, Carbon $date): Collection
    {
        return Enrolment::query()
            ->forAcademicYear($year)
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
     * @param  array{academic_year_id: int, section_id: int, attendance_date: string, records: list<array{student_id: int, status: string, remarks?: ?string}>}  $data
     */
    public function record(array $data, User $actor): Attendance
    {
        return DB::transaction(function () use ($data, $actor): Attendance {
            $year = AcademicYear::query()->lockForUpdate()->findOrFail($data['academic_year_id']);
            $section = Section::query()->lockForUpdate()->findOrFail($data['section_id']);

            if ($section->academic_year_id !== $year->id) {
                throw ValidationException::withMessages([
                    'section_id' => 'Select a section in the selected academic year.',
                ]);
            }

            if ($year->status === AcademicYearStatus::Locked) {
                throw ValidationException::withMessages([
                    'academic_year_id' => 'Attendance cannot be changed in a locked academic year.',
                ]);
            }

            $date = Carbon::createFromFormat('Y-m-d', $data['attendance_date'])->startOfDay();
            if ($date->lt($year->start_date) || $date->gt($year->end_date)) {
                throw ValidationException::withMessages([
                    'attendance_date' => 'The attendance date must be within the selected academic year.',
                ]);
            }

            $attendance = Attendance::query()
                ->where('academic_year_id', $year->id)
                ->where('section_id', $section->id)
                ->whereDate('attendance_date', $date)
                ->lockForUpdate()
                ->first();

            if ($attendance && ! $actor->can('attendance.update')) {
                throw new AuthorizationException('You are not allowed to update recorded attendance.');
            }
            if (! $attendance && ! $actor->can('attendance.mark')) {
                throw new AuthorizationException('You are not allowed to record attendance.');
            }

            $studentIds = collect($data['records'])
                ->pluck('student_id')
                ->map(fn ($studentId): int => (int) $studentId);
            if ($studentIds->unique()->count() !== $studentIds->count()) {
                throw ValidationException::withMessages([
                    'records' => 'Each student may only be included once.',
                ]);
            }

            $roster = Enrolment::query()
                ->forAcademicYear($year)
                ->where('section_id', $section->id)
                ->whereIn('status', ['active', 'completed'])
                ->whereDate('enrolment_date', '<=', $date)
                ->where(fn ($query) => $query
                    ->whereNull('exit_date')
                    ->orWhereDate('exit_date', '>=', $date))
                ->orderBy('student_id')
                ->lockForUpdate()
                ->with('student:id')
                ->get();
            $enrolments = $roster->keyBy('student_id');
            $rosterIds = $roster->pluck('student_id')->map(fn ($studentId): int => (int) $studentId);

            if (
                $enrolments->count() !== $studentIds->count()
                || $studentIds->map(fn ($id): int => (int) $id)->sort()->values()->all()
                    !== $rosterIds->sort()->values()->all()
            ) {
                throw ValidationException::withMessages([
                    'records' => 'Attendance must include each student in the section roster exactly once.',
                ]);
            }

            if (! $attendance) {
                $attendance = Attendance::query()->create([
                    'academic_year_id' => $year->id,
                    'section_id' => $section->id,
                    'attendance_date' => $date,
                    'marked_by' => $actor->id,
                ]);
            } else {
                $attendance->update(['updated_by' => $actor->id]);
            }

            foreach ($data['records'] as $record) {
                $attendanceRecord = AttendanceRecord::query()->firstOrNew([
                    'attendance_id' => $attendance->id,
                    'student_id' => $record['student_id'],
                ]);
                $previousStatus = $attendanceRecord->status;
                $eventType = $attendanceRecord->exists ? 'attendance.updated' : 'attendance.recorded';
                $attendanceRecord->fill([
                    'enrolment_id' => $enrolments->get($record['student_id'])->id,
                    'status' => $record['status'],
                    'remarks' => $record['remarks'] ?? null,
                ]);
                $attendanceRecord->save();

                $this->activities->record(
                    $enrolments->get($record['student_id'])->student,
                    $actor,
                    $eventType,
                    'Attendance '.$record['status'].' on '.$date->toDateString().'.',
                    $year,
                    [
                        'attendance_id' => $attendance->id,
                        'attendance_record_id' => $attendanceRecord->id,
                        'section_id' => $section->id,
                        'attendance_date' => $date->toDateString(),
                        'previous_status' => $previousStatus,
                        'status' => $record['status'],
                    ],
                );
            }

            return $attendance->refresh();
        });
    }
}
