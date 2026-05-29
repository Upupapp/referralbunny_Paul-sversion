<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use App\Models\PaymentMethod;
use App\Models\TenantMetric;
use App\Models\BillingAuditLog;
use App\Models\Credit;
use App\Models\Refund;
use App\Models\Payment;
use Carbon\Carbon;

class BillingService
{
    public function __construct(
        private InvoiceService      $invoiceService,
        private NotificationService $notifications,
    ) {}

    // ── Trial ─────────────────────────────────────────────────

    public function startTrial(Tenant $tenant, ?string $planId = null): Subscription
    {
        $plan = $planId ? Plan::find($planId) : Plan::where('name', 'Starter')->first();

        $subscription = Subscription::create([
            'tenant_id'      => $tenant->id,
            'plan_id'        => $plan?->id,
            'status'         => 'trial',
            'billing_cycle'  => 'monthly',
            'start_date'     => today(),
            'trial_end_date' => today()->addDays(15),
        ]);

        $this->syncMetrics($tenant, $subscription);

        $this->notifications->send(
            category:  'billing',
            type:      'info',
            priority:  'low',
            message:   "Trial started. Expires in 15 days.",
            tenantId:  $tenant->id,
            channel:   'in_app',
        );

        BillingAuditLog::log('trial_started', [
            'tenant_id'   => $tenant->id,
            'entity_type' => 'subscription',
            'entity_id'   => $subscription->id,
        ]);

        return $subscription;
    }

    // ── Activate Subscription ─────────────────────────────────

    public function activate(Subscription $subscription, string $billingCycle = 'monthly'): void
    {
        $old = $subscription->status;

        $plan   = $subscription->plan;
        $price  = $billingCycle === 'yearly'
            ? (float) $plan->price_yearly
            : (float) $plan->price_monthly;

        $subscription->update([
            'status'            => 'active',
            'billing_cycle'     => $billingCycle,
            'next_billing_date' => today()->addMonth(),
        ]);

        $this->recordHistory($subscription, $old, 'active');
        $this->syncMetrics($subscription->tenant, $subscription);

        $this->notifications->send(
            category: 'billing',
            type:     'info',
            priority: 'low',
            message:  "Subscription activated on {$plan->name} plan.",
            tenantId: $subscription->tenant_id,
            channel:  'email',
        );
    }

    // ── Handle Trial Expiry ───────────────────────────────────

    public function handleTrialExpiry(Subscription $subscription): void
    {
        $tenant        = $subscription->tenant;
        $hasPaymentMethod = PaymentMethod::where('tenant_id', $tenant->id)->exists();

        if ($hasPaymentMethod) {
            // Auto-charge
            $invoice = $this->invoiceService->createForSubscription(
                $subscription,
                (float) ($subscription->plan?->price_monthly ?? 0),
            );
            $this->activate($subscription);
        } else {
            $this->moveTo($subscription, 'limited_access', 'Trial expired — no payment method');
            $this->notifications->send(
                category: 'billing',
                type:     'action_required',
                priority: 'high',
                message:  "Trial expired. Add a payment method to continue.",
                tenantId: $tenant->id,
                channel:  'all',
            );
        }

        $this->syncMetrics($tenant, $subscription);
    }

    // ── Suspend ───────────────────────────────────────────────

    public function suspend(Subscription $subscription, string $reason, ?string $userId): void
    {
        $this->moveTo($subscription, 'suspended', $reason, $userId);
        $this->notifications->send(
            category: 'billing',
            type:     'action_required',
            priority: 'critical',
            message:  "Account suspended: {$reason}",
            tenantId: $subscription->tenant_id,
            channel:  'all',
        );
    }

    // ── Cancel ────────────────────────────────────────────────

    public function cancel(Subscription $subscription, string $reason, ?string $userId): void
    {
        $subscription->update(['status' => 'canceled', 'canceled_at' => now()]);
        $this->recordHistory($subscription, $subscription->getOriginal('status'), 'canceled', $userId, $reason);
        $this->syncMetrics($subscription->tenant, $subscription);

        BillingAuditLog::log('subscription_canceled', [
            'tenant_id'    => $subscription->tenant_id,
            'entity_type'  => 'subscription',
            'entity_id'    => $subscription->id,
            'performed_by' => $userId,
            'reason'       => $reason,
        ]);
    }

    // ── Issue Credit ──────────────────────────────────────────

    public function issueCredit(string $tenantId, float $amount, string $reason, ?string $userId): Credit
    {
        $credit = Credit::create([
            'tenant_id' => $tenantId,
            'amount'    => $amount,
            'currency'  => 'PHP',
            'reason'    => $reason,
            'created_by'=> $userId,
        ]);

        $this->notifications->send(
            category: 'billing',
            type:     'info',
            priority: 'low',
            message:  "Credit of ₱" . number_format($amount, 2) . " applied: {$reason}",
            tenantId: $tenantId,
            channel:  'in_app',
        );

        BillingAuditLog::log('credit_applied', [
            'tenant_id'    => $tenantId,
            'entity_type'  => 'credit',
            'entity_id'    => $credit->id,
            'performed_by' => $userId,
            'reason'       => $reason,
            'after'        => ['amount' => $amount],
        ]);

        return $credit;
    }

    // ── Issue Refund ──────────────────────────────────────────

    public function requestRefund(Payment $payment, float $amount, string $reason, ?string $userId): Refund
    {
        $refund = Refund::create([
            'payment_id'  => $payment->id,
            'tenant_id'   => $payment->tenant_id,
            'amount'      => $amount,
            'currency'    => $payment->currency,
            'reason'      => $reason,
            'status'      => 'pending',
            'processed_by'=> $userId,
        ]);

        $this->notifications->send(
            category: 'billing',
            type:     'info',
            priority: 'medium',
            message:  "Refund of ₱" . number_format($amount, 2) . " requested.",
            tenantId: $payment->tenant_id,
            channel:  'in_app',
        );

        BillingAuditLog::log('refund_requested', [
            'tenant_id'    => $payment->tenant_id,
            'entity_type'  => 'refund',
            'entity_id'    => $refund->id,
            'performed_by' => $userId,
            'reason'       => $reason,
            'after'        => ['amount' => $amount],
        ]);

        return $refund;
    }

    // ── Billing Dashboard ─────────────────────────────────────

    public function dashboardSummary(): array
    {
        $subscriptions = Subscription::with('plan')->get();

        $active  = $subscriptions->where('status', 'active');
        $trial   = $subscriptions->where('status', 'trial');
        $pastDue = $subscriptions->where('status', 'past_due');

        $mrr = $active->sum(fn($s) => (float) ($s->plan?->price_monthly ?? 0));
        $arr = $mrr * 12;

        $payments = Payment::where('status', 'paid');
        $totalRevenue = $payments->sum('base_amount_php');

        $failedPayments = Payment::where('status', 'failed')
            ->whereDate('created_at', '>=', now()->subDays(30))->count();

        return [
            'paying_tenants'       => $active->count(),
            'trial_tenants'        => $trial->count(),
            'past_due_tenants'     => $pastDue->count(),
            'failed_payments_30d'  => $failedPayments,
            'mrr'                  => round($mrr, 2),
            'arr'                  => round($arr, 2),
            'arpu'                 => $active->count() > 0 ? round($mrr / $active->count(), 2) : 0,
            'total_revenue'        => round($totalRevenue, 2),
            'plan_distribution'    => $subscriptions->groupBy('plan.name')->map->count(),
        ];
    }

    // ── Helpers ───────────────────────────────────────────────

    private function moveTo(Subscription $subscription, string $status, string $reason = '', ?string $userId = null): void
    {
        $old = $subscription->status;
        $subscription->update(['status' => $status]);
        $this->recordHistory($subscription, $old, $status, $userId, $reason);
        $this->syncMetrics($subscription->tenant, $subscription);
    }

    private function recordHistory(Subscription $subscription, string $oldStatus, string $newStatus, ?string $userId = null, string $reason = ''): void
    {
        SubscriptionHistory::create([
            'tenant_id'   => $subscription->tenant_id,
            'old_plan_id' => $subscription->plan_id,
            'new_plan_id' => $subscription->plan_id,
            'old_status'  => $oldStatus,
            'new_status'  => $newStatus,
            'changed_by'  => is_numeric($userId) ? (int) $userId : null,
            'reason'      => $reason,
        ]);
    }

    private function syncMetrics(Tenant $tenant, Subscription $subscription): void
    {
        TenantMetric::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'subscription_status'  => $subscription->status,
                'trial_days_remaining' => $subscription->trialDaysRemaining(),
                'payment_status'       => match($subscription->status) {
                    'active'          => 'paid',
                    'past_due'        => 'overdue',
                    'suspended'       => 'failed',
                    default           => 'unknown',
                },
                'updated_at'           => now(),
            ]
        );
    }
}
