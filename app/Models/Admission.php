<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'application_number',
    'parent_name',
    'parent_phone',
    'parent_email',
    'parent_occupation',
    'parent_address',
    'child_first_name',
    'child_middle_name',
    'child_last_name',
    'date_of_birth',
    'gender',
    'child_address',
    'applying_for',
    'source',
    'status',
    'review_notes',
    'reviewed_by',
    'reviewed_at',
    'created_by',
    'student_id',
])]
class Admission extends Model
{
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AdmissionDocument::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(AdmissionFollowUp::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(AdmissionStatusHistory::class)->orderBy('id');
    }
}
