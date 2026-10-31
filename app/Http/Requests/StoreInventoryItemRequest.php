<?php

namespace App\Http\Requests;

use App\Models\InventoryItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', InventoryItem::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('inventory_categories', 'id')->where('is_active', true)],
            'sku' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9._\-\/]+$/', Rule::unique('inventory_items', 'sku')],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'unit' => ['required', 'string', 'max:30'],
            'cost_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'selling_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'reorder_level' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['required', 'boolean'],
            'opening_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
