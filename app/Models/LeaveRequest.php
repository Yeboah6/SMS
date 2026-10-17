<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'staff_id',
    'student_id',
    'submitted_by',
    'reviewed_by',
    'leave_type',
    'start_date',
    'end_date',
    'reason',
    'status',
    'reviewed_at',
    'review_comments',
])]
class LeaveRequest extends Model
{
    public const STATUSES = ['pending', 'approved', 'rejected', 'cancelled'];

    public const ACTIVE_STATUSES = ['pending', 'approved'];

    public const TYPES = ['annual', 'sick', 'personal', 'unpaid', 'other'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class)->withTrashed();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
