<?php

namespace App\Services;

use App\Models\PartnerProgramMembership;
use App\Models\Program;
use App\Models\ReferrerProgramMembership;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Program-wide (not per-referrer) deal/commission/member metrics for the
 * Programs V4 workspace "Analytics" tab.
 *
 * Unlike ReferrerPerformanceService, this aggregates across the whole
 * program rather than attributing a split share to one specific reseller,
 * so no DISTINCT ON / commission_splits join is needed — a single sum
 * over leads filtered by program_id is sufficient. Reuses the same locked
 * commission pool formula (CommissionCalculationService::COMMISSION_POOL_RATE)
 * — never invents a new one.
 */
class ProgramAnalyticsService
{
    public function compute(Program $program): array
    {
        return Cache::remember(
            "program_analytics:{$program->tenant_id}:{$program->id}",
            120,
            fn () => $this->dealMetrics($program) + $this->memberCounts($program)
        );
    }

    private function dealMetrics(Program $program): array
    {
        $pr = CommissionCalculationService::COMMISSION_POOL_RATE;

        try {
            $agg = DB::table('leads')
                ->where('tenant_id', $program->tenant_id)
                ->where('program_id', $program->id)
                ->whereNull('deleted_at')
                ->where('status', '!=', 'archived')
                ->selectRaw("
                    COUNT(*)                                                                                  AS total,
                    SUM(CASE WHEN status IN ('active','expiring') THEN 1 ELSE 0 END)                        AS active,
                    SUM(CASE WHEN status = 'expiring'             THEN 1 ELSE 0 END)                        AS expiring,
                    SUM(CASE WHEN stage  = 'paid'                 THEN 1 ELSE 0 END)                        AS paid,
                    COALESCE(SUM(CASE WHEN status IN ('active','expiring') THEN deal_value ELSE 0 END), 0)  AS total_value,
                    COALESCE(SUM(CASE WHEN commission_status = 'pending' THEN added_amount * {$pr} ELSE 0 END), 0) AS pending_comm,
                    COALESCE(SUM(CASE WHEN commission_status = 'locked'  THEN added_amount * {$pr} ELSE 0 END), 0) AS locked_comm,
                    COALESCE(SUM(CASE WHEN commission_status = 'paid'    THEN added_amount * {$pr} ELSE 0 END), 0) AS paid_comm
                ")
                ->first();
        } catch (\Throwable) {
            $agg = null;
        }

        $total  = (int) ($agg?->total  ?? 0);
        $active = (int) ($agg?->active ?? 0);
        $paid   = (int) ($agg?->paid   ?? 0);
        $tv     = (float) ($agg?->total_value ?? 0);

        return [
            'total_deals'        => $total,
            'active_deals'       => $active,
            'expiring_deals'     => (int) ($agg?->expiring ?? 0),
            'closed_won_deals'   => $paid,
            'total_deal_value'   => $tv,
            'average_deal_value' => $active > 0 ? round($tv / $active, 2) : 0.0,
            'conversion_rate'    => $total > 0 ? round($paid / $total * 100, 1) : null,
            'pending_commission' => (float) ($agg?->pending_comm ?? 0),
            'locked_commission'  => (float) ($agg?->locked_comm  ?? 0),
            'paid_commission'    => (float) ($agg?->paid_comm    ?? 0),
        ];
    }

    private function memberCounts(Program $program): array
    {
        // One query per table (total + active via conditional aggregation)
        // instead of two, matching dealMetrics()'s single-query style.
        $referrers = ReferrerProgramMembership::forProgram($program->id)
            ->selectRaw("COUNT(*) AS total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active")
            ->first();

        $partners = PartnerProgramMembership::forProgram($program->id)
            ->selectRaw("COUNT(*) AS total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active")
            ->first();

        return [
            'referrer_total'  => (int) ($referrers->total  ?? 0),
            'referrer_active' => (int) ($referrers->active ?? 0),
            'partner_total'   => (int) ($partners->total  ?? 0),
            'partner_active'  => (int) ($partners->active ?? 0),
        ];
    }
}
