<?php

namespace App\Console\Commands;

use App\Models\Reseller;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseMultiroleReferrersCommand extends Command
{
    protected $signature   = 'referralbunny:diagnose-multirole-referrers {tenant}';
    protected $description = 'Diagnose multi-role Referrer configuration for a tenant';

    public function handle(): int
    {
        $tenantId = $this->argument('tenant');

        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        if (!$tenant) {
            $this->error("Tenant '{$tenantId}' not found.");
            return 1;
        }

        $this->info("─────────────────────────────────────────────────────");
        $this->info(" Multi-Role Referrer Diagnosis");
        $this->info("─────────────────────────────────────────────────────");
        $this->line(" Tenant ID  : {$tenantId}");
        $this->line(" Tenant Name: {$tenant->name}");
        $this->newLine();

        // 1. Active Referrers (reseller only, no tenant user link)
        $referrerOnly = Reseller::where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'nda_signed'])
            ->whereNull('linked_tenant_user_id')
            ->count();

        // 2. Admin/Manager users who also have Referrer role
        $adminReferrers = Reseller::where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'nda_signed'])
            ->whereNotNull('linked_tenant_user_id')
            ->get();

        // 3. Admin/Manager users WITHOUT Referrer role
        $adminsWithoutReferrer = DB::table('tenant_memberships as tm')
            ->join('tenant_users as u', 'tm.tenant_user_id', '=', 'u.id')
            ->where('tm.tenant_id', $tenantId)
            ->where('tm.status', 'active')
            ->whereIn('tm.role', ['owner', 'admin', 'manager'])
            ->whereNotExists(function ($q) use ($tenantId) {
                $q->from('resellers')
                  ->whereColumn(DB::raw('LOWER(resellers.email)'), DB::raw('LOWER(u.email)'))
                  ->where('resellers.tenant_id', $tenantId)
                  ->whereIn('resellers.status', ['active', 'nda_signed', 'invited']);
            })
            ->select('u.first_name', 'u.last_name', 'u.email', 'tm.role')
            ->get();

        // 4. Pending Referrer invitations
        $pendingCount = Reseller::where('tenant_id', $tenantId)
            ->where('status', 'invited')
            ->count();

        // 5. Deactivated Referrers
        $deactivatedCount = Reseller::where('tenant_id', $tenantId)
            ->where('status', 'deactivated')
            ->count();

        // 6. Activated Referrers shown in dropdown (what activatedOptions() returns)
        $dropdownCount = Reseller::where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'nda_signed'])
            ->count();

        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line(" REFERRER ROLE SUMMARY");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line(" Referrer-only (active/nda_signed)     : {$referrerOnly}");
        $this->line(" Admin/Manager + Referrer (multi-role) : " . $adminReferrers->count());
        $this->line(" Pending Referrer invitations           : {$pendingCount}");
        $this->line(" Deactivated Referrers                  : {$deactivatedCount}");
        $this->line(" Total shown in activated dropdown      : {$dropdownCount}");
        $this->newLine();

        if ($adminReferrers->isNotEmpty()) {
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->line(" MULTI-ROLE REFERRERS (Admin/Manager + Referrer)");
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            foreach ($adminReferrers as $r) {
                $tenantRole = DB::table('tenant_memberships')
                    ->where('tenant_user_id', $r->linked_tenant_user_id)
                    ->where('tenant_id', $tenantId)
                    ->value('role') ?? 'unknown';
                $this->line("  ✓ {$r->name} <{$r->email}>");
                $this->line("    Tenant Role: {$tenantRole} | Referrer Status: {$r->status}");
                $this->line("    Dropdown Label: \"{$r->name} — {$r->email} · Referrer · " . ucfirst($tenantRole) . "\"");
            }
            $this->newLine();
        }

        if ($adminsWithoutReferrer->isNotEmpty()) {
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->line(" ADMINS/MANAGERS WITHOUT REFERRER ROLE");
            $this->line(" (excluded from dropdown — no Referrer role assigned)");
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            foreach ($adminsWithoutReferrer as $u) {
                $name = trim("{$u->first_name} {$u->last_name}") ?: $u->email;
                $this->line("  ✗ {$name} <{$u->email}> · {$u->role}");
                $this->line("    Fix: POST /api/resellers/add-referrer-role/{tenant_user_id}");
            }
            $this->newLine();
        }

        // Cross-tenant leak check
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line(" TENANT ISOLATION CHECK");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        $crossTenantLeak = Reseller::where('tenant_id', '!=', $tenantId)
            ->whereIn('email', Reseller::where('tenant_id', $tenantId)->pluck('email'))
            ->count();

        if ($crossTenantLeak > 0) {
            $this->warn("  ⚠ {$crossTenantLeak} email(s) appear in OTHER tenants (expected, not a leak — tenant isolation is enforced by tenant_id)");
        } else {
            $this->line("  ✓ No cross-tenant concerns detected.");
        }

        $this->newLine();
        $this->line(" Diagnosis complete.");
        return 0;
    }
}
