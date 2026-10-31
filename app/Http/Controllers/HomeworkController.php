<?php

namespace App\Http\Controllers;

use App\Enums\AcademicYearStatus;
use App\Http\Requests\GradeHomeworkSubmissionRequest;
use App\Http\Requests\StoreHomeworkAssignmentRequest;
use App\Http\Requests\StoreHomeworkSubmissionRequest;
use App\Http\Requests\UpdateHomeworkAssignmentRequest;
use App\Models\AcademicYear;
use App\Models\Enrolment;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkSubmission;
use App\Models\Staff;
use App\Models\Student;
use App\Models\TeacherSubjectAssignment;
use App\Services\HomeworkService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HomeworkController extends Controller
{
    private const VIEWS = ['open', 'closed', 'grading'];

    public function index(Request $request): Response
    {
        $c = $this->context($request);
        $search = $request->string('search')->trim()->toString();
        $view = in_array($request->string('view')->toString(), self::VIEWS, true) ? $request->string('view')->toString() : '';
        if ($view === 'grading' && ! $c['isStaff']) {
            $view = '';
        }
        $today = today()->toDateString();

        $query = $this->scopedQuery($c)
            ->with(['section:id,name,class_id', 'section.schoolClass:id,name', 'subject:id,name', 'assigner:id,name', 'submissions' => fn ($submissions) => $this->limitSubmissions($submissions, $c)])
            ->withCount([
                'submissions' => fn ($submissions) => $this->limitSubmissions($submissions, $c),
                'submissions as graded_count' => fn ($submissions) => $this->limitSubmissions($submissions, $c)->whereNotNull('graded_at'),
            ])
            ->when($search !== '', fn ($inner) => $inner->where('title', 'like', "%{$search}%"))
            ->when($view === 'open', fn ($inner) => $inner->whereDate('assigned_date', '<=', $today)->whereDate('due_date', '>=', $today))
            ->when($view === 'closed', fn ($inner) => $inner->whereDate('due_date', '<', $today))
            ->when($view === 'grading', fn ($inner) => $inner->whereHas('submissions', fn ($submissions) => $submissions->whereNull('graded_at')))
            ->when($view === 'open', fn ($inner) => $inner->orderBy('due_date'), fn ($inner) => $inner->orderByDesc('assigned_date'))
            ->orderByDesc('id');

        $paginator = $query->paginate(15)->withQueryString();
        $rosters = $c['isStaff'] ? $this->rosterSizes($c['year'], $paginator->getCollection()->pluck('section_id')->unique()) : [];

        $paginator->through(fn (HomeworkAssignment $assignment): array => $this->listRow($assignment, $c, $rosters));

        return Inertia::render('Homework/Index', [
            'academicYear' => $c['year']?->only(['id', 'name']),
            'assignments' => $paginator,
            'filters' => ['search' => $search, 'view' => $view],
            'role' => $this->role($c),
            'children' => $c['isParent'] ? $c['children']->map(fn (Student $child): array => ['id' => $child->id, 'name' => $this->studentName($child)])->values() : [],
            'selectedStudentId' => $c['selectedStudentId'],
            'canCreate' => $this->canCreate($c),
        ]);
    }

    public function create(Request $request): Response
    {
        $c = $this->context($request);
        abort_unless($this->canCreate($c), 403);

        return Inertia::render('Homework/Create', [
            'academicYear' => $c['year']?->only(['id', 'name']),
            'subjectAssignments' => $this->subjectOptions($c),
            'today' => today()->toDateString(),
        ]);
    }

    public function store(StoreHomeworkAssignmentRequest $request, HomeworkService $service): RedirectResponse
    {
        $service->create($request->validated(), $request->user());

        return to_route('homework.index')->with('status', 'Homework assigned.');
    }

    public function show(Request $request, HomeworkAssignment $homeworkAssignment): Response
    {
        $c = $this->context($request);
        $assignment = $this->scopedQuery($c)
            ->with(['section:id,name,class_id', 'section.schoolClass:id,name', 'subject:id,name', 'assigner:id,name', 'submissions' => fn ($submissions) => $this->limitSubmissions($submissions, $c)->with(['student:id,first_name,middle_name,last_name,admission_number', 'grader:id,name'])->orderBy('submitted_at')])
            ->whereKey($homeworkAssignment->id)
            ->firstOrFail();

        $user = $request->user();
        $mine = $c['isStudent'] ? $assignment->submissions->first() : null;
        $roster = $c['isStaff'] ? $this->roster($c['year'], $assignment) : collect();
        $submittedIds = $assignment->submissions->pluck('student_id');

        return Inertia::render('Homework/Show', [
            'role' => $this->role($c),
            'assignment' => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'instructions' => $assignment->instructions,
                'assignedDate' => $assignment->assigned_date->toDateString(),
                'dueDate' => $assignment->due_date->toDateString(),
                'maxPoints' => (float) $assignment->max_points,
                'section' => $assignment->section->schoolClass->name.' · '.$assignment->section->name,
                'subject' => $assignment->subject->name,
                'assignedBy' => $assignment->assigner->name,
                'window' => $this->window($assignment),
            ],
            'stats' => $c['isStaff'] ? [
                'roster' => $roster->count(),
                'submitted' => $assignment->submissions->count(),
                'graded' => $assignment->submissions->whereNotNull('graded_at')->count(),
            ] : null,
            'submissions' => $c['isStaff'] ? $assignment->submissions->map(fn (HomeworkSubmission $submission): array => $this->submissionRow($submission, true))->values() : [],
            'missing' => $c['isStaff'] ? $roster->reject(fn (Enrolment $enrolment): bool => $submittedIds->contains($enrolment->student_id))
                ->map(fn (Enrolment $enrolment): array => ['id' => $enrolment->student_id, 'name' => $this->studentName($enrolment->student), 'admissionNumber' => $enrolment->student->admission_number])->values() : [],
            'mySubmission' => $mine ? $this->submissionRow($mine, false) : null,
            'childSubmissions' => $c['isParent'] ? $assignment->submissions->map(fn (HomeworkSubmission $submission): array => $this->submissionRow($submission, true))->values() : [],
            'can' => [
                'submit' => $this->canSubmit($user, $c, $assignment, $mine),
                'update' => $this->canManage($user, $c, 'homework.update'),
                'grade' => $c['isTeacher'] && $user->can('homework.grade'),
                'delete' => $this->canManage($user, $c, 'homework.delete') && $assignment->submissions->isEmpty(),
            ],
        ]);
    }

    public function edit(Request $request, HomeworkAssignment $homeworkAssignment): Response
    {
        $c = $this->context($request);
        abort_unless($this->canManage($request->user(), $c, 'homework.update'), 403);

        $assignment = $this->scopedQuery($c)->with(['section:id,name,class_id', 'section.schoolClass:id,name', 'subject:id,name'])->whereKey($homeworkAssignment->id)->firstOrFail();

        return Inertia::render('Homework/Edit', [
            'assignment' => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'instructions' => $assignment->instructions,
                'assignedDate' => $assignment->assigned_date->toDateString(),
                'dueDate' => $assignment->due_date->toDateString(),
                'maxPoints' => (float) $assignment->max_points,
                'section' => $assignment->section->schoolClass->name.' · '.$assignment->section->name,
                'subject' => $assignment->subject->name,
            ],
        ]);
    }

    public function update(UpdateHomeworkAssignmentRequest $request, HomeworkAssignment $homeworkAssignment, HomeworkService $service): RedirectResponse
    {
        $service->update($homeworkAssignment, $request->validated(), $request->user());

        return to_route('homework.show', $homeworkAssignment)->with('status', 'Homework updated.');
    }

    public function destroy(Request $request, HomeworkAssignment $homeworkAssignment, HomeworkService $service): RedirectResponse
    {
        $c = $this->context($request);
        abort_unless($this->canManage($request->user(), $c, 'homework.delete'), 403);
        $this->scopedQuery($c)->whereKey($homeworkAssignment->id)->firstOrFail();

        $service->delete($homeworkAssignment, $request->user());

        return to_route('homework.index')->with('status', 'Homework deleted.');
    }

    public function submit(
        StoreHomeworkSubmissionRequest $request,
        HomeworkAssignment $homeworkAssignment,
        HomeworkService $service,
    ): RedirectResponse {
        $service->submit($homeworkAssignment, $request->validated(), $request->user());

        return back()->with('status', 'Homework submitted.');
    }

    public function grade(
        GradeHomeworkSubmissionRequest $request,
        HomeworkAssignment $homeworkAssignment,
        HomeworkSubmission $homeworkSubmission,
        HomeworkService $service,
    ): RedirectResponse {
        $service->grade($homeworkAssignment, $homeworkSubmission, $request->validated(), $request->user());

        return back()->with('status', 'Homework graded.');
    }

    // ------------------------------------------------------------------ helpers

    /** Who is looking, and which assignments they may see: the same rules as before, shared by every page. */
    private function context(Request $request): array
    {
        $user = $request->user();
        $isStaff = $user->can('homework.view');
        $isStudent = $user->hasRole('Student') && $user->can('portal.homework.view');
        $isParent = ! $user->hasRole('Student') && $user->hasRole('Parent') && $user->can('portal.homework.view');
        abort_unless($isStaff || $isStudent || $isParent, 403);

        $year = AcademicYear::active()->first();
        $isTeacher = $user->hasRole('Teacher') && ! $user->hasRole('Super Admin');
        $staff = $isTeacher ? Staff::query()->where('user_id', $user->id)->where('status', 'active')->where('is_teacher', true)->first() : null;
        $student = $isStudent ? Student::query()->where('user_id', $user->id)->where('status', 'active')->first() : null;
        $enrolment = $student && $year ? Enrolment::query()->forAcademicYear($year)->where('student_id', $student->id)->where('status', 'active')->first() : null;

        $children = $isParent
            ? Student::query()->forGuardianAccount($user)
                ->whereHas('enrolments', fn ($query) => $query->where('academic_year_id', $year?->id ?? 0)->where('status', 'active'))
                ->orderBy('first_name')->orderBy('last_name')->get()
            : collect();

        $selectedStudentId = null;
        if ($isParent && $request->filled('student_id')) {
            $selectedStudentId = $request->integer('student_id');
            abort_unless($children->contains('id', $selectedStudentId), 404);
        }

        $childIds = $isParent ? $children->when($selectedStudentId, fn ($collection) => $collection->where('id', $selectedStudentId))->modelKeys() : [];
        $childSectionIds = $isParent && $year
            ? Enrolment::query()->forAcademicYear($year)->whereIn('student_id', $childIds)->where('status', 'active')->pluck('section_id')->unique()
            : collect();

        return compact('isStaff', 'isStudent', 'isParent', 'isTeacher', 'year', 'staff', 'student', 'enrolment', 'children', 'selectedStudentId', 'childIds', 'childSectionIds');
    }

    private function scopedQuery(array $c): Builder
    {
        return HomeworkAssignment::query()
            ->when($c['year'], fn ($query) => $query->where('academic_year_id', $c['year']->id), fn ($query) => $query->whereRaw('1 = 0'))
            ->when($c['isStudent'], fn ($query) => $query->where('section_id', $c['enrolment']?->section_id ?? 0))
            ->when($c['isParent'], fn ($query) => $query->whereIn('section_id', $c['childSectionIds']))
            ->when($c['isTeacher'], fn ($query) => $query->whereHas('section.teacherSubjectAssignments', fn ($assignments) => $assignments
                ->where('staff_id', $c['staff']?->id ?? 0)
                ->whereColumn('teacher_subject_assignments.subject_id', 'homework_assignments.subject_id')
                ->whereColumn('teacher_subject_assignments.academic_year_id', 'homework_assignments.academic_year_id')));
    }

    /** Students see only their own submissions, parents only their children's. */
    private function limitSubmissions($query, array $c)
    {
        return $query
            ->when($c['isStudent'], fn ($inner) => $inner->where('student_id', $c['student']?->id ?? 0))
            ->when($c['isParent'], fn ($inner) => $inner->whereIn('student_id', $c['childIds']));
    }

    private function role(array $c): string
    {
        return $c['isStudent'] ? 'student' : ($c['isParent'] ? 'parent' : 'staff');
    }

    private function canCreate(array $c): bool
    {
        return (bool) ($c['staff'] && auth()->user()->can('homework.create') && $c['year'] && $c['year']->status !== AcademicYearStatus::Locked);
    }

    private function canManage($user, array $c, string $permission): bool
    {
        return $c['isTeacher'] && $user->can($permission) && $c['year'] && $c['year']->status !== AcademicYearStatus::Locked;
    }

    private function canSubmit($user, array $c, HomeworkAssignment $assignment, ?HomeworkSubmission $mine): bool
    {
        return $c['isStudent']
            && $user->can('portal.homework.submit')
            && $c['enrolment']?->section_id === $assignment->section_id
            && $c['year']?->status !== AcademicYearStatus::Locked
            && ! today()->lt($assignment->assigned_date)
            && ! today()->gt($assignment->due_date)
            && ! $mine?->graded_at;
    }

    /** upcoming | open | closed */
    private function window(HomeworkAssignment $assignment): string
    {
        return today()->lt($assignment->assigned_date) ? 'upcoming' : (today()->gt($assignment->due_date) ? 'closed' : 'open');
    }

    private function listRow(HomeworkAssignment $assignment, array $c, array $rosters): array
    {
        $window = $this->window($assignment);
        $row = [
            'id' => $assignment->id,
            'title' => $assignment->title,
            'excerpt' => Str::limit($assignment->instructions, 160),
            'assignedDate' => $assignment->assigned_date->toDateString(),
            'dueDate' => $assignment->due_date->toDateString(),
            'maxPoints' => (float) $assignment->max_points,
            'section' => $assignment->section->schoolClass->name.' · '.$assignment->section->name,
            'subject' => $assignment->subject->name,
            'assignedBy' => $assignment->assigner->name,
            'window' => $window,
            'submitted' => $assignment->submissions_count,
            'graded' => $assignment->graded_count,
        ];

        if ($c['isStaff']) {
            $row['roster'] = $rosters[$assignment->section_id] ?? 0;
            $row['ungraded'] = $assignment->submissions_count - $assignment->graded_count;
        }
        if ($c['isStudent']) {
            $mine = $assignment->submissions->first();
            $row['myStatus'] = $mine ? ($mine->graded_at ? 'graded' : 'submitted') : ($window === 'closed' ? 'missing' : ($window === 'upcoming' ? 'upcoming' : 'to_do'));
            $row['myScore'] = $mine?->score;
        }

        return $row;
    }

    private function submissionRow(HomeworkSubmission $submission, bool $withStudent): array
    {
        return [
            'id' => $submission->id,
            'studentId' => $submission->student_id,
            'studentName' => $withStudent ? $this->studentName($submission->student) : null,
            'admissionNumber' => $withStudent ? $submission->student->admission_number : null,
            'content' => $submission->content,
            'submittedAt' => $submission->submitted_at->toDateTimeString(),
            'score' => $submission->score,
            'feedback' => $submission->feedback,
            'gradedBy' => $submission->grader?->name,
            'graded' => $submission->graded_at !== null,
        ];
    }

    /** @return \Illuminate\Support\Collection<int, Enrolment> */
    private function roster(?AcademicYear $year, HomeworkAssignment $assignment)
    {
        return $year
            ? Enrolment::query()->forAcademicYear($year)->where('section_id', $assignment->section_id)->where('status', 'active')
                ->with('student:id,first_name,middle_name,last_name,admission_number')->get()->unique('student_id')->values()
            : collect();
    }

    /** @return array<int, int> */
    private function rosterSizes(?AcademicYear $year, $sectionIds): array
    {
        if (! $year || $sectionIds->isEmpty()) {
            return [];
        }

        return Enrolment::query()->forAcademicYear($year)->whereIn('section_id', $sectionIds)->where('status', 'active')
            ->selectRaw('section_id, COUNT(DISTINCT student_id) as total')->groupBy('section_id')->pluck('total', 'section_id')->map(fn ($total): int => (int) $total)->all();
    }

    private function subjectOptions(array $c)
    {
        return $c['staff'] && $c['year']
            ? TeacherSubjectAssignment::query()->with(['section.schoolClass:id,name', 'subject:id,name'])
                ->where('academic_year_id', $c['year']->id)->where('staff_id', $c['staff']->id)->get()
                ->map(fn (TeacherSubjectAssignment $assignment): array => [
                    'sectionId' => $assignment->section_id,
                    'subjectId' => $assignment->subject_id,
                    'label' => $assignment->section->schoolClass->name.' · '.$assignment->section->name.' — '.$assignment->subject->name,
                ])->values()
            : collect();
    }

    private function studentName(Student $student): string
    {
        return trim(implode(' ', array_filter([$student->first_name, $student->middle_name, $student->last_name])));
    }
}