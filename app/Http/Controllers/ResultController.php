<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Student;
use App\Services\SchoolSettingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Published results for students and parents. */
class ResultController extends Controller
{
    public function index(Request $request, SchoolSettingsService $settings): Response
    {
        [$mode, $students, $selectedStudentId, $student] = $this->portalContext($request);

        $studentIds = $mode === 'parent'
            ? $students->when($selectedStudentId, fn ($collection) => $collection->where('id', $selectedStudentId))->modelKeys()
            : [$student?->id ?? 0];

        $academicYears = AcademicYear::query()
            ->whereHas('classes.enrolments', fn ($query) => $query->whereIn('student_id', $studentIds))
            ->orderByDesc('start_date')
            ->get(['id', 'name']);

        if ($request->filled('academic_year_id')) {
            abort_unless($academicYears->contains('id', $request->integer('academic_year_id')), 404);
        }

        $bands = $settings->gradeBands();

        return Inertia::render('Results/Index', [
            'mode' => $mode,
            'academicYears' => $academicYears->map(fn (AcademicYear $year): array => [
                'id' => $year->id,
                'name' => $year->name,
            ]),
            'selectedAcademicYearId' => $request->integer('academic_year_id') ?: null,
            'students' => $mode === 'parent' ? $students->map(fn (Student $child): array => [
                'id' => $child->id,
                'name' => $this->studentName($child),
            ])->values() : [],
            'selectedStudentId' => $selectedStudentId,
            'results' => $this->resultsQuery($student, $request->integer('academic_year_id') ?: null)
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Exam $exam): array => $this->resultPayload($exam, $bands, $settings)),
        ]);
    }

    public function show(Request $request, Exam $exam, SchoolSettingsService $settings): Response
    {
        [$mode, , , $student] = $this->portalContext($request);
        abort_unless($student, 404); // parents must choose a child: /results/{exam}?student_id=

        $published = $this->resultsQuery($student, null)->whereKey($exam->id)->firstOrFail();

        return Inertia::render('Results/Show', [
            'mode' => $mode,
            'studentId' => $mode === 'parent' ? $student->id : null,
            'studentName' => $this->studentName($student),
            'result' => $this->resultPayload($published, $settings->gradeBands(), $settings),
        ]);
    }

    /** @return array{0: string, 1: \Illuminate\Support\Collection, 2: ?int, 3: ?Student} */
    private function portalContext(Request $request): array
    {
        $user = $request->user();
        $isStudent = $user->hasRole('Student');
        $isParent = ! $isStudent && $user->hasRole('Parent');
        abort_unless(($isStudent || $isParent) && $user->can('portal.results.view'), 403);

        $students = $isStudent
            ? Student::query()->where('user_id', $user->id)->orderBy('id')->get()
            : Student::query()->forGuardianAccount($user)->orderBy('first_name')->orderBy('last_name')->get();

        $selectedStudentId = null;
        if ($isParent && $request->filled('student_id')) {
            $selectedStudentId = $request->integer('student_id');
            abort_unless($students->contains('id', $selectedStudentId), 404);
        }

        $student = $isStudent ? $students->first() : $students->firstWhere('id', $selectedStudentId);
        abort_if($isStudent && ! $student, 404);

        return [$isStudent ? 'student' : 'parent', $students, $selectedStudentId, $student];
    }

    private function studentName(Student $student): string
    {
        return trim(implode(' ', array_filter([$student->first_name, $student->middle_name, $student->last_name])));
    }

    private function resultsQuery(?Student $student, ?int $yearId): Builder
    {
        return Exam::query()
            ->when($student, fn ($query) => $query->whereHas('schoolClass.enrolments', fn ($enrolments) => $enrolments
                ->where('student_id', $student->id)
                ->whereColumn('enrolments.academic_year_id', 'exams.academic_year_id')), fn ($query) => $query->whereRaw('1 = 0'))
            ->where('status', 'published')
            ->when($yearId, fn ($query) => $query->where('academic_year_id', $yearId))
            ->with([
                'academicYear:id,name',
                'schoolClass:id,name',
                'subjects' => fn ($subjects) => $subjects->with([
                    'subject:id,name',
                    'marks' => fn ($marks) => $marks->where('student_id', $student?->id ?? 0),
                ])->orderBy('exam_date'),
            ])
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    private function resultPayload(Exam $exam, array $gradeBands, SchoolSettingsService $settings): array
    {
        $earned = 0.0;
        $possible = 0.0;
        $passed = true;

        $subjects = $exam->subjects->map(function (ExamSubject $subject) use (&$earned, &$possible, &$passed): array {
            $mark = $subject->marks->first();
            $score = $mark?->status === 'present' ? (float) $mark->marks_obtained : null;
            if ($mark?->status !== 'exempt') {
                $possible += (float) $subject->max_marks;
                $earned += $score ?? 0.0;
                if ($mark?->status !== 'present' || $score < (float) $subject->pass_marks) {
                    $passed = false;
                }
            }

            return [
                'name' => $subject->subject->name,
                'examDate' => $subject->exam_date->toDateString(),
                'marksObtained' => $score,
                'maxMarks' => (float) $subject->max_marks,
                'passMarks' => (float) $subject->pass_marks,
                'status' => $mark?->status ?? 'missing',
            ];
        })->values();

        $percentage = $possible > 0 ? round($earned / $possible * 100, 2) : null;

        return [
            'id' => $exam->id,
            'name' => $exam->name,
            'examType' => $exam->exam_type,
            'academicYear' => $exam->academicYear->name,
            'className' => $exam->schoolClass->name,
            'publishedAt' => $exam->published_at?->toDateString(),
            'subjects' => $subjects,
            'totalMarks' => $earned,
            'maximumMarks' => $possible,
            'percentage' => $percentage,
            'grade' => $settings->gradeForPercentage($percentage, $gradeBands),
            'result' => $possible <= 0 ? 'exempt' : ($passed ? 'pass' : 'fail'),
        ];
    }
}