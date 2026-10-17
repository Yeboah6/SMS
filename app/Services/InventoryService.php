<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\User;
use App\Services\HandlesMoney;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Items and the stock ledger. quantity_on_hand changes only through move(), which locks the item, refuses to go
 * below zero and writes the matching immutable movement, so the ledger always explains the number on the shelf.
 */
class InventoryService
{
    use HandlesMoney;

    public const INCREASES = ['opening', 'received', 'adjustment_in', 'sale_return', 'sale_cancellation'];

    public const DECREASES = ['sale', 'damaged', 'adjustment_out', 'supplier_return', 'purchase_reversal'];

    /** What a person may record by hand on the adjust page. */
    public const MANUAL_TYPES = ['adjustment_in', 'adjustment_out', 'damaged', 'supplier_return'];

    public const TYPE_LABELS = [
        'opening' => 'Opening stock',
        'received' => 'Stock received',
        'adjustment_in' => 'Adjustment (increase)',
        'adjustment_out' => 'Adjustment (decrease)',
        'damaged' => 'Damaged or lost',
        'supplier_return' => 'Returned to supplier',
        'sale' => 'Sold to student',
        'sale_return' => 'Returned by student',
        'sale_cancellation' => 'Sale cancelled',
        'purchase_reversal' => 'Purchase reversed',
    ];

    /**
     * @param  array<string, mixed>  $data  category_id, sku (optional), name, description, unit, cost_price, selling_price, reorder_level, is_active, opening_quantity
     */
    public function createItem(array $data, User $actor): InventoryItem
    {
        if (! $actor->can('supplies.items.create')) {
            throw new AuthorizationException('You are not allowed to add inventory items.');
        }

        return DB::transaction(function () use ($data, $actor): InventoryItem {
            $opening = (int) ($data['opening_quantity'] ?? 0);
            $generateSku = blank($data['sku'] ?? null);

            $item = InventoryItem::query()->create([
                ...Arr::except($data, ['opening_quantity', 'sku']),
                'sku' => $generateSku ? 'TMP'.Str::upper(Str::random(20)) : $data['sku'],
            ]);

            if ($generateSku) {
                $item->forceFill(['sku' => sprintf('ITM-%06d', $item->id)])->save();
            }

            if ($opening > 0) {
                $locked = InventoryItem::query()->lockForUpdate()->findOrFail($item->id);
                $this->move($locked, 'opening', $opening, $actor, 'Opening stock', null, (string) $item->cost_price);
            }

            return $item->refresh();
        });
    }

    /** Prices and details only. Stock is never edited here; use adjust(). */
    public function updateItem(InventoryItem $item, array $data, User $actor): InventoryItem
    {
        if (! $actor->can('supplies.items.update')) {
            throw new AuthorizationException('You are not allowed to edit inventory items.');
        }

        $item->update(Arr::only($data, ['category_id', 'sku', 'name', 'description', 'unit', 'cost_price', 'selling_price', 'reorder_level', 'is_active']));

        return $item->refresh();
    }

    public function adjust(InventoryItem $item, string $type, int $quantity, string $reason, User $actor): InventoryStockMovement
    {
        if (! $actor->can('supplies.stock.adjust')) {
            throw new AuthorizationException('You are not allowed to adjust stock.');
        }
        if (! in_array($type, self::MANUAL_TYPES, true)) {
            throw ValidationException::withMessages(['type' => 'Choose a valid movement type.']);
        }

        return DB::transaction(function () use ($item, $type, $quantity, $reason, $actor): InventoryStockMovement {
            $locked = InventoryItem::query()->lockForUpdate()->findOrFail($item->id);

            return $this->move($locked, $type, $quantity, $actor, $reason);
        });
    }

    /**
     * Locks the given items in id order (so two sales touching the same items cannot deadlock) and keys them by id.
     * Soft-deleted items are included so returns can still restock them.
     *
     * @param  list<int|string>  $ids
     * @return Collection<int, InventoryItem>
     */
    public function lockItems(array $ids): Collection
    {
        return InventoryItem::withTrashed()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /**
     * Applies one movement to an item the caller has already locked, inside the caller's transaction.
     */
    public function move(
        InventoryItem $lockedItem,
        string $type,
        int $quantity,
        User $actor,
        ?string $reason = null,
        ?Model $reference = null,
        ?string $unitCost = null,
    ): InventoryStockMovement {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('A stock movement must move at least one unit.');
        }

        $increase = in_array($type, self::INCREASES, true);
        if (! $increase && ! in_array($type, self::DECREASES, true)) {
            throw new InvalidArgumentException("Unknown stock movement type [{$type}].");
        }

        $before = $lockedItem->quantity_on_hand;
        $after = $before + ($increase ? $quantity : -$quantity);

        if ($after < 0) {
            throw ValidationException::withMessages([
                'quantity' => "Only {$before} {$lockedItem->unit}(s) of {$lockedItem->name} in stock.",
            ]);
        }

        $lockedItem->quantity_on_hand = $after;
        $lockedItem->save();

        return InventoryStockMovement::query()->create([
            'item_id' => $lockedItem->id,
            'type' => $type,
            'quantity_change' => $increase ? $quantity : -$quantity,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'unit_cost' => $unitCost,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'reason' => $reason,
            'created_by' => $actor->id,
        ]);
    }
}