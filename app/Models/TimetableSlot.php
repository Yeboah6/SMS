<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAcademicYear;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'academic_year_id',
    'teacher_subject_assignment_id',
    'day_of_week',
    'starts_at',
    'ends_at',
    'room',
])]
class TimetableSlot extends Model
{
    use BelongsToAcademicYear;

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
        ];
    }

    public function teachingAssignment(): BelongsTo
    {
        return $this->belongsTo(TeacherSubjectAssignment::class, 'teacher_subject_assignment_id');
    }
}
