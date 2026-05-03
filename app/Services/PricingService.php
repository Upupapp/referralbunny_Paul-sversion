<?php

namespace App\Services;

use App\Models\BillingAuditLog;
use App\Models\Plan;
use App\Models\PricingHistory;
use App\Models\Subscription;

class PricingService
{
    public function __construct(
        private ApprovalService   $approvals,
        private NotificationService $notifications
    ) {}

    // update_rule: new_subscriptions_only | next_billing_cycle | immediate_proration | grandfather
    public function updatePlanPrice(Plan $plan, array $data, int $changedBy): array
    {
        $newMonthly = (float) ($data['price_monthly'] ?? $plan->price_monthly);
        $newYearly  = (float) ($data['price_yearly']  ?? $plan->price_yearly);
        $updateRule = $data['update_rule'] ?? 'new_subscriptions_only';
        $reason     = $data['change_reason'] ?? null;

        // Check if approval is needed
        $approvalTypes = $this->approvalTypesRequired($plan, $newMonthly, $newYearly, $updateRule);

        if (!empty($approvalTypes)) {
            // Stage the change — don't apply yet
            $approval = $this->approvals->request(
                type:          $approvalTypes[0],
                referenceId:   $plan->id,
                referenceType: 'plan',
                requestedBy:   $changedBy,
                requestData:   [
                    'old_price_monthly' => (float) $plan->price_monthly,
                    'new_price_monthly' => $newMonthly,
                    'old_price_yearly'  => (float) $plan->price_yearly,
                    'new_price_yearly'  => $newYearly,
                    'currency'          => $plan->currency,
                    'update_rule'       => $updateRule,
                    'change_reason'     => $reason,
                ],
                notes: "Requires approval: " . implode(', ', $approvalTypes),
            );

            return [
                'status'      => 'pending_approval',
                'approval_id' => $approval->id,
                'message'     => 'Price change staged — pending approval.',
            ];
        }

        // Apply immediately
        $before = ['price_monthly' => $plan->price_monthly, 'price_yearly' => $plan->price_yearly];

        $plan->update([
            'price_monthly' => $newMonthly,
            'price_yearly'  => $newYearly,
            'description'   => $data['description'] ?? $plan->description,
            'is_active'     => $data['is_active']   ?? $plan->is_active,
        ]);

        PricingHistory::create([
            'plan_id'           => $plan->id,
            'old_price_monthly' => $before['price_monthly'],
            'new_price_monthly' => $newMonthly,
            'old_price_yearly'  => $before['price_yearly'],
            'new_price_yearly'  => $newYearly,
            'currency'          => $plan->currency,
            'update_rule'       => $updateRule,
            'change_reason'     => $reason,
            'changed_by'        => $changedBy,
        ]);

        BillingAuditLog::log('plan_price_changed', [
            'entity_type'  => 'plan',
            'entity_id'    => $plan->id,
            'performed_by' => $changedBy,
            'reason'       => $reason,
            'before'       => $before,
            'after'        => ['price_monthly' => $newMonthly, 'price_yearly' => $newYearly],
        ]);

        $this->notifications->send(
            category:  'billing',
            type:      'info',
            priority:  'medium',
            message:   "Plan '{$plan->name}' price updated. Monthly: ₱{$newMonthly}, Yearly: ₱{$newYearly}.",
            actionUrl: '/platform/billing/plans',
            channel:   'in_app',
            metadata:  ['plan_id' => $plan->id, 'update_rule' => $updateRule],
        );

        // Schedule next-billing-cycle changes for existing subscriptions
        if ($updateRule === 'next_billing_cycle') {
            $this->scheduleExistingSubscriptionUpdate($plan, $newMonthly, $newYearly);
        }

        return ['status' => 'applied', 'plan' => $plan->fresh()];
    }

    public function updatePlanMeta(Plan $plan, array $data, int $changedBy): Plan
    {
        // Free plan modification requires approval
        if ($plan->name === 'Free' && isset($data['price_monthly'])) {
            $this->approvals->request(
                type:          'free_plan_modification',
                referenceId:   $plan->id,
                referenceType: 'plan',
                requestedBy:   $changedBy,
                requestData:   $data,
            );
            return $plan;
        }

        $plan->update(array_filter([
            'name'               => $data['name']               ?? null,
            'description'        => $data['description']        ?? null,
            'plan_limits_json'   => $data['plan_limits_json']   ?? null,
            'plan_features_json' => $data['plan_features_json'] ?? null,
            'is_active'          => $data['is_active']          ?? null,
            'billing_cycle_options' => $data['billing_cycle_options'] ?? null,
        ], fn($v) => $v !== null));

        BillingAuditLog::log('plan_meta_updated', [
            'entity_type'  => 'plan',
            'entity_id'    => $plan->id,
            'performed_by' => $changedBy,
            'after'        => $data,
        ]);

        return $plan->fresh();
    }

    public function getPricingHistory(?string $planId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = PricingHistory::with(['plan', 'changedBy'])->orderByDesc('created_at');
        if ($planId) $query->where('plan_id', $planId);
        return $query->limit(200)->get();
    }

    private function approvalTypesRequired(Plan $plan, float $newMonthly, float $newYearly, string $updateRule): array
    {
        $types = [];
        $oldMonthly = (float) $plan->price_monthly;

        // Price decrease > 20%
        if ($oldMonthly > 0 && $newMonthly < $oldMonthly && (($oldMonthly - $newMonthly) / $oldMonthly) > 0.20) {
            $types[] = 'price_decrease';
        }

        // Applying change to existing tenants
        if (in_array($updateRule, ['next_billing_cycle', 'immediate_proration'])) {
            $types[] = 'existing_tenant_price_change';
        }

        // Free plan modification
        if ($plan->name === 'Free' && ($newMonthly !== $oldMonthly)) {
            $types[] = 'free_plan_modification';
        }

        return $types;
    }

    private function scheduleExistingSubscriptionUpdate(Plan $plan, float $newMonthly, float $newYearly): void
    {
        // Tag active subscriptions for repricing on next billing cycle
        // In a real system this would queue a job; here we log the intent
        $count = Subscription::where('plan_id', $plan->id)->where('status', 'active')->count();
        if ($count > 0) {
            $this->notifications->send(
                category:  'billing',
                type:      'warning',
                priority:  'high',
                message:   "{$count} existing subscription(s) on '{$plan->name}' will be repriced on their next billing cycle.",
                actionUrl: '/platform/billing/plans',
                channel:   'in_app',
                metadata:  ['plan_id' => $plan->id, 'affected_subscriptions' => $count],
            );
        }
    }
}
