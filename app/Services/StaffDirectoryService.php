<?php

namespace App\Services;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffDirectoryService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Staff
    {
        return DB::transaction(function () use ($data): Staff {
            if ($data['is_teacher']) {
                $user = User::query()->lockForUpdate()->find($data['user_id'] ?? null);

                if (! $user?->hasRole('Teacher')) {
                    throw ValidationException::withMessages([
                        'user_id' => 'Link a user account with the Teacher role to create a teacher profile.',
                    ]);
                }
            }

            return Staff::query()->create($data);
        });
    }
}
