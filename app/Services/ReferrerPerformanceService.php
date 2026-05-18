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
    public function forReseller(string $tenantId, string $resellerName): array
    {
        $cacheKey = "referrer_perf:{$tenantId}:" . md5($resellerName);
        return Cache::remember($cacheKey, 120, function () use ($tenantId, $resellerName) {
            return $this->compute($tenantId, $resellerName);
        });
    }

    private function compute(string $tenantId, string $resellerName): array
    {
        $pr    = CommissionCalculationService::COMMISSION_POOL_RATE;
        $lower = strtolower($resellerName);

        try {
            // Join commission_splits to get per-deal split percentage for this reseller.
            // Covers both primary referrer and co-referrer (secondary split) deals.
            $agg = DB::table('leads AS l')
                ->leftJoin('commission_splits AS cs', function ($join) use ($lower) {
                    $join->on('cs.lead_id', '=', 'l.id')
                         ->whereRaw('LOWER(cs.reseller_name) = ?', [$lower]);
                })
                ->where('l.tenant_id', $tenantId)
                ->whereRaw('(LOWER(l.reseller_name) = ? OR cs.lead_id IS NOT NULL)', [$lower])
                ->whereNull('l.deleted_at')
                ->selectRaw("
                    COUNT(DISTINCT l.id)                                                                                                                  AS total,
                    SUM(CASE WHEN l.status IN ('active','expiring') THEN 1 ELSE 0 END)                                                                    AS active,
                    SUM(CASE WHEN l.status = 'expiring'             THEN 1 ELSE 0 END)                                                                    AS expiring,
                    SUM(CASE WHEN l.stage  = 'paid'                 THEN 1 ELSE 0 END)                                                                    AS paid,
                    COALESCE(SUM(l.deal_value), 0)                                                                                                        AS total_value,
                    COALESCE(SUM(CASE WHEN l.commission_status='pending' THEN l.added_amount * {$pr} * COALESCE(cs.percentage,100.0)/100.0 ELSE 0 END),0) AS pending_comm,
                    COALESCE(SUM(CASE WHEN l.commission_status='locked'  THEN l.added_amount * {$pr} * COALESCE(cs.percentage,100.0)/100.0 ELSE 0 END),0) AS locked_comm,
                    COALESCE(SUM(CASE WHEN l.commission_status='paid'    THEN l.added_amount * {$pr} * COALESCE(cs.percentage,100.0)/100.0 ELSE 0 END),0) AS paid_comm,
                    MAX(l.created_at)                                                                                                                     AS last_deal_at
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
                         ->whereRaw('LOWER(cs2.reseller_name) = ?', [$lower]);
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
            'average_deal_value'   => $total > 0 ? round($tv / $total, 2) : 0.0,
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
