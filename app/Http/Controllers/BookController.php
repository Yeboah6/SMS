<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddBookCopiesRequest;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\LibraryLoan;
use App\Services\LibraryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The books inventory: every title, and the physical copies of it. */
class BookController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('library.view'), 403);

        $search = $request->string('search')->trim()->toString();
        $category = $request->string('category')->trim()->toString();
        $stock = $request->string('stock')->toString();
        $state = in_array($request->string('state')->toString(), ['inactive', 'all'], true) ? $request->string('state')->toString() : 'active';

        $books = Book::query()
            ->withCount([
                'copies',
                'copies as available_copies_count' => fn ($query) => $query->where('status', 'available'),
                'copies as issued_copies_count' => fn ($query) => $query->where('status', 'issued'),
            ])
            ->when($state !== 'all', fn ($query) => $query->where('is_active', $state === 'active'))
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('title', 'like', "%{$search}%")
                ->orWhere('author', 'like', "%{$search}%")
                ->orWhere('isbn', 'like', "%{$search}%")))
            ->when($stock === 'available', fn ($query) => $query->whereHas('copies', fn ($copies) => $copies->where('status', 'available')))
            ->when($stock === 'unavailable', fn ($query) => $query->whereDoesntHave('copies', fn ($copies) => $copies->where('status', 'available')))
            ->orderBy('title')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Book $book): array => [
                'id' => $book->id,
                'title' => $book->title,
                'author' => $book->author,
                'isbn' => $book->isbn,
                'category' => $book->category,
                'isActive' => (bool) $book->is_active,
                'copies' => $book->copies_count,
                'available' => $book->available_copies_count,
                'issued' => $book->issued_copies_count,
            ]);

        $totals = BookCopy::query()
            ->selectRaw("COUNT(*) as copies, COALESCE(SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END), 0) as available, COALESCE(SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END), 0) as issued")
            ->first();

        return Inertia::render('Library/Books/Index', [
            'books' => $books,
            'filters' => ['search' => $search, 'category' => $category, 'stock' => $stock, 'state' => $state],
            'categories' => Book::query()->distinct()->orderBy('category')->pluck('category'),
            'totals' => [
                'titles' => Book::query()->where('is_active', true)->count(),
                'copies' => (int) ($totals->copies ?? 0),
                'available' => (int) ($totals->available ?? 0),
                'issued' => (int) ($totals->issued ?? 0),
                'overdue' => LibraryLoan::query()->where('status', 'issued')->whereDate('due_date', '<', today())->count(),
            ],
            'can' => [
                'create' => $user->can('library.create'),
                'update' => $user->can('library.update'),
                'delete' => $user->can('library.delete'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->can('library.create'), 403);

        return Inertia::render('Library/Books/Create');
    }

    public function store(StoreBookRequest $request, LibraryService $service): RedirectResponse
    {
        $book = $service->createBook($request->validated(), $request->user());

        return to_route('library.books.show', $book)->with('status', "{$book->title} added with {$book->copies->count()} copies.");
    }

    public function show(Request $request, Book $book): Response
    {
        $user = $request->user();
        abort_unless($user->can('library.view'), 403);

        $book->load(['copies' => fn ($copies) => $copies->orderBy('accession_number')]);
        $copyIds = $book->copies->pluck('id');

        $onLoan = LibraryLoan::query()
            ->where('status', 'issued')
            ->whereIn('book_copy_id', $copyIds)
            ->with('student:id,first_name,middle_name,last_name,admission_number')
            ->get()
            ->keyBy('book_copy_id');
        $everLoaned = LibraryLoan::query()->whereIn('book_copy_id', $copyIds)->distinct()->pluck('book_copy_id');

        $history = LibraryLoan::query()
            ->whereIn('book_copy_id', $copyIds)
            ->with(['bookCopy:id,accession_number', 'student:id,first_name,middle_name,last_name,admission_number'])
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        return Inertia::render('Library/Books/Show', [
            'book' => [
                'id' => $book->id,
                'title' => $book->title,
                'author' => $book->author,
                'isbn' => $book->isbn,
                'category' => $book->category,
                'publisher' => $book->publisher,
                'description' => $book->description,
                'isActive' => (bool) $book->is_active,
            ],
            'stock' => [
                'copies' => $book->copies->count(),
                'available' => $book->copies->where('status', 'available')->count(),
                'issued' => $book->copies->where('status', 'issued')->count(),
            ],
            'copies' => $book->copies->map(function (BookCopy $copy) use ($onLoan, $everLoaned): array {
                $loan = $onLoan->get($copy->id);

                return [
                    'id' => $copy->id,
                    'accessionNumber' => $copy->accession_number,
                    'shelfLocation' => $copy->shelf_location,
                    'status' => $copy->status,
                    'canRemove' => $copy->status === 'available' && ! $everLoaned->contains($copy->id),
                    'loan' => $loan ? [
                        'id' => $loan->id,
                        'studentName' => $this->studentName($loan->student),
                        'admissionNumber' => $loan->student->admission_number,
                        'dueDate' => $loan->due_date->toDateString(),
                        'overdue' => $loan->due_date->lt(today()),
                    ] : null,
                ];
            })->values(),
            'history' => $history->map(fn (LibraryLoan $loan): array => [
                'id' => $loan->id,
                'accessionNumber' => $loan->bookCopy->accession_number,
                'studentName' => $this->studentName($loan->student),
                'admissionNumber' => $loan->student->admission_number,
                'issueDate' => $loan->issue_date->toDateString(),
                'dueDate' => $loan->due_date->toDateString(),
                'returnedAt' => $loan->returned_at?->toDateString(),
                'status' => $loan->status === 'issued' && $loan->due_date->lt(today()) ? 'overdue' : $loan->status,
            ])->values(),
            'can' => [
                'update' => $user->can('library.update'),
                'delete' => $user->can('library.delete') && $everLoaned->isEmpty(),
                'addCopies' => $user->can('library.create'),
                'issue' => $user->can('library.issue'),
                'return' => $user->can('library.return'),
            ],
        ]);
    }

    public function edit(Request $request, Book $book): Response
    {
        abort_unless($request->user()->can('library.update'), 403);

        return Inertia::render('Library/Books/Edit', [
            'book' => [
                'id' => $book->id,
                'title' => $book->title,
                'author' => $book->author,
                'isbn' => $book->isbn,
                'category' => $book->category,
                'publisher' => $book->publisher,
                'description' => $book->description,
                'isActive' => (bool) $book->is_active,
            ],
        ]);
    }

    public function update(UpdateBookRequest $request, Book $book, LibraryService $service): RedirectResponse
    {
        $service->updateBook($book, $request->validated(), $request->user());

        return to_route('library.books.show', $book)->with('status', 'Book updated.');
    }

    public function destroy(Request $request, Book $book, LibraryService $service): RedirectResponse
    {
        abort_unless($request->user()->can('library.delete'), 403);
        $service->deleteBook($book, $request->user());

        return to_route('library.books.index')->with('status', 'Book deleted.');
    }

    public function addCopies(AddBookCopiesRequest $request, Book $book, LibraryService $service): RedirectResponse
    {
        $count = $service->addCopies(
            $book,
            $request->integer('count'),
            $request->validated('shelf_location'),
            $request->user(),
        );

        return back()->with('status', "{$count} copies added.");
    }

    private function studentName($student): string
    {
        return trim(implode(' ', array_filter([$student->first_name, $student->middle_name, $student->last_name])));
    }
}