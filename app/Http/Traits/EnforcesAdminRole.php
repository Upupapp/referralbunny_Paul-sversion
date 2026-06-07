<?php

namespace App\Http\Traits;

use App\Services\TenantContext;
use Illuminate\Support\Facades\Auth;

trait EnforcesAdminRole
{
    /** Per-request role cache keyed by "tenantId:userId" to avoid repeated DB queries. */
    private static array $roleCache = [];

    protected function isAdminOrManager(): bool
    {
        if (Auth::guard('web')->check()) return true;

        $userId = Auth::guard('tenant')->id();
        if (!$userId) return false;

        $tenantId = TenantContext::id();
        if (!$tenantId) return false;

        $cacheKey = "{$tenantId}:{$userId}";
        if (!array_key_exists($cacheKey, self::$roleCache)) {
            $role = \App\Models\TenantMembership::where('tenant_user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->value('role');
            self::$roleCache[$cacheKey] = $role;
        }

        return in_array(self::$roleCache[$cacheKey], ['owner', 'admin', 'manager']);
    }

    protected function resolveActor(): array
    {
        if (Auth::guard('tenant')->check()) {
            $userId   = Auth::guard('tenant')->id();
            $tenantId = TenantContext::id();

            if ($tenantId && $userId) {
                $cacheKey = "{$tenantId}:{$userId}";
                $role     = self::$roleCache[$cacheKey]
                    ?? \App\Models\TenantMembership::where('tenant_user_id', $userId)
                        ->where('tenant_id', $tenantId)->where('status', 'active')->value('role');
            } else {
                $role = null;
            }

            return [$userId, $role ?? null];
        }
        if (Auth::guard('web')->check()) {
            return [Auth::guard('web')->user()->id, 'admin'];
        }
        if (Auth::guard('reseller')->check()) {
            return [Auth::guard('reseller')->user()->id, 'referrer'];
        }
        return [null, 'system'];
    }
}
