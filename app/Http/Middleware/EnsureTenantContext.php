<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user && filled($user->tenant_id ?? null), 403, 'Tenant access is required for this action.');

        app(TenantContext::class)->set((int) $user->tenant_id);

        return $next($request);
    }
}
