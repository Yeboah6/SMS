<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAcademicYear;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'academic_year_id',
    'student_id',
    'class_id',
    'section_id',
    'enrolment_date',
    'exit_date',
    'status',
    'outcome',
])]
class Enrolment extends Model
{
    use BelongsToAcademicYear;

    protected function casts(): array
    {
        return [
            'enrolment_date' => 'date',
            'exit_date' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function promotionAsSource(): HasOne
    {
        return $this->hasOne(Promotion::class, 'source_enrolment_id');
    }

    public function transferAsSource(): HasOne
    {
        return $this->hasOne(StudentTransfer::class);
    }
}
