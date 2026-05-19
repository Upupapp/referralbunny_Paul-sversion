<?php

namespace App\Listeners;

use App\Events\DealStageMoved;
use App\Mail\ResellerDealStageMoved;
use App\Models\Reseller;
use App\Services\EmailLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandleDealStageMoved implements ShouldQueue
{
    public int $tries = 3;

    public function handle(DealStageMoved $event): void
    {
        $email      = $event->resellerEmail;
        $resellerId = $event->resellerId;

        if (!$email || !$resellerId) {
            $reseller   = Reseller::where('tenant_id', $event->tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower($event->resellerName)])
                ->first();
            $email      = $email      ?? $reseller?->email;
            $resellerId = $resellerId ?? ($reseller ? (string) $reseller->id : null);

            // Skip pending/invited resellers
            if ($reseller && $reseller->status === 'invited') return;
        }

        if (!$email) return;

        $tenantName = DB::table('tenants')->where('id', $event->tenantId)->value('name') ?? $event->tenantId;

        EmailLogger::send(
            mailable: new ResellerDealStageMoved(
                resellerName:  $event->resellerName,
                resellerEmail: $email,
                tenantName:    $tenantName,
                dealName:      $event->leadName,
                fromStage:     $event->fromStage,
                toStage:       $event->toStage,
                dealValue:     $event->dealValue,
                movedByName:   $event->movedByName ?? 'Admin',
                dealUrl:       url("/reseller/{$event->tenantId}/deals/{$event->leadId}"),
            ),
            recipientEmail: $email,
            recipientType:  'reseller',
            emailKey:       'deal_stage_moved.' . $event->leadId . '.' . $event->toStage,
            subject:        "Deal stage updated: {$event->leadName} → " . ucfirst(str_replace('_', ' ', $event->toStage)),
            recipientId:    $resellerId,
            tenantId:       $event->tenantId,
            dailyDedup:     false,
        );
    }

    public function failed(DealStageMoved $event, \Throwable $exception): void
    {
        Log::error('[HandleDealStageMoved] Failed', [
            'lead_id' => $event->leadId,
            'error'   => $exception->getMessage(),
        ]);
    }
}
