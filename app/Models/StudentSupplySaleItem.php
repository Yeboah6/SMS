<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sale_id', 'item_id', 'item_name', 'sku', 'quantity', 'unit_price', 'unit_cost', 'line_total', 'returned_quantity', 'backordered_quantity'])]
class StudentSupplySaleItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'line_total' => 'decimal:2',
            'returned_quantity' => 'integer',
            'backordered_quantity' => 'integer',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(StudentSupplySale::class, 'sale_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id')->withTrashed();
    }

    /** Units still with the student (sold and not returned). */
    public function keptQuantity(): int
    {
        return $this->quantity - $this->returned_quantity;
    }
}