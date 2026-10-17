<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GuardianAccountService
{
    public function linkExisting(Guardian $guardian, int $userId): void
    {
        DB::transaction(function () use ($guardian, $userId): void {
            $guardian = Guardian::query()->lockForUpdate()->findOrFail($guardian->id);
            $user = User::query()->lockForUpdate()->findOrFail($userId);

            if (! $user->hasRole('Parent')) {
                throw ValidationException::withMessages([
                    'user_id' => 'Select an account assigned the Parent role.',
                ]);
            }

            if ($guardian->user_id !== null && (int) $guardian->user_id !== $user->id) {
                throw ValidationException::withMessages([
                    'user_id' => 'Unlink the current portal account before linking another one.',
                ]);
            }

            if (Guardian::query()->where('user_id', $user->id)->whereKeyNot($guardian->id)->exists()) {
                throw ValidationException::withMessages([
                    'user_id' => 'This Parent account is already linked to another guardian profile.',
                ]);
            }

            $guardian->user()->associate($user);
            $guardian->save();
        });
    }

    public function unlink(Guardian $guardian): void
    {
        DB::transaction(function () use ($guardian): void {
            $guardian = Guardian::query()->lockForUpdate()->findOrFail($guardian->id);

            if ($guardian->user_id === null) {
                return;
            }

            $guardian->user()->dissociate();
            $guardian->save();
        });
    }

    public function invite(Guardian $guardian): User
    {
        $user = DB::transaction(function () use ($guardian): User {
            $guardian = Guardian::query()->lockForUpdate()->findOrFail($guardian->id);

            if ($guardian->user_id !== null) {
                throw ValidationException::withMessages([
                    'email' => 'This guardian already has a linked portal account.',
                ]);
            }

            $email = mb_strtolower(trim((string) $guardian->email));

            if ($email === '') {
                throw ValidationException::withMessages([
                    'email' => 'Add an email address to the guardian profile before sending an invitation.',
                ]);
            }

            if (User::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'An account already uses this email. Link the existing Parent account instead.',
                ]);
            }

            $user = User::query()->create([
                'name' => $guardian->name,
                'email' => $email,
                'password' => Str::random(64),
            ]);
            $user->assignRole('Parent');
            $guardian->user()->associate($user);
            $guardian->save();

            return $user;
        });

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => 'The portal account was created, but its password setup email could not be sent. Use password reset to resend the link.',
            ]);
        }

        return $user;
    }
}
