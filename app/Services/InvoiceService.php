<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Credit;
use App\Models\ExchangeRate;
use App\Models\Tenant;
use App\Models\BillingAuditLog;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function createForSubscription(
        Subscription $subscription,
        float        $baseAmountPhp,
        array        $lineItems  = [],
        string       $notes      = ''
    ): Invoice {
        $tenant   = $subscription->tenant;
        $currency = $tenant->preferred_currency ?? 'PHP';
        $rate     = 1.0;

        if ($currency !== 'PHP') {
            $rate = (float) ExchangeRate::where('base_currency', 'PHP')
                ->where('target_currency', $currency)
                ->value('rate') ?? 1.0;
        }

        $displayAmount = round($baseAmountPhp * $rate, 2);

        $invoice = DB::transaction(function () use ($tenant, $subscription, $baseAmountPhp, $displayAmount, $currency, $rate, $lineItems, $notes) {
            // Lock and fetch unapplied credits in one query to prevent concurrent double-application
            $lockedCredits    = Credit::where('tenant_id', $tenant->id)
                ->whereNull('applied_to_invoice_id')
                ->lockForUpdate()
                ->get();

            $availableCredits = $lockedCredits->sum('amount');
            $creditsApplied   = min($availableCredits, $baseAmountPhp);
            $finalAmount      = max(0, $baseAmountPhp - $creditsApplied);

            $inv = Invoice::create([
                'tenant_id'          => $tenant->id,
                'subscription_id'    => $subscription->id,
                'invoice_number'     => Invoice::generateNumber(),
                'base_amount_php'    => $baseAmountPhp,
                'display_amount'     => $displayAmount,
                'display_currency'   => $currency,
                'exchange_rate_used' => $rate,
                'credits_applied'    => $creditsApplied,
                'final_amount'       => $finalAmount,
                'status'             => 'open',
                'due_date'           => now()->addDays(7)->toDateString(),
                'line_items_json'    => $lineItems ?: [[
                    'description'    => ($subscription->plan?->name ?? 'Subscription') . ' — ' . ucfirst($subscription->billing_cycle),
                    'amount_php'     => $baseAmountPhp,
                    'amount_display' => $displayAmount,
                    'currency'       => $currency,
                ]],
                'notes' => $notes,
            ]);

            if ($creditsApplied > 0) {
                $creditIds = $lockedCredits->take(10)->pluck('id');
                Credit::whereIn('id', $creditIds)->update(['applied_to_invoice_id' => $inv->id]);
            }

            return $inv;
        });

        BillingAuditLog::log('invoice_created', [
            'tenant_id'   => $tenant->id,
            'entity_type' => 'invoice',
            'entity_id'   => $invoice->id,
            'after'       => ['amount' => $baseAmountPhp, 'currency' => $currency],
        ]);

        return $invoice;
    }

    public function markPaid(Invoice $invoice, string $paymentId): void
    {
        if ($invoice->status === 'paid') {
            return; // idempotent — already paid, skip to prevent duplicate audit entries
        }
        $invoice->update(['status' => 'paid', 'paid_at' => now()]);

        BillingAuditLog::log('invoice_paid', [
            'tenant_id'   => $invoice->tenant_id,
            'entity_type' => 'invoice',
            'entity_id'   => $invoice->id,
            'after'       => ['payment_id' => $paymentId],
        ]);
    }

    public function waive(Invoice $invoice, string $reason, ?string $userId): void
    {
        $before = $invoice->toArray();
        $invoice->update(['status' => 'waived']);

        BillingAuditLog::log('invoice_waived', [
            'tenant_id'    => $invoice->tenant_id,
            'entity_type'  => 'invoice',
            'entity_id'    => $invoice->id,
            'performed_by' => $userId,
            'reason'       => $reason,
            'before'       => $before,
            'after'        => ['status' => 'waived'],
        ]);
    }
}
