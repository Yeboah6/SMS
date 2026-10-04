<?php

namespace App\Http\Requests;

use App\Models\AcademicYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcademicYearRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100', Rule::unique('academic_years', 'name')->ignore($academicYear)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ];
    }
}
