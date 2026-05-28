<?php

namespace App\Jobs\Emails;

use App\Mail\SuperAdminDailySummary;
use App\Services\EmailLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SendSuperAdminDailySummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 30;

    public function handle(): void
    {
        $since = now()->setTimezone('Asia/Manila')->subDay()->startOfDay();

        $stats = [
            'activeTenants' => DB::table('tenants')->where('status', '!=', 'suspended')->count(),
            'newDealsToday' => DB::table('leads')->where('created_at', '>=', $since)->count(),
            'expiringDeals' => DB::table('leads')->whereIn('status', ['expiring'])->count(),
            'newResellers'  => DB::table('resellers')->where('created_at', '>=', $since)->count(),
            'totalDeals'    => DB::table('leads')->count(),
        ];

        // Send to all super admins
        $superAdmins = DB::table('users')->get();
        foreach ($superAdmins as $admin) {
            $emailKey = "super_admin_daily.{$admin->email}";
            if (EmailLogger::sentToday($emailKey)) continue;

            EmailLogger::send(
                mailable: new SuperAdminDailySummary(
                    activeTenants: $stats['activeTenants'],
                    newDealsToday: $stats['newDealsToday'],
                    expiringDeals: $stats['expiringDeals'],
                    newResellers:  $stats['newResellers'],
                    totalDeals:    $stats['totalDeals'],
                    dashboardUrl:  url('/platform/dashboard'),
                ),
                recipientEmail: $admin->email,
                recipientType:  'super_admin',
                emailKey:       $emailKey,
                subject:        'ReferralBunny.ai Daily Platform Summary',
                dailyDedup:     true,
            );
        }
    }
}
