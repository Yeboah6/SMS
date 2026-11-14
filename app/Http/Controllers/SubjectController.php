<?php

namespace App\Http\Controllers;

use App\Concerns\ScopesTeacherAccess;
use App\Http\Requests\StoreSubjectRequest;
use App\Http\Requests\UpdateSubjectRequest;
use App\Models\ClassSubject;
use App\Models\Subject;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SubjectController extends Controller
{
    use ScopesTeacherAccess;

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('subjects.view'), 403);

        $staffId = $this->scopedStaffId($request);
        $search = trim((string) $request->query('q', ''));

        return Inertia::render('Subjects/Index', [
            'subjects' => Subject::query()
                ->when($staffId !== null, fn ($query) => $query->whereHas(
                    'teacherSubjectAssignments',
                    fn ($query) => $query->where('staff_id', $staffId),
                ))
                ->when($search !== '', fn ($query) => $query->where(
                    fn ($query) => $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%"),
                ))
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
            'filters' => ['q' => $search],
            'can' => [
                'create' => $user->can('subjects.create'),
                'update' => $user->can('subjects.update'),
                'delete' => $user->can('subjects.delete'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->can('subjects.create'), 403);

        return Inertia::render('Subjects/Create');
    }

    public function store(StoreSubjectRequest $request): RedirectResponse
    {
        $subject = Subject::query()->create($request->validated());

        return redirect()
            ->route('subjects.index', $subject)
            ->with('status', 'Subject created.');
    }

    public function show(Request $request, Subject $subject): Response
    {
        $user = $request->user();
        abort_unless($user->can('subjects.view'), 403);

        $staffId = $this->scopedStaffId($request);

        $assignmentQuery = $subject->teacherSubjectAssignments()
            ->join('sections', 'teacher_subject_assignments.section_id', '=', 'sections.id')
            ->join('classes', 'sections.class_id', '=', 'classes.id')
            ->when($staffId !== null, fn ($query) => $query->where('staff_id', $staffId))
            ->with(['staff', 'section.schoolClass'])
            ->orderByRaw('LOWER(classes.name)')
            ->orderByRaw('LOWER(sections.name)')
            ->orderBy('teacher_subject_assignments.id');

        abort_if($staffId !== null && ! (clone $assignmentQuery)->exists(), 403);

        $assignments = $assignmentQuery
            ->paginate(15, ['teacher_subject_assignments.*'], 'assignments_page')
            ->withQueryString()
            ->through(fn ($assignment): array => [
                'id' => $assignment->id,
                'staffName' => $assignment->staff->name,
                'sectionId' => $assignment->section->id,
                'sectionName' => $assignment->section->name,
                'className' => $assignment->section->schoolClass->name,
            ]);

        $classes = $staffId === null
            ? ClassSubject::query()
                ->join('classes', 'class_subjects.class_id', '=', 'classes.id')
                ->where('class_subjects.subject_id', $subject->id)
                ->with('schoolClass')
                ->orderByRaw('LOWER(classes.name)')
                ->orderBy('classes.id')
                ->paginate(15, ['class_subjects.*'], 'classes_page')
                ->withQueryString()
                ->through(fn (ClassSubject $row): array => [
                    'id' => $row->schoolClass->id,
                    'name' => $row->schoolClass->name,
                ])
            : null;

        return Inertia::render('Subjects/Show', [
            'subject' => [
                'id' => $subject->id,
                'code' => $subject->code,
                'name' => $subject->name,
                'description' => $subject->description,
            ],
            // Scoped teachers only see their own assignments, so the class list is hidden for them.
            'classes' => $classes,
            'assignments' => $assignments,
            'can' => [
                'update' => $user->can('subjects.update'),
                'delete' => $user->can('subjects.delete'),
            ],
        ]);
    }

    public function edit(Request $request, Subject $subject): Response
    {
        abort_unless($request->user()->can('subjects.update'), 403);

        return Inertia::render('Subjects/Edit', [
            'subject' => [
                'id' => $subject->id,
                'code' => $subject->code,
                'name' => $subject->name,
                'description' => $subject->description,
            ],
        ]);
    }

    public function update(UpdateSubjectRequest $request, Subject $subject): RedirectResponse
    {
        $subject->update($request->validated());

        return redirect()
            ->route('subjects.show', $subject)
            ->with('status', 'Subject updated.');
    }

    public function destroy(Request $request, Subject $subject): RedirectResponse
    {
        abort_unless($request->user()->can('subjects.delete'), 403);

        $inUse = ClassSubject::query()->where('subject_id', $subject->id)->exists()
            || $subject->teacherSubjectAssignments()->exists();

        if ($inUse) {
            throw ValidationException::withMessages([
                'subject' => 'This subject is assigned to a class or teacher. Remove those assignments before deleting it.',
            ]);
        }

        try {
            $subject->delete();
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'subject' => 'This subject has historical records (such as marks or homework) and cannot be deleted.',
            ]);
        }

        return redirect()
            ->route('subjects.index')
            ->with('status', 'Subject deleted.');
    }
}
