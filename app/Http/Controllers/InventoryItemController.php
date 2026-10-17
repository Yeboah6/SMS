<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Concerns\FormatsSupplies;
use App\Http\Requests\StockAdjustmentRequest;
use App\Http\Requests\StoreInventoryItemRequest;
use App\Http\Requests\UpdateInventoryItemRequest;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryItemController extends Controller
{
    use FormatsSupplies;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', InventoryItem::class);
        $user = $request->user();

        $search = $request->string('search')->trim()->toString();
        $category = $request->integer('category');
        $stock = $request->string('stock')->toString();
        $state = in_array($request->string('state')->toString(), ['inactive', 'all'], true) ? $request->string('state')->toString() : 'active';

        $items = InventoryItem::query()
            ->with('category:id,name')
            ->when($search !== '', fn ($query) => $query->search($search))
            ->when($category, fn ($query) => $query->where('category_id', $category))
            ->when($state !== 'all', fn ($query) => $query->where('is_active', $state === 'active'))
            ->when($stock === 'low', fn ($query) => $query->lowStock())
            ->when($stock === 'out', fn ($query) => $query->outOfStock())
            ->when($stock === 'ok', fn ($query) => $query->whereColumn('quantity_on_hand', '>', 'reorder_level'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (InventoryItem $item): array => $this->itemPayload($item));

        return Inertia::render('Supplies/Items/Index', [
            'items' => $items,
            'filters' => ['search' => $search, 'category' => $category ?: '', 'stock' => $stock, 'state' => $state],
            'categories' => InventoryCategory::query()->orderBy('name')->get(['id', 'name']),
            'totals' => [
                'low' => InventoryItem::query()->active()->lowStock()->count(),
                'out' => InventoryItem::query()->active()->outOfStock()->count(),
            ],
            'can' => ['create' => $user->can('create', InventoryItem::class), 'update' => $user->can('supplies.items.update'), 'adjust' => $user->can('supplies.stock.adjust')],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', InventoryItem::class);

        return Inertia::render('Supplies/Items/Create', ['categories' => $this->activeCategories()]);
    }

    public function store(StoreInventoryItemRequest $request, InventoryService $service): RedirectResponse
    {
        $item = $service->createItem($request->validated(), $request->user());

        return to_route('supplies.items.show', $item)->with('status', 'Item added.');
    }

    public function show(Request $request, InventoryItem $item): Response
    {
        $this->authorize('view', $item);
        $user = $request->user();
        $item->load('category:id,name');

        return Inertia::render('Supplies/Items/Show', [
            'item' => $this->itemPayload($item),
            'movements' => $item->stockMovements()->with(['creator:id,name', 'reference', 'item:id,name,sku'])->latest('id')
                ->paginate(15, ['*'], 'movements_page')->withQueryString()
                ->through(fn (InventoryStockMovement $movement): array => $this->movementPayload($movement)),
            'unitsSold' => (int) $item->saleItems()->selectRaw('COALESCE(SUM(quantity - returned_quantity), 0) as units')->value('units'),
            'can' => ['update' => $user->can('update', $item), 'adjust' => $user->can('adjustStock', $item)],
        ]);
    }

    public function edit(InventoryItem $item): Response
    {
        $this->authorize('update', $item);

        return Inertia::render('Supplies/Items/Edit', ['item' => $this->itemPayload($item), 'categories' => InventoryCategory::query()->orderBy('name')->get(['id', 'name'])]);
    }

    public function update(UpdateInventoryItemRequest $request, InventoryItem $item, InventoryService $service): RedirectResponse
    {
        $service->updateItem($item, $request->validated(), $request->user());

        return to_route('supplies.items.show', $item)->with('status', 'Item updated.');
    }

    // ------------------------------------------------------------ stock adjustments

    public function adjust(InventoryItem $item): Response
    {
        $this->authorize('adjustStock', $item);

        return Inertia::render('Supplies/Items/Adjust', [
            'item' => $this->itemPayload($item->load('category:id,name')),
            'types' => collect(InventoryService::MANUAL_TYPES)->map(fn (string $type): array => ['key' => $type, 'label' => InventoryService::TYPE_LABELS[$type]])->values(),
        ]);
    }

    public function storeAdjustment(StockAdjustmentRequest $request, InventoryItem $item, InventoryService $service): RedirectResponse
    {
        $service->adjust($item, $request->validated('type'), (int) $request->validated('quantity'), $request->validated('reason'), $request->user());

        return to_route('supplies.items.show', $item)->with('status', 'Stock updated.');
    }

    private function activeCategories()
    {
        return InventoryCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }
}