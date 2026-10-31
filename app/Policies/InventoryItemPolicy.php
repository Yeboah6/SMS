<?php

namespace App\Policies;

use App\Models\InventoryItem;
use App\Models\User;

class InventoryItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('supplies.items.view');
    }

    public function view(User $user, InventoryItem $item): bool
    {
        return $user->can('supplies.items.view');
    }

    public function create(User $user): bool
    {
        return $user->can('supplies.items.create');
    }

    public function update(User $user, InventoryItem $item): bool
    {
        return $user->can('supplies.items.update');
    }

    public function adjustStock(User $user, InventoryItem $item): bool
    {
        return $user->can('supplies.stock.adjust');
    }
}
