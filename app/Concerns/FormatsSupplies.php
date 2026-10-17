<?php

namespace App\Concerns;

use App\Models\InventoryItem;
use App\Models\InventoryPurchase;
use App\Models\InventoryStockMovement;
use App\Models\Student;
use App\Models\StudentSupplyPayment;
use App\Models\StudentSupplySale;
use App\Services\InventoryService;

trait FormatsSupplies
{
    protected function studentName(Student $student): string
    {
        return trim(implode(' ', array_filter([$student->first_name, $student->middle_name, $student->last_name])));
    }

    /** @return list<array{key: string, label: string}> */
    protected function paymentMethods(): array
    {
        return collect(config('supplies.payment_methods', []))->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])->values()->all();
    }

    protected function itemPayload(InventoryItem $item): array
    {
        return [
            'id' => $item->id,
            'sku' => $item->sku,
            'name' => $item->name,
            'description' => $item->description,
            'categoryId' => $item->category_id,
            'category' => $item->category?->name,
            'unit' => $item->unit,
            'costPrice' => (float) $item->cost_price,
            'sellingPrice' => (float) $item->selling_price,
            'quantity' => $item->quantity_on_hand,
            'reorderLevel' => $item->reorder_level,
            'isActive' => $item->is_active,
            'stockStatus' => $item->stockStatus(),
            'stockValue' => $item->stockValue(),
        ];
    }

    /** Requires creator and reference to be loaded. */
    protected function movementPayload(InventoryStockMovement $movement): array
    {
        $reference = $movement->reference;
        [$label, $href] = match (true) {
            $reference instanceof StudentSupplySale => [$reference->sale_number, "/supplies/sales/{$reference->id}"],
            $reference instanceof InventoryPurchase => [$reference->purchase_number, "/supplies/purchases/{$reference->id}"],
            default => [null, null],
        };

        return [
            'id' => $movement->id,
            'itemId' => $movement->item_id,
            'itemName' => $movement->item?->name,
            'sku' => $movement->item?->sku,
            'type' => $movement->type,
            'typeLabel' => InventoryService::TYPE_LABELS[$movement->type] ?? $movement->type,
            'change' => $movement->quantity_change,
            'before' => $movement->quantity_before,
            'after' => $movement->quantity_after,
            'unitCost' => $movement->unit_cost !== null ? (float) $movement->unit_cost : null,
            'reason' => $movement->reason,
            'reference' => $label,
            'referenceUrl' => $href,
            'by' => $movement->creator?->name,
            'at' => $movement->created_at->toDateTimeString(),
        ];
    }

    /** Needs student, schoolClass, academicYear and seller loaded; $details also needs items, payments.recorder and canceller. */
    protected function salePayload(StudentSupplySale $sale, bool $details = false): array
    {
        $payload = [
            'id' => $sale->id,
            'saleNumber' => $sale->sale_number,
            'status' => $sale->status,
            'paymentStatus' => $sale->payment_status,
            'student' => ['id' => $sale->student_id, 'name' => $this->studentName($sale->student), 'admissionNumber' => $sale->student->admission_number],
            'className' => $sale->schoolClass?->name,
            'academicYear' => $sale->academicYear?->name,
            'subtotal' => (float) $sale->subtotal,
            'discount' => (float) $sale->discount_amount,
            'total' => (float) $sale->total_amount,
            'returned' => (float) $sale->returned_amount,
            'paid' => (float) $sale->paid_amount,
            'refunded' => (float) $sale->refunded_amount,
            'netDue' => $sale->netDue(),
            'netPaid' => $sale->netPaid(),
            'balance' => $sale->balance(),
            'soldAt' => $sale->sold_at->toDateTimeString(),
            'soldBy' => $sale->seller?->name,
            'notes' => $sale->notes,
        ];

        if (! $details) {
            return $payload;
        }

        return $payload + [
            'cancelledAt' => $sale->cancelled_at?->toDateTimeString(),
            'cancelledBy' => $sale->canceller?->name,
            'cancellationReason' => $sale->cancellation_reason,
            'items' => $sale->items->map(fn ($line): array => [
                'id' => $line->id,
                'name' => $line->item_name,
                'sku' => $line->sku,
                'quantity' => $line->quantity,
                'unitPrice' => (float) $line->unit_price,
                'lineTotal' => (float) $line->line_total,
                'returned' => $line->returned_quantity,
                'kept' => $line->keptQuantity(),
                'backordered' => $line->backordered_quantity,
            ])->values()->all(),
            'payments' => $sale->payments->sortBy('id')->map(fn (StudentSupplyPayment $payment): array => [
                'id' => $payment->id,
                'type' => $payment->type,
                'receiptNumber' => $payment->receipt_number,
                'amount' => (float) $payment->amount,
                'method' => config('supplies.payment_methods.'.$payment->payment_method, $payment->payment_method),
                'reference' => $payment->transaction_reference,
                'by' => $payment->recorder?->name,
                'paidAt' => $payment->paid_at->toDateTimeString(),
                'notes' => $payment->notes,
            ])->values()->all(),
        ];
    }
}