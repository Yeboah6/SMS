<?php

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

function createStudentForExportTest(
    string $admissionNumber,
    string $firstName,
    ?string $middleName,
    string $lastName,
    string $guardianName,
): Student {
    return app(StudentProfileService::class)->create([
        'admission_number' => $admissionNumber,
        'first_name' => $firstName,
        'middle_name' => $middleName,
        'last_name' => $lastName,
        'date_of_birth' => today()->subYears(10)->toDateString(),
        'gender' => 'prefer_not_to_say',
        'blood_group' => null,
        'religion' => null,
        'address' => null,
        'admission_date' => today()->toDateString(),
        'status' => 'active',
        'guardians' => [[
            'name' => $guardianName,
            'phone' => '555-0198',
            'email' => null,
            'relationship' => 'Parent',
            'is_primary' => true,
            'lives_with_student' => true,
        ]],
    ]);
}

test('super admins can export searched students with current enrolments in name order', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    createStudentForExportTest('EXPORT-002', 'Sam', null, 'Zulu', 'Guardian Zulu');
    $alphaStudent = createStudentForExportTest('EXPORT-001', 'Sam', 'A', 'Alpha', 'Guardian Alpha');
    createStudentForExportTest('EXPORT-003', 'Taylor', null, 'Other', 'Guardian Other');

    $year = app(AcademicYearService::class)->create([
        'name' => 'Student Export Year',
        'start_date' => today()->subMonth()->toDateString(),
        'end_date' => today()->addMonths(8)->toDateString(),
    ]);
    app(AcademicYearService::class)->activate($year);
    $schoolClass = app(AcademicStructureService::class)->createClass($year, ['name' => 'Export Class']);
    $section = app(AcademicStructureService::class)->createSection($schoolClass, ['name' => 'A']);
    app(StudentProfileService::class)->enrol($alphaStudent, [
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
        'section_id' => $section->id,
        'enrolment_date' => today()->toDateString(),
    ], $admin);

    $this->actingAs($admin)
        ->get(route('students.export', ['search' => 'Sam']))
        ->assertDownload()
        ->assertStreamedContent(
            "\xEF\xBB\xBF\"Admission No.\",Name,Guardian,\"Guardian Phone\",Class,Section,\"Admission Date\",Status\n".
            'EXPORT-001,"Sam A Alpha","Guardian Alpha",555-0198,"Export Class",A,'.today()->toDateString().",active\n".
            'EXPORT-002,"Sam Zulu","Guardian Zulu",555-0198,,,'.today()->toDateString().",active\n",
        );
});

test('student exports escape spreadsheet formulas in names', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    createStudentForExportTest('EXPORT-004', '=1+1', null, 'Learner', 'Guardian');

    $this->actingAs($admin)
        ->get(route('students.export'))
        ->assertDownload()
        ->assertStreamedContent(
            "\xEF\xBB\xBF\"Admission No.\",Name,Guardian,\"Guardian Phone\",Class,Section,\"Admission Date\",Status\n".
            "EXPORT-004,\"'=1+1 Learner\",Guardian,555-0198,,,".today()->toDateString().",active\n",
        );
});

test('users without student export permission cannot download the student csv', function () {
    $librarian = User::factory()->create();
    $librarian->assignRole('Librarian');

    $this->actingAs($librarian)->get(route('students.export'))->assertForbidden();
});
