<?php

namespace Tests\Unit\Services;

use App\Services\LguIds\LguIdsPricingService;
use PHPUnit\Framework\TestCase;

class LguIdsPricingServiceTest extends TestCase
{
    // ── Tier lookup: base cost ────────────────────────────────────

    public function testLookupBaseCost4M(): void
    {
        $this->assertSame(2_400_000, LguIdsPricingService::lookupBaseCost(4_000_000));
    }

    public function testLookupBaseCost5M(): void
    {
        $this->assertSame(3_000_000, LguIdsPricingService::lookupBaseCost(5_000_000));
    }

    public function testLookupBaseCost6M(): void
    {
        $this->assertSame(3_600_000, LguIdsPricingService::lookupBaseCost(6_000_000));
    }

    public function testLookupBaseCost8M(): void
    {
        $this->assertSame(4_640_000, LguIdsPricingService::lookupBaseCost(8_000_000));
    }

    public function testLookupBaseCost10M(): void
    {
        $this->assertSame(5_800_000, LguIdsPricingService::lookupBaseCost(10_000_000));
    }

    public function testLookupBaseCost12M(): void
    {
        $this->assertSame(6_960_000, LguIdsPricingService::lookupBaseCost(12_000_000));
    }

    public function testLookupBaseCost15M(): void
    {
        // 15M × 48% = 7,200,000
        $this->assertSame(7_200_000, LguIdsPricingService::lookupBaseCost(15_000_000));
    }

    public function testLookupBaseCost17M(): void
    {
        // 17M × 41% = 6,970,000
        $this->assertSame(6_970_000, LguIdsPricingService::lookupBaseCost(17_000_000));
    }

    public function testLookupBaseCost25M(): void
    {
        $this->assertSame(10_250_000, LguIdsPricingService::lookupBaseCost(25_000_000));
    }

    // ── Added amount computation ──────────────────────────────────

    public function testAddedAmountIsComputedCorrectly(): void
    {
        // 4M tier: base = 2,400,000 → added = 4,000,000 - 2,400,000 = 1,600,000
        $result = LguIdsPricingService::compute(4_000_000.0, null, null);
        $this->assertSame(4_000_000.0, $result['deal_amount']);
        $this->assertSame(2_400_000,   $result['base_cost']);
        $this->assertSame(1_600_000.0, $result['added_amount']);
        $this->assertSame('60%',       $result['display_pct']);
        $this->assertTrue($result['tier_matched']);
        $this->assertSame('range_based', $result['pricing_status']);
        $this->assertNull($result['pricing_issue']);
    }

    // ── Amount normalization ──────────────────────────────────────

    public function testNormalizeAmountHandlesPesoSign(): void
    {
        $this->assertSame(4_000_000.0, LguIdsPricingService::normalizeAmount('₱4,000,000'));
    }

    public function testNormalizeAmountHandlesCommas(): void
    {
        $this->assertSame(4_000_000.0, LguIdsPricingService::normalizeAmount('4,000,000'));
    }

    public function testNormalizeAmountHandlesPlainInteger(): void
    {
        $this->assertSame(4_000_000.0, LguIdsPricingService::normalizeAmount('4000000'));
    }

    public function testNormalizeAmountHandlesFloat(): void
    {
        $this->assertSame(4_000_000.5, LguIdsPricingService::normalizeAmount('4000000.5'));
    }

    public function testNormalizeAmountReturnsNullForEmpty(): void
    {
        $this->assertNull(LguIdsPricingService::normalizeAmount(''));
        $this->assertNull(LguIdsPricingService::normalizeAmount(null));
    }

    public function testNormalizeAmountReturnsNullForGarbage(): void
    {
        $this->assertNull(LguIdsPricingService::normalizeAmount('not-a-number'));
    }

    // ── Range-based: any positive amount gets a computed base cost ───

    public function testMidRangeAmountGetsRangeBasedResult(): void
    {
        // 4,500,000 is between range boundaries — still computed as 4.5M × 60% = 2,700,000
        $result = LguIdsPricingService::compute(4_500_000.0, null, null);
        $this->assertTrue($result['tier_matched']);
        $this->assertSame('range_based', $result['pricing_status']);
        $this->assertNull($result['pricing_issue']);
        $this->assertSame(2_700_000, $result['base_cost']);
    }

    // ── Base cost mismatch flagged ────────────────────────────────

    public function testBaseCostMismatchFlagged(): void
    {
        // Correct base for 4M is 2,400,000 — supply a wrong one
        $result = LguIdsPricingService::compute(4_000_000.0, 2_500_000.0, null);
        $this->assertSame('amount_mismatch', $result['pricing_status']);
        $this->assertNotNull($result['pricing_issue']);
        $this->assertStringContainsString('differs from range-computed', $result['pricing_issue']);
    }

    // ── Compute from base_cost + added_amount (no deal_amount) ───

    public function testComputeFromBaseCostAndAddedAmount(): void
    {
        // Supply base + added, no deal_amount → should derive deal_amount = 4M
        $result = LguIdsPricingService::compute(null, 2_400_000.0, 1_600_000.0);
        $this->assertSame(4_000_000.0, $result['deal_amount']);
        $this->assertSame(2_400_000,   $result['base_cost']);
        $this->assertSame('base_cost_added_amount', $result['computed_from']);
        $this->assertSame('range_based', $result['pricing_status']);
    }

    // ── Missing amount returns missing_amount status ──────────────

    public function testMissingAmountReturnsMissingStatus(): void
    {
        $result = LguIdsPricingService::compute(null, null, null);
        $this->assertSame('missing_amount', $result['pricing_status']);
        $this->assertNull($result['deal_amount']);
    }

    // ── isStandardTier helper ─────────────────────────────────────

    public function testIsStandardTierReturnsTrueForPositiveAmounts(): void
    {
        foreach ([4_000_000, 6_000_000, 8_000_000, 12_000_000, 15_000_000, 17_000_000, 25_000_000] as $amount) {
            $this->assertTrue(LguIdsPricingService::isStandardTier($amount), "Expected $amount to be a standard range.");
        }
    }

    public function testIsStandardTierReturnsFalseForNonPositiveAmount(): void
    {
        $this->assertFalse(LguIdsPricingService::isStandardTier(0));
        $this->assertFalse(LguIdsPricingService::isStandardTier(-1));
    }

    // ── Sum mismatch when all three are supplied ──────────────────

    public function testSumMismatchWhenAllThreeSupplied(): void
    {
        // 2,400,000 + 1,500,000 = 3,900,000 ≠ 4,000,000
        $result = LguIdsPricingService::compute(4_000_000.0, 2_400_000.0, 1_500_000.0);
        $this->assertSame('amount_mismatch', $result['pricing_status']);
        $this->assertStringContainsString('does not equal deal amount', $result['pricing_issue']);
    }

    // ── Display pct lookup ────────────────────────────────────────

    public function testDisplayPctLookup(): void
    {
        $this->assertSame('60%', LguIdsPricingService::lookupDisplayPct(4_000_000));
        $this->assertSame('58%', LguIdsPricingService::lookupDisplayPct(8_000_000));
        $this->assertSame('41%', LguIdsPricingService::lookupDisplayPct(25_000_000));
        $this->assertSame('48%', LguIdsPricingService::lookupDisplayPct(14_000_000));
    }

    // ── LGU IDS isolation / non-globalisation guards ──────────────

    /**
     * GenericDealImportService must NOT import or reference LguIdsPricingService.
     * This prevents LGU IDS pricing logic from leaking into generic tenants.
     */
    public function testLguIdsPricingServiceIsNotGloballyApplied(): void
    {
        $projectRoot = dirname(__DIR__, 3); // tests/Unit/Services -> project root
        $serviceFile = file_get_contents($projectRoot . '/app/Services/GenericDealImportService.php');

        $this->assertFalse(
            str_contains($serviceFile, 'LguIdsPricingService'),
            'GenericDealImportService must not reference LguIdsPricingService — LGU IDS pricing is tenant-specific and must never be applied globally.'
        );
    }

    /**
     * The global import templates config must not contain an 'lgu_ids' key.
     * LGU IDS uses its own locked service; it must not appear as a generic template.
     */
    public function testLguIdsTemplateNotInGenericConfig(): void
    {
        $projectRoot = dirname(__DIR__, 3);
        $templates   = require $projectRoot . '/config/referralbunny_import_templates.php';

        $this->assertArrayNotHasKey(
            'lgu_ids',
            $templates,
            "The 'lgu_ids' template key must not exist in referralbunny_import_templates config — LGU IDS has its own locked import service."
        );
    }

    /**
     * The GenericDealImportService file must not reference 'municipality_or_city'
     * as a required field. That field is exclusive to LGU IDS.
     */
    public function testMunicipalityNotInGenericServiceAliases(): void
    {
        $projectRoot = dirname(__DIR__, 3);
        $serviceFile = file_get_contents($projectRoot . '/app/Services/GenericDealImportService.php');

        $this->assertFalse(
            str_contains($serviceFile, 'municipality_or_city'),
            "GenericDealImportService must not reference 'municipality_or_city' as a required field — that field is exclusive to LGU IDS."
        );
    }
}
