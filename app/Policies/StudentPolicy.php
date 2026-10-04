<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function view(User $user, Student $student): bool
    {
        return $user->can('students.view')
            && Student::query()->visibleTo($user)->whereKey($student->getKey())->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('students.create') && $user->can('guardians.create');
    }

    public function update(User $user, Student $student): bool
    {
        return $user->can('students.update')
            && Student::query()->visibleTo($user)->whereKey($student->getKey())->exists();
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->can('students.delete');
    }

    public function enrol(User $user, Student $student): bool
    {
        return $user->can('students.enrol')
            && Student::query()->visibleTo($user)->whereKey($student->getKey())->exists();
    }

    public function manageDocuments(User $user, Student $student): bool
    {
        return $user->can('students.documents.manage')
            && Student::query()->visibleTo($user)->whereKey($student->getKey())->exists();
    }
}
