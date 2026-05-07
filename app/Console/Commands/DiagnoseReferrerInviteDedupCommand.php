<?php

namespace App\Console\Commands;

use App\Models\Reseller;
use App\Services\ReferrerInvitationDeduplicationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseReferrerInviteDedupCommand extends Command
{
    protected $signature   = 'referralbunny:diagnose-referrer-invite-dedup {tenant} {--repair-safe}';
    protected $description = 'Diagnose Referrer invitation deduplication status for a tenant';

    public function handle(): int
    {
        $tenantId  = $this->argument('tenant');
        $repairSafe = $this->option('repair-safe');

        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        if (!$tenant) {
            $this->error("Tenant '{$tenantId}' not found.");
            return 1;
        }

        $this->info("─────────────────────────────────────────────────────");
        $this->info(" Referrer Invite Deduplication Diagnosis");
        $this->info("─────────────────────────────────────────────────────");
        $this->line(" Tenant ID  : {$tenantId}");
        $this->line(" Tenant Name: {$tenant->name}");
        $this->newLine();

        $dedup = app(ReferrerInvitationDeduplicationService::class);

        // 1. Pending invitations
        $pending = Reseller::where('tenant_id', $tenantId)
            ->where('status', 'invited')
            ->get();

        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line(" PENDING REFERRER INVITATIONS");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line(" Total pending: " . $pending->count());
        $this->newLine();

        foreach ($pending as $r) {
            $dealCount    = $r->invite_deal_count ?? 0;
            $lastSent     = $r->invite_sent_at ? $r->invite_sent_at->diffForHumans() : 'never';
            $canResend    = $dedup->shouldSendEmail($r);
            $this->line("  📧 {$r->name} <{$r->email}>");
            $this->line("     Deals in summary: {$dealCount} | Last email: {$lastSent} | Can resend: " . ($canResend ? 'YES' : 'NO (within 24h)'));
        }

        // 2. Duplicate email detection (same email more than once — should not happen with dedup)
        $this->newLine();
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line(" DUPLICATE EMAIL DETECTION");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        $duplicateEmails = DB::table('resellers')
            ->where('tenant_id', $tenantId)
            ->select(DB::raw('LOWER(email) as normalized_email'), DB::raw('COUNT(*) as cnt'))
            ->groupBy(DB::raw('LOWER(email)'))
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicateEmails->isEmpty()) {
            $this->line("  ✓ No duplicate emails detected in resellers table.");
        } else {
            $this->warn("  ⚠ Duplicate emails found:");
            foreach ($duplicateEmails as $row) {
                $this->warn("    {$row->normalized_email} appears {$row->cnt} times");
                if ($repairSafe) {
                    $this->mergeOrphanDuplicates($tenantId, $row->normalized_email);
                } else {
                    $this->line("    → Run with --repair-safe to merge orphan duplicates");
                }
            }
        }

        // 3. Active Admin/Manager Referrers (should not receive setup invites)
        $this->newLine();
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line(" ADMIN/MANAGER REFERRERS (no setup email needed)");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        $linkedResellers = Reseller::where('tenant_id', $tenantId)
            ->whereNotNull('linked_tenant_user_id')
            ->get();

        if ($linkedResellers->isEmpty()) {
            $this->line("  None. No Admin/Manager users have been added as Referrers yet.");
        } else {
            foreach ($linkedResellers as $r) {
                $this->line("  ✓ {$r->name} <{$r->email}> · status: {$r->status}");
                if ($r->status === 'invited' && $r->setup_token) {
                    $this->warn("    ⚠ Has setup_token but should be 'active' (linked user already has access)");
                    if ($repairSafe) {
                        $r->update(['status' => 'active', 'setup_token' => null]);
                        $this->info("    → Repaired: status set to active, setup_token cleared.");
                    }
                }
            }
        }

        // 4. Invite throttle status
        $this->newLine();
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line(" INVITE EMAIL THROTTLE STATUS (24h window)");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        $recentlySent = Reseller::where('tenant_id', $tenantId)
            ->where('status', 'invited')
            ->whereNotNull('invite_sent_at')
            ->where('invite_sent_at', '>=', now()->subHours(24))
            ->get();

        if ($recentlySent->isEmpty()) {
            $this->line("  No invite emails sent within last 24h. All eligible for resend.");
        } else {
            $this->line("  Within throttle window (will NOT auto-resend):");
            foreach ($recentlySent as $r) {
                $this->line("  ⏳ {$r->email} — last sent: {$r->invite_sent_at->diffForHumans()}");
            }
        }

        // 5. Invitations with multiple deals summarized
        $this->newLine();
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line(" INVITATIONS WITH MULTIPLE DEALS (summarized)");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        $multiDeal = Reseller::where('tenant_id', $tenantId)
            ->where('invite_deal_count', '>', 1)
            ->get();

        if ($multiDeal->isEmpty()) {
            $this->line("  No multi-deal summarized invitations.");
        } else {
            foreach ($multiDeal as $r) {
                $this->line("  📋 {$r->name} <{$r->email}> → {$r->invite_deal_count} deals in summary");
            }
        }

        $this->newLine();
        $this->line(" Diagnosis complete." . ($repairSafe ? " (Repair-safe mode was ON)" : ""));
        return 0;
    }

    private function mergeOrphanDuplicates(string $tenantId, string $normalizedEmail): void
    {
        $rows = Reseller::where('tenant_id', $tenantId)
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'nda_signed' THEN 1 WHEN 'invited' THEN 2 ELSE 3 END")
            ->get();

        if ($rows->count() <= 1) {
            return;
        }

        $primary    = $rows->first();
        $duplicates = $rows->slice(1);

        // Merge deal IDs
        $allDealIds = $primary->invite_deal_ids ?? [];
        foreach ($duplicates as $dup) {
            $allDealIds = array_unique(array_merge($allDealIds, $dup->invite_deal_ids ?? []));
        }

        $primary->update([
            'invite_deal_ids'   => array_values($allDealIds),
            'invite_deal_count' => count($allDealIds),
        ]);

        // Delete duplicates (only orphan ones with no password set — safe to remove)
        foreach ($duplicates as $dup) {
            if (empty($dup->password)) {
                $dup->delete();
                $this->info("    → Merged and removed orphan duplicate: {$dup->id}");
            } else {
                $this->warn("    → Skipped duplicate with password set: {$dup->id} (manual review needed)");
            }
        }
    }
}
