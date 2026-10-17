<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UpdateRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role
            && $role->name !== 'Super Admin'
            && $role->guard_name === 'web'
            && ($this->user()?->can('roles.update') ?? false)
            && ($this->user()?->can('permissions.assign') ?? false);
    }

    public function rules(): array
    {
        return [
            'permission_ids' => ['present', 'array'],
            'permission_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('permissions', 'id')->where('guard_name', 'web'),
            ],
        ];
    }
}
