<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\WebhookEvent;
use App\Services\BillingService;
use App\Services\InvoiceService;
use App\Services\NotificationService;
use App\Services\PayMongoService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private PayMongoService     $paymongo,
        private BillingService      $billing,
        private InvoiceService      $invoiceService,
        private NotificationService $notifications,
    ) {}

    public function paymongo(Request $request): Response
    {
        $rawBody  = $request->getContent();
        $signature= $request->header('Paymongo-Signature', '');

        // Verify signature
        if (!$this->paymongo->verifyWebhook($rawBody, $signature)) {
            Log::warning('PayMongo webhook: invalid signature');
            return response('Unauthorized', 401);
        }

        $payload   = $request->json()->all();
        $eventType = $payload['data']['attributes']['type'] ?? '';
        $data      = $payload['data']['attributes']['data'] ?? [];

        // Store raw webhook
        $event = WebhookEvent::create([
            'provider'   => 'paymongo',
            'event_type' => $eventType,
            'payload'    => $payload,
        ]);

        try {
            match ($eventType) {
                'payment.paid'   => $this->handlePaymentPaid($data, $event),
                'payment.failed' => $this->handlePaymentFailed($data, $event),
                default          => Log::info("PayMongo unhandled event: {$eventType}"),
            };

            $event->update(['processed' => true]);
        } catch (\Throwable $e) {
            Log::error("Webhook processing error: " . $e->getMessage());
            $event->update(['error' => $e->getMessage()]);
        }

        return response('OK', 200);
    }

    private function handlePaymentPaid(array $data, WebhookEvent $event): void
    {
        $metadata  = $data['attributes']['metadata'] ?? [];
        $invoiceId = $metadata['invoice_id'] ?? null;

        if (!$invoiceId) {
            Log::warning('payment.paid: no invoice_id in metadata');
            return;
        }

        // Lock invoice row to prevent duplicate payment recording under concurrent webhooks
        $result = \Illuminate\Support\Facades\DB::transaction(function () use ($invoiceId, $data) {
            $invoice = Invoice::where('id', $invoiceId)->lockForUpdate()->first();
            if (!$invoice) return null;
            if ($invoice->status === 'paid') return 'skipped';

            $payment = $this->paymongo->recordPayment($invoice, $data);
            $this->invoiceService->markPaid($invoice, $payment->id);
            return [$invoice->fresh(), $payment];
        });

        if ($result === null) {
            Log::warning("payment.paid: invoice {$invoiceId} not found");
            return;
        }
        if ($result === 'skipped') {
            Log::info("payment.paid: invoice {$invoiceId} already paid — skipping");
            return;
        }

        [$invoice, $payment] = $result;

        // Activate subscription
        $subscription = Subscription::find($invoice->subscription_id);
        if ($subscription && $subscription->status !== 'active') {
            $this->billing->activate($subscription);
        }

        // Notify
        $this->notifications->send(
            category:  'billing',
            type:      'info',
            priority:  'low',
            message:   "Payment received — ₱" . number_format($payment->base_amount_php, 2) . " for invoice {$invoice->invoice_number}.",
            tenantId:  $invoice->tenant_id,
            channel:   'email',
        );

        Log::info("Payment paid processed: invoice {$invoiceId}");
    }

    private function handlePaymentFailed(array $data, WebhookEvent $event): void
    {
        $metadata  = $data['attributes']['metadata'] ?? [];
        $invoiceId = $metadata['invoice_id'] ?? null;
        if (!$invoiceId) return;

        $result = \Illuminate\Support\Facades\DB::transaction(function () use ($invoiceId) {
            $invoice = Invoice::where('id', $invoiceId)->lockForUpdate()->first();
            if (!$invoice) return null;
            if ($invoice->status === 'past_due') return 'skipped';

            $invoice->update(['status' => 'past_due']);

            $subscription = Subscription::find($invoice->subscription_id);
            if ($subscription) {
                $subscription->update(['status' => 'past_due']);
            }

            return $invoice->fresh();
        });

        if ($result === null) {
            Log::warning("payment.failed: invoice {$invoiceId} not found");
            return;
        }
        if ($result === 'skipped') {
            Log::info("payment.failed: invoice {$invoiceId} already past_due — skipping");
            return;
        }

        $invoice       = $result;
        $daysSinceFail = $invoice->due_date
            ? (int) now()->diffInDays($invoice->due_date)
            : 0;

        $this->notifications->notifyPaymentFailed($invoice->tenant_id, $daysSinceFail);

        Log::info("Payment failed processed: invoice {$invoiceId}");
    }
}
