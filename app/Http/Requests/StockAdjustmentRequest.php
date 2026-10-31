<?php

namespace App\Http\Requests;

use App\Services\InventoryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('adjustStock', $this->route('item')) ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(InventoryService::MANUAL_TYPES)],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
