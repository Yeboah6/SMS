<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Used for both adding and renaming a designation. */
class StoreDesignationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('designations.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('designations', 'name')->ignore($this->route('designation'))],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'A designation with this name already exists.'];
    }
}