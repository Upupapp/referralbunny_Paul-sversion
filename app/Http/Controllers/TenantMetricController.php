<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantMetric;
use App\Services\HealthScoreService;
use App\Services\AnalyticsService;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantMetricController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TenantMetric::with('tenant')->orderBy('health_score');
        if ($request->filled('health_level')) $query->where('health_level', $request->health_level);
        return response()->json($query->get());
    }

    public function show(string $tenantId, HealthScoreService $health, AnalyticsService $analytics): JsonResponse
    {
        $tenant = Tenant::with(['config', 'resellers', 'subIndustries'])->findOrFail($tenantId);
        $metric = $health->updateMetrics($tenant);
        $detail = $analytics->tenantDetail($tenant);
        $benchmark = $health->getIndustryBenchmark($tenant);

        return response()->json([
            'metric'    => $metric,
            'detail'    => $detail,
            'benchmark' => $benchmark,
        ]);
    }

    public function recalculate(string $tenantId, HealthScoreService $health): JsonResponse
    {
        $tenant = Tenant::with(['config', 'resellers', 'subIndustries'])->findOrFail($tenantId);
        $metric = $health->updateMetrics($tenant);
        return response()->json($metric);
    }

    public function platformSummary(AnalyticsService $analytics): JsonResponse
    {
        return response()->json($analytics->platformSummary());
    }

    public function growth(AnalyticsService $analytics): JsonResponse
    {
        return response()->json([
            'by_month'   => $analytics->tenantGrowthByMonth(),
            'industries' => $analytics->industryDistribution(),
        ]);
    }

    public function funnel(Request $request, AnalyticsService $analytics): JsonResponse
    {
        return response()->json($analytics->leadFunnel($request->tenant_id ?? null));
    }

    public function weeklyDigest(AnalyticsService $analytics): JsonResponse
    {
        return response()->json($analytics->weeklyDigest());
    }

    public function monthlyReport(AnalyticsService $analytics): JsonResponse
    {
        return response()->json($analytics->monthlyReport());
    }

    // ── Exports ───────────────────────────────────────────────

    public function exportHealth(Request $request, ExportService $export): StreamedResponse
    {
        $csv      = $export->tenantHealthCsv($request->industry, $request->health_level);
        $filename = $export->filename('tenant_health');
        return $this->csvResponse($csv, $filename);
    }

    public function exportNotifications(Request $request, ExportService $export): StreamedResponse
    {
        $csv      = $export->notificationsCsv($request->priority, $request->category, $request->from, $request->to);
        $filename = $export->filename('notifications');
        return $this->csvResponse($csv, $filename);
    }

    public function exportLeads(Request $request, ExportService $export): StreamedResponse
    {
        $csv      = $export->leadsCsv($request->tenant_id, $request->stage, $request->status);
        $filename = $export->filename('leads');
        return $this->csvResponse($csv, $filename);
    }

    private function csvResponse(string $content, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
