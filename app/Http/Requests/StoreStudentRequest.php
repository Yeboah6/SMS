<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStudentRequest extends StudentProfileRequest
{
    /** Letters, digits, spaces and / - _ . so legacy numbers such as 2019/045 or ADM-12 are accepted. */
    public const ADMISSION_NUMBER_FORMAT = '/^[\pL\pN][\pL\pN\s\/\-_.]*$/u';

    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->can('students.create')
            && $user?->can('guardians.create')
            && (! $this->boolean('invite_parent_accounts') || $user->can('guardians.manage_accounts'));
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'invite_parent_accounts' => ['sometimes', 'boolean'],
            // Optional: leave blank to generate the next number (ADM0001, ...).
            'admission_number' => [
                'nullable', 'string', 'max:50', 'regex:'.self::ADMISSION_NUMBER_FORMAT,
                Rule::unique('students', 'admission_number'),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('invite_parent_accounts') || $validator->errors()->isNotEmpty()) {
                return;
            }

            $guardianEmails = [];
            $seenEmails = [];

            foreach ($this->input('guardians', []) as $index => $guardian) {
                $email = mb_strtolower(trim((string) ($guardian['email'] ?? '')));

                if ($email === '') {
                    continue;
                }

                if (isset($seenEmails[$email])) {
                    $validator->errors()->add(
                        "guardians.{$index}.email",
                        'Each guardian must have a unique email address to receive a separate portal invitation.',
                    );

                    continue;
                }

                $seenEmails[$email] = true;
                $guardianEmails[$email] = $index;
            }

            if ($guardianEmails === []) {
                return;
            }

            $existingEmails = User::withTrashed()
                ->whereIn(DB::raw('LOWER(email)'), array_keys($guardianEmails))
                ->pluck('email')
                ->map(fn (string $email): string => mb_strtolower($email))
                ->all();

            foreach ($existingEmails as $email) {
                $index = $guardianEmails[$email];
                $validator->errors()->add(
                    "guardians.{$index}.email",
                    'An account already uses this email. Create the student without invitations, then link the existing Parent account.',
                );
            }
        });
    }
}
