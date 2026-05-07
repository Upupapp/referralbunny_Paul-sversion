<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnosePendingDealParticipantsCommand extends Command
{
    protected $signature   = 'referralbunny:diagnose-pending-deal-participants {tenant}';
    protected $description = 'Diagnose deals with pending Referrers and Partners';

    public function handle(): int
    {
        $tenantId = $this->argument('tenant');
        $tenant   = DB::table('tenants')->where('id', $tenantId)->first();
        if (!$tenant) { $this->error("Tenant '{$tenantId}' not found."); return 1; }

        $this->info("─────────────────────────────────────────────────────");
        $this->info(" Pending Deal Participants — {$tenant->name}");
        $this->info("─────────────────────────────────────────────────────");

        // Deals with pending Referrers (reseller status = invited)
        $pendingReferrerDeals = DB::table('leads as l')
            ->join('resellers as r', function($j) use ($tenantId) {
                $j->on('l.tenant_id', '=', 'r.tenant_id')
                  ->whereRaw("LOWER(r.name) = LOWER(l.reseller_name)");
            })
            ->where('l.tenant_id', $tenantId)
            ->where('r.status', 'invited')
            ->select('l.id', 'l.name', 'l.reseller_name', 'r.email', 'r.status')
            ->get();

        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line(" DEALS WITH PENDING REFERRERS");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line(" Count: " . $pendingReferrerDeals->count());
        foreach ($pendingReferrerDeals->take(10) as $d) {
            $this->line("  📋 {$d->name} → Referrer: {$d->reseller_name} <{$d->email}> (invited)");
        }

        // Deals with pending Partner splits
        $pendingPartnerSplits = DB::table('deal_partner_splits')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['pending_invite', 'provisional'])
            ->whereNull('deleted_at')
            ->count();

        $this->newLine();
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line(" DEALS WITH PENDING PARTNER SPLITS");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line(" Pending/provisional partner splits: {$pendingPartnerSplits}");

        // Invite email failures
        $failedInvites = DB::table('resellers')
            ->where('tenant_id', $tenantId)
            ->where('status', 'invited')
            ->whereNull('invite_sent_at')
            ->count();

        $this->newLine();
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line(" INVITE STATUS");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line(" Referrer invites never sent   : {$failedInvites}");

        $this->newLine();
        $this->line(" Diagnosis complete.");
        return 0;
    }
}
