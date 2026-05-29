<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Reseller;
use App\Services\DealActivityService;
use App\Services\DealPartnerSplitService;
use App\Services\NotificationDispatchService;
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
        $tenantId = TenantContext::id();
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
        $tenantId = TenantContext::id();
        if (!$tenantId || $lead->tenant_id !== $tenantId) {
            return response()->json(['error' => 'Deal not found in this tenant.'], 404);
        }

        $data = $request->validate([
            'partner_name'      => 'required|string|max:255',
            'partner_email'     => 'nullable|email|max:255', // optional — triggers invite when provided
            'split_share_value' => 'required|numeric|min:0',
            'split_share_type'  => 'nullable|in:percentage,fixed_amount',
            'currency'          => 'nullable|string|max:10',
            'source'            => 'nullable|in:manual,import,admin_edit,referrer_added',
        ]);

        $partnerEmail = !empty($data['partner_email']) ? strtolower(trim($data['partner_email'])) : null;

        try {
            $split = $this->service->upsert(
                tenantId:     $tenantId,
                dealId:       $lead->id,
                partnerName:  $data['partner_name'],
                partnerEmail: $partnerEmail ?? '',
                splitValue:   (float) $data['split_share_value'],
                splitType:    $data['split_share_type']  ?? 'percentage',
                currency:     $data['currency']           ?? 'PHP',
                source:       $data['source']             ?? 'manual',
                actorId:      $this->resolveActorId(),
            );

            // Record activity
            $this->activity->partnerSplitAdded($lead, [
                'partner_name'      => $data['partner_name'],
                'partner_email'     => $partnerEmail,
                'split_share_value' => $data['split_share_value'],
                'split_share_type'  => $data['split_share_type'] ?? 'percentage',
            ]);

            // Notify assigned referrer
            $this->notifyAssignedReferrer(
                $lead,
                'Partner added to your deal',
                $data['partner_name'] . ' was added as a Partner to "' . $lead->name . '" with a ' . $data['split_share_value'] . ($data['split_share_type'] === 'fixed_amount' ? ' (fixed)' : '%') . ' split.',
                $lead->id . ':partner_added:' . md5($partnerEmail ?? $data['partner_name']),
            );

            // Notify the partner themselves (if they have an account)
            try {
                $partnerUserId = $partnerEmail
                    ? \Illuminate\Support\Facades\DB::table('partner_users')
                        ->where('tenant_id', $lead->tenant_id)
                        ->whereRaw('LOWER(email) = ?', [$partnerEmail])
                        ->value('id')
                    : null;

                if ($partnerUserId) {
                    app(\App\Services\NotificationDispatchService::class)->dispatchToPartner(
                        partnerId:    (string) $partnerUserId,
                        tenantId:     $lead->tenant_id,
                        category:     'deal_pipeline',
                        priority:     'high',
                        title:        'You were added as a Partner to a deal',
                        body:         'You have been added as a Partner to "' . $lead->name . '" with a ' . $data['split_share_value'] . ($data['split_share_type'] === 'fixed_amount' ? ' (fixed)' : '%') . ' commission split.',
                        actionUrl:    url("/partner/deals/{$lead->id}"),
                        actionLabel:  'View Deal',
                        dedupeSuffix: "{$lead->id}:partner_added_self:{$partnerUserId}",
                    );
                }
            } catch (\Throwable) {}

            try { app(\App\Services\CriticalActionService::class)->invalidateCache($lead->tenant_id, $this->resolveActorId()); } catch (\Throwable) {}

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
        $tenantId = TenantContext::id();
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

            // Notify assigned referrer
            $this->notifyAssignedReferrer(
                $lead,
                'Partner split updated',
                'The split for ' . $data['partner_name'] . ' on "' . $lead->name . '" was updated to ' . $data['split_share_value'] . ($data['split_share_type'] === 'fixed_amount' ? ' (fixed)' : '%') . '.',
                $lead->id . ':partner_updated:' . $splitId . ':v' . md5((string)($data['split_share_value'] ?? '') . ($data['split_share_type'] ?? 'percentage')),
            );

            try { app(\App\Services\CriticalActionService::class)->invalidateCache($lead->tenant_id, $this->resolveActorId()); } catch (\Throwable) {}

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
        $tenantId = TenantContext::id();
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

                // Dedup per deal + 5-minute window so removing multiple partners quickly
                // sends one notification to the referrer, not one per split.
                // Body is generic ("a partner") because only the first removal fires within the window;
                // referrer can view the full partner list on the deal page via the "View Deal" CTA.
                // The 'admin:' segment scopes this key to admin-initiated removals, preventing
                // collision with any future referrer-initiated removal keys on the same deal.
                $dedupWindow = (int) floor(time() / 300);
                $this->notifyAssignedReferrer(
                    $lead,
                    'Partner removed from your deal',
                    'A partner was removed from "' . $lead->name . '". View the deal to see the current partner list.',
                    $lead->id . ':partner_removed:admin:' . $dedupWindow,
                );

                // Notify the removed partner
                if (!empty($oldSplit->partner_user_id)) {
                    try {
                        app(NotificationDispatchService::class)->dispatchToPartner(
                            partnerId:    (string) $oldSplit->partner_user_id,
                            tenantId:     $tenantId,
                            category:     'deal_pipeline',
                            priority:     'high',
                            title:        'You have been removed from a deal',
                            body:         'You have been removed from "' . $lead->name . '". Contact your referrer or admin for more information.',
                            actionUrl:    null,
                            actionLabel:  null,
                            dedupeSuffix: $lead->id . ':partner_removed:' . $splitId,
                        );
                    } catch (\Throwable) {}
                }
            }

            try { app(\App\Services\CriticalActionService::class)->invalidateCache($lead->tenant_id, $this->resolveActorId()); } catch (\Throwable) {}

            return response()->json(['success' => true, 'message' => 'Partner split removed.']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    private function resolveActorId(): ?string
    {
        return Auth::guard('tenant')->user()?->id
            ?? Auth::guard('web')->user()?->id
            ?? Auth::guard('reseller')->user()?->id
            ?? null;
    }

    /** Notify the Referrer assigned to a deal. Never throws — best-effort only. */
    private function notifyAssignedReferrer(Lead $lead, string $title, string $body, string $dedupSuffix): void
    {
        try {
            if (!$lead->reseller_name) return;
            $reseller = Reseller::where('tenant_id', $lead->tenant_id)
                ->where('name', $lead->reseller_name)
                ->first();
            if (!$reseller) return;
            app(NotificationDispatchService::class)->dispatchToReseller(
                resellerId:   (string) $reseller->id,
                tenantId:     $lead->tenant_id,
                category:     'deal_pipeline',
                priority:     'normal',
                title:        $title,
                body:         $body,
                actionUrl:    "/reseller/{$lead->tenant_id}/deals/{$lead->id}",
                actionLabel:  'View Deal',
                dedupeSuffix: $dedupSuffix,
            );
        } catch (\Throwable) {}
    }
}
