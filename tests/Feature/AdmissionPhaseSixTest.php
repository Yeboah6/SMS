<?php

use App\Models\Admission;
use App\Models\AdmissionDocument;
use App\Models\AdmissionFollowUp;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\User;
use App\Services\AcademicStructureService;
use App\Services\AcademicYearService;
use App\Services\AdmissionWorkflowService;
use App\Services\FeeService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function phaseSixAdmissionData(): array
{
    return [
        'parent_name' => 'Taylor Guardian',
        'parent_phone' => '555-0119',
        'parent_email' => 'taylor@example.test',
        'parent_occupation' => 'Teacher',
        'parent_address' => '1 School Lane',
        'child_first_name' => 'Casey',
        'child_middle_name' => null,
        'child_last_name' => 'Learner',
        'date_of_birth' => today()->subYears(9)->toDateString(),
        'gender' => 'prefer_not_to_say',
        'child_address' => '1 School Lane',
        'applying_for' => 'Class 4',
        'source' => 'walk_in',
    ];
}

function phaseSixUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function phaseSixApprovedAdmission(User $actor): Admission
{
    $admission = app(AdmissionWorkflowService::class)->create(phaseSixAdmissionData(), $actor);
    $admission->update(['status' => 'approved']);

    return $admission;
}

function phaseSixPlacement(): array
{
    $year = app(AcademicYearService::class)->create([
        'name' => 'Phase Six Year',
        'start_date' => today()->subMonth()->toDateString(),
        'end_date' => today()->addMonths(6)->toDateString(),
    ]);
    $schoolClass = app(AcademicStructureService::class)->createClass($year, ['name' => 'Class 4']);
    $section = app(AcademicStructureService::class)->createSection($schoolClass, ['name' => 'A']);

    return [$year, $schoolClass, $section];
}

test('the public enquiry page creates a website enquiry without an account', function () {
    $this->get(route('public-enquiry.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Public/Enquiry'));

    $this->post(route('public-enquiry.store'), [
        ...phaseSixAdmissionData(),
        'source' => 'walk_in',
        'created_by' => 1,
    ])->assertRedirect()
        ->assertSessionHas('status', 'Thank you. The admissions team will contact you soon.');

    $admission = Admission::query()->firstOrFail();
    expect($admission->source)->toBe('website')
        ->and($admission->status)->toBe('enquiry')
        ->and($admission->created_by)->toBeNull();
    $this->assertDatabaseCount('users', 0);
});

test('public enquiry intake is rate limited', function () {
    $payload = phaseSixAdmissionData();

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('public-enquiry.store'), $payload)->assertRedirect();
    }

    $this->post(route('public-enquiry.store'), $payload)->assertTooManyRequests();
    $this->assertDatabaseCount('admissions', 5);
});

test('admissions staff can upload private documents and maintain follow-up history', function () {
    Storage::fake('local');
    $receptionist = phaseSixUser('Receptionist');
    $admission = phaseSixApprovedAdmission($receptionist);

    $this->actingAs($receptionist)->post(route('admissions.documents.store', $admission), [
        'category' => 'birth_certificate',
        'document' => UploadedFile::fake()->image('certificate.png'),
    ])->assertRedirect()->assertSessionHasNoErrors();

    $document = AdmissionDocument::query()->firstOrFail();
    expect($document->storage_path)->toStartWith("admission-documents/{$admission->id}/");
    Storage::disk('local')->assertExists($document->storage_path);

    $this->actingAs($receptionist)
        ->get(route('admissions.documents.download', [$admission, $document]))
        ->assertOk()
        ->assertHeader('content-disposition');

    $this->actingAs($receptionist)->post(route('admissions.follow-ups.store', $admission), [
        'follow_up_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'method' => 'phone',
        'notes' => 'Call the family about the application.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $followUp = AdmissionFollowUp::query()->firstOrFail();
    expect($followUp->status)->toBe('pending')
        ->and($followUp->created_by)->toBe($receptionist->id);

    $this->actingAs($receptionist)->patch(route('admissions.follow-ups.complete', $followUp), [
        'outcome' => 'Family confirmed the visit.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($followUp->fresh()->status)->toBe('completed')
        ->and($followUp->fresh()->outcome)->toBe('Family confirmed the visit.')
        ->and($followUp->fresh()->completed_by)->toBe($receptionist->id)
        ->and($followUp->fresh()->completed_at)->not->toBeNull();

    $this->actingAs($receptionist)->patch(route('admissions.follow-ups.complete', $followUp), [
        'outcome' => 'This must not replace the completed record.',
    ])->assertSessionHasErrors('outcome');
    expect($followUp->fresh()->outcome)->toBe('Family confirmed the visit.');
});

test('student portal provisioning emails a reset link and fees are assigned only to the enrolling student', function () {
    Notification::fake();
    $admin = phaseSixUser('Super Admin');
    $admission = phaseSixApprovedAdmission($admin);
    [$year, $schoolClass, $section] = phaseSixPlacement();
    $feeHead = app(FeeService::class)->createFeeHead([
        'code' => 'ADMISSION-TUITION-'.Str::upper(Str::random(6)),
        'name' => 'Tuition',
    ], $admin);
    app(FeeService::class)->createStructure([
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
        'fee_head_id' => $feeHead->id,
        'amount' => 100,
        'due_date' => today()->addMonths(2)->toDateString(),
    ], $admin);

    $this->actingAs($admin)->patch(route('admissions.transition', $admission), [
        'status' => 'enrolled',
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
        'section_id' => $section->id,
        'enrolment_date' => today()->toDateString(),
        'assign_fees' => true,
        'first_payment' => true,
        'first_payment_amount' => '25.00',
        'first_payment_method' => 'cash',
        'first_payment_date' => today()->toDateString(),
        'create_student_account' => true,
        'student_email' => 'casey.student@example.test',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $student = Student::query()->where('admission_number', 'STU-'.str_pad((string) $admission->id, 8, '0', STR_PAD_LEFT))->firstOrFail();
    $invoice = Invoice::query()->where('student_id', $student->id)->firstOrFail();
    $studentUser = User::query()->where('email', 'casey.student@example.test')->firstOrFail();

    expect($student->user_id)->toBe($studentUser->id)
        ->and($studentUser->hasRole('Student'))->toBeTrue()
        ->and($invoice->total_amount)->toBe('100.00')
        ->and($invoice->paid_amount)->toBe('25.00')
        ->and($invoice->status)->toBe('partially_paid');
    $this->assertDatabaseCount('invoices', 1);
    $this->assertDatabaseHas('fee_payments', [
        'invoice_id' => $invoice->id,
        'amount' => '25.00',
        'payment_method' => 'cash',
    ]);
    Notification::assertSentTo($studentUser, ResetPassword::class);
});

test('failed first payment rolls back enrolment invoice and student account', function () {
    $admin = phaseSixUser('Super Admin');
    $admission = phaseSixApprovedAdmission($admin);
    [$year, $schoolClass, $section] = phaseSixPlacement();
    $feeHead = app(FeeService::class)->createFeeHead([
        'code' => 'ROLLBACK-FEE',
        'name' => 'Tuition',
    ], $admin);
    app(FeeService::class)->createStructure([
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
        'fee_head_id' => $feeHead->id,
        'amount' => 100,
        'due_date' => today()->addMonths(2)->toDateString(),
    ], $admin);

    $this->actingAs($admin)->patch(route('admissions.transition', $admission), [
        'status' => 'enrolled',
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
        'section_id' => $section->id,
        'enrolment_date' => today()->toDateString(),
        'assign_fees' => true,
        'first_payment' => true,
        'first_payment_amount' => '150.00',
        'first_payment_method' => 'cash',
        'first_payment_date' => today()->toDateString(),
        'create_student_account' => true,
        'student_email' => 'rolled-back.student@example.test',
    ])->assertSessionHasErrors('amount');

    expect($admission->fresh()->status)->toBe('approved')
        ->and($admission->fresh()->student_id)->toBeNull();
    $this->assertDatabaseCount('students', 0);
    $this->assertDatabaseCount('invoices', 0);
    $this->assertDatabaseCount('fee_payments', 0);
    $this->assertDatabaseMissing('users', ['email' => 'rolled-back.student@example.test']);
});

test('unprivileged users cannot upload admission documents or manage follow-ups', function () {
    $student = phaseSixUser('Student');
    $admission = phaseSixApprovedAdmission(phaseSixUser('Receptionist'));

    $this->actingAs($student)->post(route('admissions.documents.store', $admission), [
        'category' => 'application',
        'document' => UploadedFile::fake()->image('application.png'),
    ])->assertForbidden();
    $this->actingAs($student)->post(route('admissions.follow-ups.store', $admission), [
        'follow_up_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'method' => 'phone',
        'notes' => 'Unauthorised',
    ])->assertForbidden();

    $this->assertDatabaseCount('admission_documents', 0);
    $this->assertDatabaseCount('admission_follow_ups', 0);
});

test('receptionists cannot assign invoices during enrolment without invoice permission', function () {
    $receptionist = phaseSixUser('Receptionist');
    $admission = phaseSixApprovedAdmission($receptionist);
    [$year, $schoolClass, $section] = phaseSixPlacement();

    $this->actingAs($receptionist)->patch(route('admissions.transition', $admission), [
        'status' => 'enrolled',
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
        'section_id' => $section->id,
        'enrolment_date' => today()->toDateString(),
        'assign_fees' => true,
    ])->assertForbidden();

    expect($admission->fresh()->status)->toBe('approved');
    $this->assertDatabaseCount('students', 0);
    $this->assertDatabaseCount('invoices', 0);
});
