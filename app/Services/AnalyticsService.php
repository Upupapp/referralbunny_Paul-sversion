<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantMetric;
use App\Models\Lead;
use App\Models\Notification;
use Carbon\Carbon;

class AnalyticsService
{
    // ── Platform-level analytics ──────────────────────────────

    public function platformSummary(): array
    {
        $tenants      = Tenant::with('leads')->get();
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
        $leads  = Lead::where('tenant_id', $tenant->id)->get();
        $metric = TenantMetric::where('tenant_id', $tenant->id)->first();

        $byStage  = $leads->groupBy('stage')->map->count();
        $byStatus = $leads->groupBy('status')->map->count();

        return [
            'tenant_id'        => $tenant->id,
            'health_score'     => $metric?->health_score ?? 0,
            'health_level'     => $metric?->health_level ?? 'at_risk',
            'setup_completion' => $metric?->setup_completion_percentage ?? 0,
            'leads_total'      => $leads->count(),
            'leads_active'     => $byStatus['active']   ?? 0,
            'leads_expired'    => $byStatus['expired']  ?? 0,
            'leads_paid'       => $byStage['paid']      ?? 0,
            'pipeline_value'   => $leads->sum('deal_value'),
            'closed_value'     => $leads->where('stage', 'paid')->sum('deal_value'),
            'by_stage'         => $byStage,
            'by_status'        => $byStatus,
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
