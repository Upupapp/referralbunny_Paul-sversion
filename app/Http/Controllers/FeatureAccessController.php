<?php

namespace App\Http\Controllers;

use App\Models\TenantOverride;
use App\Services\FeatureAccessService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeatureAccessController extends Controller
{
    public function __construct(private FeatureAccessService $featureAccess) {}

    // GET /api/feature-access/usage?tenant_id=xxx
    public function usage(Request $request): JsonResponse
    {
        $request->validate(['tenant_id' => 'required|string|exists:tenants,id']);
        return response()->json($this->featureAccess->getUsageSummary($request->tenant_id));
    }

    // GET /api/feature-access/check?tenant_id=xxx&feature=messaging
    public function check(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id' => 'required|string|exists:tenants,id',
            'feature'   => 'required|string',
        ]);
        return response()->json($this->featureAccess->checkFeature($request->tenant_id, $request->feature));
    }

    // GET /api/feature-access/limit?tenant_id=xxx&resource=leads
    public function limit(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id' => 'required|string|exists:tenants,id',
            'resource'  => 'required|string',
        ]);
        return response()->json($this->featureAccess->checkLimit($request->tenant_id, $request->resource));
    }

    // POST /api/tenant-overrides
    public function createOverride(Request $request): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Only super admins can manage feature overrides.');
        $data = $request->validate([
            'tenant_id'             => 'required|string|exists:tenants,id',
            'feature_name'          => 'required|string',
            'override_value'        => 'required|string',
            'expires_at'            => 'nullable|date',
            'approval_reference_id' => 'nullable|string',
            'notes'                 => 'nullable|string',
        ]);

        $override = $this->featureAccess->applyOverride(
            tenantId:    $data['tenant_id'],
            featureName: $data['feature_name'],
            value:       $data['override_value'],
            approvedBy:  $request->user()->id,
            expiresAt:   $data['expires_at'] ?? null,
            referenceId: $data['approval_reference_id'] ?? null,
            notes:       $data['notes'] ?? null,
        );

        return response()->json($override, 201);
    }

    // GET /api/tenant-overrides/{tenantId}
    public function listOverrides(string $tenantId): JsonResponse
    {
        $overrides = TenantOverride::where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($overrides);
    }

    // DELETE /api/tenant-overrides/{override}
    public function deleteOverride(TenantOverride $override): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Only super admins can manage feature overrides.');
        $override->delete();
        return response()->json(['message' => 'Override removed.']);
    }

    // POST /api/feature-access/grace-period
    public function startGracePeriod(Request $request): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Only super admins can manage feature overrides.');
        $data = $request->validate([
            'tenant_id' => 'required|string|exists:tenants,id',
            'days'      => 'nullable|integer|min:1|max:30',
        ]);

        $this->featureAccess->startGracePeriod($data['tenant_id'], $data['days'] ?? 7);
        return response()->json(['message' => 'Grace period started.']);
    }

    // POST /api/feature-access/reset-usage
    public function resetUsage(Request $request): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Only super admins can manage feature overrides.');
        $request->validate(['tenant_id' => 'required|string|exists:tenants,id']);
        $this->featureAccess->resetMonthlyUsage($request->tenant_id);
        return response()->json(['message' => 'Monthly usage reset.']);
    }
}
