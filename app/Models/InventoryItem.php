<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * quantity_on_hand is deliberately not fillable: it only changes through InventoryService::move(),
 * which writes the matching stock movement in the same transaction.
 */
#[Fillable(['category_id', 'sku', 'name', 'description', 'unit', 'cost_price', 'selling_price', 'reorder_level', 'is_active'])]
class InventoryItem extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'quantity_on_hand' => 'integer',
            'reorder_level' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(InventoryStockMovement::class, 'item_id');
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(StudentSupplySaleItem::class, 'item_id');
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(InventoryPurchaseItem::class, 'item_id');
    }

    /** @param  Builder<static>  $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** In stock, but at or below its reorder level. */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('quantity_on_hand', '>', 0)->whereColumn('quantity_on_hand', '<=', 'reorder_level');
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('quantity_on_hand', 0);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(fn (Builder $inner) => $inner
            ->where('name', 'like', "%{$term}%")
            ->orWhere('sku', 'like', "%{$term}%"));
    }

    /** out | low | ok */
    public function stockStatus(): string
    {
        if ($this->quantity_on_hand === 0) {
            return 'out';
        }

        return $this->quantity_on_hand <= $this->reorder_level ? 'low' : 'ok';
    }

    public function stockValue(): float
    {
        return round((float) $this->cost_price * $this->quantity_on_hand, 2);
    }
}