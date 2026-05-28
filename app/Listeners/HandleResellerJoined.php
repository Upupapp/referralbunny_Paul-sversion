<?php

namespace App\Listeners;

use App\Events\ResellerJoined;
use App\Mail\ResellerWelcome;
use App\Mail\TenantAdminNewReseller;
use App\Services\EmailLogger;
use App\Services\NotificationDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandleResellerJoined implements ShouldQueue
{
    public int $tries   = 3;
    public int $backoff = 10;

    public function handle(ResellerJoined $event): void
    {
        $tenantName = DB::table('tenants')->where('id', $event->tenantId)->value('name') ?? $event->tenantId;
        $dispatcher = app(NotificationDispatchService::class);

        // 0. In-app: welcome referrer
        $dispatcher->dispatchToReseller(
            resellerId:   $event->resellerId,
            tenantId:     $event->tenantId,
            category:     'account_profile',
            priority:     'normal',
            title:        'Your Referrer account is ready',
            body:         "You can now access your Referrer dashboard for {$tenantName}.",
            actionUrl:    url("/reseller/{$event->tenantId}/dashboard"),
            actionLabel:  'Open Dashboard',
            dedupeSuffix: $event->resellerId,
        );

        // 0b. In-app: notify tenant admins
        $dispatcher->dispatchToTenantAdmins(
            tenantId:     $event->tenantId,
            category:     'reseller_referrer',
            priority:     'normal',
            title:        "New Referrer joined: {$event->resellerName}",
            body:         "A new Referrer has joined your workspace.",
            actionUrl:    url("/tenant/{$event->tenantId}/referrers"),
            actionLabel:  'View Referrer',
            dedupeSuffix: $event->resellerId,
            metadata:     ['reseller_id' => $event->resellerId],
        );

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

        // 2. Alert tenant admins and managers via email
        $admins = DB::table('tenant_memberships as tm')
            ->join('tenant_users as u', 'tm.tenant_user_id', '=', 'u.id')
            ->where('tm.tenant_id', $event->tenantId)
            ->whereIn('tm.role', ['owner', 'admin', 'manager'])
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

    public function failed(ResellerJoined $event, \Throwable $exception): void
    {
        Log::error('[HandleResellerJoined] Failed after all retries', [
            'reseller_id' => $event->resellerId,
            'tenant_id'   => $event->tenantId,
            'error'       => $exception->getMessage(),
        ]);
    }
}
