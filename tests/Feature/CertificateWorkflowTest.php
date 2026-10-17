<?php

use App\Models\CertificateRequest;
use App\Models\Guardian;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function createCertificateStudent(string $admissionNumber, ?User $user = null): Student
{
    return Student::query()->create([
        'user_id' => $user?->id,
        'admission_number' => $admissionNumber,
        'first_name' => 'Avery',
        'middle_name' => 'Morgan',
        'last_name' => 'Learner',
        'date_of_birth' => '2014-01-01',
        'gender' => 'prefer_not_to_say',
        'admission_date' => '2022-08-01',
        'status' => 'active',
    ]);
}

/**
 * @return array{certificate_type: string, reason: string}
 */
function certificateRequestPayload(): array
{
    return [
        'certificate_type' => 'bonafide',
        'reason' => 'Required for an official school record submission.',
    ];
}

test('students can request certificates and view only their own request', function () {
    $studentUser = User::factory()->create();
    $studentUser->assignRole('Student');
    $student = createCertificateStudent('CERT-STUDENT-001', $studentUser);

    $this->actingAs($studentUser)->post(route('certificates.store'), certificateRequestPayload())
        ->assertRedirect()
        ->assertSessionHas('status', 'Certificate request submitted.');

    $certificateRequest = CertificateRequest::query()->firstOrFail();
    $this->assertDatabaseHas('certificate_requests', [
        'id' => $certificateRequest->id,
        'student_id' => $student->id,
        'requested_by' => $studentUser->id,
        'status' => 'pending',
        'certificate_number' => null,
    ]);

    $this->actingAs($studentUser)->get(route('certificates.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Certificates/Index')
            ->where('canRequest', true)
            ->where('requiresStudentSelection', false)
            ->where('certificateRequests.total', 1)
            ->where('certificateRequests.data.0.studentName', 'Avery Morgan Learner'));
});

test('parents can request certificates only for linked children', function () {
    $parent = User::factory()->create(['email' => 'parent@example.test']);
    $parent->assignRole('Parent');
    $linkedStudent = createCertificateStudent('CERT-CHILD-001');
    $unlinkedStudent = createCertificateStudent('CERT-CHILD-002');

    $guardian = Guardian::query()->create([
        'user_id' => $parent->id,
        'name' => 'Parent Account',
        'phone' => '555-0110',
        'email' => $parent->email,
    ]);
    $guardian->students()->attach($linkedStudent->id, [
        'relationship' => 'parent',
        'is_primary' => true,
        'lives_with_student' => true,
    ]);

    $this->actingAs($parent)->post(route('certificates.store'), [
        ...certificateRequestPayload(),
        'student_id' => $linkedStudent->id,
    ])->assertRedirect();

    $this->actingAs($parent)->get(route('certificates.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Certificates/Index')
            ->where('students.0.id', $linkedStudent->id)
            ->where('certificateRequests.total', 1));

    $this->actingAs($parent)->post(route('certificates.store'), [
        ...certificateRequestPayload(),
        'certificate_type' => 'character',
        'student_id' => $unlinkedStudent->id,
    ])->assertSessionHasErrors('student_id');

    $this->assertDatabaseCount('certificate_requests', 1);
});

test('principals can reject a request or issue a numbered certificate with a student snapshot', function (string $outcome) {
    $studentUser = User::factory()->create();
    $studentUser->assignRole('Student');
    $student = createCertificateStudent('CERT-STUDENT-003', $studentUser);
    $this->actingAs($studentUser)->post(route('certificates.store'), certificateRequestPayload())
        ->assertRedirect();
    $certificateRequest = CertificateRequest::query()->firstOrFail();

    $principal = User::factory()->create();
    $principal->assignRole('Principal');
    SchoolSetting::query()->create([
        'singleton_key' => 'primary',
        'school_name' => 'Riverbank School',
    ]);

    $this->actingAs($principal)->post(route('certificates.review', $certificateRequest), [
        'outcome' => $outcome,
        'review_comments' => 'Decision verified by the principal.',
    ])->assertRedirect()
        ->assertSessionHas('status', 'Certificate request reviewed.');

    $certificateRequest->refresh();
    expect($certificateRequest->status)->toBe($outcome)
        ->and($certificateRequest->reviewed_by)->toBe($principal->id)
        ->and($certificateRequest->reviewed_at)->not->toBeNull();

    if ($outcome === 'issued') {
        expect($certificateRequest->certificate_number)->toBe(sprintf('CERT-%08d', $certificateRequest->id))
            ->and($certificateRequest->school_name)->toBe('Riverbank School')
            ->and($certificateRequest->student_name)->toBe('Avery Morgan Learner')
            ->and($certificateRequest->admission_number)->toBe($student->admission_number)
            ->and($certificateRequest->issued_at)->not->toBeNull();

        $this->actingAs($studentUser)->get(route('certificates.show', $certificateRequest))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Certificates/Show')
                ->where('certificate.number', $certificateRequest->certificate_number)
                ->where('certificate.studentName', 'Avery Morgan Learner'));
    } else {
        expect($certificateRequest->certificate_number)->toBeNull()
            ->and($certificateRequest->issued_at)->toBeNull();

        $this->actingAs($studentUser)->get(route('certificates.show', $certificateRequest))
            ->assertNotFound();
    }
})->with([
    'issued' => 'issued',
    'rejected' => 'rejected',
]);

test('students cannot view another student certificate even when it has been issued', function () {
    $firstStudentUser = User::factory()->create();
    $firstStudentUser->assignRole('Student');
    $firstStudent = createCertificateStudent('CERT-STUDENT-004', $firstStudentUser);
    $secondStudentUser = User::factory()->create();
    $secondStudentUser->assignRole('Student');
    createCertificateStudent('CERT-STUDENT-005', $secondStudentUser);

    $certificateRequest = CertificateRequest::query()->create([
        'student_id' => $firstStudent->id,
        'requested_by' => $firstStudentUser->id,
        'certificate_type' => 'transfer',
        'reason' => 'Required for records.',
        'status' => 'issued',
        'certificate_number' => 'CERT-00000001',
        'school_name' => 'Riverbank School',
        'student_name' => 'Avery Morgan Learner',
        'admission_number' => $firstStudent->admission_number,
        'issued_at' => now(),
    ]);

    $this->actingAs($secondStudentUser)->get(route('certificates.show', $certificateRequest))
        ->assertNotFound();
});

test('duplicate pending certificate types and forged review fields are rejected', function () {
    $studentUser = User::factory()->create();
    $studentUser->assignRole('Student');
    createCertificateStudent('CERT-STUDENT-006', $studentUser);

    $this->actingAs($studentUser)->post(route('certificates.store'), [
        ...certificateRequestPayload(),
        'status' => 'issued',
        'requested_by' => 999,
    ])->assertRedirect();

    $this->actingAs($studentUser)->post(route('certificates.store'), certificateRequestPayload())
        ->assertSessionHasErrors([
            'certificate_type' => 'A request for this certificate type is already pending.',
        ]);

    $this->assertDatabaseCount('certificate_requests', 1);
    $this->assertDatabaseHas('certificate_requests', [
        'requested_by' => $studentUser->id,
        'status' => 'pending',
        'certificate_number' => null,
    ]);
});

test('users without certificate permissions cannot access the workflow and guests must sign in', function () {
    $this->get(route('certificates.index'))->assertRedirect(route('login'));

    $teacher = User::factory()->create();
    $teacher->assignRole('Teacher');
    $student = User::factory()->create();
    $student->assignRole('Student');
    $certificateStudent = createCertificateStudent('CERT-UNAUTHORIZED-001');
    $certificateRequest = CertificateRequest::query()->create([
        'student_id' => $certificateStudent->id,
        'requested_by' => $student->id,
        'certificate_type' => 'bonafide',
        'reason' => 'Required for an official school record submission.',
        'status' => 'pending',
    ]);

    $this->actingAs($teacher)->get(route('certificates.index'))->assertForbidden();
    $this->actingAs($teacher)->post(route('certificates.review', $certificateRequest), ['outcome' => 'issued'])->assertForbidden();
    $this->actingAs($student)->get(route('certificates.index'))->assertForbidden();
});

test('requesters can cancel only their own pending certificate requests', function () {
    $studentUser = User::factory()->create();
    $studentUser->assignRole('Student');
    $student = createCertificateStudent('CERT-STUDENT-007', $studentUser);
    $otherUser = User::factory()->create();
    $otherUser->assignRole('Student');
    createCertificateStudent('CERT-STUDENT-008', $otherUser);

    $this->actingAs($studentUser)->post(route('certificates.store'), certificateRequestPayload())
        ->assertRedirect();
    $certificateRequest = CertificateRequest::query()->firstOrFail();

    $this->actingAs($otherUser)->post(route('certificates.cancel', $certificateRequest))
        ->assertNotFound();
    expect($certificateRequest->fresh()->status)->toBe('pending');

    $this->actingAs($studentUser)->post(route('certificates.cancel', $certificateRequest))
        ->assertRedirect()
        ->assertSessionHas('status', 'Certificate request cancelled.');
    expect($certificateRequest->fresh()->status)->toBe('cancelled');
});
