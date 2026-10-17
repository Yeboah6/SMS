<?php

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Enrolment;
use App\Models\Guardian;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherClassAssignment;
use App\Models\TeacherSubjectAssignment;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Services\AcademicYearService;
use App\Services\TimetableService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * @return array{
 *   principal: User,
 *   teacher: User,
 *   secondTeacher: User,
 *   year: AcademicYear,
 *   section: Section,
 *   otherSection: Section,
 *   mathAssignment: TeacherSubjectAssignment,
 *   scienceAssignment: TeacherSubjectAssignment,
 *   otherSectionMathAssignment: TeacherSubjectAssignment
 * }
 */
function createTimetableScenario(): array
{
    $principal = User::factory()->create();
    $principal->assignRole('Principal');

    $teacher = User::factory()->create(['name' => 'Alex Teacher']);
    $teacher->assignRole('Teacher');
    $teacherStaff = Staff::query()->create([
        'user_id' => $teacher->id,
        'staff_number' => 'TIME-TEACHER-001',
        'is_teacher' => true,
        'name' => $teacher->name,
        'status' => 'active',
    ]);

    $secondTeacher = User::factory()->create(['name' => 'Sam Teacher']);
    $secondTeacher->assignRole('Teacher');
    $secondTeacherStaff = Staff::query()->create([
        'user_id' => $secondTeacher->id,
        'staff_number' => 'TIME-TEACHER-002',
        'is_teacher' => true,
        'name' => $secondTeacher->name,
        'status' => 'active',
    ]);

    $year = app(AcademicYearService::class)->create([
        'name' => 'Timetable Test Year',
        'start_date' => today()->subMonth()->toDateString(),
        'end_date' => today()->addMonths(6)->toDateString(),
    ]);
    app(AcademicYearService::class)->activate($year);
    $schoolClass = $year->classes()->create(['name' => 'Schedule Class']);
    $section = $schoolClass->sections()->create([
        'academic_year_id' => $year->id,
        'name' => 'Red',
    ]);
    $otherSection = $schoolClass->sections()->create([
        'academic_year_id' => $year->id,
        'name' => 'Blue',
    ]);
    $math = Subject::query()->create(['code' => 'TIME-MATH', 'name' => 'Mathematics']);
    $science = Subject::query()->create(['code' => 'TIME-SCIENCE', 'name' => 'Science']);
    foreach ([$math, $science] as $subject) {
        ClassSubject::query()->create([
            'academic_year_id' => $year->id,
            'class_id' => $schoolClass->id,
            'subject_id' => $subject->id,
        ]);
    }

    foreach ([[$teacherStaff, $section], [$teacherStaff, $otherSection], [$secondTeacherStaff, $section]] as [$staff, $assignedSection]) {
        TeacherClassAssignment::query()->create([
            'academic_year_id' => $year->id,
            'staff_id' => $staff->id,
            'section_id' => $assignedSection->id,
        ]);
    }

    $mathAssignment = TeacherSubjectAssignment::query()->create([
        'academic_year_id' => $year->id,
        'staff_id' => $teacherStaff->id,
        'section_id' => $section->id,
        'subject_id' => $math->id,
    ]);
    $scienceAssignment = TeacherSubjectAssignment::query()->create([
        'academic_year_id' => $year->id,
        'staff_id' => $secondTeacherStaff->id,
        'section_id' => $section->id,
        'subject_id' => $science->id,
    ]);
    $otherSectionMathAssignment = TeacherSubjectAssignment::query()->create([
        'academic_year_id' => $year->id,
        'staff_id' => $teacherStaff->id,
        'section_id' => $otherSection->id,
        'subject_id' => $math->id,
    ]);

    return compact(
        'principal',
        'teacher',
        'secondTeacher',
        'year',
        'section',
        'otherSection',
        'mathAssignment',
        'scienceAssignment',
        'otherSectionMathAssignment',
    );
}

/**
 * @param  array{academic_year_id: int, teacher_subject_assignment_id: int, day_of_week?: int, starts_at?: string, ends_at?: string, room?: ?string}  $overrides
 */
function createTimetableSlot(array $overrides, User $principal): TimetableSlot
{
    return app(TimetableService::class)->create([
        'day_of_week' => 1,
        'starts_at' => '08:00',
        'ends_at' => '09:00',
        ...$overrides,
    ], $principal);
}

test('principals can create a period for an assigned subject and teacher', function () {
    $scenario = createTimetableScenario();

    $this->actingAs($scenario['principal'])->post(route('timetable.store'), [
        'academic_year_id' => $scenario['year']->id,
        'teacher_subject_assignment_id' => $scenario['mathAssignment']->id,
        'day_of_week' => 1,
        'starts_at' => '08:00',
        'ends_at' => '09:00',
        'room' => 'Room 4',
    ])->assertRedirect()
        ->assertSessionHas('status', 'Timetable period added.');

    $this->assertDatabaseHas('timetable_slots', [
        'academic_year_id' => $scenario['year']->id,
        'teacher_subject_assignment_id' => $scenario['mathAssignment']->id,
        'day_of_week' => 1,
        'starts_at' => '08:00',
        'ends_at' => '09:00',
        'room' => 'Room 4',
    ]);
});

test('the same section cannot receive overlapping periods for different subjects', function () {
    $scenario = createTimetableScenario();
    createTimetableSlot([
        'academic_year_id' => $scenario['year']->id,
        'teacher_subject_assignment_id' => $scenario['mathAssignment']->id,
    ], $scenario['principal']);

    $this->actingAs($scenario['principal'])->post(route('timetable.store'), [
        'academic_year_id' => $scenario['year']->id,
        'teacher_subject_assignment_id' => $scenario['scienceAssignment']->id,
        'day_of_week' => 1,
        'starts_at' => '08:30',
        'ends_at' => '09:30',
    ])->assertSessionHasErrors('section_id');

    $this->assertDatabaseCount('timetable_slots', 1);
});

test('a teacher cannot be scheduled for overlapping periods in different sections', function () {
    $scenario = createTimetableScenario();
    createTimetableSlot([
        'academic_year_id' => $scenario['year']->id,
        'teacher_subject_assignment_id' => $scenario['mathAssignment']->id,
    ], $scenario['principal']);

    $this->actingAs($scenario['principal'])->post(route('timetable.store'), [
        'academic_year_id' => $scenario['year']->id,
        'teacher_subject_assignment_id' => $scenario['otherSectionMathAssignment']->id,
        'day_of_week' => 1,
        'starts_at' => '08:30',
        'ends_at' => '09:30',
    ])->assertSessionHasErrors('teacher_subject_assignment_id');

    $this->assertDatabaseCount('timetable_slots', 1);
});

test('adjacent periods and periods on different days do not conflict', function () {
    $scenario = createTimetableScenario();
    createTimetableSlot([
        'academic_year_id' => $scenario['year']->id,
        'teacher_subject_assignment_id' => $scenario['mathAssignment']->id,
    ], $scenario['principal']);

    $this->actingAs($scenario['principal'])->post(route('timetable.store'), [
        'academic_year_id' => $scenario['year']->id,
        'teacher_subject_assignment_id' => $scenario['scienceAssignment']->id,
        'day_of_week' => 1,
        'starts_at' => '09:00',
        'ends_at' => '10:00',
    ])->assertRedirect();

    $this->actingAs($scenario['principal'])->post(route('timetable.store'), [
        'academic_year_id' => $scenario['year']->id,
        'teacher_subject_assignment_id' => $scenario['otherSectionMathAssignment']->id,
        'day_of_week' => 2,
        'starts_at' => '08:30',
        'ends_at' => '09:30',
    ])->assertRedirect();

    $this->assertDatabaseCount('timetable_slots', 3);
});

test('period updates are validated and locked academic years remain read only', function () {
    $scenario = createTimetableScenario();
    $slot = createTimetableSlot([
        'academic_year_id' => $scenario['year']->id,
        'teacher_subject_assignment_id' => $scenario['mathAssignment']->id,
    ], $scenario['principal']);

    $this->actingAs($scenario['principal'])->put(route('timetable.update', $slot), [
        'teacher_subject_assignment_id' => $scenario['mathAssignment']->id,
        'day_of_week' => 2,
        'starts_at' => '10:00',
        'ends_at' => '11:00',
        'room' => 'Room 8',
    ])->assertRedirect()
        ->assertSessionHas('status', 'Timetable period updated.');

    expect($slot->fresh()->day_of_week)->toBe(2)
        ->and($slot->fresh()->room)->toBe('Room 8');

    $scenario['year']->update(['status' => AcademicYearStatus::Locked]);
    $this->actingAs($scenario['principal'])->delete(route('timetable.destroy', $slot))
        ->assertSessionHasErrors('academic_year_id');
    $this->assertModelExists($slot->fresh());
});

test('schedule reads are scoped to the teacher and the student or linked parent section', function () {
    $scenario = createTimetableScenario();
    createTimetableSlot([
        'academic_year_id' => $scenario['year']->id,
        'teacher_subject_assignment_id' => $scenario['mathAssignment']->id,
    ], $scenario['principal']);
    createTimetableSlot([
        'academic_year_id' => $scenario['year']->id,
        'teacher_subject_assignment_id' => $scenario['scienceAssignment']->id,
        'day_of_week' => 2,
    ], $scenario['principal']);
    createTimetableSlot([
        'academic_year_id' => $scenario['year']->id,
        'teacher_subject_assignment_id' => $scenario['otherSectionMathAssignment']->id,
        'day_of_week' => 3,
    ], $scenario['principal']);

    $studentUser = User::factory()->create();
    $studentUser->assignRole('Student');
    $student = Student::query()->create([
        'user_id' => $studentUser->id,
        'admission_number' => 'TIME-STUDENT-001',
        'first_name' => 'Jamie',
        'last_name' => 'Student',
        'date_of_birth' => today()->subYears(12)->toDateString(),
        'gender' => 'prefer_not_to_say',
        'admission_date' => $scenario['year']->start_date->toDateString(),
        'status' => 'active',
    ]);
    Enrolment::query()->create([
        'academic_year_id' => $scenario['year']->id,
        'student_id' => $student->id,
        'class_id' => $scenario['section']->class_id,
        'section_id' => $scenario['section']->id,
        'enrolment_date' => $scenario['year']->start_date->toDateString(),
        'status' => 'active',
    ]);

    $this->actingAs($scenario['teacher'])->get(route('timetable.index', [
        'academic_year_id' => $scenario['year']->id,
    ]))->assertInertia(fn ($page) => $page
        ->where('mode', 'teacher')
        ->where('slots.data', fn ($slots): bool => collect($slots)->pluck('subject')->all() === [
            'Mathematics',
            'Mathematics',
        ]));

    $this->actingAs($studentUser)->get(route('timetable.index'))
        ->assertInertia(fn ($page) => $page
            ->where('mode', 'student')
            ->where('slots.data', fn ($slots): bool => collect($slots)->pluck('subject')->all() === [
                'Mathematics',
                'Science',
            ]));

    $parent = User::factory()->create();
    $parent->assignRole('Parent');
    $guardian = Guardian::query()->create([
        'user_id' => $parent->id,
        'name' => 'Jamie Parent',
        'phone' => '555-0111',
        'email' => $parent->email,
    ]);
    $guardian->students()->attach($student->id, [
        'relationship' => 'parent',
        'is_primary' => true,
        'lives_with_student' => true,
    ]);

    $this->actingAs($parent)->get(route('timetable.index'))
        ->assertInertia(fn ($page) => $page
            ->where('mode', 'parent')
            ->where('slots.data', fn ($slots): bool => collect($slots)->pluck('subject')->all() === [
                'Mathematics',
                'Science',
            ]));
});

test('a timetable assignment must belong to the selected academic year', function () {
    $scenario = createTimetableScenario();
    $otherYear = app(AcademicYearService::class)->create([
        'name' => 'Other Schedule Year',
        'start_date' => today()->addMonths(8)->toDateString(),
        'end_date' => today()->addMonths(14)->toDateString(),
    ]);

    $this->actingAs($scenario['principal'])->post(route('timetable.store'), [
        'academic_year_id' => $otherYear->id,
        'teacher_subject_assignment_id' => $scenario['mathAssignment']->id,
        'day_of_week' => 1,
        'starts_at' => '08:00',
        'ends_at' => '09:00',
    ])->assertSessionHasErrors('teacher_subject_assignment_id');

    $this->assertDatabaseCount('timetable_slots', 0);
});

test('guests and users without timetable permissions cannot manage schedules', function () {
    $scenario = createTimetableScenario();
    $user = User::factory()->create();

    $this->get(route('timetable.index'))->assertRedirect(route('login'));
    $this->actingAs($user)->get(route('timetable.index'))->assertForbidden();
    $this->actingAs($user)->post(route('timetable.store'), [
        'academic_year_id' => $scenario['year']->id,
        'teacher_subject_assignment_id' => $scenario['mathAssignment']->id,
        'day_of_week' => 1,
        'starts_at' => '08:00',
        'ends_at' => '09:00',
    ])->assertForbidden();

    $this->assertDatabaseCount('timetable_slots', 0);
});
