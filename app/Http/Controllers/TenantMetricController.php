<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantMetric;
use App\Services\HealthScoreService;
use App\Services\AnalyticsService;
use App\Services\ExportService;
use App\Services\TenantContext;
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
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Super admin access required.');
        return response()->json($analytics->platformSummary());
    }

    public function growth(AnalyticsService $analytics): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Super admin access required.');
        return response()->json([
            'by_month'   => $analytics->tenantGrowthByMonth(),
            'industries' => $analytics->industryDistribution(),
        ]);
    }

    public function funnel(Request $request, AnalyticsService $analytics): JsonResponse
    {
        // SA can filter by any tenant; everyone else is locked to their own tenant
        $tenantId = TenantContext::isSuperAdmin()
            ? ($request->tenant_id ?? null)
            : TenantContext::requireId();

        return response()->json($analytics->leadFunnel($tenantId));
    }

    public function weeklyDigest(AnalyticsService $analytics): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Super admin access required.');
        return response()->json($analytics->weeklyDigest());
    }

    public function monthlyReport(AnalyticsService $analytics): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Super admin access required.');
        return response()->json($analytics->monthlyReport());
    }

    public function financialSummary(string $tenantId, AnalyticsService $analytics): JsonResponse
    {
        if (!TenantContext::isSuperAdmin() && TenantContext::id() !== $tenantId) {
            abort(403, 'Access denied.');
        }
        return response()->json($analytics->tenantFinancialSummary($tenantId));
    }

    // ── Exports ───────────────────────────────────────────────

    public function exportHealth(Request $request, ExportService $export): StreamedResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Super admin access required.');
        $csv      = $export->tenantHealthCsv($request->industry, $request->health_level);
        $filename = $export->filename('tenant_health');
        return $this->csvResponse($csv, $filename);
    }

    public function exportNotifications(Request $request, ExportService $export): StreamedResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Super admin access required.');
        $csv      = $export->notificationsCsv($request->priority, $request->category, $request->from, $request->to);
        $filename = $export->filename('notifications');
        return $this->csvResponse($csv, $filename);
    }

    public function exportLeads(Request $request, ExportService $export): StreamedResponse
    {
        // SA can export any tenant; tenant admins are locked to their own tenant
        $tenantId = TenantContext::isSuperAdmin()
            ? ($request->tenant_id ?? null)
            : TenantContext::requireId();

        $csv      = $export->leadsCsv($tenantId, $request->stage, $request->status);
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
