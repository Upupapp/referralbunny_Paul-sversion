<?php

namespace App\Services;

use App\Models\Reseller;

class TenantPlanService
{
    public function __construct(
        private readonly FeatureAccessService $featureAccess
    ) {}

    /**
     * Check whether the tenant is allowed to invite another referrer
     * based on their current plan's max_resellers limit.
     *
     * Returns an array with:
     *   allowed  bool
     *   reason   string|null   (human-readable message when not allowed)
     *   current  int
     *   max      int  (-1 = unlimited)
     */
    public function canInviteReferrer(string $tenantId): array
    {
        $check = $this->featureAccess->checkLimit($tenantId, 'resellers');

        if ($check['allowed']) {
            return [
                'allowed' => true,
                'reason'  => null,
                'current' => $check['current'] ?? 0,
                'max'     => $check['max'] ?? -1,
            ];
        }

        // Derive current count from DB as the usage metric may lag
        $current = Reseller::where('tenant_id', $tenantId)->count();
        $max     = $check['max'] ?? 0;

        return [
            'allowed' => false,
            'reason'  => $check['message'] ?? "You've reached your referrer limit ({$current}/{$max}) on your current plan. Upgrade to add more referrers.",
            'current' => $current,
            'max'     => $max,
        ];
    }
}
