<?php

namespace App\Http\Requests;

use App\Models\StudentSupplySale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** No prices are accepted from the browser: the service reads each price from the item. */
class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', StudentSupplySale::class) ?? false;
    }

    public function rules(): array
    {
        $methods = array_keys(config('supplies.payment_methods', []));

        return [
            'student_id' => ['required', 'integer', Rule::exists('students', 'id')->whereNull('deleted_at')],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.item_id' => ['required', 'integer', Rule::exists('inventory_items', 'id')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'discount_type' => ['nullable', Rule::in(['amount', 'percent'])],
            'discount_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'override_stock' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment' => ['nullable', 'array'],
            'payment.amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'payment.payment_method' => ['required_with:payment.amount', 'nullable', Rule::in($methods)],
            'payment.transaction_reference' => ['nullable', 'string', 'max:100'],
            'payment.idempotency_key' => ['nullable', 'string', 'max:64'],
        ];
    }
}
