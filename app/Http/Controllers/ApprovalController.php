<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Services\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function __construct(private ApprovalService $approvals) {}

    // GET /api/approvals
    public function queue(): JsonResponse
    {
        return response()->json($this->approvals->getQueue());
    }

    // GET /api/approvals/history
    public function history(): JsonResponse
    {
        return response()->json($this->approvals->getHistory());
    }

    // GET /api/approvals/{approval}
    public function show(ApprovalRequest $approval): JsonResponse
    {
        return response()->json($approval->load(['requestedBy', 'approvedBy']));
    }

    // POST /api/approvals/{approval}/approve
    public function approve(Request $request, ApprovalRequest $approval): JsonResponse
    {
        if (!$approval->isPending()) {
            return response()->json(['message' => 'Request is no longer pending.'], 422);
        }

        $data = $request->validate(['notes' => 'nullable|string|max:500']);
        $this->approvals->approve($approval, $request->user()->id, $data['notes'] ?? null);

        return response()->json(['message' => 'Approved.', 'approval' => $approval->fresh()]);
    }

    // POST /api/approvals/{approval}/reject
    public function reject(Request $request, ApprovalRequest $approval): JsonResponse
    {
        if (!$approval->isPending()) {
            return response()->json(['message' => 'Request is no longer pending.'], 422);
        }

        $data = $request->validate(['notes' => 'nullable|string|max:500']);
        $this->approvals->reject($approval, $request->user()->id, $data['notes'] ?? null);

        return response()->json(['message' => 'Rejected.', 'approval' => $approval->fresh()]);
    }
}
