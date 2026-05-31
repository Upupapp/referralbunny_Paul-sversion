<?php

namespace App\Listeners;

use App\Events\DealAmountUpdated;
use App\Mail\ResellerDealAmountUpdated;
use App\Models\Reseller;
use App\Services\CriticalActionService;
use App\Services\EmailLogger;
use App\Services\NotificationDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandleDealAmountUpdated implements ShouldQueue
{
    public int $tries   = 3;
    public int $backoff = 10;

    public function handle(DealAmountUpdated $event): void
    {
        $email      = $event->resellerEmail;
        $resellerId = $event->resellerId;
        $reseller   = null;

        if (!$email || !$resellerId) {
            $reseller   = Reseller::where('tenant_id', $event->tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower($event->resellerName)])
                ->first();
            $email      = $email      ?? $reseller?->email;
            $resellerId = $resellerId ?? ($reseller ? (string) $reseller->id : null);
        }

        // Guard invited resellers even when caller pre-resolved both email and resellerId
        if ($resellerId) {
            $invitedStatus = $reseller?->status
                ?? Reseller::where('tenant_id', $event->tenantId)->where('id', $resellerId)->value('status');
            if ($invitedStatus === 'invited') {
                $email      = null;
                $resellerId = null;
            }
        }

        // Cache bust — fires unconditionally; admin badge/bell must clear even for invited referrers
        try {
            $adminIds = app(CriticalActionService::class)->invalidateAllAdminBadges($event->tenantId);
            Cache::deleteMultiple($adminIds->map(fn($uid) => "notif_unread_tenant_admin_{$uid}")->toArray());
        } catch (\Throwable) {}

        if (!$email && !$resellerId) return;

        // Email to Referrer
        if ($email) {
            $tenantName = DB::table('tenants')->where('id', $event->tenantId)->value('name') ?? $event->tenantId;
            EmailLogger::send(
                mailable: new ResellerDealAmountUpdated(
                    resellerName:  $event->resellerName,
                    resellerEmail: $email,
                    tenantName:    $tenantName,
                    dealName:      $event->leadName,
                    oldAmount:     $event->oldAmount,
                    newAmount:     $event->newAmount,
                    updatedByName: $event->updatedByName ?? 'Admin',
                    dealUrl:       url("/reseller/{$event->tenantId}/deals/{$event->leadId}"),
                ),
                recipientEmail: $email,
                recipientType:  'reseller',
                emailKey:       'deal_amount_updated.' . $event->leadId,
                subject:        "Deal amount updated: {$event->leadName}",
                recipientId:    $resellerId,
                tenantId:       $event->tenantId,
                dailyDedup:     true,
            );
        }

        // In-app to Referrer
        if ($resellerId) {
            try {
                app(NotificationDispatchService::class)->dispatchToReseller(
                    resellerId:   $resellerId,
                    tenantId:     $event->tenantId,
                    category:     'deal_pipeline',
                    priority:     'normal',
                    title:        "Deal amount updated: {$event->leadName}",
                    body:         "The contract value for \"{$event->leadName}\" has been updated.",
                    actionUrl:    url("/reseller/{$event->tenantId}/deals/{$event->leadId}"),
                    actionLabel:  'View Deal',
                    dedupeSuffix: "deal_amount_updated.{$event->leadId}",
                );
            } catch (\Throwable $e) {
                Log::warning('[HandleDealAmountUpdated] in-app failed', ['error' => $e->getMessage()]);
            }
            Cache::forget("notif_unread_reseller_{$resellerId}");
        }
    }

    public function failed(DealAmountUpdated $event, \Throwable $exception): void
    {
        Log::error('[HandleDealAmountUpdated] Failed', [
            'lead_id' => $event->leadId,
            'error'   => $exception->getMessage(),
        ]);
    }
}
