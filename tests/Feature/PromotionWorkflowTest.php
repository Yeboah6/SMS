<?php

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\Enrolment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\AcademicStructureService;
use App\Services\AcademicYearService;
use App\Services\StudentProfileService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * @return array{User, AcademicYear, AcademicYear, SchoolClass, Section}
 */
function createPromotionScenario(): array
{
    $principal = User::factory()->create();
    $principal->assignRole('Principal');

    $yearService = app(AcademicYearService::class);
    $sourceYear = $yearService->create([
        'name' => '2024/2025',
        'start_date' => today()->subMonths(18)->toDateString(),
        'end_date' => today()->subMonths(6)->toDateString(),
    ]);
    $yearService->activate($sourceYear);
    $yearService->complete($sourceYear);

    $targetYear = $yearService->create([
        'name' => '2025/2026',
        'start_date' => today()->subMonths(5)->toDateString(),
        'end_date' => today()->addMonths(7)->toDateString(),
    ]);

    $structure = app(AcademicStructureService::class);
    $schoolClass = $structure->createClass($targetYear, ['name' => 'Class 6']);
    $section = $structure->createSection($schoolClass, ['name' => 'A']);

    return [$principal, $sourceYear, $targetYear, $schoolClass, $section];
}

function createPromotionStudent(string $admissionNumber): Student
{
    return app(StudentProfileService::class)->create([
        'admission_number' => $admissionNumber,
        'first_name' => 'Taylor',
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
}

function enrolForPromotion(Student $student, AcademicYear $year): Enrolment
{
    $structure = app(AcademicStructureService::class);
    $schoolClass = $structure->createClass($year, ['name' => 'Class 5 '.$student->admission_number]);
    $section = $structure->createSection($schoolClass, ['name' => 'A']);

    return app(StudentProfileService::class)->enrol($student, [
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
        'section_id' => $section->id,
        'enrolment_date' => $year->start_date->toDateString(),
    ]);
}

test('principals can review eligible students before processing an academic year promotion', function () {
    [$principal, $sourceYear, $targetYear] = createPromotionScenario();
    $student = createPromotionStudent('PROMO-001');
    enrolForPromotion($student, $sourceYear);

    $this->actingAs($principal)->get(route('promotions.index', [
        'source_year_id' => $sourceYear->id,
        'target_year_id' => $targetYear->id,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Promotions/Index')
            ->where('students.data.0.admissionNumber', 'PROMO-001')
            ->where('canOverride', true));
});

test('students already enrolled in the target year are not listed as promotion candidates', function () {
    [$principal, $sourceYear, $targetYear, $targetClass, $targetSection] = createPromotionScenario();
    $eligibleStudent = createPromotionStudent('PROMO-010');
    $alreadyEnrolledStudent = createPromotionStudent('PROMO-011');
    enrolForPromotion($eligibleStudent, $sourceYear);
    enrolForPromotion($alreadyEnrolledStudent, $sourceYear);
    app(StudentProfileService::class)->enrol($alreadyEnrolledStudent, [
        'academic_year_id' => $targetYear->id,
        'class_id' => $targetClass->id,
        'section_id' => $targetSection->id,
        'enrolment_date' => $targetYear->start_date->toDateString(),
    ]);

    $this->actingAs($principal)->get(route('promotions.index', [
        'source_year_id' => $sourceYear->id,
        'target_year_id' => $targetYear->id,
    ]))
        ->assertInertia(fn ($page) => $page
            ->where('students.total', 1)
            ->where('students.data.0.admissionNumber', 'PROMO-010'));
});

test('a bulk promotion preserves source history, creates next-year enrolments and records reasoned overrides', function () {
    [$principal, $sourceYear, $targetYear, $targetClass, $targetSection] = createPromotionScenario();
    $promotedStudent = createPromotionStudent('PROMO-002');
    $repeatingStudent = createPromotionStudent('PROMO-003');
    $graduatingStudent = createPromotionStudent('PROMO-007');
    $sourceEnrolment = enrolForPromotion($promotedStudent, $sourceYear);
    $repeatEnrolment = enrolForPromotion($repeatingStudent, $sourceYear);
    $graduationEnrolment = enrolForPromotion($graduatingStudent, $sourceYear);

    $this->actingAs($principal)->post(route('promotions.store'), [
        'source_academic_year_id' => $sourceYear->id,
        'target_academic_year_id' => $targetYear->id,
        'class_id' => $targetClass->id,
        'section_id' => $targetSection->id,
        'decisions' => [
            ['student_id' => $promotedStudent->id, 'outcome' => 'promoted'],
            ['student_id' => $repeatingStudent->id, 'outcome' => 'repeated', 'reason' => 'Needs another year to consolidate core skills.'],
            ['student_id' => $graduatingStudent->id, 'outcome' => 'graduated', 'reason' => 'Completed the final grade.'],
        ],
    ])->assertRedirect()
        ->assertSessionHas('status', 'Student promotion decisions recorded.');

    expect($sourceEnrolment->fresh()->status)->toBe('completed')
        ->and($sourceEnrolment->fresh()->outcome)->toBe('promoted')
        ->and($repeatEnrolment->fresh()->outcome)->toBe('repeated')
        ->and($graduationEnrolment->fresh()->outcome)->toBe('graduated')
        ->and($promotedStudent->enrolments()->where('academic_year_id', $targetYear->id)->count())->toBe(1)
        ->and($repeatingStudent->enrolments()->where('academic_year_id', $targetYear->id)->count())->toBe(1)
        ->and($graduatingStudent->enrolments()->where('academic_year_id', $targetYear->id)->exists())->toBeFalse();

    $this->assertDatabaseHas('promotions', [
        'student_id' => $repeatingStudent->id,
        'outcome' => 'repeated',
        'reason' => 'Needs another year to consolidate core skills.',
        'processed_by' => $principal->id,
    ]);

    $this->actingAs($principal)->get(route('students.show', $repeatingStudent))
        ->assertInertia(fn ($page) => $page
            ->where('student.enrolments.1.outcome', 'repeated')
            ->where('student.enrolments.1.outcomeReason', 'Needs another year to consolidate core skills.'));
});

test('an ineligible student aborts the entire promotion batch without changing source history', function () {
    [$principal, $sourceYear, $targetYear, $targetClass, $targetSection] = createPromotionScenario();
    $eligibleStudent = createPromotionStudent('PROMO-004');
    $ineligibleStudent = createPromotionStudent('PROMO-005');
    $sourceEnrolment = enrolForPromotion($eligibleStudent, $sourceYear);

    $this->actingAs($principal)->post(route('promotions.store'), [
        'source_academic_year_id' => $sourceYear->id,
        'target_academic_year_id' => $targetYear->id,
        'class_id' => $targetClass->id,
        'section_id' => $targetSection->id,
        'decisions' => [
            ['student_id' => $eligibleStudent->id, 'outcome' => 'promoted'],
            ['student_id' => $ineligibleStudent->id, 'outcome' => 'promoted'],
        ],
    ])->assertSessionHasErrors('decisions');

    expect($sourceEnrolment->fresh()->status)->toBe('active')
        ->and($eligibleStudent->enrolments()->where('academic_year_id', $targetYear->id)->exists())->toBeFalse();
    $this->assertDatabaseCount('promotions', 0);
});

test('a locked source year cannot be promoted', function () {
    [$principal, $sourceYear, $targetYear, $targetClass, $targetSection] = createPromotionScenario();
    $student = createPromotionStudent('PROMO-008');
    $sourceEnrolment = enrolForPromotion($student, $sourceYear);
    $sourceYear->update(['status' => AcademicYearStatus::Locked]);

    $this->actingAs($principal)->post(route('promotions.store'), [
        'source_academic_year_id' => $sourceYear->id,
        'target_academic_year_id' => $targetYear->id,
        'class_id' => $targetClass->id,
        'section_id' => $targetSection->id,
        'decisions' => [
            ['student_id' => $student->id, 'outcome' => 'promoted'],
        ],
    ])->assertSessionHasErrors('source_academic_year_id');

    expect($sourceEnrolment->fresh()->status)->toBe('active');
    $this->assertDatabaseCount('promotions', 0);
});

test('a section from a different target class is rejected without partial writes', function () {
    [$principal, $sourceYear, $targetYear, $targetClass] = createPromotionScenario();
    $student = createPromotionStudent('PROMO-012');
    $sourceEnrolment = enrolForPromotion($student, $sourceYear);
    $otherClass = app(AcademicStructureService::class)->createClass($targetYear, ['name' => 'Other class']);
    $otherSection = app(AcademicStructureService::class)->createSection($otherClass, ['name' => 'B']);

    $this->actingAs($principal)->post(route('promotions.store'), [
        'source_academic_year_id' => $sourceYear->id,
        'target_academic_year_id' => $targetYear->id,
        'class_id' => $targetClass->id,
        'section_id' => $otherSection->id,
        'decisions' => [
            ['student_id' => $student->id, 'outcome' => 'promoted'],
        ],
    ])->assertSessionHasErrors('section_id');

    expect($sourceEnrolment->fresh()->status)->toBe('active');
    $this->assertDatabaseCount('promotions', 0);
});

test('graduating students do not need an artificial target placement', function () {
    [$principal, $sourceYear, $targetYear] = createPromotionScenario();
    $student = createPromotionStudent('PROMO-009');
    enrolForPromotion($student, $sourceYear);

    $this->actingAs($principal)->post(route('promotions.store'), [
        'source_academic_year_id' => $sourceYear->id,
        'target_academic_year_id' => $targetYear->id,
        'decisions' => [
            ['student_id' => $student->id, 'outcome' => 'graduated', 'reason' => 'Completed the final grade.'],
        ],
    ])->assertRedirect();

    $this->assertDatabaseHas('promotions', [
        'student_id' => $student->id,
        'outcome' => 'graduated',
        'target_enrolment_id' => null,
        'target_class_id' => null,
        'target_section_id' => null,
    ]);
});

test('only authorized staff can use promotion workflows and overrides require a reason', function () {
    [$principal, $sourceYear, $targetYear, $targetClass, $targetSection] = createPromotionScenario();
    $student = createPromotionStudent('PROMO-006');
    enrolForPromotion($student, $sourceYear);

    $teacher = User::factory()->create();
    $teacher->assignRole('Teacher');

    $this->actingAs($teacher)->get(route('promotions.index'))->assertForbidden();

    $this->actingAs($principal)->post(route('promotions.store'), [
        'source_academic_year_id' => $sourceYear->id,
        'target_academic_year_id' => $targetYear->id,
        'class_id' => $targetClass->id,
        'section_id' => $targetSection->id,
        'decisions' => [
            ['student_id' => $student->id, 'outcome' => 'repeated'],
        ],
    ])->assertSessionHasErrors('decisions.0.reason');

    expect($student->enrolments()->where('academic_year_id', $targetYear->id)->exists())->toBeFalse();

    $promotionManager = User::factory()->create();
    $promotionManager->givePermissionTo('students.promote');
    $this->actingAs($promotionManager)->post(route('promotions.store'), [
        'source_academic_year_id' => $sourceYear->id,
        'target_academic_year_id' => $targetYear->id,
        'class_id' => $targetClass->id,
        'section_id' => $targetSection->id,
        'decisions' => [
            ['student_id' => $student->id, 'outcome' => 'repeated', 'reason' => 'Needs an exception.'],
        ],
    ])->assertForbidden();

    expect($student->enrolments()->where('academic_year_id', $targetYear->id)->exists())->toBeFalse();
});
