<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenantId = app(TenantContext::class)->id();

            if ($tenantId !== null) {
                $builder->where($builder->qualifyColumn('tenant_id'), $tenantId);
            }
        });

        static::creating(function ($model): void {
            if (filled($model->tenant_id ?? null)) {
                return;
            }

            $tenantId = app(TenantContext::class)->id() ?? Tenant::query()->value('id');
            if ($tenantId === null) {
                throw new RuntimeException('Cannot create tenant-scoped record without tenant context.');
            }

            $model->tenant_id = $tenantId;
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
