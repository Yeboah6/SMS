<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/** One row of the stock ledger. Immutable: a mistake is corrected by a new, opposite movement. */
#[Fillable(['item_id', 'type', 'quantity_change', 'quantity_before', 'quantity_after', 'unit_cost', 'reference_type', 'reference_id', 'reason', 'created_by'])]
class InventoryStockMovement extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Stock movements are immutable.'));
        static::deleting(fn () => throw new LogicException('Stock movements are immutable.'));
    }

    protected function casts(): array
    {
        return [
            'quantity_change' => 'integer',
            'quantity_before' => 'integer',
            'quantity_after' => 'integer',
            'unit_cost' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id')->withTrashed();
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}