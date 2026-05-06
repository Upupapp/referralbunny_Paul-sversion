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
        // Resolve tenant from authenticated identity, optionally validating
        // against a tenant_id query param (for SA context-switching only)
        $requestedTenantId = $request->input('tenant_id') ?? $request->query('tenant_id');

        TenantContext::resolveFromAuth($requestedTenantId);

        return $next($request);
    }
}
