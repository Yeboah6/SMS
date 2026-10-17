<?php

use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Services\AcademicStructureService;
use App\Services\AcademicYearService;
use App\Services\AttendanceService;
use App\Services\StudentProfileService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * @return array{User, AcademicYear}
 */
function createAttendanceReportFoundation(): array
{
    $principal = User::factory()->create();
    $principal->assignRole('Principal');

    $year = app(AcademicYearService::class)->create([
        'name' => 'Report 2030/2031',
        'start_date' => '2030-08-01',
        'end_date' => '2031-06-30',
    ]);
    app(AcademicYearService::class)->activate($year);

    return [$principal, $year];
}

function createAttendanceReportSection(AcademicYear $year, string $className, string $sectionName): Section
{
    $schoolClass = app(AcademicStructureService::class)->createClass($year, ['name' => $className]);

    return app(AcademicStructureService::class)->createSection($schoolClass, ['name' => $sectionName]);
}

function createAttendanceReportStudent(
    AcademicYear $year,
    Section $section,
    string $admissionNumber,
    string $firstName,
): Student {
    $student = Student::query()->create([
        'admission_number' => $admissionNumber,
        'first_name' => $firstName,
        'last_name' => 'Learner',
        'date_of_birth' => '2018-01-01',
        'gender' => 'prefer_not_to_say',
        'admission_date' => $year->start_date->toDateString(),
        'status' => 'active',
    ]);

    app(StudentProfileService::class)->enrol($student, [
        'academic_year_id' => $year->id,
        'class_id' => $section->class_id,
        'section_id' => $section->id,
        'enrolment_date' => $year->start_date->toDateString(),
    ]);

    return $student;
}

/**
 * @param  list<Student>  $students
 * @param  list<string>  $statuses
 */
function recordAttendanceReportSession(
    AcademicYear $year,
    Section $section,
    array $students,
    array $statuses,
    string $date,
    User $actor,
): void {
    $records = [];
    foreach ($students as $index => $student) {
        $records[] = [
            'student_id' => $student->id,
            'status' => $statuses[$index],
        ];
    }

    app(AttendanceService::class)->record([
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'attendance_date' => $date,
        'records' => $records,
    ], $actor);
}

test('principals can view year and section attendance summaries', function () {
    [$principal, $year] = createAttendanceReportFoundation();
    $section = createAttendanceReportSection($year, 'Grade One', 'A');
    $firstStudent = createAttendanceReportStudent($year, $section, 'REPORT-001', 'Avery');
    $secondStudent = createAttendanceReportStudent($year, $section, 'REPORT-002', 'Jordan');

    recordAttendanceReportSession($year, $section, [$firstStudent, $secondStudent], ['present', 'absent'], '2030-08-02', $principal);
    recordAttendanceReportSession($year, $section, [$firstStudent, $secondStudent], ['late', 'excused'], '2030-08-03', $principal);

    $this->actingAs($principal)->get(route('reports.attendance.index', [
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Attendance')
            ->where('selectedAcademicYearId', $year->id)
            ->where('selectedSectionId', $section->id)
            ->where('summary.markedDays', 4)
            ->where('summary.present', 1)
            ->where('summary.absent', 1)
            ->where('summary.late', 1)
            ->where('summary.excused', 1)
            ->where('students.total', 2)
            ->where('students.data.0.name', 'Avery Learner')
            ->where('students.data.0.markedDays', 2)
            ->where('students.data.0.late', 1));
});

test('teachers only see attendance reports for their assigned sections', function () {
    [$principal, $year] = createAttendanceReportFoundation();
    $assignedSection = createAttendanceReportSection($year, 'Grade One', 'A');
    $unassignedSection = createAttendanceReportSection($year, 'Grade Two', 'B');
    $assignedStudent = createAttendanceReportStudent($year, $assignedSection, 'REPORT-003', 'Riley');
    $unassignedStudent = createAttendanceReportStudent($year, $unassignedSection, 'REPORT-004', 'Casey');
    recordAttendanceReportSession($year, $assignedSection, [$assignedStudent], ['present'], '2030-08-02', $principal);
    recordAttendanceReportSession($year, $unassignedSection, [$unassignedStudent], ['absent'], '2030-08-02', $principal);

    $teacher = User::factory()->create();
    $teacher->assignRole('Teacher');
    $staff = Staff::query()->create([
        'user_id' => $teacher->id,
        'staff_number' => 'REPORT-T-001',
        'name' => $teacher->name,
        'is_teacher' => true,
        'status' => 'active',
    ]);
    app(AcademicStructureService::class)->assignTeacherToSection($staff, $assignedSection);

    $this->actingAs($teacher)->get(route('reports.attendance.index', [
        'academic_year_id' => $year->id,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('students.total', 1)
            ->where('students.data.0.name', 'Riley Learner')
            ->where('sections.0.id', $assignedSection->id));

    $this->actingAs($teacher)->get(route('reports.attendance.index', [
        'academic_year_id' => $year->id,
        'section_id' => $unassignedSection->id,
    ]))->assertNotFound();
});

test('principals can download a formula-safe attendance csv', function () {
    [$principal, $year] = createAttendanceReportFoundation();
    $section = createAttendanceReportSection($year, 'Grade One', 'A');
    $student = createAttendanceReportStudent($year, $section, 'REPORT-005', '=SUM(1,2)');
    recordAttendanceReportSession($year, $section, [$student], ['present'], '2030-08-02', $principal);

    $this->actingAs($principal)->get(route('reports.attendance.export', [
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
    ]))
        ->assertDownload('attendance-report-2030-2031.csv')
        ->assertStreamedContent("\xEF\xBB\xBF\"Admission number\",Student,Class,Section,\"Marked days\",Present,Absent,Late,Excused\nREPORT-005,\"'=SUM(1,2) Learner\",\"Grade One\",A,1,1,0,0,0\n");
});

test('users without report permissions cannot view or export attendance reports', function () {
    $student = User::factory()->create();
    $student->assignRole('Student');

    $this->actingAs($student)->get(route('reports.attendance.index'))->assertForbidden();
    $this->actingAs($student)->get(route('reports.attendance.export'))->assertForbidden();
});

test('teachers cannot export reports without the export permission', function () {
    [$principal, $year] = createAttendanceReportFoundation();
    $teacher = User::factory()->create();
    $teacher->assignRole('Teacher');

    $this->actingAs($teacher)->get(route('reports.attendance.export', [
        'academic_year_id' => $year->id,
    ]))->assertForbidden();
});
