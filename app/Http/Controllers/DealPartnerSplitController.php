<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\DealActivityService;
use App\Services\DealPartnerSplitService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DealPartnerSplitController extends Controller
{
    public function __construct(
        private DealPartnerSplitService $service,
        private DealActivityService     $activity,
    ) {}

    /**
     * GET /api/leads/{lead}/partner-splits
     */
    public function index(Request $request, Lead $lead): JsonResponse
    {
        $tenantId = TenantContext::id() ?? $request->query('tenant_id');
        if (!$tenantId || $lead->tenant_id !== $tenantId) {
            return response()->json(['error' => 'Deal not found in this tenant.'], 404);
        }

        $splits = $this->service->getForDeal($tenantId, $lead->id);
        $total  = $this->service->totalPercentage($tenantId, $lead->id);

        return response()->json([
            'splits'           => $splits,
            'total_percentage' => $total,
        ]);
    }

    /**
     * POST /api/leads/{lead}/partner-splits
     */
    public function store(Request $request, Lead $lead): JsonResponse
    {
        $tenantId = TenantContext::id() ?? $request->input('tenant_id');
        if (!$tenantId || $lead->tenant_id !== $tenantId) {
            return response()->json(['error' => 'Deal not found in this tenant.'], 404);
        }

        $data = $request->validate([
            'partner_name'      => 'required|string|max:255',
            'partner_email'     => 'required|email|max:255',
            'split_share_value' => 'required|numeric|min:0',
            'split_share_type'  => 'nullable|in:percentage,fixed_amount',
            'currency'          => 'nullable|string|max:10',
            'source'            => 'nullable|in:manual,import,admin_edit,referrer_added',
        ]);

        try {
            $split = $this->service->upsert(
                tenantId:     $tenantId,
                dealId:       $lead->id,
                partnerName:  $data['partner_name'],
                partnerEmail: $data['partner_email'],
                splitValue:   (float) $data['split_share_value'],
                splitType:    $data['split_share_type']  ?? 'percentage',
                currency:     $data['currency']           ?? 'PHP',
                source:       $data['source']             ?? 'manual',
                actorId:      $this->resolveActorId(),
            );

            // Record activity — this was completely missing before
            $this->activity->partnerSplitAdded($lead, [
                'partner_name'      => $data['partner_name'],
                'partner_email'     => $data['partner_email'],
                'split_share_value' => $data['split_share_value'],
                'split_share_type'  => $data['split_share_type'] ?? 'percentage',
            ]);

            return response()->json($split, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * PUT /api/leads/{lead}/partner-splits/{splitId}
     */
    public function update(Request $request, Lead $lead, string $splitId): JsonResponse
    {
        $tenantId = TenantContext::id() ?? $request->input('tenant_id');
        if (!$tenantId || $lead->tenant_id !== $tenantId) {
            return response()->json(['error' => 'Deal not found in this tenant.'], 404);
        }

        $data = $request->validate([
            'partner_name'      => 'required|string|max:255',
            'partner_email'     => 'required|email|max:255',
            'split_share_value' => 'required|numeric|min:0',
            'split_share_type'  => 'nullable|in:percentage,fixed_amount',
            'currency'          => 'nullable|string|max:10',
        ]);

        // Capture old split BEFORE the update so we can record old→new
        $oldSplit = DB::table('deal_partner_splits')
            ->where('id', $splitId)
            ->where('tenant_id', $tenantId)
            ->first();

        try {
            $split = $this->service->upsert(
                tenantId:        $tenantId,
                dealId:          $lead->id,
                partnerName:     $data['partner_name'],
                partnerEmail:    $data['partner_email'],
                splitValue:      (float) $data['split_share_value'],
                splitType:       $data['split_share_type'] ?? 'percentage',
                currency:        $data['currency']          ?? 'PHP',
                source:          'admin_edit',
                actorId:         $this->resolveActorId(),
                existingSplitId: $splitId,
            );

            // Record old→new activity
            if ($oldSplit) {
                $this->activity->partnerSplitUpdated($lead,
                    [
                        'partner_name'      => $oldSplit->partner_name,
                        'partner_email'     => $oldSplit->partner_email,
                        'split_share_value' => $oldSplit->split_share_value,
                        'split_share_type'  => $oldSplit->split_share_type,
                    ],
                    [
                        'partner_name'      => $data['partner_name'],
                        'partner_email'     => $data['partner_email'],
                        'split_share_value' => $data['split_share_value'],
                        'split_share_type'  => $data['split_share_type'] ?? 'percentage',
                    ]
                );
            }

            return response()->json($split);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * DELETE /api/leads/{lead}/partner-splits/{splitId}
     */
    public function destroy(Request $request, Lead $lead, string $splitId): JsonResponse
    {
        $tenantId = TenantContext::id() ?? $request->query('tenant_id');
        if (!$tenantId || $lead->tenant_id !== $tenantId) {
            return response()->json(['error' => 'Deal not found in this tenant.'], 404);
        }

        // Capture split data BEFORE removal for activity recording
        $oldSplit = DB::table('deal_partner_splits')
            ->where('id', $splitId)
            ->where('tenant_id', $tenantId)
            ->first();

        try {
            $this->service->remove($tenantId, $splitId, $this->resolveActorId());

            // Record removal activity
            if ($oldSplit) {
                $this->activity->partnerSplitRemoved($lead, [
                    'partner_name'      => $oldSplit->partner_name,
                    'partner_email'     => $oldSplit->partner_email,
                    'split_share_value' => $oldSplit->split_share_value,
                    'split_share_type'  => $oldSplit->split_share_type,
                ]);
            }

            return response()->json(['success' => true, 'message' => 'Partner split removed.']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    private function resolveActorId(): string
    {
        return Auth::guard('tenant')->user()?->id
            ?? Auth::guard('web')->user()?->id
            ?? Auth::guard('reseller')->user()?->id
            ?? 'system';
    }
}
