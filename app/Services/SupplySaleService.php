<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Enrolment;
use App\Models\Student;
use App\Models\StudentSupplySale;
use App\Models\StudentSupplySaleItem;
use App\Models\User;
use App\Services\HandlesMoney;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Sales of supplies to students. Everything that touches stock or money runs in one transaction, prices come
 * from the database (never from the browser), and history is never deleted: returns and cancellations are
 * recorded as reversals.
 */
class SupplySaleService
{
    use HandlesMoney;

    public function __construct(
        private InventoryService $inventory,
        private SupplyPaymentService $payments,
        private StudentActivityService $activities,
    ) {}

    /**
     * @param  array{student_id: int, items: list<array{item_id: int, quantity: int}>, discount_type?: ?string, discount_value?: numeric-string|float|int|null, override_stock?: bool, notes?: ?string, payment?: ?array<string, mixed>}  $data
     */
    public function create(array $data, User $actor): StudentSupplySale
    {
        if (! $actor->can('supplies.sales.create')) {
            throw new AuthorizationException('You are not allowed to sell supplies.');
        }

        return DB::transaction(function () use ($data, $actor): StudentSupplySale {
            $student = Student::query()->lockForUpdate()->find($data['student_id']);
            if (! $student || $student->status !== 'active') {
                throw ValidationException::withMessages(['student_id' => 'Select an active student.']);
            }

            $year = AcademicYear::active()->first();
            if (! $year) {
                throw ValidationException::withMessages(['student_id' => 'There is no active academic year.']);
            }

            $override = (bool) ($data['override_stock'] ?? false);
            if ($override && (! config('supplies.allow_stock_override') || ! $actor->can('supplies.stock.override'))) {
                throw ValidationException::withMessages(['override_stock' => 'Selling beyond the stock on hand is not enabled for you.']);
            }

            // Merge repeated rows, then lock the items once, in id order.
            $requested = collect($data['items'])->groupBy('item_id')->map(fn ($rows): int => (int) $rows->sum('quantity'));
            $items = $this->inventory->lockItems($requested->keys()->all());

            $lines = [];
            $subtotalCents = 0;
            foreach ($requested as $itemId => $quantity) {
                $item = $items->get($itemId);
                if (! $item || $item->trashed() || ! $item->is_active) {
                    throw ValidationException::withMessages(['items' => 'One of the items is no longer available for sale.']);
                }

                $backorder = 0;
                if ($quantity > $item->quantity_on_hand) {
                    if (! $override) {
                        throw ValidationException::withMessages(['items' => "Only {$item->quantity_on_hand} of {$item->name} in stock."]);
                    }
                    $backorder = $quantity - $item->quantity_on_hand;
                }

                $unitCents = $this->cents($item->selling_price); // the server's price, never the browser's
                $subtotalCents += $unitCents * $quantity;
                $lines[] = ['item' => $item, 'quantity' => $quantity, 'backorder' => $backorder, 'unitCents' => $unitCents];
            }

            $discountCents = $this->discountCents($data, $subtotalCents, $actor);
            $totalCents = $subtotalCents - $discountCents;

            $paymentData = $data['payment'] ?? null;
            $payNow = $paymentData ? $this->cents($paymentData['amount'] ?? 0) : 0;
            if ($totalCents > 0 && ! config('supplies.allow_partial_payment') && $payNow !== $totalCents) {
                throw ValidationException::withMessages(['payment.amount' => 'The full amount of '.$this->money($totalCents).' must be paid at the time of sale.']);
            }

            $enrolment = Enrolment::query()
                ->where('student_id', $student->id)
                ->where('academic_year_id', $year->id)
                ->where('status', 'active')
                ->first();

            $sale = StudentSupplySale::query()->create([
                'sale_number' => 'TMP'.Str::upper(Str::random(20)),
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'class_id' => $enrolment?->class_id,
                'subtotal' => $this->money($subtotalCents),
                'discount_amount' => $this->money($discountCents),
                'total_amount' => $this->money($totalCents),
                'status' => 'completed',
                'payment_status' => 'unpaid',
                'notes' => $data['notes'] ?? null,
                'sold_by' => $actor->id,
                'sold_at' => now(),
            ]);
            $sale->forceFill(['sale_number' => sprintf('SUP-%s-%06d', $year->start_date->format('Y'), $sale->id)])->save();

            foreach ($lines as $line) {
                $item = $line['item'];
                $sale->items()->create([
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'sku' => $item->sku,
                    'quantity' => $line['quantity'],
                    'unit_price' => $this->money($line['unitCents']),
                    'unit_cost' => $item->cost_price,
                    'line_total' => $this->money($line['unitCents'] * $line['quantity']),
                    'backordered_quantity' => $line['backorder'],
                ]);

                $handedOver = $line['quantity'] - $line['backorder'];
                if ($handedOver > 0) {
                    $this->inventory->move($item, 'sale', $handedOver, $actor, null, $sale);
                }
            }

            if ($payNow > 0) {
                $this->payments->record($sale, $paymentData, $actor);
            }

            $sale = $this->payments->syncTotals($sale->refresh());
            $this->audit($sale, $actor, 'supplies.sale_created', "Supply sale {$sale->sale_number} for {$this->money($totalCents)}.", []);

            return $sale->load(['items', 'payments']);
        });
    }

    /**
     * Takes goods back. Restocks them (unless told not to) and, if the student has paid more than they now owe,
     * pays the difference back in the same transaction.
     *
     * @param  array{items: list<array{sale_item_id: int, quantity: int}>, reason: string, restock?: bool, refund_method?: ?string, refund_reference?: ?string}  $data
     */
    public function returnItems(StudentSupplySale $sale, array $data, User $actor): StudentSupplySale
    {
        if (! $actor->can('supplies.sales.refund')) {
            throw new AuthorizationException('You are not allowed to refund supply sales.');
        }

        return DB::transaction(function () use ($sale, $data, $actor): StudentSupplySale {
            $sale = StudentSupplySale::query()->lockForUpdate()->findOrFail($sale->id);

            if (! in_array($sale->status, ['completed', 'partially_returned'], true)) {
                throw ValidationException::withMessages(['items' => 'This sale cannot take returns.']);
            }

            $saleItems = $sale->items()->lockForUpdate()->get()->keyBy('id');
            $requested = collect($data['items'])
                ->filter(fn (array $row): bool => (int) $row['quantity'] > 0)
                ->groupBy('sale_item_id')
                ->map(fn ($rows): int => (int) $rows->sum('quantity'));

            if ($requested->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Choose at least one item to return.']);
            }

            $subtotalCents = $this->cents($sale->subtotal);
            $payableRatio = $subtotalCents > 0 ? ($subtotalCents - $this->cents($sale->discount_amount)) / $subtotalCents : 0;
            $valueCents = 0;

            foreach ($requested as $saleItemId => $quantity) {
                $line = $saleItems->get($saleItemId);
                if (! $line) {
                    throw ValidationException::withMessages(['items' => 'An item does not belong to this sale.']);
                }
                if ($quantity > $line->keptQuantity()) {
                    throw ValidationException::withMessages(['items' => "Only {$line->keptQuantity()} of {$line->item_name} can still be returned."]);
                }
                // The refund value carries the sale's discount, so a discounted sale is never over-refunded.
                $valueCents += (int) round($this->cents($line->unit_price) * $quantity * $payableRatio);
            }

            $restock = (bool) ($data['restock'] ?? true);
            $stock = $this->inventory->lockItems($saleItems->whereIn('id', $requested->keys())->pluck('item_id')->unique()->all());

            foreach ($requested as $saleItemId => $quantity) {
                $line = $saleItems->get($saleItemId);
                $this->takeBack($line, $quantity, $restock ? $stock : null, 'sale_return', $data['reason'], $sale, $actor);
            }

            $returnedCents = min($this->cents($sale->returned_amount) + $valueCents, $this->cents($sale->total_amount));
            $allReturned = $saleItems->every(fn (StudentSupplySaleItem $line): bool => $line->keptQuantity() === 0);
            if ($allReturned) {
                $returnedCents = $this->cents($sale->total_amount); // absorbs any rounding
            }

            $sale->returned_amount = $this->money($returnedCents);
            $sale->status = $allReturned ? 'returned' : 'partially_returned';
            $sale->save();

            $excess = ($this->cents($sale->paid_amount) - $this->cents($sale->refunded_amount)) - ($this->cents($sale->total_amount) - $returnedCents);
            if ($excess > 0) {
                $this->payments->refund($sale, $excess, $data['refund_method'] ?? null, $data['refund_reference'] ?? null, $data['reason'], $actor);
            }

            $sale = $this->payments->syncTotals($sale->refresh());
            $this->audit($sale, $actor, 'supplies.sale_returned', "Items returned on supply sale {$sale->sale_number} (value {$this->money($valueCents)}).", ['reason' => $data['reason']]);

            return $sale->load(['items', 'payments']);
        });
    }

    /**
     * Voids the whole sale: everything still with the student goes back on the shelf and any money kept is refunded.
     *
     * @param  array{reason: string, refund_method?: ?string, refund_reference?: ?string}  $data
     */
    public function cancel(StudentSupplySale $sale, array $data, User $actor): StudentSupplySale
    {
        if (! $actor->can('supplies.sales.cancel')) {
            throw new AuthorizationException('You are not allowed to cancel supply sales.');
        }

        return DB::transaction(function () use ($sale, $data, $actor): StudentSupplySale {
            $sale = StudentSupplySale::query()->lockForUpdate()->findOrFail($sale->id);

            if (! in_array($sale->status, ['completed', 'partially_returned'], true)) {
                throw ValidationException::withMessages(['reason' => 'This sale can no longer be cancelled.']);
            }

            $saleItems = $sale->items()->lockForUpdate()->get();
            $stock = $this->inventory->lockItems($saleItems->pluck('item_id')->unique()->all());

            foreach ($saleItems as $line) {
                if ($line->keptQuantity() > 0) {
                    $this->takeBack($line, $line->keptQuantity(), $stock, 'sale_cancellation', $data['reason'], $sale, $actor);
                }
            }

            $sale->returned_amount = $sale->total_amount;
            $sale->status = 'cancelled';
            $sale->cancelled_at = now();
            $sale->cancelled_by = $actor->id;
            $sale->cancellation_reason = $data['reason'];
            $sale->save();

            $kept = $this->cents($sale->paid_amount) - $this->cents($sale->refunded_amount);
            if ($kept > 0) {
                $this->payments->refund($sale, $kept, $data['refund_method'] ?? null, $data['refund_reference'] ?? null, $data['reason'], $actor);
            }

            $sale = $this->payments->syncTotals($sale->refresh());
            $this->audit($sale, $actor, 'supplies.sale_cancelled', "Supply sale {$sale->sale_number} cancelled.", ['reason' => $data['reason']]);

            return $sale->load(['items', 'payments']);
        });
    }

    /** Hands over units that were sold on backorder once stock has arrived. */
    public function deliverBackorder(StudentSupplySaleItem $saleItem, int $quantity, User $actor): StudentSupplySaleItem
    {
        if (! $actor->can('supplies.sales.create')) {
            throw new AuthorizationException('You are not allowed to deliver backordered items.');
        }

        return DB::transaction(function () use ($saleItem, $quantity, $actor): StudentSupplySaleItem {
            $line = StudentSupplySaleItem::query()->lockForUpdate()->findOrFail($saleItem->id);
            $sale = StudentSupplySale::query()->findOrFail($line->sale_id);

            if (in_array($sale->status, ['cancelled', 'returned'], true) || $quantity < 1 || $quantity > $line->backordered_quantity) {
                throw ValidationException::withMessages(['quantity' => "Only {$line->backordered_quantity} unit(s) are waiting to be delivered."]);
            }

            $item = $this->inventory->lockItems([$line->item_id])->get($line->item_id);
            $this->inventory->move($item, 'sale', $quantity, $actor, 'Backorder delivered', $sale);

            $line->backordered_quantity -= $quantity;
            $line->save();

            return $line;
        });
    }

    // ------------------------------------------------------------------ helpers

    /**
     * Puts units back. Units that were never handed over (backordered) are cancelled first and do not touch stock.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\InventoryItem>|null  $stock  null means "do not restock"
     */
    private function takeBack(StudentSupplySaleItem $line, int $quantity, $stock, string $movementType, string $reason, StudentSupplySale $sale, User $actor): void
    {
        $fromBackorder = min($quantity, $line->backordered_quantity);
        $physical = $quantity - $fromBackorder;

        $line->returned_quantity += $quantity;
        $line->backordered_quantity -= $fromBackorder;
        $line->save();

        if ($stock !== null && $physical > 0 && ($item = $stock->get($line->item_id))) {
            $this->inventory->move($item, $movementType, $physical, $actor, $reason, $sale);
        }
    }

    private function discountCents(array $data, int $subtotalCents, User $actor): int
    {
        $type = $data['discount_type'] ?? null;
        $value = (float) ($data['discount_value'] ?? 0);

        if (! $type || $value <= 0) {
            return 0;
        }
        if (! $actor->can('supplies.sales.discount')) {
            throw ValidationException::withMessages(['discount_value' => 'You are not authorised to give discounts.']);
        }

        $cents = $type === 'percent'
            ? (int) round($subtotalCents * min($value, 100) / 100)
            : $this->cents($value);

        if ($cents > $subtotalCents) {
            throw ValidationException::withMessages(['discount_value' => 'The discount cannot be more than the subtotal.']);
        }

        return $cents;
    }

    private function audit(StudentSupplySale $sale, User $actor, string $event, string $summary, array $details): void
    {
        $year = AcademicYear::query()->find($sale->academic_year_id);
        $student = Student::query()->find($sale->student_id);

        if ($year && $student) {
            $this->activities->record($student, $actor, $event, $summary, $year, ['sale_id' => $sale->id] + $details);
        }
    }
}