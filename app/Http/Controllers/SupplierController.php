<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Concerns\FormatsSupplies;
use App\Http\Requests\StoreSupplierRequest;
use App\Models\InventoryPurchase;
use App\Models\InventorySupplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    use FormatsSupplies;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', InventorySupplier::class);

        $search = $request->string('search')->trim()->toString();
        $state = in_array($request->string('state')->toString(), ['inactive', 'all'], true) ? $request->string('state')->toString() : 'active';

        return Inertia::render('Supplies/Suppliers/Index', [
            'suppliers' => InventorySupplier::query()
                ->withCount('purchases')
                ->withSum(['purchases as spent' => fn ($query) => $query->where('status', 'received')], 'total_cost')
                ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('name', 'like', "%{$search}%")->orWhere('contact_person', 'like', "%{$search}%")))
                ->when($state !== 'all', fn ($query) => $query->where('is_active', $state === 'active'))
                ->orderBy('name')
                ->paginate(20)->withQueryString()
                ->through(fn (InventorySupplier $supplier): array => $this->payload($supplier) + ['purchases' => $supplier->purchases_count, 'spent' => (float) ($supplier->spent ?? 0)]),
            'filters' => ['search' => $search, 'state' => $state],
            'can' => ['create' => $request->user()->can('create', InventorySupplier::class), 'update' => $request->user()->can('supplies.suppliers.manage')],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', InventorySupplier::class);

        return Inertia::render('Supplies/Suppliers/Create');
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $supplier = InventorySupplier::query()->create([...$request->validated(), 'is_active' => $request->boolean('is_active', true)]);

        return to_route('supplies.suppliers.show', $supplier)->with('status', 'Supplier added.');
    }

    public function show(Request $request, InventorySupplier $supplier): Response
    {
        $this->authorize('view', $supplier);

        return Inertia::render('Supplies/Suppliers/Show', [
            'supplier' => $this->payload($supplier),
            'purchases' => $supplier->purchases()->latest('purchase_date')->latest('id')->paginate(10)->withQueryString()
                ->through(fn (InventoryPurchase $purchase): array => ['id' => $purchase->id, 'number' => $purchase->purchase_number, 'date' => $purchase->purchase_date->toDateString(), 'reference' => $purchase->reference, 'total' => (float) $purchase->total_cost, 'status' => $purchase->status]),
            'can' => ['update' => $request->user()->can('update', $supplier)],
        ]);
    }

    public function edit(InventorySupplier $supplier): Response
    {
        $this->authorize('update', $supplier);

        return Inertia::render('Supplies/Suppliers/Edit', ['supplier' => $this->payload($supplier)]);
    }

    public function update(StoreSupplierRequest $request, InventorySupplier $supplier): RedirectResponse
    {
        $supplier->update([...$request->validated(), 'is_active' => $request->boolean('is_active', $supplier->is_active)]);

        return to_route('supplies.suppliers.show', $supplier)->with('status', 'Supplier updated.');
    }

    private function payload(InventorySupplier $supplier): array
    {
        return ['id' => $supplier->id, 'name' => $supplier->name, 'contactPerson' => $supplier->contact_person, 'phone' => $supplier->phone, 'email' => $supplier->email, 'address' => $supplier->address, 'notes' => $supplier->notes, 'isActive' => $supplier->is_active];
    }
}