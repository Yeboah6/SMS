<?php

namespace App\Http\Requests;

use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sections.update') ?? false;
    }

    public function rules(): array
    {
        $section = $this->route('section');

        abort_unless($section instanceof Section, 404);

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('sections', 'name')
                    ->where('class_id', $section->class_id)
                    ->ignore($section->id),
            ],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ];
    }
}
