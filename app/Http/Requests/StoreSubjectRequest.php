<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('subjects.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('subjects', 'code')],
            'name' => ['required', 'string', 'max:150', Rule::unique('subjects', 'name')],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
