<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class StudentAccountService
{
    /**
     * Create the login account for a student (idempotent). Students sign in with
     * their admission number and the default password, and must change it on
     * first sign-in.
     */
    public function createFor(Student $student): User
    {
        return DB::transaction(function () use ($student): User {
            $student = Student::query()->lockForUpdate()->findOrFail($student->getKey());

            if ($student->user_id !== null) {
                return $student->user;
            }

            $user = new User(['name' => $this->displayName($student)]);
            $user->forceFill([
                'email' => null,
                'password' => Hash::make($this->defaultPassword()),
                'must_change_password' => true,
            ])->save();

            // Throws RoleDoesNotExist if the "Student" role has not been seeded.
            $user->assignRole('Student');

            $student->forceFill(['user_id' => $user->id])->save();

            return $user;
        });
    }

    /**
     * Put the account back on the default password (forgotten password). Staff
     * use this because students have no email for a reset link.
     */
    public function resetPassword(Student $student): User
    {
        $user = $this->createFor($student);

        $user->forceFill([
            'password' => Hash::make($this->defaultPassword()),
            'must_change_password' => true,
        ])->save();

        return $user;
    }

    public function deleteUser(?int $userId): void
    {
        if ($userId !== null) {
            User::query()->whereKey($userId)->delete();
        }
    }

    private function defaultPassword(): string
    {
        $password = (string) config('school.student_default_password');

        if (strlen($password) < 8) {
            throw new RuntimeException('Set STUDENT_DEFAULT_PASSWORD (8+ characters) in .env before creating student accounts.');
        }

        return $password;
    }

    private function displayName(Student $student): string
    {
        return implode(' ', array_filter([$student->first_name, $student->middle_name, $student->last_name]));
    }
}
