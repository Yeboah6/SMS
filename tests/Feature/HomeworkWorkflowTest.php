<?php

use App\Models\AcademicYear;
use App\Models\Enrolment;
use App\Models\Guardian;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkSubmission;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentActivity;
use App\Models\Subject;
use App\Models\User;
use App\Services\AcademicStructureService;
use App\Services\AcademicYearService;
use App\Services\HomeworkService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * @return array{User, Staff, AcademicYear, SchoolClass, Section, Subject}
 */
function createHomeworkScenario(): array
{
    $teacher = User::factory()->create(['name' => 'Jordan Instructor']);
    $teacher->assignRole('Teacher');
    $staff = Staff::query()->create([
        'user_id' => $teacher->id,
        'staff_number' => 'HW-TEACHER-001',
        'is_teacher' => true,
        'name' => $teacher->name,
        'status' => 'active',
    ]);
    $year = app(AcademicYearService::class)->create([
        'name' => 'Homework Current Year',
        'start_date' => today()->subMonth()->toDateString(),
        'end_date' => today()->addMonths(6)->toDateString(),
    ]);
    app(AcademicYearService::class)->activate($year);
    $structure = app(AcademicStructureService::class);
    $schoolClass = $structure->createClass($year, ['name' => 'Homework Class']);
    $section = $structure->createSection($schoolClass, ['name' => 'A']);
    $subject = Subject::query()->create([
        'code' => 'HW-SUBJECT',
        'name' => 'Homework Subject',
    ]);
    $structure->syncClassSubjects($schoolClass, [$subject->id]);
    $structure->assignTeacherToSection($staff, $section);
    $structure->assignTeacherToSubject($staff, $section, $subject);

    return [$teacher, $staff, $year, $schoolClass, $section, $subject];
}

function createHomeworkStudent(Section $section, AcademicYear $year, string $admissionNumber): array
{
    $user = User::factory()->create();
    $user->assignRole('Student');
    $student = Student::query()->create([
        'user_id' => $user->id,
        'admission_number' => $admissionNumber,
        'first_name' => 'Sam',
        'last_name' => 'Learner',
        'date_of_birth' => today()->subYears(12)->toDateString(),
        'gender' => 'prefer_not_to_say',
        'admission_date' => $year->start_date->toDateString(),
        'status' => 'active',
    ]);
    Enrolment::query()->create([
        'academic_year_id' => $year->id,
        'student_id' => $student->id,
        'class_id' => $section->class_id,
        'section_id' => $section->id,
        'enrolment_date' => $year->start_date->toDateString(),
        'status' => 'active',
    ]);

    return [$user, $student];
}

function createHomeworkAssignment(User $teacher, Section $section, Subject $subject): HomeworkAssignment
{
    return app(HomeworkService::class)->create([
        'section_id' => $section->id,
        'subject_id' => $subject->id,
        'title' => 'Fractions practice',
        'instructions' => 'Solve the assigned fraction problems and show your work.',
        'assigned_date' => today()->toDateString(),
        'due_date' => today()->addDays(5)->toDateString(),
        'max_points' => 100,
    ], $teacher);
}

test('assigned teachers can publish homework and principals can view the assignment', function () {
    [$teacher, , , , $section, $subject] = createHomeworkScenario();

    $this->actingAs($teacher)->post(route('homework.store'), [
        'section_id' => $section->id,
        'subject_id' => $subject->id,
        'title' => 'Fractions practice',
        'instructions' => 'Solve the assigned fraction problems and show your work.',
        'assigned_date' => today()->toDateString(),
        'due_date' => today()->addDays(5)->toDateString(),
        'max_points' => 100,
    ])->assertRedirect()
        ->assertSessionHas('status', 'Homework assigned.');

    $this->assertDatabaseHas('homework_assignments', [
        'section_id' => $section->id,
        'subject_id' => $subject->id,
        'assigned_by' => $teacher->id,
        'title' => 'Fractions practice',
        'max_points' => 100,
    ]);

    $this->actingAs($teacher)->get(route('homework.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Homework/Index')
            ->where('assignments.total', 1)
            ->where('assignments.data.0.canGrade', true));

    $principal = User::factory()->create();
    $principal->assignRole('Principal');

    $this->actingAs($principal)->get(route('homework.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Homework/Index')
            ->where('assignments.total', 1)
            ->where('assignments.data.0.title', 'Fractions practice'));
});

test('teachers cannot create homework for sections or subjects outside their assignment', function () {
    [$teacher, , , , $section, $subject] = createHomeworkScenario();
    $unassignedTeacher = User::factory()->create();
    $unassignedTeacher->assignRole('Teacher');
    Staff::query()->create([
        'user_id' => $unassignedTeacher->id,
        'staff_number' => 'HW-TEACHER-002',
        'is_teacher' => true,
        'name' => $unassignedTeacher->name,
        'status' => 'active',
    ]);

    $this->actingAs($unassignedTeacher)->post(route('homework.store'), [
        'section_id' => $section->id,
        'subject_id' => $subject->id,
        'title' => 'Unauthorized work',
        'instructions' => 'This assignment must not be created for another teacher.',
        'assigned_date' => today()->toDateString(),
        'due_date' => today()->addDays(5)->toDateString(),
        'max_points' => 100,
    ])->assertNotFound();

    expect(HomeworkAssignment::query()->count())->toBe(0);
});

test('assigned teachers can update homework instructions and assignment dates', function () {
    [$teacher, , , , $section, $subject] = createHomeworkScenario();
    $assignment = createHomeworkAssignment($teacher, $section, $subject);

    $this->actingAs($teacher)->put(route('homework.update', $assignment), [
        'title' => 'Updated fractions practice',
        'instructions' => 'Complete the revised fraction problems and show each step.',
        'assigned_date' => today()->toDateString(),
        'due_date' => today()->addDays(10)->toDateString(),
        'max_points' => 80,
    ])->assertRedirect()
        ->assertSessionHas('status', 'Homework updated.');

    $this->assertDatabaseHas('homework_assignments', [
        'id' => $assignment->id,
        'title' => 'Updated fractions practice',
        'due_date' => today()->addDays(10)->startOfDay()->toDateTimeString(),
        'max_points' => 80,
    ]);
});

test('students see assignments for their enrolled section and may revise an ungraded submission before the due date', function () {
    [$teacher, , $year, , $section, $subject] = createHomeworkScenario();
    $assignment = createHomeworkAssignment($teacher, $section, $subject);
    [$studentUser, $student] = createHomeworkStudent($section, $year, 'HW-STUDENT-001');

    $this->actingAs($studentUser)->get(route('homework.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Homework/Index')
            ->where('isStudent', true)
            ->where('assignments.total', 1)
            ->where('assignments.data.0.canSubmit', true));

    $this->actingAs($studentUser)->post(route('homework.submissions.store', $assignment), [
        'content' => 'First attempt with the calculations shown.',
    ])->assertRedirect()
        ->assertSessionHas('status', 'Homework submitted.');

    $this->actingAs($studentUser)->post(route('homework.submissions.store', $assignment), [
        'content' => 'Revised work with corrected calculations.',
    ])->assertRedirect();

    $this->assertDatabaseHas('homework_submissions', [
        'homework_assignment_id' => $assignment->id,
        'student_id' => $student->id,
        'content' => 'Revised work with corrected calculations.',
        'score' => null,
    ]);
    $this->assertDatabaseCount('homework_submissions', 1);
    expect(StudentActivity::query()
        ->where('student_id', $student->id)
        ->orderBy('id')
        ->pluck('event_type')
        ->all())
        ->toBe(['homework.submitted', 'homework.resubmitted']);
    $submissionActivity = StudentActivity::query()
        ->where('student_id', $student->id)
        ->latest('id')
        ->firstOrFail();
    expect($submissionActivity->details)
        ->toMatchArray([
            'homework_assignment_id' => $assignment->id,
        ])
        ->not->toHaveKey('content')
        ->not->toHaveKey('feedback');
});

test('students cannot submit after the due date', function () {
    [$teacher, , $year, , $section, $subject] = createHomeworkScenario();
    $assignment = createHomeworkAssignment($teacher, $section, $subject);
    $assignment->update(['due_date' => today()->subDay()]);
    [$studentUser] = createHomeworkStudent($section, $year, 'HW-STUDENT-002');

    $this->actingAs($studentUser)->post(route('homework.submissions.store', $assignment), [
        'content' => 'A late submission.',
    ])->assertSessionHasErrors('content');

    $this->assertDatabaseCount('homework_submissions', 0);
});

test('students cannot view or submit homework for a different enrolled section', function () {
    [$teacher, , $year, $schoolClass, $section, $subject] = createHomeworkScenario();
    $assignment = createHomeworkAssignment($teacher, $section, $subject);
    $otherSection = app(AcademicStructureService::class)->createSection($schoolClass, ['name' => 'B']);
    [$studentUser] = createHomeworkStudent($otherSection, $year, 'HW-STUDENT-003');

    $this->actingAs($studentUser)->get(route('homework.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('assignments.total', 0));

    $this->actingAs($studentUser)->post(route('homework.submissions.store', $assignment), [
        'content' => 'Attempting to submit to another section.',
    ])->assertNotFound();

    $this->assertDatabaseCount('homework_submissions', 0);
});

test('assigned teachers can grade submissions but scores cannot exceed the assignment maximum', function () {
    [$teacher, , $year, , $section, $subject] = createHomeworkScenario();
    $assignment = createHomeworkAssignment($teacher, $section, $subject);
    [$studentUser, $student] = createHomeworkStudent($section, $year, 'HW-STUDENT-004');
    $this->actingAs($studentUser)->post(route('homework.submissions.store', $assignment), [
        'content' => 'My completed work.',
    ])->assertRedirect();
    $submission = HomeworkSubmission::query()->firstOrFail();

    $this->actingAs($teacher)->patch(route('homework.submissions.grade', [$assignment, $submission]), [
        'score' => 90,
        'feedback' => 'Good work.',
    ])->assertRedirect()
        ->assertSessionHas('status', 'Homework graded.');

    $this->assertDatabaseHas('homework_submissions', [
        'id' => $submission->id,
        'student_id' => $student->id,
        'score' => 90,
        'feedback' => 'Good work.',
        'graded_by' => $teacher->id,
    ]);
    $gradeActivity = StudentActivity::query()
        ->where('student_id', $student->id)
        ->where('event_type', 'homework.graded')
        ->firstOrFail();
    expect($gradeActivity->details)
        ->toMatchArray([
            'homework_submission_id' => $submission->id,
            'score' => 90,
            'max_points' => '100.00',
        ])
        ->not->toHaveKey('feedback')
        ->not->toHaveKey('content');

    $this->actingAs($teacher)->patch(route('homework.submissions.grade', [$assignment, $submission]), [
        'score' => 101,
    ])->assertSessionHasErrors('score');

    expect($submission->fresh()->score)->toBe('90.00');

    $this->actingAs($teacher)->put(route('homework.update', $assignment), [
        'title' => $assignment->title,
        'instructions' => $assignment->instructions,
        'assigned_date' => $assignment->assigned_date->toDateString(),
        'due_date' => $assignment->due_date->toDateString(),
        'max_points' => 80,
    ])->assertSessionHasErrors('max_points');

    expect($assignment->fresh()->max_points)->toBe('100.00');
});

test('locked academic years reject homework submissions', function () {
    [$teacher, , $year, , $section, $subject] = createHomeworkScenario();
    $assignment = createHomeworkAssignment($teacher, $section, $subject);
    [$studentUser] = createHomeworkStudent($section, $year, 'HW-STUDENT-005');
    $year->update(['status' => 'locked']);

    $this->actingAs($studentUser)->post(route('homework.submissions.store', $assignment), [
        'content' => 'This cannot be submitted after the year is locked.',
    ])->assertSessionHasErrors('content');

    $this->assertDatabaseCount('homework_submissions', 0);
});

test('guests are redirected to sign in before opening homework', function () {
    $this->get(route('homework.index'))->assertRedirect(route('login'));
    $this->post(route('homework.store'), [])->assertRedirect(route('login'));
});

test('parents can view linked children homework without seeing other section submissions', function () {
    [$teacher, , $year, , $section, $subject] = createHomeworkScenario();
    $assignment = createHomeworkAssignment($teacher, $section, $subject);
    [$linkedChildUser, $linkedChild] = createHomeworkStudent($section, $year, 'HW-PARENT-LINKED');
    [$unlinkedStudentUser, $unlinkedStudent] = createHomeworkStudent($section, $year, 'HW-PARENT-UNLINKED');
    $parent = User::factory()->create();
    $parent->assignRole('Parent');
    $guardian = Guardian::query()->create([
        'user_id' => $parent->id,
        'name' => 'Homework Parent',
        'phone' => '555-0188',
    ]);
    $linkedChild->guardians()->attach($guardian->id, [
        'relationship' => 'Parent',
        'is_primary' => true,
        'lives_with_student' => true,
    ]);

    foreach ([[$linkedChild, $linkedChildUser, 'Child homework'], [$unlinkedStudent, $unlinkedStudentUser, 'Private peer homework']] as [$student, $submitter, $content]) {
        HomeworkSubmission::query()->create([
            'homework_assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'submitted_by' => $submitter?->id,
            'content' => $content,
            'submitted_at' => now(),
        ]);
    }

    $this->actingAs($parent)->get(route('homework.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Homework/Index')
            ->where('isParent', true)
            ->where('assignments.total', 1)
            ->where('assignments.data.0.childSubmissions.0.studentName', 'Sam Learner')
            ->where('assignments.data.0.childSubmissions.0.content', 'Child homework')
            ->where('assignments.data.0.submissions', []));

    $this->actingAs($parent)->get(route('homework.index', ['student_id' => $unlinkedStudent->id]))
        ->assertNotFound();
});
