<?php

namespace App\Services;

use App\Models\InventoryPurchase;
use App\Models\InventorySupplier;
use App\Models\User;
use App\Services\HandlesMoney;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Stock bought from suppliers. Receiving adds stock; reversing takes it back out, and only if it is still there. */
class SupplyPurchaseService
{
    use HandlesMoney;

    public function __construct(private InventoryService $inventory) {}

    /**
     * @param  array{supplier_id: int, purchase_date: string, reference?: ?string, notes?: ?string, update_cost_price?: bool, items: list<array{item_id: int, quantity: int, unit_cost: numeric-string|float|int}>}  $data
     */
    public function receive(array $data, User $actor): InventoryPurchase
    {
        if (! $actor->can('supplies.purchases.create')) {
            throw new AuthorizationException('You are not allowed to record stock purchases.');
        }

        return DB::transaction(function () use ($data, $actor): InventoryPurchase {
            $supplier = InventorySupplier::query()->find($data['supplier_id']);
            if (! $supplier || ! $supplier->is_active) {
                throw ValidationException::withMessages(['supplier_id' => 'Choose an active supplier.']);
            }

            $lines = collect($data['items']);
            $items = $this->inventory->lockItems($lines->pluck('item_id')->all());
            $totalCents = $lines->sum(fn (array $line): int => $this->cents($line['unit_cost']) * (int) $line['quantity']);

            $purchase = InventoryPurchase::query()->create([
                'purchase_number' => 'TMP'.Str::upper(Str::random(20)),
                'supplier_id' => $supplier->id,
                'purchase_date' => $data['purchase_date'],
                'reference' => $data['reference'] ?? null,
                'total_cost' => $this->money($totalCents),
                'status' => 'received',
                'notes' => $data['notes'] ?? null,
                'received_by' => $actor->id,
            ]);
            $purchase->forceFill(['purchase_number' => sprintf('PUR-%s-%06d', now()->format('Y'), $purchase->id)])->save();

            foreach ($lines as $line) {
                $item = $items->get($line['item_id']);
                if (! $item || $item->trashed()) {
                    throw ValidationException::withMessages(['items' => 'One of the items no longer exists.']);
                }

                $quantity = (int) $line['quantity'];
                $unitCostCents = $this->cents($line['unit_cost']);

                $purchase->items()->create([
                    'item_id' => $item->id,
                    'quantity' => $quantity,
                    'unit_cost' => $this->money($unitCostCents),
                    'line_total' => $this->money($unitCostCents * $quantity),
                ]);

                $this->inventory->move($item, 'received', $quantity, $actor, null, $purchase, $this->money($unitCostCents));

                if ($data['update_cost_price'] ?? true) {
                    $item->forceFill(['cost_price' => $this->money($unitCostCents)])->save();
                }
            }

            return $purchase->load('items');
        });
    }

    public function reverse(InventoryPurchase $purchase, string $reason, User $actor): InventoryPurchase
    {
        if (! $actor->can('supplies.purchases.reverse')) {
            throw new AuthorizationException('You are not allowed to reverse stock purchases.');
        }

        return DB::transaction(function () use ($purchase, $reason, $actor): InventoryPurchase {
            $locked = InventoryPurchase::query()->lockForUpdate()->findOrFail($purchase->id);

            if ($locked->status !== 'received') {
                throw ValidationException::withMessages(['purchase' => 'This purchase has already been reversed.']);
            }

            $lines = $locked->items()->get();
            $items = $this->inventory->lockItems($lines->pluck('item_id')->all());

            foreach ($lines as $line) {
                // Throws if some of this stock has already been sold: sell-through cannot be reversed.
                $this->inventory->move($items->get($line->item_id), 'purchase_reversal', $line->quantity, $actor, $reason, $locked);
            }

            $locked->forceFill([
                'status' => 'reversed',
                'reversed_at' => now(),
                'reversed_by' => $actor->id,
                'reversal_reason' => $reason,
            ])->save();

            return $locked;
        });
    }
}