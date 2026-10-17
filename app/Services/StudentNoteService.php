<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentNote;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StudentNoteService
{
    public function __construct(private StudentActivityService $activities) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Student $student, array $data, User $actor): StudentNote
    {
        $attachment = $data['attachment'] ?? null;
        unset($data['attachment']);
        $storedAttachment = $attachment instanceof UploadedFile
            ? $this->storeAttachment($student, $attachment)
            : null;

        return DB::transaction(function () use ($student, $data, $actor, $attachment, $storedAttachment): StudentNote {
            $note = $student->notes()->create([
                ...$data,
                'author_id' => $actor->id,
                ...$this->attachmentAttributes($attachment, $storedAttachment),
            ]);

            $this->activities->record(
                $student,
                $actor,
                'note.created',
                "Student note created: {$note->title}",
                details: [
                    'note_id' => $note->id,
                    'note_type' => $note->note_type,
                    'visibility' => $note->visibility,
                ],
            );

            return $note;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Student $student, StudentNote $note, array $data, User $actor): StudentNote
    {
        $attachment = $data['attachment'] ?? null;
        $removeAttachment = (bool) ($data['remove_attachment'] ?? false);
        unset($data['attachment'], $data['remove_attachment']);

        $storedAttachment = $attachment instanceof UploadedFile
            ? $this->storeAttachment($student, $attachment)
            : null;
        $oldAttachmentPath = $note->attachment_path;
        $before = $this->noteMetadata($note);

        $note = DB::transaction(function () use (
            $student,
            $note,
            $data,
            $actor,
            $attachment,
            $removeAttachment,
            $storedAttachment,
            $before,
        ): StudentNote {
            $attributes = $data;

            if ($attachment instanceof UploadedFile) {
                $attributes = [
                    ...$attributes,
                    ...$this->attachmentAttributes($attachment, $storedAttachment),
                ];
            } elseif ($removeAttachment) {
                $attributes = [
                    ...$attributes,
                    'attachment_path' => null,
                    'attachment_name' => null,
                    'attachment_mime_type' => null,
                    'attachment_size_bytes' => null,
                ];
            }

            $note->update($attributes);
            $contentChanged = $note->wasChanged('content');
            $note->refresh();
            $after = $this->noteMetadata($note);

            $this->activities->record(
                $student,
                $actor,
                'note.updated',
                "Student note updated: {$note->title}",
                details: [
                    'note_id' => $note->id,
                    'before' => $before,
                    'after' => $after,
                    'content_changed' => $contentChanged,
                ],
            );

            return $note;
        });

        if ($oldAttachmentPath && $oldAttachmentPath !== $note->attachment_path) {
            Storage::disk('local')->delete($oldAttachmentPath);
        }

        return $note;
    }

    public function delete(Student $student, StudentNote $note, User $actor): void
    {
        $metadata = $this->noteMetadata($note);
        $attachmentPath = $note->attachment_path;

        DB::transaction(function () use ($student, $note, $actor, $metadata): void {
            $this->activities->record(
                $student,
                $actor,
                'note.deleted',
                "Student note deleted: {$note->title}",
                details: [
                    'note_id' => $note->id,
                    'note' => $metadata,
                ],
            );

            $note->delete();
        });

        if ($attachmentPath) {
            Storage::disk('local')->delete($attachmentPath);
        }
    }

    private function storeAttachment(Student $student, UploadedFile $attachment): string
    {
        $path = $attachment->storeAs(
            "student-notes/{$student->id}",
            Str::uuid().'.'.$attachment->extension(),
            'local',
        );

        if (! $path) {
            throw new \RuntimeException('The student note attachment could not be stored.');
        }

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function attachmentAttributes(?UploadedFile $attachment, ?string $path): array
    {
        return [
            'attachment_path' => $path,
            'attachment_name' => $attachment?->getClientOriginalName(),
            'attachment_mime_type' => $attachment?->getMimeType(),
            'attachment_size_bytes' => $attachment?->getSize(),
        ];
    }

    /**
     * @return array{note_type: string, title: string, note_date: string, visibility: string, has_attachment: bool}
     */
    private function noteMetadata(StudentNote $note): array
    {
        return [
            'note_type' => $note->note_type,
            'title' => $note->title,
            'note_date' => $note->note_date->toDateString(),
            'visibility' => $note->visibility,
            'has_attachment' => $note->attachment_path !== null,
        ];
    }
}
