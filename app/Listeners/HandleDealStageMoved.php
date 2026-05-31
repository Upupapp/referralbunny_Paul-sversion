<?php

namespace App\Listeners;

use App\Events\DealStageMoved;
use App\Mail\ResellerDealStageMoved;
use App\Models\Reseller;
use App\Services\CriticalActionService;
use App\Services\EmailLogger;
use App\Services\NotificationDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles deal stage move notifications.
 *
 * Channel rules enforced:
 *  - Referrer: email (existing) + in-app notification (added)
 *  - Tenant Admins: in-app notification (new — they were previously not notified of stage moves)
 *  - Cache bust: clears admin CA badge so the nav badge updates
 */
class HandleDealStageMoved implements ShouldQueue
{
    public int $tries = 3;
    public int $backoff = 10;

    public function handle(DealStageMoved $event): void
    {
        $email      = $event->resellerEmail;
        $resellerId = $event->resellerId;
        $reseller   = null;

        if (!$email && !$resellerId && $event->resellerName) {
            $reseller   = Reseller::where('tenant_id', $event->tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower($event->resellerName)])
                ->first();
            $email      = $email      ?? $reseller?->email;
            $resellerId = $resellerId ?? ($reseller ? (string) $reseller->id : null);
        }

        // Skip notifications for invited Referrers — they don't have portal access yet.
        // This check must run outside the lookup block so it also fires when resellerId
        // was passed directly in the event (e.g., from LeadController::moveStage()).
        if ($resellerId) {
            $invitedStatus = $reseller
                ? $reseller->status
                : Reseller::where('tenant_id', $event->tenantId)->where('id', $resellerId)->value('status');
            if ($invitedStatus === 'invited') {
                $email      = null;
                $resellerId = null;
            }
        }

        $toStageLabel = ucfirst(str_replace('_', ' ', $event->toStage));
        $priority     = in_array($event->toStage, ['signed', 'paid']) ? 'high' : 'normal';
        $dispatcher   = app(NotificationDispatchService::class);
        $tenantName   = DB::table('tenants')->where('id', $event->tenantId)->value('name') ?? $event->tenantId;

        // ── 1. Email to Referrer ──────────────────────────────────────────────
        if ($email) {
            try {
                EmailLogger::send(
                    mailable: new ResellerDealStageMoved(
                        resellerName:  $event->resellerName,
                        resellerEmail: $email,
                        tenantName:    $tenantName,
                        dealName:      $event->leadName,
                        fromStage:     $event->fromStage,
                        toStage:       $event->toStage,
                        dealValue:     $event->dealValue,
                        movedByName:   $event->movedByName ?? 'Admin',
                        dealUrl:       url("/reseller/{$event->tenantId}/deals/{$event->leadId}"),
                    ),
                    recipientEmail: $email,
                    recipientType:  'reseller',
                    emailKey:       'deal_stage_moved.' . $event->leadId . '.' . $event->toStage,
                    subject:        "Deal stage updated: {$event->leadName} → {$toStageLabel}",
                    recipientId:    $resellerId,
                    tenantId:       $event->tenantId,
                    dailyDedup:     true,
                );
            } catch (\Throwable $e) {
                Log::warning('[HandleDealStageMoved] Email send failed', ['error' => $e->getMessage()]);
            }
        }

        // ── 2. In-app to Referrer ─────────────────────────────────────────────
        if ($resellerId) {
            try {
                $dispatcher->dispatchToReseller(
                    resellerId:   $resellerId,
                    tenantId:     $event->tenantId,
                    category:     'deal_pipeline',
                    priority:     $priority,
                    title:        "Deal stage updated: {$event->leadName}",
                    body:         "Your deal moved to {$toStageLabel}.",
                    actionUrl:    url("/reseller/{$event->tenantId}/deals/{$event->leadId}"),
                    actionLabel:  'View Deal',
                    dedupeSuffix: "{$event->leadId}:stage:{$event->toStage}",
                );
            } catch (\Throwable $e) {
                Log::warning('[HandleDealStageMoved] Referrer in-app failed', ['error' => $e->getMessage()]);
            }
        }

        // ── 3. In-app to Tenant Admins (stage moves are workflow events — Admin should see) ──
        try {
            $dispatcher->dispatchToTenantAdmins(
                tenantId:     $event->tenantId,
                category:     'deal_pipeline',
                priority:     $priority,
                title:        "Deal stage moved: {$event->leadName}",
                body:         (($event->movedByName ?? $event->resellerName ?: 'Admin') . " moved \"{$event->leadName}\" to {$toStageLabel}."),
                actionUrl:    url("/tenant/{$event->tenantId}/deals/{$event->leadId}"),
                actionLabel:  'View Deal',
                dedupeSuffix: "{$event->leadId}:stage:{$event->toStage}:admin",
                metadata:     [
                    'deal_id'    => $event->leadId,
                    'from_stage' => $event->fromStage,
                    'to_stage'   => $event->toStage,
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('[HandleDealStageMoved] Admin in-app failed', ['error' => $e->getMessage()]);
        }

        // ── 4. Cache bust ─────────────────────────────────────────────────────
        try {
            $adminIds = app(CriticalActionService::class)->invalidateAllAdminBadges($event->tenantId);

            foreach ($adminIds as $uid) {
                Cache::forget("notif_unread_tenant_admin_{$uid}");
            }

            if ($resellerId) {
                Cache::forget("notif_unread_reseller_{$resellerId}");
            }

            // Bust partner bell caches for all active partners on this deal
            $partnerIds = DB::table('deal_partner_splits')
                ->where('deal_id', $event->leadId)
                ->where('tenant_id', $event->tenantId)
                ->whereNull('deleted_at')
                ->where('status', '!=', 'removed')
                ->whereNotNull('partner_user_id')
                ->pluck('partner_user_id');

            foreach ($partnerIds as $pid) {
                Cache::forget("notif_unread_partner_{$pid}");
                Cache::forget("partner_notif_unread:{$pid}");
            }
        } catch (\Throwable $e) {
            Log::warning('[HandleDealStageMoved] Cache bust failed', ['error' => $e->getMessage()]);
        }
    }

    public function failed(DealStageMoved $event, \Throwable $exception): void
    {
        Log::error('[HandleDealStageMoved] Failed', [
            'lead_id' => $event->leadId,
            'error'   => $exception->getMessage(),
        ]);
    }
}
