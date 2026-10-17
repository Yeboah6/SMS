<?php

use App\Models\AcademicYear;
use App\Models\Admission;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Enrolment;
use App\Models\FeePayment;
use App\Models\Guardian;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkSubmission;
use App\Models\Invoice;
use App\Models\LibraryLoan;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherClassAssignment;
use App\Models\TeacherSubjectAssignment;
use App\Models\User;
use App\Services\AcademicYearService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function createDashboardAcademicYear(): AcademicYear
{
    $year = app(AcademicYearService::class)->create([
        'name' => 'Dashboard Academic Year',
        'start_date' => today()->subMonth()->toDateString(),
        'end_date' => today()->addMonths(6)->toDateString(),
    ]);

    app(AcademicYearService::class)->activate($year);

    return $year;
}

/**
 * @return array{Student, SchoolClass, Section}
 */
function createDashboardStudent(User $user, AcademicYear $year, string $admissionNumber): array
{
    $schoolClass = $year->classes()->create(['name' => 'Dashboard Class '.$admissionNumber]);
    $section = Section::query()->create([
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
        'name' => 'Section '.$admissionNumber,
    ]);
    $student = Student::query()->create([
        'user_id' => $user->id,
        'admission_number' => $admissionNumber,
        'first_name' => 'Dashboard',
        'last_name' => 'Learner',
        'date_of_birth' => '2015-01-01',
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

    return [$student, $schoolClass, $section];
}

test('school leaders see current operational counts and permission-gated shortcuts', function () {
    $year = createDashboardAcademicYear();
    $principal = User::factory()->create();
    $principal->assignRole('Principal');
    User::factory()->count(2)->create();
    Staff::query()->create([
        'name' => 'Dashboard Staff Member',
        'email' => 'staff-dashboard@example.test',
        'staff_number' => 'DASH-STAFF-001',
        'joining_date' => $year->start_date->toDateString(),
        'status' => 'active',
    ]);
    Admission::query()->create([
        'application_number' => 'DASH-APP-001',
        'parent_name' => 'Guardian Name',
        'parent_phone' => '555-0111',
        'child_first_name' => 'Applicant',
        'child_last_name' => 'Candidate',
        'date_of_birth' => '2018-01-01',
        'gender' => 'prefer_not_to_say',
        'applying_for' => 'Dashboard Class',
        'source' => 'walk_in',
        'status' => 'under_review',
        'created_by' => $principal->id,
    ]);

    $this->actingAs($principal)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('dashboard.metrics.0.label', 'Active students')
            ->where('dashboard.metrics.0.value', 0)
            ->where('dashboard.metrics.1.label', 'Active staff')
            ->where('dashboard.metrics.1.value', 1)
            ->where('dashboard.metrics.2.label', 'Admissions to review')
            ->where('dashboard.metrics.2.value', 1)
            ->where('dashboard.actions.0.href', '/students')
            ->where('dashboard.academicYear', $year->name));
});

test('student dashboards show only their active-year homework and unpaid invoices', function () {
    $year = createDashboardAcademicYear();
    $studentUser = User::factory()->create();
    $studentUser->assignRole('Student');
    [$student, , $section] = createDashboardStudent($studentUser, $year, 'DASH-STU-001');
    $otherStudent = User::factory()->create();
    [$unrelatedStudent, , $unrelatedSection] = createDashboardStudent($otherStudent, $year, 'DASH-STU-002');
    $subject = Subject::query()->create(['code' => 'DASH', 'name' => 'Dashboard Subject']);

    HomeworkAssignment::query()->create([
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'subject_id' => $subject->id,
        'assigned_by' => $studentUser->id,
        'title' => 'Current homework',
        'instructions' => 'Complete the assigned work.',
        'assigned_date' => today()->toDateString(),
        'due_date' => today()->addDay()->toDateString(),
        'max_points' => 10,
    ]);
    HomeworkAssignment::query()->create([
        'academic_year_id' => $year->id,
        'section_id' => $unrelatedSection->id,
        'subject_id' => $subject->id,
        'assigned_by' => $studentUser->id,
        'title' => 'Unrelated homework',
        'instructions' => 'This is for another student.',
        'assigned_date' => today()->toDateString(),
        'due_date' => today()->addDay()->toDateString(),
        'max_points' => 10,
    ]);

    $studentInvoice = Invoice::query()->create([
        'academic_year_id' => $year->id,
        'student_id' => $student->id,
        'invoice_number' => 'DASH-INV-001',
        'total_amount' => 100,
        'paid_amount' => 25,
        'issued_at' => today()->toDateString(),
        'due_date' => today()->addMonth()->toDateString(),
    ]);
    Invoice::query()->create([
        'academic_year_id' => $year->id,
        'student_id' => $unrelatedStudent->id,
        'invoice_number' => 'DASH-INV-002',
        'total_amount' => 100,
        'paid_amount' => 0,
        'issued_at' => today()->toDateString(),
        'due_date' => today()->addMonth()->toDateString(),
    ]);
    $this->actingAs($studentUser)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('dashboard.metrics.0.label', 'My profile')
            ->where('dashboard.metrics.0.value', 1)
            ->where('dashboard.metrics.1.label', 'Homework due')
            ->where('dashboard.metrics.1.value', 1)
            ->where('dashboard.metrics.2.label', 'Outstanding invoices')
            ->where('dashboard.metrics.2.value', 1)
            ->where('dashboard.metrics.3.label', 'Current balance')
            ->where('dashboard.metrics.3.value', 75));

    expect($studentInvoice->fresh()->paid_amount)->toBe('25.00');
});

test('parent dashboards are limited to students linked to the signed-in guardian', function () {
    $year = createDashboardAcademicYear();
    $parent = User::factory()->create(['email' => 'guardian-dashboard@example.test']);
    $parent->assignRole('Parent');
    [$linkedStudent, , $linkedSection] = createDashboardStudent(User::factory()->create(), $year, 'DASH-CHILD-001');
    [$unlinkedStudent] = createDashboardStudent(User::factory()->create(), $year, 'DASH-CHILD-002');
    $guardian = Guardian::query()->create([
        'user_id' => $parent->id,
        'name' => 'Dashboard Guardian',
        'email' => $parent->email,
        'phone' => '555-0112',
    ]);
    $linkedStudent->guardians()->attach($guardian, [
        'relationship' => 'parent',
        'is_primary' => true,
        'lives_with_student' => true,
    ]);
    $emailOnlyParent = User::factory()->create(['email' => 'email-only-guardian@example.test']);
    $emailOnlyParent->assignRole('Parent');
    $emailOnlyGuardian = Guardian::query()->create([
        'name' => 'Email Only Guardian',
        'email' => $emailOnlyParent->email,
        'phone' => '555-0113',
    ]);
    $unlinkedStudent->guardians()->attach($emailOnlyGuardian, [
        'relationship' => 'parent',
        'is_primary' => true,
        'lives_with_student' => true,
    ]);
    $subject = Subject::query()->create(['code' => 'DASH-PARENT', 'name' => 'Dashboard Parent Homework']);
    HomeworkAssignment::query()->create([
        'academic_year_id' => $year->id,
        'section_id' => $linkedSection->id,
        'subject_id' => $subject->id,
        'assigned_by' => User::factory()->create()->id,
        'title' => 'Linked child homework',
        'instructions' => 'Complete this work.',
        'assigned_date' => today()->toDateString(),
        'due_date' => today()->addDay()->toDateString(),
        'max_points' => 10,
    ]);
    Invoice::query()->create([
        'academic_year_id' => $year->id,
        'student_id' => $linkedStudent->id,
        'invoice_number' => 'DASH-PAR-001',
        'total_amount' => 100,
        'paid_amount' => 20,
        'issued_at' => today()->toDateString(),
        'due_date' => today()->addMonth()->toDateString(),
    ]);
    Invoice::query()->create([
        'academic_year_id' => $year->id,
        'student_id' => $unlinkedStudent->id,
        'invoice_number' => 'DASH-PAR-002',
        'total_amount' => 100,
        'paid_amount' => 0,
        'issued_at' => today()->toDateString(),
        'due_date' => today()->addMonth()->toDateString(),
    ]);

    $this->actingAs($parent)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('dashboard.metrics.0.label', 'Linked children')
            ->where('dashboard.metrics.0.value', 1)
            ->where('dashboard.metrics.1.label', 'Homework due')
            ->where('dashboard.metrics.1.value', 1)
            ->where('dashboard.metrics.2.label', 'Outstanding invoices')
            ->where('dashboard.metrics.2.value', 1)
            ->where('dashboard.metrics.3.label', 'Current balance')
            ->where('dashboard.metrics.3.value', 80)
            ->has('dashboard.actions', 6)
            ->where('dashboard.actions.0.href', '/timetable')
            ->where('dashboard.actions.1.href', '/fees')
            ->where('dashboard.actions.2.href', '/library')
            ->where('dashboard.actions.3.href', '/notices')
            ->where('dashboard.actions.4.href', '/certificates')
            ->where('dashboard.actions.5.href', '/leave-requests'));

    $this->actingAs($emailOnlyParent)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('dashboard.metrics.0.label', 'Linked children')
            ->where('dashboard.metrics.0.value', 0)
            ->where('dashboard.metrics.1.value', 0)
            ->where('dashboard.metrics.2.value', 0)
            ->where('dashboard.metrics.3.value', 0));
});

test('teacher dashboards count only assigned students, sections, and ungraded submissions', function () {
    $year = createDashboardAcademicYear();
    $teacher = User::factory()->create();
    $teacher->assignRole('Teacher');
    $staff = Staff::query()->create([
        'user_id' => $teacher->id,
        'name' => 'Dashboard Teacher',
        'staff_number' => 'DASH-TEACHER-001',
        'is_teacher' => true,
        'status' => 'active',
    ]);
    [$student, , $assignedSection] = createDashboardStudent(User::factory()->create(), $year, 'DASH-ASSIGNED-001');
    [, , $otherSection] = createDashboardStudent(User::factory()->create(), $year, 'DASH-UNASSIGNED-002');
    TeacherClassAssignment::query()->create([
        'academic_year_id' => $year->id,
        'staff_id' => $staff->id,
        'section_id' => $assignedSection->id,
    ]);
    $subject = Subject::query()->create(['code' => 'DASH-TEACH', 'name' => 'Dashboard Teaching']);
    TeacherSubjectAssignment::query()->create([
        'academic_year_id' => $year->id,
        'staff_id' => $staff->id,
        'section_id' => $assignedSection->id,
        'subject_id' => $subject->id,
    ]);
    $assignment = HomeworkAssignment::query()->create([
        'academic_year_id' => $year->id,
        'section_id' => $assignedSection->id,
        'subject_id' => $subject->id,
        'assigned_by' => $teacher->id,
        'title' => 'Submitted work',
        'instructions' => 'Review this work.',
        'assigned_date' => today()->subDay()->toDateString(),
        'due_date' => today()->addDay()->toDateString(),
        'max_points' => 10,
    ]);
    HomeworkSubmission::query()->create([
        'homework_assignment_id' => $assignment->id,
        'student_id' => $student->id,
        'submitted_by' => $student->user_id,
        'content' => 'Completed.',
        'submitted_at' => now(),
    ]);
    HomeworkAssignment::query()->create([
        'academic_year_id' => $year->id,
        'section_id' => $otherSection->id,
        'subject_id' => $subject->id,
        'assigned_by' => $teacher->id,
        'title' => 'Unassigned section work',
        'instructions' => 'Not in the teacher roster.',
        'assigned_date' => today()->subDay()->toDateString(),
        'due_date' => today()->addDay()->toDateString(),
        'max_points' => 10,
    ]);

    $this->actingAs($teacher)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.metrics.0.label', 'Students in assigned sections')
            ->where('dashboard.metrics.0.value', 1)
            ->where('dashboard.metrics.1.label', 'Assigned sections')
            ->where('dashboard.metrics.1.value', 1)
            ->where('dashboard.metrics.2.label', 'Submissions to grade')
            ->where('dashboard.metrics.2.value', 1));
});

test('accountant dashboards show current-year outstanding invoices and daily payments', function () {
    $year = createDashboardAcademicYear();
    $accountant = User::factory()->create();
    $accountant->assignRole('Accountant');
    [$student] = createDashboardStudent(User::factory()->create(), $year, 'DASH-ACCOUNT-001');
    $invoice = Invoice::query()->create([
        'academic_year_id' => $year->id,
        'student_id' => $student->id,
        'invoice_number' => 'DASH-ACCOUNT-INV',
        'total_amount' => 200,
        'paid_amount' => 50,
        'issued_at' => today()->toDateString(),
        'due_date' => today()->addMonth()->toDateString(),
    ]);
    FeePayment::query()->create([
        'invoice_id' => $invoice->id,
        'received_by' => $accountant->id,
        'receipt_number' => 'DASH-ACCOUNT-RECEIPT',
        'payment_method' => 'cash',
        'amount' => 50,
        'payment_date' => today()->toDateString(),
    ]);

    $this->actingAs($accountant)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.metrics.0.label', 'Outstanding invoices')
            ->where('dashboard.metrics.0.value', 1)
            ->where('dashboard.metrics.1.label', 'Current balance')
            ->where('dashboard.metrics.1.value', 150)
            ->where('dashboard.metrics.2.label', 'Payments recorded today')
            ->where('dashboard.metrics.2.value', 1));
});

test('librarian dashboards show issued and overdue circulation counts', function () {
    $librarian = User::factory()->create();
    $librarian->assignRole('Librarian');
    $year = createDashboardAcademicYear();
    [$student] = createDashboardStudent(User::factory()->create(), $year, 'DASH-LIBRARY-001');
    $book = Book::query()->create([
        'created_by' => $librarian->id,
        'title' => 'Dashboard Library Book',
        'author' => 'School Author',
        'category' => 'Reference',
    ]);
    $copy = BookCopy::query()->create([
        'book_id' => $book->id,
        'accession_number' => 'DASH-LIBRARY-COPY',
        'status' => 'issued',
    ]);
    LibraryLoan::query()->create([
        'book_copy_id' => $copy->id,
        'student_id' => $student->id,
        'issued_by' => $librarian->id,
        'issue_date' => today()->subWeeks(2)->toDateString(),
        'due_date' => today()->subDay()->toDateString(),
        'status' => 'issued',
    ]);

    $this->actingAs($librarian)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.metrics.0.label', 'Books on loan')
            ->where('dashboard.metrics.0.value', 1)
            ->where('dashboard.metrics.1.label', 'Overdue books')
            ->where('dashboard.metrics.1.value', 1));
});
