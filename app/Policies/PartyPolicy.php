<?php

namespace App\Policies;

use App\Models\Party;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class PartyPolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->hasTenant($user);
    }

    public function view(User $user, Party $party): bool
    {
        return $this->sameTenant($user, (int) $party->tenant_id);
    }

    public function create(User $user): bool
    {
        return $this->hasTenant($user);
    }

    public function update(User $user, Party $party): bool
    {
        return $this->sameTenant($user, (int) $party->tenant_id);
    }

    public function delete(User $user, Party $party): bool
    {
        return $this->sameTenant($user, (int) $party->tenant_id) && $user->isAdmin();
    }
}
