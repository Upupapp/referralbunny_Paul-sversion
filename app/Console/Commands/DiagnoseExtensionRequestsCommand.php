<?php

namespace App\Console\Commands;

use App\Models\DealAssignmentExtensionRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseExtensionRequestsCommand extends Command
{
    protected $signature   = 'referralbunny:diagnose-extension-requests {tenant}';
    protected $description = 'Diagnose deal assignment extension requests for a tenant';

    public function handle(): int
    {
        $tenantId = $this->argument('tenant');
        $tenant   = DB::table('tenants')->where('id', $tenantId)->first();
        if (!$tenant) { $this->error("Tenant '{$tenantId}' not found."); return 1; }

        $this->info("─────────────────────────────────────────────────────");
        $this->info(" Extension Request Diagnosis — {$tenant->name}");
        $this->info("─────────────────────────────────────────────────────");

        $all = DealAssignmentExtensionRequest::where('tenant_id', $tenantId)->get();

        $this->line(" Total requests         : " . $all->count());
        $this->line(" Pending review         : " . $all->where('status', 'pending_review')->count());
        $this->line(" Approved               : " . $all->where('status', 'approved')->count());
        $this->line(" Rejected               : " . $all->where('status', 'rejected')->count());
        $this->line(" Clarification requested: " . $all->where('status', 'clarification_requested')->count());
        $this->line(" Cancelled              : " . $all->where('status', 'cancelled')->count());

        // Deals expiring with no pending extension request
        $expiringNoRequest = DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->where('status', 'expiring')
            ->whereNotIn('id', $all->where('status', 'pending_review')->pluck('deal_id'))
            ->count();

        $this->newLine();
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line(" EXPIRY VS REQUEST HEALTH");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line(" Expiring deals without extension request : {$expiringNoRequest}");

        $expiredPending = DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->where('status', 'expired')
            ->whereIn('id', $all->where('status', 'pending_review')->pluck('deal_id'))
            ->count();
        $this->line(" Expired deals with pending request       : {$expiredPending}");
        if ($expiredPending > 0) {
            $this->warn("  ⚠ Review these — deals are already expired but requests are still pending.");
        }

        $this->newLine();
        $this->line(" Diagnosis complete.");
        return 0;
    }
}
