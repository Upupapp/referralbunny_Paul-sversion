<?php

namespace App\Listeners;

use App\Events\DealExtensionApproved;
use App\Mail\ResellerDealExtensionApproved;
use App\Models\Reseller;
use App\Services\CriticalActionService;
use App\Services\EmailLogger;
use App\Services\NotificationDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandleDealExtensionApproved implements ShouldQueue
{
    public int $tries   = 3;
    public int $backoff = 10;

    public function handle(DealExtensionApproved $event): void
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

        // Cache bust — fires unconditionally; admin badge/bell clears even for invited referrers
        // Bell keys now busted inside invalidateAllAdminBadges.
        try {
            app(CriticalActionService::class)->invalidateAllAdminBadges($event->tenantId);
        } catch (\Throwable) {}

        if (!$email && !$resellerId) return;

        // Email to Referrer
        if ($email) {
            $tenantName = DB::table('tenants')->where('id', $event->tenantId)->value('name') ?? $event->tenantId;
            EmailLogger::send(
                mailable: new ResellerDealExtensionApproved(
                    resellerName:  $event->resellerName,
                    resellerEmail: $email,
                    tenantName:    $tenantName,
                    dealName:      $event->leadName,
                    approvedDays:  $event->approvedDays,
                    newDaysLeft:   $event->newDaysLeft,
                    adminNote:     $event->adminNote,
                    dealUrl:       url("/reseller/{$event->tenantId}/deals/{$event->leadId}"),
                ),
                recipientEmail: $email,
                recipientType:  'reseller',
                emailKey:       'deal_extension_approved.' . $event->leadId . '.' . ($resellerId ?? md5($email)),
                subject:        "Extension approved — {$event->approvedDays} days added to: {$event->leadName}",
                recipientId:    $resellerId,
                tenantId:       $event->tenantId,
                dailyDedup:     false,
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
                    title:        "Extension approved: {$event->leadName}",
                    body:         "Your extension request was approved. {$event->approvedDays} extra days have been added.",
                    actionUrl:    url("/reseller/{$event->tenantId}/deals/{$event->leadId}"),
                    actionLabel:  'View Deal',
                    dedupeSuffix: "deal_extension_approved.{$event->leadId}",
                );
            } catch (\Throwable $e) {
                Log::warning('[HandleDealExtensionApproved] in-app failed', ['error' => $e->getMessage()]);
            }
            Cache::forget("notif_unread_reseller_{$resellerId}");

            // Refresh referrer-side caches ("active deals" stats, activity lists, and
            // critical-action badges) for the primary referrer AND any commission_splits
            // co-referrers, so a just-approved extension (status reverted to 'active') is
            // reflected immediately — mirrors TenantDealLifecycleController::bulkExtendExpired.
            try {
                Cache::forget("referrer_perf:{$event->tenantId}:{$resellerId}");
                Cache::forget("reseller_leadids:{$event->tenantId}:{$resellerId}");
                Cache::forget("ca_reseller:{$event->tenantId}:" . md5($event->resellerName . ':' . $resellerId));

                $coReferrerNames = DB::table('commission_splits')
                    ->where('lead_id', $event->leadId)
                    ->pluck('reseller_name')
                    ->map(fn($n) => trim((string) $n))
                    ->filter()
                    ->unique()
                    ->reject(fn($n) => strtolower($n) === strtolower($event->resellerName));

                foreach ($coReferrerNames as $rName) {
                    $r = Reseller::where('tenant_id', $event->tenantId)
                        ->whereRaw('LOWER(name) = ?', [strtolower($rName)])
                        ->first(['id']);

                    if ($r) {
                        Cache::forget("referrer_perf:{$event->tenantId}:{$r->id}");
                        Cache::forget("reseller_leadids:{$event->tenantId}:{$r->id}");
                    }
                    Cache::forget("ca_reseller:{$event->tenantId}:" . md5($rName . ':' . ($r?->id ?? '')));
                }
            } catch (\Throwable) {}
        }
    }

    public function failed(DealExtensionApproved $event, \Throwable $exception): void
    {
        Log::error('[HandleDealExtensionApproved] Failed', [
            'lead_id' => $event->leadId,
            'error'   => $exception->getMessage(),
        ]);
    }
}
