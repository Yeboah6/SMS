<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $user = $this->route('user');

        return $user instanceof User
            && ($actor?->can('users.update') ?? false)
            && ($actor->hasRole('Super Admin')
                || (! $user->hasRole('Super Admin') && $this->input('role') !== 'Super Admin'));
    }

    public function rules(): array
    {
        $user = $this->route('user');

        abort_unless($user instanceof User, 404);

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['nullable', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
            'role' => ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ];
    }
}
