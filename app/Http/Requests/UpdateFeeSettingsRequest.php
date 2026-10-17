<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFeeSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'fee_settings' => ['required', 'array:currency_code,default_due_days'],
            'fee_settings.currency_code' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
            'fee_settings.default_due_days' => ['required', 'integer', 'between:1,365'],
        ];
    }
}