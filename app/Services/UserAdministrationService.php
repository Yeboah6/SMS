<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class UserAdministrationService
{
    /**
     * @param  array{name: string, email: string, password: string, role: string}  $data
     */
    public function create(array $data, User $actor): User
    {
        return DB::transaction(function () use ($data, $actor): User {
            $role = Role::findByName($data['role'], 'web');

            if ($role->name === 'Super Admin' && ! $actor->hasRole('Super Admin')) {
                throw new AuthorizationException('Only a Super Admin can provision another Super Admin account.');
            }

            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $user->syncRoles([$role]);

            return $user;
        });
    }

    /**
     * @param  array{name: string, email: string, role: string, password?: string|null}  $data
     */
    public function update(User $user, array $data, User $actor): User
    {
        return DB::transaction(function () use ($user, $data, $actor): User {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $newRole = Role::findByName($data['role'], 'web');

            if (
                ($user->hasRole('Super Admin') || $newRole->name === 'Super Admin')
                && ! $actor->hasRole('Super Admin')
            ) {
                throw new AuthorizationException('Only a Super Admin can manage Super Admin accounts.');
            }

            if ($user->is($actor) && $user->hasRole('Super Admin') && $newRole->name !== 'Super Admin') {
                throw new AuthorizationException('You cannot remove your own Super Admin access.');
            }

            if ($user->hasRole('Super Admin') && $newRole->name !== 'Super Admin') {
                $this->ensureAnotherSuperAdminExists();
            }

            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
            ]);

            if (filled($data['password'] ?? null)) {
                $user->password = $data['password'];
            }

            $user->save();
            $user->syncRoles([$newRole]);

            return $user;
        });
    }

    public function delete(User $user, User $actor): void
    {
        DB::transaction(function () use ($user, $actor): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($user->is($actor)) {
                throw new AuthorizationException('You cannot delete your own account.');
            }

            if ($user->hasRole('Super Admin')) {
                if (! $actor->hasRole('Super Admin')) {
                    throw new AuthorizationException('Only a Super Admin can remove another Super Admin account.');
                }

                $this->ensureAnotherSuperAdminExists();
            }

            $user->delete();
        });
    }

    private function ensureAnotherSuperAdminExists(): void
    {
        $superAdmins = User::query()
            ->role('Super Admin')
            ->lockForUpdate()
            ->get(['users.id']);

        if ($superAdmins->count() <= 1) {
            throw new AuthorizationException('The final Super Admin account must remain active.');
        }
    }
}
