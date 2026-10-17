<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentNoteRequest;
use App\Http\Requests\UpdateStudentNoteRequest;
use App\Models\Student;
use App\Models\StudentNote;
use App\Services\StudentNoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentNoteController extends Controller
{
    public function index(Request $request, Student $student): Response
    {
        $this->authorize('viewAny', [StudentNote::class, $student]);
        $notes = $student->notes()
            ->with('author:id,name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (StudentNote $note): array => $this->noteData($request, $student, $note));

        return Inertia::render('StudentNotes/Index', [
            'student' => $this->studentData($student),
            'notes' => $notes,
            'canCreate' => $request->user()->can('create', [StudentNote::class, $student]),
        ]);
    }

    public function create(Request $request, Student $student): Response
    {
        $this->authorize('create', [StudentNote::class, $student]);

        return Inertia::render('StudentNotes/Create', [
            'student' => $this->studentData($student),
            'today' => today()->toDateString(),
        ]);
    }

    public function store(
        StoreStudentNoteRequest $request,
        Student $student,
        StudentNoteService $service,
    ): RedirectResponse {
        $note = $service->create($student, $request->validated(), $request->user());

        return to_route('students.notes.show', [$student, $note])->with('status', 'Student note added.');
    }

    public function show(Request $request, Student $student, StudentNote $note): Response
    {
        $this->authorize('view', $note);
        abort_unless($note->student_id === $student->id, 404);
        $note->load('author:id,name');

        return Inertia::render('StudentNotes/Show', [
            'student' => $this->studentData($student),
            'note' => $this->noteData($request, $student, $note),
            'canEdit' => $request->user()->can('update', $note),
            'canDelete' => $request->user()->can('delete', $note),
        ]);
    }

    public function edit(Request $request, Student $student, StudentNote $note): Response
    {
        $this->authorize('update', $note);
        abort_unless($note->student_id === $student->id, 404);

        return Inertia::render('StudentNotes/Edit', [
            'student' => $this->studentData($student),
            'note' => [
                ...$this->noteData($request, $student, $note),
                'content' => $note->content,
            ],
            'today' => today()->toDateString(),
        ]);
    }

    public function update(
        UpdateStudentNoteRequest $request,
        Student $student,
        StudentNote $note,
        StudentNoteService $service,
    ): RedirectResponse {
        abort_unless($note->student_id === $student->id, 404);
        $note = $service->update($student, $note, $request->validated(), $request->user());

        return to_route('students.notes.show', [$student, $note])->with('status', 'Student note updated.');
    }

    public function destroy(
        Request $request,
        Student $student,
        StudentNote $note,
        StudentNoteService $service,
    ): RedirectResponse {
        $this->authorize('delete', $note);
        abort_unless($note->student_id === $student->id, 404);
        $service->delete($student, $note, $request->user());

        return to_route('students.notes.index', $student)->with('status', 'Student note deleted.');
    }

    public function download(Request $request, Student $student, StudentNote $note): StreamedResponse
    {
        $this->authorize('view', $note);
        abort_unless($note->student_id === $student->id, 404);
        abort_unless($note->attachment_path && Storage::disk('local')->exists($note->attachment_path), 404);

        return Storage::disk('local')->download($note->attachment_path, $note->attachment_name);
    }

    /**
     * @return array{id: int, admissionNumber: string, name: string}
     */
    private function studentData(Student $student): array
    {
        return [
            'id' => $student->id,
            'admissionNumber' => $student->admission_number,
            'name' => trim(implode(' ', array_filter([
                $student->first_name,
                $student->middle_name,
                $student->last_name,
            ]))),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function noteData(Request $request, Student $student, StudentNote $note): array
    {
        return [
            'id' => $note->id,
            'type' => $note->note_type,
            'title' => $note->title,
            'content' => $note->content,
            'date' => $note->note_date->toDateString(),
            'visibility' => $note->visibility,
            'author' => $note->author->name,
            'attachmentName' => $note->attachment_name,
            'attachmentSize' => $note->attachment_size_bytes,
            'attachmentUrl' => $note->attachment_path
                ? route('students.notes.attachment', [$student, $note])
                : null,
            'canEdit' => $request->user()->can('update', $note),
            'canDelete' => $request->user()->can('delete', $note),
        ];
    }
}
