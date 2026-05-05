<?php

namespace App\Listeners;

use App\Events\DealCreated;
use App\Mail\ResellerDealCreated;
use App\Mail\TenantAdminNewDeal;
use App\Services\EmailLogger;
use Illuminate\Support\Facades\DB;

class HandleDealCreated
{
    public function handle(DealCreated $event): void
    {
        $tenantName = DB::table('tenants')->where('id', $event->tenantId)->value('name') ?? $event->tenantId;

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

        // 2. Notify tenant admins
        $admins = DB::table('tenant_memberships as tm')
            ->join('tenant_users as u', 'tm.tenant_user_id', '=', 'u.id')
            ->where('tm.tenant_id', $event->tenantId)
            ->whereIn('tm.role', ['owner', 'admin'])
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
