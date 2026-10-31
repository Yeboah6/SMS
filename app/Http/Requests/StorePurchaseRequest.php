<?php

namespace App\Http\Requests;

use App\Models\InventoryPurchase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', InventoryPurchase::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', Rule::exists('inventory_suppliers', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'purchase_date' => ['required', 'date', 'before_or_equal:today'],
            // The supplier's own invoice or delivery number: one stock receipt per document.
            'reference' => ['nullable', 'string', 'max:100', Rule::unique('inventory_purchases', 'reference')->where('supplier_id', $this->input('supplier_id'))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'update_cost_price' => ['sometimes', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.item_id' => ['required', 'integer', 'distinct', Rule::exists('inventory_items', 'id')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit_cost' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
        ];
    }
}
