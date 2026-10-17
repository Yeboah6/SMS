<?php

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\AcademicStructureService;
use App\Services\AcademicYearService;
use App\Services\StudentProfileService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function createStructureYear(string $name, string $start, string $end): AcademicYear
{
    return app(AcademicYearService::class)->create([
        'name' => $name,
        'start_date' => $start,
        'end_date' => $end,
    ]);
}

function createSuperAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('Super Admin');

    return $user;
}

function createStructureTeacher(string $staffNumber, string $name): array
{
    $user = User::factory()->create();
    $user->assignRole('Teacher');
    $staff = Staff::query()->create([
        'user_id' => $user->id,
        'staff_number' => $staffNumber,
        'name' => $name,
        'is_teacher' => true,
        'status' => 'active',
    ]);

    return [$user, $staff];
}

function createStudentProfile(string $admissionNumber, string $firstName = 'Jordan'): Student
{
    return app(StudentProfileService::class)->create([
        'admission_number' => $admissionNumber,
        'first_name' => $firstName,
        'middle_name' => null,
        'last_name' => 'Learner',
        'date_of_birth' => now()->subYears(10)->toDateString(),
        'gender' => 'prefer_not_to_say',
        'blood_group' => null,
        'religion' => null,
        'address' => null,
        'admission_date' => today()->toDateString(),
        'status' => 'active',
        'guardians' => [[
            'name' => 'Taylor Learner',
            'phone' => '555-0101',
            'relationship' => 'Parent',
            'is_primary' => true,
            'lives_with_student' => true,
        ]],
    ]);
}

test('classes and sections are scoped to their academic year', function () {
    $service = app(AcademicStructureService::class);
    $firstYear = createStructureYear('2030/2031', '2030-08-01', '2031-06-30');
    $secondYear = createStructureYear('2031/2032', '2031-08-01', '2032-06-30');

    $firstClass = $service->createClass($firstYear, ['name' => 'Class 1']);
    $secondClass = $service->createClass($secondYear, ['name' => 'Class 1']);
    $section = $service->createSection($firstClass, ['name' => 'A', 'capacity' => 30]);

    expect(SchoolClass::query()->forAcademicYear($firstYear)->pluck('id')->all())
        ->toBe([$firstClass->id])
        ->and(SchoolClass::query()->forAcademicYear($secondYear)->pluck('id')->all())
        ->toBe([$secondClass->id])
        ->and($section->academic_year_id)->toBe($firstYear->id)
        ->and($section->schoolClass->academic_year_id)->toBe($firstYear->id);
});

test('subjects can be assigned idempotently to a class', function () {
    $year = createStructureYear('2032/2033', '2032-08-01', '2033-06-30');
    $schoolClass = app(AcademicStructureService::class)->createClass($year, ['name' => 'Class 2']);
    $math = Subject::query()->create(['code' => 'MATH', 'name' => 'Mathematics']);
    $science = Subject::query()->create(['code' => 'SCI', 'name' => 'Science']);
    $service = app(AcademicStructureService::class);

    $service->syncClassSubjects($schoolClass, [$math->id, $science->id]);
    $service->syncClassSubjects($schoolClass, [$math->id, $science->id]);

    expect($schoolClass->classSubjects()->count())->toBe(2);
});

test('teacher assignment requires active teaching staff, class membership, and an assigned subject', function () {
    $year = createStructureYear('2033/2034', '2033-08-01', '2034-06-30');
    $service = app(AcademicStructureService::class);
    $schoolClass = $service->createClass($year, ['name' => 'Class 3']);
    $section = $service->createSection($schoolClass, ['name' => 'B']);
    $subject = Subject::query()->create(['code' => 'ENG', 'name' => 'English']);
    $teacherUser = User::factory()->create();
    $teacherUser->assignRole('Teacher');
    $staff = Staff::query()->create([
        'user_id' => $teacherUser->id,
        'staff_number' => 'T-100',
        'name' => 'Avery Teacher',
        'is_teacher' => true,
        'status' => 'active',
    ]);

    expect(fn () => $service->assignTeacherToSubject($staff, $section, $subject))
        ->toThrow(ValidationException::class);

    $service->syncClassSubjects($schoolClass, [$subject->id]);
    $service->assignTeacherToSection($staff, $section);
    $assignment = $service->assignTeacherToSubject($staff, $section, $subject);

    expect($assignment->academic_year_id)->toBe($year->id)
        ->and($staff->classAssignments()->count())->toBe(1)
        ->and($staff->subjectAssignments()->count())->toBe(1);
});

test('locked academic years reject new class structure', function () {
    $year = createStructureYear('2034/2035', '2034-08-01', '2035-06-30');
    $year->update(['status' => AcademicYearStatus::Locked]);

    expect(fn () => app(AcademicStructureService::class)->createClass($year, ['name' => 'Class 4']))
        ->toThrow(ValidationException::class);

    expect(SchoolClass::query()->forAcademicYear($year)->count())->toBe(0);
});

test('administrators can edit classes and sections within an editable year', function () {
    $admin = createSuperAdmin();
    $year = createStructureYear('2040/2041', '2040-08-01', '2041-06-30');
    $service = app(AcademicStructureService::class);
    $schoolClass = $service->createClass($year, ['name' => 'Class 1']);
    $otherClass = $service->createClass($year, ['name' => 'Class 2']);
    $section = $service->createSection($schoolClass, ['name' => 'A', 'capacity' => 25]);

    $this->actingAs($admin)->patch(route('classes.update', $schoolClass), [
        'name' => 'Class One',
        'sort_order' => 2,
    ])->assertRedirect()->assertSessionHas('status', 'Class updated.');

    $this->actingAs($admin)->patch(route('sections.update', $section), [
        'name' => 'Blue',
        'capacity' => 30,
    ])->assertRedirect()->assertSessionHas('status', 'Section updated.');

    expect($schoolClass->fresh()->name)->toBe('Class One')
        ->and($schoolClass->fresh()->sort_order)->toBe(2)
        ->and($section->fresh()->name)->toBe('Blue')
        ->and($section->fresh()->capacity)->toBe(30)
        ->and($section->fresh()->academic_year_id)->toBe($year->id)
        ->and($otherClass->fresh()->name)->toBe('Class 2');
});

test('class and section edits reject duplicate names and preserve their records', function () {
    $admin = createSuperAdmin();
    $year = createStructureYear('2041/2042', '2041-08-01', '2042-06-30');
    $service = app(AcademicStructureService::class);
    $firstClass = $service->createClass($year, ['name' => 'Class 1']);
    $secondClass = $service->createClass($year, ['name' => 'Class 2']);
    $firstSection = $service->createSection($firstClass, ['name' => 'A']);
    $secondSection = $service->createSection($firstClass, ['name' => 'B']);

    $this->actingAs($admin)->patch(route('classes.update', $firstClass), [
        'name' => 'Class 2',
        'sort_order' => 0,
    ])->assertSessionHasErrors('name');

    $this->actingAs($admin)->patch(route('sections.update', $firstSection), [
        'name' => 'B',
        'capacity' => 20,
    ])->assertSessionHasErrors('name');

    expect($firstClass->fresh()->name)->toBe('Class 1')
        ->and($firstSection->fresh()->name)->toBe('A')
        ->and($secondClass->exists)->toBeTrue()
        ->and($secondSection->exists)->toBeTrue();
});

test('administrators can delete unused classes and sections but not records with dependencies', function () {
    $admin = createSuperAdmin();
    $year = createStructureYear('2042/2043', '2042-08-01', '2043-06-30');
    $service = app(AcademicStructureService::class);
    $unusedClass = $service->createClass($year, ['name' => 'Unused class']);
    $this->actingAs($admin)->delete(route('classes.destroy', $unusedClass))
        ->assertRedirect()->assertSessionHas('status', 'Class deleted.');
    expect(SchoolClass::query()->find($unusedClass->id))->toBeNull();

    $schoolClass = $service->createClass($year, ['name' => 'In-use class']);
    $section = $service->createSection($schoolClass, ['name' => 'A']);
    $this->actingAs($admin)->delete(route('classes.destroy', $schoolClass))
        ->assertSessionHasErrors('schoolClass');
    $this->actingAs($admin)->delete(route('sections.destroy', $section))
        ->assertRedirect()->assertSessionHas('status', 'Section deleted.');

    expect(Section::query()->find($section->id))->toBeNull();

    $teacher = createStructureTeacher('T-DELETE', 'Taylor Assigned')[1];
    $assignedSection = $service->createSection($schoolClass, ['name' => 'B']);
    $service->assignTeacherToSection($teacher, $assignedSection);

    $this->actingAs($admin)->delete(route('sections.destroy', $assignedSection))
        ->assertSessionHasErrors('section');
    expect($assignedSection->fresh())->not->toBeNull();
});

test('locked academic years reject class and section edits and deletion', function () {
    $admin = createSuperAdmin();
    $year = createStructureYear('2043/2044', '2043-08-01', '2044-06-30');
    $service = app(AcademicStructureService::class);
    $schoolClass = $service->createClass($year, ['name' => 'Locked class']);
    $section = $service->createSection($schoolClass, ['name' => 'A']);
    $year->update(['status' => AcademicYearStatus::Locked]);

    $this->actingAs($admin)->patch(route('classes.update', $schoolClass), [
        'name' => 'Changed class',
        'sort_order' => 1,
    ])->assertSessionHasErrors('academic_year');
    $this->actingAs($admin)->delete(route('classes.destroy', $schoolClass))
        ->assertSessionHasErrors('academic_year');
    $this->actingAs($admin)->patch(route('sections.update', $section), [
        'name' => 'Changed section',
        'capacity' => 10,
    ])->assertSessionHasErrors('academic_year');
    $this->actingAs($admin)->delete(route('sections.destroy', $section))
        ->assertSessionHasErrors('academic_year');

    expect($schoolClass->fresh()->name)->toBe('Locked class')
        ->and($section->fresh()->name)->toBe('A');
});

test('class, subject, and staff management pages render only for permitted users', function () {
    $admin = createSuperAdmin();

    $this->actingAs($admin)->get('/classes')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Classes/Index'));
    $this->actingAs($admin)->get('/subjects')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Subjects/Index'));
    $this->actingAs($admin)->get('/staff')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Staff/Index'));

    $student = User::factory()->create();
    $student->assignRole('Student');

    $this->actingAs($student)->get('/classes')->assertForbidden();
    $this->actingAs($student)->get('/subjects')->assertForbidden();
    $this->actingAs($student)->get('/staff')->assertForbidden();
});

test('teacher profiles must be linked to users with the Teacher role', function () {
    $admin = createSuperAdmin();
    $user = User::factory()->create();

    $this->actingAs($admin)->post('/staff', [
        'user_id' => $user->id,
        'staff_number' => 'T-101',
        'is_teacher' => true,
        'name' => 'Unassigned Teacher',
        'status' => 'active',
    ])->assertSessionHasErrors('user_id');

    expect(Staff::query()->count())->toBe(0);
});

test('administrators can edit staff profiles and soft-delete unassigned staff', function () {
    $admin = createSuperAdmin();
    $teacherUser = User::factory()->create();
    $teacherUser->assignRole('Teacher');
    $staff = Staff::query()->create([
        'staff_number' => 'STAFF-EDIT',
        'name' => 'Original Name',
        'is_teacher' => false,
        'status' => 'active',
    ]);

    $this->actingAs($admin)->patch(route('staff.update', $staff), [
        'user_id' => $teacherUser->id,
        'department_id' => '',
        'designation_id' => '',
        'staff_number' => 'STAFF-UPDATED',
        'is_teacher' => true,
        'name' => 'Updated Name',
        'email' => 'updated@example.test',
        'phone' => '555-1010',
        'joining_date' => today()->toDateString(),
        'qualifications' => 'Teaching diploma',
        'status' => 'active',
    ])->assertRedirect()->assertSessionHas('status', 'Staff profile updated.');

    expect($staff->fresh()->name)->toBe('Updated Name')
        ->and($staff->fresh()->staff_number)->toBe('STAFF-UPDATED')
        ->and($staff->fresh()->user_id)->toBe($teacherUser->id)
        ->and($staff->fresh()->is_teacher)->toBeTrue();

    $this->actingAs($admin)->delete(route('staff.destroy', $staff))
        ->assertRedirect()->assertSessionHas('status', 'Staff profile deleted.');

    expect(Staff::query()->find($staff->id))->toBeNull()
        ->and(Staff::withTrashed()->findOrFail($staff->id)->trashed())->toBeTrue();
});

test('staff profiles with teaching assignments cannot be deleted or made non-teaching', function () {
    $admin = createSuperAdmin();
    $year = createStructureYear('2044/2045', '2044-08-01', '2045-06-30');
    $schoolClass = app(AcademicStructureService::class)->createClass($year, ['name' => 'Teacher class']);
    $section = app(AcademicStructureService::class)->createSection($schoolClass, ['name' => 'A']);
    [, $teacher] = createStructureTeacher('T-PROTECTED', 'Assigned Teacher');
    app(AcademicStructureService::class)->assignTeacherToSection($teacher, $section);

    $this->actingAs($admin)->delete(route('staff.destroy', $teacher))
        ->assertSessionHasErrors('staff');
    $this->actingAs($admin)->patch(route('staff.update', $teacher), [
        'user_id' => $teacher->user_id,
        'department_id' => '',
        'designation_id' => '',
        'staff_number' => $teacher->staff_number,
        'is_teacher' => false,
        'name' => $teacher->name,
        'email' => '',
        'phone' => '',
        'joining_date' => '',
        'qualifications' => '',
        'status' => 'active',
    ])->assertSessionHasErrors('is_teacher');

    expect(Staff::query()->find($teacher->id))->not->toBeNull()
        ->and($teacher->fresh()->is_teacher)->toBeTrue();
});

test('students cannot update or delete class, section, or staff records', function () {
    $year = createStructureYear('2045/2046', '2045-08-01', '2046-06-30');
    $schoolClass = app(AcademicStructureService::class)->createClass($year, ['name' => 'Protected class']);
    $section = app(AcademicStructureService::class)->createSection($schoolClass, ['name' => 'A']);
    $staff = Staff::query()->create([
        'staff_number' => 'STAFF-PROTECTED',
        'name' => 'Protected Staff',
        'is_teacher' => false,
        'status' => 'active',
    ]);
    $student = User::factory()->create();
    $student->assignRole('Student');

    $this->actingAs($student)->patch(route('classes.update', $schoolClass), [
        'name' => 'Changed',
        'sort_order' => 1,
    ])->assertForbidden();
    $this->actingAs($student)->delete(route('classes.destroy', $schoolClass))->assertForbidden();
    $this->actingAs($student)->patch(route('sections.update', $section), [
        'name' => 'Changed',
        'capacity' => 20,
    ])->assertForbidden();
    $this->actingAs($student)->delete(route('sections.destroy', $section))->assertForbidden();
    $this->actingAs($student)->patch(route('staff.update', $staff), [
        'staff_number' => 'CHANGED',
    ])->assertForbidden();
    $this->actingAs($student)->delete(route('staff.destroy', $staff))->assertForbidden();

    expect($schoolClass->fresh()->name)->toBe('Protected class')
        ->and($section->fresh()->name)->toBe('A')
        ->and(Staff::query()->find($staff->id))->not->toBeNull();
});

test('authorized administrators can assign teachers to sections and subjects idempotently', function () {
    $admin = createSuperAdmin();
    $year = createStructureYear('2035/2036', '2035-08-01', '2036-06-30');
    $service = app(AcademicStructureService::class);
    $schoolClass = $service->createClass($year, ['name' => 'Class 5']);
    $section = $service->createSection($schoolClass, ['name' => 'C']);
    $subject = Subject::query()->create(['code' => 'ART', 'name' => 'Art']);
    $service->syncClassSubjects($schoolClass, [$subject->id]);
    [, $staff] = createStructureTeacher('T-200', 'Casey Teacher');

    $this->actingAs($admin)->post("/sections/{$section->id}/subject-assignments", [
        'staff_id' => $staff->id,
        'subject_id' => $subject->id,
    ])->assertSessionHasErrors('staff_id');

    $this->actingAs($admin)->post("/sections/{$section->id}/teacher-assignments", [
        'staff_id' => $staff->id,
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->post("/sections/{$section->id}/teacher-assignments", [
        'staff_id' => $staff->id,
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->post("/sections/{$section->id}/subject-assignments", [
        'staff_id' => $staff->id,
        'subject_id' => $subject->id,
    ])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post("/sections/{$section->id}/subject-assignments", [
        'staff_id' => $staff->id,
        'subject_id' => $subject->id,
    ])->assertSessionHasNoErrors();

    expect($staff->classAssignments()->count())->toBe(1)
        ->and($staff->subjectAssignments()->count())->toBe(1);
});

test('teachers see only assigned classes, sections, and subjects', function () {
    $year = createStructureYear('2036/2037', '2036-08-01', '2037-06-30');
    $service = app(AcademicStructureService::class);
    $assignedClass = $service->createClass($year, ['name' => 'Class 6']);
    $unassignedClass = $service->createClass($year, ['name' => 'Class 7']);
    $assignedSection = $service->createSection($assignedClass, ['name' => 'A']);
    $service->createSection($unassignedClass, ['name' => 'B']);
    $assignedSubject = Subject::query()->create(['code' => 'HIST', 'name' => 'History']);
    $unassignedSubject = Subject::query()->create(['code' => 'GEO', 'name' => 'Geography']);
    $service->syncClassSubjects($assignedClass, [$assignedSubject->id]);
    [, $staff] = createStructureTeacher('T-201', 'Riley Teacher');
    $service->assignTeacherToSection($staff, $assignedSection);
    $service->assignTeacherToSubject($staff, $assignedSection, $assignedSubject);

    $this->actingAs($staff->user)->get("/classes?academic_year_id={$year->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Classes/Index')
            ->has('classes', 1)
            ->where('classes.0.id', $assignedClass->id)
            ->has('classes.0.sections', 1)
            ->where('classes.0.sections.0.id', $assignedSection->id)
            ->has('classes.0.subjects', 1)
            ->where('classes.0.subjects.0.id', $assignedSubject->id));

    $this->actingAs($staff->user)->get('/subjects')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('subjects.data', fn ($subjects) => count($subjects) === 1 && $subjects[0]['id'] === $assignedSubject->id));

    expect($unassignedSubject->id)->not->toBe($assignedSubject->id);
});

test('students cannot create teacher assignments', function () {
    $year = createStructureYear('2037/2038', '2037-08-01', '2038-06-30');
    $schoolClass = app(AcademicStructureService::class)->createClass($year, ['name' => 'Class 8']);
    $section = app(AcademicStructureService::class)->createSection($schoolClass, ['name' => 'A']);
    $student = User::factory()->create();
    $student->assignRole('Student');

    $this->actingAs($student)->post("/sections/{$section->id}/teacher-assignments", [
        'staff_id' => 1,
    ])->assertForbidden();
});

test('receptionists can create permanent student profiles with multiple guardians', function () {
    Storage::fake('local');
    $receptionist = User::factory()->create();
    $receptionist->assignRole('Receptionist');

    $this->actingAs($receptionist)->post('/students', [
        'admission_number' => 'STU-001',
        'first_name' => 'Morgan',
        'middle_name' => '',
        'last_name' => 'Student',
        'date_of_birth' => now()->subYears(12)->toDateString(),
        'gender' => 'female',
        'blood_group' => 'A+',
        'religion' => '',
        'address' => '12 School Street',
        'admission_date' => today()->toDateString(),
        'status' => 'active',
        'photo' => UploadedFile::fake()->image('student.jpg'),
        'guardians' => [
            ['name' => 'Alex Student', 'phone' => '555-0111', 'email' => '', 'occupation' => '', 'relationship' => 'Mother', 'is_primary' => true, 'lives_with_student' => true],
            ['name' => 'Sam Student', 'phone' => '555-0112', 'email' => '', 'occupation' => '', 'relationship' => 'Father', 'is_primary' => false, 'lives_with_student' => false],
        ],
    ])->assertRedirect();

    $student = Student::query()->with('guardians')->firstOrFail();

    expect($student->admission_number)->toBe('STU-001')
        ->and($student->guardians)->toHaveCount(2)
        ->and($student->guardians->where('pivot.is_primary', true))->toHaveCount(1)
        ->and($student->photo_path)->not->toBeNull();
});

test('student enrolments preserve each academic year and reject mismatched or locked placements', function () {
    $student = createStudentProfile('STU-002');
    $service = app(AcademicStructureService::class);
    $profileService = app(StudentProfileService::class);
    $firstYear = createStructureYear('2038/2039', '2038-08-01', '2039-06-30');
    $secondYear = createStructureYear('2039/2040', '2039-08-01', '2040-06-30');
    $firstClass = $service->createClass($firstYear, ['name' => 'Class 1']);
    $secondClass = $service->createClass($secondYear, ['name' => 'Class 1']);
    $firstSection = $service->createSection($firstClass, ['name' => 'A']);
    $secondSection = $service->createSection($secondClass, ['name' => 'A']);

    $profileService->enrol($student, [
        'academic_year_id' => $firstYear->id,
        'class_id' => $firstClass->id,
        'section_id' => $firstSection->id,
        'enrolment_date' => '2038-08-15',
    ]);
    $profileService->enrol($student, [
        'academic_year_id' => $secondYear->id,
        'class_id' => $secondClass->id,
        'section_id' => $secondSection->id,
        'enrolment_date' => '2039-08-15',
    ]);

    expect($student->enrolments()->count())->toBe(2);
    expect(fn () => $profileService->enrol($student, [
        'academic_year_id' => $firstYear->id,
        'class_id' => $firstClass->id,
        'section_id' => $firstSection->id,
        'enrolment_date' => '2038-09-01',
    ]))->toThrow(ValidationException::class);

    expect(fn () => $profileService->enrol($student, [
        'academic_year_id' => $firstYear->id,
        'class_id' => $firstClass->id,
        'section_id' => $secondSection->id,
        'enrolment_date' => '2038-09-01',
    ]))->toThrow(ValidationException::class);

    $lockedYear = createStructureYear('2040/2041', '2040-08-01', '2041-06-30');
    $lockedClass = $service->createClass($lockedYear, ['name' => 'Class 1']);
    $lockedSection = $service->createSection($lockedClass, ['name' => 'A']);
    $lockedYear->update(['status' => AcademicYearStatus::Locked]);

    expect(fn () => $profileService->enrol($student, [
        'academic_year_id' => $lockedYear->id,
        'class_id' => $lockedClass->id,
        'section_id' => $lockedSection->id,
        'enrolment_date' => '2040-08-15',
    ]))->toThrow(ValidationException::class);
});

test('teachers can only view students enrolled in their assigned active-year sections', function () {
    $year = createStructureYear('2041/2042', '2041-08-01', '2042-06-30');
    app(AcademicYearService::class)->activate($year);
    $service = app(AcademicStructureService::class);
    $schoolClass = $service->createClass($year, ['name' => 'Class 9']);
    $section = $service->createSection($schoolClass, ['name' => 'A']);
    $otherClass = $service->createClass($year, ['name' => 'Class 10']);
    $otherSection = $service->createSection($otherClass, ['name' => 'B']);
    [$teacher, $staff] = createStructureTeacher('T-301', 'Taylor Teacher');
    $service->assignTeacherToSection($staff, $section);
    $visibleStudent = createStudentProfile('STU-003', 'Visible');
    $hiddenStudent = createStudentProfile('STU-004', 'Hidden');
    app(StudentProfileService::class)->enrol($visibleStudent, [
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
        'section_id' => $section->id,
        'enrolment_date' => '2041-08-15',
    ]);
    app(StudentProfileService::class)->enrol($hiddenStudent, [
        'academic_year_id' => $year->id,
        'class_id' => $otherClass->id,
        'section_id' => $otherSection->id,
        'enrolment_date' => '2041-08-15',
    ]);

    $this->actingAs($teacher)->get('/students')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('students.data', 1)
            ->where('students.data.0.id', $visibleStudent->id));
    $this->actingAs($teacher)->get("/students/{$visibleStudent->id}")->assertOk();
    $this->actingAs($teacher)->get("/students/{$hiddenStudent->id}")->assertNotFound();
});

test('student documents are stored privately and downloads are student-scoped', function () {
    Storage::fake('local');
    $receptionist = User::factory()->create();
    $receptionist->assignRole('Receptionist');
    $student = createStudentProfile('STU-005', 'Secure');
    $otherStudent = createStudentProfile('STU-006', 'Unrelated');

    $this->actingAs($receptionist)->post("/students/{$student->id}/documents", [
        'category' => 'birth_certificate',
        'document' => UploadedFile::fake()->create('birth-certificate.pdf', 120, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    $document = $student->documents()->firstOrFail();
    Storage::disk('local')->assertExists($document->storage_path);

    $this->actingAs($receptionist)->get(route('students.documents.download', [$student, $document]))
        ->assertOk()
        ->assertDownload('birth-certificate.pdf');
    $this->actingAs($receptionist)->get(route('students.documents.download', [$otherStudent, $document]))
        ->assertNotFound();

    $this->actingAs($receptionist)->get(route('students.photo', $student))->assertOk();
    $studentUser = User::factory()->create();
    $studentUser->assignRole('Student');
    $this->actingAs($studentUser)->get(route('students.photo', $student))->assertForbidden();
});
