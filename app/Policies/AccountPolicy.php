<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class AccountPolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->hasTenant($user);
    }

    public function view(User $user, Account $account): bool
    {
        return $this->sameTenant($user, (int) $account->tenant_id);
    }

    public function create(User $user): bool
    {
        return $this->hasTenant($user);
    }

    public function update(User $user, Account $account): bool
    {
        return $this->sameTenant($user, (int) $account->tenant_id);
    }

    public function delete(User $user, Account $account): bool
    {
        return $this->sameTenant($user, (int) $account->tenant_id) && $user->isAdmin();
    }
}
