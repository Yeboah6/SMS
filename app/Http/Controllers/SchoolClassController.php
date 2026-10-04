<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSchoolClassRequest;
use App\Http\Requests\StoreSectionRequest;
use App\Http\Requests\SyncClassSubjectsRequest;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Subject;
use App\Services\AcademicStructureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SchoolClassController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('classes.view'), 403);

        $years = AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name', 'status']);
        $yearId = $request->integer('academic_year_id')
            ?: AcademicYear::active()->value('id')
            ?: $years->first()?->id;
        $user = $request->user();
        $isScopedTeacher = $user->hasRole('Teacher') && ! $user->hasRole('Super Admin');
        $teacherStaffId = $isScopedTeacher
            ? Staff::query()->where('user_id', $user->id)->value('id')
            : null;
        $classesQuery = SchoolClass::query()
            ->with([
                'sections.teacherClassAssignments.staff',
                'sections.teacherSubjectAssignments.staff',
                'sections.teacherSubjectAssignments.subject',
                'classSubjects.subject',
            ])
            ->when(
                $yearId,
                fn ($query) => $query->forAcademicYear((int) $yearId),
                fn ($query) => $query->whereRaw('1 = 0'),
            );

        if ($isScopedTeacher) {
            $classesQuery->whereHas('sections.teacherClassAssignments', fn ($query) => $query
                ->where('staff_id', $teacherStaffId ?? 0)
                ->where('academic_year_id', (int) $yearId));
        }

        return Inertia::render('Classes/Index', [
            'years' => $years->map(fn (AcademicYear $year): array => [
                'id' => $year->id,
                'name' => $year->name,
                'status' => $year->status->value,
            ]),
            'selectedYearId' => $yearId,
            'classes' => $classesQuery
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (SchoolClass $schoolClass): array => [
                    'id' => $schoolClass->id,
                    'name' => $schoolClass->name,
                    'sortOrder' => $schoolClass->sort_order,
                    'sections' => $schoolClass->sections
                        ->when($isScopedTeacher, fn ($sections) => $sections->filter(
                            fn (Section $section): bool => $section->teacherClassAssignments->contains('staff_id', $teacherStaffId),
                        ))
                        ->map(fn (Section $section): array => [
                            'id' => $section->id,
                            'name' => $section->name,
                            'capacity' => $section->capacity,
                            'teacherAssignments' => $user->can('teacher_assignments.manage')
                                ? $section->teacherClassAssignments->map(fn ($assignment): array => [
                                    'staffId' => $assignment->staff_id,
                                    'name' => $assignment->staff->name,
                                ])->values()
                                : [],
                            'subjectAssignments' => $user->can('teacher_assignments.manage')
                                ? $section->teacherSubjectAssignments->map(fn ($assignment): array => [
                                    'staffId' => $assignment->staff_id,
                                    'staffName' => $assignment->staff->name,
                                    'subjectId' => $assignment->subject_id,
                                    'subjectName' => $assignment->subject->name,
                                ])->values()
                                : [],
                        ])->values(),
                    'subjects' => $schoolClass->classSubjects
                        ->when($isScopedTeacher, function ($assignments) use ($schoolClass, $teacherStaffId) {
                            $teacherSubjectIds = $schoolClass->sections
                                ->flatMap(fn (Section $section) => $section->teacherSubjectAssignments
                                    ->where('staff_id', $teacherStaffId)
                                    ->pluck('subject_id'))
                                ->unique();

                            return $assignments->whereIn('subject_id', $teacherSubjectIds);
                        })
                        ->map(fn (ClassSubject $assignment): array => [
                            'id' => $assignment->subject->id,
                            'code' => $assignment->subject->code,
                            'name' => $assignment->subject->name,
                        ])->values(),
                ]),
            'subjects' => Subject::query()
                ->when($isScopedTeacher, fn ($query) => $query->whereHas('teacherSubjectAssignments', fn ($query) => $query
                    ->where('staff_id', $teacherStaffId ?? 0)
                    ->where('academic_year_id', (int) $yearId)))
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'teachers' => $user->can('teacher_assignments.manage')
                ? Staff::query()->where('is_teacher', true)->where('status', 'active')->orderBy('name')->get(['id', 'staff_number', 'name'])
                : [],
            'can' => [
                'create' => $request->user()->can('classes.create'),
                'createSection' => $request->user()->can('sections.create'),
                'assignSubjects' => $request->user()->can('class_subjects.assign'),
                'assignTeachers' => $request->user()->can('teacher_assignments.manage'),
            ],
        ]);
    }

    public function store(
        StoreSchoolClassRequest $request,
        AcademicStructureService $service,
    ): RedirectResponse {
        $data = $request->validated();
        $year = AcademicYear::query()->findOrFail($data['academic_year_id']);

        $service->createClass($year, $data);

        return back()->with('status', 'Class created.');
    }

    public function storeSection(
        StoreSectionRequest $request,
        SchoolClass $schoolClass,
        AcademicStructureService $service,
    ): RedirectResponse {
        $service->createSection($schoolClass, $request->validated());

        return back()->with('status', 'Section created.');
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
