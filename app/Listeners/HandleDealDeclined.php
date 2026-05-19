<?php

namespace App\Listeners;

use App\Events\DealDeclined;
use App\Mail\ResellerDealDeclined;
use App\Models\Reseller;
use App\Services\EmailLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandleDealDeclined implements ShouldQueue
{
    public int $tries = 3;

    public function handle(DealDeclined $event): void
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
            mailable: new ResellerDealDeclined(
                resellerName:   $event->resellerName,
                resellerEmail:  $email,
                tenantName:     $tenantName,
                dealName:       $event->leadName,
                stage:          $event->stage,
                dealValue:      $event->dealValue,
                declinedByName: $event->declinedByName ?? 'Admin',
                dashboardUrl:   url("/reseller/{$event->tenantId}/deals"),
            ),
            recipientEmail: $email,
            recipientType:  'reseller',
            emailKey:       'deal_declined.' . $event->leadId . '.' . now()->format('Ymd'),
            subject:        "Deal update — {$event->leadName} has been declined",
            recipientId:    $resellerId,
            tenantId:       $event->tenantId,
            dailyDedup:     false,
        );
    }

    public function failed(DealDeclined $event, \Throwable $exception): void
    {
        Log::error('[HandleDealDeclined] Failed', [
            'lead_id' => $event->leadId,
            'error'   => $exception->getMessage(),
        ]);
    }
}
