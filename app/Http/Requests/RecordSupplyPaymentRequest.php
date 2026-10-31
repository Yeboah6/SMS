<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordSupplyPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('recordPayment', $this->route('sale')) ?? false;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'payment_method' => ['required', Rule::in(array_keys(config('supplies.payment_methods', [])))],
            'transaction_reference' => ['nullable', 'string', 'max:100'],
            'idempotency_key' => ['required', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
