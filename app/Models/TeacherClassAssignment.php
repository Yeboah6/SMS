<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAcademicYear;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['academic_year_id', 'staff_id', 'section_id'])]
class TeacherClassAssignment extends Model
{
    use BelongsToAcademicYear;

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
