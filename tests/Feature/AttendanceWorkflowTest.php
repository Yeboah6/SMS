<?php

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentActivity;
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
 * @return array{User, Staff, AcademicYear, Section}
 */
function createAttendanceScenario(): array
{
    $teacher = User::factory()->create(['name' => 'Morgan Teacher']);
    $teacher->assignRole('Teacher');
    $staff = Staff::query()->create([
        'user_id' => $teacher->id,
        'staff_number' => 'ATT-T-001',
        'name' => 'Morgan Teacher',
        'is_teacher' => true,
        'status' => 'active',
    ]);
    $year = app(AcademicYearService::class)->create([
        'name' => 'Attendance 2030/2031',
        'start_date' => '2030-08-01',
        'end_date' => '2031-06-30',
    ]);
    app(AcademicYearService::class)->activate($year);
    $structure = app(AcademicStructureService::class);
    $schoolClass = $structure->createClass($year, ['name' => 'Attendance Class']);
    $section = $structure->createSection($schoolClass, ['name' => 'A']);
    $structure->assignTeacherToSection($staff, $section);

    return [$teacher, $staff, $year, $section];
}

function createAttendanceStudent(string $admissionNumber): Student
{
    return app(StudentProfileService::class)->create([
        'admission_number' => $admissionNumber,
        'first_name' => 'Riley',
        'middle_name' => null,
        'last_name' => 'Learner',
        'date_of_birth' => '2020-01-01',
        'gender' => 'prefer_not_to_say',
        'blood_group' => null,
        'religion' => null,
        'address' => null,
        'admission_date' => '2030-08-01',
        'status' => 'active',
        'guardians' => [[
            'name' => 'Sky Guardian',
            'phone' => '555-0120',
            'relationship' => 'Parent',
            'is_primary' => true,
            'lives_with_student' => true,
        ]],
    ]);
}

/**
 * @return list<Student>
 */
function enrolAttendanceStudents(Section $section, AcademicYear $year, array $students): array
{
    foreach ($students as $student) {
        app(StudentProfileService::class)->enrol($student, [
            'academic_year_id' => $year->id,
            'class_id' => $section->class_id,
            'section_id' => $section->id,
            'enrolment_date' => $year->start_date->toDateString(),
        ]);
    }

    return $students;
}

test('assigned teachers can view a section roster and record daily attendance', function () {
    [$teacher, , $year, $section] = createAttendanceScenario();
    [$firstStudent, $secondStudent] = enrolAttendanceStudents($section, $year, [
        createAttendanceStudent('ATT-STU-001'),
        createAttendanceStudent('ATT-STU-002'),
    ]);

    $this->actingAs($teacher)->get(route('attendance.index', [
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'date' => '2030-08-02',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Attendance/Index')
            ->where('students.total', 2)
            ->where('canMark', true));

    $this->actingAs($teacher)->post(route('attendance.store'), [
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'attendance_date' => '2030-08-02',
        'records' => [
            ['student_id' => $firstStudent->id, 'status' => 'present'],
            ['student_id' => $secondStudent->id, 'status' => 'late', 'remarks' => 'Arrived after roll call.'],
        ],
    ])->assertRedirect()
        ->assertSessionHas('status', 'Attendance recorded.');

    $this->assertDatabaseHas('attendance_records', [
        'student_id' => $firstStudent->id,
        'status' => 'present',
    ]);
    $this->assertDatabaseHas('attendance_records', [
        'student_id' => $secondStudent->id,
        'status' => 'late',
        'remarks' => 'Arrived after roll call.',
    ]);
    $this->assertDatabaseCount('attendance', 1);
    $this->assertDatabaseHas('attendance', [
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'attendance_date' => '2030-08-02 00:00:00',
        'marked_by' => $teacher->id,
    ]);
    $this->assertDatabaseHas('student_activities', [
        'student_id' => $firstStudent->id,
        'actor_id' => $teacher->id,
        'academic_year_id' => $year->id,
        'event_type' => 'attendance.recorded',
    ]);
    expect(StudentActivity::query()
        ->where('student_id', $secondStudent->id)
        ->where('event_type', 'attendance.recorded')
        ->firstOrFail()
        ->details)
        ->toMatchArray([
            'previous_status' => null,
            'status' => 'late',
        ])
        ->not->toHaveKey('remarks');
});

test('teachers cannot mark attendance for sections outside their assignments', function () {
    [$teacher, , $year, $assignedSection] = createAttendanceScenario();
    $otherClass = app(AcademicStructureService::class)->createClass($year, ['name' => 'Other Class']);
    $otherSection = app(AcademicStructureService::class)->createSection($otherClass, ['name' => 'B']);
    $student = createAttendanceStudent('ATT-STU-003');
    enrolAttendanceStudents($otherSection, $year, [$student]);

    $this->actingAs($teacher)->get(route('attendance.index', [
        'academic_year_id' => $year->id,
        'section_id' => $otherSection->id,
        'date' => '2030-08-02',
    ]))->assertNotFound();

    $this->actingAs($teacher)->post(route('attendance.store'), [
        'academic_year_id' => $year->id,
        'section_id' => $otherSection->id,
        'attendance_date' => '2030-08-02',
        'records' => [
            ['student_id' => $student->id, 'status' => 'present'],
        ],
    ])->assertNotFound();

    expect($assignedSection->id)->not->toBe($otherSection->id);
    $this->assertDatabaseCount('attendance', 0);
});

test('attendance batches require the full active section roster and reject invalid students atomically', function () {
    [$teacher, , $year, $section] = createAttendanceScenario();
    [$firstStudent, $secondStudent] = enrolAttendanceStudents($section, $year, [
        createAttendanceStudent('ATT-STU-004'),
        createAttendanceStudent('ATT-STU-005'),
    ]);

    $this->actingAs($teacher)->post(route('attendance.store'), [
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'attendance_date' => '2030-08-02',
        'records' => [
            ['student_id' => $firstStudent->id, 'status' => 'present'],
        ],
    ])->assertSessionHasErrors('records');

    $this->assertDatabaseCount('attendance', 0);
    $this->assertDatabaseCount('attendance_records', 0);

    $this->actingAs($teacher)->post(route('attendance.store'), [
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'attendance_date' => '2030-08-02',
        'records' => [
            ['student_id' => $firstStudent->id, 'status' => 'present'],
            ['student_id' => $firstStudent->id, 'status' => 'absent'],
            ['student_id' => $secondStudent->id, 'status' => 'present'],
        ],
    ])->assertSessionHasErrors('records.1.student_id');

    $this->assertDatabaseCount('attendance', 0);
});

test('attendance cannot be recorded outside the academic year or in a locked year', function () {
    [$teacher, , $year, $section] = createAttendanceScenario();
    $student = createAttendanceStudent('ATT-STU-006');
    enrolAttendanceStudents($section, $year, [$student]);

    $this->actingAs($teacher)->post(route('attendance.store'), [
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'attendance_date' => '2031-07-01',
        'records' => [
            ['student_id' => $student->id, 'status' => 'present'],
        ],
    ])->assertSessionHasErrors('attendance_date');

    $year->update(['status' => AcademicYearStatus::Locked]);

    $this->actingAs($teacher)->post(route('attendance.store'), [
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'attendance_date' => '2030-08-02',
        'records' => [
            ['student_id' => $student->id, 'status' => 'present'],
        ],
    ])->assertSessionHasErrors('academic_year_id');

    $this->assertDatabaseCount('attendance', 0);
});

test('assigned teachers can correct existing daily attendance without creating duplicate sessions', function () {
    [$teacher, , $year, $section] = createAttendanceScenario();
    $student = createAttendanceStudent('ATT-STU-007');
    enrolAttendanceStudents($section, $year, [$student]);

    $payload = [
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'attendance_date' => '2030-08-02',
        'records' => [
            ['student_id' => $student->id, 'status' => 'present'],
        ],
    ];
    $this->actingAs($teacher)->post(route('attendance.store'), $payload)->assertRedirect();

    $payload['records'][0]['status'] = 'excused';
    $payload['records'][0]['remarks'] = 'Approved absence.';
    $this->actingAs($teacher)->post(route('attendance.store'), $payload)
        ->assertRedirect()
        ->assertSessionHas('status', 'Attendance recorded.');

    $this->assertDatabaseCount('attendance', 1);
    $this->assertDatabaseCount('attendance_records', 1);
    $this->assertDatabaseHas('attendance_records', [
        'student_id' => $student->id,
        'status' => 'excused',
        'remarks' => 'Approved absence.',
    ]);
    expect(StudentActivity::query()
        ->where('student_id', $student->id)
        ->whereIn('event_type', ['attendance.recorded', 'attendance.updated'])
        ->orderBy('id')
        ->get()
        ->pluck('event_type')
        ->all())
        ->toBe(['attendance.recorded', 'attendance.updated'])
        ->and(StudentActivity::query()
            ->where('student_id', $student->id)
            ->where('event_type', 'attendance.updated')
            ->firstOrFail()
            ->details)
        ->toMatchArray([
            'previous_status' => 'present',
            'status' => 'excused',
        ])
        ->not->toHaveKey('remarks');

    $this->actingAs($teacher)->get(route('attendance.index', [
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'date' => '2030-08-02',
    ]))
        ->assertInertia(fn ($page) => $page
            ->where('attendanceExists', true)
            ->where('students.data.0.status', 'excused')
            ->where('students.data.0.remarks', 'Approved absence.'));
});

test('users without attendance permissions and guests cannot access attendance', function () {
    [$teacher, , $year, $section] = createAttendanceScenario();
    $userWithoutPortalAccess = User::factory()->create();

    $this->get(route('attendance.index'))->assertRedirect('/login');
    $this->actingAs($userWithoutPortalAccess)->get(route('attendance.index'))->assertForbidden();
    $this->actingAs($userWithoutPortalAccess)->post(route('attendance.store'), [])->assertForbidden();

    expect($teacher->can('attendance.mark'))->toBeTrue();
});

test('students and parents can view only attendance for their own linked student records', function () {
    [$teacher, , $year, $section] = createAttendanceScenario();
    $linkedStudent = createAttendanceStudent('ATT-PORTAL-LINKED');
    $unlinkedStudent = createAttendanceStudent('ATT-PORTAL-UNLINKED');
    enrolAttendanceStudents($section, $year, [$linkedStudent, $unlinkedStudent]);

    $studentUser = User::factory()->create();
    $studentUser->assignRole('Student');
    $linkedStudent->update(['user_id' => $studentUser->id]);

    $parentUser = User::factory()->create();
    $parentUser->assignRole('Parent');
    $linkedStudent->guardians()->firstOrFail()->update(['user_id' => $parentUser->id]);

    $this->actingAs($teacher)->post(route('attendance.store'), [
        'academic_year_id' => $year->id,
        'section_id' => $section->id,
        'attendance_date' => '2030-08-02',
        'records' => [
            ['student_id' => $linkedStudent->id, 'status' => 'absent', 'remarks' => 'Staff-only remark.'],
            ['student_id' => $unlinkedStudent->id, 'status' => 'present', 'remarks' => 'Other child record.'],
        ],
    ])->assertRedirect();

    $this->actingAs($studentUser)->get(route('attendance.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Portal/Attendance/Index')
            ->where('mode', 'student')
            ->has('records.data', 1)
            ->where('records.data.0.status', 'absent')
            ->missing('records.data.0.remarks'));

    $this->actingAs($parentUser)->get(route('attendance.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Portal/Attendance/Index')
            ->where('mode', 'parent')
            ->has('students', 1)
            ->has('records.data', 1)
            ->where('records.data.0.studentName', 'Riley Learner')
            ->missing('records.data.0.remarks'));

    $this->actingAs($parentUser)->get(route('attendance.index', ['student_id' => $unlinkedStudent->id]))
        ->assertNotFound();
});
