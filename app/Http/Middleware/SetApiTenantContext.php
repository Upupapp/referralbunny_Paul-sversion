<?php
namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;

/**
 * Resolves active tenant context for API routes from the authenticated user.
 * Replaces the unsafe pattern of reading tenant_id from request parameters.
 * Applied to all auth:sanctum API routes.
 */
class SetApiTenantContext
{
    public function handle(Request $request, Closure $next)
    {
        // All guards may pass a tenant_id to select which of their tenants to act in.
        // resolveFromAuth() validates the ID against the user's actual membership and
        // aborts 403 if they don't belong to that tenant — so this is safe for all guards.
        $requestedTenantId = $request->input('tenant_id') ?? $request->query('tenant_id');

        TenantContext::resolveFromAuth($requestedTenantId);

        return $next($request);
    }
}
