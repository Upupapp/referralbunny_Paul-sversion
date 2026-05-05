<?php

namespace App\Listeners;

use App\Events\CommissionStatusChanged;
use App\Mail\CommissionStatusUpdate;
use App\Services\EmailLogger;
use Illuminate\Support\Facades\DB;

class HandleCommissionStatusChanged
{
    public function handle(CommissionStatusChanged $event): void
    {
        $email = $event->resellerEmail
            ?? DB::table('resellers')
                ->where('tenant_id', $event->tenantId)
                ->where('name', $event->resellerName)
                ->value('email');

        if (!$email) return;

        $tenantName = DB::table('tenants')->where('id', $event->tenantId)->value('name') ?? $event->tenantId;

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
