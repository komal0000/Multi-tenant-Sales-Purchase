<?php

namespace App\Policies;

use App\Models\Item;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class ItemPolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->hasTenant($user);
    }

    public function create(User $user): bool
    {
        return $this->hasTenant($user);
    }

    public function view(User $user, Item $item): bool
    {
        return $this->sameTenant($user, (int) $item->tenant_id);
    }

    public function update(User $user, Item $item): bool
    {
        return $this->sameTenant($user, (int) $item->tenant_id);
    }

    public function delete(User $user, Item $item): bool
    {
        return $this->sameTenant($user, (int) $item->tenant_id);
    }
}
