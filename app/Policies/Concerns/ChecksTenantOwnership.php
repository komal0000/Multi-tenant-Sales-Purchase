<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait ChecksTenantOwnership
{
    protected function hasTenant(User $user): bool
    {
        return filled($user->tenant_id ?? null);
    }

    protected function sameTenant(User $user, ?int $tenantId): bool
    {
        return $this->hasTenant($user) && (int) $user->tenant_id === (int) $tenantId;
    }
}
