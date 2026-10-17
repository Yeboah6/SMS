<?php

use App\Models\AcademicYear;
use App\Models\Enrolment;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\AcademicYearService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * @return array{User, AcademicYear, SchoolClass, Term, Exam, list<Student>}
 */
function createAcademicResultsReportScenario(): array
{
    $principal = User::factory()->create();
    $principal->assignRole('Principal');

    $year = app(AcademicYearService::class)->create([
        'name' => 'Academic Results Report Year',
        'start_date' => today()->subMonth()->toDateString(),
        'end_date' => today()->addMonths(6)->toDateString(),
    ]);
    app(AcademicYearService::class)->activate($year);

    $schoolClass = $year->classes()->create(['name' => 'Reports Grade Five']);
    $section = Section::query()->create([
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
        'name' => 'A',
    ]);
    $term = Term::query()->create([
        'academic_year_id' => $year->id,
        'name' => 'Reports Term One',
        'start_date' => $year->start_date->toDateString(),
        'end_date' => $year->end_date->toDateString(),
    ]);
    $exam = Exam::query()->create([
        'academic_year_id' => $year->id,
        'term_id' => $term->id,
        'class_id' => $schoolClass->id,
        'created_by' => $principal->id,
        'published_by' => $principal->id,
        'name' => 'Published Results',
        'exam_type' => 'end_term',
        'status' => 'published',
        'start_date' => today()->toDateString(),
        'end_date' => today()->toDateString(),
        'published_at' => now(),
    ]);
    $subjects = collect([
        ['code' => 'RPT-MATH', 'name' => 'Report Mathematics', 'pass_marks' => 50],
        ['code' => 'RPT-SCI', 'name' => 'Report Science', 'pass_marks' => 50],
        ['code' => 'RPT-ART', 'name' => 'Report Arts', 'pass_marks' => 40],
    ])->map(function (array $data) use ($exam): ExamSubject {
        $subject = Subject::query()->create([
            'code' => $data['code'],
            'name' => $data['name'],
        ]);

        return ExamSubject::query()->create([
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'max_marks' => 100,
            'pass_marks' => $data['pass_marks'],
            'exam_date' => today()->toDateString(),
        ]);
    });
    $students = collect([
        ['admission_number' => 'AR-001', 'first_name' => 'Ava', 'last_name' => 'Learner'],
        ['admission_number' => 'AR-002', 'first_name' => 'Ben', 'last_name' => 'Learner'],
        ['admission_number' => 'AR-003', 'first_name' => '=2+2', 'last_name' => 'Learner'],
        ['admission_number' => 'AR-004', 'first_name' => 'Dee', 'last_name' => 'Learner'],
    ])->map(function (array $data) use ($year, $schoolClass, $section): Student {
        $student = Student::query()->create([
            ...$data,
            'date_of_birth' => today()->subYears(12)->toDateString(),
            'gender' => 'prefer_not_to_say',
            'admission_date' => $year->start_date->toDateString(),
            'status' => 'active',
        ]);
        Enrolment::query()->create([
            'academic_year_id' => $year->id,
            'student_id' => $student->id,
            'class_id' => $schoolClass->id,
            'section_id' => $section->id,
            'enrolment_date' => $year->start_date->toDateString(),
            'status' => 'active',
        ]);

        return $student;
    });

    $marks = [
        [80, 45, 'exempt'],
        [75, 60, 'exempt'],
        [50, 'absent', 'exempt'],
        ['exempt', 'exempt', 'exempt'],
    ];

    foreach ($students as $studentIndex => $student) {
        foreach ($subjects as $subjectIndex => $examSubject) {
            $status = match ($marks[$studentIndex][$subjectIndex]) {
                'absent' => 'absent',
                'exempt' => 'exempt',
                default => 'present',
            };
            ExamMark::query()->create([
                'exam_subject_id' => $examSubject->id,
                'student_id' => $student->id,
                'entered_by' => $principal->id,
                'status' => $status,
                'marks_obtained' => $status === 'present' ? $marks[$studentIndex][$subjectIndex] : null,
            ]);
        }
    }

    return [$principal, $year, $schoolClass, $term, $exam, $students->all()];
}

test('principals can view published exam summaries with exempt and absent marks handled correctly', function () {
    [$principal, $year, $schoolClass, $term, $exam, $students] = createAcademicResultsReportScenario();
    $draftExam = Exam::query()->create([
        'academic_year_id' => $year->id,
        'term_id' => $term->id,
        'class_id' => $schoolClass->id,
        'created_by' => $principal->id,
        'name' => 'Unpublished Results',
        'exam_type' => 'mid_term',
        'status' => 'draft',
        'start_date' => today()->addDay()->toDateString(),
        'end_date' => today()->addDay()->toDateString(),
    ]);

    $this->actingAs($principal)->get(route('reports.academic.index', [
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
        'term_id' => $term->id,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Academic')
            ->where('selectedExamId', $exam->id)
            ->has('exams', 1)
            ->where('summary.candidateCount', 4)
            ->where('summary.assessedCandidateCount', 3)
            ->where('summary.averagePercentage', 51.67)
            ->where('summary.highestPercentage', 67.5)
            ->where('summary.passedAllSubjects', 1)
            ->where('students.total', 4)
            ->where('students.data.0.id', $students[1]->id)
            ->where('students.data.0.marksObtained', 135)
            ->where('students.data.0.maximumMarks', 200)
            ->where('students.data.0.percentage', 67.5)
            ->where('students.data.0.passedSubjects', 2)
            ->where('students.data.2.marksObtained', 50)
            ->where('students.data.2.maximumMarks', 200)
            ->where('students.data.2.percentage', 25)
            ->where('students.data.3.id', $students[3]->id)
            ->where('students.data.3.percentage', null)
            ->where('students.data.3.assessedSubjects', 0));

    expect($draftExam->status)->toBe('draft');
});

test('academic results filters reject an exam from another academic year', function () {
    [$principal, $year] = createAcademicResultsReportScenario();
    $otherYear = app(AcademicYearService::class)->create([
        'name' => 'Unrelated Results Year',
        'start_date' => today()->subYears(2)->toDateString(),
        'end_date' => today()->subYears(1)->toDateString(),
    ]);
    $otherClass = $otherYear->classes()->create(['name' => 'Unrelated Results Class']);
    $otherExam = Exam::query()->create([
        'academic_year_id' => $otherYear->id,
        'class_id' => $otherClass->id,
        'created_by' => $principal->id,
        'published_by' => $principal->id,
        'name' => 'Other Year Results',
        'exam_type' => 'end_term',
        'status' => 'published',
        'start_date' => $otherYear->start_date->toDateString(),
        'end_date' => $otherYear->end_date->toDateString(),
        'published_at' => now(),
    ]);

    $this->actingAs($principal)->get(route('reports.academic.index', [
        'academic_year_id' => $year->id,
        'exam_id' => $otherExam->id,
    ]))->assertNotFound();
});

test('users without result-view permission cannot access academic results reports', function () {
    $accountant = User::factory()->create();
    $accountant->assignRole('Accountant');

    $this->actingAs($accountant)->get(route('reports.academic.index'))->assertForbidden();
    $this->actingAs($accountant)->get(route('reports.academic.export'))->assertForbidden();
});

test('guests are redirected to sign in before viewing academic results', function () {
    $this->get(route('reports.academic.index'))->assertRedirect(route('login'));
});

test('principals can download a formula-safe academic results csv', function () {
    [$principal, $year, $schoolClass, $term, $exam] = createAcademicResultsReportScenario();

    $this->actingAs($principal)->get(route('reports.academic.export', [
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
        'term_id' => $term->id,
        'exam_id' => $exam->id,
    ]))
        ->assertDownload('academic-results-report-'.$year->start_date->year.'-'.$year->end_date->year.'.csv')
        ->assertStreamedContent(
            "\xEF\xBB\xBF\"Admission number\",Student,Class,Examination,\"Total marks\",\"Maximum marks\",Percentage,\"Subjects passed\",\"Subjects assessed\"\n".
            "AR-002,\"Ben Learner\",\"Reports Grade Five\",\"Published Results\",135.00,200.00,67.50,2,2\n".
            "AR-001,\"Ava Learner\",\"Reports Grade Five\",\"Published Results\",125.00,200.00,62.50,1,2\n".
            "AR-003,\"'=2+2 Learner\",\"Reports Grade Five\",\"Published Results\",50.00,200.00,25.00,1,2\n".
            "AR-004,\"Dee Learner\",\"Reports Grade Five\",\"Published Results\",0.00,0.00,,0,0\n",
        );
});

test('academic results filters validate identifier formats', function () {
    $principal = User::factory()->create();
    $principal->assignRole('Principal');

    $this->actingAs($principal)->get(route('reports.academic.index', [
        'academic_year_id' => 'not-a-year',
    ]))->assertSessionHasErrors('academic_year_id');
});
