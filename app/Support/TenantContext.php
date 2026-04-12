<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use RuntimeException;

class TenantContext
{
    private ?int $tenantId = null;

    public function set(?int $tenantId): void
    {
        $this->tenantId = $tenantId !== null ? (int) $tenantId : null;
    }

    public function id(): ?int
    {
        if ($this->tenantId !== null) {
            return $this->tenantId;
        }

        $authUser = Auth::user();
        if (! $authUser || ! filled($authUser->tenant_id ?? null)) {
            return null;
        }

        return (int) $authUser->tenant_id;
    }

    public function requireId(): int
    {
        $tenantId = $this->id();

        if ($tenantId === null) {
            throw new RuntimeException('Tenant context is missing for this request.');
        }

        return $tenantId;
    }
}
