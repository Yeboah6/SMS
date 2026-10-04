<?php

namespace App\Http\Requests;

use App\Models\SchoolClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sections.create') ?? false;
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
                Rule::unique('sections', 'name')->where('class_id', $schoolClass->id),
            ],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ];
    }
}
