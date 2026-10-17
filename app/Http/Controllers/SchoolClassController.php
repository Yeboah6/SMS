<?php

namespace App\Http\Controllers;

use App\Enums\AcademicYearStatus;
use App\Concerns\ScopesTeacherAccess;
use App\Http\Requests\StoreSchoolClassRequest;
use App\Http\Requests\SyncClassSubjectsRequest;
use App\Http\Requests\UpdateSchoolClassRequest;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Services\AcademicStructureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SchoolClassController extends Controller
{
    use ScopesTeacherAccess;

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('classes.view'), 403);

        $years = AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name', 'status']);
        $yearId = $request->integer('academic_year_id')
            ?: AcademicYear::active()->value('id')
            ?: $years->first()?->id;
        $staffId = $this->scopedStaffId($request);

        $classes = SchoolClass::query()
            ->with(['sections' => fn ($query) => $query->withCount('students')->with('teacherClassAssignments')])
            ->withCount('classSubjects')
            ->when(
                $yearId,
                fn ($query) => $query->forAcademicYear((int) $yearId),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->when($staffId !== null, fn ($query) => $query->whereHas(
                'sections.teacherClassAssignments',
                fn ($query) => $query->where('staff_id', $staffId)->where('academic_year_id', (int) $yearId),
            ))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('Classes/Index', [
            'years' => $years->map(fn (AcademicYear $year): array => [
                'id' => $year->id,
                'name' => $year->name,
                'status' => $year->status->value,
            ]),
            'selectedYearId' => $yearId,
            'yearLocked' => $this->yearIsLocked($yearId ? (int) $yearId : null),
            'classes' => $classes->map(function (SchoolClass $schoolClass) use ($staffId): array {
                $sections = $this->visibleSections($schoolClass, $staffId);

                return [
                    'id' => $schoolClass->id,
                    'name' => $schoolClass->name,
                    'sortOrder' => $schoolClass->sort_order,
                    'sectionsCount' => $sections->count(),
                    'subjectsCount' => $schoolClass->class_subjects_count,
                    'studentsCount' => (int) $sections->sum('students_count'),
                ];
            })->values(),
            'can' => [
                'create' => $user->can('classes.create'),
                'update' => $user->can('classes.update'),
                'delete' => $user->can('classes.delete'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->can('classes.create'), 403);

        $years = AcademicYear::query()
            ->where('status', '!=', AcademicYearStatus::Locked->value)
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'status']);

        $requested = $request->integer('academic_year_id')
            ?: AcademicYear::active()->value('id');
        $selected = $years->firstWhere('id', $requested)?->id ?? $years->first()?->id;

        return Inertia::render('Classes/Create', [
            'years' => $years->map(fn (AcademicYear $year): array => [
                'id' => $year->id,
                'name' => $year->name,
                'status' => $year->status->value,
            ]),
            'selectedYearId' => $selected,
        ]);
    }

    public function store(
        StoreSchoolClassRequest $request,
        AcademicStructureService $service,
    ): RedirectResponse {
        $data = $request->validated();
        $year = AcademicYear::query()->findOrFail($data['academic_year_id']);

        $service->createClass($year, $data);

        return redirect()
            ->route('classes.index', ['academic_year_id' => $year->id])
            ->with('status', 'Class created.');
    }

    public function show(Request $request, SchoolClass $schoolClass): Response
    {
        $user = $request->user();
        abort_unless($user->can('classes.view'), 403);

        $staffId = $this->scopedStaffId($request);

        $schoolClass->load([
            'sections' => fn ($query) => $query
                ->orderBy('name')
                ->with(['students', 'teacherClassAssignments', 'teacherSubjectAssignments']),
            'classSubjects.subject',
        ]);

        $sections = $this->visibleSections($schoolClass, $staffId);
        abort_if($staffId !== null && $sections->isEmpty(), 403);

        $teacherSubjectIds = $staffId === null
            ? null
            : $sections
                ->flatMap(fn (Section $section) => $section->teacherSubjectAssignments
                    ->where('staff_id', $staffId)
                    ->pluck('subject_id'))
                ->unique();

        $canViewStudents = $user->can('students.view');
        $year = AcademicYear::query()->find($schoolClass->academic_year_id);

        return Inertia::render('Classes/Show', [
            'schoolClass' => [
                'id' => $schoolClass->id,
                'name' => $schoolClass->name,
                'sortOrder' => $schoolClass->sort_order,
                'yearId' => $year?->id,
                'yearName' => $year?->name,
            ],
            'yearLocked' => $this->yearIsLocked($year?->id),
            'sections' => $sections->map(fn (Section $section): array => [
                'id' => $section->id,
                'name' => $section->name,
                'capacity' => $section->capacity,
                'studentsCount' => $section->students->count(),
            ])->values(),
            'subjects' => $schoolClass->classSubjects
                ->when($teacherSubjectIds !== null, fn ($rows) => $rows->whereIn('subject_id', $teacherSubjectIds))
                ->map(fn (ClassSubject $row): array => [
                    'id' => $row->subject->id,
                    'code' => $row->subject->code,
                    'name' => $row->subject->name,
                ])->values(),
            'availableSubjects' => Subject::query()
                ->when($staffId !== null, fn ($query) => $query->whereHas(
                    'teacherSubjectAssignments',
                    fn ($query) => $query->where('staff_id', $staffId)->where('academic_year_id', (int) $schoolClass->academic_year_id),
                ))
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'students' => $canViewStudents
                ? $sections
                    ->flatMap(fn (Section $section) => $section->students
                        ->map(fn ($student) => $this->studentPayload($student, $section)))
                    ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values()
                : [],
            'can' => [
                'update' => $user->can('classes.update'),
                'delete' => $user->can('classes.delete'),
                'createSection' => $user->can('sections.create'),
                'updateSection' => $user->can('sections.update'),
                'deleteSection' => $user->can('sections.delete'),
                'assignSubjects' => $user->can('class_subjects.assign'),
                'viewStudents' => $canViewStudents,
            ],
        ]);
    }

    public function edit(Request $request, SchoolClass $schoolClass): Response
    {
        abort_unless($request->user()->can('classes.update'), 403);
        abort_if($this->yearIsLocked((int) $schoolClass->academic_year_id), 403);

        return Inertia::render('Classes/Edit', [
            'schoolClass' => [
                'id' => $schoolClass->id,
                'name' => $schoolClass->name,
                'sortOrder' => $schoolClass->sort_order,
            ],
        ]);
    }

    public function update(
        UpdateSchoolClassRequest $request,
        SchoolClass $schoolClass,
        AcademicStructureService $service,
    ): RedirectResponse {
        $service->updateClass($schoolClass, $request->validated());

        return redirect()
            ->route('classes.show', $schoolClass)
            ->with('status', 'Class updated.');
    }

    public function destroy(
        Request $request,
        SchoolClass $schoolClass,
        AcademicStructureService $service,
    ): RedirectResponse {
        abort_unless($request->user()->can('classes.delete'), 403);

        $yearId = $schoolClass->academic_year_id;
        $service->deleteClass($schoolClass);

        return redirect()
            ->route('classes.index', ['academic_year_id' => $yearId])
            ->with('status', 'Class deleted.');
    }

    public function syncSubjects(
        SyncClassSubjectsRequest $request,
        SchoolClass $schoolClass,
        AcademicStructureService $service,
    ): RedirectResponse {
        $service->syncClassSubjects($schoolClass, $request->validated('subject_ids'));

        return back()->with('status', 'Class subjects updated.');
    }
}