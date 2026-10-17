<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\LibraryLoan;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** /library: staff go to the books inventory; students and parents see the catalogue and their own loans. */
class LibraryController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasRole('Student') && ! $user->hasRole('Parent')) {
            abort_unless($user->can('library.view'), 403);

            return to_route('library.books.index');
        }

        abort_unless($user->can('portal.library.view'), 403);

        $isParent = ! $user->hasRole('Student');
        $students = $isParent
            ? Student::query()->forGuardianAccount($user)->orderBy('first_name')->orderBy('last_name')->get(['students.id', 'first_name', 'last_name'])
            : Student::query()->where('user_id', $user->id)->get(['id', 'first_name', 'last_name']);
        $studentIds = $students->pluck('id');

        $selectedStudentId = null;
        if ($isParent && $request->filled('student_id')) {
            $selectedStudentId = $request->integer('student_id');
            abort_unless($students->contains('id', $selectedStudentId), 404);
            $studentIds = collect([$selectedStudentId]);
        }

        return Inertia::render('Library/Portal', [
            'mode' => $isParent ? 'parent' : 'student',
            'books' => Book::query()
                ->where('is_active', true)
                ->withCount(['copies as available_copies_count' => fn ($query) => $query->where('status', 'available')])
                ->orderBy('title')
                ->get()
                ->map(fn (Book $book): array => [
                    'id' => $book->id,
                    'title' => $book->title,
                    'author' => $book->author,
                    'isbn' => $book->isbn,
                    'category' => $book->category,
                    'availableCopies' => $book->available_copies_count,
                ])->values(),
            'loans' => LibraryLoan::query()
                ->whereIn('student_id', $studentIds)
                ->with(['bookCopy.book:id,title,author', 'student:id,first_name,middle_name,last_name,admission_number'])
                ->orderByDesc('issue_date')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (LibraryLoan $loan): array => [
                    'id' => $loan->id,
                    'bookTitle' => $loan->bookCopy->book->title,
                    'author' => $loan->bookCopy->book->author,
                    'accessionNumber' => $loan->bookCopy->accession_number,
                    'studentName' => trim(implode(' ', array_filter([$loan->student->first_name, $loan->student->middle_name, $loan->student->last_name]))),
                    'issueDate' => $loan->issue_date->toDateString(),
                    'dueDate' => $loan->due_date->toDateString(),
                    'returnedAt' => $loan->returned_at?->toDateString(),
                    'renewalCount' => $loan->renewal_count,
                    'status' => $loan->status === 'issued' && $loan->due_date->lt(today()) ? 'overdue' : $loan->status,
                ]),
            'students' => $isParent
                ? $students->map(fn (Student $student): array => ['id' => $student->id, 'name' => trim($student->first_name.' '.$student->last_name)])->values()
                : [],
            'selectedStudentId' => $selectedStudentId,
        ]);
    }
}