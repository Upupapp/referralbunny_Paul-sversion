<?php

namespace App\Console\Commands;

use App\Models\PromoCode;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class ExpirePromoCodes extends Command
{
    protected $signature   = 'promos:expire';
    protected $description = 'Mark expired promo codes and notify on upcoming expirations';

    public function handle(NotificationService $notifications): void
    {
        // Mark past-due codes as expired
        $expired = PromoCode::where('status', 'active')
            ->whereNotNull('valid_until')
            ->where('valid_until', '<', now())
            ->get();

        foreach ($expired as $code) {
            $code->update(['status' => 'expired']);
            \App\Models\BillingAuditLog::log('promo_code_expired', [
                'entity_type' => 'promo_code',
                'entity_id'   => $code->id,
                'after'       => ['status' => 'expired'],
            ]);
        }

        if ($expired->count() > 0) {
            $notifications->send(
                category:  'billing',
                type:      'info',
                priority:  'low',
                message:   "{$expired->count()} promo code(s) expired today.",
                actionUrl: '/platform/billing/promo-codes',
                channel:   'in_app',
                metadata:  ['count' => $expired->count()],
            );
        }

        // Warn on codes expiring within 3 days
        $expiringSoon = PromoCode::where('status', 'active')
            ->whereNotNull('valid_until')
            ->whereBetween('valid_until', [now(), now()->addDays(3)])
            ->get();

        foreach ($expiringSoon as $code) {
            $notifications->send(
                category:  'billing',
                type:      'warning',
                priority:  'medium',
                message:   "Promo code '{$code->code}' expires on {$code->valid_until->toDateString()}.",
                actionUrl: "/platform/billing/promo-codes/{$code->id}",
                channel:   'in_app',
                metadata:  ['promo_code_id' => $code->id],
            );
        }

        $this->info("Expired: {$expired->count()}. Expiring soon: {$expiringSoon->count()}.");
    }
}
