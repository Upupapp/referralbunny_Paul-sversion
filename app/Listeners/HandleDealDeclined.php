<?php

namespace App\Listeners;

use App\Events\DealDeclined;
use App\Mail\ResellerDealDeclined;
use App\Models\Reseller;
use App\Services\EmailLogger;
use App\Services\NotificationDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandleDealDeclined implements ShouldQueue
{
    public int $tries   = 3;
    public int $backoff = 10;

    public function handle(DealDeclined $event): void
    {
        $email      = $event->resellerEmail;
        $resellerId = $event->resellerId;

        if (!$email || !$resellerId) {
            $reseller   = Reseller::where('tenant_id', $event->tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower($event->resellerName)])
                ->first();
            $email      = $email      ?? $reseller?->email;
            $resellerId = $resellerId ?? ($reseller ? (string) $reseller->id : null);

            if ($reseller && $reseller->status === 'invited') return;
        }

        if (!$email) return;

        $tenantName = DB::table('tenants')->where('id', $event->tenantId)->value('name') ?? $event->tenantId;
        $dispatcher = app(NotificationDispatchService::class);

        EmailLogger::send(
            mailable: new ResellerDealDeclined(
                resellerName:   $event->resellerName,
                resellerEmail:  $email,
                tenantName:     $tenantName,
                dealName:       $event->leadName,
                stage:          $event->stage,
                dealValue:      $event->dealValue,
                declinedByName: $event->declinedByName ?? 'Admin',
                dashboardUrl:   url("/reseller/{$event->tenantId}/deals"),
            ),
            recipientEmail: $email,
            recipientType:  'reseller',
            emailKey:       'deal_declined.' . $event->leadId,
            subject:        "Deal update — {$event->leadName} has been declined",
            recipientId:    $resellerId,
            tenantId:       $event->tenantId,
            dailyDedup:     true,
        );

        // In-app: Referrer
        if ($resellerId) {
            try {
                $dispatcher->dispatchToReseller(
                    resellerId:   $resellerId,
                    tenantId:     $event->tenantId,
                    category:     'deal_pipeline',
                    priority:     'high',
                    title:        "Deal declined: {$event->leadName}",
                    body:         "\"{$event->leadName}\" has been declined by " . ($event->declinedByName ?? 'Admin') . '.',
                    actionUrl:    url("/reseller/{$event->tenantId}/deals"),
                    actionLabel:  'View Deals',
                    dedupeSuffix: "{$event->leadId}:declined",
                );
            } catch (\Throwable $e) {
                Log::warning('[HandleDealDeclined] Referrer in-app failed', ['error' => $e->getMessage()]);
            }
        }

        // In-app: Tenant Admins
        try {
            $dispatcher->dispatchToTenantAdmins(
                tenantId:     $event->tenantId,
                category:     'deal_pipeline',
                priority:     'normal',
                title:        "Deal declined: {$event->leadName}",
                body:         "\"{$event->leadName}\" (Referrer: {$event->resellerName}) was declined by " . ($event->declinedByName ?? 'Admin') . '.',
                actionUrl:    url("/tenant/{$event->tenantId}/deals/{$event->leadId}"),
                actionLabel:  'View Deal',
                dedupeSuffix: "{$event->leadId}:declined_admin",
            );
        } catch (\Throwable $e) {
            Log::warning('[HandleDealDeclined] Admin in-app failed', ['error' => $e->getMessage()]);
        }
    }

    public function failed(DealDeclined $event, \Throwable $exception): void
    {
        Log::error('[HandleDealDeclined] Failed', [
            'lead_id' => $event->leadId,
            'error'   => $exception->getMessage(),
        ]);
    }
}
