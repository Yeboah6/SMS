<?php

use App\Models\Guardian;
use App\Models\LeaveRequest;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * @return array{User, Staff}
 */
function createLeaveApplicant(string $email = 'teacher@example.test'): array
{
    $user = User::factory()->create(['email' => $email]);
    $user->assignRole('Teacher');
    $staff = Staff::query()->create([
        'user_id' => $user->id,
        'staff_number' => 'STAFF-'.strtoupper(substr(md5($email), 0, 8)),
        'is_teacher' => true,
        'name' => $user->name,
        'email' => $user->email,
        'status' => 'active',
    ]);

    return [$user, $staff];
}

/**
 * @return array{leave_type: string, start_date: string, end_date: string, reason: string}
 */
function leaveRequestPayload(): array
{
    return [
        'leave_type' => 'annual',
        'start_date' => today()->addDays(5)->startOfDay()->toDateTimeString(),
        'end_date' => today()->addDays(6)->startOfDay()->toDateTimeString(),
        'reason' => 'Family responsibilities require time away.',
    ];
}

test('staff can submit a leave request that is saved as pending for their own profile', function () {
    [$teacher, $staff] = createLeaveApplicant();

    $this->actingAs($teacher)->post(route('leave-requests.store'), leaveRequestPayload())
        ->assertRedirect()
        ->assertSessionHas('status', 'Leave request submitted.');

    $this->assertDatabaseHas('leave_requests', [
        'staff_id' => $staff->id,
        'submitted_by' => $teacher->id,
        'leave_type' => 'annual',
        'start_date' => today()->addDays(5)->startOfDay()->toDateTimeString(),
        'end_date' => today()->addDays(6)->startOfDay()->toDateTimeString(),
        'status' => 'pending',
    ]);
});

test('staff cannot forge leave ownership or approval fields when submitting', function () {
    [$teacher, $staff] = createLeaveApplicant();
    [, $otherStaff] = createLeaveApplicant('another-teacher@example.test');
    $principal = User::factory()->create();
    $principal->assignRole('Principal');

    $this->actingAs($teacher)->post(route('leave-requests.store'), [
        ...leaveRequestPayload(),
        'staff_id' => $otherStaff->id,
        'submitted_by' => $principal->id,
        'reviewed_by' => $principal->id,
        'status' => 'approved',
    ])->assertRedirect();

    $this->assertDatabaseHas('leave_requests', [
        'staff_id' => $staff->id,
        'submitted_by' => $teacher->id,
        'reviewed_by' => null,
        'status' => 'pending',
    ]);
});

test('staff cannot submit a leave period that overlaps a pending request', function () {
    [$teacher] = createLeaveApplicant();
    $this->actingAs($teacher)->post(route('leave-requests.store'), leaveRequestPayload())
        ->assertRedirect();

    $overlappingDates = leaveRequestPayload();
    $overlappingDates['start_date'] = today()->addDays(6)->toDateString();
    $overlappingDates['end_date'] = today()->addDays(8)->toDateString();

    $this->actingAs($teacher)->post(route('leave-requests.store'), $overlappingDates)
        ->assertSessionHasErrors([
            'start_date' => 'This period overlaps another pending or approved leave request.',
        ]);

    $this->assertDatabaseCount('leave_requests', 1);
});

test('staff cannot submit past dates or a range ending before it starts', function () {
    [$teacher] = createLeaveApplicant();
    $invalidDates = leaveRequestPayload();
    $invalidDates['start_date'] = today()->subDay()->toDateString();
    $invalidDates['end_date'] = today()->subDays(2)->toDateString();

    $this->actingAs($teacher)->post(route('leave-requests.store'), $invalidDates)
        ->assertSessionHasErrors(['start_date', 'end_date']);

    $this->assertDatabaseCount('leave_requests', 0);
});

test('principals can approve or reject a pending request with an audit record', function (string $outcome) {
    [$teacher] = createLeaveApplicant();
    $this->actingAs($teacher)->post(route('leave-requests.store'), leaveRequestPayload())
        ->assertRedirect();
    $leaveRequest = LeaveRequest::query()->firstOrFail();

    $principal = User::factory()->create();
    $principal->assignRole('Principal');

    $this->actingAs($principal)->get(route('leave-requests.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('LeaveRequests/Index')
            ->where('leaveRequests.total', 1)
            ->where('leaveRequests.data.0.staffName', $teacher->name));

    $this->actingAs($principal)->post(route('leave-requests.review', $leaveRequest), [
        'outcome' => $outcome,
        'review_comments' => 'Decision reviewed by the principal.',
    ])->assertRedirect()
        ->assertSessionHas('status', 'Leave request reviewed.');

    $this->assertDatabaseHas('leave_requests', [
        'id' => $leaveRequest->id,
        'status' => $outcome,
        'reviewed_by' => $principal->id,
        'review_comments' => 'Decision reviewed by the principal.',
    ]);
    expect($leaveRequest->fresh()->reviewed_at)->not->toBeNull();

    $this->actingAs($principal)->post(route('leave-requests.review', $leaveRequest), [
        'outcome' => $outcome === 'approved' ? 'rejected' : 'approved',
    ])->assertSessionHasErrors('outcome');

    expect($leaveRequest->fresh()->status)->toBe($outcome);
})->with([
    'approved' => 'approved',
    'rejected' => 'rejected',
]);

test('applicants can only view their own leave requests', function () {
    [$firstTeacher] = createLeaveApplicant();
    $this->actingAs($firstTeacher)->post(route('leave-requests.store'), leaveRequestPayload())
        ->assertRedirect();
    [$secondTeacher] = createLeaveApplicant('second-teacher@example.test');

    $this->actingAs($secondTeacher)->get(route('leave-requests.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('LeaveRequests/Index')
            ->where('leaveRequests.total', 0));
});

test('applicants can cancel their pending requests but cannot cancel another staff member request', function () {
    [$teacher] = createLeaveApplicant();
    $this->actingAs($teacher)->post(route('leave-requests.store'), leaveRequestPayload())
        ->assertRedirect();
    $leaveRequest = LeaveRequest::query()->firstOrFail();
    [$otherTeacher] = createLeaveApplicant('other-teacher@example.test');

    $this->actingAs($otherTeacher)->post(route('leave-requests.cancel', $leaveRequest))
        ->assertNotFound();
    expect($leaveRequest->fresh()->status)->toBe('pending');

    $this->actingAs($teacher)->post(route('leave-requests.cancel', $leaveRequest))
        ->assertRedirect()
        ->assertSessionHas('status', 'Leave request cancelled.');

    expect($leaveRequest->fresh()->status)->toBe('cancelled');
});

test('applicants cannot cancel a request after it has been approved', function () {
    [$teacher] = createLeaveApplicant();
    $this->actingAs($teacher)->post(route('leave-requests.store'), leaveRequestPayload())
        ->assertRedirect();
    $leaveRequest = LeaveRequest::query()->firstOrFail();
    $principal = User::factory()->create();
    $principal->assignRole('Principal');
    $this->actingAs($principal)->post(route('leave-requests.review', $leaveRequest), [
        'outcome' => 'approved',
    ])->assertRedirect();

    $this->actingAs($teacher)->post(route('leave-requests.cancel', $leaveRequest))
        ->assertSessionHasErrors('leave_request');

    expect($leaveRequest->fresh()->status)->toBe('approved');
});

test('users without an active linked staff profile cannot submit leave', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('Teacher');

    $this->actingAs($teacher)->post(route('leave-requests.store'), leaveRequestPayload())
        ->assertForbidden();

    $this->assertDatabaseCount('leave_requests', 0);
});

test('guests are redirected to sign in before accessing leave requests', function () {
    $this->get(route('leave-requests.index'))->assertRedirect(route('login'));

    $this->post(route('leave-requests.store'), leaveRequestPayload())->assertRedirect(route('login'));
});

test('active students can submit and view leave requests for their own profile', function () {
    $studentUser = User::factory()->create();
    $studentUser->assignRole('Student');
    $student = Student::query()->create([
        'user_id' => $studentUser->id,
        'admission_number' => 'LEAVE-STUDENT-001',
        'first_name' => 'Avery',
        'last_name' => 'Learner',
        'date_of_birth' => today()->subYears(12)->toDateString(),
        'gender' => 'prefer_not_to_say',
        'admission_date' => today()->subYear()->toDateString(),
        'status' => 'active',
    ]);

    $this->actingAs($studentUser)->post(route('leave-requests.store'), leaveRequestPayload())
        ->assertRedirect()
        ->assertSessionHas('status', 'Leave request submitted.');

    $leaveRequest = LeaveRequest::query()->firstOrFail();
    expect($leaveRequest->staff_id)->toBeNull()
        ->and($leaveRequest->student_id)->toBe($student->id)
        ->and($leaveRequest->submitted_by)->toBe($studentUser->id);

    $this->actingAs($studentUser)->get(route('leave-requests.index'))
        ->assertInertia(fn ($page) => $page
            ->component('LeaveRequests/Index')
            ->where('canApply', true)
            ->where('leaveRequests.total', 1)
            ->where('leaveRequests.data.0.staffName', 'Avery Learner')
            ->where('leaveRequests.data.0.subjectType', 'student'));
});

test('a student with a parent role submits leave for their own profile', function () {
    $studentUser = User::factory()->create();
    $studentUser->assignRole(['Student', 'Parent']);
    $student = Student::query()->create([
        'user_id' => $studentUser->id,
        'admission_number' => 'LEAVE-STUDENT-ROLE-001',
        'first_name' => 'Morgan',
        'last_name' => 'Learner',
        'date_of_birth' => today()->subYears(12)->toDateString(),
        'gender' => 'prefer_not_to_say',
        'admission_date' => today()->subYear()->toDateString(),
        'status' => 'active',
    ]);

    $this->actingAs($studentUser)->post(route('leave-requests.store'), leaveRequestPayload())
        ->assertRedirect()
        ->assertSessionHas('status', 'Leave request submitted.');

    $leaveRequest = LeaveRequest::query()->firstOrFail();
    expect($leaveRequest->student_id)->toBe($student->id)
        ->and($leaveRequest->staff_id)->toBeNull();
});

test('parents can request leave for an active linked student but not an unrelated student', function () {
    $parent = User::factory()->create();
    $parent->assignRole('Parent');
    $studentUser = User::factory()->create();
    $studentUser->assignRole('Student');
    $student = Student::query()->create([
        'user_id' => $studentUser->id,
        'admission_number' => 'LEAVE-STUDENT-002',
        'first_name' => 'Jordan',
        'last_name' => 'Learner',
        'date_of_birth' => today()->subYears(11)->toDateString(),
        'gender' => 'prefer_not_to_say',
        'admission_date' => today()->subYear()->toDateString(),
        'status' => 'active',
    ]);
    $otherStudentUser = User::factory()->create();
    $otherStudentUser->assignRole('Student');
    $otherStudent = Student::query()->create([
        'user_id' => $otherStudentUser->id,
        'admission_number' => 'LEAVE-STUDENT-003',
        'first_name' => 'Casey',
        'last_name' => 'Learner',
        'date_of_birth' => today()->subYears(10)->toDateString(),
        'gender' => 'prefer_not_to_say',
        'admission_date' => today()->subYear()->toDateString(),
        'status' => 'active',
    ]);
    $guardian = Guardian::query()->create([
        'user_id' => $parent->id,
        'name' => 'Jordan Parent',
        'phone' => '555-0102',
        'email' => $parent->email,
    ]);
    $guardian->students()->attach($student->id, [
        'relationship' => 'parent',
        'is_primary' => true,
        'lives_with_student' => true,
    ]);

    $this->actingAs($parent)->post(route('leave-requests.store'), [
        ...leaveRequestPayload(),
        'student_id' => $student->id,
    ])->assertRedirect()
        ->assertSessionHas('status', 'Leave request submitted.');

    $this->actingAs($parent)->get(route('leave-requests.index'))
        ->assertInertia(fn ($page) => $page
            ->component('LeaveRequests/Index')
            ->where('canApply', true)
            ->where('requiresStudentSelection', true)
            ->where('students.0.id', $student->id)
            ->where('leaveRequests.total', 1)
            ->where('leaveRequests.data.0.isOwn', true));

    $this->actingAs($parent)->post(route('leave-requests.store'), [
        ...leaveRequestPayload(),
        'student_id' => $otherStudent->id,
    ])->assertSessionHasErrors('student_id');

    $this->assertDatabaseCount('leave_requests', 1);
    $leaveRequest = LeaveRequest::query()->firstOrFail();
    expect($leaveRequest->student_id)->toBe($student->id)
        ->and($leaveRequest->staff_id)->toBeNull()
        ->and($leaveRequest->submitted_by)->toBe($parent->id);
});

test('student leave periods cannot overlap another pending or approved student request', function () {
    $studentUser = User::factory()->create();
    $studentUser->assignRole('Student');
    $student = Student::query()->create([
        'user_id' => $studentUser->id,
        'admission_number' => 'LEAVE-STUDENT-004',
        'first_name' => 'Taylor',
        'last_name' => 'Learner',
        'date_of_birth' => today()->subYears(12)->toDateString(),
        'gender' => 'prefer_not_to_say',
        'admission_date' => today()->subYear()->toDateString(),
        'status' => 'active',
    ]);

    $this->actingAs($studentUser)->post(route('leave-requests.store'), leaveRequestPayload())
        ->assertRedirect();
    $overlappingDates = leaveRequestPayload();
    $overlappingDates['start_date'] = today()->addDays(6)->toDateString();
    $overlappingDates['end_date'] = today()->addDays(8)->toDateString();

    $this->actingAs($studentUser)->post(route('leave-requests.store'), $overlappingDates)
        ->assertSessionHasErrors([
            'start_date' => 'This period overlaps another pending or approved leave request.',
        ]);

    $this->assertDatabaseCount('leave_requests', 1);
    expect(LeaveRequest::query()->firstOrFail()->student_id)->toBe($student->id);
});
