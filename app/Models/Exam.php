<?php

namespace App\Models;

use App\Enums\AcademicYearStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'academic_year_id',
    'term_id',
    'class_id',
    'created_by',
    'published_by',
    'reopened_by',
    'name',
    'exam_type',
    'status',
    'start_date',
    'end_date',
    'published_at',
    'reopened_at',
])]
class Exam extends Model
{
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'published_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(ExamSubject::class);
    }

    public function isLocked(): bool
    {
        return $this->academicYear->status === AcademicYearStatus::Locked;
    }
}
