<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CancelSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cancel', $this->route('sale')) ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'refund_method' => ['nullable', Rule::in(array_keys(config('supplies.payment_methods', [])))],
            'refund_reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
