<?php

use App\Models\AcademicYear;
use App\Models\ClassSupplyRequirement;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Guardian;
use App\Models\InventoryItem;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Notifications\NoticePublishedNotification;
use App\Services\AcademicStructureService;
use App\Services\AcademicYearService;
use App\Services\StudentProfileService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function taggedPaginationAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('Super Admin');

    return $user;
}

function taggedPaginationYear(string $name, bool $active = false): AcademicYear
{
    $service = app(AcademicYearService::class);
    $year = $service->create([
        'name' => $name,
        'start_date' => today()->subMonth()->toDateString(),
        'end_date' => today()->addMonths(6)->toDateString(),
    ]);

    if ($active) {
        $service->activate($year);
    }

    return $year;
}

function taggedPaginationStudent(string $admissionNumber, string $firstName): Student
{
    return app(StudentProfileService::class)->create([
        'admission_number' => $admissionNumber,
        'first_name' => $firstName,
        'middle_name' => null,
        'last_name' => 'Learner',
        'date_of_birth' => today()->subYears(10)->toDateString(),
        'gender' => 'prefer_not_to_say',
        'blood_group' => null,
        'religion' => null,
        'address' => null,
        'admission_date' => today()->toDateString(),
        'status' => 'active',
        'guardians' => [],
    ]);
}

test('section student rows are paginated while preserving the page query', function (): void {
    $admin = taggedPaginationAdmin();
    $year = taggedPaginationYear('Section Pagination Year');
    $schoolClass = app(AcademicStructureService::class)->createClass($year, ['name' => 'Section Pagination Class']);
    $section = app(AcademicStructureService::class)->createSection($schoolClass, ['name' => 'A']);
    $profiles = app(StudentProfileService::class);

    for ($index = 1; $index <= 16; $index++) {
        $student = taggedPaginationStudent(sprintf('PAG-%03d', $index), sprintf('Student %02d', $index));
        $profiles->enrol($student, [
            'academic_year_id' => $year->id,
            'class_id' => $schoolClass->id,
            'section_id' => $section->id,
            'enrolment_date' => today()->toDateString(),
        ]);
    }

    $this->actingAs($admin)->get(route('sections.show', $section))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Sections/Show')
            ->has('students.data', 15)
            ->where('students.total', 16)
            ->where('students.from', 1)
            ->where('students.to', 15));

    $this->actingAs($admin)->get(route('sections.show', [$section, 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('students.current_page', 2)
            ->has('students.data', 1)
            ->where('students.total', 16));
});

test('subject classes and assignments have independent pagination and preserve teacher scope', function (): void {
    $admin = taggedPaginationAdmin();
    $year = taggedPaginationYear('Subject Pagination Year');
    $service = app(AcademicStructureService::class);
    $subject = Subject::query()->create(['code' => 'PAG-MATH', 'name' => 'Pagination Mathematics']);
    $teacherUser = User::factory()->create();
    $teacherUser->assignRole('Teacher');
    $teacher = Staff::query()->create([
        'user_id' => $teacherUser->id,
        'staff_number' => 'PAG-TEACHER-001',
        'name' => 'Assigned Teacher',
        'is_teacher' => true,
        'status' => 'active',
    ]);

    for ($index = 1; $index <= 16; $index++) {
        $schoolClass = $service->createClass($year, ['name' => sprintf('Class %02d', $index)]);
        $section = $service->createSection($schoolClass, ['name' => 'A']);
        $service->syncClassSubjects($schoolClass, [$subject->id]);
        $service->assignTeacherToSection($teacher, $section);
        $service->assignTeacherToSubject($teacher, $section, $subject);
    }

    $otherUser = User::factory()->create();
    $otherUser->assignRole('Teacher');
    $otherTeacher = Staff::query()->create([
        'user_id' => $otherUser->id,
        'staff_number' => 'PAG-TEACHER-002',
        'name' => 'Other Teacher',
        'is_teacher' => true,
        'status' => 'active',
    ]);
    $otherClass = $service->createClass($year, ['name' => 'Class 17']);
    $otherSection = $service->createSection($otherClass, ['name' => 'A']);
    $service->syncClassSubjects($otherClass, [$subject->id]);
    $service->assignTeacherToSection($otherTeacher, $otherSection);
    $service->assignTeacherToSubject($otherTeacher, $otherSection, $subject);

    $this->actingAs($admin)->get(route('subjects.show', ['subject' => $subject, 'classes_page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Subjects/Show')
            ->where('classes.current_page', 2)
            ->where('classes.total', 17)
            ->where('assignments.current_page', 1)
            ->where('assignments.total', 17));

    $this->actingAs($admin)->get(route('subjects.show', ['subject' => $subject, 'assignments_page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('classes.current_page', 1)
            ->where('assignments.current_page', 2));

    $this->actingAs($teacherUser)->get(route('subjects.show', ['subject' => $subject]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('classes', null)
            ->where('assignments.total', 16));
});

test('department and designation staff lists are paginated', function (): void {
    $admin = taggedPaginationAdmin();
    $department = Department::query()->create(['name' => 'Pagination Department']);
    $designation = Designation::query()->create(['name' => 'Pagination Designation']);

    for ($index = 1; $index <= 16; $index++) {
        Staff::query()->create([
            'staff_number' => sprintf('PAG-STAFF-%03d', $index),
            'name' => sprintf('Staff %02d', $index),
            'is_teacher' => false,
            'status' => 'active',
            'department_id' => $department->id,
            'designation_id' => $designation->id,
        ]);
    }

    $this->actingAs($admin)->get(route('departments.show', $department))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Departments/Show')
            ->has('staff.data', 15)
            ->where('staff.total', 16));

    $this->actingAs($admin)->get(route('designations.show', [$designation, 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Designations/Show')
            ->where('staff.current_page', 2)
            ->has('staff.data', 1)
            ->where('staff.total', 16));
});

test('department and designation indexes paginate their record lists', function (): void {
    $admin = taggedPaginationAdmin();

    for ($index = 1; $index <= 16; $index++) {
        Department::query()->create(['name' => sprintf('Department %02d', $index)]);
        Designation::query()->create(['name' => sprintf('Designation %02d', $index)]);
    }

    $this->actingAs($admin)->get(route('departments.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Departments/Index')
            ->has('items.data', 15)
            ->where('items.total', 16)
            ->has('items.links'));

    $this->actingAs($admin)->get(route('designations.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Designations/Index')
            ->where('items.current_page', 2)
            ->has('items.data', 1)
            ->where('items.total', 16));
});

test('notice notifications paginate independently from the notice board', function (): void {
    $admin = taggedPaginationAdmin();

    for ($index = 1; $index <= 16; $index++) {
        $admin->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => NoticePublishedNotification::class,
            'data' => [
                'title' => "Notice {$index}",
                'body' => 'A school update.',
            ],
            'read_at' => null,
            'created_at' => now()->subMinutes($index),
            'updated_at' => now()->subMinutes($index),
        ]);
    }

    $this->actingAs($admin)->get(route('notices.index', ['notifications_page' => 2, 'search' => '']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Notices/Index')
            ->where('notifications.current_page', 2)
            ->has('notifications.data', 1)
            ->where('notifications.total', 16)
            ->where('notices.current_page', 1));
});

test('book requirements paginate while the PDF keeps the complete item collection', function (): void {
    $year = taggedPaginationYear('Requirements Pagination Year', active: true);
    $schoolClass = $year->classes()->create(['name' => 'Requirements Pagination Class']);
    $section = $schoolClass->sections()->create(['academic_year_id' => $year->id, 'name' => 'A']);
    $student = taggedPaginationStudent('PAG-RESULT-001', 'Result Student');
    app(StudentProfileService::class)->enrol($student, [
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
        'section_id' => $section->id,
        'enrolment_date' => today()->toDateString(),
    ]);
    $parent = User::factory()->create();
    $parent->assignRole('Parent');
    $guardian = Guardian::query()->create([
        'user_id' => $parent->id,
        'name' => 'Pagination Parent',
        'phone' => '555-0191',
    ]);
    $student->guardians()->attach($guardian->id, [
        'relationship' => 'Parent',
        'is_primary' => true,
        'lives_with_student' => true,
    ]);

    for ($index = 1; $index <= 16; $index++) {
        $item = InventoryItem::factory()->create(['name' => sprintf('Required Item %02d', $index)]);
        ClassSupplyRequirement::query()->create([
            'academic_year_id' => $year->id,
            'class_id' => $schoolClass->id,
            'item_id' => $item->id,
            'quantity_per_student' => 1,
            'is_required' => true,
            'notes' => null,
        ]);
    }

    $this->actingAs($parent)->get(route('results.index', [
        'student_id' => $student->id,
        'book_requirements_page' => 2,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Results/Index')
            ->where('bookRequirements.items.current_page', 2)
            ->has('bookRequirements.items.data', 1)
            ->where('bookRequirements.items.total', 16)
            ->where('results.current_page', 1));

    $this->actingAs($parent)->get(route('results.book-requirements.download', $student))
        ->assertDownload("book-requirements-{$student->id}.pdf");
});
