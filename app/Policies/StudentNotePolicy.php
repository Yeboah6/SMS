<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\StudentNote;
use App\Models\User;

class StudentNotePolicy
{
    public function viewAny(User $user, Student $student): bool
    {
        return $user->can('student_notes.view')
            && $user->can('view', $student);
    }

    public function view(User $user, StudentNote $studentNote): bool
    {
        return $user->can('student_notes.view')
            && $user->can('view', $studentNote->student);
    }

    public function create(User $user, Student $student): bool
    {
        return $user->can('student_notes.create')
            && $user->can('view', $student);
    }

    public function update(User $user, StudentNote $studentNote): bool
    {
        return $user->can('student_notes.update')
            && $user->can('view', $studentNote->student)
            && ($user->can('student_notes.manage') || $studentNote->author_id === $user->id);
    }

    public function delete(User $user, StudentNote $studentNote): bool
    {
        return $user->can('student_notes.delete')
            && $user->can('view', $studentNote->student)
            && ($user->can('student_notes.manage') || $studentNote->author_id === $user->id);
    }
}
