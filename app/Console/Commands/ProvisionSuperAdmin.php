<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

#[Signature('school:provision-super-admin {name} {email}')]
#[Description('Create the first Super Admin with a securely prompted password')]
class ProvisionSuperAdmin extends Command
{
    public function handle(): int
    {
        if (User::query()->role('Super Admin')->exists()) {
            $this->error('A Super Admin already exists. Use the Users page to manage accounts.');

            return self::FAILURE;
        }

        $role = Role::query()
            ->where('name', 'Super Admin')
            ->where('guard_name', 'web')
            ->first();

        if ($role === null) {
            $this->error('The Super Admin role is missing. Run "php artisan db:seed" first.');

            return self::FAILURE;
        }

        if (! $this->input->isInteractive()) {
            $this->error('This command requires an interactive terminal to enter the password securely.');

            return self::FAILURE;
        }

        $name = (string) $this->argument('name');
        $email = (string) $this->argument('email');
        $password = $this->secret('Password');
        $passwordConfirmation = $this->secret('Confirm password');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(12)->letters()->mixedCase()->numbers()],
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        if (! is_string($passwordConfirmation) || ! hash_equals((string) $password, $passwordConfirmation)) {
            $this->error('The password confirmation does not match.');

            return self::FAILURE;
        }

        $created = DB::transaction(function () use ($name, $email, $password): bool {
            $role = Role::query()
                ->where('name', 'Super Admin')
                ->where('guard_name', 'web')
                ->lockForUpdate()
                ->firstOrFail();

            if (User::query()->role('Super Admin')->exists()) {
                return false;
            }

            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make((string) $password),
            ]);
            $user->assignRole($role);

            return true;
        });

        if (! $created) {
            $this->error('A Super Admin was provisioned while this command was running.');

            return self::FAILURE;
        }

        $this->info('The first Super Admin account was created successfully.');

        return self::SUCCESS;
    }
}
