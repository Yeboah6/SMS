<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Used for both adding and renaming a department. */
class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('departments.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('departments', 'name')->ignore($this->route('department'))],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'A department with this name already exists.'];
    }
}