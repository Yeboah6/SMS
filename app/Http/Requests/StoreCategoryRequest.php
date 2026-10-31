<?php

namespace App\Http\Requests;

use App\Models\InventoryCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', InventoryCategory::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('inventory_categories', 'name')->ignore($this->route('category'))],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
