<?php

namespace App\Http\Requests;

use App\Support\LinkedProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Your own name and email. Changing the email (it is the sign-in) needs your current password, and details that
 * belong to a staff or student record are changed on that record, not here.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $user = $this->user();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => [Rule::requiredIf(fn (): bool => strcasecmp((string) $this->input('email'), $user->email) !== 0), 'nullable', 'current_password'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $user = $this->user();
                $profile = LinkedProfile::for($user);
                $current = ['name' => $user->name, 'email' => $user->email];

                foreach ($profile['locked'] ?? [] as $field) {
                    if ($this->input($field) !== $current[$field]) {
                        $validator->errors()->add($field, "Your {$field} is kept on your {$profile['type']} record. Ask an administrator to change it.");
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return ['current_password.required' => 'Enter your current password to change your email address.'];
    }
}