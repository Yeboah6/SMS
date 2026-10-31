<?php

namespace App\Http\Requests;

use App\Services\StaffDirectoryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'string', Rule::in(StaffDirectoryService::LINKABLE_ROLES)],
            'password' => ['prohibited'],
            'password_confirmation' => ['prohibited'],
            'phone' => ['nullable', 'string', 'max:32'],
            'joining_date' => ['nullable', 'date'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'designation_id' => ['nullable', 'exists:designations,id'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'qualifications' => ['nullable', 'string', 'max:2000'],
        ];
    }
}