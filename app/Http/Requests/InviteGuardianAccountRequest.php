<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InviteGuardianAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('guardians.manage_accounts') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
