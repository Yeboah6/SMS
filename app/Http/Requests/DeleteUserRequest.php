<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class DeleteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $user = $this->route('user');

        return $user instanceof User
            && ($actor?->can('users.delete') ?? false)
            && (! $user->hasRole('Super Admin') || $actor->hasRole('Super Admin'));
    }

    public function rules(): array
    {
        return [];
    }
}
