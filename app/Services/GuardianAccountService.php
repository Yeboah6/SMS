<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\User;
use App\Notifications\GuardianPortalInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GuardianAccountService
{
    public function __construct(private AuditLogService $auditLogs) {}

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
            $this->auditLogs->recordRelationshipChange($user, 'roles.updated', ['roles'], null);
            $guardian->user()->associate($user);
            $guardian->save();

            return $user;
        });

        $this->sendInvitation($user);

        return $user;
    }

    public function resendInvitation(Guardian $guardian): void
    {
        $user = $guardian->user;

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => 'This guardian has no portal account yet. Send an invitation first.',
            ]);
        }

        if ($user->email_verified_at !== null) {
            throw ValidationException::withMessages([
                'email' => 'This parent has already activated their account.',
            ]);
        }

        $this->sendInvitation($user);
    }

    private function sendInvitation(User $user): void
    {
        // createToken bypasses the broker's resend throttle and replaces any older token.
        $token = Password::broker()->createToken($user);

        try {
            $user->notify(new GuardianPortalInvitation($token));
        } catch (\Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'email' => 'The portal account was created, but the invitation email could not be sent. Use "Resend invitation" to try again.',
            ]);
        }
    }
}
