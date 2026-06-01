<?php

namespace App\Services;

use App\Events\DealExtensionApproved;
use App\Models\ActivityLog;
use App\Models\DealAssignmentExtensionRequest;
use App\Models\Lead;
use App\Models\Notification;
use App\Services\CriticalActionService;
use App\Services\EmailLogger;
use App\Services\NotificationDispatchService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Manages LGU IDS deal assignment extension requests.
 *
 * Rules:
 * - Only one pending extension request per deal per requester at a time.
 * - Extension approval ONLY updates the specific deal's days_left.
 * - Does NOT change global LGU IDS stage time limits.
 * - LGU IDS stage limits (14/21/30/30) remain locked as platform rules.
 * - Extension adds extra days on top of remaining days, bounded by approved_days.
 */
class DealAssignmentExtensionService
{
    /**
     * Create a new extension request.
     *
     * @throws \InvalidArgumentException
     */
    public function createRequest(
        string $tenantId,
        string $dealId,
        string $requestedByUserId,
        string $requestedByRole,
        int    $requestedDays,
        string $reason,
        string $actorId = 'system'
    ): DealAssignmentExtensionRequest {
        // Validate deal belongs to tenant
        $deal = Lead::where('id', $dealId)->where('tenant_id', $tenantId)->firstOrFail();

        // Block duplicate pending request for same deal
        $existing = DealAssignmentExtensionRequest::where('tenant_id', $tenantId)
            ->where('deal_id', $dealId)
            ->where('requested_by_user_id', $requestedByUserId)
            ->where('status', 'pending_review')
            ->first();

        if ($existing) {
            throw new \InvalidArgumentException(
                'You already have a pending extension request for this deal.'
            );
        }

        if ($requestedDays < 1 || $requestedDays > 90) {
            throw new \InvalidArgumentException('Requested extension must be between 1 and 90 days.');
        }

        if (empty(trim($reason))) {
            throw new \InvalidArgumentException('A reason is required for the extension request.');
        }

        $currentDaysLeft   = $deal->days_left ?? 0;
        $currentExpiryAt   = now()->addDays(max(0, $currentDaysLeft));
        $requestedExpiryAt = $currentExpiryAt->copy()->addDays($requestedDays);

        $request = DealAssignmentExtensionRequest::create([
            'tenant_id'               => $tenantId,
            'deal_id'                 => $dealId,
            'requested_by_user_id'    => $requestedByUserId,
            'requested_by_role'       => $requestedByRole,
            'current_stage'           => $deal->stage,
            'current_expiry_at'       => $currentExpiryAt,
            'current_days_left'       => $currentDaysLeft,
            'requested_days'          => $requestedDays,
            'requested_new_expiry_at' => $requestedExpiryAt,
            'reason'                  => trim($reason),
            'status'                  => 'pending_review',
        ]);

        $this->notifyAdminsOfNewRequest($tenantId, $deal, $request);
        $this->audit($tenantId, $dealId, $request->id, 'deal_extension_requested', $actorId, [
            'requested_days'   => $requestedDays,
            'current_days_left'=> $currentDaysLeft,
        ]);

        return $request;
    }

    /**
     * Approve an extension request. Updates the deal's days_left.
     * Does NOT alter global stage rules.
     */
    public function approve(
        string  $requestId,
        string  $tenantId,
        ?string $reviewerUserId,
        int     $approvedDays,
        ?string $adminNote = null
    ): DealAssignmentExtensionRequest {
        $request = $this->loadForReview($requestId, $tenantId);
        $deal    = Lead::where('id', $request->deal_id)->where('tenant_id', $tenantId)->firstOrFail();

        if ($approvedDays < 1) {
            throw new \InvalidArgumentException('Approved days must be at least 1.');
        }

        // Computed inside transaction from the locked row to prevent stale reads
        $newDaysLeft      = 0;
        $approvedExpiryAt = now();

        DB::transaction(function () use ($request, $deal, $approvedDays, $adminNote, $reviewerUserId, &$newDaysLeft, &$approvedExpiryAt) {
            $lockedLead       = DB::table('leads')->where('id', $deal->id)->lockForUpdate()->first();
            $newDaysLeft      = max(0, ($lockedLead->days_left ?? 0)) + $approvedDays;
            $approvedExpiryAt = now()->addDays($newDaysLeft);

            DB::table('leads')->where('id', $deal->id)->update([
                'days_left'  => $newDaysLeft,
                'status'     => 'active',
                'updated_at' => now(),
            ]);

            $request->update([
                'status'                 => 'approved',
                'approved_days'          => $approvedDays,
                'approved_new_expiry_at' => $approvedExpiryAt,
                'admin_note'             => $adminNote,
                'reviewed_by_user_id'    => $reviewerUserId,
                'reviewed_at'            => now(),
            ]);
        });

        $this->notifyRequesterOfDecision($tenantId, $deal, $request, 'approved');
        $this->audit($tenantId, $deal->id, $requestId, 'deal_extension_approved', $reviewerUserId, [
            'approved_days'   => $approvedDays,
            'new_days_left'   => $newDaysLeft,
        ]);

        DealExtensionApproved::dispatch(
            leadId:       $deal->id,
            leadName:     $deal->name,
            tenantId:     $tenantId,
            resellerName: $deal->reseller_name ?? '',
            approvedDays: $approvedDays,
            newDaysLeft:  $newDaysLeft,
            adminNote:    $adminNote,
        );

        try {
            app(CriticalActionService::class)->invalidateAllAdminBadges($tenantId);
        } catch (\Throwable) {}

        return $request->fresh();
    }

    /**
     * Reject an extension request.
     */
    public function reject(
        string  $requestId,
        string  $tenantId,
        ?string $reviewerUserId,
        string  $reason
    ): DealAssignmentExtensionRequest {
        $request = null;
        $deal    = null;

        DB::transaction(function () use ($requestId, $tenantId, $reviewerUserId, $reason, &$request, &$deal) {
            $request = $this->loadForReview($requestId, $tenantId);
            $deal    = Lead::where('id', $request->deal_id)->where('tenant_id', $tenantId)->firstOrFail();

            $request->update([
                'status'              => 'rejected',
                'admin_note'          => $reason,
                'reviewed_by_user_id' => $reviewerUserId,
                'reviewed_at'         => now(),
            ]);
        });

        $this->notifyRequesterOfDecision($tenantId, $deal, $request, 'rejected');
        $this->audit($tenantId, $deal->id, $requestId, 'deal_extension_rejected', $reviewerUserId);

        try {
            app(CriticalActionService::class)->invalidateAllAdminBadges($tenantId);
        } catch (\Throwable) {}

        return $request->fresh();
    }

    /**
     * Request clarification — sets status and notifies requester.
     */
    public function requestClarification(
        string  $requestId,
        string  $tenantId,
        ?string $reviewerUserId,
        string  $note
    ): DealAssignmentExtensionRequest {
        $request = null;
        $deal    = null;

        DB::transaction(function () use ($requestId, $tenantId, $reviewerUserId, $note, &$request, &$deal) {
            $request = $this->loadForReview($requestId, $tenantId);
            $deal    = Lead::where('id', $request->deal_id)->where('tenant_id', $tenantId)->firstOrFail();

            $request->update([
                'status'              => 'clarification_requested',
                'admin_note'          => $note,
                'reviewed_by_user_id' => $reviewerUserId,
                'reviewed_at'         => now(),
            ]);
        });

        $this->notifyRequesterOfDecision($tenantId, $deal, $request, 'clarification_requested');
        $this->audit($tenantId, $deal->id, $requestId, 'deal_extension_clarification_requested', $reviewerUserId);

        try {
            app(CriticalActionService::class)->invalidateAllAdminBadges($tenantId);
        } catch (\Throwable) {}

        return $request->fresh();
    }

    /**
     * Get all extension requests for a tenant (admin view).
     */
    public function getForTenant(string $tenantId, ?string $status = null): \Illuminate\Support\Collection
    {
        $query = DealAssignmentExtensionRequest::where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        return $query->with('lead')->get();
    }

    /**
     * Get extension requests for a specific deal.
     */
    public function getForDeal(string $tenantId, string $dealId): \Illuminate\Support\Collection
    {
        return DealAssignmentExtensionRequest::where('tenant_id', $tenantId)
            ->where('deal_id', $dealId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function loadForReview(string $requestId, string $tenantId): DealAssignmentExtensionRequest
    {
        $request = DealAssignmentExtensionRequest::where('id', $requestId)
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->firstOrFail();

        if (!in_array($request->status, ['pending_review', 'clarification_requested'])) {
            throw new \InvalidArgumentException("Extension request is already {$request->status}.");
        }

        return $request;
    }

    private function notifyAdminsOfNewRequest(string $tenantId, Lead $deal, DealAssignmentExtensionRequest $request): void
    {
        try {
            app(NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'deal_pipeline',
                priority:     'high',
                title:        "Extension request: {$deal->name}",
                body:         "A Referrer requested {$request->requested_days} extra days for deal \"{$deal->name}\". Reason: {$request->reason}",
                actionUrl:    url("/tenant/{$tenantId}/deals/{$deal->id}?extension_request_id={$request->id}"),
                actionLabel:  'Review Request',
                dedupeSuffix: "ext_req:{$request->id}",
                metadata:     ['extension_request_id' => $request->id, 'deal_id' => $deal->id],
            );
        } catch (\Throwable) {}
    }

    private function notifyRequesterOfDecision(string $tenantId, Lead $deal, DealAssignmentExtensionRequest $request, string $decision): void
    {
        $titleLabels = [
            'approved'                => 'Approved',
            'rejected'                => 'Rejected',
            'clarification_requested' => 'Clarification Needed',
        ];

        $messages = [
            'approved'                => "Your extension request for \"{$deal->name}\" was approved. {$request->approved_days} days added.",
            'rejected'                => "Your extension request for \"{$deal->name}\" was rejected." . ($request->admin_note ? " Reason: {$request->admin_note}" : ''),
            'clarification_requested' => "The admin needs clarification on your extension request for \"{$deal->name}\"." . ($request->admin_note ? " {$request->admin_note}" : ''),
        ];

        $titleLabel = $titleLabels[$decision] ?? ucfirst($decision);
        $message    = $messages[$decision] ?? "Your extension request for \"{$deal->name}\" was updated.";

        try {
            $reseller = DB::table('resellers')
                ->where('tenant_id', $tenantId)
                ->where('id', $request->requested_by_user_id)
                ->first();

            // Approved case: in-app + email are handled by HandleDealExtensionApproved listener
            if ($reseller && $decision !== 'approved') {
                app(NotificationDispatchService::class)->dispatchToReseller(
                    resellerId:   $reseller->id,
                    tenantId:     $tenantId,
                    category:     'deal_pipeline',
                    priority:     'high',
                    title:        "{$titleLabel}: Extension request for \"{$deal->name}\"",
                    body:         $message,
                    actionUrl:    url("/reseller/{$tenantId}/deals/{$deal->id}"),
                    actionLabel:  'View Deal',
                    dedupeSuffix: "ext_decision:{$request->id}:{$decision}",
                );
                Cache::forget("notif_unread_reseller_{$reseller->id}");

                if ($reseller->email) {
                    $tenant = \App\Models\Tenant::find($tenantId);
                    EmailLogger::send(
                        mailable:       new \App\Mail\DealExtensionDecisionMail(
                            dealName:     $deal->name,
                            dealId:       $deal->id,
                            dealTenantId: $deal->tenant_id,
                            decision:     $decision,
                            resellerName: $reseller->name,
                            tenantName:   $tenant?->name ?? 'ReferralBunny',
                            approvedDays: $request->approved_days,
                            adminNote:    $request->admin_note,
                        ),
                        recipientEmail: $reseller->email,
                        recipientType:  'reseller',
                        recipientId:    $reseller->id,
                        emailKey:       'deal_ext_decision.' . $request->id . '.' . $decision,
                        subject:        match($decision) {
                            'approved'                => 'Extension approved',
                            'clarification_requested' => 'Clarification needed',
                            default                   => 'Extension request update',
                        } . ': ' . $deal->name,
                        tenantId:       $tenantId,
                    );
                }
            }
        } catch (\Throwable) {}
    }

    private function audit(string $tenantId, string $dealId, string $requestId, string $event, ?string $actorId, array $extra = []): void
    {
        try {
            ActivityLog::create([
                'id'        => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'user_id'   => is_numeric($actorId) ? (int) $actorId : null,
                'action'    => $event,
                'entity'    => 'extension_request',
                'entity_id' => $requestId,
                'metadata'  => array_merge([
                    'deal_id'              => $dealId,
                    'extension_request_id' => $requestId,
                    'timestamp'            => now()->toIso8601String(),
                ], $extra),
            ]);
        } catch (\Throwable) {}
    }
}
