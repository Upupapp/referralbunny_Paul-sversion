<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantMetric;
use App\Models\Lead;
use Carbon\Carbon;

class HealthScoreService
{
    public function calculate(Tenant $tenant): int
    {
        $score = 0;
        $metric = TenantMetric::where('tenant_id', $tenant->id)->first();

        // 1. Lead creation activity (0-20)
        $leadsCount = Lead::where('tenant_id', $tenant->id)->count();
        $score += min(20, $leadsCount * 4);

        // 2. Lead update activity — leads updated in last 7 days (0-20)
        $recentActivity = Lead::where('tenant_id', $tenant->id)
            ->where('last_updated', '>=', now()->subDays(7))
            ->count();
        $score += min(20, $recentActivity * 5);

        // 3. Setup completion (0-20)
        $setupScore = $this->calculateSetupCompletion($tenant);
        $score += (int) ($setupScore * 0.20);

        // 4. Has config (0-15)
        if ($tenant->config) {
            $score += 15;
        }

        // 5. Has resellers (0-15)
        $resellersCount = $tenant->resellers()->count();
        $score += min(15, $resellersCount * 5);

        // 6. Payment status (0-10)
        if ($metric) {
            $score += match ($metric->payment_status) {
                'paid'    => 10,
                'unknown' => 5,
                'overdue' => 2,
                'failed'  => 0,
                default   => 0,
            };
        }

        return min(100, max(0, $score));
    }

    public function getLevel(int $score): string
    {
        return match (true) {
            $score >= 80 => 'healthy',
            $score >= 50 => 'needs_attention',
            default      => 'at_risk',
        };
    }

    public function calculateSetupCompletion(Tenant $tenant): int
    {
        $steps = [
            'has_name'         => !empty($tenant->name),
            'has_email'        => !empty($tenant->admin_email),
            'has_industry'     => !empty($tenant->industry),
            'has_config'       => $tenant->config !== null,
            'has_resellers'    => $tenant->resellers()->exists(),
            'has_leads'        => Lead::where('tenant_id', $tenant->id)->exists(),
            'has_description'  => !empty($tenant->description),
            'has_sub_industry' => $tenant->subIndustries()->exists(),
        ];

        $completed = count(array_filter($steps));
        return (int) round(($completed / count($steps)) * 100);
    }

    public function updateMetrics(Tenant $tenant): TenantMetric
    {
        $score       = $this->calculate($tenant);
        $level       = $this->getLevel($score);
        $setup       = $this->calculateSetupCompletion($tenant);
        $leadsCount  = Lead::where('tenant_id', $tenant->id)->count();
        $staleLeads  = Lead::where('tenant_id', $tenant->id)
            ->where('status', 'expired')
            ->count();

        $metric = TenantMetric::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'health_score'               => $score,
                'health_level'               => $level,
                'leads_count'                => $leadsCount,
                'stale_leads_count'          => $staleLeads,
                'setup_completion_percentage'=> $setup,
                'last_activity_at'           => now(),
                'updated_at'                 => now(),
            ]
        );

        return $metric;
    }

    public function getIndustryBenchmark(Tenant $tenant): array
    {
        $industry = (string) ($tenant->industry ?? '');
        if (empty($industry)) {
            return [];
        }

        $peers = Tenant::where('industry', $industry)
            ->where('id', '!=', $tenant->id)
            ->with('leads')
            ->get();

        if ($peers->isEmpty()) {
            return [];
        }

        $peerLeadCounts = $peers->map(fn($p) => Lead::where('tenant_id', $p->id)->count());
        $avgLeads = $peerLeadCounts->avg();

        $myLeads = Lead::where('tenant_id', $tenant->id)->count();
        $diff    = $avgLeads > 0 ? round((($myLeads - $avgLeads) / $avgLeads) * 100) : 0;

        $peerScores = $peers->map(function ($p) {
            $m = TenantMetric::where('tenant_id', $p->id)->first();
            return $m ? $m->health_score : 0;
        });

        return [
            'industry'          => $industry,
            'peer_count'        => $peers->count(),
            'avg_leads'         => round($avgLeads, 1),
            'my_leads'          => $myLeads,
            'leads_diff_pct'    => $diff,
            'avg_health_score'  => round($peerScores->avg()),
            'my_health_score'   => TenantMetric::where('tenant_id', $tenant->id)->value('health_score') ?? 0,
            'performance_label' => $diff > 0 ? "{$diff}% above industry average" : abs($diff) . "% below industry average",
        ];
    }
}
