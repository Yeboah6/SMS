<?php

namespace App\Policies;

use App\Models\InventorySupplier;
use App\Models\User;

class InventorySupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('supplies.suppliers.view');
    }

    public function view(User $user, InventorySupplier $supplier): bool
    {
        return $user->can('supplies.suppliers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('supplies.suppliers.manage');
    }

    public function update(User $user, InventorySupplier $supplier): bool
    {
        return $user->can('supplies.suppliers.manage');
    }
}
