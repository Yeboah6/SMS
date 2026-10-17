<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['purchase_number', 'supplier_id', 'purchase_date', 'reference', 'total_cost', 'status', 'notes', 'received_by', 'reversed_at', 'reversed_by', 'reversal_reason'])]
class InventoryPurchase extends Model
{
    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'total_cost' => 'decimal:2',
            'reversed_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(InventorySupplier::class, 'supplier_id')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryPurchaseItem::class, 'purchase_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}