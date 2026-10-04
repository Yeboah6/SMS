<?php

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\SchoolSetting;
use App\Models\Term;
use App\Models\User;
use App\Services\AcademicYearService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function academicYearAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    return $admin;
}

function createAcademicYear(array $overrides = []): AcademicYear
{
    return app(AcademicYearService::class)->create([
        'name' => '2026/2027',
        'start_date' => '2026-08-01',
        'end_date' => '2027-06-30',
        ...$overrides,
    ]);
}

test('academic year access requires view permission', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('Teacher');

    $this->actingAs($teacher)->get('/academic-years')->assertForbidden();

    $principal = User::factory()->create();
    $principal->assignRole('Principal');

    $this->actingAs($principal)->get('/academic-years')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('AcademicYears/Index'));
});

test('authorized users can create a draft academic year and its terms', function () {
    $admin = academicYearAdmin();

    $this->actingAs($admin)->post('/academic-years', [
        'name' => '2026/2027',
        'start_date' => '2026-08-01',
        'end_date' => '2027-06-30',
    ])->assertRedirect();

    $year = AcademicYear::query()->where('name', '2026/2027')->firstOrFail();
    expect($year->status)->toBe(AcademicYearStatus::Draft);

    $this->actingAs($admin)->post("/academic-years/{$year->id}/terms", [
        'name' => 'Term 1',
        'start_date' => '2026-08-01',
        'end_date' => '2026-12-20',
    ])->assertRedirect();

    expect($year->terms()->count())->toBe(1)
        ->and(Term::query()->forAcademicYear($year)->first()->name)->toBe('Term 1');
});

test('only one academic year can be active and lifecycle changes are ordered', function () {
    $admin = academicYearAdmin();
    $first = createAcademicYear();
    $second = createAcademicYear([
        'name' => '2027/2028',
        'start_date' => '2027-08-01',
        'end_date' => '2028-06-30',
    ]);

    $this->actingAs($admin)->post("/academic-years/{$first->id}/activate")->assertRedirect();
    $this->actingAs($admin)->post("/academic-years/{$second->id}/activate")
        ->assertRedirect()
        ->assertSessionHasErrors('academic_year');

    expect(AcademicYear::active()->count())->toBe(1)
        ->and($first->fresh()->status)->toBe(AcademicYearStatus::Active)
        ->and($second->fresh()->status)->toBe(AcademicYearStatus::Draft);

    $this->actingAs($admin)->post("/academic-years/{$first->id}/complete")->assertRedirect();
    $this->actingAs($admin)->post("/academic-years/{$first->id}/lock")->assertRedirect();

    expect($first->fresh()->status)->toBe(AcademicYearStatus::Locked)
        ->and(AcademicYear::active()->count())->toBe(0);
});

test('locked years cannot be edited or deleted', function () {
    $admin = academicYearAdmin();
    $year = createAcademicYear();
    $year->update(['status' => AcademicYearStatus::Locked]);

    $this->actingAs($admin)->patch("/academic-years/{$year->id}", [
        'name' => 'Renamed year',
        'start_date' => '2026-08-01',
        'end_date' => '2027-06-30',
    ])->assertRedirect()->assertSessionHasErrors('academic_year');

    $this->actingAs($admin)->delete("/academic-years/{$year->id}")
        ->assertRedirect()
        ->assertSessionHasErrors('academic_year');

    expect($year->fresh()->name)->toBe('2026/2027');
});

test('terms must stay inside their academic year dates', function () {
    $admin = academicYearAdmin();
    $year = createAcademicYear();

    $this->actingAs($admin)->post("/academic-years/{$year->id}/terms", [
        'name' => 'Term outside year',
        'start_date' => '2026-07-31',
        'end_date' => '2026-12-20',
    ])->assertSessionHasErrors('start_date');

    expect($year->terms()->count())->toBe(0);
});

test('school settings are permission protected and immediately available as shared props', function () {
    $admin = academicYearAdmin();

    $this->actingAs($admin)->put('/settings', [
        'school_name' => 'Northstar School',
        'motto' => 'Learn and grow',
        'address' => '1 School Road',
        'phone' => '+1 555 0100',
        'email' => 'office@northstar.example',
        'website' => 'https://northstar.example',
        'attendance_threshold' => 82.5,
    ])->assertRedirect();

    $setting = SchoolSetting::query()->firstOrFail();
    expect($setting->school_name)->toBe('Northstar School')
        ->and((float) $setting->attendance_threshold)->toBe(82.5);

    $this->actingAs($admin)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('schoolSettings.school_name', 'Northstar School')
            ->where('activeAcademicYear', null));
});

test('attendance threshold is validated on the server', function () {
    $admin = academicYearAdmin();

    $this->actingAs($admin)->put('/settings', [
        'school_name' => 'Northstar School',
        'attendance_threshold' => 101,
    ])->assertSessionHasErrors('attendance_threshold');

    expect(SchoolSetting::query()->count())->toBe(0);
});
