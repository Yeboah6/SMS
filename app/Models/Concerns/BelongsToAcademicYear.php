<?php

namespace App\Models\Concerns;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToAcademicYear
{
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForAcademicYear(Builder $query, AcademicYear|int $academicYear): Builder
    {
        $academicYearId = $academicYear instanceof AcademicYear
            ? $academicYear->getKey()
            : $academicYear;

        return $query->where($query->getModel()->qualifyColumn('academic_year_id'), $academicYearId);
    }
}
