<?php

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\AttendanceRecord;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkSubmission;
use App\Models\Invoice;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\AcademicStructureService;
use App\Services\AcademicYearService;
use App\Services\StudentProfileService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function createPhaseFiveStudent(string $admissionNumber, ?string $guardianEmail = null): Student
{
    return app(StudentProfileService::class)->create([
        'admission_number' => $admissionNumber,
        'first_name' => 'Jordan',
        'middle_name' => null,
        'last_name' => 'Learner',
        'date_of_birth' => today()->subYears(10)->toDateString(),
        'gender' => 'prefer_not_to_say',
        'blood_group' => null,
        'religion' => null,
        'address' => null,
        'admission_date' => today()->toDateString(),
        'status' => 'active',
        'guardians' => [[
            'name' => 'Casey Guardian',
            'phone' => '555-0198',
            'email' => $guardianEmail,
            'relationship' => 'Parent',
            'is_primary' => true,
            'lives_with_student' => true,
        ]],
    ]);
}

function createPhaseFivePrincipal(): User
{
    $principal = User::factory()->create();
    $principal->assignRole('Principal');

    return $principal;
}

/**
 * @return array{AcademicYear, SchoolClass, Section, Subject}
 */
function createPhaseFiveAcademicPlacement(Student $student): array
{
    $year = app(AcademicYearService::class)->create([
        'name' => 'Phase Five Year',
        'start_date' => today()->subMonth()->toDateString(),
        'end_date' => today()->addMonths(8)->toDateString(),
    ]);
    app(AcademicYearService::class)->activate($year);

    $structure = app(AcademicStructureService::class);
    $schoolClass = $structure->createClass($year, ['name' => 'Phase Five Class']);
    $section = $structure->createSection($schoolClass, ['name' => 'A']);
    $subject = Subject::query()->create(['code' => 'PHASE-FIVE', 'name' => 'General Studies']);
    app(StudentProfileService::class)->enrol($student, [
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
        'section_id' => $section->id,
        'enrolment_date' => today()->subMonth()->toDateString(),
    ]);

    return [$year, $schoolClass, $section, $subject];
}

test('student profiles expose paginated academic records and only published examination results', function () {
    $principal = createPhaseFivePrincipal();
    $principal->assignRole('Accountant');
    $student = createPhaseFiveStudent('PHASE5-PROFILE-001');
    [$year, $schoolClass, $section, $subject] = createPhaseFiveAcademicPlacement($student);

    $attendance = Attendance::query()->create([
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'attendance_date' => today()->subDays(2)->toDateString(),
        'marked_by' => $principal->id,
    ]);
    AttendanceRecord::query()->create([
        'attendance_id' => $attendance->id,
        'student_id' => $student->id,
        'enrolment_id' => $student->enrolments()->firstOrFail()->id,
        'status' => 'present',
        'remarks' => 'On time',
    ]);

    $publishedExam = Exam::query()->create([
        'academic_year_id' => $year->id,
        'term_id' => null,
        'class_id' => $schoolClass->id,
        'created_by' => $principal->id,
        'published_by' => $principal->id,
        'name' => 'Published Midterm',
        'exam_type' => 'mid_term',
        'status' => 'published',
        'start_date' => today()->subDays(3)->toDateString(),
        'end_date' => today()->subDays(2)->toDateString(),
        'published_at' => now(),
    ]);
    $publishedSubject = ExamSubject::query()->create([
        'exam_id' => $publishedExam->id,
        'subject_id' => $subject->id,
        'max_marks' => 100,
        'pass_marks' => 40,
        'exam_date' => today()->subDays(3)->toDateString(),
    ]);
    ExamMark::query()->create([
        'exam_subject_id' => $publishedSubject->id,
        'student_id' => $student->id,
        'entered_by' => $principal->id,
        'marks_obtained' => 86,
        'status' => 'present',
    ]);

    $draftExam = Exam::query()->create([
        'academic_year_id' => $year->id,
        'term_id' => null,
        'class_id' => $schoolClass->id,
        'created_by' => $principal->id,
        'name' => 'Unpublished Draft',
        'exam_type' => 'final',
        'status' => 'draft',
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDay()->toDateString(),
    ]);
    $draftSubject = ExamSubject::query()->create([
        'exam_id' => $draftExam->id,
        'subject_id' => $subject->id,
        'max_marks' => 100,
        'pass_marks' => 40,
        'exam_date' => today()->toDateString(),
    ]);
    ExamMark::query()->create([
        'exam_subject_id' => $draftSubject->id,
        'student_id' => $student->id,
        'entered_by' => $principal->id,
        'marks_obtained' => 99,
        'status' => 'present',
    ]);

    Invoice::query()->create([
        'academic_year_id' => $year->id,
        'student_id' => $student->id,
        'invoice_number' => 'PHASE5-INV-001',
        'status' => 'partially_paid',
        'total_amount' => 250,
        'paid_amount' => 50,
        'issued_at' => today()->subDays(5)->toDateString(),
        'due_date' => today()->addDays(10)->toDateString(),
    ]);
    $assignment = HomeworkAssignment::query()->create([
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'subject_id' => $subject->id,
        'assigned_by' => $principal->id,
        'title' => 'Reading assignment',
        'instructions' => 'Read the assigned pages.',
        'assigned_date' => today()->subDays(4)->toDateString(),
        'due_date' => today()->addDays(2)->toDateString(),
        'max_points' => 20,
    ]);
    HomeworkSubmission::query()->create([
        'homework_assignment_id' => $assignment->id,
        'student_id' => $student->id,
        'submitted_by' => $principal->id,
        'content' => 'Completed reading.',
        'submitted_at' => now(),
    ]);

    $this->actingAs($principal)->get(route('students.show', $student))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canViewAttendance', true)
            ->where('attendanceRecords.data.0.status', 'present')
            ->where('canViewResults', true)
            ->where('examMarks.total', 1)
            ->where('examMarks.data.0.exam', 'Published Midterm')
            ->where('canViewFees', true)
            ->where('invoices.data.0.number', 'PHASE5-INV-001')
            ->where('canViewHomework', true)
            ->where('homeworkSubmissions.data.0.title', 'Reading assignment')
            ->where('history.0.type', 'Homework'));
});

test('staff can explicitly link and unlink only an existing Parent account', function () {
    $principal = createPhaseFivePrincipal();
    $parent = User::factory()->create(['email' => 'linked-parent@example.test']);
    $parent->assignRole('Parent');
    $notParent = User::factory()->create();
    $student = createPhaseFiveStudent('PHASE5-LINK-001', $parent->email);
    $guardian = $student->guardians()->firstOrFail();

    $this->actingAs($principal)->post(route('students.guardians.account.link', [$student, $guardian]), [
        'user_id' => $parent->id,
    ])->assertRedirect()
        ->assertSessionHas('status', 'Guardian portal account linked.');

    expect($guardian->fresh()->user_id)->toBe($parent->id);

    $this->actingAs($principal)->delete(route('students.guardians.account.unlink', [$student, $guardian]))
        ->assertRedirect()
        ->assertSessionHas('status', 'Guardian portal account unlinked.');

    expect($guardian->fresh()->user_id)->toBeNull()
        ->and(User::query()->find($parent->id))->not->toBeNull();

    $this->actingAs($principal)->post(route('students.guardians.account.link', [$student, $guardian]), [
        'user_id' => $notParent->id,
    ])->assertSessionHasErrors('user_id');

    expect($guardian->fresh()->user_id)->toBeNull();

    $otherStudent = createPhaseFiveStudent('PHASE5-LINK-002');
    $otherGuardian = $otherStudent->guardians()->firstOrFail();
    $this->actingAs($principal)->post(route('students.guardians.account.link', [$student, $otherGuardian]), [
        'user_id' => $parent->id,
    ])->assertNotFound();

    $teacher = User::factory()->create();
    $teacher->assignRole('Teacher');
    $this->actingAs($teacher)->post(route('students.guardians.account.link', [$student, $guardian]), [
        'user_id' => $parent->id,
    ])->assertForbidden();
});

test('guardian invitation creates a Parent account with a password setup notification and does not auto-link matching email', function () {
    Notification::fake();
    $principal = createPhaseFivePrincipal();
    $existingParent = User::factory()->create(['email' => 'Existing-Parent@example.test']);
    $existingParent->assignRole('Parent');
    $student = createPhaseFiveStudent('PHASE5-INVITE-001', 'existing-parent@example.test');
    $guardian = $student->guardians()->firstOrFail();

    expect($guardian->user_id)->toBeNull();

    $this->actingAs($principal)->post(route('students.guardians.account.invite', [$student, $guardian]))
        ->assertSessionHasErrors('email');

    expect($guardian->fresh()->user_id)->toBeNull();

    $invitationStudent = createPhaseFiveStudent('PHASE5-INVITE-002', 'new-parent@example.test');
    $invitationGuardian = $invitationStudent->guardians()->firstOrFail();

    $this->actingAs($principal)->post(route('students.guardians.account.invite', [$invitationStudent, $invitationGuardian]))
        ->assertRedirect()
        ->assertSessionHas('status', 'Parent account created and password setup email sent.');

    $invitedUser = User::query()->where('email', 'new-parent@example.test')->firstOrFail();
    expect($invitationGuardian->fresh()->user_id)->toBe($invitedUser->id)
        ->and($invitedUser->hasRole('Parent'))->toBeTrue();
    Notification::assertSentTo($invitedUser, ResetPassword::class);
});

test('student ID cards download as PDFs and remain scoped to visible student records', function () {
    Storage::fake('local');
    $principal = createPhaseFivePrincipal();
    $student = createPhaseFiveStudent('PHASE5-CARD-001');

    $response = $this->actingAs($principal)->get(route('students.id-card', $student))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename=student-id-PHASE5-CARD-001.pdf');
    expect(substr($response->getContent(), 0, 5))->toBe('%PDF-');

    $teacher = User::factory()->create();
    $teacher->assignRole('Teacher');
    $this->actingAs($teacher)->get(route('students.id-card', $student))->assertNotFound();
});
