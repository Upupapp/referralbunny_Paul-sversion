<?php

namespace App\Listeners;

use App\Events\DealExpired;
use App\Mail\ResellerDealExpired;
use App\Services\CriticalActionService;
use App\Services\EmailLogger;
use App\Services\NotificationDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Queued so expiry notifications don't block the expiry job.
 * Rule: Admin + Manager receive high-severity in-app notification (already in place).
 * Digest rule: multiple deals expiring in quick succession do NOT each send an email.
 * The daily email digest job (SendResellerDailySummariesJob) handles bulk expiry emails.
 * Cache bust: clears admin CA badges so the nav badge updates within 60s.
 */
class HandleDealExpired implements ShouldQueue
{
    public int $tries = 3;
    public int $backoff = 10;

    public function handle(DealExpired $event): void
    {
        $dispatcher = app(NotificationDispatchService::class);

        // Notify tenant admins
        $dispatcher->dispatchToTenantAdmins(
            tenantId:     $event->tenantId,
            category:     'deal_pipeline',
            priority:     'high',
            title:        "Deal expired and is now re-claimable: {$event->leadName}",
            body:         "{$event->leadName} expired and may now be claimed again.",
            actionUrl:    url("/tenant/{$event->tenantId}/deals/{$event->leadId}"),
            actionLabel:  'View Deal',
            dedupeSuffix: "{$event->leadId}:expired",
            metadata:     ['deal_id' => $event->leadId, 'stage' => $event->stage],
        );

        // Notify reseller if known — look up by name from resellers table to get email
        $resellerId    = null;
        $resellerEmail = null;
        if ($event->resellerName) {
            $reseller = DB::table('resellers')
                ->where('tenant_id', $event->tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower($event->resellerName)])
                ->whereNull('deleted_at')
                ->first(['id', 'email', 'status']);
            if ($reseller) {
                if ($reseller->status === 'invited') {
                    $resellerId    = null;
                    $resellerEmail = null;
                } else {
                    $resellerId    = $reseller->id;
                    $resellerEmail = $reseller->email;
                }
            }
        }

        if ($resellerId) {
            $dispatcher->dispatchToReseller(
                resellerId:   $resellerId,
                tenantId:     $event->tenantId,
                category:     'deal_pipeline',
                priority:     'high',
                title:        "Deal expired: {$event->leadName}",
                body:         "This deal has expired and is now re-claimable by other referrers.",
                actionUrl:    url("/reseller/{$event->tenantId}/deals"),
                actionLabel:  'View Deals',
                dedupeSuffix: "{$event->leadId}:expired",
            );

            // Send email to reseller
            if ($resellerEmail) {
                try {
                    $tenantName = DB::table('tenants')->where('id', $event->tenantId)->value('name') ?? 'Referral Bunny';
                    EmailLogger::send(
                        mailable: new ResellerDealExpired(
                            resellerName:  $event->resellerName ?? '',
                            resellerEmail: $resellerEmail,
                            tenantName:    $tenantName,
                            dealName:      $event->leadName,
                            stage:         $event->stage,
                            dashboardUrl:  url("/reseller/{$event->tenantId}/deals"),
                        ),
                        recipientEmail: $resellerEmail,
                        recipientType:  'reseller',
                        recipientId:    (string) $resellerId,
                        emailKey:       'deal_expired.' . $event->leadId . '.' . $resellerId,
                        subject:        "Deal expired: {$event->leadName}",
                        tenantId:       $event->tenantId,
                    );
                } catch (\Throwable) {}
            }
        }

        // Notify active Partners associated with this deal
        $activePartners = DB::table('deal_partner_splits')
            ->where('tenant_id', $event->tenantId)
            ->where('deal_id', $event->leadId)
            ->where('status', '!=', 'removed')
            ->whereNotNull('partner_user_id')
            ->whereNull('deleted_at')
            ->select('partner_user_id', 'partner_name')
            ->get();

        $pKeys = [];
        foreach ($activePartners as $partnerRow) {
            $pKeys[] = "notif_unread_partner_{$partnerRow->partner_user_id}";
            $pKeys[] = "partner_notif_unread:{$partnerRow->partner_user_id}";
            try {
                $dispatcher->dispatchToPartner(
                    partnerId:    (string) $partnerRow->partner_user_id,
                    tenantId:     $event->tenantId,
                    category:     'deal_pipeline',
                    priority:     'high',
                    title:        "Associated deal expired: {$event->leadName}",
                    body:         "A deal you are associated with has expired: \"{$event->leadName}\".",
                    actionUrl:    url("/partner/deals/{$event->leadId}"),
                    actionLabel:  'View Deal',
                    dedupeSuffix: "{$event->leadId}:expired:partner",
                );
            } catch (\Throwable) {}
        }
        if (!empty($pKeys)) {
            Cache::deleteMultiple($pKeys);
        }

        // ── Cache bust: refresh admin CA badges within 60s ────────────────────
        try {
            $adminIds = app(CriticalActionService::class)->invalidateAllAdminBadges($event->tenantId);
            foreach ($adminIds as $uid) {
                Cache::forget("notif_unread_tenant_admin_{$uid}");
            }
        } catch (\Throwable $e) {
            Log::warning('[HandleDealExpired] Cache bust failed', ['error' => $e->getMessage()]);
        }
        if ($resellerId) {
            try { Cache::forget("notif_unread_reseller_{$resellerId}"); } catch (\Throwable) {}
        }
    }

    public function failed(DealExpired $event, \Throwable $exception): void
    {
        Log::error('[HandleDealExpired] Failed after all retries', [
            'tenant_id' => $event->tenantId,
            'lead_id'   => $event->leadId,
            'error'     => $exception->getMessage(),
        ]);
    }
}
