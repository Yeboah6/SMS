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

    /** Generated numbers look like ADM0001. Numbers typed in by hand (legacy students) can use any format. */
    public const ADMISSION_PREFIX = 'ADM';

    protected static function booted(): void
    {
        // A blank admission number is replaced with the next generated one.
        static::creating(function (Student $student): void {
            $student->admission_number ??= static::nextAdmissionNumber();
        });
    }

    /**
     * Next free generated number. Only numbers in the ADM0001 pattern are counted, so legacy numbers
     * in other formats never collide with it. Soft-deleted students are included, so a number is
     * never reused. Call inside a transaction (the service does) so the lock serialises creators.
     */
    public static function nextAdmissionNumber(): string
    {
        $pattern = '/^'.preg_quote(self::ADMISSION_PREFIX, '/').'(\d+)$/';

        $highest = static::withTrashed()
            ->where('admission_number', 'like', self::ADMISSION_PREFIX.'%')
            ->lockForUpdate()
            ->pluck('admission_number')
            ->map(fn (string $number): int => preg_match($pattern, $number, $matches) ? (int) $matches[1] : 0)
            ->max() ?? 0;

        return self::ADMISSION_PREFIX.str_pad((string) ($highest + 1), 4, '0', STR_PAD_LEFT);
    }

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

    public function certificateRequests(): HasMany
    {
        return $this->hasMany(CertificateRequest::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(StudentNote::class)
            ->orderByDesc('note_date')
            ->orderByDesc('id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(StudentActivity::class);
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class)->latest('id');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(StudentTransfer::class)->latest('transfer_date')->latest('id');
    }

    public function homeworkSubmissions(): HasMany
    {
        return $this->hasMany(HomeworkSubmission::class);
    }

    public function examMarks(): HasMany
    {
        return $this->hasMany(ExamMark::class);
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

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForGuardianAccount(Builder $query, User $user): Builder
    {
        return $query->whereHas('guardians', fn (Builder $guardians) => $guardians
            ->where('guardians.user_id', $user->id));
    }
}