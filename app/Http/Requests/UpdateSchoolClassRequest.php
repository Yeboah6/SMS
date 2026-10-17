<?php

namespace App\Http\Requests;

use App\Models\SchoolClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSchoolClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('classes.update') ?? false;
    }

    public function rules(): array
    {
        $schoolClass = $this->route('schoolClass');

        abort_unless($schoolClass instanceof SchoolClass, 404);

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('classes', 'name')
                    ->where('academic_year_id', $schoolClass->academic_year_id)
                    ->ignore($schoolClass->id),
            ],
            'sort_order' => ['required', 'integer', 'between:0,9999'],
        ];
    }
}
