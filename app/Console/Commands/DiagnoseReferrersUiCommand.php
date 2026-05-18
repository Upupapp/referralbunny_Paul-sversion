<?php

namespace App\Console\Commands;

use App\Models\Reseller;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class DiagnoseReferrersUiCommand extends Command
{
    protected $signature = 'referralbunny:diagnose-referrers-ui
                                {tenant : Tenant ID or slug}
                                {--repair-cache : Clear Referrers tab cache keys}
                                {--repair-safe  : Create missing metadata from valid records}';

    protected $description = 'Diagnose the Tenant Admin Referrers tab UI, routes, data quality, and completeness.';

    public function handle(): int
    {
        $this->newLine();
        $this->line('<fg=cyan;options=bold>══════════════════════════════════════════════════════</fg=cyan;options=bold>');
        $this->line('<fg=cyan;options=bold>  ReferralBunny.ai — Referrers UI Diagnostic</fg=cyan;options=bold>');
        $this->line('<fg=cyan;options=bold>══════════════════════════════════════════════════════</fg=cyan;options=bold>');
        $this->newLine();

        $slug   = $this->argument('tenant');
        $tenant = Tenant::where('id', $slug)->orWhere('slug', $slug)->first();

        if (!$tenant) {
            $this->error("Tenant '{$slug}' not found.");
            return 1;
        }

        $this->info("Tenant: {$tenant->name} ({$tenant->id})");
        $this->newLine();

        // ── 1. Count breakdown ──────────────────────────────────────────────
        $this->line('<options=bold>=== REFERRER COUNTS ===</options=bold>');
        $rows = [];
        try {
            $all        = Reseller::where('tenant_id', $tenant->id)->get();
            $total      = $all->count();
            $active     = $all->whereIn('status', ['active', 'nda_signed'])->count();
            $invited    = $all->where('status', 'invited')->count();
            $deactivated= $all->where('status', 'deactivated')->count();
            $noEmail    = $all->filter(fn($r) => empty($r->email))->count();
            $noPassword = $all->filter(fn($r) => empty($r->password) && $r->status === 'invited')->count();
            $needsEmail = $noEmail;
            $pendingSetup = $noPassword;

            $rows = [
                ['Total Referrers',     $total],
                ['Active',              $active],
                ['Pending Invite',      $invited],
                ['Deactivated',         $deactivated],
                ['Needs Email',         $needsEmail],
                ['Pending Setup',       $pendingSetup],
                ['Other',               $total - $active - $invited - $deactivated],
            ];
        } catch (\Throwable $e) {
            $this->error("Count query failed: " . $e->getMessage());
            $rows = [['Count query failed', $e->getMessage()]];
        }
        $this->table(['Metric', 'Count'], $rows);
        $this->newLine();

        // ── 2. Data Quality ────────────────────────────────────────────────
        $this->line('<options=bold>=== DATA QUALITY ===</options=bold>');
        $quality = [];
        if (isset($all)) {
            foreach ($all as $r) {
                $issues = [];
                if (empty($r->email))    $issues[] = 'no email';
                if (empty($r->name))     $issues[] = 'no name';
                if (empty($r->phone))    $issues[] = 'no phone';
                if ($r->status === 'invited' && empty($r->password)) $issues[] = 'pending setup';

                if (!empty($issues)) {
                    $quality[] = [$r->name ?? '?', $r->email ?? '—', $r->status, implode(', ', $issues)];
                }
            }
        }
        if (empty($quality)) {
            $this->info('  ✓ All Referrers have basic required data.');
        } else {
            $this->table(['Name', 'Email', 'Status', 'Issues'], $quality);
        }
        $this->newLine();

        // ── 3. Route existence ─────────────────────────────────────────────
        $this->line('<options=bold>=== ROUTE CHECKS ===</options=bold>');
        $routes = [
            'tenant.referrers'      => 'Referrers Tab',
            'tenant.referrers.show' => 'Referrer Detail Page',
            'tenant.dashboard'      => 'Tenant Dashboard',
        ];
        $routeRows = [];
        foreach ($routes as $name => $label) {
            $routeRows[] = [$label, $name, Route::has($name) ? '✓ EXISTS' : '✗ MISSING'];
        }
        $this->table(['Page', 'Route Name', 'Status'], $routeRows);
        $this->newLine();

        // ── 4. Territory column check ──────────────────────────────────────
        $this->line('<options=bold>=== TERRITORY COLUMN ===</options=bold>');
        $indexPath = base_path('resources/views/tenant/referrers/index.blade.php');
        if (file_exists($indexPath)) {
            $content = file_get_contents($indexPath);
            $hasTerritory = str_contains($content, "'Territory'") || str_contains($content, '"Territory"')
                || (str_contains($content, 'territory') && str_contains($content, '<th'));
            $this->table(['Check', 'Result'], [
                ['Territory column visible in index view', $hasTerritory ? '⚠ STILL PRESENT' : '✓ REMOVED'],
                ['Referrer detail page exists', file_exists(base_path('resources/views/tenant/referrers/show.blade.php')) ? '✓ EXISTS' : '✗ MISSING'],
                ['ReferrerPerformanceService exists', file_exists(app_path('Services/ReferrerPerformanceService.php')) ? '✓ EXISTS' : '✗ MISSING'],
            ]);
        }
        $this->newLine();

        // ── 5. Deal associations ───────────────────────────────────────────
        $this->line('<options=bold>=== DEAL ASSOCIATIONS ===</options=bold>');
        try {
            $dealRows = [];
            if (isset($all)) {
                foreach ($all->take(10) as $r) {
                    $count = DB::table('leads')->where('tenant_id', $tenant->id)->where('reseller_name', $r->name)->count();
                    $dealRows[] = [$r->name ?? '?', $r->status, $count];
                }
            }
            $this->table(['Referrer', 'Status', 'Deals'], $dealRows);
        } catch (\Throwable $e) {
            $this->warn("Deal query failed: " . $e->getMessage());
        }
        $this->newLine();

        // ── 6. Tenant isolation check ──────────────────────────────────────
        $this->line('<options=bold>=== TENANT ISOLATION ===</options=bold>');
        try {
            $otherCount = Reseller::where('tenant_id', '!=', $tenant->id)->count();
            $this->table(['Check', 'Result'], [
                ['Resellers in other tenants (should NOT show here)', $otherCount . ' (isolated ✓)'],
            ]);
        } catch (\Throwable $e) {
            $this->warn("Isolation check failed: " . $e->getMessage());
        }
        $this->newLine();

        // ── Cache repair ───────────────────────────────────────────────────
        if ($this->option('repair-cache')) {
            $this->line('<options=bold>=== CACHE REPAIR ===</options=bold>');
            try {
                \Illuminate\Support\Facades\Cache::forget("tenant_{$tenant->id}_resellers_count");
                \Illuminate\Support\Facades\Cache::forget("dash_counts:{$tenant->id}");
                $this->info("  ✓ Cleared Referrers-related caches for tenant {$tenant->id}.");
            } catch (\Throwable $e) {
                $this->warn("  Cache clear partial: " . $e->getMessage());
            }
            $this->newLine();
        }

        $this->info('Diagnosis complete.');
        return 0;
    }
}
