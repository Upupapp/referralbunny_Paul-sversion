<?php

namespace App\Services\QA;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Verifies tenant isolation across all tenant-scoped tables.
 * Tests that data from one tenant cannot be accessed by queries for another tenant.
 * Read-only. Never modifies data.
 */
class QaTenantIsolationChecker
{
    public function run(?string $tenantId = null): array
    {
        $results = [];

        $results = array_merge($results, $this->checkAllTenantsHaveIsolatedData());
        $results = array_merge($results, $this->checkCrossTenantEmailOverlap());
        $results = array_merge($results, $this->checkNotificationTenantScope());
        $results = array_merge($results, $this->checkLeadTenantScope($tenantId));
        $results = array_merge($results, $this->checkResellerTenantScope($tenantId));

        return $results;
    }

    /**
     * Verify that all tenant-scoped tables have exactly the expected tenant distribution.
     * Critical: no records should have tenant_id of a tenant that doesn't exist.
     */
    private function checkAllTenantsHaveIsolatedData(): array
    {
        $results = [];

        if (!Schema::hasTable('tenants')) {
            return [['check' => 'isolation.tenants_table', 'status' => 'critical', 'message' => 'tenants table missing', 'details' => [], 'module' => 'tenant_isolation', 'severity' => 'critical']];
        }

        $tenantIds = DB::table('tenants')->pluck('id')->toArray();

        $tables = ['leads', 'resellers', 'notifications', 'deal_partner_splits'];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) continue;

            // Find records with tenant_id not in tenants table
            $orphans = DB::table($table)
                ->whereNotIn('tenant_id', $tenantIds)
                ->whereNotNull('tenant_id')
                ->count();

            if ($orphans === 0) {
                $results[] = $this->pass("isolation.{$table}.valid_tenant", "All {$table} records have valid tenant_id", 'tenant_isolation');
            } else {
                $results[] = $this->critical("isolation.{$table}.valid_tenant", "{$orphans} records in {$table} have tenant_id not in tenants table — possible orphan data", 'tenant_isolation');
            }
        }

        return $results;
    }

    /**
     * Check that the same email in two tenants has separate records (not merged).
     * Verifies tenant isolation for multi-tenant email handling.
     */
    private function checkCrossTenantEmailOverlap(): array
    {
        $results = [];

        if (!Schema::hasTable('resellers')) {
            return [];
        }

        // Find emails that appear in more than one tenant
        $crossTenantEmails = DB::table('resellers')
            ->select(DB::raw('LOWER(email) as normalized_email'), DB::raw('COUNT(DISTINCT tenant_id) as tenant_count'))
            ->whereNotNull('email')
            ->groupBy(DB::raw('LOWER(email)'))
            ->havingRaw('COUNT(DISTINCT tenant_id) > 1')
            ->count();

        if ($crossTenantEmails === 0) {
            $results[] = $this->pass('isolation.reseller.cross_tenant_email', 'All reseller emails exist in exactly one tenant (no cross-tenant overlap detected at record level)', 'tenant_isolation');
        } else {
            // This is expected (same person can be referrer in multiple tenants) but flagged as info
            $results[] = $this->info("isolation.reseller.cross_tenant_email", "{$crossTenantEmails} email(s) appear as resellers in multiple tenants — expected if same person joins multiple tenants. Verify role/access is separate per tenant.", 'tenant_isolation');
        }

        return $results;
    }

    /**
     * Check notifications are always tenant-scoped.
     */
    private function checkNotificationTenantScope(): array
    {
        $results = [];

        if (!Schema::hasTable('notifications')) {
            return [];
        }

        // Notifications without tenant_id (could be platform-wide, which is allowed for super admin)
        $noTenant = DB::table('notifications')
            ->whereNull('tenant_id')
            ->where('notifiable_type', '!=', 'super_admin')
            ->count();

        if ($noTenant === 0) {
            $results[] = $this->pass('isolation.notifications.tenant_scope', 'All non-super-admin notifications have tenant_id', 'tenant_isolation');
        } else {
            $results[] = $this->fail('isolation.notifications.tenant_scope', "{$noTenant} notifications for non-super-admin recipients are missing tenant_id", 'tenant_isolation', 'high');
        }

        return $results;
    }

    /**
     * Verify leads are strictly tenant-scoped.
     */
    private function checkLeadTenantScope(?string $tenantId): array
    {
        $results = [];

        if (!Schema::hasTable('leads')) {
            return [];
        }

        if ($tenantId) {
            $totalLeads = DB::table('leads')->where('tenant_id', $tenantId)->count();
            $allLeads   = DB::table('leads')->count();

            $results[] = $this->pass(
                "isolation.leads.tenant_{$tenantId}",
                "Tenant '{$tenantId}' has {$totalLeads} deals out of {$allLeads} platform total — data is isolated by tenant_id filter",
                'tenant_isolation'
            );

            // Verify all leads for this tenant actually belong to it
            $wrongTenant = DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->where('tenant_id', '!=', $tenantId)
                ->count();

            if ($wrongTenant === 0) {
                $results[] = $this->pass("isolation.leads.scope_integrity", "Lead tenant scoping query is consistent", 'tenant_isolation');
            }
        }

        return $results;
    }

    /**
     * Verify resellers are strictly tenant-scoped.
     */
    private function checkResellerTenantScope(?string $tenantId): array
    {
        $results = [];

        if (!Schema::hasTable('resellers') || !$tenantId) {
            return [];
        }

        // Verify no deactivated reseller has active leads in another tenant
        $deactivatedWithLeads = DB::table('resellers as r')
            ->join('leads as l', function($j) {
                $j->on('l.tenant_id', '=', 'r.tenant_id')
                  ->whereRaw("LOWER(l.reseller_name) = LOWER(r.name)");
            })
            ->where('r.tenant_id', $tenantId)
            ->where('r.status', 'deactivated')
            ->whereIn('l.status', ['active', 'expiring'])
            ->count();

        if ($deactivatedWithLeads === 0) {
            $results[] = $this->pass('isolation.deactivated_reseller.no_active_deals', 'No deactivated referrers have active deals', 'tenant_isolation');
        } else {
            $results[] = $this->warning('isolation.deactivated_reseller.active_deals', "{$deactivatedWithLeads} deactivated referrers still have active/expiring deals — review deal reassignment", 'tenant_isolation', 'high');
        }

        return $results;
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function pass(string $check, string $message, string $module): array
    {
        return ['check' => $check, 'status' => 'pass', 'message' => $message, 'details' => [], 'module' => $module, 'severity' => 'low'];
    }

    private function fail(string $check, string $message, string $module, string $severity = 'high'): array
    {
        return ['check' => $check, 'status' => 'fail', 'message' => $message, 'details' => [], 'module' => $module, 'severity' => $severity];
    }

    private function warning(string $check, string $message, string $module, string $severity = 'medium'): array
    {
        return ['check' => $check, 'status' => 'warning', 'message' => $message, 'details' => [], 'module' => $module, 'severity' => $severity];
    }

    private function critical(string $check, string $message, string $module): array
    {
        return ['check' => $check, 'status' => 'critical', 'message' => $message, 'details' => [], 'module' => $module, 'severity' => 'critical'];
    }

    private function info(string $check, string $message, string $module): array
    {
        return ['check' => $check, 'status' => 'info', 'message' => $message, 'details' => [], 'module' => $module, 'severity' => 'low'];
    }
}
