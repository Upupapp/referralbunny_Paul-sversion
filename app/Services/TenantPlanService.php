<?php

namespace App\Services;

use App\Models\Reseller;
use App\Models\Subscription;
use App\Models\TenantMembership;
use Illuminate\Support\Facades\Cache;

class TenantPlanService
{
    // Cache lifetime in seconds (5 minutes)
    private const CACHE_TTL = 300;

    // Statuses that exclude a reseller from limit counting
    private const RESELLER_EXCLUDED_STATUSES = ['deleted', 'deactivated'];

    // Subscription statuses considered fully active (no payment gate)
    private const ACTIVE_SUBSCRIPTION_STATUSES = ['active', 'comped', 'internal', 'manual', 'trial'];

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Get active subscription plan details for a tenant.
     * Returns an array with plan name, status, plan_key, limits and features.
     * Returns an empty array if no subscription is found.
     */
    public function getActivePlan(string $tenantId): array
    {
        return $this->getCachedPlan($tenantId) ?? [];
    }

    /**
     * Get plan_limits_json as an array for a tenant.
     * Returns empty array if no subscription/plan found.
     */
    public function getPlanLimits(string $tenantId): array
    {
        $plan = $this->getCachedPlan($tenantId);
        return $plan['limits'] ?? [];
    }

    /**
     * Get plan_features_json as an array for a tenant.
     * Returns empty array if no subscription/plan found.
     */
    public function getPlanFeatures(string $tenantId): array
    {
        $plan = $this->getCachedPlan($tenantId);
        return $plan['features'] ?? [];
    }

    /**
     * Check if the tenant is on the Max plan (plan_key = 'max' or name = 'Max').
     */
    public function isMaxPlan(string $tenantId): bool
    {
        $plan = $this->getCachedPlan($tenantId);
        if (! $plan) return false;

        $key  = strtolower($plan['plan_key'] ?? '');
        $name = strtolower($plan['plan_name'] ?? '');

        return $key === 'max' || $name === 'max';
    }

    /**
     * Check if the tenant's subscription is considered active.
     * Covers: active, comped, internal, manual, trial.
     */
    public function isSubscriptionActive(string $tenantId): bool
    {
        $plan = $this->getCachedPlan($tenantId);
        if (! $plan) return false;

        return in_array($plan['subscription_status'] ?? '', self::ACTIVE_SUBSCRIPTION_STATUSES);
    }

    /**
     * Count current resellers for a tenant (excludes deleted/deactivated).
     */
    public function countReferrers(string $tenantId): int
    {
        return Reseller::where('tenant_id', $tenantId)
            ->whereNotIn('status', self::RESELLER_EXCLUDED_STATUSES)
            ->count();
    }

    /**
     * Count active tenant users (all TenantMembership rows with status='active').
     * This includes the owner + all active members.
     */
    public function countTenantUsers(string $tenantId): int
    {
        return TenantMembership::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();
    }

    /**
     * Check whether the tenant can invite another referrer.
     *
     * Returns:
     *   ['allowed' => bool, 'current' => int, 'max' => int|null, 'reason' => string]
     */
    public function canInviteReferrer(string $tenantId): array
    {
        $limits       = $this->getPlanLimits($tenantId);
        $maxResellers = isset($limits['max_resellers']) ? (int) $limits['max_resellers'] : null;

        $current = $this->countReferrers($tenantId);

        // Unlimited
        if ($maxResellers === null || $this->isUnlimited($maxResellers)) {
            return [
                'allowed' => true,
                'current' => $current,
                'max'     => -1,
                'reason'  => 'Unlimited referrers on this plan.',
            ];
        }

        if ($current >= $maxResellers) {
            return [
                'allowed' => false,
                'current' => $current,
                'max'     => $maxResellers,
                'reason'  => "Referrer limit reached. Your plan allows {$maxResellers} referrer(s). "
                    . "You currently have {$current}. Upgrade your plan to invite more.",
            ];
        }

        $remaining = $maxResellers - $current;

        return [
            'allowed' => true,
            'current' => $current,
            'max'     => $maxResellers,
            'reason'  => "{$remaining} referrer slot(s) remaining on your plan.",
        ];
    }

    /**
     * Check whether the tenant can invite another tenant manager/user.
     *
     * Returns:
     *   ['allowed' => bool, 'current' => int, 'max' => int|null, 'reason' => string]
     *
     * For Basic plan (max_users=2): count includes owner + all active members.
     * Blocks new invites if current TenantMembership count >= max_users.
     */
    public function canInviteTenantUser(string $tenantId): array
    {
        $limits   = $this->getPlanLimits($tenantId);
        $maxUsers = isset($limits['max_users']) ? (int) $limits['max_users'] : null;

        $current = $this->countTenantUsers($tenantId);

        // Unlimited
        if ($maxUsers === null || $this->isUnlimited($maxUsers)) {
            return [
                'allowed' => true,
                'current' => $current,
                'max'     => -1,
                'reason'  => 'Unlimited team users on this plan.',
            ];
        }

        // Block if at or over limit (covers Basic plan max_users=2)
        if ($current >= $maxUsers) {
            return [
                'allowed' => false,
                'current' => $current,
                'max'     => $maxUsers,
                'reason'  => "Team user limit reached. Your plan allows {$maxUsers} user(s) (including owner). "
                    . "You currently have {$current} active. Upgrade your plan to invite more.",
            ];
        }

        $remaining = $maxUsers - $current;

        return [
            'allowed' => true,
            'current' => $current,
            'max'     => $maxUsers,
            'reason'  => "{$remaining} team user slot(s) remaining on your plan.",
        ];
    }

    /**
     * Check if a feature is enabled on the tenant's current plan.
     * Feature keys match plan_features_json keys (e.g. 'messaging', 'api_access').
     * Returns true if the feature value is truthy (true, "limited", "full", etc.)
     * Returns false if the value is false, "false", 0, "0", or not present.
     */
    public function hasFeature(string $tenantId, string $featureKey): bool
    {
        $features = $this->getPlanFeatures($tenantId);

        if (! array_key_exists($featureKey, $features)) {
            return false;
        }

        $value = $features[$featureKey];

        // Explicit falsy values
        if ($value === false || $value === 'false' || $value === 0 || $value === '0') {
            return false;
        }

        return (bool) $value;
    }

    /**
     * Get a full usage summary for a tenant (current counts vs plan limits).
     */
    public function getUsageSummary(string $tenantId): array
    {
        $limits   = $this->getPlanLimits($tenantId);
        $planInfo = $this->getCachedPlan($tenantId);

        $currentResellers = $this->countReferrers($tenantId);
        $currentUsers     = $this->countTenantUsers($tenantId);

        $maxResellers = isset($limits['max_resellers'])           ? (int) $limits['max_resellers']           : -1;
        $maxUsers     = isset($limits['max_users'])               ? (int) $limits['max_users']               : -1;
        $maxLeads     = isset($limits['max_leads_per_month'])     ? (int) $limits['max_leads_per_month']     : -1;
        $maxMessages  = isset($limits['max_messages_per_month'])  ? (int) $limits['max_messages_per_month']  : -1;
        $maxStorage   = isset($limits['max_storage_mb'])          ? (int) $limits['max_storage_mb']          : -1;

        return [
            'plan_name'           => $planInfo['plan_name'] ?? null,
            'plan_key'            => $planInfo['plan_key']  ?? null,
            'subscription_status' => $planInfo['subscription_status'] ?? null,

            'resellers' => [
                'current'   => $currentResellers,
                'max'       => $maxResellers,
                'unlimited' => $this->isUnlimited($maxResellers),
                'at_limit'  => ! $this->isUnlimited($maxResellers) && $currentResellers >= $maxResellers,
            ],

            'users' => [
                'current'   => $currentUsers,
                'max'       => $maxUsers,
                'unlimited' => $this->isUnlimited($maxUsers),
                'at_limit'  => ! $this->isUnlimited($maxUsers) && $currentUsers >= $maxUsers,
            ],

            'leads_per_month' => [
                'max'       => $maxLeads,
                'unlimited' => $this->isUnlimited($maxLeads),
            ],

            'messages_per_month' => [
                'max'       => $maxMessages,
                'unlimited' => $this->isUnlimited($maxMessages),
            ],

            'storage_mb' => [
                'max'       => $maxStorage,
                'unlimited' => $this->isUnlimited($maxStorage),
            ],

            'grace_period_days' => $limits['grace_period_days'] ?? 7,
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Return true when a limit of -1 means unlimited.
     */
    private function isUnlimited(int $limit): bool
    {
        return $limit === -1;
    }

    /**
     * Fetch and cache plan data for a tenant for CACHE_TTL seconds.
     * Returns null if no subscription or plan is found.
     *
     * Cached array shape:
     *   plan_name, plan_key, subscription_status, billing_status,
     *   limits (array), features (array), subscription_id, plan_id
     */
    private function getCachedPlan(string $tenantId): ?array
    {
        $cacheKey = "tenant_{$tenantId}_plan";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($tenantId) {
            $subscription = Subscription::where('tenant_id', $tenantId)
                ->with('plan')
                ->first();

            if (! $subscription) {
                return null;
            }

            $plan = $subscription->plan;

            if (! $plan) {
                return null;
            }

            $limits   = is_array($plan->plan_limits_json)   ? $plan->plan_limits_json   : [];
            $features = is_array($plan->plan_features_json) ? $plan->plan_features_json : [];

            return [
                'subscription_id'     => $subscription->id,
                'plan_id'             => $plan->id,
                'plan_name'           => $plan->name,
                'plan_key'            => $plan->plan_key ?? null,
                'subscription_status' => $subscription->status,
                'billing_status'      => $subscription->billing_status ?? 'standard',
                'limits'              => $limits,
                'features'            => $features,
            ];
        });
    }
}
