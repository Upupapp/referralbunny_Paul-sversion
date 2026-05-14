<?php

namespace App\Listeners;

use App\Events\DealCreated;
use App\Mail\ResellerDealCreated;
use App\Mail\TenantAdminNewDeal;
use App\Services\EmailLogger;
use App\Services\NotificationDispatchService;
use Illuminate\Support\Facades\DB;

class HandleDealCreated
{
    public function handle(DealCreated $event): void
    {
        $tenantName = DB::table('tenants')->where('id', $event->tenantId)->value('name') ?? $event->tenantId;
        $dispatcher = app(NotificationDispatchService::class);

        // 1. Notify reseller (if they have an email and account)
        $email = $event->resellerEmail
            ?? DB::table('resellers')
                ->where('tenant_id', $event->tenantId)
                ->where('name', $event->resellerName)
                ->value('email');

        if ($email) {
            EmailLogger::send(
                mailable:       new ResellerDealCreated(
                    resellerName: $event->resellerName,
                    resellerEmail: $email,
                    tenantName:   $tenantName,
                    dealName:     $event->leadName,
                    stage:        $event->stage,
                    daysLeft:     $event->daysLeft,
                    dealValue:    $event->dealValue,
                    dashboardUrl: url("/reseller/{$event->tenantId}/deals"),
                ),
                recipientEmail: $email,
                recipientType:  'reseller',
                emailKey:       "deal_created.{$event->leadId}",
                subject:        "Deal created: {$event->leadName}",
                tenantId:       $event->tenantId,
            );
        }

        // 2a. In-app: notify reseller
        if ($email) {
            $reseller = DB::table('resellers')
                ->where('tenant_id', $event->tenantId)
                ->where('email', $email)
                ->select('id')
                ->first();
            if ($reseller) {
                $dispatcher->dispatchToReseller(
                    resellerId:   $reseller->id,
                    tenantId:     $event->tenantId,
                    category:     'deal_pipeline',
                    priority:     'normal',
                    title:        "Deal created: {$event->leadName}",
                    body:         "Your deal has been created successfully.",
                    actionUrl:    url("/reseller/{$event->tenantId}/deals"),
                    actionLabel:  'View Deal',
                    dedupeSuffix: $event->leadId,
                );
            }
        }

        // 2b. In-app: notify tenant admins
        $dispatcher->dispatchToTenantAdmins(
            tenantId:     $event->tenantId,
            category:     'deal_pipeline',
            priority:     'normal',
            title:        "New deal created: {$event->leadName}",
            body:         "{$event->resellerName} created a new deal.",
            actionUrl:    url("/tenant/{$event->tenantId}/deals"),
            actionLabel:  'Review Deal',
            dedupeSuffix: $event->leadId,
            metadata:     ['deal_id' => $event->leadId, 'stage' => $event->stage],
        );

        // 2. Email: notify tenant admins + managers
        $admins = DB::table('tenant_memberships as tm')
            ->join('tenant_users as u', 'tm.tenant_user_id', '=', 'u.id')
            ->where('tm.tenant_id', $event->tenantId)
            ->whereIn('tm.role', ['owner', 'admin', 'manager'])
            ->where('tm.status', 'active')
            ->select('u.email', 'u.first_name', 'u.last_name')
            ->get();

        foreach ($admins as $admin) {
            EmailLogger::send(
                mailable:       new TenantAdminNewDeal(
                    adminName:    trim("{$admin->first_name} {$admin->last_name}"),
                    tenantName:   $tenantName,
                    dealName:     $event->leadName,
                    resellerName: $event->resellerName,
                    stage:        $event->stage,
                    daysLeft:     $event->daysLeft,
                    dealValue:    $event->dealValue,
                    dashboardUrl: url("/tenant/{$event->tenantId}/deals"),
                ),
                recipientEmail: $admin->email,
                recipientType:  'tenant_admin',
                emailKey:       "new_deal_admin.{$event->leadId}.{$admin->email}",
                subject:        "New deal created: {$event->leadName}",
                tenantId:       $event->tenantId,
            );
        }
    }
}
