<?php

namespace App\Services;

use App\Models\AcademicYear;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class AttendanceReportService
{
    /**
     * @param  list<int>|null  $visibleSectionIds  Null grants school-wide visibility.
     * @return array{markedDays: int, present: int, absent: int, late: int, excused: int}
     */
    public function summary(AcademicYear $year, ?int $sectionId, ?array $visibleSectionIds): array
    {
        $summary = $this->attendanceRecords($year, $sectionId, $visibleSectionIds)
            ->selectRaw(
                'COUNT(attendance_records.id) AS marked_days,
                SUM(CASE WHEN attendance_records.status = ? THEN 1 ELSE 0 END) AS present,
                SUM(CASE WHEN attendance_records.status = ? THEN 1 ELSE 0 END) AS absent,
                SUM(CASE WHEN attendance_records.status = ? THEN 1 ELSE 0 END) AS late,
                SUM(CASE WHEN attendance_records.status = ? THEN 1 ELSE 0 END) AS excused',
                ['present', 'absent', 'late', 'excused'],
            )
            ->first();

        return [
            'markedDays' => (int) ($summary->marked_days ?? 0),
            'present' => (int) ($summary->present ?? 0),
            'absent' => (int) ($summary->absent ?? 0),
            'late' => (int) ($summary->late ?? 0),
            'excused' => (int) ($summary->excused ?? 0),
        ];
    }

    /**
     * @param  list<int>|null  $visibleSectionIds  Null grants school-wide visibility.
     */
    public function students(AcademicYear $year, ?int $sectionId, ?array $visibleSectionIds): Builder
    {
        return $this->attendanceRecords($year, $sectionId, $visibleSectionIds)
            ->select([
                'students.id',
                'students.admission_number',
                'students.first_name',
                'students.middle_name',
                'students.last_name',
                'classes.name AS class_name',
                'sections.name AS section_name',
            ])
            ->selectRaw(
                'COUNT(attendance_records.id) AS marked_days,
                SUM(CASE WHEN attendance_records.status = ? THEN 1 ELSE 0 END) AS present,
                SUM(CASE WHEN attendance_records.status = ? THEN 1 ELSE 0 END) AS absent,
                SUM(CASE WHEN attendance_records.status = ? THEN 1 ELSE 0 END) AS late,
                SUM(CASE WHEN attendance_records.status = ? THEN 1 ELSE 0 END) AS excused',
                ['present', 'absent', 'late', 'excused'],
            )
            ->groupBy(
                'students.id',
                'students.admission_number',
                'students.first_name',
                'students.middle_name',
                'students.last_name',
                'classes.name',
                'sections.name',
            )
            ->orderBy('classes.name')
            ->orderBy('sections.name')
            ->orderBy('students.last_name')
            ->orderBy('students.first_name')
            ->orderBy('students.id');
    }

    /**
     * @param  list<int>|null  $visibleSectionIds  Null grants school-wide visibility.
     */
    private function attendanceRecords(
        AcademicYear $year,
        ?int $sectionId,
        ?array $visibleSectionIds,
    ): Builder {
        return DB::table('attendance_records')
            ->join('attendance', 'attendance.id', '=', 'attendance_records.attendance_id')
            ->join('enrolments', function ($join): void {
                $join->on('enrolments.id', '=', 'attendance_records.enrolment_id')
                    ->on('enrolments.student_id', '=', 'attendance_records.student_id')
                    ->on('enrolments.section_id', '=', 'attendance.section_id')
                    ->on('enrolments.academic_year_id', '=', 'attendance.academic_year_id');
            })
            ->join('students', 'students.id', '=', 'attendance_records.student_id')
            ->join('sections', 'sections.id', '=', 'attendance.section_id')
            ->join('classes', 'classes.id', '=', 'sections.class_id')
            ->where('attendance.academic_year_id', $year->id)
            ->when($sectionId !== null, fn (Builder $query): Builder => $query
                ->where('attendance.section_id', $sectionId))
            ->when($visibleSectionIds !== null, fn (Builder $query): Builder => $query
                ->whereIn('attendance.section_id', $visibleSectionIds));
    }
}
