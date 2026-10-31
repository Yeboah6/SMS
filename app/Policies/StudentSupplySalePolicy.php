<?php

namespace App\Policies;

use App\Models\StudentSupplySale;
use App\Models\User;

class StudentSupplySalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('supplies.sales.view');
    }

    public function view(User $user, StudentSupplySale $sale): bool
    {
        return $user->can('supplies.sales.view');
    }

    public function create(User $user): bool
    {
        return $user->can('supplies.sales.create');
    }

    public function recordPayment(User $user, StudentSupplySale $sale): bool
    {
        return $user->can('supplies.payments.create') && ! in_array($sale->status, ['cancelled', 'returned'], true) && $sale->balance() > 0;
    }

    public function refund(User $user, StudentSupplySale $sale): bool
    {
        return $user->can('supplies.sales.refund') && in_array($sale->status, ['completed', 'partially_returned'], true);
    }

    public function cancel(User $user, StudentSupplySale $sale): bool
    {
        return $user->can('supplies.sales.cancel') && in_array($sale->status, ['completed', 'partially_returned'], true);
    }
}
