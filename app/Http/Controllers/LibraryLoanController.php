<?php

namespace App\Http\Controllers;

use App\Http\Requests\RenewBookRequest;
use App\Http\Requests\ReturnBookRequest;
use App\Models\LibraryLoan;
use App\Models\Student;
use App\Services\LibraryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** The loan register. Books are issued from a copy on the book page; they are returned and renewed here. */
class LibraryLoanController extends Controller
{
    private const STATUSES = ['issued', 'overdue', 'returned'];

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('library.view'), 403);

        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();
        $today = today()->toDateString();

        $stats = LibraryLoan::query()->selectRaw(
            "COUNT(*) as total, "
            ."COALESCE(SUM(CASE WHEN status = 'issued' AND due_date >= ? THEN 1 ELSE 0 END), 0) as issued_count, "
            ."COALESCE(SUM(CASE WHEN status = 'issued' AND due_date < ? THEN 1 ELSE 0 END), 0) as overdue_count, "
            ."COALESCE(SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END), 0) as returned_count",
            [$today, $today],
        )->first();

        $loans = LibraryLoan::query()
            ->when($status === 'issued', fn ($query) => $query->where('status', 'issued')->whereDate('due_date', '>=', $today))
            ->when($status === 'overdue', fn ($query) => $query->where('status', 'issued')->whereDate('due_date', '<', $today))
            ->when($status === 'returned', fn ($query) => $query->where('status', 'returned'))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereHas('bookCopy', fn ($copies) => $copies
                    ->where('accession_number', 'like', "%{$search}%")
                    ->orWhereHas('book', fn ($books) => $books->where('title', 'like', "%{$search}%")->orWhere('author', 'like', "%{$search}%")))
                ->orWhereHas('student', fn ($students) => $students
                    ->where('admission_number', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%"))))
            ->with(['bookCopy.book:id,title,author', 'student:id,first_name,middle_name,last_name,admission_number'])
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (LibraryLoan $loan): array => [
                'id' => $loan->id,
                'bookId' => $loan->bookCopy->book->id,
                'bookTitle' => $loan->bookCopy->book->title,
                'author' => $loan->bookCopy->book->author,
                'accessionNumber' => $loan->bookCopy->accession_number,
                'studentName' => trim(implode(' ', array_filter([$loan->student->first_name, $loan->student->middle_name, $loan->student->last_name]))),
                'admissionNumber' => $loan->student->admission_number,
                'issueDate' => $loan->issue_date->toDateString(),
                'dueDate' => $loan->due_date->toDateString(),
                'returnedAt' => $loan->returned_at?->toDateString(),
                'renewalCount' => $loan->renewal_count,
                'status' => $loan->status === 'issued' && $loan->due_date->lt(today()) ? 'overdue' : $loan->status,
            ]);

        return Inertia::render('Library/Loans/Index', [
            'loans' => $loans,
            'filters' => ['search' => $search, 'status' => in_array($status, self::STATUSES, true) ? $status : ''],
            'counts' => [
                'all' => (int) ($stats->total ?? 0),
                'issued' => (int) ($stats->issued_count ?? 0),
                'overdue' => (int) ($stats->overdue_count ?? 0),
                'returned' => (int) ($stats->returned_count ?? 0),
            ],
            'can' => [
                'return' => $user->can('library.return'),
                'renew' => $user->can('library.renew'),
            ],
        ]);
    }

    public function recordReturn(ReturnBookRequest $request, LibraryLoan $libraryLoan, LibraryService $service): RedirectResponse
    {
        $service->returnLoan($libraryLoan, $request->user());

        return back()->with('status', 'Book return recorded.');
    }

    public function renew(RenewBookRequest $request, LibraryLoan $libraryLoan, LibraryService $service): RedirectResponse
    {
        $service->renew($libraryLoan, $request->validated('due_date'), $request->user());

        return back()->with('status', 'Loan due date extended.');
    }

    /** Type-ahead for the "issue to" field, so the page never loads every student. */
    public function students(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('library.issue'), 403);

        $term = $request->string('q')->trim()->toString();
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        return response()->json(
            Student::query()
                ->where('status', 'active')
                ->where(fn ($query) => $query
                    ->where('admission_number', 'like', "%{$term}%")
                    ->orWhere('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%"))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->limit(10)
                ->get(['id', 'first_name', 'middle_name', 'last_name', 'admission_number'])
                ->map(fn (Student $student): array => [
                    'id' => $student->id,
                    'name' => trim(implode(' ', array_filter([$student->first_name, $student->middle_name, $student->last_name]))),
                    'admissionNumber' => $student->admission_number,
                ])->values(),
        );
    }
}