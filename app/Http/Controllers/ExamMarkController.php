<?php

namespace App\Http\Controllers;

use App\Enums\AcademicYearStatus;
use App\Concerns\ManagesExamAccess;
use App\Http\Requests\RecordExamMarksRequest;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Models\Section;
use App\Services\ExamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExamMarkController extends Controller
{
    use ManagesExamAccess;

    public function edit(Request $request, Exam $exam, ExamSubject $examSubject, ExamService $service): Response
    {
        abort_unless($examSubject->exam_id === $exam->id, 404);

        $user = $request->user();
        abort_unless($user->can('marks.view') || $user->can('marks.enter') || $user->can('marks.update'), 403);
        [, $isTeacher, $staff] = $this->authorizeExam($request, $exam);

        $exam->load(['schoolClass:id,name', 'academicYear:id,name,status']);
        $examSubject->load('subject:id,name');

        $sections = Section::query()
            ->with('schoolClass:id,name')
            ->where('academic_year_id', $exam->academic_year_id)
            ->where('class_id', $exam->class_id)
            ->when($isTeacher, fn ($query) => $query->whereHas('teacherSubjectAssignments', fn ($assignments) => $assignments
                ->where('academic_year_id', $exam->academic_year_id)
                ->where('staff_id', $staff?->id ?? 0)
                ->where('subject_id', $examSubject->subject_id)))
            ->when($isTeacher, fn ($query) => $query->whereHas('teacherClassAssignments', fn ($assignments) => $assignments
                ->where('academic_year_id', $exam->academic_year_id)
                ->where('staff_id', $staff?->id ?? 0)))
            ->orderBy('name')
            ->get();
        abort_if($isTeacher && $sections->isEmpty(), 403);

        $section = $request->filled('section_id')
            ? $sections->firstWhere('id', $request->integer('section_id'))
            : $sections->first();
        abort_if($request->filled('section_id') && ! $section, 404);

        $enrolments = $section
            ? $service->sectionRoster($exam->academic_year_id, $exam->class_id, $section, $examSubject->exam_date)
            : collect();
        $marks = $enrolments->isNotEmpty()
            ? ExamMark::query()
                ->where('exam_subject_id', $examSubject->id)
                ->whereIn('student_id', $enrolments->pluck('student_id'))
                ->get()
                ->keyBy('student_id')
            : collect();

        $unlocked = $exam->academicYear->status !== AcademicYearStatus::Locked;

        return Inertia::render('Exams/Marks', [
            'exam' => [
                'id' => $exam->id,
                'name' => $exam->name,
                'status' => $exam->status,
                'className' => $exam->schoolClass->name,
                'academicYear' => $exam->academicYear->name,
            ],
            'subject' => [
                'id' => $examSubject->id,
                'name' => $examSubject->subject->name,
                'examDate' => $examSubject->exam_date->toDateString(),
                'maxMarks' => (float) $examSubject->max_marks,
                'passMarks' => (float) $examSubject->pass_marks,
            ],
            'sections' => $sections->map(fn (Section $item): array => [
                'id' => $item->id,
                'name' => $item->schoolClass->name.' · '.$item->name,
            ])->values(),
            'selectedSectionId' => $section?->id,
            'roster' => $enrolments->map(fn ($enrolment): array => [
                'studentId' => $enrolment->student_id,
                'name' => trim(implode(' ', array_filter([
                    $enrolment->student->first_name,
                    $enrolment->student->middle_name,
                    $enrolment->student->last_name,
                ]))),
                'admissionNumber' => $enrolment->student->admission_number,
                'status' => $marks->get($enrolment->student_id)?->status ?? 'present',
                'marksObtained' => $marks->get($enrolment->student_id)?->marks_obtained,
                'remarks' => $marks->get($enrolment->student_id)?->remarks ?? '',
            ])->values(),
            'canRecordMarks' => $exam->status === 'draft'
                && $unlocked
                && ($user->can('marks.enter') || $user->can('marks.update')),
        ]);
    }

    public function store(
        RecordExamMarksRequest $request,
        Exam $exam,
        ExamSubject $examSubject,
        ExamService $service,
    ): RedirectResponse {
        abort_unless($examSubject->exam_id === $exam->id, 404);
        $service->recordMarks($exam, $examSubject, $request->validated(), $request->user());

        return back()->with('status', 'Marks saved.');
    }
}