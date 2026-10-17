<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'student_id',
    'author_id',
    'note_type',
    'title',
    'content',
    'note_date',
    'visibility',
    'attachment_path',
    'attachment_name',
    'attachment_mime_type',
    'attachment_size_bytes',
])]
class StudentNote extends Model
{
    protected function casts(): array
    {
        return [
            'note_date' => 'date',
            'attachment_size_bytes' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
