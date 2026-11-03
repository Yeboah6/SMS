<?php

use App\Models\Student;
use App\Models\User;
use App\Notifications\GuardianPortalInvitation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * @return array<string, mixed>
 */
function studentParentAccountPayload(bool $inviteParentAccounts = false): array
{
    return [
        'admission_number' => 'PARENT-ACCOUNT-001',
        'first_name' => 'Avery',
        'middle_name' => null,
        'last_name' => 'Student',
        'date_of_birth' => today()->subYears(10)->toDateString(),
        'gender' => 'prefer_not_to_say',
        'blood_group' => null,
        'religion' => null,
        'address' => null,
        'admission_date' => today()->toDateString(),
        'status' => 'active',
        'invite_parent_accounts' => $inviteParentAccounts,
        'guardians' => [
            [
                'name' => 'Jordan Guardian',
                'phone' => '555-0101',
                'email' => 'jordan.guardian@example.test',
                'relationship' => 'Mother',
                'is_primary' => true,
                'lives_with_student' => true,
            ],
            [
                'name' => 'Taylor Guardian',
                'phone' => '555-0102',
                'email' => 'taylor.guardian@example.test',
                'relationship' => 'Father',
                'is_primary' => false,
                'lives_with_student' => false,
            ],
        ],
    ];
}

function createStudentParentAccountReceptionist(): User
{
    $receptionist = User::factory()->create();
    $receptionist->assignRole('Receptionist');

    return $receptionist;
}

test('student creation can invite every guardian with an email to set up a Parent account', function () {
    $receptionist = createStudentParentAccountReceptionist();
    Notification::fake();

    $this->actingAs($receptionist)
        ->get(route('students.create'))
        ->assertInertia(fn ($page) => $page->where('canManageGuardianAccounts', true));

    $response = $this->actingAs($receptionist)
        ->post(route('students.store'), studentParentAccountPayload(inviteParentAccounts: true));

    $student = Student::query()->where('admission_number', 'PARENT-ACCOUNT-001')->firstOrFail();
    $guardians = $student->guardians()->orderBy('email')->get();
    $parents = User::query()->whereIn('email', [
        'jordan.guardian@example.test',
        'taylor.guardian@example.test',
    ])->orderBy('email')->get();

    $response->assertRedirect(route('students.show', $student))
        ->assertSessionHas('status', "Student profile {$student->admission_number} created. Password setup invitations sent to 2 guardian(s).");

    expect($parents)->toHaveCount(2);

    foreach ($parents as $parent) {
        expect($parent->hasRole('Parent'))->toBeTrue();
        Notification::assertSentToOnce($parent, GuardianPortalInvitation::class);
        Notification::assertNotSentTo($parent, ResetPassword::class);
    }

    foreach ($guardians as $guardian) {
        expect($guardian->user_id)->not->toBeNull();
    }
});

test('student creation does not create parent accounts unless invitations are selected', function () {
    $receptionist = createStudentParentAccountReceptionist();
    Notification::fake();

    $response = $this->actingAs($receptionist)
        ->post(route('students.store'), studentParentAccountPayload());

    $student = Student::query()->where('admission_number', 'PARENT-ACCOUNT-001')->firstOrFail();

    $response->assertRedirect(route('students.show', $student))
        ->assertSessionHas('status', "Student profile {$student->admission_number} created.");

    expect(User::query()->whereIn('email', [
        'jordan.guardian@example.test',
        'taylor.guardian@example.test',
    ])->exists())->toBeFalse();

    Notification::assertNothingSent();
});

test('student creation rejects invitations when a guardian email already belongs to an account', function () {
    $receptionist = createStudentParentAccountReceptionist();
    $existingParent = User::factory()->create(['email' => 'JORDAN.GUARDIAN@example.test']);
    $existingParent->assignRole('Parent');
    $payload = studentParentAccountPayload(inviteParentAccounts: true);

    $this->actingAs($receptionist)
        ->post(route('students.store'), $payload)
        ->assertSessionHasErrors([
            'guardians.0.email' => 'An account already uses this email. Create the student without invitations, then link the existing Parent account.',
        ]);

    expect(Student::query()->where('admission_number', 'PARENT-ACCOUNT-001')->exists())->toBeFalse()
        ->and($existingParent->fresh()->hasRole('Parent'))->toBeTrue();
});

test('student creation forbids parent invitations without guardian account permission', function () {
    $staff = User::factory()->create();
    $staff->givePermissionTo('students.create', 'guardians.create');

    $this->actingAs($staff)
        ->get(route('students.create'))
        ->assertInertia(fn ($page) => $page->where('canManageGuardianAccounts', false));

    $this->actingAs($staff)
        ->post(route('students.store'), studentParentAccountPayload(inviteParentAccounts: true))
        ->assertForbidden();

    expect(Student::query()->where('admission_number', 'PARENT-ACCOUNT-001')->exists())->toBeFalse();
});
