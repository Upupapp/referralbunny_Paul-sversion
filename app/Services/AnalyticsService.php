<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantMetric;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\Reseller;
use Carbon\Carbon;

class AnalyticsService
{
    // ── Platform-level analytics ──────────────────────────────

    public function platformSummary(): array
    {
        $tenants      = Tenant::get();
        $totalLeads   = Lead::count();
        $totalPipeline= Lead::sum('deal_value');

        $byStatus = $tenants->groupBy('status')->map->count();
        $byHealth = TenantMetric::selectRaw('health_level, count(*) as count')
            ->groupBy('health_level')
            ->pluck('count', 'health_level');

        $atRiskCount   = $byHealth['at_risk']          ?? 0;
        $healthyCount  = $byHealth['healthy']           ?? 0;
        $attentionCount= $byHealth['needs_attention']   ?? 0;

        return [
            'total_tenants'    => $tenants->count(),
            'active_tenants'   => $byStatus['active']   ?? 0,
            'trial_tenants'    => $byStatus['trial']    ?? 0,
            'inactive_tenants' => $byStatus['inactive'] ?? 0,
            'total_leads'      => $totalLeads,
            'total_pipeline'   => $totalPipeline,
            'at_risk_tenants'  => $atRiskCount,
            'healthy_tenants'  => $healthyCount,
            'needs_attention'  => $attentionCount,
            'unread_notifications' => Notification::where('is_read', false)->where('is_dismissed', false)->count(),
            'critical_alerts'  => Notification::where('priority', 'critical')->where('is_read', false)->count(),
        ];
    }

    public function tenantGrowthByMonth(): array
    {
        $tenants = Tenant::selectRaw("to_char(created_at, 'YYYY-MM') as month, count(*) as count")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return $tenants->map(fn($t) => [
            'month' => Carbon::parse($t->month . '-01')->format('M Y'),
            'count' => (int) $t->count,
        ])->toArray();
    }

    public function industryDistribution(): array
    {
        return Tenant::selectRaw('industry, count(*) as count')
            ->whereNotNull('industry')
            ->groupBy('industry')
            ->orderByDesc('count')
            ->get()
            ->map(fn($t) => ['industry' => $t->industry, 'count' => (int) $t->count])
            ->toArray();
    }

    // ── Lead funnel analytics ─────────────────────────────────

    public function leadFunnel(?string $tenantId = null): array
    {
        $stages = ['introduction', 'presentation', 'contract_sent', 'signed', 'paid'];
        $query  = Lead::query();
        if ($tenantId) $query->where('tenant_id', $tenantId);

        $counts = $query->selectRaw('stage, count(*) as count')
            ->groupBy('stage')
            ->pluck('count', 'stage');

        $funnel = [];
        $prev   = null;
        foreach ($stages as $stage) {
            $count = (int) ($counts[$stage] ?? 0);
            $funnel[] = [
                'stage'           => $stage,
                'label'           => ucwords(str_replace('_', ' ', $stage)),
                'count'           => $count,
                'conversion_rate' => $prev && $prev > 0 ? round(($count / $prev) * 100, 1) : 100,
            ];
            $prev = $count;
        }

        return $funnel;
    }

    // ── Tenant-level analytics ────────────────────────────────

    public function tenantDetail(Tenant $tenant): array
    {
        $metric = TenantMetric::where('tenant_id', $tenant->id)->first();

        // Aggregate queries — never loads all rows into memory
        $stageCounts = \Illuminate\Support\Facades\DB::table('leads')
            ->where('tenant_id', $tenant->id)->whereNull('deleted_at')
            ->selectRaw('stage, count(*) as cnt')->groupBy('stage')
            ->pluck('cnt', 'stage');

        $statusCounts = \Illuminate\Support\Facades\DB::table('leads')
            ->where('tenant_id', $tenant->id)->whereNull('deleted_at')
            ->selectRaw('status, count(*) as cnt')->groupBy('status')
            ->pluck('cnt', 'status');

        $totals = \Illuminate\Support\Facades\DB::table('leads')
            ->where('tenant_id', $tenant->id)->whereNull('deleted_at')
            ->selectRaw('count(*) as total, coalesce(sum(deal_value),0) as pipeline_value')
            ->first();

        $closedValue = \Illuminate\Support\Facades\DB::table('leads')
            ->where('tenant_id', $tenant->id)->whereNull('deleted_at')
            ->where('stage', 'paid')
            ->sum('deal_value');

        return [
            'tenant_id'        => $tenant->id,
            'health_score'     => $metric?->health_score ?? 0,
            'health_level'     => $metric?->health_level ?? 'at_risk',
            'setup_completion' => $metric?->setup_completion_percentage ?? 0,
            'total_leads'      => (int) ($totals->total ?? 0),
            'leads_total'      => (int) ($totals->total ?? 0),
            'leads_active'     => (int) ($statusCounts['active']  ?? 0),
            'leads_expired'    => (int) ($statusCounts['expired'] ?? 0),
            'leads_paid'       => (int) ($stageCounts['paid']     ?? 0),
            'pipeline_value'   => (float) ($totals->pipeline_value ?? 0),
            'closed_value'     => (float) ($closedValue ?? 0),
            'by_stage'         => $stageCounts,
            'by_status'        => $statusCounts,
            'total_resellers'  => Reseller::where('tenant_id', $tenant->id)->count(),
        ];
    }

    // ── Tenant financial summary (server-side, avoids pagination gaps) ──

    public function tenantFinancialSummary(string $tenantId): array
    {
        $r = \Illuminate\Support\Facades\DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->selectRaw("
                COALESCE(SUM(deal_value), 0)                                                        AS total_contract,
                COALESCE(SUM(added_amount * 0.30), 0)                                               AS total_company,
                COALESCE(SUM(added_amount * 0.70), 0)                                               AS total_pool,
                COALESCE(SUM(CASE WHEN commission_status = 'paid'    THEN added_amount * 0.70 ELSE 0 END), 0) AS paid_pool,
                COALESCE(SUM(CASE WHEN commission_status = 'pending' THEN 1 ELSE 0 END), 0)         AS pending_count,
                COALESCE(SUM(CASE WHEN commission_status = 'locked'  THEN 1 ELSE 0 END), 0)         AS locked_count,
                COALESCE(SUM(CASE WHEN commission_status = 'paid'    THEN 1 ELSE 0 END), 0)         AS paid_count,
                COALESCE(SUM(CASE WHEN commission_status = 'pending' THEN deal_value  ELSE 0 END), 0) AS pending_contract,
                COALESCE(SUM(CASE WHEN commission_status = 'locked'  THEN deal_value  ELSE 0 END), 0) AS locked_contract,
                COALESCE(SUM(CASE WHEN commission_status = 'paid'    THEN deal_value  ELSE 0 END), 0) AS paid_contract,
                COALESCE(SUM(CASE WHEN commission_status = 'pending' THEN added_amount * 0.30 ELSE 0 END), 0) AS pending_company,
                COALESCE(SUM(CASE WHEN commission_status = 'locked'  THEN added_amount * 0.30 ELSE 0 END), 0) AS locked_company,
                COALESCE(SUM(CASE WHEN commission_status = 'paid'    THEN added_amount * 0.30 ELSE 0 END), 0) AS paid_company,
                COALESCE(SUM(CASE WHEN commission_status = 'pending' THEN added_amount * 0.70 ELSE 0 END), 0) AS pending_pool,
                COALESCE(SUM(CASE WHEN commission_status = 'locked'  THEN added_amount * 0.70 ELSE 0 END), 0) AS locked_pool
            ")->first();

        $breakdown = [
            ['label' => 'pending', 'count' => (int) $r->pending_count, 'contract' => (float) $r->pending_contract, 'company' => (float) $r->pending_company, 'pool' => (float) $r->pending_pool],
            ['label' => 'locked',  'count' => (int) $r->locked_count,  'contract' => (float) $r->locked_contract,  'company' => (float) $r->locked_company,  'pool' => (float) $r->locked_pool],
            ['label' => 'paid',    'count' => (int) $r->paid_count,    'contract' => (float) $r->paid_contract,    'company' => (float) $r->paid_company,    'pool' => (float) $r->paid_pool],
        ];

        return [
            'total_contract_value' => round((float) $r->total_contract, 2),
            'total_company_share'  => round((float) $r->total_company,  2),
            'total_comm_pool'      => round((float) $r->total_pool,     2),
            'paid_comm_pool'       => round((float) $r->paid_pool,      2),
            'commission_breakdown' => $breakdown,
        ];
    }

    // ── Scheduled report data ─────────────────────────────────

    public function weeklyDigest(): array
    {
        $from = now()->subWeek();

        $newTenants     = Tenant::where('created_at', '>=', $from)->count();
        $newLeads       = Lead::where('created_at', '>=', $from)->count();
        $atRisk         = TenantMetric::where('health_level', 'at_risk')->count();
        $criticalAlerts = Notification::where('priority', 'critical')
            ->where('created_at', '>=', $from)->count();

        return [
            'period'           => 'Weekly — ' . $from->format('M d') . ' to ' . now()->format('M d, Y'),
            'new_tenants'      => $newTenants,
            'new_leads'        => $newLeads,
            'at_risk_tenants'  => $atRisk,
            'critical_alerts'  => $criticalAlerts,
            'summary'          => $this->platformSummary(),
        ];
    }

    public function monthlyReport(): array
    {
        $from = now()->startOfMonth();

        return [
            'period'             => now()->format('F Y'),
            'new_tenants'        => Tenant::where('created_at', '>=', $from)->count(),
            'new_leads'          => Lead::where('created_at', '>=', $from)->count(),
            'closed_deals'       => Lead::where('stage', 'paid')->where('last_updated', '>=', $from)->count(),
            'industry_breakdown' => $this->industryDistribution(),
            'lead_funnel'        => $this->leadFunnel(),
        ];
    }
}
