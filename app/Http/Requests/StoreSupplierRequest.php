<?php

namespace App\Http\Requests;

use App\Models\InventorySupplier;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $supplier = $this->route('supplier');

        return $supplier
            ? ($this->user()?->can('update', $supplier) ?? false)
            : ($this->user()?->can('create', InventorySupplier::class) ?? false);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
