<?php

namespace App\Policies;

use App\Models\InventoryCategory;
use App\Models\User;

class InventoryCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('supplies.items.view');
    }

    public function manage(User $user, ?InventoryCategory $category = null): bool
    {
        return $user->can('supplies.categories.manage');
    }
}
