<?php

namespace App\Listeners;

use App\Events\DealAmountUpdated;
use App\Mail\ResellerDealAmountUpdated;
use App\Models\Reseller;
use App\Services\EmailLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandleDealAmountUpdated implements ShouldQueue
{
    public int $tries   = 3;
    public int $backoff = 10;

    public function handle(DealAmountUpdated $event): void
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
            if ($invitedStatus === 'invited') {
                $email      = null;
                $resellerId = null;
            }
        }

        if (!$email) return;

        $tenantName = DB::table('tenants')->where('id', $event->tenantId)->value('name') ?? $event->tenantId;

        EmailLogger::send(
            mailable: new ResellerDealAmountUpdated(
                resellerName:  $event->resellerName,
                resellerEmail: $email,
                tenantName:    $tenantName,
                dealName:      $event->leadName,
                oldAmount:     $event->oldAmount,
                newAmount:     $event->newAmount,
                updatedByName: $event->updatedByName ?? 'Admin',
                dealUrl:       url("/reseller/{$event->tenantId}/deals/{$event->leadId}"),
            ),
            recipientEmail: $email,
            recipientType:  'reseller',
            emailKey:       'deal_amount_updated.' . $event->leadId,
            subject:        "Deal amount updated: {$event->leadName}",
            recipientId:    $resellerId,
            tenantId:       $event->tenantId,
            dailyDedup:     true,
        );
    }

    public function failed(DealAmountUpdated $event, \Throwable $exception): void
    {
        Log::error('[HandleDealAmountUpdated] Failed', [
            'lead_id' => $event->leadId,
            'error'   => $exception->getMessage(),
        ]);
    }
}
