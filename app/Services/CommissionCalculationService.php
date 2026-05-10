<?php

namespace App\Services;

/**
 * CommissionCalculationService
 *
 * Single source of truth for all commission formula math in ReferralBunny.ai.
 *
 * LOCKED FORMULA:
 *   Deal Value  = Base Cost + Added Amount
 *   Company Share   = 30% of Added Amount
 *   Commission Pool = 70% of Added Amount
 *
 * All controllers, services, reports, and exports MUST use this service.
 * Never hardcode 0.70 or 0.30 outside this class.
 */
class CommissionCalculationService
{
    public const COMPANY_SHARE_RATE   = 0.30;
    public const COMMISSION_POOL_RATE = 0.70;

    // ── Core formula ──────────────────────────────────────────────────────────

    public function addedAmount(float $dealValue, float $baseCost): float
    {
        return max(0.0, round($dealValue - $baseCost, 2));
    }

    public function companyShare(float $addedAmount): float
    {
        return round($addedAmount * self::COMPANY_SHARE_RATE, 2);
    }

    public function commissionPool(float $addedAmount): float
    {
        return round($addedAmount * self::COMMISSION_POOL_RATE, 2);
    }

    /**
     * Full breakdown from deal_value + base_cost.
     */
    public function breakdown(float $dealValue, float $baseCost): array
    {
        $aa  = $this->addedAmount($dealValue, $baseCost);
        $cs  = $this->companyShare($aa);
        $cp  = $this->commissionPool($aa);

        return [
            'deal_value'      => round($dealValue, 2),
            'base_cost'       => round($baseCost,  2),
            'added_amount'    => $aa,
            'company_share'   => $cs,
            'commission_pool' => $cp,
        ];
    }

    /**
     * Referrer's individual share from the pool, given a split %.
     */
    public function referrerShare(float $commissionPool, float $splitPercentage): float
    {
        return round($commissionPool * max(0, min(100, $splitPercentage)) / 100, 2);
    }

    /**
     * Partner's fixed or percentage-based share from the pool.
     */
    public function partnerShare(float $basis, float $value, string $type = 'percentage'): float
    {
        if ($type === 'fixed_amount') {
            return round((float) $value, 2);
        }
        // percentage of the commission pool (or contract value if that's the basis)
        return round($basis * max(0, min(100, $value)) / 100, 2);
    }

    // ── Validation ────────────────────────────────────────────────────────────

    /**
     * Returns true when base_cost + added_amount ≈ deal_value (within ₱0.05).
     */
    public function formulaIsValid(float $dealValue, float $baseCost, float $addedAmount): bool
    {
        return abs(($baseCost + $addedAmount) - $dealValue) < 0.05;
    }

    /**
     * Validate that total partner+referrer splits do not exceed the commission pool.
     * Returns ['valid' => bool, 'allocated' => float, 'remaining' => float].
     */
    public function validateDistribution(
        float $commissionPool,
        array $referrerSplitAmounts,
        array $partnerSplitAmounts
    ): array {
        $allocated = array_sum($referrerSplitAmounts) + array_sum($partnerSplitAmounts);
        $remaining = round($commissionPool - $allocated, 2);

        return [
            'valid'       => $remaining >= -0.05,
            'allocated'   => round($allocated, 2),
            'remaining'   => $remaining,
            'overallocated' => $remaining < -0.05,
        ];
    }

    // ── Formatting ────────────────────────────────────────────────────────────

    public function formatMoney(float $amount, bool $showSign = false): string
    {
        $formatted = number_format(abs($amount), 0);
        $sign      = $amount < 0 ? '-' : ($showSign ? '+' : '');
        return $sign . "\u{20B1}" . $formatted;
    }

    public function formatPercent(float $pct, int $decimals = 0): string
    {
        return number_format($pct, $decimals) . '%';
    }

    // ── Safe accessor from Lead/deal array ────────────────────────────────────

    /**
     * Build a breakdown from a Lead model or array of deal fields.
     * Uses deal_value as fallback if added_amount is 0 (for legacy deals
     * created before the base_cost/added_amount split was introduced).
     */
    public function breakdownFromLead(object|array $lead): array
    {
        // Handle Eloquent models, stdClass (DB::table() results), and plain arrays
        if (is_array($lead)) {
            $data = $lead;
        } elseif ($lead instanceof \Illuminate\Database\Eloquent\Model) {
            $data = $lead->getAttributes();
        } else {
            $data = (array) $lead; // stdClass from raw DB queries
        }

        $dv  = (float) ($data['deal_value']   ?? 0);
        $bc  = (float) ($data['base_cost']    ?? 0);
        $aa  = (float) ($data['added_amount'] ?? 0);

        // For deals where only deal_value was set (legacy), treat full amount as margin
        if ($aa <= 0 && $dv > 0) {
            $aa = $dv;
            $bc = 0;
        }

        return $this->breakdown($dv > 0 ? $dv : ($bc + $aa), $bc);
    }
}
