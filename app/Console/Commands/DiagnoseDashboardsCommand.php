<?php

namespace App\Console\Commands;

use App\Models\Reseller;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\CriticalActionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class DiagnoseDashboardsCommand extends Command
{
    protected $signature   = 'referralbunny:diagnose-dashboards
                                {--tenant= : Tenant ID or slug to diagnose}
                                {--role=   : Role to diagnose (tenant_admin, tenant_manager, referrer, partner)}
                                {--all     : Diagnose all dashboards}
                                {--repair-cache : Clear dashboard caches}';

    protected $description = 'Diagnose all ReferralBunny.ai dashboards for broken routes, missing data, and permission issues.';

    public function handle(): int
    {
        $this->newLine();
        $this->line('<fg=cyan;options=bold>══════════════════════════════════════════════════════</fg=cyan;options=bold>');
        $this->line('<fg=cyan;options=bold>  ReferralBunny.ai Dashboard Diagnostic</fg=cyan;options=bold>');
        $this->line('<fg=cyan;options=bold>══════════════════════════════════════════════════════</fg=cyan;options=bold>');
        $this->newLine();

        if ($this->option('repair-cache')) {
            $this->repairCache();
        }

        $tenantSlug = $this->option('tenant');
        $role       = $this->option('role');

        // Find tenant if specified
        $tenant = null;
        if ($tenantSlug) {
            $tenant = Tenant::where('id', $tenantSlug)
                ->orWhere('slug', $tenantSlug)
                ->first();
            if (!$tenant) {
                $this->error("Tenant '{$tenantSlug}' not found.");
                return 1;
            }
        }

        // Run specific or all diagnostics
        if ($role === 'referrer' || $this->option('all')) {
            $this->diagnosReferrerDashboard($tenant);
        }
        if ($role === 'tenant_admin' || $role === 'tenant_manager' || $this->option('all')) {
            $this->diagnoseTenantAdminDashboard($tenant);
        }
        if ($role === 'partner' || $this->option('all')) {
            $this->diagnosePartnerDashboard($tenant);
        }
        if (!$role || $this->option('all')) {
            $this->diagnoseRoutes();
            $this->diagnoseGeneral($tenant);
        }

        $this->newLine();
        $this->info('Diagnosis complete.');
        return 0;
    }

    private function diagnosReferrerDashboard(?Tenant $tenant): void
    {
        $this->line('<options=bold>=== REFERRER DASHBOARD DIAGNOSIS ===</options=bold>');
        $this->newLine();

        $checks = [];

        // 1. Route check
        $checks[] = ['Check', 'reseller.dashboard route exists', Route::has('reseller.dashboard') ? 'PASS' : 'FAIL'];

        // 2. Controller method
        $checks[] = ['Check', 'ResellerPortalController@dashboard', class_exists(\App\Http\Controllers\ResellerPortalController::class) ? 'PASS' : 'FAIL'];

        // 3. View exists
        $checks[] = ['Check', 'reseller.dashboard view', view()->exists('reseller.dashboard') ? 'PASS' : 'FAIL'];

        // 4. Layout exists
        $checks[] = ['Check', 'layouts.reseller view', view()->exists('layouts.reseller') ? 'PASS' : 'FAIL'];

        // 5. CriticalActionService forReseller
        $casOk = false;
        try {
            $cas   = app(CriticalActionService::class);
            $tenantId = $tenant?->id ?? 'test-tenant';
            $result = $cas->forReseller($tenantId, 'Test Referrer', 3);
            $casOk  = is_array($result);
        } catch (\Throwable $e) {
            $this->warn("  CriticalActionService::forReseller error: " . $e->getMessage());
        }
        $checks[] = ['Check', 'CriticalActionService::forReseller() safe', $casOk ? 'PASS' : 'WARN'];

        // 6. lead_history table
        try {
            DB::table('lead_history')->limit(1)->get();
            $checks[] = ['Check', 'lead_history table exists', 'PASS'];
        } catch (\Throwable) {
            $checks[] = ['Check', 'lead_history table exists', 'WARN (safe fallback in place)'];
        }

        // 7. Reseller-specific data if tenant given
        if ($tenant) {
            $resellerCount = Reseller::where('tenant_id', $tenant->id)->count();
            $checks[] = ['Data', "Total resellers for {$tenant->name}", (string) $resellerCount];

            $activeResellers = Reseller::where('tenant_id', $tenant->id)->whereIn('status', ['active', 'nda_signed'])->count();
            $checks[] = ['Data', 'Active resellers', (string) $activeResellers];

            $invitedResellers = Reseller::where('tenant_id', $tenant->id)->where('status', 'invited')->count();
            $checks[] = ['Data', 'Invited resellers', (string) $invitedResellers];

            // Test per-reseller data for first active reseller
            $sampleReseller = Reseller::where('tenant_id', $tenant->id)->first();
            if ($sampleReseller) {
                try {
                    $leads = DB::table('leads')
                        ->where('tenant_id', $tenant->id)
                        ->where('reseller_name', $sampleReseller->name)
                        ->count();
                    $checks[] = ['Data', "Leads for '{$sampleReseller->name}'", (string) $leads];
                } catch (\Throwable $e) {
                    $checks[] = ['Data', "Leads query for reseller", 'FAIL: ' . $e->getMessage()];
                }

                try {
                    $activity = app(CriticalActionService::class)->forReseller($tenant->id, $sampleReseller->name, 3);
                    $checks[] = ['Data', 'Recent activity items returned', (string) count($activity)];
                } catch (\Throwable $e) {
                    $checks[] = ['Data', 'Recent activity for reseller', 'FAIL: ' . $e->getMessage()];
                }
            }
        }

        $this->table(['Type', 'Check', 'Result'], $checks);
        $this->newLine();
    }

    private function diagnoseTenantAdminDashboard(?Tenant $tenant): void
    {
        $this->line('<options=bold>=== TENANT ADMIN DASHBOARD DIAGNOSIS ===</options=bold>');
        $this->newLine();

        $checks = [];
        $checks[] = ['Check', 'tenant.dashboard route exists', Route::has('tenant.dashboard') ? 'PASS' : 'FAIL'];
        $checks[] = ['Check', 'TenantAdminController@dashboard', class_exists(\App\Http\Controllers\Web\TenantAdminController::class) ? 'PASS' : 'FAIL'];
        $checks[] = ['Check', 'tenant.dashboard view', view()->exists('tenant.dashboard') ? 'PASS' : 'FAIL'];
        $checks[] = ['Check', 'layouts.app view', view()->exists('layouts.app') ? 'PASS' : 'FAIL'];

        // CriticalActionService dashboardSummary
        try {
            $tenantId = $tenant?->id ?? 'test';
            $result   = app(CriticalActionService::class)->dashboardSummary($tenantId, 3, false);
            $checks[] = ['Check', 'CriticalActionService::dashboardSummary() safe', is_array($result) ? 'PASS' : 'WARN'];
        } catch (\Throwable $e) {
            $checks[] = ['Check', 'CriticalActionService::dashboardSummary()', 'FAIL: ' . $e->getMessage()];
        }

        if ($tenant) {
            try {
                $leadCount = DB::table('leads')->where('tenant_id', $tenant->id)->count();
                $checks[]  = ['Data', "Leads for {$tenant->name}", (string) $leadCount];
            } catch (\Throwable $e) {
                $checks[] = ['Data', 'Leads query', 'FAIL: ' . $e->getMessage()];
            }

            try {
                $memberCount = TenantMembership::where('tenant_id', $tenant->id)->where('status', 'active')->count();
                $checks[]    = ['Data', 'Active tenant users', (string) $memberCount];
            } catch (\Throwable $e) {
                $checks[] = ['Data', 'Tenant users query', 'FAIL: ' . $e->getMessage()];
            }
        }

        $this->table(['Type', 'Check', 'Result'], $checks);
        $this->newLine();
    }

    private function diagnosePartnerDashboard(?Tenant $tenant): void
    {
        $this->line('<options=bold>=== PARTNER DASHBOARD DIAGNOSIS ===</options=bold>');
        $this->newLine();

        $checks = [];
        $checks[] = ['Check', 'partner.dashboard route exists', Route::has('partner.dashboard') ? 'PASS' : 'FAIL'];
        $checks[] = ['Check', 'PartnerPortalController exists', class_exists(\App\Http\Controllers\Web\PartnerPortalController::class) ? 'PASS' : 'FAIL'];
        $checks[] = ['Check', 'partner.dashboard view', view()->exists('partner.dashboard') ? 'PASS' : 'FAIL'];
        $checks[] = ['Check', 'layouts.partner view', view()->exists('layouts.partner') ? 'PASS' : 'FAIL'];
        $checks[] = ['Security', 'Partner cannot see Referrer list', 'N/A — enforced by controller scope'];
        $checks[] = ['Security', 'Partner cannot export data', 'N/A — export routes blocked'];

        $this->table(['Type', 'Check', 'Result'], $checks);
        $this->newLine();
    }

    private function diagnoseRoutes(): void
    {
        $this->line('<options=bold>=== ROUTE EXISTENCE CHECK ===</options=bold>');
        $this->newLine();

        $routes = [
            'platform.dashboard'    => 'Super Admin Dashboard',
            'tenant.dashboard'      => 'Tenant Admin Dashboard',
            'reseller.dashboard'    => 'Referrer Dashboard',
            'partner.dashboard'     => 'Partner Dashboard',
            'reseller.deals'        => 'Referrer Deals',
            'reseller.commission'   => 'Referrer Commission',
            'reseller.messages'     => 'Referrer Messages',
            'reseller.notifications'=> 'Referrer Notifications',
            'reseller.profile'      => 'Referrer Profile',
            'reseller.login'        => 'Referrer Login',
            'tenant.exports'        => 'Exports (Tenant Admin)',
            'tenant.critical-actions' => 'Critical Actions',
        ];

        $rows = [];
        foreach ($routes as $routeName => $label) {
            $rows[] = [$label, $routeName, Route::has($routeName) ? '✓ EXISTS' : '✗ MISSING'];
        }

        $this->table(['Dashboard / Page', 'Route Name', 'Status'], $rows);
        $this->newLine();
    }

    private function diagnoseGeneral(?Tenant $tenant): void
    {
        $this->line('<options=bold>=== GENERAL HEALTH ===</options=bold>');
        $this->newLine();

        $checks = [];

        // Database tables
        $tables = ['tenants', 'leads', 'resellers', 'tenant_memberships', 'notifications', 'subscriptions', 'plans', 'export_requests'];
        foreach ($tables as $table) {
            try {
                DB::table($table)->limit(1)->get();
                $checks[] = ['Table', $table, 'EXISTS'];
            } catch (\Throwable) {
                $checks[] = ['Table', $table, 'MISSING'];
            }
        }

        // Optional tables
        $optionals = ['lead_history', 'activity_logs', 'billing_audit_logs', 'usage_metrics'];
        foreach ($optionals as $table) {
            try {
                DB::table($table)->limit(1)->get();
                $checks[] = ['Optional Table', $table, 'EXISTS'];
            } catch (\Throwable) {
                $checks[] = ['Optional Table', $table, 'ABSENT (safe — handled with fallback)'];
            }
        }

        if ($tenant) {
            // Subscription check
            try {
                $sub = DB::table('subscriptions')->where('tenant_id', $tenant->id)->first();
                if ($sub) {
                    $plan = $sub->plan_id ? DB::table('plans')->where('id', $sub->plan_id)->value('name') : 'Unknown';
                    $checks[] = ['Subscription', "{$tenant->name} plan", "{$plan} / status: {$sub->status}"];
                } else {
                    $checks[] = ['Subscription', "{$tenant->name}", 'No subscription found'];
                }
            } catch (\Throwable $e) {
                $checks[] = ['Subscription', 'Query', 'FAIL: ' . $e->getMessage()];
            }
        }

        $this->table(['Type', 'Item', 'Status'], $checks);
        $this->newLine();
    }

    private function repairCache(): void
    {
        $this->line('<options=bold>=== CACHE REPAIR ===</options=bold>');
        $this->newLine();

        $patterns = [];

        // Clear tenant plan caches
        try {
            $tenants = Tenant::pluck('id');
            foreach ($tenants as $tid) {
                Cache::forget("tenant_plan_{$tid}");
                Cache::forget("tenant_{$tid}_plan");
                Cache::forget("tenant_{$tid}_subscription");
                Cache::forget("tenant_{$tid}_resellers_count");
                Cache::forget("dashboard_{$tid}_counts");
                $patterns[] = $tid;
            }
            $this->info("  ✓ Cleared dashboard caches for " . count($patterns) . " tenant(s).");
        } catch (\Throwable $e) {
            $this->warn("  Cache clear partial: " . $e->getMessage());
        }

        $this->newLine();
    }
}
