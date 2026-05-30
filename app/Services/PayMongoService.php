<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayMongoService
{
    private string $secretKey;
    private string $baseUrl = 'https://api.paymongo.com/v1';

    public function __construct()
    {
        $this->secretKey = config('services.paymongo.secret_key', '');
    }

    // ── Create Payment Intent ─────────────────────────────────

    public function createPaymentIntent(Invoice $invoice): array
    {
        $tenant      = $invoice->tenant;
        $amountPhp   = (float) $invoice->final_amount;
        $amountCents = (int) round($amountPhp * 100); // PayMongo uses centavos

        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->post("{$this->baseUrl}/payment_intents", [
                    'data' => [
                        'attributes' => [
                            'amount'                => $amountCents,
                            'currency'              => 'PHP',
                            'payment_method_allowed'=> ['card', 'gcash', 'maya', 'grab_pay'],
                            'description'           => "Invoice {$invoice->invoice_number} — {$tenant->name}",
                            'metadata'              => [
                                'invoice_id'    => $invoice->id,
                                'tenant_id'     => $tenant->id,
                                'invoice_number'=> $invoice->invoice_number,
                            ],
                        ],
                    ],
                ]);

            if ($response->successful()) {
                return $response->json('data');
            }

            Log::error('PayMongo createPaymentIntent failed', $response->json());
            return [];

        } catch (\Throwable $e) {
            Log::error('PayMongo exception: ' . $e->getMessage());
            return [];
        }
    }

    // ── Record Payment (from webhook or manual) ───────────────

    public function recordPayment(Invoice $invoice, array $paymongoData): Payment
    {
        $tenant      = $invoice->tenant;
        $amountPhp   = (float) ($paymongoData['attributes']['amount'] ?? 0) / 100;
        $currency    = $tenant->preferred_currency ?? 'PHP';
        $rate        = $currency !== 'PHP'
            ? (float) (ExchangeRate::where('base_currency', 'PHP')->where('target_currency', $currency)->value('rate') ?? 1)
            : 1.0;

        return Payment::create([
            'tenant_id'           => $tenant->id,
            'subscription_id'     => $invoice->subscription_id,
            'invoice_id'          => $invoice->id,
            'plan_id'             => $invoice->subscription?->plan_id,
            'provider'            => 'paymongo',
            'amount'              => $amountPhp,
            'currency'            => 'PHP',
            'base_amount_php'     => $amountPhp,
            'display_amount'      => round($amountPhp * $rate, 2),
            'display_currency'    => $currency,
            'exchange_rate_used'  => $rate,
            'status'              => 'paid',
            'external_payment_id' => $paymongoData['id'] ?? null,
            'payment_method'      => $paymongoData['attributes']['payment_method_used'] ?? null,
            'metadata_json'       => $paymongoData,
        ]);
    }

    // ── Verify Webhook Signature ──────────────────────────────

    public function verifyWebhook(string $rawBody, string $signature): bool
    {
        $webhookSecret = config('services.paymongo.webhook_secret', '');
        if (empty($webhookSecret) || empty($signature)) return false;

        // PayMongo signature header format: t=<timestamp>,li=<hmac>,te=<hmac>
        // Signed payload is: "{timestamp}.{rawBody}"
        // We verify against the "li" (live) component.
        $parts = [];
        foreach (explode(',', $signature) as $part) {
            [$k, $v] = array_pad(explode('=', $part, 2), 2, '');
            $parts[trim($k)] = trim($v);
        }

        if (empty($parts['t']) || empty($parts['li'])) {
            return false;
        }

        $signedPayload = $parts['t'] . '.' . $rawBody;
        $computedSig   = hash_hmac('sha256', $signedPayload, $webhookSecret);

        return hash_equals($computedSig, $parts['li']);
    }

    // ── Refund ────────────────────────────────────────────────

    public function refund(string $externalPaymentId, float $amountPhp): array
    {
        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->post("{$this->baseUrl}/refunds", [
                    'data' => [
                        'attributes' => [
                            'amount'  => (int) round($amountPhp * 100),
                            'payment_id' => $externalPaymentId,
                            'reason'  => 'requested_by_customer',
                        ],
                    ],
                ]);

            return $response->json('data') ?? [];
        } catch (\Throwable $e) {
            Log::error('PayMongo refund error: ' . $e->getMessage());
            return [];
        }
    }
}
