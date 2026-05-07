<?php

namespace App\Console\Commands;

use App\Models\DealPartnerSplit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnosePartnerSplitsCommand extends Command
{
    protected $signature   = 'referralbunny:diagnose-partner-splits {tenant} {--repair-safe}';
    protected $description = 'Diagnose partner split allocations for a tenant';

    public function handle(): int
    {
        $tenantId   = $this->argument('tenant');
        $repairSafe = $this->option('repair-safe');

        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        if (!$tenant) { $this->error("Tenant '{$tenantId}' not found."); return 1; }

        $this->info("─────────────────────────────────────────────────────");
        $this->info(" Partner Split Diagnosis — {$tenant->name}");
        $this->info("─────────────────────────────────────────────────────");

        $splits = DealPartnerSplit::where('tenant_id', $tenantId)->whereNull('deleted_at')->get();

        $this->line(" Total splits     : " . $splits->count());
        $this->line(" Active           : " . $splits->where('status', 'active')->count());
        $this->line(" Pending invite   : " . $splits->where('status', 'pending_invite')->count());
        $this->line(" Provisional      : " . $splits->where('status', 'provisional')->count());
        $this->line(" Invite failed    : " . $splits->where('status', 'invite_failed')->count());

        $missingName  = $splits->filter(fn($s) => empty($s->partner_name))->count();
        $missingEmail = $splits->filter(fn($s) => empty($s->partner_email))->count();
        $missingLink  = $splits->filter(fn($s) => !$s->partner_user_id && !$s->partner_contact_id)->count();

        $this->newLine();
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line(" DATA INTEGRITY");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line(" Missing partner name  : {$missingName}");
        $this->line(" Missing partner email : {$missingEmail}");
        $this->line(" No user/contact link  : {$missingLink}");

        if ($repairSafe && $missingLink > 0) {
            $repaired = 0;
            foreach ($splits->filter(fn($s) => !$s->partner_user_id && !$s->partner_contact_id && $s->partner_email) as $split) {
                $contact = DB::table('contacts')
                    ->where('tenant_id', $tenantId)
                    ->whereRaw('LOWER(email) = ?', [strtolower($split->partner_email)])
                    ->value('id');
                if ($contact) {
                    $split->update(['partner_contact_id' => $contact]);
                    $repaired++;
                }
            }
            $this->info(" → Repaired {$repaired} splits linked to contacts.");
        }

        $this->newLine();
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line(" CROSS-TENANT LEAK CHECK");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $leaks = DealPartnerSplit::where('tenant_id', '!=', $tenantId)
            ->whereIn('partner_email', $splits->pluck('partner_email')->filter()->unique())
            ->count();
        $this->line($leaks > 0
            ? "  ⚠ {$leaks} email(s) exist in other tenants (normal — tenant isolation enforced by tenant_id)"
            : "  ✓ No cross-tenant concerns.");

        $this->newLine();
        $this->line(" Diagnosis complete.");
        return 0;
    }
}
