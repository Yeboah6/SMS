<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * total_amount never changes after the sale. Returns raise returned_amount, payments raise paid_amount and
 * refunds raise refunded_amount, so every figure can be traced to a row.
 */
#[Fillable(['sale_number', 'student_id', 'academic_year_id', 'class_id', 'subtotal', 'discount_amount', 'total_amount', 'returned_amount', 'paid_amount', 'refunded_amount', 'status', 'payment_status', 'notes', 'sold_by', 'sold_at', 'cancelled_at', 'cancelled_by', 'cancellation_reason'])]
class StudentSupplySale extends Model
{
    public const STATUSES = ['completed', 'partially_returned', 'returned', 'cancelled'];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'returned_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'sold_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StudentSupplySaleItem::class, 'sale_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(StudentSupplyPayment::class, 'sale_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** What the student owes after returns. */
    public function netDue(): float
    {
        return round((float) $this->total_amount - (float) $this->returned_amount, 2);
    }

    /** What has actually been kept after refunds. */
    public function netPaid(): float
    {
        return round((float) $this->paid_amount - (float) $this->refunded_amount, 2);
    }

    public function balance(): float
    {
        return round($this->netDue() - $this->netPaid(), 2);
    }

    /** Sales that still owe money. */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->where('status', '!=', 'cancelled')
            ->whereRaw('(total_amount - returned_amount) - (paid_amount - refunded_amount) > 0');
    }
}