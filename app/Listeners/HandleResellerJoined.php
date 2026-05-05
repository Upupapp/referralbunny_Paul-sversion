<?php

namespace App\Listeners;

use App\Events\ResellerJoined;
use App\Mail\ResellerWelcome;
use App\Mail\TenantAdminNewReseller;
use App\Services\EmailLogger;
use Illuminate\Support\Facades\DB;

class HandleResellerJoined
{
    public function handle(ResellerJoined $event): void
    {
        $tenantName = DB::table('tenants')->where('id', $event->tenantId)->value('name') ?? $event->tenantId;

        // 1. Welcome email to reseller
        EmailLogger::send(
            mailable:       new ResellerWelcome(
                resellerName:  $event->resellerName,
                resellerEmail: $event->resellerEmail,
                tenantName:    $tenantName,
                dashboardUrl:  url("/reseller/{$event->tenantId}/dashboard"),
            ),
            recipientEmail: $event->resellerEmail,
            recipientType:  'reseller',
            emailKey:       "reseller_welcome.{$event->resellerId}",
            subject:        "Your ReferralBunny.ai referrer account is ready",
            tenantId:       $event->tenantId,
        );

        // 2. Alert tenant admins
        $admins = DB::table('tenant_memberships as tm')
            ->join('tenant_users as u', 'tm.tenant_user_id', '=', 'u.id')
            ->where('tm.tenant_id', $event->tenantId)
            ->whereIn('tm.role', ['owner', 'admin'])
            ->where('tm.status', 'active')
            ->select('u.email', 'u.first_name', 'u.last_name')
            ->get();

        foreach ($admins as $admin) {
            EmailLogger::send(
                mailable:       new TenantAdminNewReseller(
                    adminName:     trim("{$admin->first_name} {$admin->last_name}"),
                    tenantName:    $tenantName,
                    resellerName:  $event->resellerName,
                    resellerEmail: $event->resellerEmail,
                    joinDate:      now()->setTimezone('Asia/Manila')->format('M j, Y'),
                    dashboardUrl:  url("/tenant/{$event->tenantId}/referrers"),
                ),
                recipientEmail: $admin->email,
                recipientType:  'tenant_admin',
                emailKey:       "new_reseller_admin.{$event->resellerId}.{$admin->email}",
                subject:        "New referrer joined: {$event->resellerName}",
                tenantId:       $event->tenantId,
            );
        }
    }
}
