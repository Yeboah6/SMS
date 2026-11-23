<?php

namespace App\Services;

use App\Enums\AcademicYearStatus;
use App\Models\AttendanceRecord;
use App\Models\ClassScore;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Models\Invoice;
use App\Models\ReportCardDetail;
use App\Models\SchoolSetting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ReportCardService
{
    public const CLASS_WEIGHT = 50;
    public const EXAM_WEIGHT = 50;

    /** Columns on report_card_details that the page can edit. */
    private const DETAIL_FIELDS = [
        'closing_date', 'next_term_begins', 'next_term_ends', 'attendance', 'attendance_out_of',
        'conduct', 'attitude', 'class_teacher_remarks', 'head_teacher_remarks',
        'school_fees_arrears', 'next_term_fee',
    ];

    public function __construct(private ExamService $exams) {}

    /** Exam details shown at the top of a report card. */
    public function header(Exam $exam): array
    {
        $exam->loadMissing(['schoolClass:id,name', 'term:id,name,start_date,end_date', 'academicYear:id,name']);

        return [
            'id' => $exam->id,
            'name' => $exam->name,
            'className' => $exam->schoolClass->name,
            'termName' => $exam->term?->name,
            'academicYear' => $exam->academicYear->name,
            'reportDate' => now()->toDateString(),
            'school' => $this->branding(),
        ];
    }

    /**
     * School identity for the report card header, from the school settings.
     * Falls back to the app name when no school name has been saved.
     * Pass $embedLogo for PDFs, which need the logo inlined as a data URI.
     *
     * @return array{name: string, motto: ?string, contact: ?string, logoUrl: ?string, logoDataUri: ?string}
     */
    public function branding(bool $embedLogo = false): array
    {
        // ASSUMPTION: school settings is a single row. Change this line if you load it another way
        // (e.g. a method on SchoolSettingsService).
        $settings = SchoolSetting::query()->first();
        $disk = Storage::disk('public');
        $hasLogo = $settings?->logo_path && $disk->exists($settings->logo_path);

        $dataUri = null;
        if ($hasLogo && $embedLogo) {
            $mime = $disk->mimeType($settings->logo_path);
            // DomPDF cannot render SVG/WebP reliably, so those fall back to name-only.
            if (in_array($mime, ['image/png', 'image/jpeg', 'image/gif'], true)) {
                $dataUri = 'data:'.$mime.';base64,'.base64_encode($disk->get($settings->logo_path));
            }
        }

        $contact = collect([$settings?->address, $settings?->phone, $settings?->email])
            ->filter()
            ->implode(' · ');

        return [
            'name' => $settings?->school_name ?: (string) config('app.name'),
            'motto' => $settings?->motto ?: null,
            'contact' => $contact !== '' ? $contact : null,
            'logoUrl' => $hasLogo ? $disk->url($settings->logo_path) : null,
            'logoDataUri' => $dataUri,
        ];
    }

    /**
     * One report card per student enrolled in the exam's class.
     * Exam score  = this exam's mark scaled to 70.
     * Class score = average % across the OTHER published exams of the same class and term, scaled to 30.
     */
    public function forExam(Exam $exam, ?int $onlyStudentId = null): Collection
    {
        $exam->loadMissing(['term', 'subjects.subject:id,name']);

        $roster = $this->exams->classRoster($exam->academic_year_id, $exam->class_id, $exam->end_date)
            ->load('student:id,first_name,middle_name,last_name,admission_number')
            ->filter(fn ($e) => $e->student)
            ->sortBy(fn ($e) => strtolower($e->student->last_name.' '.$e->student->first_name))
            ->values();

        $studentIds = $roster->pluck('student_id')->unique()->values();
        $examSubjectIds = $exam->subjects->pluck('id');

        // This exam's marks: [exam_subject_id][student_id] => ExamMark
        $marks = ExamMark::query()
            ->whereIn('exam_subject_id', $examSubjectIds)
            ->whereIn('student_id', $studentIds)
            ->whereNotNull('marks_obtained')
            ->get()
            ->groupBy('exam_subject_id')
            ->map(fn ($g) => $g->keyBy('student_id'));

        $classPct = $this->classAssessmentPercentages($exam, $studentIds);

        // Saved (manual) class scores override the auto-calculated ones: [student_id][subject_id] => score
        $saved = ClassScore::query()
            ->where('exam_id', $exam->id)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->groupBy('student_id')
            ->map(fn ($g) => $g->mapWithKeys(fn ($c) => [$c->subject_id => (float) $c->score]));

        // Totals used for ranking: [exam_subject_id][student_id] => total (out of 100)
        $totals = [];
        foreach ($exam->subjects as $es) {
            foreach ($marks->get($es->id, collect()) as $sid => $mark) {
                $totals[$es->id][$sid] = $this->scores($es, $mark, $classPct[$es->subject_id][$sid] ?? null, $saved->get($sid)?->get($es->subject_id))['total'];
            }
        }
        $overall = [];
        foreach ($totals as $byStudent) {
            foreach ($byStudent as $sid => $t) {
                $overall[$sid] = ($overall[$sid] ?? 0) + $t;
            }
        }

        $attendance = $this->attendance($exam, $studentIds);
        $reportDetails = ReportCardDetail::query()
            ->where('exam_id', $exam->id)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');
        $arrears = Invoice::query()
            ->whereIn('student_id', $studentIds)
            ->selectRaw('student_id, SUM(total_amount - paid_amount) AS owing')
            ->groupBy('student_id')
            ->pluck('owing', 'student_id');

        return $roster
            ->when($onlyStudentId, fn ($c) => $c->where('student_id', $onlyStudentId))
            ->map(function ($enrolment) use ($exam, $marks, $classPct, $saved, $totals, $overall, $attendance, $arrears, $reportDetails) {
                $s = $enrolment->student;
                $rows = $exam->subjects->map(function (ExamSubject $es) use ($s, $marks, $classPct, $saved, $totals) {
                    $mark = $marks->get($es->id)?->get($s->id);
                    if (! $mark) {
                        return ['subjectId' => $es->subject_id, 'subject' => $es->subject->name, 'classScore' => null, 'classSaved' => false, 'examScore' => null, 'total' => null, 'position' => null, 'remark' => null];
                    }
                    $t = $this->scores($es, $mark, $classPct[$es->subject_id][$s->id] ?? null, $saved->get($s->id)?->get($es->subject_id));

                    return [
                        'subjectId' => $es->subject_id,
                        'subject' => $es->subject->name,
                        'classScore' => $t['class'],
                        'classSaved' => $t['saved'],
                        'examScore' => $t['exam'],
                        'total' => $t['total'],
                        'position' => $this->ordinal($this->rank($totals[$es->id] ?? [], $s->id)),
                        'remark' => $this->remark($t['total']),
                    ];
                })->values();

                $att = $attendance->get($s->id);

                return [
                    'student' => [
                        'id' => $s->id,
                        'name' => trim(implode(' ', array_filter([$s->first_name, $s->middle_name, $s->last_name]))),
                        'admissionNumber' => $s->admission_number,
                    ],
                    'rows' => $rows,
                    'grandTotal' => round($rows->sum('total'), 1),
                    'overallPosition' => isset($overall[$s->id]) ? $this->ordinal($this->rank($overall, $s->id)) : null,
                    'classSize' => count($overall),
                    'details' => $this->details($exam, $reportDetails->get($s->id), $att, (float) ($arrears[$s->id] ?? 0)),
                ];
            })->values();
    }

    /** @return array<int, array<int, float>> [subject_id][student_id] => average percentage (0-100) */
    private function classAssessmentPercentages(Exam $exam, Collection $studentIds): array
    {
        $sources = ExamSubject::query()
            ->whereHas('exam', fn ($q) => $q
                ->where('class_id', $exam->class_id)
                ->where('academic_year_id', $exam->academic_year_id)
                ->where('status', 'published')
                ->where('id', '!=', $exam->id)
                ->when($exam->term_id, fn ($q) => $q->where('term_id', $exam->term_id), fn ($q) => $q->whereNull('term_id')))
            ->get()
            ->keyBy('id');

        if ($sources->isEmpty()) {
            return [];
        }

        $acc = [];
        ExamMark::query()
            ->whereIn('exam_subject_id', $sources->keys())
            ->whereIn('student_id', $studentIds)
            ->whereNotNull('marks_obtained')
            ->get()
            ->each(function ($m) use ($sources, &$acc) {
                $es = $sources[$m->exam_subject_id];
                if ((float) $es->max_marks > 0) {
                    $acc[$es->subject_id][$m->student_id][] = (float) $m->marks_obtained / (float) $es->max_marks * 100;
                }
            });

        return collect($acc)->map(fn ($byStudent) => collect($byStudent)->map(fn ($p) => array_sum($p) / count($p))->all())->all();
    }

    private function attendance(Exam $exam, Collection $studentIds): Collection
    {
        if (! $exam->term) {
            return collect();
        }

        // ASSUMPTION: statuses 'present' and 'late' count as attended. Adjust to your attendance statuses.
        return AttendanceRecord::query()
            ->join('attendance', 'attendance.id', '=', 'attendance_records.attendance_id')
            ->whereIn('attendance_records.student_id', $studentIds)
            ->whereBetween('attendance.attendance_date', [$exam->term->start_date->toDateString(), $exam->term->end_date->toDateString()])
            ->selectRaw("attendance_records.student_id, COUNT(*) AS total, SUM(CASE WHEN attendance_records.status IN ('present','late') THEN 1 ELSE 0 END) AS attended")
            ->groupBy('attendance_records.student_id')
            ->get()
            ->keyBy('student_id');
    }

    /**
     * Saved values win; otherwise fall back to what the system can work out
     * (term end date, attendance counts, unpaid invoice balance).
     */
    private function details(Exam $exam, ?ReportCardDetail $saved, $att, float $owing): array
    {
        $auto = max(0, round($owing, 2));

        return [
            'closing' => $saved?->closing_date?->toDateString() ?? $exam->term?->end_date?->toDateString(),
            'nextBegins' => $saved?->next_term_begins?->toDateString(),
            'nextEnds' => $saved?->next_term_ends?->toDateString(),
            'attendance' => $saved?->attendance ?? ($att ? (int) $att->attended : null),
            'outOf' => $saved?->attendance_out_of ?? ($att ? (int) $att->total : null),
            'conduct' => $saved?->conduct,
            'attitude' => $saved?->attitude,
            'teacherRemarks' => $saved?->class_teacher_remarks,
            'headRemarks' => $saved?->head_teacher_remarks,
            'arrears' => $saved?->school_fees_arrears ?? ($auto > 0 ? $auto : null),
            'nextFee' => $saved?->next_term_fee,
            'saved' => (bool) $saved,
        ];
    }

    private function scores(ExamSubject $es, ExamMark $mark, ?float $classPct, ?float $saved = null): array
    {
        $max = (float) $es->max_marks ?: 100;
        $exam = round((float) $mark->marks_obtained / $max * self::EXAM_WEIGHT, 1);
        $class = $saved !== null
            ? round($saved, 1)
            : ($classPct === null ? null : round($classPct / 100 * self::CLASS_WEIGHT, 1));

        return ['class' => $class, 'exam' => $exam, 'total' => round(($class ?? 0) + $exam, 1), 'saved' => $saved !== null];
    }

    /**
     * Save manual class scores (out of 30). A null score deletes the saved value so the
     * auto-calculated one applies again.
     *
     * @param  list<array{student_id: int, subject_id: int, score: float|int|string|null}>  $scores
     */
    public function saveClassScores(Exam $exam, array $scores, User $actor): void
    {
        $exam->loadMissing(['subjects', 'academicYear']);

        if ($exam->academicYear->status === AcademicYearStatus::Locked) {
            throw ValidationException::withMessages(['scores' => 'Class scores cannot be changed in a locked academic year.']);
        }

        $subjectIds = $exam->subjects->pluck('subject_id')->map(fn ($id): int => (int) $id);
        $studentIds = $this->exams->classRoster($exam->academic_year_id, $exam->class_id, $exam->end_date)
            ->pluck('student_id')->map(fn ($id): int => (int) $id);

        DB::transaction(function () use ($exam, $scores, $actor, $subjectIds, $studentIds): void {
            foreach ($scores as $row) {
                if (! $subjectIds->contains((int) $row['subject_id']) || ! $studentIds->contains((int) $row['student_id'])) {
                    throw ValidationException::withMessages(['scores' => 'A score refers to a student or subject that is not part of this examination.']);
                }

                $keys = ['exam_id' => $exam->id, 'subject_id' => $row['subject_id'], 'student_id' => $row['student_id']];

                if ($row['score'] === null || $row['score'] === '') {
                    ClassScore::query()->where($keys)->delete();

                    continue;
                }

                ClassScore::query()->updateOrCreate($keys, [
                    'score' => round((float) $row['score'], 2),
                    'updated_by' => $actor->id,
                ]);
            }
        });
    }

    /**
     * Save per-student report details. Only the keys present in a row are changed; a blank value
     * clears that field (so attendance, arrears and closing date fall back to the automatic value).
     *
     * @param  list<array<string, mixed>>  $cards  Each row has student_id plus any DETAIL_FIELDS.
     */
    public function saveDetails(Exam $exam, array $cards, User $actor): void
    {
        $exam->loadMissing('academicYear');

        if ($exam->academicYear->status === AcademicYearStatus::Locked) {
            throw ValidationException::withMessages(['cards' => 'Report details cannot be changed in a locked academic year.']);
        }

        $studentIds = $this->exams->classRoster($exam->academic_year_id, $exam->class_id, $exam->end_date)
            ->pluck('student_id')->map(fn ($id): int => (int) $id);

        DB::transaction(function () use ($exam, $cards, $actor, $studentIds): void {
            foreach ($cards as $row) {
                if (! $studentIds->contains((int) $row['student_id'])) {
                    throw ValidationException::withMessages(['cards' => 'A report card refers to a student who is not in this examination’s class.']);
                }

                $detail = ReportCardDetail::query()->firstOrNew(['exam_id' => $exam->id, 'student_id' => $row['student_id']]);

                foreach (self::DETAIL_FIELDS as $field) {
                    if (array_key_exists($field, $row)) {
                        $detail->{$field} = ($row[$field] === '' || $row[$field] === null) ? null : $row[$field];
                    }
                }

                if ($detail->next_term_begins && $detail->next_term_ends && $detail->next_term_ends->lt($detail->next_term_begins)) {
                    throw ValidationException::withMessages(['cards' => 'Next term must end on or after the day it begins.']);
                }
                if ($detail->attendance !== null && $detail->attendance_out_of !== null && $detail->attendance > $detail->attendance_out_of) {
                    throw ValidationException::withMessages(['cards' => 'Attendance obtained cannot be more than the attendance it is out of.']);
                }

                $hasValue = collect(self::DETAIL_FIELDS)->contains(fn ($f) => $detail->{$f} !== null);
                if (! $hasValue) {
                    if ($detail->exists) {
                        $detail->delete();
                    }

                    continue;
                }

                $detail->updated_by = $actor->id;
                $detail->save();
            }
        });
    }

    /** Ties share a position. */
    private function rank(array $scores, int $studentId): int
    {
        $mine = $scores[$studentId] ?? 0;

        return 1 + collect($scores)->filter(fn ($s) => $s > $mine)->count();
    }

    private function ordinal(int $n): string
    {
        $v = $n % 100;
        $suffix = ($v >= 11 && $v <= 13) ? 'th' : [1 => 'st', 2 => 'nd', 3 => 'rd'][$n % 10] ?? 'th';

        return $n.$suffix;
    }

    private function remark(float $total): string
    {
        return match (true) {
            $total >= 80 => 'Excellent',
            $total >= 70 => 'Very Good',
            $total >= 60 => 'Good',
            $total >= 50 => 'Credit',
            $total >= 40 => 'Pass',
            default => 'Needs Improvement',
        };
    }
}