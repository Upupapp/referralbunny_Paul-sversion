<?php

namespace App\Jobs\Emails;

use App\Mail\ResellerDailySummary;
use App\Services\EmailLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SendResellerDailySummariesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 30;

    public function handle(): void
    {
        // Active resellers with email and password (activated accounts only)
        $resellers = DB::table('resellers')
            ->whereNotNull('email')
            ->whereNotNull('password')
            ->whereIn('status', ['active', 'nda_signed'])
            ->get();

        // Batch-resolve tenant names to avoid one query per reseller
        $tenantNames = DB::table('tenants')
            ->whereIn('id', $resellers->pluck('tenant_id')->unique()->all())
            ->pluck('name', 'id')
            ->all();

        $today = now()->setTimezone('Asia/Manila');

        foreach ($resellers as $reseller) {
            $emailKey = "reseller_daily.{$reseller->id}";
            if (EmailLogger::sentToday($emailKey)) continue;

            $leads = DB::table('leads')
                ->where('tenant_id', $reseller->tenant_id)
                ->whereNull('deleted_at')
                ->where('reseller_name', $reseller->name)
                ->get();

            // Skip if no deals at all
            if ($leads->isEmpty()) continue;

            $expiringList = $leads->whereIn('status', ['expiring'])->map(fn($l) => [
                'name'     => $l->name,
                'days_left'=> $l->days_left ?? 0,
                'stage'    => $l->stage,
            ])->values()->toArray();

            $tenantName = $tenantNames[$reseller->tenant_id] ?? '';

            EmailLogger::send(
                mailable: new ResellerDailySummary(
                    resellerName:      $reseller->name,
                    resellerEmail:     $reseller->email,
                    tenantName:        $tenantName,
                    activeDeals:       $leads->whereIn('status', ['active', 'expiring'])->count(),
                    expiringDeals:     count($expiringList),
                    totalDeals:        $leads->count(),
                    pendingCommission: (float) $leads->where('commission_status', 'pending')->sum('deal_value'),
                    expiringList:      $expiringList,
                    dashboardUrl:      url("/reseller/{$reseller->tenant_id}/dashboard"),
                ),
                recipientEmail: $reseller->email,
                recipientType:  'reseller',
                recipientId:    $reseller->id,
                emailKey:       $emailKey,
                subject:        "Your daily referral summary — " . $today->format('M j'),
                tenantId:       $reseller->tenant_id,
                dailyDedup:     true,
            );
        }
    }
}
