<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'exam_id',
    'student_id',
    'closing_date',
    'next_term_begins',
    'next_term_ends',
    'attendance',
    'attendance_out_of',
    'conduct',
    'attitude',
    'class_teacher_remarks',
    'head_teacher_remarks',
    'school_fees_arrears',
    'next_term_fee',
    'updated_by',
])]
class ReportCardDetail extends Model
{
    protected function casts(): array
    {
        return [
            'closing_date' => 'date',
            'next_term_begins' => 'date',
            'next_term_ends' => 'date',
            'school_fees_arrears' => 'decimal:2',
            'next_term_fee' => 'decimal:2',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}