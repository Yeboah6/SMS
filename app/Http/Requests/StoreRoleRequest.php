<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('roles.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->where('guard_name', 'web'),
            ],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('permissions', 'id')->where('guard_name', 'web'),
            ],
        ];
    }
}
