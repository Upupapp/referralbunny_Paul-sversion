<?php

namespace App\Jobs\Emails;

use App\Mail\TenantAdminDailyBriefing;
use App\Services\EmailLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendTenantAdminDailyBriefingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $today    = now()->setTimezone('Asia/Manila')->format('Y-m-d');
        $since    = now()->setTimezone('Asia/Manila')->subDay()->startOfDay();

        // Get all active tenants
        $tenants = DB::table('tenants')->where('status', '!=', 'suspended')->get();

        foreach ($tenants as $tenant) {
            $tenantId = $tenant->id;

            // Build data
            $allLeads     = DB::table('leads')->where('tenant_id', $tenantId)->whereNull('deleted_at')->get();
            $expiringList = $allLeads->whereIn('status', ['expiring'])->map(fn($l) => [
                'name'     => $l->name,
                'days_left'=> $l->days_left ?? 0,
                'stage'    => $l->stage,
                'reseller' => $l->reseller_name,
            ])->values()->toArray();
            $newDealsList = $allLeads->where('created_at', '>=', $since->toDateTimeString())->map(fn($l) => [
                'name'     => $l->name,
                'stage'    => $l->stage,
                'reseller' => $l->reseller_name,
            ])->values()->toArray();

            // Skip if nothing to report
            if (count($expiringList) === 0 && count($newDealsList) === 0) continue;

            // Get tenant admins
            $admins = DB::table('tenant_memberships as tm')
                ->join('tenant_users as u', 'tm.tenant_user_id', '=', 'u.id')
                ->where('tm.tenant_id', $tenantId)
                ->whereIn('tm.role', ['owner', 'admin'])
                ->where('tm.status', 'active')
                ->select('u.email', 'u.first_name', 'u.last_name')
                ->get();

            foreach ($admins as $admin) {
                $emailKey = "tenant_daily_briefing.{$tenantId}.{$admin->email}";
                if (EmailLogger::sentToday($emailKey)) continue;

                EmailLogger::send(
                    mailable: new TenantAdminDailyBriefing(
                        adminName:    trim("{$admin->first_name} {$admin->last_name}"),
                        tenantName:   $tenant->name,
                        expiringDeals: count($expiringList),
                        newDeals:     count($newDealsList),
                        newResellers: DB::table('resellers')->where('tenant_id', $tenantId)->whereNull('deleted_at')->whereNotIn('status', ['deactivated'])->where('created_at', '>=', $since)->count(),
                        activeDeals:  $allLeads->whereIn('status', ['active', 'expiring'])->count(),
                        expiringList: $expiringList,
                        newDealsList: $newDealsList,
                        dashboardUrl: url("/tenant/{$tenantId}/dashboard"),
                    ),
                    recipientEmail: $admin->email,
                    recipientType:  'tenant_admin',
                    emailKey:       $emailKey,
                    subject:        "Your daily {$tenant->name} referral briefing",
                    tenantId:       $tenantId,
                    dailyDedup:     true,
                );
            }
        }
    }
}
