<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkGuardianAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('guardians.manage_accounts') ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
        ];
    }
}
