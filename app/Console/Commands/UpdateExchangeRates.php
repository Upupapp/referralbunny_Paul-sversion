<?php

namespace App\Console\Commands;

use App\Models\ExchangeRate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UpdateExchangeRates extends Command
{
    protected $signature   = 'billing:update-rates';
    protected $description = 'Fetch and update exchange rates (PHP base currency)';

    // Supported display currencies
    private array $targets = ['USD', 'SGD', 'EUR'];

    public function handle(): void
    {
        try {
            // Try fetching from exchangerate-api (free tier)
            $apiKey  = config('services.exchange_rates.api_key', '');
            $baseUrl = "https://v6.exchangerate-api.com/v6/{$apiKey}/latest/PHP";

            if ($apiKey) {
                $response = Http::get($baseUrl);
                if ($response->successful()) {
                    $rates = $response->json('conversion_rates', []);
                    foreach ($this->targets as $target) {
                        if (isset($rates[$target])) {
                            ExchangeRate::updateOrCreate(
                                ['base_currency' => 'PHP', 'target_currency' => $target],
                                ['rate' => $rates[$target], 'source' => 'exchangerate-api', 'updated_at' => now()]
                            );
                            ExchangeRate::updateOrCreate(
                                ['base_currency' => $target, 'target_currency' => 'PHP'],
                                ['rate' => round(1 / $rates[$target], 6), 'source' => 'exchangerate-api', 'updated_at' => now()]
                            );
                            $this->line("Updated PHP → {$target}: {$rates[$target]}");
                        }
                    }
                    $this->info('Exchange rates updated from API.');
                    return;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Exchange rate API fetch failed: ' . $e->getMessage());
        }

        // Fallback: log that rates need manual update
        $this->warn('Exchange rates not updated. Set EXCHANGE_RATES_API_KEY in .env or update manually via /api/billing/exchange-rates.');
    }
}
