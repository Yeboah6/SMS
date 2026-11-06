<?php

namespace App\Http\Controllers;

use App\Concerns\ManagesExamAccess;
use App\Enums\AcademicYearStatus;
use App\Models\Exam;
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
        $this->authorizeExam($request, $exam);
        abort_unless($request->user()->can('marks.update'), 403);
        abort_unless($exam->status === 'published', 403);

        $data = $request->validate([
            'scores' => ['required_without:cards', 'array', 'max:2000'],
            'scores.*.student_id' => ['required', 'integer'],
            'scores.*.subject_id' => ['required', 'integer'],
            'scores.*.score' => ['nullable', 'numeric', 'min:0', 'max:30'],
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
        $this->authorizeExam($request, $exam);
        abort_unless($request->user()->can('marks.view'), 403);
        abort_unless($exam->status === 'published', 403, 'Report cards are available once results are published.');

        $exam->load(['schoolClass:id,name', 'term:id,name,start_date,end_date', 'academicYear:id,name']);
        $cards = $service->forExam($exam, $studentId);
        abort_if($studentId && $cards->isEmpty(), 404);

        return Inertia::render('Exams/ReportCards', [
            'exam' => $service->header($exam),
            'cards' => $cards,
            'single' => (bool) $studentId,
            'can' => [
                'saveClassScores' => $request->user()->can('marks.update')
                    && $exam->academicYear->status !== AcademicYearStatus::Locked,
            ],
        ]);
    }
}