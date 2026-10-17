<?php

namespace App\Http\Controllers;

use App\Concerns\ScopesTeacherAccess;
use App\Http\Requests\StoreSectionRequest;
use App\Http\Requests\UpdateSectionRequest;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Staff;
use App\Services\AcademicStructureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SectionController extends Controller
{
    use ScopesTeacherAccess;

    public function create(Request $request, SchoolClass $schoolClass): Response
    {
        abort_unless($request->user()->can('sections.create'), 403);
        abort_if($this->yearIsLocked((int) $schoolClass->academic_year_id), 403);

        return Inertia::render('Sections/Create', [
            'schoolClass' => ['id' => $schoolClass->id, 'name' => $schoolClass->name],
        ]);
    }

    public function store(
        StoreSectionRequest $request,
        SchoolClass $schoolClass,
        AcademicStructureService $service,
    ): RedirectResponse {
        $service->createSection($schoolClass, $request->validated());

        return redirect()
            ->route('classes.show', $schoolClass)
            ->with('status', 'Section created.');
    }

    public function show(Request $request, Section $section): Response
    {
        $user = $request->user();
        abort_unless($user->can('classes.view'), 403);

        $staffId = $this->scopedStaffId($request);

        $section->load([
            'schoolClass.classSubjects.subject',
            'students',
            'teacherClassAssignments.staff',
            'teacherSubjectAssignments.staff',
            'teacherSubjectAssignments.subject',
        ]);

        abort_if(
            $staffId !== null && ! $section->teacherClassAssignments->contains('staff_id', $staffId),
            403,
        );

        $schoolClass = $section->schoolClass;
        $canManageTeachers = $user->can('teacher_assignments.manage');
        $canViewStudents = $user->can('students.view');

        return Inertia::render('Sections/Show', [
            'section' => [
                'id' => $section->id,
                'name' => $section->name,
                'capacity' => $section->capacity,
                'teacherAssignments' => $canManageTeachers
                    ? $section->teacherClassAssignments->map(fn ($assignment): array => [
                        'staffId' => $assignment->staff_id,
                        'name' => $assignment->staff->name,
                    ])->values()
                    : [],
                'subjectAssignments' => $canManageTeachers
                    ? $section->teacherSubjectAssignments->map(fn ($assignment): array => [
                        'staffId' => $assignment->staff_id,
                        'staffName' => $assignment->staff->name,
                        'subjectId' => $assignment->subject_id,
                        'subjectName' => $assignment->subject->name,
                    ])->values()
                    : [],
            ],
            'schoolClass' => ['id' => $schoolClass->id, 'name' => $schoolClass->name],
            'yearLocked' => $this->yearIsLocked((int) $schoolClass->academic_year_id),
            'subjects' => $schoolClass->classSubjects->map(fn ($row): array => [
                'id' => $row->subject->id,
                'name' => $row->subject->name,
            ])->values(),
            'teachers' => $canManageTeachers
                ? Staff::query()->where('is_teacher', true)->where('status', 'active')->orderBy('name')->get(['id', 'staff_number', 'name'])
                : [],
            'students' => $canViewStudents
                ? $section->students
                    ->map(fn ($student) => $this->studentPayload($student, $section))
                    ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values()
                : [],
            'can' => [
                'updateSection' => $user->can('sections.update'),
                'deleteSection' => $user->can('sections.delete'),
                'assignTeachers' => $canManageTeachers,
                'viewStudents' => $canViewStudents,
            ],
        ]);
    }

    public function edit(Request $request, Section $section): Response
    {
        abort_unless($request->user()->can('sections.update'), 403);

        $schoolClass = $section->schoolClass;
        abort_if($this->yearIsLocked((int) $schoolClass->academic_year_id), 403);

        return Inertia::render('Sections/Edit', [
            'schoolClass' => ['id' => $schoolClass->id, 'name' => $schoolClass->name],
            'section' => [
                'id' => $section->id,
                'name' => $section->name,
                'capacity' => $section->capacity,
            ],
        ]);
    }

    public function update(
        UpdateSectionRequest $request,
        Section $section,
        AcademicStructureService $service,
    ): RedirectResponse {
        $service->updateSection($section, $request->validated());

        return redirect()
            ->route('sections.show', $section)
            ->with('status', 'Section updated.');
    }

    public function destroy(
        Request $request,
        Section $section,
        AcademicStructureService $service,
    ): RedirectResponse {
        abort_unless($request->user()->can('sections.delete'), 403);

        $schoolClass = $section->schoolClass;
        $service->deleteSection($section);

        return redirect()
            ->route('classes.show', $schoolClass)
            ->with('status', 'Section deleted.');
    }
}