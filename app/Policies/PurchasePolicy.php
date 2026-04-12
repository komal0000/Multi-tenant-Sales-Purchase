<?php

namespace App\Policies;

use App\Models\Purchase;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class PurchasePolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->hasTenant($user);
    }

    public function view(User $user, Purchase $purchase): bool
    {
        return $this->sameTenant($user, (int) $purchase->tenant_id);
    }

    public function create(User $user): bool
    {
        return $this->hasTenant($user);
    }

    public function update(User $user, Purchase $purchase): bool
    {
        return $this->sameTenant($user, (int) $purchase->tenant_id);
    }

    public function delete(User $user, Purchase $purchase): bool
    {
        return $this->sameTenant($user, (int) $purchase->tenant_id) && $user->isAdmin();
    }
}
