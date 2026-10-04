<?php

namespace App\Policies;

use App\Models\AcademicYear;
use App\Models\User;

class AcademicYearPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('academic_years.view');
    }

    public function view(User $user, AcademicYear $academicYear): bool
    {
        return $user->can('academic_years.view');
    }

    public function create(User $user): bool
    {
        return $user->can('academic_years.create');
    }

    public function update(User $user, AcademicYear $academicYear): bool
    {
        return $user->can('academic_years.update');
    }

    public function delete(User $user, AcademicYear $academicYear): bool
    {
        return $user->can('academic_years.delete');
    }

    public function activate(User $user, AcademicYear $academicYear): bool
    {
        return $user->can('academic_years.activate');
    }

    public function complete(User $user, AcademicYear $academicYear): bool
    {
        return $user->can('academic_years.complete');
    }

    public function lock(User $user, AcademicYear $academicYear): bool
    {
        return $user->can('academic_years.lock');
    }
}
