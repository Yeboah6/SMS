<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkExternalFulfilmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('supplies.requirements.fulfil_external') ?? false;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
