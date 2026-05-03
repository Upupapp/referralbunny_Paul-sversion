<?php

namespace App\Http\Middleware;

use App\Services\FeatureAccessService;
use App\Services\NotificationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FeatureAccessMiddleware
{
    public function __construct(
        private FeatureAccessService $featureAccess,
        private NotificationService  $notifications
    ) {}

    // Usage: ->middleware('feature.access:leads,create')
    public function handle(Request $request, Closure $next, string $resource, string $action = 'any'): Response
    {
        $tenantId = $this->resolveTenantId($request);

        if (!$tenantId) {
            return $next($request); // no tenant context = platform admin, allow
        }

        // 1. Check feature flag for write actions
        if ($action !== 'read') {
            $featureResult = $this->checkFeatureFlag($tenantId, $resource);
            if (!$featureResult['allowed']) {
                $this->maybeNotify($tenantId, $resource, 'feature_blocked');
                return response()->json($featureResult, 403);
            }
        }

        // 2. Check resource limits for create actions
        if ($action === 'create') {
            $limitResult = $this->featureAccess->checkLimit($tenantId, $resource);
            if (!$limitResult['allowed']) {
                $this->maybeNotify($tenantId, $resource, 'limit_reached');
                return response()->json($limitResult, 403);
            }

            // Warn at 80%
            if (!empty($limitResult['near_limit'])) {
                $this->maybeNotify($tenantId, $resource, 'near_limit');
            }
        }

        $response = $next($request);

        // 3. Track usage on successful create
        if ($action === 'create' && $response->getStatusCode() < 300) {
            $this->featureAccess->trackResourceUsage($tenantId, $resource);
            $this->featureAccess->trackFeatureUsage($tenantId, $resource);
        }

        return $response;
    }

    private function resolveTenantId(Request $request): ?string
    {
        // Route parameter: /tenant/{tenantId}/...
        if ($request->route('tenantId')) {
            return $request->route('tenantId');
        }
        if ($request->route('tenant')) {
            $tenant = $request->route('tenant');
            return is_object($tenant) ? $tenant->id : $tenant;
        }

        // Request body or query string
        return $request->input('tenant_id') ?? $request->query('tenant_id');
    }

    private function checkFeatureFlag(string $tenantId, string $resource): array
    {
        $featureMap = [
            'messages'  => 'messaging',
            'analytics' => 'advanced_analytics',
            'templates' => 'template_customization',
            'api'       => 'api_access',
        ];

        $feature = $featureMap[$resource] ?? null;
        if (!$feature) return ['allowed' => true];

        return $this->featureAccess->checkFeature($tenantId, $feature);
    }

    private function maybeNotify(string $tenantId, string $resource, string $type): void
    {
        try {
            match ($type) {
                'limit_reached' => $this->notifications->send(
                    category:  'growth',
                    type:      'action_required',
                    priority:  'high',
                    message:   "Tenant has reached their {$resource} limit.",
                    tenantId:  $tenantId,
                    actionUrl: "/platform/tenants/{$tenantId}",
                    channel:   'in_app',
                    metadata:  ['resource' => $resource, 'event' => 'limit_reached']
                ),
                'near_limit' => $this->notifications->send(
                    category:  'growth',
                    type:      'warning',
                    priority:  'medium',
                    message:   "Tenant is approaching their {$resource} limit (80%+).",
                    tenantId:  $tenantId,
                    actionUrl: "/platform/tenants/{$tenantId}",
                    channel:   'in_app',
                    metadata:  ['resource' => $resource, 'event' => 'near_limit']
                ),
                'feature_blocked' => $this->notifications->send(
                    category:  'growth',
                    type:      'warning',
                    priority:  'medium',
                    message:   "Tenant attempted to use blocked feature: {$resource}.",
                    tenantId:  $tenantId,
                    actionUrl: "/platform/tenants/{$tenantId}",
                    channel:   'in_app',
                    metadata:  ['resource' => $resource, 'event' => 'feature_blocked']
                ),
                default => null,
            };
        } catch (\Throwable) {
            // never block the request over a notification failure
        }
    }
}
