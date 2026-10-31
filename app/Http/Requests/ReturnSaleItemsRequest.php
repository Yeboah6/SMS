<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturnSaleItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('refund', $this->route('sale')) ?? false;
    }

    public function rules(): array
    {
        $methods = array_keys(config('supplies.payment_methods', []));

        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:0', 'max:10000'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'restock' => ['sometimes', 'boolean'],
            'refund_method' => ['nullable', Rule::in($methods)],
            'refund_reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
