<?php

namespace App\Listeners;

use App\Events\CommissionStatusChanged;
use App\Mail\CommissionStatusUpdate;
use App\Services\EmailLogger;
use App\Services\NotificationDispatchService;
use Illuminate\Support\Facades\DB;

class HandleCommissionStatusChanged
{
    public function handle(CommissionStatusChanged $event): void
    {
        $reseller = DB::table('resellers')
            ->where('tenant_id', $event->tenantId)
            ->where(fn($q) => $q->where('email', $event->resellerEmail ?? '')
                                ->orWhere('name', $event->resellerName))
            ->select('id', 'email')
            ->first();

        $email = $reseller?->email ?? $event->resellerEmail;
        if (!$email) return;

        $tenantName = DB::table('tenants')->where('id', $event->tenantId)->value('name') ?? $event->tenantId;

        // In-app notification
        if ($reseller) {
            $dispatcher = app(NotificationDispatchService::class);
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
            $priorities = ['pending' => 'normal', 'locked' => 'high', 'paid' => 'high'];

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
    }
}
