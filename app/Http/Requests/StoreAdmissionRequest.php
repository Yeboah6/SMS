<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admissions.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'parent_name' => ['required', 'string', 'max:255'],
            'parent_phone' => ['required', 'string', 'max:50'],
            'parent_email' => ['nullable', 'email', 'max:255'],
            'parent_occupation' => ['nullable', 'string', 'max:150'],
            'parent_address' => ['nullable', 'string', 'max:2000'],
            'child_first_name' => ['required', 'string', 'max:100'],
            'child_middle_name' => ['nullable', 'string', 'max:100'],
            'child_last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', Rule::in(['female', 'male', 'other', 'prefer_not_to_say'])],
            'child_address' => ['nullable', 'string', 'max:2000'],
            'applying_for' => ['required', 'string', 'max:100'],
            'source' => ['required', Rule::in(['walk_in', 'website', 'referral', 'phone', 'social_media'])],
        ];
    }
}
