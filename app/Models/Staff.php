<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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

    public const NUMBER_PREFIX = 'ST';

    protected static function booted(): void
    {
        // Every new profile gets the next number (ST001, ST002, ...) unless one is supplied,
        // so seeders and factories are covered as well as the staff form.
        static::creating(function (Staff $staff): void {
            $staff->staff_number ??= static::nextNumber();
        });
    }

    /**
     * Next free staff number. Soft-deleted staff are included so a number is never reused.
     * Call inside a transaction (the service does) so the lock serialises concurrent creates.
     */
    public static function nextNumber(): string
    {
        $pattern = '/^'.preg_quote(self::NUMBER_PREFIX, '/').'(\d+)$/';

        $highest = static::withTrashed()
            ->where('staff_number', 'like', self::NUMBER_PREFIX.'%')
            ->lockForUpdate()
            ->pluck('staff_number')
            ->map(fn (string $number): int => preg_match($pattern, $number, $matches) ? (int) $matches[1] : 0)
            ->max() ?? 0;

        return self::NUMBER_PREFIX.str_pad((string) ($highest + 1), 3, '0', STR_PAD_LEFT);
    }

    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
            'is_teacher' => 'boolean',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeTeachers(Builder $query): Builder
    {
        return $query->where('is_teacher', true);
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

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}