<?php

namespace App\Listeners;

use App\Events\DealDeclined;
use App\Mail\ResellerDealDeclined;
use App\Models\Reseller;
use App\Services\EmailLogger;
use App\Services\NotificationDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
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
        $reseller = null;

        // Only do the DB lookup when BOTH identifiers are missing — AND guard prevents
        // overwriting a valid resellerId when only email is absent, and the resellerName
        // guard prevents a LOWER(name)='' match on deals with no assigned reseller.
        if (!$email && !$resellerId && $event->resellerName) {
            $reseller   = Reseller::where('tenant_id', $event->tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower($event->resellerName)])
                ->first();
            $email      = $email      ?? $reseller?->email;
            $resellerId = $resellerId ?? ($reseller ? (string) $reseller->id : null);
        }

        // Null-out both identifiers for invited (unactivated) resellers so neither email
        // nor in-app fires for them. Admin and cache-bust paths still fire below.
        if ($resellerId) {
            $invitedStatus = $reseller
                ? $reseller->status
                : Reseller::where('tenant_id', $event->tenantId)->where('id', $resellerId)->value('status');
            if ($invitedStatus === 'invited') {
                $email      = null;
                $resellerId = null;
            }
        }

        $dispatcher = app(NotificationDispatchService::class);

        // Email: only if reseller is active and has an email
        if ($email) {
            $tenantName = DB::table('tenants')->where('id', $event->tenantId)->value('name') ?? $event->tenantId;
            try {
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
            } catch (\Throwable $e) {
                Log::warning('[HandleDealDeclined] Email send failed', ['error' => $e->getMessage()]);
            }
        }

        // In-app: Referrer — fires when reseller is active and known, regardless of email
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

        // In-app: Tenant Admins — always fires regardless of reseller email/status
        try {
            $dispatcher->dispatchToTenantAdmins(
                tenantId:     $event->tenantId,
                category:     'deal_pipeline',
                priority:     'normal',
                title:        "Deal declined: {$event->leadName}",
                body:         "\"{$event->leadName}\"" .
                    ($event->resellerName ? " (Referrer: {$event->resellerName})" : '') .
                    ' was declined by ' . ($event->declinedByName ?? 'Admin') . '.',
                actionUrl:    url("/tenant/{$event->tenantId}/deals/{$event->leadId}"),
                actionLabel:  'View Deal',
                dedupeSuffix: "{$event->leadId}:declined_admin",
            );
        } catch (\Throwable $e) {
            Log::warning('[HandleDealDeclined] Admin in-app failed', ['error' => $e->getMessage()]);
        }

        // Cache bust — refresh reseller bell + admin CA/notification badges
        try {
            if ($resellerId) {
                Cache::forget("notif_unread_reseller_{$resellerId}");
            }
            app(\App\Services\CriticalActionService::class)->invalidateCache($event->tenantId);

            $adminIds = DB::table('tenant_memberships as tm')
                ->join('tenant_users as u', 'tm.tenant_user_id', '=', 'u.id')
                ->where('tm.tenant_id', $event->tenantId)
                ->where('tm.status', 'active')
                ->whereIn('tm.role', ['owner', 'admin', 'manager'])
                ->pluck('u.id');

            foreach ($adminIds as $uid) {
                Cache::forget("ca_badge_{$event->tenantId}_{$uid}");
                Cache::forget("ca_badge_urgent:{$event->tenantId}:{$uid}");
                Cache::forget("ca_badge_suppressed:{$event->tenantId}:{$uid}");
                Cache::forget("notif_unread_tenant_admin_{$uid}");
            }

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
            Log::warning('[HandleDealDeclined] Cache bust failed', ['error' => $e->getMessage()]);
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
