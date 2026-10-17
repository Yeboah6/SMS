<?php

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Enrolment;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentActivity;
use App\Models\Subject;
use App\Models\TeacherClassAssignment;
use App\Models\TeacherSubjectAssignment;
use App\Models\Term;
use App\Models\User;
use App\Services\AcademicYearService;
use App\Services\ExamService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * @return array{User, User, Staff, AcademicYear, SchoolClass, Section, Section, Subject}
 */
function createExamScenario(): array
{
    $principal = User::factory()->create();
    $principal->assignRole('Principal');
    $teacher = User::factory()->create(['name' => 'Avery Instructor']);
    $teacher->assignRole('Teacher');
    $staff = Staff::query()->create([
        'user_id' => $teacher->id,
        'staff_number' => 'EXAM-TEACHER-001',
        'is_teacher' => true,
        'name' => $teacher->name,
        'status' => 'active',
    ]);
    $year = app(AcademicYearService::class)->create([
        'name' => 'Exam Test Year',
        'start_date' => today()->subMonth()->toDateString(),
        'end_date' => today()->addMonths(6)->toDateString(),
    ]);
    app(AcademicYearService::class)->activate($year);
    $schoolClass = $year->classes()->create(['name' => 'Exam Class']);
    $firstSection = $schoolClass->sections()->create([
        'academic_year_id' => $year->id,
        'name' => 'A',
    ]);
    $secondSection = $schoolClass->sections()->create([
        'academic_year_id' => $year->id,
        'name' => 'B',
    ]);
    $subject = Subject::query()->create([
        'code' => 'EXAM-MATH',
        'name' => 'Mathematics',
    ]);
    ClassSubject::query()->create([
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
        'subject_id' => $subject->id,
    ]);
    foreach ([$firstSection, $secondSection] as $section) {
        TeacherClassAssignment::query()->create([
            'academic_year_id' => $year->id,
            'staff_id' => $staff->id,
            'section_id' => $section->id,
        ]);
        TeacherSubjectAssignment::query()->create([
            'academic_year_id' => $year->id,
            'staff_id' => $staff->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
        ]);
    }

    return [$principal, $teacher, $staff, $year, $schoolClass, $firstSection, $secondSection, $subject];
}

function createExamStudent(
    AcademicYear $year,
    SchoolClass $schoolClass,
    Section $section,
    string $admissionNumber,
): array {
    $user = User::factory()->create();
    $user->assignRole('Student');
    $student = Student::query()->create([
        'user_id' => $user->id,
        'admission_number' => $admissionNumber,
        'first_name' => 'Taylor',
        'last_name' => 'Learner',
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

    return [$user, $student];
}

/**
 * @param  list<array{subject_id: int, exam_date: string, max_marks: int|float, pass_marks: int|float}>  $subjects
 * @return array<string, mixed>
 */
function examPayload(AcademicYear $year, SchoolClass $schoolClass, array $subjects): array
{
    return [
        'academic_year_id' => $year->id,
        'term_id' => null,
        'class_id' => $schoolClass->id,
        'name' => 'Term Examination',
        'exam_type' => 'mid_term',
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDays(5)->toDateString(),
        'subjects' => $subjects,
    ];
}

function createExam(User $principal, AcademicYear $year, SchoolClass $schoolClass, Subject $subject): Exam
{
    $payload = examPayload($year, $schoolClass, [[
        'subject_id' => $subject->id,
        'exam_date' => today()->toDateString(),
        'max_marks' => 100,
        'pass_marks' => 40,
    ]]);

    return app(ExamService::class)->create($payload, $principal);
}

test('principals can create an academic-year examination with class subjects and scoring rules', function () {
    [$principal, , , $year, $schoolClass, , , $subject] = createExamScenario();
    $term = Term::query()->create([
        'academic_year_id' => $year->id,
        'name' => 'First Term',
        'start_date' => today()->subDays(2)->toDateString(),
        'end_date' => today()->addDays(7)->toDateString(),
    ]);
    $payload = examPayload($year, $schoolClass, [[
        'subject_id' => $subject->id,
        'exam_date' => today()->addDay()->toDateString(),
        'max_marks' => 100,
        'pass_marks' => 40,
    ]]);
    $payload['term_id'] = $term->id;

    $this->actingAs($principal)->post(route('exams.store'), $payload)
        ->assertRedirect()
        ->assertSessionHas('status', 'Examination created.');

    $this->assertDatabaseHas('exams', [
        'academic_year_id' => $year->id,
        'term_id' => $term->id,
        'class_id' => $schoolClass->id,
        'created_by' => $principal->id,
        'name' => 'Term Examination',
        'status' => 'draft',
    ]);
    $this->assertDatabaseHas('exam_subjects', [
        'subject_id' => $subject->id,
        'max_marks' => 100,
        'pass_marks' => 40,
    ]);
});

test('exam creation rejects subjects not assigned to the selected class and invalid pass marks', function () {
    [$principal, , , $year, $schoolClass, , , $subject] = createExamScenario();
    $unassignedSubject = Subject::query()->create([
        'code' => 'EXAM-SCIENCE',
        'name' => 'Science',
    ]);
    $invalidSubjectMarks = examPayload($year, $schoolClass, [[
        'subject_id' => $subject->id,
        'exam_date' => today()->toDateString(),
        'max_marks' => 20,
        'pass_marks' => 21,
    ]]);

    $this->actingAs($principal)->post(route('exams.store'), examPayload($year, $schoolClass, [[
        'subject_id' => $unassignedSubject->id,
        'exam_date' => today()->toDateString(),
        'max_marks' => 100,
        'pass_marks' => 40,
    ]]))->assertSessionHasErrors('subjects');

    $this->actingAs($principal)->post(route('exams.store'), $invalidSubjectMarks)
        ->assertSessionHasErrors('subjects.0.pass_marks');

    $this->assertDatabaseCount('exams', 0);
});

test('exam creation rejects a class or term from another year and dates outside the selected year', function () {
    [$principal, , , $year, $schoolClass, , , $subject] = createExamScenario();
    $otherYear = app(AcademicYearService::class)->create([
        'name' => 'Other Exam Year',
        'start_date' => today()->subYear()->toDateString(),
        'end_date' => today()->subMonths(7)->toDateString(),
    ]);
    $otherClass = $otherYear->classes()->create(['name' => 'Other Exam Class']);
    $otherTerm = $otherYear->terms()->create([
        'name' => 'Other Term',
        'start_date' => $otherYear->start_date->toDateString(),
        'end_date' => $otherYear->end_date->toDateString(),
    ]);
    $payload = examPayload($year, $schoolClass, [[
        'subject_id' => $subject->id,
        'exam_date' => today()->toDateString(),
        'max_marks' => 100,
        'pass_marks' => 40,
    ]]);

    $this->actingAs($principal)->post(route('exams.store'), [...$payload, 'class_id' => $otherClass->id])
        ->assertSessionHasErrors('class_id');
    $this->actingAs($principal)->post(route('exams.store'), [...$payload, 'term_id' => $otherTerm->id])
        ->assertSessionHasErrors('term_id');

    $outsideYear = examPayload($year, $schoolClass, [[
        'subject_id' => $subject->id,
        'exam_date' => $year->end_date->copy()->addDay()->toDateString(),
        'max_marks' => 100,
        'pass_marks' => 40,
    ]]);
    $outsideYear['end_date'] = $year->end_date->copy()->addDay()->toDateString();
    $this->actingAs($principal)->post(route('exams.store'), $outsideYear)
        ->assertSessionHasErrors('start_date');

    $this->assertDatabaseCount('exams', 0);
});

test('teachers can enter marks only for assigned sections and the full section roster', function () {
    [$principal, $teacher, , $year, $schoolClass, $sectionA, $sectionB, $subject] = createExamScenario();
    [, $studentA] = createExamStudent($year, $schoolClass, $sectionA, 'EXAM-STUDENT-001');
    [, $studentB] = createExamStudent($year, $schoolClass, $sectionB, 'EXAM-STUDENT-002');
    $exam = createExam($principal, $year, $schoolClass, $subject);
    $examSubject = $exam->subjects->first();

    $this->actingAs($teacher)->post(route('exams.marks.store', [$exam, $examSubject]), [
        'section_id' => $sectionA->id,
        'records' => [[
            'student_id' => $studentA->id,
            'status' => 'present',
            'marks_obtained' => 78,
        ]],
    ])->assertRedirect()
        ->assertSessionHas('status', 'Marks saved.');

    $this->actingAs($teacher)->post(route('exams.marks.store', [$exam, $examSubject]), [
        'section_id' => $sectionB->id,
        'records' => [[
            'student_id' => $studentB->id,
            'status' => 'present',
            'marks_obtained' => 80,
        ]],
    ])->assertRedirect()
        ->assertSessionHas('status', 'Marks saved.');

    $this->assertDatabaseCount('exam_marks', 2);
    $this->assertDatabaseHas('student_activities', [
        'student_id' => $studentA->id,
        'actor_id' => $teacher->id,
        'academic_year_id' => $year->id,
        'event_type' => 'exam.mark_recorded',
    ]);

    $this->actingAs($teacher)->post(route('exams.marks.store', [$exam, $examSubject]), [
        'section_id' => $sectionA->id,
        'records' => [],
    ])->assertSessionHasErrors('records');

    expect(ExamMark::query()->where('student_id', $studentA->id)->value('marks_obtained'))->toBe('78.00');
});

test('teachers cannot enter marks for an unassigned subject or change marks without update permission', function () {
    [$principal, $teacher, $staff, $year, $schoolClass, $section, , $subject] = createExamScenario();
    [, $student] = createExamStudent($year, $schoolClass, $section, 'EXAM-STUDENT-003');
    $exam = createExam($principal, $year, $schoolClass, $subject);
    $examSubject = $exam->subjects->first();
    $mark = ExamMark::query()->create([
        'exam_subject_id' => $examSubject->id,
        'student_id' => $student->id,
        'entered_by' => $principal->id,
        'marks_obtained' => 65,
        'status' => 'present',
    ]);
    $teacher->syncRoles([]);
    $teacher->givePermissionTo('marks.enter');

    $this->actingAs($teacher)->post(route('exams.marks.store', [$exam, $examSubject]), [
        'section_id' => $section->id,
        'records' => [[
            'student_id' => $student->id,
            'status' => 'present',
            'marks_obtained' => 80,
        ]],
    ])->assertForbidden();

    $teacher->givePermissionTo('marks.update');
    TeacherSubjectAssignment::query()->where('staff_id', $staff->id)->delete();

    $this->actingAs($teacher)->post(route('exams.marks.store', [$exam, $examSubject]), [
        'section_id' => $section->id,
        'records' => [[
            'student_id' => $student->id,
            'status' => 'present',
            'marks_obtained' => 80,
        ]],
    ])->assertNotFound();

    expect($mark->fresh()->marks_obtained)->toBe('65.00');
});

test('marks above the subject maximum are rejected without altering existing marks', function () {
    [$principal, $teacher, , $year, $schoolClass, $section, , $subject] = createExamScenario();
    [, $student] = createExamStudent($year, $schoolClass, $section, 'EXAM-STUDENT-004');
    $exam = createExam($principal, $year, $schoolClass, $subject);
    $examSubject = $exam->subjects->first();

    $this->actingAs($teacher)->post(route('exams.marks.store', [$exam, $examSubject]), [
        'section_id' => $section->id,
        'records' => [[
            'student_id' => $student->id,
            'status' => 'present',
            'marks_obtained' => 101,
        ]],
    ])->assertSessionHasErrors('records');

    $this->assertDatabaseCount('exam_marks', 0);
});

test('super administrators can enter marks without a teacher assignment', function () {
    [, , , $year, $schoolClass, $section, , $subject] = createExamScenario();
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('Super Admin');
    [, $student] = createExamStudent($year, $schoolClass, $section, 'EXAM-STUDENT-ADMIN');
    $exam = createExam($superAdmin, $year, $schoolClass, $subject);
    $examSubject = $exam->subjects->first();

    $this->actingAs($superAdmin)->post(route('exams.marks.store', [$exam, $examSubject]), [
        'section_id' => $section->id,
        'records' => [[
            'student_id' => $student->id,
            'status' => 'present',
            'marks_obtained' => 90,
        ]],
    ])->assertRedirect()
        ->assertSessionHas('status', 'Marks saved.');

    $this->assertDatabaseHas('exam_marks', [
        'exam_subject_id' => $examSubject->id,
        'student_id' => $student->id,
        'entered_by' => $superAdmin->id,
        'marks_obtained' => 90,
    ]);
});

test('principals can publish complete results and students can view only their own published marks', function () {
    [$principal, $teacher, , $year, $schoolClass, $section, , $subject] = createExamScenario();
    [, $otherStudent] = createExamStudent($year, $schoolClass, $section, 'EXAM-STUDENT-005');
    [$studentUser, $student] = createExamStudent($year, $schoolClass, $section, 'EXAM-STUDENT-006');
    $exam = createExam($principal, $year, $schoolClass, $subject);
    $draftPayload = examPayload($year, $schoolClass, [[
        'subject_id' => $subject->id,
        'exam_date' => today()->toDateString(),
        'max_marks' => 100,
        'pass_marks' => 40,
    ]]);
    $draftPayload['name'] = 'Unpublished Examination';
    app(ExamService::class)->create($draftPayload, $principal);
    $examSubject = $exam->subjects->first();

    $this->actingAs($principal)->post(route('exams.publish', $exam))
        ->assertSessionHasErrors('exam');
    expect($exam->fresh()->status)->toBe('draft');

    $this->actingAs($teacher)->post(route('exams.marks.store', [$exam, $examSubject]), [
        'section_id' => $section->id,
        'records' => [
            ['student_id' => $student->id, 'status' => 'present', 'marks_obtained' => 88],
            ['student_id' => $otherStudent->id, 'status' => 'present', 'marks_obtained' => 12],
        ],
    ])->assertRedirect();

    $this->actingAs($principal)->post(route('exams.publish', $exam))
        ->assertRedirect()
        ->assertSessionHas('status', 'Examination results published.');

    $this->assertDatabaseHas('student_activities', [
        'student_id' => $student->id,
        'actor_id' => $principal->id,
        'academic_year_id' => $year->id,
        'event_type' => 'exam.result_published',
    ]);
    $this->assertDatabaseHas('student_activities', [
        'student_id' => $otherStudent->id,
        'event_type' => 'exam.result_published',
    ]);

    SchoolSetting::query()->create([
        'singleton_key' => 'primary',
        'school_name' => 'Exam Grade Test School',
        'grade_settings' => ['bands' => [
            ['label' => 'Excellent', 'minimum_percentage' => 85],
            ['label' => 'Satisfactory', 'minimum_percentage' => 0],
        ]],
    ]);

    $this->actingAs($studentUser)->get(route('exams.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Exams/Index')
            ->where('mode', 'student')
            ->has('results.data', 1)
            ->where('results.data.0.name', 'Term Examination')
            ->where('results.data.0.totalMarks', 88)
            ->where('results.data.0.percentage', 88)
            ->where('results.data.0.grade', 'Excellent')
            ->where('results.data.0.result', 'pass')
            ->where('results.data.0.subjects.0.marksObtained', 88));

    $this->actingAs($principal)->get(route('exams.index', [
        'academic_year_id' => $year->id,
        'exam_id' => $exam->id,
    ]))
        ->assertInertia(fn ($page) => $page
            ->where('selectedExamStatus', 'published')
            ->where('canReopen', true));
});

test('parents can view published results only for linked children', function () {
    [$principal, , $teacherStaff, $year, $schoolClass, $section, , $subject] = createExamScenario();
    [, $unlinkedStudent] = createExamStudent($year, $schoolClass, $section, 'EXAM-PARENT-UNLINKED');
    [, $linkedStudent] = createExamStudent($year, $schoolClass, $section, 'EXAM-PARENT-LINKED');
    $parent = User::factory()->create();
    $parent->assignRole('Parent');
    $guardian = Guardian::query()->create([
        'user_id' => $parent->id,
        'name' => 'Linked Parent',
        'phone' => '555-0190',
    ]);
    $linkedStudent->guardians()->attach($guardian->id, [
        'relationship' => 'Parent',
        'is_primary' => true,
        'lives_with_student' => true,
    ]);

    $exam = createExam($principal, $year, $schoolClass, $subject);
    $examSubject = $exam->subjects->first();
    foreach ([[$linkedStudent, 87], [$unlinkedStudent, 14]] as [$student, $score]) {
        ExamMark::query()->create([
            'exam_subject_id' => $examSubject->id,
            'student_id' => $student->id,
            'entered_by' => $teacherStaff->user_id,
            'status' => 'present',
            'marks_obtained' => $score,
        ]);
    }
    $exam->update(['status' => 'published', 'published_at' => now()]);

    $this->actingAs($parent)->get(route('exams.index', ['student_id' => $linkedStudent->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Exams/Index')
            ->where('mode', 'parent')
            ->where('selectedStudentId', $linkedStudent->id)
            ->has('results.data', 1)
            ->where('results.data.0.subjects.0.marksObtained', 87));

    $this->actingAs($parent)->get(route('exams.index', ['student_id' => $unlinkedStudent->id]))
        ->assertNotFound();
});

test('exempt marks are excluded from the students total and percentage', function () {
    [$principal, $teacher, , $year, $schoolClass, $section, , $subject] = createExamScenario();
    [$studentUser, $student] = createExamStudent($year, $schoolClass, $section, 'EXAM-STUDENT-EXEMPT');
    $exam = createExam($principal, $year, $schoolClass, $subject);
    $examSubject = $exam->subjects->first();
    ExamMark::query()->create([
        'exam_subject_id' => $examSubject->id,
        'student_id' => $student->id,
        'entered_by' => $teacher->id,
        'status' => 'exempt',
    ]);

    $this->actingAs($principal)->post(route('exams.publish', $exam))
        ->assertRedirect()
        ->assertSessionHas('status', 'Examination results published.');

    $this->actingAs($studentUser)->get(route('exams.index'))
        ->assertInertia(fn ($page) => $page
            ->where('results.data.0.subjects.0.status', 'exempt')
            ->where('results.data.0.totalMarks', 0)
            ->where('results.data.0.maximumMarks', 0)
            ->where('results.data.0.percentage', null)
            ->where('results.data.0.grade', null)
            ->where('results.data.0.result', 'exempt'));
});

test('published marks are read-only until reopened by a user with the reopen permission', function () {
    [$principal, $teacher, , $year, $schoolClass, $section, , $subject] = createExamScenario();
    [, $student] = createExamStudent($year, $schoolClass, $section, 'EXAM-STUDENT-007');
    $exam = createExam($principal, $year, $schoolClass, $subject);
    $examSubject = $exam->subjects->first();
    ExamMark::query()->create([
        'exam_subject_id' => $examSubject->id,
        'student_id' => $student->id,
        'entered_by' => $teacher->id,
        'marks_obtained' => 55,
        'status' => 'present',
    ]);
    $exam->update([
        'status' => 'published',
        'published_by' => $principal->id,
        'published_at' => now(),
    ]);

    $this->actingAs($teacher)->post(route('exams.marks.store', [$exam, $examSubject]), [
        'section_id' => $section->id,
        'records' => [[
            'student_id' => $student->id,
            'status' => 'present',
            'marks_obtained' => 60,
        ]],
    ])->assertSessionHasErrors('records');

    $this->actingAs($principal)->post(route('exams.reopen', $exam))
        ->assertRedirect()
        ->assertSessionHas('status', 'Examination reopened for correction.');
    $this->assertDatabaseHas('student_activities', [
        'student_id' => $student->id,
        'actor_id' => $principal->id,
        'academic_year_id' => $year->id,
        'event_type' => 'exam.result_reopened',
    ]);

    $this->actingAs($teacher)->post(route('exams.marks.store', [$exam, $examSubject]), [
        'section_id' => $section->id,
        'records' => [[
            'student_id' => $student->id,
            'status' => 'present',
            'marks_obtained' => 60,
        ]],
    ])->assertRedirect();

    $this->assertDatabaseHas('exam_marks', [
        'id' => $examSubject->marks()->value('id'),
        'marks_obtained' => 60,
        'updated_by' => $teacher->id,
    ]);
    expect(StudentActivity::query()
        ->where('student_id', $student->id)
        ->where('event_type', 'exam.mark_updated')
        ->firstOrFail()
        ->details)
        ->toMatchArray([
            'previous_marks_obtained' => '55.00',
            'marks_obtained' => 60,
        ])
        ->not->toHaveKey('remarks');
    expect($exam->fresh()->status)->toBe('draft')
        ->and($exam->fresh()->reopened_by)->toBe($principal->id);
});

test('locked academic years reject mark changes, publication and reopening', function () {
    [$principal, $teacher, , $year, $schoolClass, $section, , $subject] = createExamScenario();
    [, $student] = createExamStudent($year, $schoolClass, $section, 'EXAM-STUDENT-008');
    $exam = createExam($principal, $year, $schoolClass, $subject);
    $examSubject = $exam->subjects->first();
    ExamMark::query()->create([
        'exam_subject_id' => $examSubject->id,
        'student_id' => $student->id,
        'entered_by' => $teacher->id,
        'marks_obtained' => 75,
        'status' => 'present',
    ]);
    $year->update(['status' => AcademicYearStatus::Locked]);

    $this->actingAs($teacher)->post(route('exams.marks.store', [$exam, $examSubject]), [
        'section_id' => $section->id,
        'records' => [[
            'student_id' => $student->id,
            'status' => 'present',
            'marks_obtained' => 80,
        ]],
    ])->assertSessionHasErrors('academic_year_id');

    $this->actingAs($principal)->post(route('exams.publish', $exam))
        ->assertSessionHasErrors('academic_year_id');

    $exam->update(['status' => 'published']);
    $this->actingAs($principal)->post(route('exams.reopen', $exam))
        ->assertSessionHasErrors('academic_year_id');

    expect($exam->fresh()->status)->toBe('published')
        ->and($examSubject->marks()->value('marks_obtained'))->toBe('75.00');
});

test('students cannot inspect examinations and guests must sign in', function () {
    $this->get(route('exams.index'))->assertRedirect(route('login'));

    $student = User::factory()->create();
    $this->actingAs($student)->get(route('exams.index'))->assertForbidden();
});
