<?php

namespace App\Services;

use App\Models\Party;
use App\Support\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PartyCacheService
{
    private const TTL_MINUTES = 30;

    public function __construct(private readonly TenantContext $tenantContext) {}

    public function all(): Collection
    {
        $tenantId = $this->tenantContext->requireId();

        return Cache::remember(
            $this->allPartiesKey($tenantId),
            now()->addMinutes(self::TTL_MINUTES),
            fn () => Party::query()
                ->select(['id', 'name', 'phone', 'address'])
                ->orderBy('name')
                ->get()
        );
    }

    public function unassignedForEmployees(): Collection
    {
        $tenantId = $this->tenantContext->requireId();

        return Cache::remember(
            $this->unassignedPartiesKey($tenantId),
            now()->addMinutes(self::TTL_MINUTES),
            fn () => Party::query()
                ->select(['id', 'name', 'phone', 'address'])
                ->whereDoesntHave('employees')
                ->orderBy('name')
                ->get()
        );
    }

    public function refreshAll(): void
    {
        $tenantId = $this->tenantContext->requireId();

        Cache::forget($this->allPartiesKey($tenantId));
        Cache::forget($this->unassignedPartiesKey($tenantId));

        $this->all();
        $this->unassignedForEmployees();
    }

    public function refreshUnassigned(): void
    {
        $tenantId = $this->tenantContext->requireId();

        Cache::forget($this->unassignedPartiesKey($tenantId));

        $this->unassignedForEmployees();
    }

    private function allPartiesKey(int $tenantId): string
    {
        return "tenant:{$tenantId}:parties:all:v1";
    }

    private function unassignedPartiesKey(int $tenantId): string
    {
        return "tenant:{$tenantId}:parties:unassigned:v1";
    }
}
