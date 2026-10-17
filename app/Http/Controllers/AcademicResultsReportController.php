<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcademicResultsReportRequest;
use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Services\AcademicResultsReportService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AcademicResultsReportController extends Controller
{
    public function index(
        AcademicResultsReportRequest $request,
        AcademicResultsReportService $service,
    ): Response {
        $context = $this->reportContext($request);
        $exam = $context['exam'];
        $summary = $exam
            ? $service->summary($exam)
            : [
                'candidateCount' => 0,
                'assessedCandidateCount' => 0,
                'averagePercentage' => 0.0,
                'highestPercentage' => 0.0,
                'passedAllSubjects' => 0,
            ];
        $students = $exam
            ? $service->students($exam)
                ->paginate(50)
                ->withQueryString()
                ->through(fn (object $student): array => $this->studentData($student))
            : new LengthAwarePaginator([], 0, 50);

        return Inertia::render('Reports/Academic', [
            'academicYears' => $context['academicYears']->map(fn (AcademicYear $year): array => [
                'id' => $year->id,
                'name' => $year->name,
            ]),
            'selectedAcademicYearId' => $context['academicYear']?->id,
            'selectedClassId' => $context['classId'],
            'selectedTermId' => $context['termId'],
            'selectedExamId' => $exam?->id,
            'classes' => $context['classes']->map(fn (SchoolClass $schoolClass): array => [
                'id' => $schoolClass->id,
                'name' => $schoolClass->name,
            ]),
            'terms' => $context['terms']->map(fn (Term $term): array => [
                'id' => $term->id,
                'name' => $term->name,
            ]),
            'exams' => $context['exams']->map(fn (Exam $availableExam): array => [
                'id' => $availableExam->id,
                'name' => $availableExam->name,
                'className' => $availableExam->schoolClass->name,
                'termName' => $availableExam->term?->name,
            ]),
            'selectedExam' => $exam ? [
                'name' => $exam->name,
                'className' => $exam->schoolClass->name,
                'termName' => $exam->term?->name,
            ] : null,
            'summary' => $summary,
            'students' => $students,
            'canExport' => $request->user()->can('reports.export'),
        ]);
    }

    public function export(
        AcademicResultsReportRequest $request,
        AcademicResultsReportService $service,
    ): StreamedResponse {
        abort_unless($request->user()->can('reports.export'), 403);

        $context = $this->reportContext($request);
        $exam = $context['exam'];
        $academicYear = $context['academicYear'];
        abort_if($exam === null || $academicYear === null, 404);

        $students = $service->students($exam)->cursor();
        $className = $exam->schoolClass->name;
        $examName = $exam->name;
        $filename = 'academic-results-report-'.$academicYear->start_date->year.'-'.$academicYear->end_date->year.'.csv';

        return response()->streamDownload(function () use ($students, $className, $examName): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                throw new \RuntimeException('Unable to open the academic results report output stream.');
            }

            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, [
                'Admission number',
                'Student',
                'Class',
                'Examination',
                'Total marks',
                'Maximum marks',
                'Percentage',
                'Subjects passed',
                'Subjects assessed',
            ], ',', '"', '', "\n");

            foreach ($students as $student) {
                $data = $this->studentData($student);
                fputcsv($stream, [
                    $this->safeCsvCell($data['admissionNumber']),
                    $this->safeCsvCell($data['name']),
                    $this->safeCsvCell($className),
                    $this->safeCsvCell($examName),
                    number_format($data['marksObtained'], 2, '.', ''),
                    number_format($data['maximumMarks'], 2, '.', ''),
                    $data['percentage'] === null ? '' : number_format($data['percentage'], 2, '.', ''),
                    $data['passedSubjects'],
                    $data['assessedSubjects'],
                ], ',', '"', '', "\n");
            }

            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{
     *     academicYears: Collection<int, AcademicYear>,
     *     academicYear: ?AcademicYear,
     *     classes: Collection<int, SchoolClass>,
     *     terms: Collection<int, Term>,
     *     exams: Collection<int, Exam>,
     *     classId: ?int,
     *     termId: ?int,
     *     exam: ?Exam
     * }
     */
    private function reportContext(AcademicResultsReportRequest $request): array
    {
        $filters = $request->validated();
        $academicYears = AcademicYear::query()->orderByDesc('start_date')->get();
        $academicYear = isset($filters['academic_year_id'])
            ? $academicYears->firstWhere('id', (int) $filters['academic_year_id'])
            : AcademicYear::active()->first();
        $classes = $academicYear
            ? $academicYear->classes()->orderBy('sort_order')->orderBy('name')->get()
            : new Collection;
        $terms = $academicYear
            ? $academicYear->terms()->orderBy('start_date')->orderBy('id')->get()
            : new Collection;
        $classId = isset($filters['class_id']) ? (int) $filters['class_id'] : null;
        $termId = isset($filters['term_id']) ? (int) $filters['term_id'] : null;

        if ($classId !== null && ! $classes->contains('id', $classId)) {
            abort(404);
        }
        if ($termId !== null && ! $terms->contains('id', $termId)) {
            abort(404);
        }

        $exams = $academicYear
            ? Exam::query()
                ->with(['schoolClass:id,name', 'term:id,name'])
                ->where('academic_year_id', $academicYear->id)
                ->where('status', 'published')
                ->when($classId !== null, fn ($query) => $query->where('class_id', $classId))
                ->when($termId !== null, fn ($query) => $query->where('term_id', $termId))
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->get()
            : new Collection;
        $exam = isset($filters['exam_id'])
            ? $exams->firstWhere('id', (int) $filters['exam_id'])
            : $exams->first();

        if (isset($filters['exam_id']) && ! $exam) {
            abort(404);
        }

        return [
            'academicYears' => $academicYears,
            'academicYear' => $academicYear,
            'classes' => $classes,
            'terms' => $terms,
            'exams' => $exams,
            'classId' => $classId,
            'termId' => $termId,
            'exam' => $exam,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     admissionNumber: string,
     *     name: string,
     *     marksObtained: float,
     *     maximumMarks: float,
     *     percentage: ?float,
     *     passedSubjects: int,
     *     assessedSubjects: int
     * }
     */
    private function studentData(object $student): array
    {
        return [
            'id' => (int) $student->id,
            'admissionNumber' => $student->admission_number,
            'name' => trim(implode(' ', array_filter([
                $student->first_name,
                $student->middle_name,
                $student->last_name,
            ], fn (?string $name): bool => $name !== null && $name !== ''))),
            'marksObtained' => (float) $student->marks_obtained,
            'maximumMarks' => (float) $student->maximum_marks,
            'percentage' => $student->percentage === null ? null : (float) $student->percentage,
            'passedSubjects' => (int) $student->passed_subjects,
            'assessedSubjects' => (int) $student->assessed_subjects,
        ];
    }

    private function safeCsvCell(string $value): string
    {
        return preg_match('/^[\x00-\x20]*[=+\-@]/', $value) === 1
            ? "'".$value
            : $value;
    }
}
