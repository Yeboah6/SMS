<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportFeeStructuresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('fee_structures.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
        ];
    }
}
