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
        // Only SA may context-switch via a request-supplied tenant_id.
        // For all other guards the membership lookup in resolveFromAuth() already
        // enforces the correct tenant — passing a user-supplied ID would allow a
        // tenant user to switch context to a different tenant.
        $requestedTenantId = \Illuminate\Support\Facades\Auth::guard('web')->check()
            ? ($request->input('tenant_id') ?? $request->query('tenant_id'))
            : null;

        TenantContext::resolveFromAuth($requestedTenantId);

        return $next($request);
    }
}
