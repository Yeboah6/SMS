<?php

namespace App\Http\Requests;

use App\Models\ClassSupplyRequirement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', ClassSupplyRequirement::class) ?? false;
    }

    public function rules(): array
    {
        $creating = $this->route('requirement') === null;

        return [
            'academic_year_id' => [$creating ? 'required' : 'prohibited', 'integer', 'exists:academic_years,id'],
            'class_id' => [$creating ? 'required' : 'prohibited', 'integer', 'exists:classes,id'],
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
            'item_id' => ['required', 'integer', Rule::exists('inventory_items', 'id')->whereNull('deleted_at')],
            'quantity_per_student' => ['required', 'integer', 'min:1', 'max:1000'],
            'is_required' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
