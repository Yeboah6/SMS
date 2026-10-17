<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAcademicYear;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['academic_year_id', 'name', 'start_date', 'end_date'])]
class Term extends Model
{
    use BelongsToAcademicYear;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }
}
