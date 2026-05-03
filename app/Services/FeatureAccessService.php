<?php

namespace App\Services;

use App\Models\FeatureUsage;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\TenantOverride;
use App\Models\UsageMetric;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FeatureAccessService
{
    // Limits that apply during limited_access mode (trial expired, no payment)
    private const LIMITED_ACCESS_ALLOWED = ['view_data', 'add_payment_method'];

    // Resource → usage column map
    private const RESOURCE_COLUMN = [
        'leads'     => 'leads_this_month',
        'messages'  => 'messages_this_month',
        'resellers' => 'resellers_count',
        'users'     => 'users_count',
    ];

    // Resource → plan limit key map
    private const RESOURCE_LIMIT_KEY = [
        'leads'     => 'max_leads_per_month',
        'messages'  => 'max_messages_per_month',
        'resellers' => 'max_resellers',
        'users'     => 'max_users',
        'storage'   => 'max_storage_mb',
    ];

    // Plan hierarchy for upgrade suggestions
    private const PLAN_ORDER = ['Free', 'Starter', 'Pro', 'Enterprise'];

    // ── Public API ────────────────────────────────────────────────

    public function checkFeature(string $tenantId, string $feature): array
    {
        $subscription = $this->getSubscription($tenantId);

        if (!$subscription) {
            return $this->deny('no_subscription', 'No active subscription found.', $tenantId, $feature);
        }

        // Limited access mode: trial expired, no payment
        if ($subscription->status === 'limited_access') {
            if (!in_array($feature, self::LIMITED_ACCESS_ALLOWED)) {
                return $this->deny('limited_access', 'Your trial has expired. Please add a payment method to continue.', $tenantId, $feature);
            }
            return $this->allow();
        }

        // Suspended or canceled past grace period
        if (in_array($subscription->status, ['suspended', 'canceled'])) {
            if (!$this->isInGracePeriod($tenantId, $subscription)) {
                return $this->deny('suspended', 'Account is suspended. Please contact support.', $tenantId, $feature);
            }
        }

        // Check tenant override first
        $override = $this->getActiveOverride($tenantId, $feature);
        if ($override !== null) {
            $val = $override->override_value;
            if (in_array($val, ['false', '0'])) {
                return $this->deny('feature_override_disabled', "Feature '{$feature}' is disabled by admin override.", $tenantId, $feature);
            }
            return $this->allow(['override' => true, 'override_value' => $val]);
        }

        // Trial tenants get Pro-level access
        if ($subscription->status === 'trial') {
            return $this->checkFeatureAgainstPlanName('Pro', $feature, $tenantId);
        }

        $plan = $subscription->plan;
        if (!$plan) {
            return $this->deny('no_plan', 'No plan associated with subscription.', $tenantId, $feature);
        }

        return $this->checkFeatureAgainstPlanName($plan->name, $feature, $tenantId);
    }

    public function checkLimit(string $tenantId, string $resource): array
    {
        $subscription = $this->getSubscription($tenantId);

        if (!$subscription) {
            return $this->denyLimit('no_subscription', $tenantId, $resource, 0, 0);
        }

        if ($subscription->status === 'limited_access') {
            return $this->denyLimit('limited_access', $tenantId, $resource, 0, 0);
        }

        if (in_array($subscription->status, ['suspended', 'canceled'])) {
            if (!$this->isInGracePeriod($tenantId, $subscription)) {
                return $this->denyLimit('suspended', $tenantId, $resource, 0, 0);
            }
        }

        $limitKey = self::RESOURCE_LIMIT_KEY[$resource] ?? null;
        if (!$limitKey) {
            return $this->allow();
        }

        // Check tenant override for this limit
        $override = $this->getActiveOverride($tenantId, $limitKey);
        $limits = $this->getPlanLimits($tenantId, $subscription);
        $max = $override ? (int) $override->override_value : ($limits[$limitKey] ?? 0);

        // -1 = unlimited
        if ($max === -1) {
            return $this->allow(['max' => -1, 'unlimited' => true]);
        }

        $usage = $this->getCurrentUsage($tenantId, $resource);

        if ($usage >= $max) {
            $percent = 100;
            return $this->denyLimit('limit_reached', $tenantId, $resource, $usage, $max);
        }

        $percent = $max > 0 ? round(($usage / $max) * 100) : 0;

        return array_merge($this->allow(), [
            'current'      => $usage,
            'max'          => $max,
            'percent_used' => $percent,
            'near_limit'   => $percent >= 80,
        ]);
    }

    public function trackResourceUsage(string $tenantId, string $resource, int $delta = 1): void
    {
        $column = self::RESOURCE_COLUMN[$resource] ?? null;
        if (!$column) return;

        $metric = $this->getOrCreateUsageMetric($tenantId);
        $metric->increment($column, $delta);
        $metric->touch();
    }

    public function trackFeatureUsage(string $tenantId, string $feature): void
    {
        $periodStart = now()->startOfMonth()->toDateString();

        FeatureUsage::upsert(
            [
                'tenant_id'    => $tenantId,
                'feature_name' => $feature,
                'period_start' => $periodStart,
                'usage_count'  => 1,
                'last_used_at' => now(),
                'updated_at'   => now(),
                'created_at'   => now(),
            ],
            ['tenant_id', 'feature_name', 'period_start'],
            ['usage_count' => DB::raw('feature_usage.usage_count + 1'), 'last_used_at' => now(), 'updated_at' => now()]
        );
    }

    public function getUsageSummary(string $tenantId): array
    {
        $subscription = $this->getSubscription($tenantId);
        $metric = $this->getOrCreateUsageMetric($tenantId);
        $limits = $this->getPlanLimits($tenantId, $subscription);

        $resources = ['leads', 'messages', 'resellers', 'users'];
        $summary = [];

        foreach ($resources as $resource) {
            $limitKey = self::RESOURCE_LIMIT_KEY[$resource];
            $column   = self::RESOURCE_COLUMN[$resource];
            $max      = $limits[$limitKey] ?? 0;
            $current  = $metric->{$column} ?? 0;
            $override  = $this->getActiveOverride($tenantId, $limitKey);
            if ($override) $max = (int) $override->override_value;

            $summary[$resource] = [
                'current'      => $current,
                'max'          => $max,
                'unlimited'    => $max === -1,
                'percent_used' => ($max > 0) ? round(($current / $max) * 100) : 0,
            ];
        }

        $summary['storage'] = [
            'current_mb'  => (float) $metric->storage_mb_used,
            'max_mb'      => $limits['max_storage_mb'] ?? 500,
            'unlimited'   => ($limits['max_storage_mb'] ?? 0) === -1,
            'percent_used'=> ($limits['max_storage_mb'] ?? 0) > 0
                ? round(($metric->storage_mb_used / $limits['max_storage_mb']) * 100)
                : 0,
        ];

        return [
            'period_start' => $metric->billing_period_start,
            'period_end'   => $metric->billing_period_end,
            'resources'    => $summary,
            'grace_period' => [
                'active'    => $this->isInGracePeriod($tenantId, $subscription),
                'ends_at'   => $metric->grace_period_ends_at,
            ],
        ];
    }

    public function getUpgradePrompt(string $tenantId, string $blockedResource): array
    {
        $subscription = $this->getSubscription($tenantId);
        $currentPlan  = $subscription?->plan?->name ?? 'Free';
        $nextPlan     = $this->getNextPlan($currentPlan);

        return [
            'upgrade_prompt' => true,
            'current_plan'   => $currentPlan,
            'blocked_resource' => $blockedResource,
            'suggested_plan' => $nextPlan,
            'upgrade_url'    => '/platform/billing/upgrade',
            'message'        => "You've reached your {$blockedResource} limit on the {$currentPlan} plan. Upgrade to {$nextPlan} to continue.",
        ];
    }

    public function applyOverride(
        string $tenantId,
        string $featureName,
        string $value,
        int    $approvedBy,
        ?string $expiresAt = null,
        ?string $referenceId = null,
        ?string $notes = null
    ): TenantOverride {
        return TenantOverride::create([
            'tenant_id'             => $tenantId,
            'feature_name'          => $featureName,
            'override_value'        => $value,
            'expires_at'            => $expiresAt,
            'approved_by'           => $approvedBy,
            'approval_reference_id' => $referenceId,
            'notes'                 => $notes,
        ]);
    }

    public function startGracePeriod(string $tenantId, int $days = 7): void
    {
        $metric = $this->getOrCreateUsageMetric($tenantId);
        $metric->update(['grace_period_ends_at' => now()->addDays($days)]);
    }

    public function resetMonthlyUsage(string $tenantId): void
    {
        $periodStart = now()->startOfMonth()->toDateString();
        $periodEnd   = now()->endOfMonth()->toDateString();

        $metric = $this->getOrCreateUsageMetric($tenantId);
        $metric->update([
            'billing_period_start' => $periodStart,
            'billing_period_end'   => $periodEnd,
            'leads_this_month'     => 0,
            'messages_this_month'  => 0,
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────

    private function getSubscription(string $tenantId): ?Subscription
    {
        return Subscription::with('plan')
            ->where('tenant_id', $tenantId)
            ->orderByRaw("CASE status WHEN 'active' THEN 1 WHEN 'trial' THEN 2 WHEN 'past_due' THEN 3 WHEN 'limited_access' THEN 4 ELSE 5 END")
            ->first();
    }

    private function getPlanLimits(string $tenantId, ?Subscription $subscription): array
    {
        if (!$subscription || !$subscription->plan) {
            return [];
        }

        // Trial gets Pro limits
        if ($subscription->status === 'trial') {
            $proPlan = Plan::where('name', 'Pro')->first();
            return $proPlan ? ($proPlan->plan_limits_json ?? []) : [];
        }

        return $subscription->plan->plan_limits_json ?? [];
    }

    private function getPlanFeatures(string $planName): array
    {
        $plan = Plan::where('name', $planName)->first();
        return $plan ? ($plan->plan_features_json ?? []) : [];
    }

    private function checkFeatureAgainstPlanName(string $planName, string $feature, string $tenantId): array
    {
        $features = $this->getPlanFeatures($planName);
        $value = $features[$feature] ?? null;

        if ($value === null) {
            return $this->allow(); // unknown feature = allowed
        }

        if ($value === false || $value === 'false') {
            return $this->deny(
                'feature_not_available',
                "The '{$feature}' feature is not available on your current plan.",
                $tenantId,
                $feature
            );
        }

        return $this->allow(['feature_level' => $value]);
    }

    private function getActiveOverride(string $tenantId, string $featureName): ?TenantOverride
    {
        return TenantOverride::where('tenant_id', $tenantId)
            ->where('feature_name', $featureName)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->first();
    }

    private function getOrCreateUsageMetric(string $tenantId): UsageMetric
    {
        $periodStart = now()->startOfMonth()->toDateString();
        $periodEnd   = now()->endOfMonth()->toDateString();

        return UsageMetric::firstOrCreate(
            ['tenant_id' => $tenantId, 'billing_period_start' => $periodStart],
            ['billing_period_end' => $periodEnd]
        );
    }

    private function getCurrentUsage(string $tenantId, string $resource): int
    {
        $column = self::RESOURCE_COLUMN[$resource] ?? null;
        if (!$column) return 0;

        $metric = $this->getOrCreateUsageMetric($tenantId);
        return (int) ($metric->{$column} ?? 0);
    }

    private function isInGracePeriod(string $tenantId, ?Subscription $subscription): bool
    {
        if (!$subscription) return false;
        if (!in_array($subscription->status, ['suspended', 'canceled', 'past_due'])) return false;

        $metric = UsageMetric::where('tenant_id', $tenantId)
            ->where('billing_period_start', now()->startOfMonth()->toDateString())
            ->first();

        if ($metric?->grace_period_ends_at && $metric->grace_period_ends_at->isFuture()) {
            return true;
        }

        return false;
    }

    private function getNextPlan(string $currentPlan): string
    {
        $index = array_search($currentPlan, self::PLAN_ORDER);
        if ($index === false || $index >= count(self::PLAN_ORDER) - 1) {
            return 'Enterprise';
        }
        return self::PLAN_ORDER[$index + 1];
    }

    private function allow(array $extra = []): array
    {
        return array_merge(['allowed' => true], $extra);
    }

    private function deny(string $errorCode, string $message, string $tenantId, string $resource): array
    {
        return array_merge(
            ['allowed' => false, 'error_code' => $errorCode, 'message' => $message],
            $this->getUpgradePrompt($tenantId, $resource)
        );
    }

    private function denyLimit(string $errorCode, string $tenantId, string $resource, int $current, int $max): array
    {
        return array_merge(
            ['allowed' => false, 'error_code' => $errorCode, 'current' => $current, 'max' => $max],
            ['message' => "You've reached your {$resource} limit."],
            $this->getUpgradePrompt($tenantId, $resource)
        );
    }
}
