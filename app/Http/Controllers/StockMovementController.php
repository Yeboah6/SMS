<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Concerns\FormatsSupplies;
use App\Models\InventoryStockMovement;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The full stock ledger across every item. */
class StockMovementController extends Controller
{
    use FormatsSupplies;

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('supplies.stock.view'), 403);

        $search = $request->string('search')->trim()->toString();
        $type = $request->string('type')->toString();
        $from = $request->filled('from') ? $request->date('from')?->startOfDay() : null;
        $to = $request->filled('to') ? $request->date('to')?->endOfDay() : null;

        return Inertia::render('Supplies/Movements/Index', [
            'movements' => InventoryStockMovement::query()
                ->with(['item:id,name,sku', 'creator:id,name', 'reference'])
                ->when($search !== '', fn ($query) => $query->whereHas('item', fn ($items) => $items->withTrashed()->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")))
                ->when(array_key_exists($type, InventoryService::TYPE_LABELS), fn ($query) => $query->where('type', $type))
                ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
                ->when($to, fn ($query) => $query->where('created_at', '<=', $to))
                ->latest('id')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (InventoryStockMovement $movement): array => $this->movementPayload($movement)),
            'filters' => ['search' => $search, 'type' => $type, 'from' => $from?->toDateString() ?? '', 'to' => $to?->toDateString() ?? ''],
            'types' => collect(InventoryService::TYPE_LABELS)->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])->values(),
        ]);
    }
}