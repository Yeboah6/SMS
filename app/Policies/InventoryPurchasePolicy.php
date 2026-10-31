<?php

namespace App\Policies;

use App\Models\InventoryPurchase;
use App\Models\User;

class InventoryPurchasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('supplies.purchases.view');
    }

    public function view(User $user, InventoryPurchase $purchase): bool
    {
        return $user->can('supplies.purchases.view');
    }

    public function create(User $user): bool
    {
        return $user->can('supplies.purchases.create');
    }

    public function reverse(User $user, InventoryPurchase $purchase): bool
    {
        return $user->can('supplies.purchases.reverse') && $purchase->status === 'received';
    }
}
