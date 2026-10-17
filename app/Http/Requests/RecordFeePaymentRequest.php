<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordFeePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payments.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'payment_method' => ['required', Rule::in(['cash', 'bank_transfer', 'card', 'mobile_money', 'cheque', 'other'])],
            'transaction_reference' => ['nullable', 'string', 'max:100', Rule::unique('fee_payments', 'transaction_reference')],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
