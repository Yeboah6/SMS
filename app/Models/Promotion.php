<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'student_id',
    'source_enrolment_id',
    'target_enrolment_id',
    'source_academic_year_id',
    'target_academic_year_id',
    'source_class_id',
    'source_section_id',
    'target_class_id',
    'target_section_id',
    'processed_by',
    'outcome',
    'reason',
])]
class Promotion extends Model
{
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function sourceEnrolment(): BelongsTo
    {
        return $this->belongsTo(Enrolment::class, 'source_enrolment_id');
    }

    public function targetEnrolment(): BelongsTo
    {
        return $this->belongsTo(Enrolment::class, 'target_enrolment_id');
    }

    public function sourceAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'source_academic_year_id');
    }

    public function targetAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'target_academic_year_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
