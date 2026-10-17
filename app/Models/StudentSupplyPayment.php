<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * A payment or a refund against a sale. Immutable: payments are never edited. The one permitted write after
 * creation is filling in the receipt number once the row has an id.
 */
#[Fillable(['sale_id', 'type', 'amount', 'payment_method', 'transaction_reference', 'idempotency_key', 'receipt_number', 'recorded_by', 'paid_at', 'notes'])]
class StudentSupplyPayment extends Model
{
    public const PAYMENT = 'payment';

    public const REFUND = 'refund';

    protected static function booted(): void
    {
        static::updating(function (StudentSupplyPayment $payment): void {
            $onlyReceipt = $payment->getOriginal('receipt_number') === null
                && array_diff(array_keys($payment->getDirty()), ['receipt_number', 'updated_at']) === [];

            if (! $onlyReceipt) {
                throw new LogicException('Payments cannot be edited. Record a refund to correct a mistake.');
            }
        });
        static::deleting(fn () => throw new LogicException('Payments cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(StudentSupplySale::class, 'sale_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}