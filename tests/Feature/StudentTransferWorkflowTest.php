<?php

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\Enrolment;
use App\Models\Student;
use App\Models\User;
use App\Services\AcademicStructureService;
use App\Services\AcademicYearService;
use App\Services\StudentProfileService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * @return array{User, Student, Enrolment, AcademicYear}
 */
function createTransferScenario(
    string $admissionNumber = 'TRANSFER-001',
    ?AcademicYear $year = null,
): array {
    $principal = User::factory()->create();
    $principal->assignRole('Principal');

    if (! $year) {
        $year = app(AcademicYearService::class)->create([
            'name' => '2026/2027',
            'start_date' => today()->subDays(30)->toDateString(),
            'end_date' => today()->addMonths(8)->toDateString(),
        ]);
        app(AcademicYearService::class)->activate($year);
    }

    $schoolClass = app(AcademicStructureService::class)->createClass($year, ['name' => 'Transfer '.$admissionNumber]);
    $section = app(AcademicStructureService::class)->createSection($schoolClass, ['name' => 'A']);
    $student = app(StudentProfileService::class)->create([
        'admission_number' => $admissionNumber,
        'first_name' => 'Morgan',
        'middle_name' => null,
        'last_name' => 'Student',
        'date_of_birth' => today()->subYears(10)->toDateString(),
        'gender' => 'prefer_not_to_say',
        'blood_group' => null,
        'religion' => null,
        'address' => null,
        'admission_date' => today()->subYears(2)->toDateString(),
        'status' => 'active',
        'guardians' => [[
            'name' => 'Casey Guardian',
            'phone' => '555-0199',
            'relationship' => 'Parent',
            'is_primary' => true,
            'lives_with_student' => true,
        ]],
    ]);
    $enrolment = app(StudentProfileService::class)->enrol($student, [
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
        'section_id' => $section->id,
        'enrolment_date' => today()->subDays(10)->toDateString(),
    ]);

    return [$principal, $student, $enrolment, $year];
}

function studentTransferPayload(Enrolment $enrolment): array
{
    return [
        'enrolment_id' => $enrolment->id,
        'transfer_date' => today()->toDateString(),
        'destination_school' => 'Northside School',
        'reason' => 'The family is relocating to another city.',
    ];
}

test('principals can transfer a student and preserve an audited enrolment history', function () {
    [$principal, $student, $enrolment, $year] = createTransferScenario();

    $this->actingAs($principal)->post(route('students.transfers.store', $student), studentTransferPayload($enrolment))
        ->assertRedirect(route('students.show', $student))
        ->assertSessionHas('status', 'Student transfer recorded.');

    expect($enrolment->fresh()->status)->toBe('transferred')
        ->and($enrolment->fresh()->outcome)->toBe('transferred')
        ->and($enrolment->fresh()->exit_date->toDateString())->toBe(today()->toDateString())
        ->and($student->fresh()->status)->toBe('transferred');

    $this->assertDatabaseHas('student_transfers', [
        'student_id' => $student->id,
        'enrolment_id' => $enrolment->id,
        'academic_year_id' => $year->id,
        'processed_by' => $principal->id,
        'destination_school' => 'Northside School',
    ]);

    $this->actingAs($principal)->get(route('students.show', $student))
        ->assertInertia(fn ($page) => $page
            ->where('student.status', 'transferred')
            ->where('transfers.0.destinationSchool', 'Northside School')
            ->where('transfers.0.processedBy', $principal->name));

    $this->actingAs($principal)->post(route('students.transfers.store', $student), studentTransferPayload($enrolment))
        ->assertSessionHasErrors('student');
    $this->assertDatabaseCount('student_transfers', 1);
});

test('users without transfer permission cannot transfer a student', function () {
    [, $student, $enrolment] = createTransferScenario('TRANSFER-002');
    $teacher = User::factory()->create();
    $teacher->assignRole('Teacher');

    $this->actingAs($teacher)->post(route('students.transfers.store', $student), studentTransferPayload($enrolment))
        ->assertForbidden();

    expect($student->fresh()->status)->toBe('active')
        ->and($enrolment->fresh()->status)->toBe('active');
    $this->assertDatabaseCount('student_transfers', 0);
});

test('transfer requests cannot target another student or predate their active enrolment', function () {
    [$principal, $student, $enrolment, $year] = createTransferScenario('TRANSFER-003');
    [, , $otherEnrolment] = createTransferScenario('TRANSFER-004', $year);

    $this->actingAs($principal)->post(route('students.transfers.store', $student), [
        ...studentTransferPayload($enrolment),
        'enrolment_id' => $otherEnrolment->id,
    ])->assertSessionHasErrors('enrolment_id');

    $this->actingAs($principal)->post(route('students.transfers.store', $student), [
        ...studentTransferPayload($enrolment),
        'transfer_date' => today()->subDays(20)->toDateString(),
    ])->assertSessionHasErrors('transfer_date');

    expect($student->fresh()->status)->toBe('active')
        ->and($enrolment->fresh()->status)->toBe('active');
    $this->assertDatabaseCount('student_transfers', 0);
});

test('transfers cannot be recorded against a locked academic year', function () {
    [$principal, $student, $enrolment, $year] = createTransferScenario('TRANSFER-005');
    $year->update(['status' => AcademicYearStatus::Locked]);

    $this->actingAs($principal)->post(route('students.transfers.store', $student), studentTransferPayload($enrolment))
        ->assertSessionHasErrors('enrolment_id');

    expect($student->fresh()->status)->toBe('active')
        ->and($enrolment->fresh()->status)->toBe('active')
        ->and($year->fresh()->status)->toBe(AcademicYearStatus::Locked);
    $this->assertDatabaseCount('student_transfers', 0);
});
