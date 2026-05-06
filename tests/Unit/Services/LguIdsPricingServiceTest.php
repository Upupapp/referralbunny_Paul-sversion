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
        $this->assertSame(7_000_000, LguIdsPricingService::lookupBaseCost(15_000_000));
    }

    public function testLookupBaseCost17M(): void
    {
        $this->assertSame(7_000_000, LguIdsPricingService::lookupBaseCost(17_000_000));
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
        $this->assertSame('standard_tier', $result['pricing_status']);
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

    // ── Non-standard amount flagged for review ────────────────────

    public function testNonStandardAmountFlaggedForReview(): void
    {
        // 4,500,000 is not in the tier table and no base cost supplied
        $result = LguIdsPricingService::compute(4_500_000.0, null, null);
        $this->assertFalse($result['tier_matched']);
        $this->assertSame('needs_pricing_review', $result['pricing_status']);
        $this->assertNotNull($result['pricing_issue']);
        $this->assertNull($result['base_cost']);
    }

    // ── Base cost mismatch flagged ────────────────────────────────

    public function testBaseCostMismatchFlagged(): void
    {
        // Correct base for 4M is 2,400,000 — supply a wrong one
        $result = LguIdsPricingService::compute(4_000_000.0, 2_500_000.0, null);
        $this->assertSame('amount_mismatch', $result['pricing_status']);
        $this->assertNotNull($result['pricing_issue']);
        $this->assertStringContainsString('does not match standard tier', $result['pricing_issue']);
    }

    // ── Compute from base_cost + added_amount (no deal_amount) ───

    public function testComputeFromBaseCostAndAddedAmount(): void
    {
        // Supply base + added, no deal_amount → should derive deal_amount = 4M
        $result = LguIdsPricingService::compute(null, 2_400_000.0, 1_600_000.0);
        $this->assertSame(4_000_000.0, $result['deal_amount']);
        $this->assertSame(2_400_000,   $result['base_cost']);
        $this->assertSame('base_cost_added_amount', $result['computed_from']);
        $this->assertSame('standard_tier', $result['pricing_status']);
    }

    // ── Missing amount returns missing_amount status ──────────────

    public function testMissingAmountReturnsMissingStatus(): void
    {
        $result = LguIdsPricingService::compute(null, null, null);
        $this->assertSame('missing_amount', $result['pricing_status']);
        $this->assertNull($result['deal_amount']);
    }

    // ── isStandardTier helper ─────────────────────────────────────

    public function testIsStandardTierReturnsTrueForKnownTiers(): void
    {
        foreach (array_keys(LguIdsPricingService::TIERS) as $amount) {
            $this->assertTrue(LguIdsPricingService::isStandardTier($amount), "Expected $amount to be a standard tier.");
        }
    }

    public function testIsStandardTierReturnsFalseForUnknownAmount(): void
    {
        $this->assertFalse(LguIdsPricingService::isStandardTier(3_500_000));
        $this->assertFalse(LguIdsPricingService::isStandardTier(4_500_000));
        $this->assertFalse(LguIdsPricingService::isStandardTier(9_999_999));
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
        $this->assertNull(LguIdsPricingService::lookupDisplayPct(3_000_000));
    }
}
