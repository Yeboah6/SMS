<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('permissions.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/',
                Rule::unique('permissions', 'name')->where('guard_name', 'web'),
            ],
        ];
    }
}
