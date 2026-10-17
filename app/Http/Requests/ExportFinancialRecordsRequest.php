<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportFinancialRecordsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reports.financial.export') ?? false;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
        ];
    }
}
