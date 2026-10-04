<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSchoolClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('classes.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('classes', 'name')->where('academic_year_id', $this->integer('academic_year_id')),
            ],
            'sort_order' => ['sometimes', 'integer', 'between:0,9999'],
        ];
    }
}
