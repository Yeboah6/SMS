<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'admission_number',
    'first_name',
    'middle_name',
    'last_name',
    'date_of_birth',
    'gender',
    'blood_group',
    'religion',
    'address',
    'photo_path',
    'admission_date',
    'status',
])]
class Student extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'admission_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class)
            ->withPivot(['relationship', 'is_primary', 'lives_with_student'])
            ->withTimestamps();
    }

    public function enrolments(): HasMany
    {
        return $this->hasMany(Enrolment::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StudentDocument::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->hasRole('Teacher') || $user->hasRole('Super Admin')) {
            return $query;
        }

        $activeYearId = AcademicYear::active()->value('id');

        return $query->whereHas('enrolments.section.teacherClassAssignments', fn (Builder $assignments) => $assignments
            ->where('academic_year_id', $activeYearId ?? 0)
            ->whereHas('staff', fn (Builder $staff) => $staff->where('user_id', $user->id)));
    }
}
