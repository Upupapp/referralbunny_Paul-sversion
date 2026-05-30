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
    public int $tries   = 3;
    public int $backoff = 10;

    public function handle(DealExtensionApproved $event): void
    {
        $email      = $event->resellerEmail;
        $resellerId = $event->resellerId;
        $reseller   = null;

        if (!$email || !$resellerId) {
            $reseller   = Reseller::where('tenant_id', $event->tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower($event->resellerName)])
                ->first();
            $email      = $email      ?? $reseller?->email;
            $resellerId = $resellerId ?? ($reseller ? (string) $reseller->id : null);
        }

        // Guard invited resellers even when caller pre-resolved both email and resellerId
        if ($resellerId) {
            $invitedStatus = $reseller?->status
                ?? Reseller::where('tenant_id', $event->tenantId)->where('id', $resellerId)->value('status');
            if ($invitedStatus === 'invited') return;
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
            emailKey:       'deal_extension_approved.' . $event->leadId . '.' . ($resellerId ?? md5($email)),
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
