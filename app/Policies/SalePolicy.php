<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class SalePolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->hasTenant($user);
    }

    public function view(User $user, Sale $sale): bool
    {
        return $this->sameTenant($user, (int) $sale->tenant_id);
    }

    public function create(User $user): bool
    {
        return $this->hasTenant($user);
    }

    public function update(User $user, Sale $sale): bool
    {
        return $this->sameTenant($user, (int) $sale->tenant_id);
    }

    public function delete(User $user, Sale $sale): bool
    {
        return $this->sameTenant($user, (int) $sale->tenant_id) && $user->isAdmin();
    }
}
