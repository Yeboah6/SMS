<?php

use App\Models\Admission;
use App\Models\Student;
use App\Models\User;
use App\Services\AcademicStructureService;
use App\Services\AcademicYearService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function createAdmissionUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function validAdmissionData(): array
{
    return [
        'parent_name' => 'Jamie Guardian',
        'parent_phone' => '555-0188',
        'parent_email' => 'jamie@example.test',
        'parent_occupation' => 'Engineer',
        'parent_address' => '18 School Road',
        'child_first_name' => 'Alex',
        'child_middle_name' => 'Morgan',
        'child_last_name' => 'Learner',
        'date_of_birth' => today()->subYears(8)->toDateString(),
        'gender' => 'prefer_not_to_say',
        'child_address' => '18 School Road',
        'applying_for' => 'Class 3',
        'source' => 'referral',
    ];
}

function createAdmissionSchoolPlacement(): array
{
    $year = app(AcademicYearService::class)->create([
        'name' => 'Current admissions year',
        'start_date' => today()->subMonth()->toDateString(),
        'end_date' => today()->addMonths(5)->toDateString(),
    ]);
    $schoolClass = app(AcademicStructureService::class)->createClass($year, ['name' => 'Class 3']);
    $section = app(AcademicStructureService::class)->createSection($schoolClass, ['name' => 'A']);

    return [$year, $schoolClass, $section];
}

test('receptionists can record enquiries and administrators can view the admissions pipeline', function () {
    $receptionist = createAdmissionUser('Receptionist');

    $this->actingAs($receptionist)->post(route('admissions.store'), validAdmissionData())
        ->assertRedirect()
        ->assertSessionHas('status', 'Enquiry created.');

    $admission = Admission::query()->firstOrFail();
    expect($admission->status)->toBe('enquiry')
        ->and($admission->application_number)->toBe('APP-00000001')
        ->and($admission->created_by)->toBe($receptionist->id);

    $admin = createAdmissionUser('Super Admin');
    $this->actingAs($admin)->get(route('admissions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admissions/Index')
            ->has('admissions.data', 1)
            ->where('admissions.data.0.childName', 'Alex Morgan Learner')
            ->where('admissions.data.0.status', 'enquiry'));
});

test('students cannot create or view admissions', function () {
    $student = createAdmissionUser('Student');

    $this->actingAs($student)->post(route('admissions.store'), validAdmissionData())
        ->assertForbidden();
    $this->actingAs($student)->get(route('admissions.index'))->assertForbidden();

    expect(Admission::query()->count())->toBe(0);
});

test('authorized roles can review, approve, and enrol an admission atomically', function () {
    $receptionist = createAdmissionUser('Receptionist');
    $this->actingAs($receptionist)->post(route('admissions.store'), validAdmissionData())->assertRedirect();
    $admission = Admission::query()->firstOrFail();

    $this->actingAs($receptionist)->patch(route('admissions.transition', $admission), [
        'status' => 'application_received',
    ])->assertSessionHasNoErrors();
    $this->actingAs($receptionist)->patch(route('admissions.transition', $admission), [
        'status' => 'under_review',
    ])->assertSessionHasNoErrors();

    $principal = createAdmissionUser('Principal');
    $this->actingAs($principal)->patch(route('admissions.transition', $admission), [
        'status' => 'approved',
        'review_notes' => 'Application documents reviewed.',
    ])->assertSessionHasNoErrors();

    [$year, $schoolClass, $section] = createAdmissionSchoolPlacement();
    $this->actingAs($receptionist)->patch(route('admissions.transition', $admission), [
        'status' => 'enrolled',
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
        'section_id' => $section->id,
        'enrolment_date' => today()->toDateString(),
    ])->assertSessionHasNoErrors();

    $admission->refresh();
    $student = Student::query()->with(['guardians', 'enrolments'])->findOrFail($admission->student_id);

    expect($admission->status)->toBe('enrolled')
        ->and($admission->reviewed_by)->toBe($principal->id)
        ->and($student->admission_number)->toBe('STU-00000001')
        ->and($student->guardians)->toHaveCount(1)
        ->and($student->guardians->first()->name)->toBe('Jamie Guardian')
        ->and($student->enrolments)->toHaveCount(1)
        ->and($student->enrolments->first()->academic_year_id)->toBe($year->id);
});

test('receptionists cannot approve applications and rejected applications require a reason', function () {
    $receptionist = createAdmissionUser('Receptionist');
    $this->actingAs($receptionist)->post(route('admissions.store'), validAdmissionData())->assertRedirect();
    $admission = Admission::query()->firstOrFail();
    $admission->update(['status' => 'under_review']);

    $this->actingAs($receptionist)->patch(route('admissions.transition', $admission), [
        'status' => 'approved',
    ])->assertForbidden();

    $principal = createAdmissionUser('Principal');
    $this->actingAs($principal)->patch(route('admissions.transition', $admission), [
        'status' => 'rejected',
    ])->assertSessionHasErrors('review_notes');

    expect($admission->fresh()->status)->toBe('under_review');
});

test('failed enrolment leaves the approved admission and student data unchanged', function () {
    $receptionist = createAdmissionUser('Receptionist');
    $this->actingAs($receptionist)->post(route('admissions.store'), validAdmissionData())->assertRedirect();
    $admission = Admission::query()->firstOrFail();
    $admission->update(['status' => 'approved']);
    [$year, $schoolClass] = createAdmissionSchoolPlacement();
    $otherYear = app(AcademicYearService::class)->create([
        'name' => 'Other admissions year',
        'start_date' => today()->addMonths(6)->toDateString(),
        'end_date' => today()->addMonths(12)->toDateString(),
    ]);
    $otherClass = app(AcademicStructureService::class)->createClass($otherYear, ['name' => 'Class 3']);
    $otherSection = app(AcademicStructureService::class)->createSection($otherClass, ['name' => 'A']);

    $this->actingAs($receptionist)->patch(route('admissions.transition', $admission), [
        'status' => 'enrolled',
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
        'section_id' => $otherSection->id,
        'enrolment_date' => today()->toDateString(),
    ])->assertSessionHasErrors('section_id');

    expect($admission->fresh()->status)->toBe('approved')
        ->and($admission->fresh()->student_id)->toBeNull()
        ->and(Student::query()->count())->toBe(0);
});
