<?php

namespace App\Listeners;

use App\Events\DealExtensionApproved;
use App\Mail\ResellerDealExtensionApproved;
use App\Models\Reseller;
use App\Services\EmailLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandleDealExtensionApproved implements ShouldQueue
{
    public int $tries = 3;

    public function handle(DealExtensionApproved $event): void
    {
        $email      = $event->resellerEmail;
        $resellerId = $event->resellerId;

        if (!$email || !$resellerId) {
            $reseller   = Reseller::where('tenant_id', $event->tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower($event->resellerName)])
                ->first();
            $email      = $email      ?? $reseller?->email;
            $resellerId = $resellerId ?? ($reseller ? (string) $reseller->id : null);

            if ($reseller && $reseller->status === 'invited') return;
        }

        if (!$email) return;

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
            emailKey:       'deal_extension_approved.' . $event->leadId . '.' . now()->format('YmdHi'),
            subject:        "Extension approved — {$event->approvedDays} days added to: {$event->leadName}",
            recipientId:    $resellerId,
            tenantId:       $event->tenantId,
            dailyDedup:     false,
        );
    }

    public function failed(DealExtensionApproved $event, \Throwable $exception): void
    {
        Log::error('[HandleDealExtensionApproved] Failed', [
            'lead_id' => $event->leadId,
            'error'   => $exception->getMessage(),
        ]);
    }
}
