<?php

namespace App\Services;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\LibraryLoan;
use App\Models\Student;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LibraryService
{
    /**
     * @param  array{title: string, author: string, isbn?: ?string, category: string, publisher?: ?string, description?: ?string, copies: int, shelf_location?: ?string}  $data
     */
    public function createBook(array $data, User $actor): Book
    {
        $this->authorize($actor, 'library.create');

        return DB::transaction(function () use ($data, $actor): Book {
            $book = Book::query()->create([
                'created_by' => $actor->id,
                'title' => $data['title'],
                'author' => $data['author'],
                'isbn' => $data['isbn'] ?? null,
                'category' => $data['category'],
                'publisher' => $data['publisher'] ?? null,
                'description' => $data['description'] ?? null,
                'is_active' => true,
            ]);

            $this->createCopies($book, $data['copies'], $data['shelf_location'] ?? null);

            return $book->load('copies');
        });
    }

    public function addCopies(Book $book, int $count, ?string $shelfLocation, User $actor): int
    {
        $this->authorize($actor, 'library.create');

        return DB::transaction(function () use ($book, $count, $shelfLocation): int {
            $lockedBook = Book::query()->lockForUpdate()->findOrFail($book->id);
            if (! $lockedBook->is_active) {
                throw ValidationException::withMessages([
                    'book' => 'Copies cannot be added to an inactive catalogue record.',
                ]);
            }

            $this->createCopies($lockedBook, $count, $shelfLocation);

            return $count;
        });
    }

    public function issue(BookCopy $bookCopy, int $studentId, string $dueDate, User $actor): LibraryLoan
    {
        $this->authorize($actor, 'library.issue');

        return DB::transaction(function () use ($bookCopy, $studentId, $dueDate, $actor): LibraryLoan {
            $copy = BookCopy::query()->lockForUpdate()->findOrFail($bookCopy->id);
            $book = Book::query()->lockForUpdate()->findOrFail($copy->book_id);
            if ($copy->status !== 'available' || ! $book->is_active) {
                throw ValidationException::withMessages([
                    'book_copy' => 'This book copy is not currently available to borrow.',
                ]);
            }

            $student = Student::query()->lockForUpdate()->findOrFail($studentId);
            if ($student->status !== 'active') {
                throw ValidationException::withMessages([
                    'student_id' => 'Only active students can borrow books.',
                ]);
            }

            $due = Carbon::parse($dueDate);
            if ($due->lte(today())) {
                throw ValidationException::withMessages([
                    'due_date' => 'The due date must be after the issue date.',
                ]);
            }

            $loan = LibraryLoan::query()->create([
                'book_copy_id' => $copy->id,
                'student_id' => $student->id,
                'issued_by' => $actor->id,
                'issue_date' => today(),
                'due_date' => $due,
                'status' => 'issued',
            ]);
            $copy->update(['status' => 'issued']);

            return $loan->load(['bookCopy.book', 'student']);
        });
    }

    public function returnLoan(LibraryLoan $libraryLoan, User $actor): LibraryLoan
    {
        $this->authorize($actor, 'library.return');

        return DB::transaction(function () use ($libraryLoan, $actor): LibraryLoan {
            $loan = LibraryLoan::query()->lockForUpdate()->findOrFail($libraryLoan->id);
            if ($loan->status !== 'issued') {
                throw ValidationException::withMessages([
                    'loan' => 'This book has already been returned.',
                ]);
            }

            $copy = BookCopy::query()->lockForUpdate()->findOrFail($loan->book_copy_id);
            $loan->update([
                'status' => 'returned',
                'returned_by' => $actor->id,
                'returned_at' => now(),
            ]);
            $copy->update(['status' => 'available']);

            return $loan->refresh();
        });
    }

    public function renew(LibraryLoan $libraryLoan, string $dueDate, User $actor): LibraryLoan
    {
        $this->authorize($actor, 'library.renew');

        return DB::transaction(function () use ($libraryLoan, $dueDate): LibraryLoan {
            $loan = LibraryLoan::query()->lockForUpdate()->findOrFail($libraryLoan->id);
            if ($loan->status !== 'issued') {
                throw ValidationException::withMessages([
                    'loan' => 'Only an active loan can be renewed.',
                ]);
            }
            if ($loan->due_date->lt(today())) {
                throw ValidationException::withMessages([
                    'due_date' => 'Overdue loans must be returned before they can be renewed.',
                ]);
            }

            $newDueDate = Carbon::parse($dueDate);
            if ($newDueDate->lte($loan->due_date)) {
                throw ValidationException::withMessages([
                    'due_date' => 'A renewed due date must be later than the current due date.',
                ]);
            }

            $loan->update([
                'due_date' => $newDueDate,
                'renewal_count' => $loan->renewal_count + 1,
            ]);

            return $loan->refresh();
        });
    }

    public function updateBook(Book $book, array $data, User $actor): Book
    {
        if (! $actor->can('library.update')) {
            throw new AuthorizationException('You are not allowed to edit library books.');
        }
    
        return DB::transaction(function () use ($book, $data): Book {
            $book = Book::query()->lockForUpdate()->findOrFail($book->id);
    
            // A title cannot be taken off the shelf while copies are out on loan.
            if (array_key_exists('is_active', $data) && ! $data['is_active']
                && LibraryLoan::query()->where('status', 'issued')->whereIn('book_copy_id', $book->copies()->select('id'))->exists()) {
                throw ValidationException::withMessages([
                    'is_active' => 'Some copies of this title are on loan. Deactivate it after they are returned.',
                ]);
            }
    
            $book->update($data);
    
            return $book->refresh();
        });
    }

    public function deleteBook(Book $book, User $actor): void
    {
        if (! $actor->can('library.delete')) {
            throw new AuthorizationException('You are not allowed to delete library books.');
        }
    
        DB::transaction(function () use ($book): void {
            $book = Book::query()->lockForUpdate()->findOrFail($book->id);
    
            if (LibraryLoan::query()->whereIn('book_copy_id', $book->copies()->select('id'))->exists()) {
                throw ValidationException::withMessages([
                    'book' => 'This title has loan history and cannot be deleted. Deactivate it instead.',
                ]);
            }
    
            $book->copies()->delete();
            $book->delete();
        });
    }
    
    public function updateCopy(BookCopy $copy, ?string $shelfLocation, User $actor): BookCopy
    {
        if (! $actor->can('library.update')) {
            throw new AuthorizationException('You are not allowed to edit book copies.');
        }
    
        $copy->update(['shelf_location' => $shelfLocation]);
    
        return $copy->refresh();
    }
    
    /** Removes a copy that is on the shelf and was never loaned. */
    public function deleteCopy(BookCopy $copy, User $actor): void
    {
        if (! $actor->can('library.delete')) {
            throw new AuthorizationException('You are not allowed to delete book copies.');
        }
    
        DB::transaction(function () use ($copy): void {
            $copy = BookCopy::query()->lockForUpdate()->findOrFail($copy->id);
    
            if ($copy->status !== 'available' || LibraryLoan::query()->where('book_copy_id', $copy->id)->exists()) {
                throw ValidationException::withMessages([
                    'copy' => 'Only a copy that is on the shelf and has never been loaned can be deleted.',
                ]);
            }
    
            $copy->delete();
        });
    }

    private function createCopies(Book $book, int $count, ?string $shelfLocation): void
    {
        $nextNumber = (int) $book->copies()->count() + 1;
        for ($number = $nextNumber; $number < $nextNumber + $count; $number++) {
            $book->copies()->create([
                'accession_number' => sprintf('BK-%06d-%03d', $book->id, $number),
                'shelf_location' => $shelfLocation,
                'status' => 'available',
            ]);
        }
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $actor->can($permission)) {
            throw new AuthorizationException('You are not allowed to manage this library operation.');
        }
    }
}
