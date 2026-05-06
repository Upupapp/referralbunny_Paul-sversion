<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\Plan;
use App\Models\Reseller;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DiagnoseReferrersCommand extends Command
{
    protected $signature = 'referralbunny:diagnose-referrers
                            {tenant : Tenant ID or slug}
                            {--repair : Clear caches (does not modify data)}';

    protected $description = 'Diagnose referrer/reseller count discrepancies for a tenant';

    public function handle(): int
    {
        $tenantArg = $this->argument('tenant');

        // ── Locate tenant ─────────────────────────────────────────────────────
        $tenant = Tenant::where('id', $tenantArg)
            ->orWhere('slug', $tenantArg)
            ->first();

        if (! $tenant) {
            $this->error("Tenant not found: {$tenantArg}");
            return self::FAILURE;
        }

        $tenantId = $tenant->id;
        $this->info("Diagnosing referrers for tenant: {$tenant->name} (id={$tenantId})");
        $this->line('');

        // ── Repair mode ───────────────────────────────────────────────────────
        if ($this->option('repair')) {
            Cache::forget("tenant_{$tenantId}_resellers_count");
            Cache::forget("tenant_{$tenantId}_plan");
            Cache::forget("tenant_{$tenantId}_subscription");
            $this->warn('[REPAIR] Caches cleared for this tenant. No data was modified.');
            $this->line('');
        }

        // ── Run diagnostic counts ─────────────────────────────────────────────

        // 1. Total reseller rows (no filter)
        $totalRows = Reseller::where('tenant_id', $tenantId)->count();

        // 2. Active resellers
        $activeCount = Reseller::where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'nda_signed'])
            ->count();

        // 3. Invited (pending)
        $invitedCount = Reseller::where('tenant_id', $tenantId)
            ->where('status', 'invited')
            ->count();

        // 4. Resellers with no password (setup incomplete)
        $noPasswordCount = Reseller::where('tenant_id', $tenantId)
            ->where('status', 'invited')
            ->whereNull('password')
            ->count();

        // 5. Resellers with no email
        $noEmailCount = Reseller::where('tenant_id', $tenantId)
            ->whereNull('email')
            ->count();

        // 6. Resellers with can_export_own_data = true
        $canExportCount = Reseller::where('tenant_id', $tenantId)
            ->where('can_export_own_data', true)
            ->count();

        // 7. Total leads with non-null reseller_name
        $leadsWithReferrerName = Lead::where('tenant_id', $tenantId)
            ->whereNotNull('reseller_name')
            ->count();

        // 8. Distinct reseller_name values from leads (provisional imported names)
        $distinctReferrerNames = Lead::where('tenant_id', $tenantId)
            ->whereNotNull('reseller_name')
            ->distinct('reseller_name')
            ->count('reseller_name');

        // 9-12. Subscription and plan limits
        $subscription = Subscription::where('tenant_id', $tenantId)->first();
        $planName     = 'N/A';
        $subStatus    = 'N/A';
        $maxResellers = 'N/A';

        if ($subscription) {
            $subStatus = $subscription->status;
            $plan      = $subscription->plan;
            if ($plan) {
                $planName     = $plan->name . ($plan->plan_key ? " ({$plan->plan_key})" : '');
                $limits       = is_array($plan->plan_limits_json) ? $plan->plan_limits_json : [];
                $maxResellers = array_key_exists('max_resellers', $limits)
                    ? (int) $limits['max_resellers']
                    : 'N/A';
            }
        }

        // 12. Current resellers count vs plan limit (eligible = not deleted/deactivated)
        $eligibleCount = Reseller::where('tenant_id', $tenantId)
            ->whereNotIn('status', ['deleted', 'deactivated'])
            ->count();

        $limitDisplay = $maxResellers === -1
            ? 'Unlimited'
            : (string) $maxResellers;

        $limitStatus = 'N/A';
        if (is_int($maxResellers)) {
            if ($maxResellers === -1) {
                $limitStatus = 'OK (unlimited)';
            } elseif ($eligibleCount >= $maxResellers) {
                $limitStatus = 'AT LIMIT or OVER';
            } else {
                $limitStatus = 'OK (' . ($maxResellers - $eligibleCount) . ' slots remaining)';
            }
        }

        // 13. API query (same as referrers tab API endpoint)
        $apiCount = Reseller::where('tenant_id', $tenantId)
            ->orderBy('performance_score', 'desc')
            ->get()
            ->count();

        // ── Output: Counts table ──────────────────────────────────────────────
        $this->info('=== REFERRER COUNTS ===');
        $this->table(
            ['#', 'Check', 'Value'],
            [
                ['1',  'Total reseller rows (all statuses)',               $totalRows],
                ['2',  'Active (status: active or nda_signed)',            $activeCount],
                ['3',  'Invited / pending',                                $invitedCount],
                ['4',  'Invited with no password (setup incomplete)',       $noPasswordCount],
                ['5',  'Resellers with no email',                          $noEmailCount],
                ['6',  'Resellers with can_export_own_data = true',        $canExportCount],
                ['7',  'Leads with non-null reseller_name',                $leadsWithReferrerName],
                ['8',  'Distinct reseller_name values on leads',           $distinctReferrerNames],
                ['9',  'Current subscription plan',                        $planName],
                ['10', 'Subscription status',                              $subStatus],
                ['11', 'Plan max_resellers limit',                         $limitDisplay],
                ['12', 'Eligible resellers vs limit',                      "{$eligibleCount} / {$limitDisplay} — {$limitStatus}"],
                ['13', 'API query count (all, ordered by performance)',     $apiCount],
                ['14', 'API has status filter?',                           'NO — returns all statuses'],
                ['15', 'Frontend default filter',                          'All statuses (Active filter chip also available)'],
            ]
        );

        // ── Mismatch analysis ─────────────────────────────────────────────────
        $this->line('');
        $this->info('=== MISMATCH ANALYSIS ===');

        if ($totalRows === 0) {
            $this->warn('No resellers found for this tenant at all.');
        } else {
            $this->line("  Total DB rows       : {$totalRows}");
            $this->line("  API returns         : {$apiCount}  (all rows, no status filter)");
            $this->line("  Dashboard shows     : {$apiCount}  (resellers.length from API response)");
            $this->line("  Tab 'All' shows     : {$apiCount}  (no frontend filter applied)");
            $this->line("  Tab 'Active' shows  : {$activeCount}  (frontend filters by status active/nda_signed)");
            $this->line('');

            if ($apiCount !== $totalRows) {
                $this->warn("  DISCREPANCY: API returned {$apiCount} but DB has {$totalRows} rows.");
                $this->warn('  Possible cause: query ordering/scope discarded rows (unlikely with simple orderBy).');
            } else {
                $this->line('  API count matches total DB rows. No backend filtering discrepancy detected.');
            }

            if ($invitedCount > 0) {
                $this->warn("  NOTE: {$invitedCount} resellers are in 'invited' status.");
                $this->line('  These appear in the All tab but NOT in the Active filter. This is expected behaviour.');
            }

            if ($noPasswordCount > 0) {
                $this->warn("  NOTE: {$noPasswordCount} invited resellers have no password set (setup not completed).");
                $this->line('  These accounts exist but the referrer has not completed onboarding.');
            }

            if ($noEmailCount > 0) {
                $this->warn("  NOTE: {$noEmailCount} resellers have no email address.");
                $this->line('  These may be manually created rows without a valid login.');
            }

            if ($distinctReferrerNames > 0) {
                $this->line('');
                $this->warn("  IMPORT DATA: {$distinctReferrerNames} distinct reseller_name values found on leads.");
                $this->line("  These are provisional referrer names from lead imports — they do NOT automatically");
                $this->line('  create Reseller accounts. A reseller row must be created separately.');
            }
        }

        // ── Plan limit warnings ───────────────────────────────────────────────
        $this->line('');
        $this->info('=== PLAN LIMIT STATUS ===');

        if (is_int($maxResellers) && $maxResellers !== -1 && $eligibleCount >= $maxResellers) {
            $this->error("  LIMIT REACHED: {$eligibleCount} eligible resellers >= plan limit of {$maxResellers}.");
            $this->error('  New referrer invitations will be BLOCKED until the plan is upgraded or resellers are deactivated.');
        } elseif (is_int($maxResellers) && $maxResellers !== -1) {
            $remaining = $maxResellers - $eligibleCount;
            $this->info("  Plan allows {$maxResellers} resellers. {$eligibleCount} currently eligible. {$remaining} slot(s) remaining.");
        } elseif ($maxResellers === -1) {
            $this->info('  Plan has unlimited resellers. No limit enforcement needed.');
        } else {
            $this->warn('  Could not determine plan limits. No active subscription or plan found.');
        }

        // ── Recommended actions ───────────────────────────────────────────────
        $this->line('');
        $this->info('=== RECOMMENDED ACTIONS ===');
        $hasRecommendations = false;

        if ($noPasswordCount > 0) {
            $this->line("  - {$noPasswordCount} referrer(s) have not completed setup. Resend invitation emails.");
            $hasRecommendations = true;
        }

        if ($noEmailCount > 0) {
            $this->line("  - {$noEmailCount} reseller(s) have no email. Review manually in the admin panel.");
            $hasRecommendations = true;
        }

        if (is_int($maxResellers) && $maxResellers !== -1 && $eligibleCount >= $maxResellers) {
            $this->line('  - Tenant is at plan limit. Upgrade plan or deactivate unused resellers to allow new invites.');
            $hasRecommendations = true;
        }

        if ($this->option('repair')) {
            $this->line('  - Cache was cleared. Re-run without --repair to verify counts are now fresh.');
            $hasRecommendations = true;
        }

        if (! $hasRecommendations) {
            $this->line('  No issues detected. Counts and limits look healthy.');
        }

        $this->line('');
        $this->info('Diagnosis complete.');

        return self::SUCCESS;
    }
}
