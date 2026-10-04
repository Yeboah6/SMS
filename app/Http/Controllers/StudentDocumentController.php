<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentDocumentRequest;
use App\Models\Student;
use App\Models\StudentDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentDocumentController extends Controller
{
    public function store(
        StoreStudentDocumentRequest $request,
        Student $student,
    ): RedirectResponse {
        $file = $request->file('document');
        $storagePath = $file->storeAs(
            "student-documents/{$student->id}",
            Str::uuid().'.'.$file->extension(),
            'local',
        );

        if (! $storagePath) {
            abort(500, 'The student document could not be stored.');
        }

        $student->documents()->create([
            'uploaded_by' => $request->user()->id,
            'category' => $request->validated('category'),
            'original_name' => $file->getClientOriginalName(),
            'storage_path' => $storagePath,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ]);

        return back()->with('status', 'Student document uploaded.');
    }

    public function download(Student $student, StudentDocument $document): StreamedResponse
    {
        $this->authorize('view', $student);
        abort_unless($document->student_id === $student->id, 404);
        abort_unless(Storage::disk('local')->exists($document->storage_path), 404);

        return Storage::disk('local')->download($document->storage_path, $document->original_name);
    }

    public function photo(Student $student)
    {
        $this->authorize('view', $student);

        if (! $student->photo_path) {
            $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="240" height="240" viewBox="0 0 240 240" role="img" aria-label="Student profile photo placeholder">
  <rect width="240" height="240" fill="#e5e7eb"/>
  <circle cx="120" cy="88" r="42" fill="#cbd5e1"/>
  <path d="M60 188c10-30 36-46 60-46s50 16 60 46" fill="#cbd5e1"/>
</svg>
SVG;

            return response($svg, 200, ['Content-Type' => 'image/svg+xml']);
        }

        abort_unless(Storage::disk('local')->exists($student->photo_path), 404);

        return response()->file(Storage::disk('local')->path($student->photo_path));
    }
}
