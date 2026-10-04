<?php

namespace App\Http\Requests;

use App\Models\AcademicYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('academic_years.update') ?? false;
    }

    public function rules(): array
    {
        $academicYear = $this->route('academicYear');

        abort_unless($academicYear instanceof AcademicYear, 404);

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('terms', 'name')->where('academic_year_id', $academicYear->id),
            ],
            'start_date' => [
                'required',
                'date',
                'after_or_equal:'.$academicYear->start_date->toDateString(),
            ],
            'end_date' => [
                'required',
                'date',
                'after:start_date',
                'before_or_equal:'.$academicYear->end_date->toDateString(),
            ],
        ];
    }
}
