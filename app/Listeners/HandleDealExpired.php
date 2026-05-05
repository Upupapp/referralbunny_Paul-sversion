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
    }
}
