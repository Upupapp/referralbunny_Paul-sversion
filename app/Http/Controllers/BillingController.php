<?php

namespace App\Http\Controllers;

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
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BillingController extends Controller
{
    public function __construct(
        private BillingService  $billing,
        private InvoiceService  $invoiceService,
        private PayMongoService $paymongo,
    ) {}

    // ── Dashboard ─────────────────────────────────────────────
    public function dashboard(): JsonResponse
    {
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
        $sub = Subscription::with('plan')
            ->where('tenant_id', $tenantId)
            ->latest()
            ->first();

        return response()->json($sub);
    }

    public function activateTrial(Request $request, string $tenantId): JsonResponse
    {
        $tenant = \App\Models\Tenant::findOrFail($tenantId);
        $sub    = $this->billing->startTrial($tenant, $request->plan_id);
        return response()->json($sub, 201);
    }

    public function activateSubscription(Request $request, string $subscriptionId): JsonResponse
    {
        $sub = Subscription::findOrFail($subscriptionId);
        $this->billing->activate($sub, $request->billing_cycle ?? 'monthly');
        return response()->json($sub->fresh('plan'));
    }

    public function cancelSubscription(Request $request, string $subscriptionId): JsonResponse
    {
        $request->validate(['reason' => 'required|string']);
        $sub = Subscription::findOrFail($subscriptionId);
        $this->billing->cancel($sub, $request->reason, $request->user()?->id ?? 1);
        return response()->json(['message' => 'Subscription canceled.']);
    }

    public function suspendTenant(Request $request, string $tenantId): JsonResponse
    {
        $request->validate(['reason' => 'required|string']);
        $sub = Subscription::where('tenant_id', $tenantId)->latest()->firstOrFail();
        $this->billing->suspend($sub, $request->reason, $request->user()?->id ?? 1);
        return response()->json(['message' => 'Tenant suspended.']);
    }

    public function extendAccess(Request $request, string $tenantId): JsonResponse
    {
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

        // Audit log
        BillingAuditLog::log('access_extended', [
            'tenant_id'    => $tenantId,
            'performed_by' => $request->user()?->id ?? 1,
            'reason'       => "Access extended by {$data['days']} day(s). " . ($data['note'] ?? ''),
            'after'        => [
                'days'       => $data['days'],
                'note'       => $data['note'] ?? null,
                'reactivate' => $data['reactivate'] ?? false,
            ],
        ]);

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
        if ($request->filled('tenant_id')) $q->where('tenant_id', $request->tenant_id);
        if ($request->filled('status'))    $q->where('status', $request->status);
        return response()->json($q->limit(100)->get());
    }

    public function invoice(Invoice $invoice): JsonResponse
    {
        return response()->json($invoice->load('tenant', 'payments', 'credits'));
    }

    public function createInvoice(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id'       => 'required|string|exists:tenants,id',
            'subscription_id' => 'required|string|exists:subscriptions,id',
            'amount_php'      => 'required|numeric|min:0',
            'notes'           => 'nullable|string',
        ]);

        $sub     = Subscription::findOrFail($request->subscription_id);
        $invoice = $this->invoiceService->createForSubscription($sub, $request->amount_php, [], $request->notes ?? '');
        return response()->json($invoice, 201);
    }

    public function waiveInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        $request->validate(['reason' => 'required|string']);
        $this->invoiceService->waive($invoice, $request->reason, $request->user()?->id ?? 1);
        return response()->json(['message' => 'Invoice waived.']);
    }

    public function createPaymentIntent(Invoice $invoice): JsonResponse
    {
        $intent = $this->paymongo->createPaymentIntent($invoice);
        return response()->json($intent);
    }

    // ── Payments ──────────────────────────────────────────────
    public function payments(Request $request): JsonResponse
    {
        $q = Payment::with('tenant')->orderByDesc('created_at');
        if ($request->filled('tenant_id')) $q->where('tenant_id', $request->tenant_id);
        if ($request->filled('status'))    $q->where('status', $request->status);
        return response()->json($q->limit(100)->get());
    }

    // ── Refunds ───────────────────────────────────────────────
    public function requestRefund(Request $request): JsonResponse
    {
        $request->validate([
            'payment_id' => 'required|string|exists:payments,id',
            'amount'     => 'required|numeric|min:1',
            'reason'     => 'required|string',
        ]);

        $payment = Payment::findOrFail($request->payment_id);
        $refund  = $this->billing->requestRefund($payment, $request->amount, $request->reason, $request->user()?->id ?? 1);
        return response()->json($refund, 201);
    }

    public function refunds(Request $request): JsonResponse
    {
        $q = Refund::with('payment', 'tenant')->orderByDesc('created_at');
        if ($request->filled('tenant_id')) $q->where('tenant_id', $request->tenant_id);
        return response()->json($q->limit(100)->get());
    }

    // ── Credits ───────────────────────────────────────────────
    public function issueCredit(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id' => 'required|string|exists:tenants,id',
            'amount'    => 'required|numeric|min:1',
            'reason'    => 'required|string',
        ]);

        $credit = $this->billing->issueCredit($request->tenant_id, $request->amount, $request->reason, $request->user()?->id ?? 1);
        return response()->json($credit, 201);
    }

    // ── Audit Log ─────────────────────────────────────────────
    public function auditLog(Request $request): JsonResponse
    {
        $q = BillingAuditLog::orderByDesc('created_at');
        if ($request->filled('tenant_id')) $q->where('tenant_id', $request->tenant_id);
        return response()->json($q->limit(200)->get());
    }

    // ── Exchange Rates ────────────────────────────────────────
    public function exchangeRates(): JsonResponse
    {
        return response()->json(\App\Models\ExchangeRate::all());
    }

    public function updateExchangeRate(Request $request): JsonResponse
    {
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
