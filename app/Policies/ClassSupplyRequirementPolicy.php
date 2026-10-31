<?php

namespace App\Policies;

use App\Models\ClassSupplyRequirement;
use App\Models\User;

class ClassSupplyRequirementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('supplies.requirements.view');
    }

    public function manage(User $user, ?ClassSupplyRequirement $requirement = null): bool
    {
        return $user->can('supplies.requirements.manage');
    }
}
