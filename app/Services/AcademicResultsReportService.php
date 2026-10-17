<?php

namespace App\Services;

use App\Models\Exam;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class AcademicResultsReportService
{
    /**
     * @return array{
     *     candidateCount: int,
     *     assessedCandidateCount: int,
     *     averagePercentage: float,
     *     highestPercentage: float,
     *     passedAllSubjects: int
     * }
     */
    public function summary(Exam $exam): array
    {
        $summary = DB::query()
            ->fromSub($this->students($exam), 'student_results')
            ->selectRaw(
                'COUNT(*) AS candidate_count,
                COUNT(percentage) AS assessed_candidate_count,
                COALESCE(ROUND(AVG(percentage), 2), 0) AS average_percentage,
                COALESCE(MAX(percentage), 0) AS highest_percentage,
                COALESCE(SUM(CASE WHEN assessed_subjects > 0 AND passed_subjects = assessed_subjects THEN 1 ELSE 0 END), 0) AS passed_all_subjects',
            )
            ->first();

        return [
            'candidateCount' => (int) ($summary->candidate_count ?? 0),
            'assessedCandidateCount' => (int) ($summary->assessed_candidate_count ?? 0),
            'averagePercentage' => (float) ($summary->average_percentage ?? 0),
            'highestPercentage' => (float) ($summary->highest_percentage ?? 0),
            'passedAllSubjects' => (int) ($summary->passed_all_subjects ?? 0),
        ];
    }

    public function students(Exam $exam): Builder
    {
        $earnedMarks = "SUM(CASE WHEN exam_marks.status = 'present' THEN COALESCE(exam_marks.marks_obtained, 0) ELSE 0 END)";
        $maximumMarks = "SUM(CASE WHEN exam_marks.status != 'exempt' THEN exam_subjects.max_marks ELSE 0 END)";
        $assessedSubjects = "SUM(CASE WHEN exam_marks.status != 'exempt' THEN 1 ELSE 0 END)";
        $passedSubjects = "SUM(CASE WHEN exam_marks.status = 'present' AND exam_marks.marks_obtained >= exam_subjects.pass_marks THEN 1 ELSE 0 END)";

        return DB::table('exam_marks')
            ->join('exam_subjects', 'exam_subjects.id', '=', 'exam_marks.exam_subject_id')
            ->join('exams', 'exams.id', '=', 'exam_subjects.exam_id')
            ->join('students', 'students.id', '=', 'exam_marks.student_id')
            ->where('exams.id', $exam->id)
            ->where('exams.status', 'published')
            ->select([
                'students.id',
                'students.admission_number',
                'students.first_name',
                'students.middle_name',
                'students.last_name',
            ])
            ->selectRaw(
                "COALESCE($earnedMarks, 0) AS marks_obtained,
                COALESCE($maximumMarks, 0) AS maximum_marks,
                $assessedSubjects AS assessed_subjects,
                $passedSubjects AS passed_subjects,
                ROUND(100.0 * $earnedMarks / NULLIF($maximumMarks, 0), 2) AS percentage",
            )
            ->groupBy(
                'students.id',
                'students.admission_number',
                'students.first_name',
                'students.middle_name',
                'students.last_name',
            )
            ->orderByDesc('percentage')
            ->orderBy('students.last_name')
            ->orderBy('students.first_name')
            ->orderBy('students.id');
    }
}
