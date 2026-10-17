<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Models\InventoryCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', InventoryCategory::class);

        return Inertia::render('Supplies/Categories/Index', [
            'categories' => InventoryCategory::query()->withCount('items')->orderBy('name')->get()
                ->map(fn (InventoryCategory $category): array => ['id' => $category->id, 'name' => $category->name, 'description' => $category->description, 'isActive' => $category->is_active, 'items' => $category->items_count]),
            'can' => ['manage' => $request->user()->can('manage', InventoryCategory::class)],
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        InventoryCategory::query()->create([...$request->validated(), 'is_active' => $request->boolean('is_active', true)]);

        return back()->with('status', 'Category added.');
    }

    public function update(StoreCategoryRequest $request, InventoryCategory $category): RedirectResponse
    {
        $category->update([...$request->validated(), 'is_active' => $request->boolean('is_active', $category->is_active)]);

        return back()->with('status', 'Category updated.');
    }

    public function destroy(Request $request, InventoryCategory $category): RedirectResponse
    {
        $this->authorize('manage', $category);

        if ($category->items()->withTrashed()->exists()) {
            throw ValidationException::withMessages(['category' => 'This category has items. Deactivate it instead of deleting it.']);
        }

        $category->delete();

        return back()->with('status', 'Category deleted.');
    }
}