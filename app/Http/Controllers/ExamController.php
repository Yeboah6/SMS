<?php

namespace App\Http\Controllers;

use App\Enums\AcademicYearStatus;
use App\Concerns\ManagesExamAccess;
use App\Http\Requests\StoreExamRequest;
use App\Http\Requests\UpdateExamRequest;
use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\SchoolClass;
use App\Services\ExamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExamController extends Controller
{
    use ManagesExamAccess;

    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // Students and parents now use the dedicated results pages.
        if ($user->hasRole('Student') || $user->hasRole('Parent')) {
            abort_unless($user->can('portal.results.view'), 403);

            return redirect()->route('results.index', $request->query());
        }

        [$canManage, $isTeacher, $staff] = $this->examAccess($request);

        $years = AcademicYear::query()->orderByDesc('start_date')->get();
        $year = $request->filled('academic_year_id')
            ? $years->firstWhere('id', $request->integer('academic_year_id'))
            : AcademicYear::active()->first();
        abort_if($request->filled('academic_year_id') && ! $year, 404);

        $exams = Exam::query()
            ->when($year, fn ($query) => $query->where('academic_year_id', $year->id), fn ($query) => $query->whereRaw('1 = 0'))
            ->when($isTeacher && $year, fn ($query) => $this->restrictToTeacherExams($query, $staff, $year->id))
            ->with(['schoolClass:id,name', 'term:id,name', 'academicYear:id,name'])
            ->withCount('subjects')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Exams/Index', [
            'exams' => $exams->map(fn (Exam $exam): array => [
                'id' => $exam->id,
                'name' => $exam->name,
                'examType' => $exam->exam_type,
                'status' => $exam->status,
                'startDate' => $exam->start_date->toDateString(),
                'endDate' => $exam->end_date->toDateString(),
                'className' => $exam->schoolClass->name,
                'termName' => $exam->term?->name,
                'subjectCount' => $exam->subjects_count,
            ])->values(),
            'academicYears' => $years->map(fn (AcademicYear $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'status' => $item->status->value,
            ])->values(),
            'selectedAcademicYearId' => $year?->id,
            'canCreate' => $canManage && $year && $year->status !== AcademicYearStatus::Locked,
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->can('exams.create'), 403);

        $years = AcademicYear::query()
            ->where('status', '!=', AcademicYearStatus::Locked->value)
            ->orderByDesc('start_date')
            ->get();
        $requested = $request->integer('academic_year_id') ?: AcademicYear::active()->value('id');
        $year = $years->firstWhere('id', $requested) ?? $years->first();
        abort_unless($year, 404);

        return Inertia::render('Exams/Create', [
            'academicYears' => $years->map(fn (AcademicYear $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'status' => $item->status->value,
            ])->values(),
            'selectedAcademicYearId' => $year->id,
            'terms' => $year->terms()->orderBy('start_date')->get(['id', 'name'])
                ->map(fn ($term): array => ['id' => $term->id, 'name' => $term->name])->values(),
            'classes' => $year->classes()
                ->with(['classSubjects.subject:id,name'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (SchoolClass $schoolClass): array => [
                    'id' => $schoolClass->id,
                    'name' => $schoolClass->name,
                    'subjects' => $schoolClass->classSubjects->map(fn ($classSubject): array => [
                        'id' => $classSubject->subject_id,
                        'name' => $classSubject->subject->name,
                    ])->values(),
                ])->values(),
        ]);
    }

    public function store(StoreExamRequest $request, ExamService $service): RedirectResponse
    {
        $exam = $service->create($request->validated(), $request->user());

        return to_route('exams.show', $exam)->with('status', 'Examination created.');
    }

    public function show(Request $request, Exam $exam): Response
    {
        $user = $request->user();
        $this->authorizeExam($request, $exam);

        $exam->load([
            'schoolClass:id,name',
            'term:id,name',
            'academicYear:id,name,status',
            'subjects' => fn ($query) => $query->with('subject:id,name')->withCount('marks')->orderBy('exam_date')->orderBy('id'),
        ]);

        $unlocked = $exam->academicYear->status !== AcademicYearStatus::Locked;
        $draft = $exam->status === 'draft';
        $hasMarks = $exam->subjects->sum('marks_count') > 0;

        return Inertia::render('Exams/Show', [
            'exam' => [
                'id' => $exam->id,
                'name' => $exam->name,
                'examType' => $exam->exam_type,
                'status' => $exam->status,
                'startDate' => $exam->start_date->toDateString(),
                'endDate' => $exam->end_date->toDateString(),
                'className' => $exam->schoolClass->name,
                'termName' => $exam->term?->name,
                'academicYearId' => $exam->academic_year_id,
                'academicYear' => $exam->academicYear->name,
                'publishedAt' => $exam->published_at?->toDateString(),
            ],
            'subjects' => $exam->subjects->map(fn (ExamSubject $item): array => [
                'id' => $item->id,
                'name' => $item->subject->name,
                'examDate' => $item->exam_date->toDateString(),
                'maxMarks' => (float) $item->max_marks,
                'passMarks' => (float) $item->pass_marks,
                'marksCount' => $item->marks_count,
            ])->values(),
            'can' => [
                'update' => $user->can('exams.update') && $draft && $unlocked,
                'delete' => $user->can('exams.delete') && $draft && $unlocked && ! $hasMarks,
                'publish' => $user->can('marks.publish') && $draft && $unlocked,
                'reopen' => $user->can('marks.reopen') && $exam->status === 'published' && $unlocked,
                'viewMarks' => $user->can('marks.view') || $user->can('marks.enter') || $user->can('marks.update'),
            ],
        ]);
    }

    public function edit(Request $request, Exam $exam): Response
    {
        abort_unless($request->user()->can('exams.update'), 403);

        $exam->load([
            'schoolClass:id,name',
            'academicYear:id,name,status',
            'subjects' => fn ($query) => $query->withCount('marks')->orderBy('exam_date')->orderBy('id'),
        ]);
        abort_if($exam->academicYear->status === AcademicYearStatus::Locked, 403);
        abort_unless($exam->status === 'draft', 403);

        return Inertia::render('Exams/Edit', [
            'exam' => [
                'id' => $exam->id,
                'name' => $exam->name,
                'examType' => $exam->exam_type,
                'termId' => $exam->term_id,
                'startDate' => $exam->start_date->toDateString(),
                'endDate' => $exam->end_date->toDateString(),
                'className' => $exam->schoolClass->name,
                'academicYear' => $exam->academicYear->name,
            ],
            'terms' => $exam->academicYear->terms()->orderBy('start_date')->get(['id', 'name'])
                ->map(fn ($term): array => ['id' => $term->id, 'name' => $term->name])->values(),
            'classSubjects' => $exam->schoolClass->classSubjects()->with('subject:id,name')->get()
                ->map(fn ($classSubject): array => [
                    'id' => $classSubject->subject_id,
                    'name' => $classSubject->subject->name,
                ])->values(),
            'selected' => $exam->subjects->map(fn (ExamSubject $item): array => [
                'subjectId' => $item->subject_id,
                'examDate' => $item->exam_date->toDateString(),
                'maxMarks' => (float) $item->max_marks,
                'passMarks' => (float) $item->pass_marks,
                'hasMarks' => $item->marks_count > 0,
            ])->values(),
        ]);
    }

    public function update(UpdateExamRequest $request, Exam $exam, ExamService $service): RedirectResponse
    {
        $service->update($exam, $request->validated(), $request->user());

        return to_route('exams.show', $exam)->with('status', 'Examination updated.');
    }

    public function destroy(Request $request, Exam $exam, ExamService $service): RedirectResponse
    {
        abort_unless($request->user()->can('exams.delete'), 403);

        $yearId = $exam->academic_year_id;
        $service->delete($exam, $request->user());

        return to_route('exams.index', ['academic_year_id' => $yearId])->with('status', 'Examination deleted.');
    }

    public function publish(Request $request, Exam $exam, ExamService $service): RedirectResponse
    {
        $service->publish($exam, $request->user());

        return back()->with('status', 'Examination results published.');
    }

    public function reopen(Request $request, Exam $exam, ExamService $service): RedirectResponse
    {
        $service->reopen($exam, $request->user());

        return back()->with('status', 'Examination reopened for correction.');
    }
}