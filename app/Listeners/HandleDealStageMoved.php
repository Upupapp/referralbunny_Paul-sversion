<?php

namespace App\Listeners;

use App\Events\DealStageMoved;
use App\Mail\ResellerDealStageMoved;
use App\Models\Reseller;
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

        if (!$email || !$resellerId) {
            $reseller   = Reseller::where('tenant_id', $event->tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower($event->resellerName)])
                ->first();
            $email      = $email      ?? $reseller?->email;
            $resellerId = $resellerId ?? ($reseller ? (string) $reseller->id : null);

            // Skip pending/invited Referrers — they don't have portal access yet
            if ($reseller && $reseller->status === 'invited') return;
        }

        $toStageLabel = ucfirst(str_replace('_', ' ', $event->toStage));
        $dispatcher   = app(NotificationDispatchService::class);
        $tenantName   = DB::table('tenants')->where('id', $event->tenantId)->value('name') ?? $event->tenantId;

        // ── 1. Email to Referrer ──────────────────────────────────────────────
        if ($email) {
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
        }

        // ── 2. In-app to Referrer ─────────────────────────────────────────────
        if ($resellerId) {
            try {
                $dispatcher->dispatchToReseller(
                    resellerId:   $resellerId,
                    tenantId:     $event->tenantId,
                    category:     'deal_pipeline',
                    priority:     'normal',
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
                priority:     'normal',
                title:        "Deal stage moved: {$event->leadName}",
                body:         "{$event->resellerName} moved \"{$event->leadName}\" to {$toStageLabel}.",
                actionUrl:    url("/tenant/{$event->tenantId}/deals/{$event->leadId}"),
                actionLabel:  'View Deal',
                dedupeSuffix: "{$event->leadId}:stage:{$event->toStage}",
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
            $adminIds = DB::table('tenant_memberships as tm')
                ->join('tenant_users as u', 'tm.tenant_user_id', '=', 'u.id')
                ->where('tm.tenant_id', $event->tenantId)
                ->where('tm.status', 'active')
                ->whereIn('tm.role', ['owner', 'admin', 'manager'])
                ->pluck('u.id');

            foreach ($adminIds as $uid) {
                Cache::forget("ca_badge_{$event->tenantId}_{$uid}");
                Cache::forget("ca_badge_urgent:{$event->tenantId}:{$uid}");
            }
        } catch (\Throwable) {}
    }

    public function failed(DealStageMoved $event, \Throwable $exception): void
    {
        Log::error('[HandleDealStageMoved] Failed', [
            'lead_id' => $event->leadId,
            'error'   => $exception->getMessage(),
        ]);
    }
}
