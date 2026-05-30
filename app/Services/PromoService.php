<?php

namespace App\Services;

use App\Models\BillingAuditLog;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\PromoCode;
use App\Models\PromoRedemption;
use App\Models\PromoRedemptionAttempt;
use App\Models\Promotion;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PromoService
{
    public function __construct(
        private ApprovalService     $approvals,
        private NotificationService $notifications
    ) {}

    // ── Promo Code Creation ────────────────────────────────────

    public function createPromoCode(array $data, int $createdBy): array
    {
        $discountValue = (float) ($data['discount_value'] ?? 0);
        $maxRedemptions = $data['max_redemptions'] ?? null;
        $targetScope = $data['eligibility_rules_json']['target_scope'] ?? 'all';

        // Approval required checks
        $approvalType = null;
        if ($data['discount_type'] === 'percentage' && $discountValue > 30) {
            $approvalType = 'promo_high_discount';
        } elseif ($maxRedemptions === null) {
            $approvalType = 'promo_unlimited';
        } elseif ($targetScope === 'all') {
            $approvalType = 'promo_all_tenants';
        }

        $status = $approvalType ? 'pending_approval' : 'active';

        $code = PromoCode::create([
            'code'                      => strtoupper($data['code'] ?? Str::upper(Str::random(8))),
            'name'                      => $data['name'],
            'description'               => $data['description'] ?? null,
            'discount_type'             => $data['discount_type'],
            'discount_value'            => $discountValue,
            'currency'                  => $data['currency'] ?? 'PHP',
            'max_redemptions'           => $maxRedemptions,
            'valid_from'                => $data['valid_from'] ?? now(),
            'valid_until'               => $data['valid_until'] ?? null,
            'applies_to_plan_ids_json'  => $data['applies_to_plan_ids_json'] ?? [],
            'applies_to_billing_cycle'  => $data['applies_to_billing_cycle'] ?? 'both',
            'eligibility_rules_json'    => $data['eligibility_rules_json'] ?? [],
            'allow_stacking'            => $data['allow_stacking'] ?? false,
            'status'                    => $status,
            'created_by'                => $createdBy,
        ]);

        BillingAuditLog::log('promo_code_created', [
            'entity_type'  => 'promo_code',
            'entity_id'    => $code->id,
            'performed_by' => $createdBy,
            'after'        => ['code' => $code->code, 'discount' => "{$discountValue} {$data['discount_type']}"],
        ]);

        if ($approvalType) {
            $approval = $this->approvals->request(
                type:          $approvalType,
                referenceId:   $code->id,
                referenceType: 'promo_code',
                requestedBy:   $createdBy,
                requestData:   ['code' => $code->code, 'discount_type' => $data['discount_type'], 'discount_value' => $discountValue],
            );
            return ['promo_code' => $code, 'status' => 'pending_approval', 'approval_id' => $approval->id];
        }

        $this->notifications->send(
            category:  'billing',
            type:      'info',
            priority:  'low',
            message:   "Promo code '{$code->code}' created.",
            actionUrl: "/platform/billing/promo-codes/{$code->id}",
            channel:   'in_app',
            metadata:  ['promo_code_id' => $code->id],
        );

        return ['promo_code' => $code, 'status' => 'active'];
    }

    // ── Validation ────────────────────────────────────────────

    public function validate(string $code, string $tenantId, ?string $planId, string $billingCycle): array
    {
        $promo = PromoCode::where('code', strtoupper($code))->first();

        if (!$promo) {
            return $this->failedValidation(null, $code, $tenantId, 'code_not_found', 'Promo code not found.', compact('planId', 'billingCycle'));
        }

        if (!$promo->isActive()) {
            return $this->failedValidation($promo, $code, $tenantId, 'code_inactive', 'Promo code is not active.', compact('planId', 'billingCycle'));
        }

        if ($promo->valid_until && $promo->valid_until->isPast()) {
            return $this->failedValidation($promo, $code, $tenantId, 'code_expired', 'Promo code has expired.', compact('planId', 'billingCycle'));
        }

        if ($promo->max_redemptions !== null && $promo->redemptions_count >= $promo->max_redemptions) {
            return $this->failedValidation($promo, $code, $tenantId, 'redemption_limit_reached', 'Promo code has reached its redemption limit.', compact('planId', 'billingCycle'));
        }

        // Billing cycle match
        if ($promo->applies_to_billing_cycle !== 'both' && $promo->applies_to_billing_cycle !== $billingCycle) {
            return $this->failedValidation($promo, $code, $tenantId, 'billing_cycle_mismatch', "This code only applies to {$promo->applies_to_billing_cycle} billing.", compact('planId', 'billingCycle'));
        }

        // Plan match
        $applicablePlanIds = $promo->applies_to_plan_ids_json ?? [];
        if (!empty($applicablePlanIds) && $planId && !in_array($planId, $applicablePlanIds)) {
            return $this->failedValidation($promo, $code, $tenantId, 'plan_not_eligible', 'This code does not apply to the selected plan.', compact('planId', 'billingCycle'));
        }

        // Eligibility rules
        $rules = $promo->eligibility_rules_json ?? [];
        $tenant = Tenant::find($tenantId);

        // New tenants only
        if (!empty($rules['new_tenants_only'])) {
            $invoiceCount = \App\Models\Invoice::where('tenant_id', $tenantId)->where('status', 'paid')->count();
            if ($invoiceCount > 0) {
                return $this->failedValidation($promo, $code, $tenantId, 'new_tenants_only', 'This code is for new tenants only.', compact('planId', 'billingCycle'));
            }
        }

        // Existing tenants only
        if (!empty($rules['existing_tenants_only'])) {
            $invoiceCount = \App\Models\Invoice::where('tenant_id', $tenantId)->where('status', 'paid')->count();
            if ($invoiceCount === 0) {
                return $this->failedValidation($promo, $code, $tenantId, 'existing_tenants_only', 'This code is for existing tenants only.', compact('planId', 'billingCycle'));
            }
        }

        // Max per tenant
        $maxPerTenant = $rules['max_per_tenant'] ?? 1;
        $tenantRedemptions = PromoRedemption::where('promo_code_id', $promo->id)->where('tenant_id', $tenantId)->count();
        if ($tenantRedemptions >= $maxPerTenant) {
            return $this->failedValidation($promo, $code, $tenantId, 'tenant_limit_reached', 'You have already used this promo code the maximum number of times.', compact('planId', 'billingCycle'));
        }

        // Industry restriction
        if (!empty($rules['target_industries']) && $tenant) {
            if (!in_array($tenant->industry, $rules['target_industries'])) {
                return $this->failedValidation($promo, $code, $tenantId, 'industry_not_eligible', 'This code is not available for your industry.', compact('planId', 'billingCycle'));
            }
        }

        // Specific tenant restriction
        if (!empty($rules['target_tenant_ids'])) {
            if (!in_array($tenantId, $rules['target_tenant_ids'])) {
                return $this->failedValidation($promo, $code, $tenantId, 'tenant_not_eligible', 'This code is not available for your account.', compact('planId', 'billingCycle'));
            }
        }

        return ['valid' => true, 'promo_code' => $promo];
    }

    // ── Application ───────────────────────────────────────────

    public function apply(PromoCode $promo, Subscription $subscription, Invoice $invoice, int $redeemedBy): PromoRedemption
    {
        $tenant   = $subscription->tenant;
        $currency = $tenant->preferred_currency ?? 'PHP';
        $rate     = 1.0;

        if ($currency !== 'PHP') {
            $rate = (float) ExchangeRate::where('base_currency', 'PHP')
                ->where('target_currency', $currency)
                ->value('rate') ?? 1.0;
        }

        $discountPhp = $this->calculateDiscount($promo, (float) $invoice->base_amount_php);

        DB::transaction(function () use ($promo, $subscription, $invoice, $discountPhp, $currency, $rate, $redeemedBy, $tenant) {
            // Re-check redemption limit inside the transaction with a row lock
            $locked = PromoCode::where('id', $promo->id)->lockForUpdate()->first();
            if ($locked && $locked->max_redemptions !== null && $locked->redemptions_count >= $locked->max_redemptions) {
                throw new \RuntimeException('Promo code redemption limit reached.');
            }

            // Update invoice
            $newFinal = max(0, (float) $invoice->final_amount - $discountPhp);
            $lineItems = $invoice->line_items_json ?? [];
            $lineItems[] = [
                'type'            => 'discount',
                'description'     => "Promo: {$promo->code} — {$promo->name}",
                'discount_amount' => $discountPhp,
                'currency'        => 'PHP',
            ];

            $invoice->update([
                'discount_amount' => (float) $invoice->discount_amount + $discountPhp,
                'final_amount'    => $newFinal,
                'line_items_json' => $lineItems,
            ]);

            // Record redemption
            $redemption = PromoRedemption::create([
                'promo_code_id'     => $promo->id,
                'tenant_id'         => $tenant->id,
                'subscription_id'   => $subscription->id,
                'invoice_id'        => $invoice->id,
                'discount_amount'   => $discountPhp,
                'currency'          => $currency,
                'exchange_rate_used'=> $rate,
                'redeemed_by'       => $redeemedBy,
                'redeemed_at'       => now(),
            ]);

            // Increment counter
            $promo->increment('redemptions_count');

            // Mark fully redeemed if limit hit (increment() already updated in-memory count)
            if ($promo->max_redemptions !== null && $promo->redemptions_count >= $promo->max_redemptions) {
                $promo->update(['status' => 'fully_redeemed']);
                $this->notifications->send(
                    category:  'billing',
                    type:      'info',
                    priority:  'medium',
                    message:   "Promo code '{$promo->code}' has reached its redemption limit.",
                    actionUrl: "/platform/billing/promo-codes/{$promo->id}",
                    channel:   'in_app',
                    metadata:  ['promo_code_id' => $promo->id],
                );
            }

            BillingAuditLog::log('promo_redeemed', [
                'tenant_id'    => $tenant->id,
                'entity_type'  => 'promo_code',
                'entity_id'    => $promo->id,
                'performed_by' => $redeemedBy,
                'after'        => ['discount' => $discountPhp, 'invoice_id' => $invoice->id],
            ]);
        });

        // Notify for high-value discounts
        if ($discountPhp >= 1000) {
            $this->notifications->send(
                category:  'billing',
                type:      'warning',
                priority:  'high',
                message:   "High-value discount of ₱{$discountPhp} applied via promo '{$promo->code}'.",
                tenantId:  $tenant->id,
                actionUrl: "/platform/billing/invoices/{$invoice->id}",
                channel:   'in_app',
                metadata:  ['promo_code_id' => $promo->id, 'discount' => $discountPhp],
            );
        }

        return PromoRedemption::where('promo_code_id', $promo->id)
            ->where('invoice_id', $invoice->id)
            ->latest()
            ->first();
    }

    public function calculateDiscount(PromoCode $promo, float $baseAmount): float
    {
        return match ($promo->discount_type) {
            'percentage'    => round($baseAmount * (min(100.0, (float) $promo->discount_value) / 100), 2),
            'fixed_amount'  => min((float) $promo->discount_value, $baseAmount),
            'free_months'   => $baseAmount, // full invoice discount; caller handles multi-month
            'trial_extension' => 0, // handled separately via subscription trial_end_date extension
            default         => 0,
        };
    }

    // ── Promotions ────────────────────────────────────────────

    public function createPromotion(array $data, int $createdBy): array
    {
        $discountValue = (float) ($data['discount_value'] ?? 0);
        $targetScope   = $data['target_scope'] ?? 'specific_tenants';

        $needsApproval = ($data['discount_type'] === 'percentage' && $discountValue > 30)
            || $targetScope === 'all';

        $status = $needsApproval ? 'pending_approval' : ($data['status'] ?? 'draft');

        $promotion = Promotion::create([
            'name'                       => $data['name'],
            'description'                => $data['description'] ?? null,
            'promotion_type'             => $data['promotion_type'] ?? 'campaign',
            'discount_type'              => $data['discount_type'],
            'discount_value'             => $discountValue,
            'currency'                   => $data['currency'] ?? 'PHP',
            'target_scope'               => $targetScope,
            'target_ids_json'            => $data['target_ids_json'] ?? [],
            'applies_to_plan_ids_json'   => $data['applies_to_plan_ids_json'] ?? [],
            'applies_to_billing_cycles'  => $data['applies_to_billing_cycles'] ?? ['monthly', 'yearly'],
            'valid_from'                 => $data['valid_from'] ?? now(),
            'valid_until'                => $data['valid_until'] ?? null,
            'auto_apply'                 => $data['auto_apply'] ?? false,
            'allow_stacking'             => $data['allow_stacking'] ?? false,
            'max_discounts_per_invoice'  => $data['max_discounts_per_invoice'] ?? 1,
            'affects_duration'           => $data['affects_duration'] ?? 'first_invoice',
            'duration_months'            => $data['duration_months'] ?? null,
            'promo_code_id'              => $data['promo_code_id'] ?? null,
            'status'                     => $status,
            'created_by'                 => $createdBy,
        ]);

        BillingAuditLog::log('promotion_created', [
            'entity_type'  => 'promotion',
            'entity_id'    => $promotion->id,
            'performed_by' => $createdBy,
            'after'        => ['name' => $promotion->name, 'status' => $status],
        ]);

        if ($needsApproval) {
            $approval = $this->approvals->request(
                type:          $discountValue > 30 ? 'promo_high_discount' : 'promo_all_tenants',
                referenceId:   $promotion->id,
                referenceType: 'promotion',
                requestedBy:   $createdBy,
                requestData:   $data,
            );
            return ['promotion' => $promotion, 'status' => 'pending_approval', 'approval_id' => $approval->id];
        }

        return ['promotion' => $promotion, 'status' => $status];
    }

    public function autoApplyPromotions(string $tenantId, ?string $planId, string $billingCycle): array
    {
        $tenant = Tenant::find($tenantId);
        if (!$tenant) return [];

        return Promotion::where('status', 'active')
            ->where('auto_apply', true)
            ->where('valid_from', '<=', now())
            ->where(fn($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>', now()))
            ->get()
            ->filter(function (Promotion $promo) use ($tenant, $planId, $billingCycle) {
                // Plan match
                if (!empty($promo->applies_to_plan_ids_json) && $planId) {
                    if (!in_array($planId, $promo->applies_to_plan_ids_json)) return false;
                }
                // Billing cycle match
                if (!in_array($billingCycle, $promo->applies_to_billing_cycles ?? [])) return false;
                // Target scope
                return $this->promotionMatchesTenant($promo, $tenant);
            })
            ->values()
            ->toArray();
    }

    // ── Performance Stats ─────────────────────────────────────

    public function getPerformance(): array
    {
        $activeCodes = PromoCode::where('status', 'active')->count();
        $totalRedemptions = PromoRedemption::count();
        $totalDiscount = PromoRedemption::sum('discount_amount');

        $topCode = PromoCode::withCount('redemptions')
            ->orderByDesc('redemptions_count')
            ->first();

        $byPromo = PromoRedemption::selectRaw('promo_code_id, count(*) as redemptions, sum(discount_amount) as total_discount')
            ->groupBy('promo_code_id')
            ->with('promoCode')
            ->orderByDesc('total_discount')
            ->limit(10)
            ->get();

        $failedAttempts = PromoRedemptionAttempt::selectRaw('failure_reason, count(*) as count')
            ->groupBy('failure_reason')
            ->orderByDesc('count')
            ->get();

        $expiringSoon = PromoCode::where('status', 'active')
            ->whereNotNull('valid_until')
            ->where('valid_until', '<=', now()->addDays(7))
            ->get();

        return compact('activeCodes', 'totalRedemptions', 'totalDiscount', 'topCode', 'byPromo', 'failedAttempts', 'expiringSoon');
    }

    // ── Private helpers ───────────────────────────────────────

    private function failedValidation(?PromoCode $promo, string $code, string $tenantId, string $reason, string $message, array $context): array
    {
        PromoRedemptionAttempt::create([
            'promo_code_id'  => $promo?->id,
            'code_attempted' => strtoupper($code),
            'tenant_id'      => $tenantId,
            'failure_reason' => $reason,
            'attempt_data'   => $context,
            'attempted_at'   => now(),
        ]);

        return ['valid' => false, 'error_code' => $reason, 'message' => $message];
    }

    private function promotionMatchesTenant(Promotion $promo, Tenant $tenant): bool
    {
        return match ($promo->target_scope) {
            'all'                => true,
            'new_tenants'        => \App\Models\Invoice::where('tenant_id', $tenant->id)->where('status', 'paid')->count() === 0,
            'existing_tenants'   => \App\Models\Invoice::where('tenant_id', $tenant->id)->where('status', 'paid')->count() > 0,
            'specific_tenants'   => in_array($tenant->id, $promo->target_ids_json ?? []),
            'industry'           => in_array($tenant->industry, $promo->target_ids_json ?? []),
            'sub_industry'       => false, // extend when sub-industry model is ready
            default              => false,
        };
    }
}
