<?php

namespace App\Services\QA;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Verifies LGU IDS protected rules remain unchanged.
 * These are locked business rules that must never be altered.
 *
 * Protected rules:
 * - Stage limits: introduction=14, presentation=21, contract_sent=30, signed=30
 * - Default deal value placeholder: ₱4,000,000
 * - One deal per organization rule in LeadController
 * - Commission split is referrer-based (not partner)
 */
class QaLguIdsChecker
{
    const TENANT_ID = 'lgu-ids';

    const EXPECTED_STAGE_LIMITS = [
        'introduction'  => 14,
        'presentation'  => 21,
        'contract_sent' => 30,
        'signed'        => 30,
    ];

    public function run(): array
    {
        $results = [];

        $results = array_merge($results, $this->checkTenantExists());
        $results = array_merge($results, $this->checkStageLimits());
        $results = array_merge($results, $this->checkLeadControllerRules());
        $results = array_merge($results, $this->checkCommissionModel());
        $results = array_merge($results, $this->checkPricingService());
        $results = array_merge($results, $this->checkImportTemplate());

        return $results;
    }

    private function checkTenantExists(): array
    {
        $results = [];

        if (!Schema::hasTable('tenants')) {
            return [['check' => 'lguids.tenant_exists', 'status' => 'critical', 'message' => 'tenants table missing', 'details' => [], 'module' => 'lgu_ids', 'severity' => 'critical']];
        }

        $tenant = DB::table('tenants')->where('id', self::TENANT_ID)->first();

        if ($tenant) {
            $results[] = $this->pass('lguids.tenant_exists', "LGU IDS tenant exists (id: " . self::TENANT_ID . ")", 'lgu_ids');
        } else {
            $results[] = $this->warning('lguids.tenant_exists', "LGU IDS tenant not found in tenants table — may not be seeded yet", 'lgu_ids');
        }

        return $results;
    }

    private function checkStageLimits(): array
    {
        $results = [];

        if (!Schema::hasTable('tenant_pipeline_stage_rules')) {
            $results[] = $this->warning('lguids.stage_rules.table', 'tenant_pipeline_stage_rules table not found — cannot verify stage limits', 'lgu_ids');
            return $results;
        }

        foreach (self::EXPECTED_STAGE_LIMITS as $stage => $expectedDays) {
            $rule = DB::table('tenant_pipeline_stage_rules')
                ->where('tenant_id', self::TENANT_ID)
                ->where('stage', $stage)
                ->first();

            if (!$rule) {
                $results[] = $this->fail("lguids.stage_rule.{$stage}", "LGU IDS stage rule for '{$stage}' NOT FOUND — protected rule may have been deleted", 'lgu_ids', 'critical');
                continue;
            }

            if ((int) $rule->max_days === $expectedDays) {
                $results[] = $this->pass("lguids.stage_rule.{$stage}", "Stage '{$stage}' max_days = {$expectedDays} ✓ (unchanged)", 'lgu_ids');
            } else {
                $results[] = $this->critical(
                    "lguids.stage_rule.{$stage}",
                    "PROTECTED RULE CHANGED: Stage '{$stage}' max_days = {$rule->max_days} (expected {$expectedDays}). DO NOT ship without review.",
                    'lgu_ids',
                    ['expected' => $expectedDays, 'found' => $rule->max_days]
                );
            }
        }

        return $results;
    }

    private function checkLeadControllerRules(): array
    {
        $results = [];

        $controllerPath = app_path('Http/Controllers/LeadController.php');

        if (!file_exists($controllerPath)) {
            $results[] = $this->fail('lguids.lead_controller.exists', 'LeadController.php not found', 'lgu_ids', 'critical');
            return $results;
        }

        $content = file_get_contents($controllerPath);

        // Check: one-deal-per-organization rule
        if (str_contains($content, 'ORG_ALREADY_CLAIMED') || str_contains($content, 'organization_id') && str_contains($content, 'lgu-ids')) {
            $results[] = $this->pass('lguids.lead_controller.one_org_rule', 'One-deal-per-organization rule is present in LeadController', 'lgu_ids');
        } else {
            $results[] = $this->critical('lguids.lead_controller.one_org_rule', 'One-deal-per-organization rule may be MISSING from LeadController — verify immediately', 'lgu_ids');
        }

        // Check: stage rule reset
        if (str_contains($content, 'tenant_pipeline_stage_rules') && str_contains($content, 'lgu-ids')) {
            $results[] = $this->pass('lguids.lead_controller.stage_reset', 'Stage-based days_left reset rule is present in LeadController', 'lgu_ids');
        } else {
            $results[] = $this->warning('lguids.lead_controller.stage_reset', 'Stage-based days_left reset may be missing from LeadController', 'lgu_ids');
        }

        // Check: default deal value 4M
        if (str_contains($content, '4_000_000') || str_contains($content, '4000000')) {
            $results[] = $this->pass('lguids.lead_controller.default_deal_value', 'Default ₱4,000,000 deal value rule is present', 'lgu_ids');
        } else {
            $results[] = $this->warning('lguids.lead_controller.default_deal_value', 'Default ₱4,000,000 deal value rule not found in LeadController', 'lgu_ids');
        }

        // Verify LGU IDS lock comments are still present
        if (str_contains($content, 'LOCKED RULE') || str_contains($content, 'LGU IDS pipeline protection')) {
            $results[] = $this->pass('lguids.lead_controller.lock_comments', 'Protected rule comments are present in LeadController', 'lgu_ids');
        } else {
            $results[] = $this->warning('lguids.lead_controller.lock_comments', 'Protected rule comments may have been removed from LeadController', 'lgu_ids');
        }

        return $results;
    }

    private function checkCommissionModel(): array
    {
        $results = [];

        $modelPath = app_path('Models/CommissionSplit.php');

        if (!file_exists($modelPath)) {
            $results[] = $this->fail('lguids.commission_model.exists', 'CommissionSplit model not found', 'lgu_ids', 'high');
            return $results;
        }

        $content = file_get_contents($modelPath);

        // Commission splits should still be reseller-based (not partner-based)
        if (str_contains($content, 'reseller_name')) {
            $results[] = $this->pass('lguids.commission_model.reseller_based', 'CommissionSplit model is still reseller-based (reseller_name present)', 'lgu_ids');
        } else {
            $results[] = $this->critical('lguids.commission_model.reseller_based', 'CommissionSplit model may have changed — reseller_name field missing. Commission formula may be affected.', 'lgu_ids');
        }

        return $results;
    }

    private function checkPricingService(): array
    {
        $results = [];

        // Check LguIdsPricingService exists (or equivalent)
        $servicePaths = [
            app_path('Services/LguIdsPricingService.php'),
            app_path('Services/PricingService.php'),
        ];

        $found = false;
        foreach ($servicePaths as $path) {
            if (file_exists($path)) {
                $found = true;
                $content = file_get_contents($path);

                // Check for TIERS or base_cost computation
                if (str_contains($content, 'TIERS') || str_contains($content, 'base_cost') || str_contains($content, 'added_amount')) {
                    $results[] = $this->pass('lguids.pricing_service.formula', 'Pricing service contains base_cost/added_amount/TIERS reference (formula intact)', 'lgu_ids');
                } else {
                    $results[] = $this->warning('lguids.pricing_service.formula', 'Pricing service found but formula reference unclear — manual review recommended', 'lgu_ids');
                }
                break;
            }
        }

        if (!$found) {
            $results[] = $this->warning('lguids.pricing_service.exists', 'No LGU IDS-specific pricing service found (may be inline) — verify formula is intact', 'lgu_ids');
        }

        return $results;
    }

    private function checkImportTemplate(): array
    {
        $results = [];

        // Check LguIdsImportController exists and has protected template logic
        $controllerPath = app_path('Http/Controllers/Web/LguIdsImportController.php');

        if (!file_exists($controllerPath)) {
            $results[] = $this->warning('lguids.import.controller_exists', 'LguIdsImportController not found — import template check skipped', 'lgu_ids');
            return $results;
        }

        $content = file_get_contents($controllerPath);

        if (str_contains($content, 'downloadTemplate') || str_contains($content, 'template')) {
            $results[] = $this->pass('lguids.import.template', 'LGU IDS import controller has template method', 'lgu_ids');
        } else {
            $results[] = $this->warning('lguids.import.template', 'LGU IDS import controller may be missing template download method', 'lgu_ids');
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

    private function warning(string $check, string $message, string $module): array
    {
        return ['check' => $check, 'status' => 'warning', 'message' => $message, 'details' => [], 'module' => $module, 'severity' => 'medium'];
    }

    private function critical(string $check, string $message, string $module, array $details = []): array
    {
        return ['check' => $check, 'status' => 'critical', 'message' => $message, 'details' => $details, 'module' => $module, 'severity' => 'critical'];
    }
}
