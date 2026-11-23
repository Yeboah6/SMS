<?php

namespace App\Http\Controllers;

use App\Concerns\ManagesExamAccess;
use App\Enums\AcademicYearStatus;
use App\Models\Enrolment;
use App\Models\Exam;
use App\Models\Staff;
use App\Models\TeacherClassAssignment;
use App\Models\TeacherSubjectAssignment;
use App\Services\ReportCardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportCardController extends Controller
{
    use ManagesExamAccess;

    /** All report cards for the exam's class. */
    public function index(Request $request, Exam $exam, ReportCardService $service): Response
    {
        return $this->render($request, $exam, $service, null);
    }

    /** One student's report card. */
    public function show(Request $request, Exam $exam, int $student, ReportCardService $service): Response
    {
        return $this->render($request, $exam, $service, $student);
    }

    /** Save class scores and/or per-student report details in one go. */
    public function save(Request $request, Exam $exam, ReportCardService $service): RedirectResponse
    {
        [, $isTeacher, $staff] = $this->authorizeExam($request, $exam);
        abort_unless($request->user()->can('marks.update'), 403);
        abort_unless($exam->status === 'published', 403);

        $data = $request->validate([
            'scores' => ['required_without:cards', 'array', 'max:2000'],
            'scores.*.student_id' => ['required', 'integer'],
            'scores.*.subject_id' => ['required', 'integer'],
            'scores.*.score' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'cards' => ['required_without:scores', 'array', 'max:500'],
            'cards.*.student_id' => ['required', 'integer'],
            'cards.*.closing_date' => ['nullable', 'date'],
            'cards.*.next_term_begins' => ['nullable', 'date'],
            'cards.*.next_term_ends' => ['nullable', 'date'],
            'cards.*.attendance' => ['nullable', 'integer', 'min:0', 'max:366'],
            'cards.*.attendance_out_of' => ['nullable', 'integer', 'min:0', 'max:366'],
            'cards.*.conduct' => ['nullable', 'string', 'max:255'],
            'cards.*.attitude' => ['nullable', 'string', 'max:255'],
            'cards.*.class_teacher_remarks' => ['nullable', 'string', 'max:2000'],
            'cards.*.head_teacher_remarks' => ['nullable', 'string', 'max:2000'],
            'cards.*.school_fees_arrears' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'cards.*.next_term_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);

        if ($isTeacher) {
            $this->authorizeTeacherChanges($data, $this->teacherScope($exam, $staff));
        }

        DB::transaction(function () use ($service, $exam, $data, $request): void {
            if (! empty($data['scores'])) {
                $service->saveClassScores($exam, $data['scores'], $request->user());
            }
            if (! empty($data['cards'])) {
                $service->saveDetails($exam, $data['cards'], $request->user());
            }
        });

        return back()->with('status', 'Report card changes saved.');
    }

    private function render(Request $request, Exam $exam, ReportCardService $service, ?int $studentId): Response
    {
        [, $isTeacher, $staff] = $this->authorizeExam($request, $exam);
        abort_unless($request->user()->can('marks.view'), 403);
        abort_unless($exam->status === 'published', 403, 'Report cards are available once results are published.');

        $exam->load(['schoolClass:id,name', 'term:id,name,start_date,end_date', 'academicYear:id,name']);
        $cards = $service->forExam($exam, $studentId);
        $scope = $isTeacher ? $this->teacherScope($exam, $staff) : null;

        if ($scope !== null) {
            $cards = $cards->filter(fn (array $card): bool => array_key_exists(
                (int) $card['student']['id'],
                $scope['studentSectionIds'],
            ));
        }

        abort_if($studentId && $cards->isEmpty(), 404);

        $cards = $cards->map(function (array $card) use ($scope): array {
            $sectionId = $scope['studentSectionIds'][(int) $card['student']['id']] ?? null;

            $card['rows'] = collect($card['rows'])
                ->map(function (array $row) use ($scope, $sectionId): array {
                    $row['canEditClassScore'] = $scope === null
                        || ($sectionId !== null && in_array(
                            $sectionId.':'.(int) $row['subjectId'],
                            $scope['subjectAssignments'],
                            true,
                        ));

                    return $row;
                })
                ->all();

            return $card;
        });

        return Inertia::render('Exams/ReportCards', [
            'exam' => $service->header($exam),
            'cards' => $cards,
            'single' => (bool) $studentId,
            'can' => [
                'teacherScoped' => $isTeacher,
                'saveClassScores' => $request->user()->can('marks.update')
                    && $exam->academicYear->status !== AcademicYearStatus::Locked,
            ],
        ]);
    }

    /**
     * @return array{studentSectionIds: array<int, int>, subjectAssignments: list<string>}
     */
    private function teacherScope(Exam $exam, ?Staff $staff): array
    {
        if ($staff === null) {
            return ['studentSectionIds' => [], 'subjectAssignments' => []];
        }

        $sectionIds = TeacherClassAssignment::query()
            ->where('academic_year_id', $exam->academic_year_id)
            ->where('staff_id', $staff->id)
            ->whereHas('section', fn ($sections) => $sections
                ->where('academic_year_id', $exam->academic_year_id)
                ->where('class_id', $exam->class_id))
            ->pluck('section_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($sectionIds === []) {
            return ['studentSectionIds' => [], 'subjectAssignments' => []];
        }

        $subjectAssignments = TeacherSubjectAssignment::query()
            ->where('academic_year_id', $exam->academic_year_id)
            ->where('staff_id', $staff->id)
            ->whereIn('section_id', $sectionIds)
            ->whereHas('section', fn ($sections) => $sections
                ->where('academic_year_id', $exam->academic_year_id)
                ->where('class_id', $exam->class_id))
            ->get(['section_id', 'subject_id'])
            ->map(fn (TeacherSubjectAssignment $assignment): string => $assignment->section_id.':'.$assignment->subject_id)
            ->all();

        $studentSectionIds = Enrolment::query()
            ->where('academic_year_id', $exam->academic_year_id)
            ->where('class_id', $exam->class_id)
            ->whereIn('section_id', $sectionIds)
            ->whereIn('status', ['active', 'completed'])
            ->whereDate('enrolment_date', '<=', $exam->end_date)
            ->where(fn ($enrolments) => $enrolments
                ->whereNull('exit_date')
                ->orWhereDate('exit_date', '>=', $exam->end_date))
            ->get(['student_id', 'section_id'])
            ->mapWithKeys(fn (Enrolment $enrolment): array => [
                (int) $enrolment->student_id => (int) $enrolment->section_id,
            ])
            ->all();

        return [
            'studentSectionIds' => $studentSectionIds,
            'subjectAssignments' => $subjectAssignments,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{studentSectionIds: array<int, int>, subjectAssignments: list<string>}  $scope
     */
    private function authorizeTeacherChanges(array $data, array $scope): void
    {
        foreach ($data['cards'] ?? [] as $card) {
            abort_unless(array_key_exists((int) $card['student_id'], $scope['studentSectionIds']), 403);
        }

        foreach ($data['scores'] ?? [] as $score) {
            $sectionId = $scope['studentSectionIds'][(int) $score['student_id']] ?? null;
            $assignment = $sectionId.':'.(int) $score['subject_id'];

            abort_unless(
                $sectionId !== null && in_array($assignment, $scope['subjectAssignments'], true),
                403,
            );
        }
    }
}
