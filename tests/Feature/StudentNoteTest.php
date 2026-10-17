<?php

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentActivity;
use App\Models\StudentNote;
use App\Models\User;
use App\Services\AcademicStructureService;
use App\Services\AcademicYearService;
use App\Services\StudentProfileService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function createStudentNoteTeacher(): array
{
    $user = User::factory()->create(['name' => 'Avery Teacher']);
    $user->assignRole('Teacher');
    $staff = Staff::query()->create([
        'user_id' => $user->id,
        'staff_number' => 'NOTE-T-001',
        'name' => 'Avery Teacher',
        'is_teacher' => true,
        'status' => 'active',
    ]);

    return [$user, $staff];
}

function createStudentForNotes(string $admissionNumber): Student
{
    return app(StudentProfileService::class)->create([
        'admission_number' => $admissionNumber,
        'first_name' => 'Alex',
        'middle_name' => null,
        'last_name' => 'Learner',
        'date_of_birth' => today()->subYears(10)->toDateString(),
        'gender' => 'prefer_not_to_say',
        'blood_group' => null,
        'religion' => null,
        'address' => null,
        'admission_date' => today()->toDateString(),
        'status' => 'active',
        'guardians' => [[
            'name' => 'Jamie Guardian',
            'phone' => '555-0166',
            'relationship' => 'Parent',
            'is_primary' => true,
            'lives_with_student' => true,
        ]],
    ]);
}

/**
 * @return array{AcademicYear, SchoolClass, Section}
 */
function enrolStudentForNotes(Staff $staff, Student $student): array
{
    $year = app(AcademicYearService::class)->create([
        'name' => 'Notes test academic year',
        'start_date' => today()->subMonth()->toDateString(),
        'end_date' => today()->addMonths(10)->toDateString(),
    ]);
    app(AcademicYearService::class)->activate($year);
    $structure = app(AcademicStructureService::class);
    $schoolClass = $structure->createClass($year, ['name' => 'Class 4']);
    $section = $structure->createSection($schoolClass, ['name' => 'A']);
    $structure->assignTeacherToSection($staff, $section);
    app(StudentProfileService::class)->enrol($student, [
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
        'section_id' => $section->id,
        'enrolment_date' => today()->toDateString(),
    ]);

    return [$year, $schoolClass, $section];
}

test('assigned teachers can create and view student notes and download private attachments', function () {
    Storage::fake('local');
    [$teacher, $staff] = createStudentNoteTeacher();
    $student = createStudentForNotes('NOTE-STU-001');
    [$year, $schoolClass, $section] = enrolStudentForNotes($staff, $student);

    $this->actingAs($teacher)->post(route('students.notes.store', $student), [
        'note_type' => 'academic',
        'title' => 'Reading progress',
        'content' => 'Alex completed the assigned reading.',
        'note_date' => today()->toDateString(),
        'visibility' => 'staff_only',
        'author_id' => 99999,
        'attachment' => UploadedFile::fake()->create('reading.pdf', 120, 'application/pdf'),
    ])->assertRedirect()
        ->assertSessionHas('status', 'Student note added.');

    $note = StudentNote::query()->firstOrFail();
    expect($note->author_id)->toBe($teacher->id)
        ->and($note->attachment_name)->toBe('reading.pdf');
    Storage::disk('local')->assertExists($note->attachment_path);

    $this->actingAs($teacher)->get(route('students.show', $student))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canViewNotes', true)
            ->where('student.notes.0.title', 'Reading progress')
            ->where('student.notes.0.author', 'Avery Teacher'));

    $this->actingAs($teacher)->get(route('students.notes.attachment', [$student, $note]))
        ->assertOk()
        ->assertDownload('reading.pdf');

    $otherStudent = createStudentForNotes('NOTE-STU-005');
    app(StudentProfileService::class)->enrol($otherStudent, [
        'academic_year_id' => $year->id,
        'class_id' => $schoolClass->id,
        'section_id' => $section->id,
        'enrolment_date' => today()->toDateString(),
    ]);
    $this->actingAs($teacher)->get(route('students.notes.attachment', [$otherStudent, $note]))
        ->assertNotFound();
});

test('teachers cannot read or create notes for students outside their assignments', function () {
    [$teacher, $staff] = createStudentNoteTeacher();
    $assignedStudent = createStudentForNotes('NOTE-STU-002');
    enrolStudentForNotes($staff, $assignedStudent);
    $unassignedStudent = createStudentForNotes('NOTE-STU-003');

    $this->actingAs($teacher)->get(route('students.show', $unassignedStudent))->assertNotFound();
    $this->actingAs($teacher)->post(route('students.notes.store', $unassignedStudent), [
        'note_type' => 'general',
        'title' => 'Unauthorized note',
        'content' => 'This must not be saved.',
        'note_date' => today()->toDateString(),
        'visibility' => 'staff_only',
    ])->assertNotFound();

    expect(StudentNote::query()->count())->toBe(0);
});

test('students cannot create notes and invalid note values are rejected', function () {
    $student = createStudentForNotes('NOTE-STU-004');
    $this->post(route('students.notes.store', $student), [])
        ->assertRedirect('/login');

    $studentUser = User::factory()->create();
    $studentUser->assignRole('Student');

    $this->actingAs($studentUser)->post(route('students.notes.store', $student), [
        'note_type' => 'general',
        'title' => 'Unauthorized note',
        'content' => 'Students cannot add staff notes.',
        'note_date' => today()->toDateString(),
        'visibility' => 'staff_only',
    ])->assertForbidden();

    [$teacher, $staff] = createStudentNoteTeacher();
    enrolStudentForNotes($staff, $student);

    $this->actingAs($teacher)->post(route('students.notes.store', $student), [
        'note_type' => 'unrecognized',
        'title' => '',
        'content' => '',
        'note_date' => today()->addDay()->toDateString(),
        'visibility' => 'everyone',
    ])->assertSessionHasErrors(['note_type', 'title', 'content', 'note_date', 'visibility']);

    expect(StudentNote::query()->count())->toBe(0);
});

test('note authors can browse edit replace attachments and delete notes with permanent audit entries', function () {
    Storage::fake('local');
    [$teacher, $staff] = createStudentNoteTeacher();
    $student = createStudentForNotes('NOTE-CRUD-001');
    [, , $section] = enrolStudentForNotes($staff, $student);

    $otherTeacher = User::factory()->create(['name' => 'Casey Teacher']);
    $otherTeacher->assignRole('Teacher');
    $otherStaff = Staff::query()->create([
        'user_id' => $otherTeacher->id,
        'staff_number' => 'NOTE-T-002',
        'name' => 'Casey Teacher',
        'is_teacher' => true,
        'status' => 'active',
    ]);
    app(AcademicStructureService::class)->assignTeacherToSection($otherStaff, $section);

    $this->actingAs($teacher)->post(route('students.notes.store', $student), [
        'note_type' => 'academic',
        'title' => 'Initial progress',
        'content' => 'The first observation.',
        'note_date' => today()->toDateString(),
        'visibility' => 'staff_only',
        'attachment' => UploadedFile::fake()->create('initial.pdf', 100, 'application/pdf'),
    ])->assertRedirect();

    $note = StudentNote::query()->firstOrFail();
    $originalAttachment = $note->attachment_path;

    $this->actingAs($otherTeacher)->get(route('students.notes.edit', [$student, $note]))
        ->assertForbidden();
    $this->actingAs($otherTeacher)->delete(route('students.notes.destroy', [$student, $note]))
        ->assertForbidden();

    $this->actingAs($teacher)->get(route('students.notes.index', $student))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('StudentNotes/Index')
            ->where('notes.data.0.title', 'Initial progress'));

    $this->actingAs($teacher)->get(route('students.notes.show', [$student, $note]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('StudentNotes/Show'));

    $this->actingAs($teacher)->get(route('students.notes.edit', [$student, $note]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('StudentNotes/Edit')
            ->where('note.content', 'The first observation.'));

    $this->actingAs($teacher)->put(route('students.notes.update', [$student, $note]), [
        'note_type' => 'academic',
        'title' => 'Updated progress',
        'content' => 'The observation was corrected.',
        'note_date' => today()->toDateString(),
        'visibility' => 'staff_only',
        'attachment' => UploadedFile::fake()->create('updated.pdf', 120, 'application/pdf'),
    ])->assertRedirect(route('students.notes.show', [$student, $note]));

    $note->refresh();
    expect($note->title)->toBe('Updated progress')
        ->and($note->content)->toBe('The observation was corrected.')
        ->and($note->attachment_name)->toBe('updated.pdf');
    Storage::disk('local')->assertMissing($originalAttachment);
    Storage::disk('local')->assertExists($note->attachment_path);

    $replacementAttachment = $note->attachment_path;
    $updateActivity = StudentActivity::query()->where('event_type', 'note.updated')->firstOrFail();
    expect($updateActivity->details['content_changed'])->toBeTrue();

    $this->actingAs($teacher)->put(route('students.notes.update', [$student, $note]), [
        'note_type' => 'academic',
        'title' => 'Updated progress',
        'content' => 'The observation was corrected.',
        'note_date' => today()->toDateString(),
        'visibility' => 'staff_only',
        'remove_attachment' => true,
    ])->assertRedirect();
    $note->refresh();
    expect($note->attachment_path)->toBeNull();
    Storage::disk('local')->assertMissing($replacementAttachment);

    $this->actingAs($teacher)->put(route('students.notes.update', [$student, $note]), [
        'note_type' => 'academic',
        'title' => 'Updated progress',
        'content' => 'The observation was corrected.',
        'note_date' => today()->toDateString(),
        'visibility' => 'staff_only',
        'attachment' => UploadedFile::fake()->create('final.pdf', 125, 'application/pdf'),
    ])->assertRedirect();
    $note->refresh();

    $updatedAttachment = $note->attachment_path;
    $this->actingAs($teacher)->delete(route('students.notes.destroy', [$student, $note]))
        ->assertRedirect(route('students.notes.index', $student));

    $this->assertDatabaseMissing('student_notes', ['id' => $note->id]);
    Storage::disk('local')->assertMissing($updatedAttachment);
    expect(StudentActivity::query()->where('student_id', $student->id)->whereIn('event_type', [
        'note.created',
        'note.updated',
        'note.deleted',
    ])->count())->toBe(5);

    $this->actingAs($teacher)->get(route('students.show', $student))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canViewAuditHistory', true)
            ->has('auditEntries.data'));
});
