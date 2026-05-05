<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DetectTenantSubdomain
{
    public function handle(Request $request, Closure $next)
    {
        $host      = $request->getHost();
        $appDomain = config('app.domain', 'referralbunny.ai');

        // Only act if this is a subdomain of our domain
        if ($host === $appDomain || !str_ends_with($host, '.' . $appDomain)) {
            return $next($request);
        }

        $subdomain = substr($host, 0, -(strlen($appDomain) + 1));

        // Skip well-known subdomains
        if (in_array($subdomain, ['www', 'api', 'mail', 'admin', 'staging'])) {
            return $next($request);
        }

        $tenant = DB::table('tenants')
            ->where('subdomain', $subdomain)
            ->first();

        if (!$tenant) {
            abort(404, "Tenant \"{$subdomain}\" not found.");
        }

        if (in_array($tenant->status ?? '', ['suspended', 'cancelled'])) {
            abort(403, 'This tenant workspace is suspended.');
        }

        // Inject tenantId into route parameters so controllers + middleware receive it
        if ($request->route()) {
            $request->route()->setParameter('tenantId', $tenant->id);
        }

        // Also store on request for middleware that reads it before routing
        $request->merge(['_subdomain_tenant_id' => $tenant->id]);

        return $next($request);
    }
}
