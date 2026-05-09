<?php

namespace App\Services\LguIds;

class LguIdsPricingService
{
    /**
     * Range-based base cost tiers (updated per LGU IDS business rules).
     * Base Cost = deal_amount × pct / 100 for the matching range.
     *
     * Deal Amount Range          % of Base Cost
     * ₱0 – ₱6,000,000            60%
     * ₱6,000,001 – ₱12,000,000   58%
     * ₱12,000,001 – ₱15,000,000  48%
     * ₱15,000,001 and above       41%
     */
    public const RANGES = [
        ['max' =>  6_000_000, 'pct' => 60, 'label' => '60%'],
        ['max' => 12_000_000, 'pct' => 58, 'label' => '58%'],
        ['max' => 15_000_000, 'pct' => 48, 'label' => '48%'],
        ['max' => PHP_INT_MAX, 'pct' => 41, 'label' => '41%'],
    ];

    public static function lookupBaseCost(int|float $dealAmount): int
    {
        foreach (self::RANGES as $range) {
            if ($dealAmount <= $range['max']) {
                return (int) round($dealAmount * $range['pct'] / 100);
            }
        }
        return (int) round($dealAmount * 0.41);
    }

    public static function lookupDisplayPct(int|float $dealAmount): string
    {
        foreach (self::RANGES as $range) {
            if ($dealAmount <= $range['max']) {
                return $range['label'];
            }
        }
        return '41%';
    }

    /** All positive amounts are valid in the range-based system. */
    public static function isStandardTier(int|float $dealAmount): bool
    {
        return $dealAmount > 0;
    }

    /**
     * Normalize ₱4,000,000 / "4,000,000" / "4000000" → 4000000.0
     * Returns null if unparseable.
     */
    public static function normalizeAmount(string|int|float|null $raw): ?float
    {
        if ($raw === null || $raw === '') return null;
        $str = (string) $raw;
        $str = preg_replace('/[₱\s,]/', '', $str);
        $str = preg_replace('/[^0-9.\-]/', '', $str);
        if ($str === '' || !is_numeric($str)) return null;
        return (float) $str;
    }

    /**
     * Compute all pricing fields for a row given user-supplied values.
     * Base cost is now always derived from the range-based percentage.
     * Manual base_cost overrides are accepted but flagged if they deviate.
     *
     * Returns array: [
     *   'deal_amount', 'base_cost', 'added_amount', 'display_pct',
     *   'tier_matched', 'pricing_status', 'pricing_issue', 'computed_from'
     * ]
     */
    public static function compute(
        ?float $rawDealAmount,
        ?float $rawBaseCost,
        ?float $rawAddedAmount
    ): array {
        $has_da = $rawDealAmount !== null;
        $has_bc = $rawBaseCost  !== null;
        $has_aa = $rawAddedAmount !== null;

        $issues       = [];
        $status       = 'ok';
        $computedFrom = 'none';

        // ── Derive deal_amount from base_cost + added_amount if not supplied
        if (!$has_da && $has_bc && $has_aa) {
            $rawDealAmount = $rawBaseCost + $rawAddedAmount;
            $has_da        = true;
            $computedFrom  = 'base_cost_added_amount';
        }

        if (!$has_da) {
            return [
                'deal_amount'    => null,
                'base_cost'      => null,
                'added_amount'   => null,
                'display_pct'    => null,
                'tier_matched'   => false,
                'pricing_status' => 'missing_amount',
                'pricing_issue'  => 'Deal amount is required.',
                'computed_from'  => $computedFrom,
            ];
        }

        $dealAmount    = $rawDealAmount;
        $rangeBaseCost = self::lookupBaseCost($dealAmount);
        $displayPct    = self::lookupDisplayPct($dealAmount);

        // Use supplied base_cost if provided; otherwise auto-compute from range
        $finalBaseCost = $has_bc ? $rawBaseCost : $rangeBaseCost;

        // Flag deviation from range calculation (allowed, but noted)
        if ($has_bc && abs($rawBaseCost - $rangeBaseCost) > 1) {
            $issues[] = "Supplied base cost ₱" . number_format($rawBaseCost) . " differs from range-computed ₱" . number_format($rangeBaseCost) . " (" . $displayPct . " of deal amount).";
            $status   = 'amount_mismatch';
        }

        $computedAddedAmount = $finalBaseCost !== null ? ($dealAmount - $finalBaseCost) : null;

        // Flag supplied added_amount deviation
        if ($has_aa && $computedAddedAmount !== null && abs($rawAddedAmount - $computedAddedAmount) > 0.01) {
            $issues[] = "Supplied added amount ₱" . number_format($rawAddedAmount) . " does not match computed ₱" . number_format($computedAddedAmount) . ".";
            $status   = 'amount_mismatch';
        }

        // Validate all three supplied sum
        if ($has_da && $has_bc && $has_aa) {
            $sum = $rawBaseCost + $rawAddedAmount;
            if (abs($sum - $dealAmount) > 0.01) {
                $issues[]     = "Base cost + added amount (" . number_format($sum) . ") does not equal deal amount (" . number_format($dealAmount) . ").";
                $status       = 'amount_mismatch';
            }
            $computedFrom = 'full_values';
        } elseif ($has_da && $has_bc) {
            $computedFrom = $computedFrom ?: 'deal_amount_base_cost';
        } elseif ($has_da && $has_aa) {
            $computedFrom = $computedFrom ?: 'deal_amount_added_amount';
        } else {
            $computedFrom = $computedFrom ?: 'deal_amount';
        }

        // If manual base_cost differs, show actual percentage
        if ($has_bc && $dealAmount > 0 && abs($rawBaseCost - $rangeBaseCost) > 1) {
            $displayPct = round(($rawBaseCost / $dealAmount) * 100, 1) . '% (manual)';
        }

        $finalAddedAmount = $has_aa ? $rawAddedAmount : $computedAddedAmount;

        if ($status === 'ok') $status = 'range_based';

        return [
            'deal_amount'    => $dealAmount,
            'base_cost'      => $finalBaseCost,
            'added_amount'   => $finalAddedAmount,
            'display_pct'    => $displayPct,
            'tier_matched'   => true,
            'pricing_status' => $status,
            'pricing_issue'  => empty($issues) ? null : implode(' ', $issues),
            'computed_from'  => $computedFrom,
        ];
    }
}
