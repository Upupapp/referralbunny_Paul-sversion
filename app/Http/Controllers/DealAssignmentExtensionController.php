<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\DealAssignmentExtensionService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DealAssignmentExtensionController extends Controller
{
    public function __construct(private DealAssignmentExtensionService $service) {}

    /**
     * GET /api/leads/{lead}/extension-requests
     * List extension requests for a deal.
     */
    public function forDeal(Request $request, Lead $lead): JsonResponse
    {
        $tenantId = TenantContext::id() ?? $request->query('tenant_id');
        if (!$tenantId || $lead->tenant_id !== $tenantId) {
            return response()->json(['error' => 'Deal not found.'], 404);
        }

        $requests = $this->service->getForDeal($tenantId, $lead->id);
        return response()->json($requests);
    }

    /**
     * GET /api/extension-requests
     * List all extension requests for the tenant (Admin/Manager view).
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = TenantContext::id() ?? $request->query('tenant_id');
        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context.'], 403);
        }

        if (!$this->isAdminOrManager()) {
            return response()->json(['error' => 'Not authorized.'], 403);
        }

        $status   = $request->query('status');
        $requests = $this->service->getForTenant($tenantId, $status ?: null);

        return response()->json($requests);
    }

    /**
     * GET /api/extension-requests/{id}
     * Fetch a single extension request by ID (for the modal).
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $req = \App\Models\DealAssignmentExtensionRequest::with('lead:id,name,reseller_name,tenant_id')->find($id);
        if (!$req) return response()->json(['error' => 'Not found.'], 404);

        $tenantId = TenantContext::id() ?? $request->query('tenant_id');
        if ($tenantId && $req->tenant_id !== $tenantId) {
            return response()->json(['error' => 'Not found.'], 404);
        }

        return response()->json(array_merge($req->toArray(), [
            'deal_name'          => $req->lead?->name,
            'reseller_name'      => $req->lead?->reseller_name,
            'requested_by_name'  => $req->lead?->reseller_name ?? ucfirst($req->requested_by_role ?? 'Referrer'),
        ]));
    }

    /**
     * POST /api/leads/{lead}/extension-requests
     * Submit a new extension request (Referrer or Admin).
     */
    public function store(Request $request, Lead $lead): JsonResponse
    {
        $tenantId = TenantContext::id() ?? $request->input('tenant_id');
        if (!$tenantId || $lead->tenant_id !== $tenantId) {
            return response()->json(['error' => 'Deal not found.'], 404);
        }

        $data = $request->validate([
            'requested_days' => 'required|integer|min:1|max:90',
            'reason'         => 'required|string|min:10|max:1000',
        ]);

        [$actorId, $actorRole] = $this->resolveActor();

        try {
            $extRequest = $this->service->createRequest(
                tenantId:          $tenantId,
                dealId:            $lead->id,
                requestedByUserId: $actorId,
                requestedByRole:   $actorRole,
                requestedDays:     (int) $data['requested_days'],
                reason:            $data['reason'],
                actorId:           $actorId,
            );

            return response()->json([
                'success' => true,
                'message' => 'Extension request submitted. The Tenant Admin will review it shortly.',
                'request' => $extRequest,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/extension-requests/{id}/approve
     * Approve an extension request (Admin/Manager only).
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $tenantId = TenantContext::id() ?? $request->input('tenant_id');
        if (!$tenantId || !$this->isAdminOrManager()) {
            return response()->json(['error' => 'Not authorized.'], 403);
        }

        $data = $request->validate([
            'approved_days' => 'required|integer|min:1|max:90',
            'admin_note'    => 'nullable|string|max:500',
        ]);

        try {
            [$actorId] = $this->resolveActor();
            $extRequest = $this->service->approve(
                requestId:      $id,
                tenantId:       $tenantId,
                reviewerUserId: $actorId,
                approvedDays:   (int) $data['approved_days'],
                adminNote:      $data['admin_note'] ?? null,
            );

            try { app(\App\Services\CriticalActionService::class)->invalidateCache($tenantId); } catch (\Throwable) {}
            return response()->json([
                'success' => true,
                'message' => "Extension approved. {$extRequest->approved_days} days added to the deal.",
                'request' => $extRequest,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/extension-requests/{id}/reject
     * Reject an extension request (Admin/Manager only).
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $tenantId = TenantContext::id() ?? $request->input('tenant_id');
        if (!$tenantId || !$this->isAdminOrManager()) {
            return response()->json(['error' => 'Not authorized.'], 403);
        }

        $data = $request->validate([
            'reason' => 'required|string|min:5|max:500',
        ]);

        try {
            [$actorId] = $this->resolveActor();
            $extRequest = $this->service->reject(
                requestId:      $id,
                tenantId:       $tenantId,
                reviewerUserId: $actorId,
                reason:         $data['reason'],
            );

            try { app(\App\Services\CriticalActionService::class)->invalidateCache($tenantId); } catch (\Throwable) {}
            return response()->json([
                'success' => true,
                'message' => 'Extension request rejected.',
                'request' => $extRequest,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/extension-requests/{id}/clarify
     * Request clarification (Admin/Manager only).
     */
    public function clarify(Request $request, string $id): JsonResponse
    {
        $tenantId = TenantContext::id() ?? $request->input('tenant_id');
        if (!$tenantId || !$this->isAdminOrManager()) {
            return response()->json(['error' => 'Not authorized.'], 403);
        }

        $data = $request->validate([
            'note' => 'required|string|min:5|max:500',
        ]);

        try {
            [$actorId] = $this->resolveActor();
            $extRequest = $this->service->requestClarification(
                requestId:      $id,
                tenantId:       $tenantId,
                reviewerUserId: $actorId,
                note:           $data['note'],
            );

            try { app(\App\Services\CriticalActionService::class)->invalidateCache($tenantId); } catch (\Throwable) {}
            return response()->json([
                'success' => true,
                'message' => 'Clarification requested from the Referrer.',
                'request' => $extRequest,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function isAdminOrManager(): bool
    {
        return Auth::guard('tenant')->check() || Auth::guard('web')->check();
    }

    private function resolveActor(): array
    {
        if (Auth::guard('tenant')->check()) {
            return [Auth::guard('tenant')->user()->id, 'manager'];
        }
        if (Auth::guard('web')->check()) {
            return [Auth::guard('web')->user()->id, 'admin'];
        }
        if (Auth::guard('reseller')->check()) {
            return [Auth::guard('reseller')->user()->id, 'referrer'];
        }
        return ['system', 'system'];
    }
}
