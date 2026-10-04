<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'department_id',
    'designation_id',
    'staff_number',
    'is_teacher',
    'name',
    'email',
    'phone',
    'joining_date',
    'qualifications',
    'photo_path',
    'status',
])]
class Staff extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
            'is_teacher' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function classAssignments(): HasMany
    {
        return $this->hasMany(TeacherClassAssignment::class);
    }

    public function subjectAssignments(): HasMany
    {
        return $this->hasMany(TeacherSubjectAssignment::class);
    }
}
