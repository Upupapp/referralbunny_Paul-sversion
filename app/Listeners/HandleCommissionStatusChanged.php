<?php

namespace App\Listeners;

use App\Events\CommissionStatusChanged;
use App\Mail\CommissionStatusUpdate;
use App\Services\EmailLogger;
use App\Services\NotificationDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandleCommissionStatusChanged implements ShouldQueue
{
    public int $tries   = 3;
    public int $backoff = 10;

    public function failed(CommissionStatusChanged $event, \Throwable $e): void
    {
        \Illuminate\Support\Facades\Log::error(
            'HandleCommissionStatusChanged failed permanently for lead ' . $event->leadId . ': ' . $e->getMessage()
        );
    }

    public function handle(CommissionStatusChanged $event): void
    {
        // Explicit if/else avoids the ambiguous OR that the ->when() inside orWhereRaw() generates
        if ($event->resellerEmail) {
            $reseller = DB::table('resellers')
                ->where('tenant_id', $event->tenantId)
                ->where('email', $event->resellerEmail)
                ->whereNull('deleted_at')
                ->select('id', 'email')
                ->first();
        } elseif ($event->resellerName) {
            $reseller = DB::table('resellers')
                ->where('tenant_id', $event->tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower($event->resellerName)])
                ->whereNull('deleted_at')
                ->select('id', 'email')
                ->first();
        } else {
            $reseller = null;
        }

        $email = $reseller?->email ?? $event->resellerEmail;
        if (!$email) return;

        $tenantName = DB::table('tenants')->where('id', $event->tenantId)->value('name') ?? $event->tenantId;

        // Priority map used for both reseller and partner notifications
        $priorities = ['pending' => 'normal', 'locked' => 'high', 'paid' => 'high'];
        $dispatcher = app(NotificationDispatchService::class);

        // In-app notification
        if ($reseller) {
            $titles = [
                'pending' => 'Commission pending',
                'locked'  => 'Commission approved',
                'paid'    => 'Commission paid',
            ];
            $bodies = [
                'pending' => "Your commission for {$event->leadName} is pending review.",
                'locked'  => "Your commission for {$event->leadName} was approved.",
                'paid'    => "Your commission for {$event->leadName} has been paid.",
            ];

            $dispatcher->dispatchToReseller(
                resellerId:   $reseller->id,
                tenantId:     $event->tenantId,
                category:     'commission',
                priority:     $priorities[$event->newStatus] ?? 'normal',
                title:        $titles[$event->newStatus] ?? 'Commission updated',
                body:         $bodies[$event->newStatus] ?? "Commission status updated for {$event->leadName}.",
                actionUrl:    url("/reseller/{$event->tenantId}/commission"),
                actionLabel:  'View Commission',
                dedupeSuffix: "commission_{$event->newStatus}.{$event->leadId}",
                metadata:     ['deal_id' => $event->leadId, 'status' => $event->newStatus],
            );
        }

        $subjects = [
            'pending' => "Commission pending for {$event->leadName}",
            'locked'  => "Commission locked for {$event->leadName}",
            'paid'    => "Commission paid for {$event->leadName}",
        ];

        try {
            EmailLogger::send(
                mailable:       new CommissionStatusUpdate(
                    resellerName:  $event->resellerName,
                    resellerEmail: $email,
                    tenantName:    $tenantName,
                    dealName:      $event->leadName,
                    status:        $event->newStatus,
                    dealValue:     $event->dealValue,
                    dashboardUrl:  url("/reseller/{$event->tenantId}/commission"),
                ),
                recipientEmail: $email,
                recipientType:  'reseller',
                emailKey:       "commission_{$event->newStatus}.{$event->leadId}",
                subject:        $subjects[$event->newStatus] ?? "Commission update for {$event->leadName}",
                tenantId:       $event->tenantId,
            );
        } catch (\Throwable $e) {
            Log::warning('[HandleCommissionStatusChanged] Email send failed', ['error' => $e->getMessage()]);
        }

        // Notify partners who have an active split on this deal
        try {
            $partnerSplits = DB::table('deal_partner_splits')
                ->where('deal_id', $event->leadId)
                ->where('tenant_id', $event->tenantId)
                ->whereNull('deleted_at')
                ->where('status', '!=', 'removed')
                ->whereNotNull('partner_user_id')
                ->select('partner_user_id')
                ->get();

            if ($partnerSplits->isNotEmpty()) {
                $partnerTitles = [
                    'pending' => 'Commission pending',
                    'locked'  => 'Commission approved',
                    'paid'    => 'Commission paid',
                ];
                $partnerBodies = [
                    'pending' => "Commission for deal \"{$event->leadName}\" is pending review.",
                    'locked'  => "Commission for deal \"{$event->leadName}\" has been approved.",
                    'paid'    => "Commission for deal \"{$event->leadName}\" has been paid.",
                ];

                foreach ($partnerSplits as $split) {
                    $dispatcher->dispatchToPartner(
                        partnerId:    $split->partner_user_id,
                        tenantId:     $event->tenantId,
                        category:     'commission',
                        priority:     $priorities[$event->newStatus] ?? 'normal',
                        title:        $partnerTitles[$event->newStatus] ?? 'Commission updated',
                        body:         $partnerBodies[$event->newStatus] ?? "Commission updated for \"{$event->leadName}\".",
                        actionUrl:    url('/partner/commissions'),
                        actionLabel:  'View Commissions',
                        dedupeSuffix: "partner_commission_{$event->newStatus}.{$event->leadId}.{$split->partner_user_id}",
                        metadata:     ['deal_id' => $event->leadId, 'status' => $event->newStatus],
                    );
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[HandleCommissionStatusChanged] Partner notification failed', ['error' => $e->getMessage()]);
        }

        // Cache bust — refresh reseller bell + admin CA badge so counts update promptly
        try {
            if ($reseller) {
                Cache::forget("notif_unread_reseller_{$reseller->id}");
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
                Cache::forget("notif_unread_tenant_admin_{$uid}");
            }
        } catch (\Throwable) {}
    }
}
