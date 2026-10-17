<?php

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Guardian;
use App\Models\LibraryLoan;
use App\Models\Student;
use App\Models\User;
use App\Services\LibraryService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * @return array{User, Book}
 */
function createLibraryScenario(): array
{
    $librarian = User::factory()->create();
    $librarian->assignRole('Librarian');
    $book = app(LibraryService::class)->createBook([
        'title' => 'The School Library',
        'author' => 'A. Writer',
        'isbn' => '9780000000001',
        'category' => 'Education',
        'copies' => 2,
        'shelf_location' => 'A-01',
    ], $librarian);

    return [$librarian, $book];
}

/**
 * @return array{User, Student}
 */
function createLibraryStudent(string $admissionNumber, string $status = 'active'): array
{
    $user = User::factory()->create();
    $user->assignRole('Student');
    $student = Student::query()->create([
        'user_id' => $user->id,
        'admission_number' => $admissionNumber,
        'first_name' => 'Morgan',
        'last_name' => 'Reader',
        'date_of_birth' => today()->subYears(12)->toDateString(),
        'gender' => 'prefer_not_to_say',
        'admission_date' => today()->subYear()->toDateString(),
        'status' => $status,
    ]);

    return [$user, $student];
}

test('librarians can create catalogue titles with uniquely accessioned copies', function () {
    [$librarian] = createLibraryScenario();

    $this->actingAs($librarian)->post(route('library.books.store'), [
        'title' => 'Science Reader',
        'author' => 'M. Author',
        'isbn' => '9780000000002',
        'category' => 'Science',
        'copies' => 3,
        'shelf_location' => 'B-12',
    ])->assertRedirect()
        ->assertSessionHas('status', 'Science Reader added with 3 copies.');

    $book = Book::query()->where('isbn', '9780000000002')->firstOrFail();
    expect($book->copies)->toHaveCount(3)
        ->and($book->copies->pluck('accession_number')->unique())->toHaveCount(3)
        ->and($book->copies->every(fn (BookCopy $copy): bool => $copy->status === 'available'))->toBeTrue();
});

test('librarians can add copies and duplicate ISBNs are rejected', function () {
    [$librarian, $book] = createLibraryScenario();

    $this->actingAs($librarian)->post(route('library.books.copies.store', $book), [
        'count' => 2,
        'shelf_location' => 'C-03',
    ])->assertRedirect()
        ->assertSessionHas('status', '2 copies added.');

    $this->actingAs($librarian)->post(route('library.books.store'), [
        'title' => 'Duplicate ISBN',
        'author' => 'Another Author',
        'isbn' => '9780000000001',
        'category' => 'Reference',
        'copies' => 1,
    ])->assertSessionHasErrors('isbn');

    expect($book->copies()->count())->toBe(4);
    $this->assertDatabaseCount('books', 1);
});

test('librarians can issue an available copy to an active student and cannot issue the same copy twice', function () {
    [$librarian, $book] = createLibraryScenario();
    [, $student] = createLibraryStudent('LIB-STUDENT-001');
    $copy = $book->copies->first();
    $dueDate = today()->addDays(14)->toDateString();

    $this->actingAs($librarian)->post(route('library.copies.issue', $copy), [
        'student_id' => $student->id,
        'due_date' => $dueDate,
    ])->assertRedirect()
        ->assertSessionHas('status', 'Book issued to the student.');

    $loan = LibraryLoan::query()->firstOrFail();
    expect($loan->status)->toBe('issued')
        ->and($loan->due_date->toDateString())->toBe($dueDate)
        ->and($copy->fresh()->status)->toBe('issued');

    $this->actingAs($librarian)->post(route('library.copies.issue', $copy), [
        'student_id' => $student->id,
        'due_date' => today()->addDays(14)->toDateString(),
    ])->assertSessionHasErrors('book_copy');

    $this->assertDatabaseCount('library_loans', 1);
});

test('inactive students cannot borrow and invalid loan dates are rejected', function () {
    [$librarian, $book] = createLibraryScenario();
    [, $student] = createLibraryStudent('LIB-STUDENT-002', 'inactive');
    $copy = $book->copies->first();

    $this->actingAs($librarian)->post(route('library.copies.issue', $copy), [
        'student_id' => $student->id,
        'due_date' => today()->addDays(7)->toDateString(),
    ])->assertSessionHasErrors('student_id');

    $this->actingAs($librarian)->post(route('library.copies.issue', $copy), [
        'student_id' => $student->id,
        'due_date' => today()->toDateString(),
    ])->assertSessionHasErrors('due_date');

    $this->assertDatabaseCount('library_loans', 0);
    expect($copy->fresh()->status)->toBe('available');
});

test('librarians can renew active loans and record returns exactly once', function () {
    [$librarian, $book] = createLibraryScenario();
    [, $student] = createLibraryStudent('LIB-STUDENT-003');
    $copy = $book->copies->first();
    $loan = app(LibraryService::class)->issue(
        $copy,
        $student->id,
        today()->addDays(7)->toDateString(),
        $librarian,
    );

    $this->actingAs($librarian)->post(route('library.loans.renew', $loan), [
        'due_date' => today()->addDays(14)->toDateString(),
    ])->assertRedirect()
        ->assertSessionHas('status', 'Loan due date extended.');

    expect($loan->fresh()->renewal_count)->toBe(1)
        ->and($loan->fresh()->due_date->toDateString())->toBe(today()->addDays(14)->toDateString());

    $this->actingAs($librarian)->post(route('library.loans.return', $loan))
        ->assertRedirect()
        ->assertSessionHas('status', 'Book return recorded.');
    expect($loan->fresh()->status)->toBe('returned')
        ->and($loan->fresh()->returned_by)->toBe($librarian->id)
        ->and($copy->fresh()->status)->toBe('available');

    $this->actingAs($librarian)->post(route('library.loans.return', $loan))
        ->assertSessionHasErrors('loan');
    $this->assertDatabaseCount('library_loans', 1);
});

test('overdue or returned loans cannot be renewed', function () {
    [$librarian, $book] = createLibraryScenario();
    [, $student] = createLibraryStudent('LIB-STUDENT-004');
    $copy = $book->copies->first();
    $copy->update(['status' => 'issued']);
    $loan = LibraryLoan::query()->create([
        'book_copy_id' => $copy->id,
        'student_id' => $student->id,
        'issued_by' => $librarian->id,
        'issue_date' => today()->subDays(10)->toDateString(),
        'due_date' => today()->subDay()->toDateString(),
        'status' => 'issued',
    ]);

    $this->actingAs($librarian)->post(route('library.loans.renew', $loan), [
        'due_date' => today()->addDays(7)->toDateString(),
    ])->assertSessionHasErrors('due_date');

    $loan->update(['status' => 'returned', 'returned_at' => now()]);
    $this->actingAs($librarian)->post(route('library.loans.renew', $loan), [
        'due_date' => today()->addDays(7)->toDateString(),
    ])->assertSessionHasErrors('loan');
});

test('students and linked parents can view only their own library loans', function () {
    [$librarian, $book] = createLibraryScenario();
    [$studentUser, $student] = createLibraryStudent('LIB-STUDENT-005');
    [, $otherStudent] = createLibraryStudent('LIB-STUDENT-006');
    $copies = $book->copies;
    app(LibraryService::class)->issue($copies[0], $student->id, today()->addDays(7)->toDateString(), $librarian);
    app(LibraryService::class)->issue($copies[1], $otherStudent->id, today()->addDays(7)->toDateString(), $librarian);

    $this->actingAs($studentUser)->get(route('library.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Library/Index')
            ->where('mode', 'student')
            ->has('loans.data', 1)
            ->where('loans.data.0.admissionNumber', 'LIB-STUDENT-005'));

    $parent = User::factory()->create();
    $parent->assignRole('Parent');
    $guardian = Guardian::query()->create([
        'user_id' => $parent->id,
        'name' => 'Morgan Guardian',
        'phone' => '555-0202',
        'email' => $parent->email,
    ]);
    $guardian->students()->attach($student->id, [
        'relationship' => 'parent',
        'is_primary' => true,
        'lives_with_student' => true,
    ]);

    $this->actingAs($parent)->get(route('library.index'))
        ->assertInertia(fn ($page) => $page
            ->where('mode', 'parent')
            ->has('loans.data', 1)
            ->where('loans.data.0.admissionNumber', 'LIB-STUDENT-005'));
});

test('guests and users without library permissions cannot access circulation', function () {
    [$librarian, $book] = createLibraryScenario();
    $user = User::factory()->create();

    $this->get(route('library.index'))->assertRedirect(route('login'));
    $this->actingAs($user)->get(route('library.index'))->assertForbidden();
    $this->actingAs($user)->post(route('library.books.store'), [
        'title' => 'Unauthorized Book',
        'author' => 'Nobody',
        'category' => 'General',
        'copies' => 1,
    ])->assertForbidden();

    expect($book->copies()->count())->toBe(2);
});
