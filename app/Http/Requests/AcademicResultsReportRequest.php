<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcademicResultsReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('results.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
            'exam_id' => ['nullable', 'integer', 'exists:exams,id'],
        ];
    }
}
