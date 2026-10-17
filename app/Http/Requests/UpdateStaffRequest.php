<?php

namespace App\Http\Requests;

use App\Services\StaffDirectoryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Replaces your existing UpdateStaffRequest (I haven't seen it): copy over anything extra it had.
 * Staff number is no longer editable.
 */
class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.update') ?? false;
    }

    public function rules(): array
    {
        $staff = $this->route('staff');

        return [
            'role' => ['required', Rule::in(StaffDirectoryService::LINKABLE_ROLES)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff->user_id)],
            // Optional when an account exists (blank keeps the current password); required to create one.
            'password' => [Rule::requiredIf($staff->user_id === null), 'nullable', 'confirmed', Password::defaults()],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'designation_id' => ['nullable', 'integer', 'exists:designations,id'],
            'phone' => ['nullable', 'string', 'max:50'],
            'joining_date' => ['nullable', 'date', 'before_or_equal:today'],
            'qualifications' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}