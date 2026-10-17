<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Concerns\FormatsSupplies;
use App\Http\Requests\ReasonRequest;
use App\Http\Requests\StorePurchaseRequest;
use App\Models\InventoryItem;
use App\Models\InventoryPurchase;
use App\Models\InventorySupplier;
use App\Services\SupplyPurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Stock bought from suppliers. A purchase is never edited or deleted: it can be reversed. */
class PurchaseController extends Controller
{
    use FormatsSupplies;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', InventoryPurchase::class);

        $search = $request->string('search')->trim()->toString();
        $supplier = $request->integer('supplier');
        $status = $request->string('status')->toString();
        $from = $request->filled('from') ? $request->date('from')?->toDateString() : null;
        $to = $request->filled('to') ? $request->date('to')?->toDateString() : null;

        return Inertia::render('Supplies/Purchases/Index', [
            'purchases' => InventoryPurchase::query()
                ->with(['supplier:id,name', 'receiver:id,name'])
                ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('purchase_number', 'like', "%{$search}%")->orWhere('reference', 'like', "%{$search}%")))
                ->when($supplier, fn ($query) => $query->where('supplier_id', $supplier))
                ->when(in_array($status, ['received', 'reversed'], true), fn ($query) => $query->where('status', $status))
                ->when($from, fn ($query) => $query->whereDate('purchase_date', '>=', $from))
                ->when($to, fn ($query) => $query->whereDate('purchase_date', '<=', $to))
                ->latest('purchase_date')->latest('id')
                ->paginate(20)->withQueryString()
                ->through(fn (InventoryPurchase $purchase): array => $this->payload($purchase)),
            'filters' => ['search' => $search, 'supplier' => $supplier ?: '', 'status' => $status, 'from' => $from ?? '', 'to' => $to ?? ''],
            'suppliers' => InventorySupplier::query()->withTrashed()->orderBy('name')->get(['id', 'name']),
            'can' => ['create' => $request->user()->can('create', InventoryPurchase::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', InventoryPurchase::class);

        return Inertia::render('Supplies/Purchases/Create', [
            'suppliers' => InventorySupplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'items' => InventoryItem::query()->active()->with('category:id,name')->orderBy('name')->get()->map(fn (InventoryItem $item): array => $this->itemPayload($item))->values(),
            'preselectedItemId' => $request->integer('item') ?: null,
            'today' => today()->toDateString(),
        ]);
    }

    public function store(StorePurchaseRequest $request, SupplyPurchaseService $service): RedirectResponse
    {
        $purchase = $service->receive($request->validated(), $request->user());

        return to_route('supplies.purchases.show', $purchase)->with('status', "Stock received on {$purchase->purchase_number}.");
    }

    public function show(Request $request, InventoryPurchase $purchase): Response
    {
        $this->authorize('view', $purchase);
        $purchase->load(['supplier:id,name', 'receiver:id,name', 'reverser:id,name', 'items.item:id,name,sku,unit']);

        return Inertia::render('Supplies/Purchases/Show', [
            'purchase' => $this->payload($purchase) + [
                'notes' => $purchase->notes,
                'reversedAt' => $purchase->reversed_at?->toDateTimeString(),
                'reversedBy' => $purchase->reverser?->name,
                'reversalReason' => $purchase->reversal_reason,
                'lines' => $purchase->items->map(fn ($line): array => ['id' => $line->id, 'itemId' => $line->item_id, 'name' => $line->item?->name, 'sku' => $line->item?->sku, 'unit' => $line->item?->unit, 'quantity' => $line->quantity, 'unitCost' => (float) $line->unit_cost, 'lineTotal' => (float) $line->line_total])->values(),
            ],
            'can' => ['reverse' => $request->user()->can('reverse', $purchase)],
        ]);
    }

    public function reverse(ReasonRequest $request, InventoryPurchase $purchase, SupplyPurchaseService $service): RedirectResponse
    {
        $this->authorize('reverse', $purchase);
        $service->reverse($purchase, $request->validated('reason'), $request->user());

        return back()->with('status', 'Purchase reversed and the stock taken back out.');
    }

    private function payload(InventoryPurchase $purchase): array
    {
        return [
            'id' => $purchase->id,
            'number' => $purchase->purchase_number,
            'supplierId' => $purchase->supplier_id,
            'supplier' => $purchase->supplier?->name,
            'date' => $purchase->purchase_date->toDateString(),
            'reference' => $purchase->reference,
            'total' => (float) $purchase->total_cost,
            'status' => $purchase->status,
            'receivedBy' => $purchase->receiver?->name,
        ];
    }
}