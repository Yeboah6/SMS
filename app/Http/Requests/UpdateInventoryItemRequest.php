<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Stock quantity is deliberately absent: it only changes through stock movements. */
class UpdateInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('item')) ?? false;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:inventory_categories,id'],
            'sku' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9._\-\/]+$/', Rule::unique('inventory_items', 'sku')->ignore($this->route('item'))],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'unit' => ['required', 'string', 'max:30'],
            'cost_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'selling_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'reorder_level' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
