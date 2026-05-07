<?php

namespace App\Listeners;

use App\Events\DealExpired;
use App\Services\NotificationDispatchService;
use Illuminate\Support\Facades\DB;

class HandleDealExpired
{
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

        // Notify reseller if known
        $resellerId = null;
        if ($event->resellerEmail) {
            $resellerId = DB::table('resellers')
                ->where('tenant_id', $event->tenantId)
                ->where('email', $event->resellerEmail)
                ->value('id');
        }
        if (!$resellerId && $event->resellerName) {
            $resellerId = DB::table('resellers')
                ->where('tenant_id', $event->tenantId)
                ->where('name', $event->resellerName)
                ->value('id');
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
        }

        // Notify active Partners associated with this deal
        $activePartners = DB::table('deal_partner_splits')
            ->where('tenant_id', $event->tenantId)
            ->where('deal_id', $event->leadId)
            ->where('status', 'active')
            ->whereNotNull('partner_user_id')
            ->whereNull('deleted_at')
            ->select('partner_user_id', 'partner_name')
            ->get();

        foreach ($activePartners as $partnerRow) {
            $dispatcher->dispatchToPartner(
                partnerId:    (string) $partnerRow->partner_user_id,
                tenantId:     $event->tenantId,
                category:     'deal_pipeline',
                priority:     'high',
                title:        "Associated deal expired: {$event->leadName}",
                body:         "A deal you are associated with has expired: \"{$event->leadName}\".",
                actionUrl:    url("/partner/dashboard"),
                actionLabel:  'View Dashboard',
                dedupeSuffix: "{$event->leadId}:expired:partner",
            );
        }
    }
}
