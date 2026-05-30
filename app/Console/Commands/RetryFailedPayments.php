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
                // Increment retry count and set next retry
                $payment->increment('retry_count');
                $payment->update(['next_retry_at' => now()->addDay()]);

                // Notify based on retry day
                $notifications->notifyPaymentFailed($payment->tenant_id, $day);

                $this->line("Retry #{$payment->retry_count} triggered for payment {$payment->id} (day {$day})");
            }
        }

        // Suspend tenants with 3+ failed retries (day 7+)
        $toSuspend = Payment::where('status', 'failed')
            ->where('retry_count', '>=', 3)
            ->whereDate('updated_at', today())
            ->get();

        foreach ($toSuspend as $payment) {
            try {
                $subscription = Subscription::where('tenant_id', $payment->tenant_id)
                    ->whereIn('status', ['active', 'trial', 'past_due'])
                    ->latest()
                    ->first();

                if ($subscription) {
                    $billing->suspend($subscription);
                }
            } catch (\Throwable $e) {
                $this->error("Failed to suspend tenant {$payment->tenant_id}: {$e->getMessage()}");
            }

            $notifications->send(
                category:  'billing',
                type:      'action_required',
                priority:  'critical',
                message:   "Payment failed after 3 retries. Account suspended.",
                tenantId:  $payment->tenant_id,
                channel:   'all',
            );
        }

        $this->info('Payment retry cycle complete.');
    }
}
