<?php

namespace App\Services\LguIds;

class LguIdsPricingService
{
    // LOCKED TABLE — do not modify values. Source: LGU IDS protected business rules.
    // deal_amount => [base_cost, display_pct_string]
    public const TIERS = [
         4_000_000 => [2_400_000, '60%'],
         5_000_000 => [3_000_000, '60%'],
         6_000_000 => [3_600_000, '60%'],
         8_000_000 => [4_640_000, '58%'],
        10_000_000 => [5_800_000, '58%'],
        12_000_000 => [6_960_000, '58%'],
        15_000_000 => [7_000_000, '48%'],
        17_000_000 => [7_000_000, '41%'],
        25_000_000 => [10_250_000, '41%'],
    ];

    public static function lookupBaseCost(int|float $dealAmount): ?int
    {
        $key = (int) $dealAmount;
        return isset(self::TIERS[$key]) ? self::TIERS[$key][0] : null;
    }

    public static function lookupDisplayPct(int|float $dealAmount): ?string
    {
        $key = (int) $dealAmount;
        return isset(self::TIERS[$key]) ? self::TIERS[$key][1] : null;
    }

    public static function isStandardTier(int|float $dealAmount): bool
    {
        return isset(self::TIERS[(int) $dealAmount]);
    }

    /**
     * Normalize ₱4,000,000 / "4,000,000" / "4000000" → 4000000.0
     * Returns null if unparseable.
     */
    public static function normalizeAmount(string|int|float|null $raw): ?float
    {
        if ($raw === null || $raw === '') return null;
        $str = (string) $raw;
        // Remove PHP peso sign, commas, spaces, non-numeric except . and -
        $str = preg_replace('/[₱\s,]/', '', $str);
        $str = preg_replace('/[^0-9.\-]/', '', $str);
        if ($str === '' || !is_numeric($str)) return null;
        return (float) $str;
    }

    /**
     * Compute all pricing fields for a row given user-supplied values.
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
        $has_bc = $rawBaseCost !== null;
        $has_aa = $rawAddedAmount !== null;

        $issues      = [];
        $status      = 'ok';
        $computedFrom = 'none';

        // ── Case 4: only base_cost + added_amount
        if (!$has_da && $has_bc && $has_aa) {
            $rawDealAmount = $rawBaseCost + $rawAddedAmount;
            $has_da        = true;
            $computedFrom  = 'base_cost_added_amount';
        }

        // ── No amount at all
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

        $dealAmount   = $rawDealAmount;
        $tierBaseCost = self::lookupBaseCost($dealAmount);
        $displayPct   = self::lookupDisplayPct($dealAmount);
        $tierMatched  = $tierBaseCost !== null;

        if (!$tierMatched) {
            $status = 'non_standard';
            if (!$has_bc) {
                return [
                    'deal_amount'    => $dealAmount,
                    'base_cost'      => null,
                    'added_amount'   => null,
                    'display_pct'    => null,
                    'tier_matched'   => false,
                    'pricing_status' => 'needs_pricing_review',
                    'pricing_issue'  => "Deal amount ₱" . number_format($dealAmount) . " is not a standard LGU IDS tier. Base cost must be provided manually.",
                    'computed_from'  => $computedFrom ?: 'deal_amount',
                ];
            }
        }

        // Determine final base_cost
        $finalBaseCost = $has_bc ? $rawBaseCost : ($tierBaseCost ?? null);

        // Validate user-supplied base_cost against tier
        if ($has_bc && $tierMatched && (int) $rawBaseCost !== $tierBaseCost) {
            $issues[] = "Supplied base cost ₱" . number_format($rawBaseCost) . " does not match standard tier ₱" . number_format($tierBaseCost) . ".";
            $status   = 'amount_mismatch';
        }

        $computedAddedAmount = $finalBaseCost !== null ? ($dealAmount - $finalBaseCost) : null;

        // Validate user-supplied added_amount
        if ($has_aa && $computedAddedAmount !== null && abs($rawAddedAmount - $computedAddedAmount) > 0.01) {
            $issues[] = "Supplied added amount ₱" . number_format($rawAddedAmount) . " does not match computed ₱" . number_format($computedAddedAmount) . ".";
            $status   = 'amount_mismatch';
        }

        // Validate case 5: all three supplied
        if ($has_da && $has_bc && $has_aa) {
            $sum = $rawBaseCost + $rawAddedAmount;
            if (abs($sum - $dealAmount) > 0.01) {
                $issues[]     = "Base cost + added amount (" . number_format($sum) . ") does not equal deal amount (" . number_format($dealAmount) . ").";
                $status       = 'amount_mismatch';
            }
            $computedFrom = 'full_values';
        } elseif ($has_da && $has_bc && !$has_aa) {
            $computedFrom = $computedFrom ?: 'deal_amount_base_cost';
        } elseif ($has_da && !$has_bc && $has_aa) {
            $computedFrom = $computedFrom ?: 'deal_amount_added_amount';
        } elseif ($has_da && !$has_bc && !$has_aa) {
            $computedFrom = $computedFrom ?: 'deal_amount';
        }

        // Display percent for non-standard
        if (!$tierMatched && $finalBaseCost !== null && $dealAmount > 0) {
            $displayPct = round(($finalBaseCost / $dealAmount) * 100, 1) . '% (computed)';
        }

        $finalAddedAmount = $has_aa ? $rawAddedAmount : $computedAddedAmount;

        if ($status === 'ok' && $tierMatched) $status = 'standard_tier';
        elseif ($status === 'ok')             $status = 'non_standard';

        return [
            'deal_amount'    => $dealAmount,
            'base_cost'      => $finalBaseCost,
            'added_amount'   => $finalAddedAmount,
            'display_pct'    => $displayPct,
            'tier_matched'   => $tierMatched,
            'pricing_status' => $status,
            'pricing_issue'  => empty($issues) ? null : implode(' ', $issues),
            'computed_from'  => $computedFrom,
        ];
    }
}
