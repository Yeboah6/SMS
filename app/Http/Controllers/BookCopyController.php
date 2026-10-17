<?php

namespace App\Http\Controllers;

use App\Http\Requests\IssueBookRequest;
use App\Http\Requests\UpdateBookCopyRequest;
use App\Models\BookCopy;
use App\Services\LibraryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BookCopyController extends Controller
{
    public function update(UpdateBookCopyRequest $request, BookCopy $bookCopy, LibraryService $service): RedirectResponse
    {
        $service->updateCopy($bookCopy, $request->validated('shelf_location'), $request->user());

        return back()->with('status', 'Copy updated.');
    }

    public function destroy(Request $request, BookCopy $bookCopy, LibraryService $service): RedirectResponse
    {
        abort_unless($request->user()->can('library.delete'), 403);
        $service->deleteCopy($bookCopy, $request->user());

        return back()->with('status', 'Copy deleted.');
    }

    public function issue(IssueBookRequest $request, BookCopy $bookCopy, LibraryService $service): RedirectResponse
    {
        $service->issue(
            $bookCopy,
            $request->integer('student_id'),
            $request->validated('due_date'),
            $request->user(),
        );

        return back()->with('status', 'Book issued to the student.');
    }
}