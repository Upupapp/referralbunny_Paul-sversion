<?php

namespace App\Services;

use App\Services\CommissionCalculationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReferrerPerformanceService
{
    /**
     * Calculate tenant-scoped performance metrics for a single referrer.
     * Uses only the existing leads table and approved commission logic.
     * Never invents new LGU IDS formulas.
     */
    public function forReseller(string $tenantId, string $resellerName, ?string $resellerId = null): array
    {
        // Key on reseller ID when available — name-based keys cause cache poisoning on name collisions
        $cacheKey = $resellerId
            ? "referrer_perf:{$tenantId}:{$resellerId}"
            : "referrer_perf:{$tenantId}:" . md5($resellerName);
        return Cache::remember($cacheKey, 120, function () use ($tenantId, $resellerName) {
            return $this->compute($tenantId, $resellerName);
        });
    }

    private function compute(string $tenantId, string $resellerName): array
    {
        $pr    = CommissionCalculationService::COMMISSION_POOL_RATE;
        $lower = strtolower($resellerName);

        try {
            // DISTINCT ON (l.id) deduplicates leads before aggregating so that a lead with
            // multiple commission_splits rows for the same reseller is counted exactly once.
            // The ORDER BY l.id, cs.id NULLS LAST picks the split row (non-null) over no-split.
            $inner = "
                SELECT DISTINCT ON (l.id)
                    l.id,
                    l.status,
                    l.stage,
                    l.deal_value,
                    l.added_amount,
                    l.commission_status,
                    l.created_at,
                    COALESCE(cs.percentage, 100.0) AS percentage
                FROM leads AS l
                LEFT JOIN commission_splits AS cs
                    ON cs.lead_id = l.id AND LOWER(cs.reseller_name) = ? AND cs.deleted_at IS NULL
                WHERE l.tenant_id = ?
                  AND (LOWER(l.reseller_name) = ? OR cs.lead_id IS NOT NULL)
                  AND l.deleted_at IS NULL
                ORDER BY l.id, cs.id NULLS LAST
            ";

            $agg = DB::query()
                ->fromRaw("({$inner}) AS deduped", [$lower, $tenantId, $lower])
                ->selectRaw("
                    COUNT(*)                                                                                                            AS total,
                    SUM(CASE WHEN status IN ('active','expiring') THEN 1 ELSE 0 END)                                                   AS active,  -- includes both 'active' and 'expiring'
                    SUM(CASE WHEN status = 'expiring'             THEN 1 ELSE 0 END)                                                   AS expiring,
                    SUM(CASE WHEN stage  = 'paid'                 THEN 1 ELSE 0 END)                                                   AS paid,
                    COALESCE(SUM(CASE WHEN status IN ('active','expiring') THEN deal_value ELSE 0 END), 0)                             AS total_value,
                    COALESCE(SUM(CASE WHEN commission_status='pending' THEN added_amount * {$pr} * percentage/100.0 ELSE 0 END), 0)   AS pending_comm,
                    COALESCE(SUM(CASE WHEN commission_status='locked'  THEN added_amount * {$pr} * percentage/100.0 ELSE 0 END), 0)   AS locked_comm,
                    COALESCE(SUM(CASE WHEN commission_status='paid'    THEN added_amount * {$pr} * percentage/100.0 ELSE 0 END), 0)   AS paid_comm,
                    MAX(created_at)                                                                                                    AS last_deal_at
                ")->first();
        } catch (\Throwable) {
            $agg = null;
        }

        $total  = (int)  ($agg?->total       ?? 0);
        $paid   = (int)  ($agg?->paid        ?? 0);
        $tv     = (float)($agg?->total_value ?? 0);
        $lastDeal = $agg?->last_deal_at;

        // Unread messages
        $unreadMessages = 0;
        try {
            $thread = DB::table('message_threads')
                ->where('tenant_id', $tenantId)
                ->where('reseller_name', $resellerName)
                ->first();
            $unreadMessages = $thread ? (int) ($thread->admin_unread ?? 0) : 0;
        } catch (\Throwable) {}

        // Overdue updates — leads that haven't been updated in >14 days
        $overdueUpdates = 0;
        try {
            $overdueUpdates = DB::table('leads AS l')
                ->leftJoin('commission_splits AS cs2', function ($join) use ($lower) {
                    $join->on('cs2.lead_id', '=', 'l.id')
                         ->whereRaw('LOWER(cs2.reseller_name) = ?', [$lower])
                         ->whereNull('cs2.deleted_at');
                })
                ->where('l.tenant_id', $tenantId)
                ->whereRaw('(LOWER(l.reseller_name) = ? OR cs2.lead_id IS NOT NULL)', [$lower])
                ->whereIn('l.status', ['active', 'expiring'])
                ->where('l.updated_at', '<', now()->subDays(14))
                ->whereNull('l.deleted_at')
                ->distinct()
                ->count('l.id');
        } catch (\Throwable) {}

        return [
            'total_deals'          => $total,
            'active_deals'         => (int)   ($agg?->active     ?? 0),
            'expiring_deals'       => (int)   ($agg?->expiring   ?? 0),
            'closed_won_deals'     => $paid,
            'total_deal_value'     => $tv,
            'average_deal_value'   => (int)($agg?->active ?? 0) > 0 ? round($tv / (int)($agg?->active ?? 0), 2) : 0.0,
            'conversion_rate'      => $total > 0 ? round($paid / $total * 100, 1) : null,
            'pending_commission'   => (float) ($agg?->pending_comm ?? 0),
            'locked_commission'    => (float) ($agg?->locked_comm  ?? 0),
            'paid_commission'      => (float) ($agg?->paid_comm    ?? 0),
            'unread_messages'      => $unreadMessages,
            'overdue_updates'      => $overdueUpdates,
            'last_deal_created_at' => $lastDeal,
        ];
    }

    /**
     * Compute details completeness for a reseller row.
     */
    public function completenessStatus(object $reseller): array
    {
        $missing = [];

        if (empty($reseller->name))  $missing[] = 'name';
        if (empty($reseller->email)) $missing[] = 'email';
        if (empty($reseller->phone)) $missing[] = 'phone';

        $hasSetup = !empty($reseller->password) || in_array($reseller->status ?? '', ['active', 'nda_signed']);

        if (!$hasSetup) $missing[] = 'account setup';

        $score = match(true) {
            count($missing) === 0                      => 'complete',
            in_array('email', $missing)                => 'needs_email',
            in_array('account setup', $missing)        => 'pending_setup',
            default                                    => 'needs_details',
        };

        return [
            'status'  => $score,
            'missing' => $missing,
        ];
    }

    /**
     * Batch completeness for a list of resellers.
     */
    public function batchCompleteness(iterable $resellers): array
    {
        $result = [];
        foreach ($resellers as $r) {
            $result[$r->id] = $this->completenessStatus($r);
        }
        return $result;
    }
}
