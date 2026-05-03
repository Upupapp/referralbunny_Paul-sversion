<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Credit;
use App\Models\ExchangeRate;
use App\Models\Tenant;
use App\Models\BillingAuditLog;

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

        // Apply pending credits
        $availableCredits = Credit::where('tenant_id', $tenant->id)
            ->whereNull('applied_to_invoice_id')
            ->sum('amount');
        $creditsApplied = min($availableCredits, $baseAmountPhp);
        $finalAmount    = max(0, $baseAmountPhp - $creditsApplied);

        $invoice = Invoice::create([
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
                'description' => ($subscription->plan?->name ?? 'Subscription') . ' — ' . ucfirst($subscription->billing_cycle),
                'amount_php'  => $baseAmountPhp,
                'amount_display' => $displayAmount,
                'currency'    => $currency,
            ]],
            'notes' => $notes,
        ]);

        // Mark credits as applied
        if ($creditsApplied > 0) {
            Credit::where('tenant_id', $tenant->id)
                ->whereNull('applied_to_invoice_id')
                ->take(10)
                ->get()
                ->each(fn($c) => $c->update(['applied_to_invoice_id' => $invoice->id]));
        }

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
        $invoice->update(['status' => 'paid', 'paid_at' => now()]);

        BillingAuditLog::log('invoice_paid', [
            'tenant_id'   => $invoice->tenant_id,
            'entity_type' => 'invoice',
            'entity_id'   => $invoice->id,
            'after'       => ['payment_id' => $paymentId],
        ]);
    }

    public function waive(Invoice $invoice, string $reason, int $userId): void
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
