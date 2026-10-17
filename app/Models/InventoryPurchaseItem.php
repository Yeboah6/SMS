<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['purchase_id', 'item_id', 'quantity', 'unit_cost', 'line_total'])]
class InventoryPurchaseItem extends Model
{
    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_cost' => 'decimal:2', 'line_total' => 'decimal:2'];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(InventoryPurchase::class, 'purchase_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id')->withTrashed();
    }
}