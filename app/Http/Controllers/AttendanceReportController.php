<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\Staff;
use App\Services\AttendanceReportService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceReportController extends Controller
{
    public function index(Request $request, AttendanceReportService $service): Response
    {
        $context = $this->reportContext($request);
        $year = $context['academicYear'];
        $summary = $year
            ? $service->summary($year, $context['sectionId'], $context['visibleSectionIds'])
            : ['markedDays' => 0, 'present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];
        $students = $year
            ? $service->students($year, $context['sectionId'], $context['visibleSectionIds'])
                ->paginate(50)
                ->withQueryString()
                ->through(fn (object $student): array => [
                    'id' => (int) $student->id,
                    'admissionNumber' => $student->admission_number,
                    'name' => $this->studentName($student),
                    'className' => $student->class_name,
                    'sectionName' => $student->section_name,
                    'markedDays' => (int) $student->marked_days,
                    'present' => (int) $student->present,
                    'absent' => (int) $student->absent,
                    'late' => (int) $student->late,
                    'excused' => (int) $student->excused,
                ])
            : new LengthAwarePaginator([], 0, 50);

        return Inertia::render('Reports/Attendance', [
            'academicYears' => $context['academicYears']->map(fn (AcademicYear $academicYear): array => [
                'id' => $academicYear->id,
                'name' => $academicYear->name,
            ]),
            'selectedAcademicYearId' => $year?->id,
            'selectedSectionId' => $context['sectionId'],
            'sections' => $context['sections']->map(fn (Section $section): array => [
                'id' => $section->id,
                'name' => $section->schoolClass->name.' · '.$section->name,
            ]),
            'summary' => $summary,
            'students' => $students,
            'canExport' => $request->user()->can('reports.export'),
        ]);
    }

    public function export(Request $request, AttendanceReportService $service): StreamedResponse
    {
        abort_unless($request->user()->can('reports.export'), 403);

        $context = $this->reportContext($request);
        $year = $context['academicYear'];
        abort_if($year === null, 404);

        $students = $service->students($year, $context['sectionId'], $context['visibleSectionIds'])
            ->cursor();

        return response()->streamDownload(function () use ($students): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                throw new \RuntimeException('Unable to open the attendance report output stream.');
            }

            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, [
                'Admission number',
                'Student',
                'Class',
                'Section',
                'Marked days',
                'Present',
                'Absent',
                'Late',
                'Excused',
            ], ',', '"', '', "\n");

            foreach ($students as $student) {
                fputcsv($stream, [
                    $this->safeCsvCell($student->admission_number),
                    $this->safeCsvCell($this->studentName($student)),
                    $this->safeCsvCell($student->class_name),
                    $this->safeCsvCell($student->section_name),
                    (int) $student->marked_days,
                    (int) $student->present,
                    (int) $student->absent,
                    (int) $student->late,
                    (int) $student->excused,
                ], ',', '"', '', "\n");
            }

            fclose($stream);
        }, 'attendance-report-'.$year->start_date->year.'-'.$year->end_date->year.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{
     *     academicYears: Collection<int, AcademicYear>,
     *     academicYear: ?AcademicYear,
     *     sections: Collection<int, Section>,
     *     sectionId: ?int,
     *     visibleSectionIds: ?list<int>
     * }
     */
    private function reportContext(Request $request): array
    {
        $filters = $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
        ]);

        $academicYears = AcademicYear::query()->orderByDesc('start_date')->get();
        $academicYear = isset($filters['academic_year_id'])
            ? $academicYears->firstWhere('id', (int) $filters['academic_year_id'])
            : AcademicYear::active()->first();

        $user = $request->user();
        $isAssignedTeacher = $user->hasRole('Teacher') && ! $user->hasRole('Super Admin');
        $sections = $academicYear
            ? Section::query()
                ->with('schoolClass:id,name')
                ->where('academic_year_id', $academicYear->id)
                ->when($isAssignedTeacher, function ($query) use ($academicYear, $user): void {
                    $staffId = Staff::query()
                        ->where('user_id', $user->id)
                        ->where('status', 'active')
                        ->value('id');

                    $query->whereHas('teacherClassAssignments', fn ($assignments) => $assignments
                        ->forAcademicYear($academicYear)
                        ->where('staff_id', $staffId ?? 0));
                })
                ->orderBy('class_id')
                ->orderBy('name')
                ->get()
            : new Collection;

        $sectionId = isset($filters['section_id']) ? (int) $filters['section_id'] : null;
        if ($sectionId !== null && ! $sections->contains('id', $sectionId)) {
            abort(404);
        }

        return [
            'academicYears' => $academicYears,
            'academicYear' => $academicYear,
            'sections' => $sections,
            'sectionId' => $sectionId,
            'visibleSectionIds' => $isAssignedTeacher
                ? $sections->modelKeys()
                : null,
        ];
    }

    private function studentName(object $student): string
    {
        return trim(implode(' ', array_filter([
            $student->first_name,
            $student->middle_name,
            $student->last_name,
        ], fn (?string $name): bool => $name !== null && $name !== '')));
    }

    private function safeCsvCell(string $value): string
    {
        return preg_match('/^[\x00-\x20]*[=+\-@]/', $value) === 1
            ? "'".$value
            : $value;
    }
}
