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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeworkController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $canViewStaff = $user->can('homework.view');
        $isStudent = $user->hasRole('Student') && $user->can('portal.homework.view');
        $isParent = ! $user->hasRole('Student')
            && $user->hasRole('Parent')
            && $user->can('portal.homework.view');
        abort_unless($canViewStaff || $isStudent || $isParent, 403);

        $academicYear = AcademicYear::active()->first();
        $isTeacher = $user->hasRole('Teacher') && ! $user->hasRole('Super Admin');
        $staff = $isTeacher
            ? Staff::query()->where('user_id', $user->id)->where('status', 'active')->where('is_teacher', true)->first()
            : null;
        $student = $isStudent ? Student::query()->where('user_id', $user->id)->where('status', 'active')->first() : null;
        $enrolment = $student && $academicYear
            ? Enrolment::query()->forAcademicYear($academicYear)->where('student_id', $student->id)->where('status', 'active')->first()
            : null;
        $children = $isParent
            ? Student::query()
                ->forGuardianAccount($user)
                ->whereHas('enrolments', fn ($query) => $query
                    ->where('academic_year_id', $academicYear?->id ?? 0)
                    ->where('status', 'active'))
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get()
            : collect();
        $selectedStudentId = null;

        if ($isParent && $request->filled('student_id')) {
            $selectedStudentId = $request->integer('student_id');
            abort_unless($children->contains('id', $selectedStudentId), 404);
        }

        $childIds = $isParent
            ? $children->when($selectedStudentId, fn ($collection) => $collection->where('id', $selectedStudentId))->modelKeys()
            : [];
        $childSectionIds = $isParent && $academicYear
            ? Enrolment::query()
                ->forAcademicYear($academicYear)
                ->whereIn('student_id', $childIds)
                ->where('status', 'active')
                ->pluck('section_id')
                ->unique()
            : collect();

        $subjectAssignments = $staff && $academicYear
            ? TeacherSubjectAssignment::query()
                ->with(['section.schoolClass:id,name', 'subject:id,name'])
                ->where('academic_year_id', $academicYear->id)
                ->where('staff_id', $staff->id)
                ->get()
            : collect();
        $canCreate = $staff && $user->can('homework.create');
        $assignmentQuery = HomeworkAssignment::query()
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id), fn ($query) => $query->whereRaw('1 = 0'))
            ->when($isStudent, fn ($query) => $query->where('section_id', $enrolment?->section_id ?? 0))
            ->when($isParent, fn ($query) => $query->whereIn('section_id', $childSectionIds))
            ->when($isTeacher, fn ($query) => $query->whereHas('section.teacherSubjectAssignments', fn ($assignments) => $assignments
                ->where('staff_id', $staff?->id ?? 0)
                ->whereColumn('teacher_subject_assignments.subject_id', 'homework_assignments.subject_id')
                ->whereColumn('teacher_subject_assignments.academic_year_id', 'homework_assignments.academic_year_id')))
            ->with([
                'section:id,name,class_id',
                'section.schoolClass:id,name',
                'subject:id,name',
                'assigner:id,name',
                'submissions' => fn ($query) => $query
                    ->when($isStudent, fn ($submissions) => $submissions->where('student_id', $student?->id ?? 0))
                    ->when($isParent, fn ($submissions) => $submissions->whereIn('student_id', $childIds))
                    ->with(['student:id,first_name,middle_name,last_name,admission_number', 'grader:id,name'])
                    ->orderBy('submitted_at'),
            ])
            ->withCount(['submissions' => fn ($query) => $query
                ->when($isStudent, fn ($submissions) => $submissions->where('student_id', $student?->id ?? 0))
                ->when($isParent, fn ($submissions) => $submissions->whereIn('student_id', $childIds))])
            ->orderByDesc('assigned_date')
            ->orderByDesc('id');

        $assignments = $assignmentQuery
            ->paginate(20)
            ->withQueryString()
            ->through(fn (HomeworkAssignment $assignment): array => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'instructions' => $assignment->instructions,
                'assignedDate' => $assignment->assigned_date->toDateString(),
                'dueDate' => $assignment->due_date->toDateString(),
                'maxPoints' => (float) $assignment->max_points,
                'section' => $assignment->section->schoolClass->name.' · '.$assignment->section->name,
                'subject' => $assignment->subject->name,
                'assignedBy' => $assignment->assigner->name,
                'submissionCount' => $assignment->submissions_count,
                'canSubmit' => $isStudent
                    && $user->can('portal.homework.submit')
                    && $enrolment?->section_id === $assignment->section_id
                    && $academicYear?->status !== AcademicYearStatus::Locked
                    && ! today()->lt($assignment->assigned_date)
                    && ! today()->gt($assignment->due_date)
                    && ! $assignment->submissions->first()?->graded_at,
                'canGrade' => $isTeacher && $user->can('homework.grade'),
                'canUpdate' => $isTeacher && $user->can('homework.update'),
                'mySubmission' => $isStudent && $assignment->submissions->first()
                    ? [
                        'id' => $assignment->submissions->first()->id,
                        'content' => $assignment->submissions->first()->content,
                        'submittedAt' => $assignment->submissions->first()->submitted_at->toDateTimeString(),
                        'score' => $assignment->submissions->first()->score,
                        'feedback' => $assignment->submissions->first()->feedback,
                        'gradedBy' => $assignment->submissions->first()->grader?->name,
                    ]
                    : null,
                'childSubmissions' => $isParent ? $assignment->submissions->map(fn (HomeworkSubmission $submission): array => [
                    'id' => $submission->id,
                    'studentName' => trim(implode(' ', array_filter([
                        $submission->student->first_name,
                        $submission->student->middle_name,
                        $submission->student->last_name,
                    ]))),
                    'content' => $submission->content,
                    'submittedAt' => $submission->submitted_at->toDateTimeString(),
                    'score' => $submission->score,
                    'feedback' => $submission->feedback,
                    'gradedBy' => $submission->grader?->name,
                ])->values() : [],
                'submissions' => ($isStudent || $isParent) ? [] : $assignment->submissions->map(fn (HomeworkSubmission $submission): array => [
                    'id' => $submission->id,
                    'studentId' => $submission->student_id,
                    'studentName' => trim(implode(' ', array_filter([
                        $submission->student->first_name,
                        $submission->student->middle_name,
                        $submission->student->last_name,
                    ]))),
                    'admissionNumber' => $submission->student->admission_number,
                    'content' => $submission->content,
                    'submittedAt' => $submission->submitted_at->toDateTimeString(),
                    'score' => $submission->score,
                    'feedback' => $submission->feedback,
                    'gradedBy' => $submission->grader?->name,
                ])->values(),
            ]);

        return Inertia::render('Homework/Index', [
            'academicYear' => $academicYear?->only(['id', 'name']),
            'assignments' => $assignments,
            'subjectAssignments' => $subjectAssignments->map(fn (TeacherSubjectAssignment $assignment): array => [
                'sectionId' => $assignment->section_id,
                'subjectId' => $assignment->subject_id,
                'label' => $assignment->section->schoolClass->name.' · '.$assignment->section->name.' — '.$assignment->subject->name,
            ])->values(),
            'canCreate' => (bool) $canCreate,
            'isStudent' => $isStudent,
            'isParent' => $isParent,
            'children' => $isParent ? $children->map(fn (Student $child): array => [
                'id' => $child->id,
                'name' => trim(implode(' ', array_filter([
                    $child->first_name,
                    $child->middle_name,
                    $child->last_name,
                ]))),
            ])->values() : [],
            'selectedStudentId' => $selectedStudentId,
        ]);
    }

    public function store(
        StoreHomeworkAssignmentRequest $request,
        HomeworkService $service,
    ): RedirectResponse {
        $service->create($request->validated(), $request->user());

        return back()->with('status', 'Homework assigned.');
    }

    public function submit(
        StoreHomeworkSubmissionRequest $request,
        HomeworkAssignment $homeworkAssignment,
        HomeworkService $service,
    ): RedirectResponse {
        $service->submit($homeworkAssignment, $request->validated(), $request->user());

        return back()->with('status', 'Homework submitted.');
    }

    public function update(
        UpdateHomeworkAssignmentRequest $request,
        HomeworkAssignment $homeworkAssignment,
        HomeworkService $service,
    ): RedirectResponse {
        $service->update($homeworkAssignment, $request->validated(), $request->user());

        return back()->with('status', 'Homework updated.');
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
}
