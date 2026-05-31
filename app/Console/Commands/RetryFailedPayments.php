<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\Subscription;
use App\Services\BillingService;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class RetryFailedPayments extends Command
{
    protected $signature   = 'billing:retry-payments';
    protected $description = 'Retry failed payments on schedule (Day 1, 3, 7)';

    public function handle(NotificationService $notifications, BillingService $billing): void
    {
        $retryDays = [1, 3, 7];

        foreach ($retryDays as $day) {
            $payments = Payment::where('status', 'failed')
                ->where('retry_count', '<', 3)
                ->whereDate('created_at', today()->subDays($day))
                ->get();

            foreach ($payments as $payment) {
                try {
                    $payment->increment('retry_count');
                    $payment->update(['next_retry_at' => now()->addDay()]);
                    $notifications->notifyPaymentFailed($payment->tenant_id, $day);
                    $this->line("Retry #{$payment->retry_count} triggered for payment {$payment->id} (day {$day})");
                } catch (\Throwable $e) {
                    $this->error("Failed to process retry for payment {$payment->id}: {$e->getMessage()}");
                }
            }
        }

        // Suspend tenants with 3+ failed retries (day 7+)
        $toSuspend = Payment::where('status', 'failed')
            ->where('retry_count', '>=', 3)
            ->whereDate('updated_at', today())
            ->get();

        if ($toSuspend->isNotEmpty()) {
            // Batch-load subscriptions to avoid N+1
            $subscriptionsByTenant = Subscription::whereIn('tenant_id', $toSuspend->pluck('tenant_id')->unique())
                ->whereIn('status', ['active', 'trial', 'past_due'])
                ->get()
                ->groupBy('tenant_id')
                ->map(fn($group) => $group->sortByDesc('created_at')->first());

            foreach ($toSuspend as $payment) {
                try {
                    $subscription = $subscriptionsByTenant->get($payment->tenant_id);

                    if ($subscription) {
                        // suspend() internally fires the tenant-facing suspension notification
                        $billing->suspend($subscription, 'Payment failed after 3 retries', null);
                    }
                } catch (\Throwable $e) {
                    $this->error("Failed to suspend tenant {$payment->tenant_id}: {$e->getMessage()}");
                }
            }
        }

        $this->info('Payment retry cycle complete.');
    }
}
