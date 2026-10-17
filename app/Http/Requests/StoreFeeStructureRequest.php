<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFeeStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->isMethod('put') ? 'fee_structures.update' : 'fee_structures.create';

        return $this->user()?->can($permission) ?? false;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'fee_head_id' => [
                'required',
                'integer',
                'exists:fee_heads,id',
                Rule::unique('fee_structures', 'fee_head_id')
                    ->where(fn ($query) => $query
                        ->where('academic_year_id', $this->input('academic_year_id'))
                        ->where('class_id', $this->input('class_id')))
                    ->ignore($this->route('feeStructure')),
            ],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'due_date' => ['required', 'date'],
        ];
    }
}
