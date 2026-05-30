<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Credit;
use App\Models\BillingAuditLog;
use App\Services\BillingService;
use App\Services\InvoiceService;
use App\Services\PayMongoService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class BillingController extends Controller
{
    public function __construct(
        private BillingService  $billing,
        private InvoiceService  $invoiceService,
        private PayMongoService $paymongo,
    ) {}

    private function actorId(): ?string
    {
        return Auth::guard('web')->id() ?? Auth::guard('tenant')->id() ?? null;
    }

    // ── Dashboard ─────────────────────────────────────────────
    public function dashboard(): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Only super admins can access the billing dashboard.');
        return response()->json($this->billing->dashboardSummary());
    }

    // ── Plans ─────────────────────────────────────────────────
    public function plans(): JsonResponse
    {
        return response()->json(Plan::where('is_active', true)->get());
    }

    // ── Tenant Subscription ───────────────────────────────────
    public function tenantSubscription(string $tenantId): JsonResponse
    {
        if (!TenantContext::isSuperAdmin() && TenantContext::id() !== $tenantId) {
            abort(403, 'Access denied.');
        }
        $sub = Subscription::with('plan')
            ->where('tenant_id', $tenantId)
            ->latest()
            ->first();

        return response()->json($sub);
    }

    public function activateTrial(Request $request, string $tenantId): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Only super admins can perform billing operations.');
        $tenant = \App\Models\Tenant::findOrFail($tenantId);
        $sub    = $this->billing->startTrial($tenant, $request->plan_id);
        return response()->json($sub, 201);
    }

    public function activateSubscription(Request $request, string $subscriptionId): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Only super admins can perform billing operations.');
        $sub = Subscription::findOrFail($subscriptionId);
        $this->billing->activate($sub, $request->billing_cycle ?? 'monthly');
        return response()->json($sub->fresh('plan'));
    }

    public function cancelSubscription(Request $request, string $subscriptionId): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Only super admins can perform billing operations.');
        $request->validate(['reason' => 'required|string']);
        $sub = Subscription::findOrFail($subscriptionId);
        $this->billing->cancel($sub, $request->reason, $this->actorId());
        return response()->json(['message' => 'Subscription canceled.']);
    }

    public function suspendTenant(Request $request, string $tenantId): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Only super admins can perform billing operations.');
        $request->validate(['reason' => 'required|string']);
        $sub = Subscription::where('tenant_id', $tenantId)->latest()->firstOrFail();
        $this->billing->suspend($sub, $request->reason, $this->actorId());
        return response()->json(['message' => 'Tenant suspended.']);
    }

    public function extendAccess(Request $request, string $tenantId): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Only super admins can perform billing operations.');
        $data = $request->validate([
            'days'       => 'required|integer|min:1|max:365',
            'note'       => 'nullable|string|max:500',
            'reactivate' => 'boolean',
        ]);

        $tenant = \App\Models\Tenant::findOrFail($tenantId);
        $sub    = Subscription::where('tenant_id', $tenantId)->latest()->first();

        if ($sub) {
            // Extend trial_end_date if on trial, else extend next_billing_date
            if ($sub->trial_end_date) {
                $base = $sub->trial_end_date->isFuture()
                    ? $sub->trial_end_date
                    : now();
                $sub->trial_end_date = $base->addDays($data['days']);
            } else {
                $base = $sub->next_billing_date && $sub->next_billing_date->isFuture()
                    ? $sub->next_billing_date
                    : now();
                $sub->next_billing_date = $base->addDays($data['days']);
            }

            // Reactivate if requested or if currently suspended/inactive
            if (($data['reactivate'] ?? false) || in_array($sub->status, ['suspended', 'canceled'])) {
                $sub->status     = $sub->trial_end_date ? 'trial' : 'active';
                $sub->canceled_at = null;
            }

            $sub->save();
        }

        // Sync tenant status
        if (($data['reactivate'] ?? false) && $tenant->status === 'inactive') {
            $tenant->update(['status' => 'trial']);
        }

        // In-app notification for the tenant admin portal
        Notification::create([
            'id'            => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id'     => $tenantId,
            'category'      => 'billing',
            'type'          => 'info',
            'priority'      => 'high',
            'message'       => "R Bunny extended your access by {$data['days']} day" . ($data['days'] > 1 ? 's' : '') . '.' . ($data['note'] ? ' ' . $data['note'] : ''),
            'channel'       => 'in_app',
            'is_read'       => false,
            'is_dismissed'  => false,
            'metadata_json' => [
                'event'       => 'access_extended',
                'days'        => $data['days'],
                'note'        => $data['note'] ?? null,
                'extended_at' => now()->toISOString(),
                'reactivated' => $data['reactivate'] ?? false,
            ],
            'sent_at'       => now(),
        ]);

        // Send access-extended email to tenant admin
        try {
            if ($tenant->admin_email) {
                \App\Services\EmailLogger::send(
                    mailable: new \App\Mail\AccessExtendedMail(
                        tenantId:        $tenant->id,
                        tenantName:      $tenant->name,
                        tenantAdminName: $tenant->admin_name ?? $tenant->name,
                        adminEmail:      $tenant->admin_email,
                        days:            $data['days'],
                        note:            $data['note'] ?? null,
                    ),
                    recipientEmail: $tenant->admin_email,
                    recipientType:  'tenant_admin',
                    emailKey:       'access_extended.' . $tenant->id,
                    dailyDedup:     true,
                    subject:        "Your {$tenant->name} access has been extended",
                    tenantId:       $tenant->id,
                );
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Access extended email failed', [
                'tenant_id' => $tenantId,
                'error'     => $e->getMessage(),
            ]);
        }

        // Audit log
        BillingAuditLog::log('access_extended', [
            'tenant_id'    => $tenantId,
            'performed_by' => $this->actorId(),
            'reason'       => "Access extended by {$data['days']} day(s). " . ($data['note'] ?? ''),
            'after'        => [
                'days'       => $data['days'],
                'note'       => $data['note'] ?? null,
                'reactivate' => $data['reactivate'] ?? false,
            ],
        ]);

        try {
            app(\App\Services\CriticalActionService::class)->invalidateCache($tenantId, $this->actorId());
        } catch (\Throwable) {}

        return response()->json([
            'message'      => "Access extended by {$data['days']} day(s).",
            'subscription' => $sub,
            'tenant_status'=> $tenant->fresh()->status,
        ]);
    }

    // ── Invoices ──────────────────────────────────────────────
    public function invoices(Request $request): JsonResponse
    {
        $q = Invoice::with('tenant')->orderByDesc('created_at');

        if (TenantContext::isSuperAdmin()) {
            if ($request->filled('tenant_id')) $q->where('tenant_id', $request->tenant_id);
        } else {
            $q->where('tenant_id', TenantContext::requireId());
        }

        if ($request->filled('status')) $q->where('status', $request->status);
        return response()->json($q->limit(100)->get());
    }

    public function invoice(Invoice $invoice): JsonResponse
    {
        if (!TenantContext::isSuperAdmin() && $invoice->tenant_id !== TenantContext::requireId()) {
            abort(403, 'Access denied.');
        }
        return response()->json($invoice->load('tenant', 'payments', 'credits'));
    }

    public function createInvoice(Request $request): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Only super admins can perform billing operations.');
        $request->validate([
            'tenant_id'       => 'required|string|exists:tenants,id',
            'subscription_id' => 'required|string|exists:subscriptions,id',
            'amount_php'      => 'required|numeric|min:0',
            'notes'           => 'nullable|string',
        ]);

        $sub = Subscription::findOrFail($request->subscription_id);
        if ((string) $sub->tenant_id !== (string) $request->tenant_id) {
            abort(422, 'Subscription does not belong to this tenant.');
        }
        $invoice = $this->invoiceService->createForSubscription($sub, $request->amount_php, [], $request->notes ?? '');
        return response()->json($invoice, 201);
    }

    public function waiveInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Only super admins can perform billing operations.');
        $request->validate(['reason' => 'required|string']);
        $this->invoiceService->waive($invoice, $request->reason, $this->actorId());
        return response()->json(['message' => 'Invoice waived.']);
    }

    public function createPaymentIntent(Invoice $invoice): JsonResponse
    {
        if (!TenantContext::isSuperAdmin() && $invoice->tenant_id !== TenantContext::requireId()) {
            abort(403, 'Access denied.');
        }
        if ($invoice->status === 'paid') {
            return response()->json(['message' => 'Invoice is already paid.'], 422);
        }
        $intent = $this->paymongo->createPaymentIntent($invoice);
        return response()->json($intent);
    }

    // ── Payments ──────────────────────────────────────────────
    public function payments(Request $request): JsonResponse
    {
        $q = Payment::with('tenant')->orderByDesc('created_at');

        if (TenantContext::isSuperAdmin()) {
            if ($request->filled('tenant_id')) $q->where('tenant_id', $request->tenant_id);
        } else {
            $q->where('tenant_id', TenantContext::requireId());
        }

        if ($request->filled('status')) $q->where('status', $request->status);
        return response()->json($q->limit(100)->get());
    }

    // ── Refunds ───────────────────────────────────────────────
    public function requestRefund(Request $request): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Only super admins can request refunds.');

        $request->validate([
            'payment_id' => 'required|string|exists:payments,id',
            'amount'     => 'required|numeric|min:1',
            'reason'     => 'required|string',
        ]);

        $payment = Payment::findOrFail($request->payment_id);

        if ((float) $request->amount > (float) $payment->amount) {
            return response()->json(['message' => 'Refund amount cannot exceed the original payment amount.'], 422);
        }

        $refund = $this->billing->requestRefund($payment, $request->amount, $request->reason, $this->actorId());
        return response()->json($refund, 201);
    }

    public function refunds(Request $request): JsonResponse
    {
        $q = Refund::with('payment', 'tenant')->orderByDesc('created_at');

        if (TenantContext::isSuperAdmin()) {
            if ($request->filled('tenant_id')) $q->where('tenant_id', $request->tenant_id);
        } else {
            $q->where('tenant_id', TenantContext::requireId());
        }

        return response()->json($q->limit(100)->get());
    }

    // ── Credits ───────────────────────────────────────────────
    public function issueCredit(Request $request): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Only super admins can issue credits.');

        $request->validate([
            'tenant_id' => 'required|string|exists:tenants,id',
            'amount'    => 'required|numeric|min:1',
            'reason'    => 'required|string',
        ]);

        $credit = $this->billing->issueCredit($request->tenant_id, $request->amount, $request->reason, $this->actorId());
        return response()->json($credit, 201);
    }

    // ── Audit Log ─────────────────────────────────────────────
    public function auditLog(Request $request): JsonResponse
    {
        $q = BillingAuditLog::orderByDesc('created_at');

        if (TenantContext::isSuperAdmin()) {
            if ($request->filled('tenant_id')) $q->where('tenant_id', $request->tenant_id);
        } else {
            $q->where('tenant_id', TenantContext::requireId());
        }

        return response()->json($q->limit(200)->get());
    }

    // ── Super Admin: Change Tenant Plan (requires double-auth) ───────────────
    public function changeTenantPlan(Request $request, string $tenantId): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Only super admins can perform billing operations.');
        $data = $request->validate([
            'plan_id'           => 'required|string|exists:plans,id',
            'billing_status'    => 'nullable|in:standard,comped,internal,manual,sponsored',
            'billing_cycle'     => 'nullable|in:monthly,yearly',
            'subscription_end'  => 'nullable|date',
            'payment_required'  => 'nullable|boolean',
            'auto_renew'        => 'nullable|boolean',
            'note'              => 'required|string|max:1000',
            'password'          => 'required|string|min:8|max:128',
            'typed_confirm'     => 'nullable|string',
        ]);

        // ── Double Authentication: password confirmation ───────────────────
        $admin = Auth::guard('web')->user() ?? Auth::guard('tenant')->user();
        if (!$admin) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (!\Illuminate\Support\Facades\Hash::check($data['password'], $admin->password)) {
            BillingAuditLog::log('super_admin_plan_change_auth_failed', [
                'tenant_id'    => $tenantId,
                'performed_by' => $admin->id ?? 'unknown',
                'reason'       => 'Wrong password during plan change double-auth.',
            ]);
            return response()->json([
                'message'    => 'Password confirmation failed. Plan was not changed.',
                'error_code' => 'double_auth_failed',
            ], 403);
        }

        // ── Typed confirmation for Max plan upgrades ───────────────────────
        $newPlan = \App\Models\Plan::findOrFail($data['plan_id']);
        if (in_array(strtolower($newPlan->plan_key ?? $newPlan->name), ['max', 'enterprise'])) {
            if (($data['typed_confirm'] ?? '') !== 'UPGRADE TO MAX') {
                return response()->json([
                    'message'    => 'For Max plan upgrades, type exactly: UPGRADE TO MAX',
                    'error_code' => 'typed_confirm_required',
                ], 422);
            }
        }

        $tenant = \App\Models\Tenant::findOrFail($tenantId);
        $sub    = Subscription::where('tenant_id', $tenantId)->latest()->first();
        $oldPlan= $sub?->plan_id;

        if ($sub) {
            $sub->plan_id           = $data['plan_id'];
            $sub->status            = 'active';
            $sub->billing_status    = $data['billing_status'] ?? 'standard';
            $sub->billing_cycle     = $data['billing_cycle'] ?? $sub->billing_cycle ?? 'monthly';
            $sub->payment_required  = $data['payment_required'] ?? true;
            $sub->auto_renew        = $data['auto_renew'] ?? true;
            $sub->manual_note       = $data['note'];
            $sub->assigned_by       = (string) ($admin->id ?? 'super_admin');
            $sub->canceled_at       = null;
            if (!empty($data['subscription_end'])) {
                $sub->subscription_end_date = $data['subscription_end'];
                $sub->next_billing_date     = $data['subscription_end'];
            }
            $sub->save();
        } else {
            $endDate = $data['subscription_end'] ?? now()->addYear()->toDateString();
            $sub = Subscription::create([
                'tenant_id'           => $tenantId,
                'plan_id'             => $data['plan_id'],
                'status'              => 'active',
                'billing_status'      => $data['billing_status'] ?? 'standard',
                'billing_cycle'       => $data['billing_cycle'] ?? 'monthly',
                'start_date'          => today(),
                'next_billing_date'   => $endDate,
                'subscription_end_date' => $endDate,
                'payment_required'    => $data['payment_required'] ?? true,
                'auto_renew'          => $data['auto_renew'] ?? true,
                'manual_note'         => $data['note'],
                'assigned_by'         => (string) ($admin->id ?? 'super_admin'),
            ]);
        }

        // Audit
        BillingAuditLog::log('tenant_plan_changed_by_super_admin', [
            'tenant_id'    => $tenantId,
            'performed_by' => $admin->id ?? 'super_admin',
            'reason'       => $data['note'],
            'before'       => ['plan_id' => $oldPlan],
            'after'        => [
                'plan_id'        => $data['plan_id'],
                'plan_name'      => $newPlan->name,
                'billing_status' => $data['billing_status'] ?? 'standard',
                'billing_cycle'  => $data['billing_cycle'] ?? 'monthly',
            ],
        ]);

        // Notify tenant admin
        Notification::create([
            'id'            => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id'     => $tenantId,
            'category'      => 'billing',
            'type'          => 'info',
            'priority'      => 'high',
            'message'       => "Your ReferralBunny.ai plan has been updated to {$newPlan->name}.",
            'channel'       => 'in_app',
            'is_read'       => false,
            'is_dismissed'  => false,
            'metadata_json' => ['plan_name' => $newPlan->name, 'billing_status' => $data['billing_status'] ?? 'standard'],
            'sent_at'       => now(),
        ]);

        // Clear plan cache
        \Illuminate\Support\Facades\Cache::forget("tenant_plan_{$tenantId}");

        return response()->json([
            'message'      => "Plan updated to {$newPlan->name} successfully.",
            'subscription' => $sub->fresh('plan'),
        ]);
    }

    // ── Super Admin: Get tenant plan usage summary ────────────────────────
    public function tenantPlanUsage(string $tenantId): JsonResponse
    {
        if (!TenantContext::isSuperAdmin() && TenantContext::id() !== $tenantId) {
            abort(403, 'Access denied.');
        }
        $planService = app(\App\Services\TenantPlanService::class);
        return response()->json($planService->getUsageSummary($tenantId));
    }

    // ── Exchange Rates ────────────────────────────────────────
    public function exchangeRates(): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Only super admins can view exchange rates.');
        return response()->json(\App\Models\ExchangeRate::all());
    }

    public function updateExchangeRate(Request $request): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Only super admins can perform billing operations.');
        $request->validate([
            'base_currency'   => 'required|string|size:3',
            'target_currency' => 'required|string|size:3',
            'rate'            => 'required|numeric|min:0.000001',
        ]);

        $rate = \App\Models\ExchangeRate::updateOrCreate(
            ['base_currency' => strtoupper($request->base_currency), 'target_currency' => strtoupper($request->target_currency)],
            ['rate' => $request->rate, 'source' => 'manual', 'updated_at' => now()]
        );

        return response()->json($rate);
    }
}
