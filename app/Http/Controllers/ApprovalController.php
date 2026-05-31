<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Services\ApprovalService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function __construct(private ApprovalService $approvals) {}

    // GET /api/approvals
    public function queue(): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Super admin access required.');
        return response()->json($this->approvals->getQueue());
    }

    // GET /api/approvals/history
    public function history(): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Super admin access required.');
        return response()->json($this->approvals->getHistory());
    }

    // GET /api/approvals/{approval}
    public function show(ApprovalRequest $approval): JsonResponse
    {
        if (!TenantContext::isSuperAdmin()) {
            abort_unless(TenantContext::id() === $approval->tenant_id, 403);
            abort_unless(in_array(TenantContext::role(), ['owner', 'admin']), 403);
        }
        return response()->json($approval->load(['requestedBy', 'approvedBy']));
    }

    // POST /api/approvals/{approval}/approve
    public function approve(Request $request, ApprovalRequest $approval): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Only super admins can approve requests.');

        if (!$approval->isPending()) {
            return response()->json(['message' => 'Request is no longer pending.'], 422);
        }

        $data = $request->validate(['notes' => 'nullable|string|max:500']);
        $this->approvals->approve($approval, $request->user()->id, $data['notes'] ?? null);
        try {
            $cs = app(\App\Services\CriticalActionService::class);
            $adminIds = $cs->invalidateAllAdminBadges($approval->tenant_id);
            \Illuminate\Support\Facades\Cache::deleteMultiple($adminIds->map(fn($uid) => "notif_unread_tenant_admin_{$uid}")->toArray());
        } catch (\Throwable) {}

        return response()->json(['message' => 'Approved.', 'approval' => $approval->fresh()]);
    }

    // POST /api/approvals/{approval}/reject
    public function reject(Request $request, ApprovalRequest $approval): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Only super admins can reject requests.');

        if (!$approval->isPending()) {
            return response()->json(['message' => 'Request is no longer pending.'], 422);
        }

        $data = $request->validate(['notes' => 'nullable|string|max:500']);
        $this->approvals->reject($approval, $request->user()->id, $data['notes'] ?? null);
        try {
            $cs = app(\App\Services\CriticalActionService::class);
            $adminIds = $cs->invalidateAllAdminBadges($approval->tenant_id);
            \Illuminate\Support\Facades\Cache::deleteMultiple($adminIds->map(fn($uid) => "notif_unread_tenant_admin_{$uid}")->toArray());
        } catch (\Throwable) {}

        return response()->json(['message' => 'Rejected.', 'approval' => $approval->fresh()]);
    }
}
