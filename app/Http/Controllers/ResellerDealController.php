<?php

namespace App\Http\Controllers;

use App\Mail\ResellerInvitation;
use App\Models\DealApprovalRequest;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\LeadHistory;
use App\Models\CommissionSplit;
use App\Models\Reseller;
use App\Models\Tenant;
use App\Services\CommissionCalculationService;
use App\Services\DealActivityService;
use App\Services\DealPartnerSplitService;
use App\Services\NotificationDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ResellerDealController extends Controller
{
    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Resolve the authenticated reseller; abort if not found. */
    private function reseller(): Reseller
    {
        $r = Auth::guard('reseller')->user();
        if ($r instanceof Reseller) return $r;
        abort(403, 'Reseller authentication required.');
    }

    /** Load deal, verify it belongs to tenant, verify reseller is assigned. */
    private function deal(string $tenantId, string $dealId): Lead
    {
        $reseller = $this->reseller();

        // Check withTrashed first so we can give a better error if the deal is archived
        $lead = Lead::withTrashed()
            ->where('id', $dealId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$lead) abort(404, 'Deal not found.');

        // Archived (soft-deleted) deals are not accessible from the referrer portal
        if ($lead->trashed()) {
            abort(404, 'This deal has been archived and is no longer accessible.');
        }

        // Access check: reseller must be assigned to this deal
        if (!$this->resellerCanAccessDeal($reseller, $lead)) {
            abort(403, 'You do not have access to this deal.');
        }

        return $lead;
    }

    private function resellerCanAccessDeal(Reseller $reseller, Lead $lead): bool
    {
        // Primary assignment — case-insensitive to handle import name-casing differences
        if (strtolower((string) $reseller->name) === strtolower((string) $lead->reseller_name)) return true;

        // Secondary: commission split (additional referrer) — also case-insensitive
        return CommissionSplit::where('lead_id', $lead->id)
            ->whereRaw('LOWER(reseller_name) = ?', [strtolower($reseller->name)])
            ->exists();
    }

    // ── Deal Detail (Show) ────────────────────────────────────────────────────

    public function show(string $tenantId, string $dealId)
    {
        $reseller = $this->reseller();
        $lead     = $this->deal($tenantId, $dealId);

        $tenant = DB::table('tenants')->where('id', $tenantId)->first();

        // Pending approval requests for this deal
        $pendingApprovals = [];
        try {
            $pendingApprovals = DealApprovalRequest::where('deal_id', $dealId)
                ->where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->get()
                ->toArray();
        } catch (\Throwable) {}

        // Load shared notes — DealComment (new system, supports attachments) + LeadNote (legacy)
        $notes = [];
        try {
            // New notes: deal_comments with visibility = 'shared'
            $dealComments = \App\Models\DealComment::with('attachments')
                ->where('deal_id', $dealId)
                ->where('tenant_id', $tenantId)
                ->where('visibility', 'shared')
                ->whereNull('deleted_at')
                ->whereNull('parent_comment_id')
                ->orderBy('created_at', 'asc')
                ->get();

            // Batch-resolve author names for referrer-authored comments
            $resellerIds   = $dealComments->where('author_role', 'referrer')->pluck('author_user_id')->unique()->filter();
            $resellerNames = $resellerIds->isNotEmpty()
                ? Reseller::whereIn('id', $resellerIds)->pluck('name', 'id')
                : collect();

            $commentNotes = $dealComments->map(fn($c) => [
                'id'          => $c->id,
                'text'        => $c->body ?? '',
                'author'      => match($c->author_role) {
                    'referrer'    => $resellerNames[$c->author_user_id] ?? 'Referrer',
                    'partner'     => 'Partner',
                    'tenant_admin', 'super_admin' => 'Admin',
                    default       => 'Team',
                },
                'created_at'  => $c->created_at,
                'attachments' => $c->attachments->map(fn($a) => [
                    'id'                => $a->id,
                    'original_filename' => $a->original_filename,
                    'file_type_group'   => $a->file_type_group ?? 'document',
                    'file_size'         => $a->file_size,
                    'download_url'      => route('reseller.deals.notes.attachments.download', ['tenantId' => $tenantId, 'dealId' => $dealId, 'commentId' => $c->id, 'attachmentId' => $a->id]),
                ])->toArray(),
            ])->toArray();

            // Legacy notes: lead_notes (old system, no attachments)
            $legacyNotes = LeadNote::where('lead_id', $dealId)
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(fn($n) => [
                    'id'          => $n->id ?? null,
                    'text'        => $n->text ?? '',
                    'author'      => $n->author ?? 'Referrer',
                    'created_at'  => $n->created_at,
                    'attachments' => [],
                ])->toArray();

            // Merge and sort chronologically
            $merged = array_merge($commentNotes, $legacyNotes);
            usort($merged, fn($a, $b) => ($a['created_at'] ?? '') <=> ($b['created_at'] ?? ''));
            $notes = $merged;
        } catch (\Throwable $e) {
            Log::warning('ResellerDealController: notes load failed', ['error' => $e->getMessage()]);
        }

        // Commission splits
        $splits = CommissionSplit::where('lead_id', $dealId)->get();

        // Partner splits
        $partnerSplits = [];
        try {
            $partnerSplits = DB::table('deal_partner_splits')
                ->where('deal_id', $dealId)
                ->whereNull('deleted_at')
                ->where('status', '!=', 'removed')
                ->get()
                ->toArray();
        } catch (\Throwable) {}

        // Attachments
        $attachments = [];
        try {
            $attachments = DB::table('lead_attachments')
                ->where('lead_id', $dealId)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get()
                ->toArray();
        } catch (\Throwable) {}

        // Activity (lead_history)
        $history = [];
        try {
            $history = DB::table('lead_history')
                ->where('lead_id', $dealId)
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get()
                ->toArray();
        } catch (\Throwable) {}

        // Financial breakdown — always falls back to raw Lead fields if service fails
        $calc      = app(CommissionCalculationService::class);
        $breakdown = [];
        try {
            $breakdown = $calc->breakdownFromLead($lead);
        } catch (\Throwable) {}

        // Robust fallback: if breakdown service fails or returns 0, use DB columns directly.
        // added_amount drives the commission pool (70%). Never let a service failure zero it out.
        $addedAmountFallback = (float) ($lead->added_amount ?? 0);
        $commissionPool = (float) ($breakdown['commission_pool']
            ?? ($addedAmountFallback > 0 ? round($addedAmountFallback * 0.70, 2) : 0));

        // Partners Commission — computed FIRST so we can deduct from referrer's net.
        $partnersCommission = 0.0;
        $partnerSplits = collect($partnerSplits)->map(function ($ps) use ($calc, $commissionPool) {
            $estimated = 0.0;
            try {
                $estimated = $calc->partnerShare(
                    $commissionPool,
                    (float) ($ps->split_share_value ?? 0),
                    $ps->split_share_type ?? 'percentage'
                );
            } catch (\Throwable) {}

            $val = (float) ($ps->split_share_value ?? 0);
            $displayShare = ($ps->split_share_type ?? '') === 'fixed_amount'
                ? '₱' . number_format($val, 0)
                : rtrim(rtrim(number_format($val, 2, '.', ''), '0'), '.') . '%';

            return array_merge((array) $ps, [
                'estimated_commission' => $estimated,
                'display_share'        => $displayShare,
            ]);
        })->toArray();

        try {
            $partnersCommission = round(array_sum(array_column($partnerSplits, 'estimated_commission')), 2);
        } catch (\Throwable) {}

        // Pool remaining after partner deductions — this is what referrers share.
        $remainingPool = max(0.0, round($commissionPool - $partnersCommission, 2));

        // My Commission — applied against the remaining pool, not the gross pool.
        // When the primary referrer has no explicit CommissionSplit record, their share
        // is 100% minus whatever was allocated to co-referrers (secondary splits).
        $myCommission = 0.0;
        $myPct        = 100.0;
        try {
            $mySplitRecord = $splits->firstWhere('reseller_name', $reseller->name);
            if ($mySplitRecord) {
                $myPct = (float) ($mySplitRecord->percentage ?? 100.0);
            } else {
                $coReferrerTotal = $splits->where('role', 'secondary')->sum('percentage');
                $myPct = max(0.0, 100.0 - (float) $coReferrerTotal);
            }
            $myCommission = $calc->referrerShare($remainingPool, $myPct);
        } catch (\Throwable) {}

        // Stage requirements: defines what each transition requires before moving.
        // required=true items must all be checked to move directly; any unchecked → approval request.
        $stageRequirements = [
            'introduction_to_presentation' => [
                ['id' => 'contact_made',      'label' => 'Initial contact made with decision-maker',      'required' => true],
                ['id' => 'meeting_scheduled', 'label' => 'Meeting/demo date confirmed with client',       'required' => true],
            ],
            'presentation_to_contract_sent' => [
                ['id' => 'presentation_done', 'label' => 'Formal presentation or demo completed',         'required' => true],
                ['id' => 'proposal_ready',    'label' => 'Proposal or quotation prepared',                'required' => true],
                ['id' => 'client_interested', 'label' => 'Client expressed intent to proceed',            'required' => true],
            ],
            'contract_sent_to_signed' => [
                ['id' => 'contract_sent',     'label' => 'Contract sent and received by client',          'required' => true],
                ['id' => 'client_reviewed',   'label' => 'Client reviewed and confirmed contract terms',  'required' => true],
                ['id' => 'legal_cleared',     'label' => 'Legal / procurement clearance obtained',        'required' => false],
            ],
            'signed_to_paid' => [
                ['id' => 'signed_received',   'label' => 'Signed contract received from client',          'required' => true],
                ['id' => 'payment_confirmed', 'label' => 'Payment schedule or terms confirmed',           'required' => true],
                ['id' => 'po_received',       'label' => 'Purchase order or COA received',                'required' => false],
            ],
        ];

        // no-store: ensures every page load fetches fresh DB data.
        // This guarantees that after a referrer updates the deal amount and the
        // page reloads, the browser never serves a cached (stale) response.
        return response()
            ->view('reseller.deals.show', compact(
                'reseller', 'tenant', 'lead', 'tenantId',
                'pendingApprovals', 'notes', 'splits', 'partnerSplits',
                'attachments', 'history', 'breakdown',
                'myCommission', 'partnersCommission',
                'commissionPool', 'remainingPool',
                'stageRequirements'
            ))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache');
    }

    // ── Add Note ─────────────────────────────────────────────────────────────

    public function addNote(Request $request, string $tenantId, string $dealId): JsonResponse
    {
        $reseller = $this->reseller();
        $lead     = $this->deal($tenantId, $dealId);

        $data = $request->validate([
            'body'    => 'nullable|string|max:10000',
            'files'   => 'nullable|array|max:5',
            'files.*' => 'nullable|file|max:10240',
        ]);

        $body     = strip_tags(trim($data['body'] ?? ''));
        $hasFiles = !empty($request->file('files'));
        if (!$body && !$hasFiles) {
            return response()->json(['error' => 'Please add a note or attach a file.'], 422);
        }

        try {
            // Use DealComment (supports files + mentions) instead of LeadNote
            $comment = \App\Models\DealComment::create([
                'tenant_id'      => $tenantId,
                'deal_id'        => $dealId,
                'author_user_id' => $reseller->id,
                'author_role'    => 'referrer',
                'body'           => $body,
                'visibility'     => 'shared',
            ]);

            // Store attachments if provided
            $attachments = [];
            if ($hasFiles) {
                $attachments = \App\Http\Controllers\DealNoteAttachmentController::storeFiles(
                    $request->file('files'), $tenantId, $dealId, $comment->id, (string) $reseller->id, 'referrer'
                );
            }
        } catch (\Throwable $e) {
            Log::error('ResellerDealController addNote failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Could not save note. Please try again.'], 500);
        }

        // Activity + admin notification — outside transaction so failures don't roll back
        try {
            app(DealActivityService::class)->record($lead, 'Note added by referrer', 'note', [
                'category'   => 'note',
                'reseller'   => $reseller->name,
                'actor_name' => $reseller->name,
                'actor_role' => 'referrer',
            ]);
        } catch (\Throwable) {}

        try {
            app(NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'deal_pipeline',
                priority:     'normal',
                title:        'Note added by Referrer',
                body:         $reseller->name . ' added a note on "' . $lead->name . '"' . ($body ? ': "' . \Illuminate\Support\Str::limit($body, 60) . '"' : ' (with attachment)'),
                actionUrl:    url("/tenant/{$tenantId}/deals/{$dealId}"),
                actionLabel:  'View Deal',
                dedupeSuffix: $dealId . ':note:' . $comment->id,
            );
        } catch (\Throwable) {}

        return response()->json([
            'success' => true,
            'note'    => [
                'id'          => $comment->id,
                'body'        => $comment->body,
                'author'      => $reseller->name,
                'attachments' => count($attachments),
                'created_ago' => 'just now',
            ],
        ]);
    }

    // ── Update Deal Amount ────────────────────────────────────────────────────

    public function updateAmount(Request $request, string $tenantId, string $dealId): JsonResponse
    {
        $reseller = $this->reseller();
        $lead     = $this->deal($tenantId, $dealId);

        // Block edits once commission is locked or paid — amounts are finalised
        if (in_array($lead->commission_status ?? '', ['locked', 'paid'])) {
            return response()->json(['error' => 'Deal amount cannot be changed after commission has been ' . $lead->commission_status . '. Contact your admin.'], 422);
        }

        $data = $request->validate([
            'deal_value' => 'required|numeric|min:1|max:999999999',
            'reason'     => 'required|string|max:1000',
        ]);

        $oldAmount = (float) $lead->deal_value;
        $newAmount = (float) $data['deal_value'];

        if (abs($oldAmount - $newAmount) < 0.01) {
            return response()->json(['error' => 'New amount is the same as the current amount.'], 422);
        }

        // Recalculate base_cost and added_amount for the new deal value.
        // LGU IDS uses tiered pricing; other tenants scale proportionally.
        if ($tenantId === 'lgu-ids') {
            $newBaseCost = \App\Services\LguIds\LguIdsPricingService::lookupBaseCost($newAmount);
        } elseif ($oldAmount > 0 && ($lead->base_cost ?? 0) > 0) {
            $newBaseCost = round($newAmount * ((float) $lead->base_cost / $oldAmount), 2);
        } else {
            $newBaseCost = (float) $lead->base_cost;
        }
        $newAddedAmount = max(0.0, round($newAmount - $newBaseCost, 2));

        DB::beginTransaction();
        try {
            $lead->update([
                'deal_value'   => $newAmount,
                'base_cost'    => $newBaseCost,
                'added_amount' => $newAddedAmount,
            ]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ResellerDealController updateAmount failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Could not update amount. Please try again.'], 500);
        }

        // Activity log AFTER commit — never let logging failure roll back the business action
        try {
            app(DealActivityService::class)->record($lead->fresh(), 'Deal amount updated by referrer', 'amount', [
                'category'   => 'financial',
                'reseller'   => $reseller->name,
                'actor_name' => $reseller->name,
                'actor_role' => 'referrer',
                'old_values' => ['deal_value' => $oldAmount],
                'new_values' => ['deal_value' => $newAmount, 'reason' => $data['reason']],
            ]);
        } catch (\Throwable) {}

        // Compute breakdown AFTER commit (read-only)
        $newBreakdown = [];
        try {
            $newBreakdown = app(CommissionCalculationService::class)->breakdownFromLead($lead->fresh());
        } catch (\Throwable) {}

        // Notify admins — dedup per deal per day (not per hour) to prevent spam on rapid edits
        try {
            app(NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'deal_pipeline',
                priority:     'normal',
                title:        'Deal amount updated by Referrer',
                body:         $reseller->name . ' updated "' . $lead->name . '" from ₱' . number_format($oldAmount, 0) . ' to ₱' . number_format($newAmount, 0) . '. Reason: ' . \Illuminate\Support\Str::limit($data['reason'], 80),
                actionUrl:    url("/tenant/{$tenantId}/deals/{$dealId}"),
                actionLabel:  'Review Deal',
                dedupeSuffix: $dealId . ':amount:' . now()->format('Ymd'),  // per-day dedup (not per-hour)
            );
        } catch (\Throwable) {}

        return response()->json([
            'success'    => true,
            'deal_value' => $newAmount,
            'breakdown'  => $newBreakdown,
        ]);
    }

    // ── Move Stage ────────────────────────────────────────────────────────────

    public function moveStage(Request $request, string $tenantId, string $dealId): JsonResponse
    {
        $reseller = $this->reseller();
        $lead     = $this->deal($tenantId, $dealId);

        $data = $request->validate([
            'stage'  => 'required|in:introduction,presentation,contract_sent,signed,paid',
            'reason' => 'nullable|string|max:1000',
        ]);

        $oldStage    = $lead->stage;
        $targetStage = $data['stage'];

        if ($oldStage === $targetStage) {
            return response()->json(['error' => 'Deal is already at this stage.'], 422);
        }

        // Server-side forward-only guard — UI enforces this too but API must also validate
        $stageOrder = ['introduction', 'presentation', 'contract_sent', 'signed', 'paid'];
        $currentIdx = array_search($oldStage, $stageOrder, true);
        $targetIdx  = array_search($targetStage, $stageOrder, true);
        if ($targetIdx !== false && $currentIdx !== false && $targetIdx <= $currentIdx) {
            return response()->json(['error' => 'Stage moves must progress forward. You cannot move a deal to an earlier stage.'], 422);
        }

        // Check for a pending approval for this deal + stage
        $pendingExists = DealApprovalRequest::where('deal_id', $dealId)
            ->where('tenant_id', $tenantId)
            ->where('type', 'deal_stage_move')
            ->where('status', 'pending')
            ->exists();

        if ($pendingExists) {
            return response()->json(['error' => 'A stage move request is already pending approval.'], 422);
        }

        DB::beginTransaction();
        try {
            $lead->update(['stage' => $targetStage]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ResellerDealController moveStage failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Could not move stage. Please try again.'], 500);
        }

        // Activity log and notification outside transaction
        try {
            app(DealActivityService::class)->record($lead, 'Stage moved by referrer', 'stage', [
                'category'   => 'stage',
                'reseller'   => $reseller->name,
                'actor_name' => $reseller->name,
                'actor_role' => 'referrer',
                'old_values' => ['stage' => $oldStage],
                'new_values' => ['stage' => $targetStage],
            ]);
        } catch (\Throwable) {}

        try {
            app(NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'deal_pipeline',
                priority:     'normal',
                title:        'Deal stage moved by referrer',
                body:         $reseller->name . ' moved "' . $lead->name . '" from ' . ucfirst(str_replace('_', ' ', $oldStage)) . ' to ' . ucfirst(str_replace('_', ' ', $targetStage)) . '.',
                actionUrl:    url("/tenant/{$tenantId}/deals/{$dealId}"),
                actionLabel:  'View Deal',
                dedupeSuffix: $dealId . ':stage:' . $targetStage . ':' . now()->format('YmdH'),
            );
        } catch (\Throwable) {}

        return response()->json(['success' => true, 'stage' => $targetStage]);
    }

    // ── Request Stage Approval (when docs incomplete) ────────────────────────

    public function requestStageApproval(Request $request, string $tenantId, string $dealId): JsonResponse
    {
        $reseller = $this->reseller();
        $lead     = $this->deal($tenantId, $dealId);

        $data = $request->validate([
            'target_stage'         => 'required|in:introduction,presentation,contract_sent,signed,paid',
            'reason'               => 'required|string|max:2000',
            'missing_requirements' => 'nullable|array',
            'missing_requirements.*' => 'string|max:200',
        ]);

        // Block if already pending
        $alreadyPending = DealApprovalRequest::where('deal_id', $dealId)
            ->where('tenant_id', $tenantId)
            ->where('type', 'deal_stage_move')
            ->where('status', 'pending')
            ->exists();

        if ($alreadyPending) {
            return response()->json(['error' => 'A stage approval request is already pending.'], 422);
        }

        $approvalId = null;
        DB::beginTransaction();
        try {
            $approval = DealApprovalRequest::create([
                'tenant_id'            => $tenantId,
                'type'                 => 'deal_stage_move',
                'deal_id'              => $dealId,
                'requested_by_type'    => 'reseller',
                'requested_by_id'      => (string) $reseller->id,
                'status'               => 'pending',
                'reason'               => $data['reason'],
                'missing_requirements' => $data['missing_requirements'] ?? [],
                'request_payload'      => [
                    'current_stage' => $lead->stage,
                    'target_stage'  => $data['target_stage'],
                    'deal_name'     => $lead->name,
                    'referrer_name' => $reseller->name,
                ],
            ]);
            $approvalId = $approval->id;

            app(DealActivityService::class)->record($lead, 'Stage move approval requested by referrer', 'approval', [
                'category'   => 'approval',
                'reseller'   => $reseller->name,
                'actor_name' => $reseller->name,
                'actor_role' => 'referrer',
                'new_values' => ['approval_id' => $approvalId, 'target_stage' => $data['target_stage']],
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ResellerDealController requestStageApproval failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Could not submit approval request. Please try again.'], 500);
        }

        try {
            app(NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'deal_pipeline',
                priority:     'high',
                title:        'Stage move approval needed',
                body:         $reseller->name . ' requested to move "' . $lead->name . '" to ' . ucfirst(str_replace('_', ' ', $data['target_stage'])) . '. Review required.',
                actionUrl:    url("/tenant/{$tenantId}/deals/{$dealId}"),
                actionLabel:  'Review & Approve',
                dedupeSuffix: $dealId . ':stage_approval:' . $approvalId,
            );
        } catch (\Throwable) {}

        return response()->json(['success' => true, 'approval_id' => $approvalId]);
    }

    // ── Request Archive ───────────────────────────────────────────────────────

    public function requestArchive(Request $request, string $tenantId, string $dealId): JsonResponse
    {
        $reseller = $this->reseller();
        $lead     = $this->deal($tenantId, $dealId);

        // TEST — block archive on finalized deal statuses
        if (in_array($lead->status, ['expired', 'declined'])) {
            return response()->json(['error' => 'This deal has already been closed and cannot be archived again.'], 422);
        }
        if ($lead->stage === 'paid') {
            return response()->json(['error' => 'Paid deals cannot be archived. Contact an admin if this is a mistake.'], 422);
        }

        $data = $request->validate([
            'reason' => 'required|string|max:2000',
        ]);

        // Block if already pending
        $alreadyPending = DealApprovalRequest::where('deal_id', $dealId)
            ->where('tenant_id', $tenantId)
            ->where('type', 'deal_archive')
            ->where('status', 'pending')
            ->exists();

        if ($alreadyPending) {
            return response()->json(['error' => 'An archive request is already pending approval.'], 422);
        }

        DB::beginTransaction();
        try {
            $approval = DealApprovalRequest::create([
                'tenant_id'         => $tenantId,
                'type'              => 'deal_archive',
                'deal_id'           => $dealId,
                'requested_by_type' => 'reseller',
                'requested_by_id'   => (string) $reseller->id,
                'status'            => 'pending',
                'reason'            => $data['reason'],
                'expires_at'        => now()->addDays(30),
                'request_payload'   => [
                    'deal_name'     => $lead->name,
                    'deal_stage'    => $lead->stage,
                    'referrer_name' => $reseller->name,
                ],
            ]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ResellerDealController requestArchive failed', [
                'error'     => $e->getMessage(),
                'exception' => class_basename($e),
                'trace'     => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Could not submit archive request. Please try again.'], 500);
        }

        // Activity log + notification AFTER commit — never let these roll back the business record
        try {
            app(DealActivityService::class)->record($lead, 'Archive request submitted by referrer — reason: ' . \Illuminate\Support\Str::limit($data['reason'], 100), 'archive', [
                'category'   => 'archive',
                'reseller'   => $reseller->name,
                'actor_name' => $reseller->name,
                'actor_role' => 'referrer',
                'new_values' => ['approval_id' => $approval->id, 'reason' => $data['reason']],
            ]);
        } catch (\Throwable) {}

        try {
            app(NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'deal_pipeline',
                priority:     'high',
                title:        'Archive approval needed: ' . $lead->name,
                body:         $reseller->name . ' wants to archive "' . $lead->name . '". Reason: ' . \Illuminate\Support\Str::limit($data['reason'], 120),
                actionUrl:    url("/tenant/{$tenantId}/deals/{$dealId}"),
                actionLabel:  'Review & Decide',
                dedupeSuffix: $dealId . ':archive:' . $approval->id,
            );
        } catch (\Throwable) {}

        return response()->json(['success' => true, 'approval_id' => $approval->id]);
    }

    // ── Admin: Add Co-Referrer ───────────────────────────────────────────────

    public function adminAddReferrer(Request $request, string $tenantId, string $dealId): JsonResponse
    {
        $actor     = Auth::guard('tenant')->user() ?? Auth::guard('web')->user();
        $actorName = $actor ? ($actor->full_name ?: $actor->email) : 'Admin';

        $lead = Lead::where('id', $dealId)->where('tenant_id', $tenantId)->firstOrFail();

        $data = $request->validate([
            'referrer_email' => 'required|email|max:200',
            'percentage'     => 'required|numeric|min:0|max:100',
        ]);

        $email      = strtolower(trim($data['referrer_email']));
        $percentage = (float) $data['percentage'];

        $targetReseller = Reseller::where('tenant_id', $tenantId)
            ->whereRaw('LOWER(email) = ?', [$email])->first();
        $displayName = $targetReseller ? $targetReseller->name : $email;

        $alreadySplit = CommissionSplit::where('lead_id', $lead->id)
            ->where(function ($q) use ($displayName, $email) {
                $q->whereRaw('LOWER(reseller_name) = ?', [strtolower($displayName)])
                  ->orWhereRaw('LOWER(reseller_name) = ?', [$email]);
            })->exists();

        if ($alreadySplit) {
            return response()->json(['error' => 'This referrer is already associated with this deal.'], 422);
        }

        $existingTotal = CommissionSplit::where('lead_id', $lead->id)->sum('percentage');
        if ($existingTotal + $percentage > 100.005) {
            $available = max(0.0, round(100.0 - (float) $existingTotal, 2));
            return response()->json(['error' => "Total splits cannot exceed 100%. Available: {$available}%.", 'max_percentage' => $available], 422);
        }

        DB::beginTransaction();
        try {
            CommissionSplit::create([
                'lead_id'         => $lead->id,
                'reseller_name'   => $displayName,
                'percentage'      => $percentage,
                'role'            => 'secondary',
                'activity_status' => 'active',
            ]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('adminAddReferrer failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Could not add co-referrer. Please try again.'], 500);
        }

        try {
            app(DealActivityService::class)->record($lead->fresh(), 'Co-referrer added by admin: ' . $email, 'referrer', [
                'category'   => 'assignment',
                'actor_name' => $actorName,
                'actor_role' => 'admin',
                'new_values' => ['added_referrer_email' => $email, 'percentage' => $percentage, 'display_name' => $displayName],
            ]);
        } catch (\Throwable) {}

        if ($targetReseller && in_array($targetReseller->status, ['active', 'nda_signed'])) {
            try {
                app(NotificationDispatchService::class)->dispatchToReseller(
                    resellerId:   (string) $targetReseller->id,
                    tenantId:     $tenantId,
                    category:     'deal_pipeline',
                    priority:     'high',
                    title:        'You were added as a co-referrer',
                    body:         $actorName . ' added you as a co-referrer on "' . $lead->name . '".',
                    actionUrl:    url("/reseller/{$tenantId}/deals/{$lead->id}"),
                    actionLabel:  'View Deal',
                    dedupeSuffix: $lead->id . ':coreferrer:' . (string) $targetReseller->id,
                );
            } catch (\Throwable) {}
        }

        return response()->json(['success' => true, 'display_name' => $displayName, 'percentage' => $percentage]);
    }

    // ── Request Extension ────────────────────────────────────────────────────

    public function requestExtension(Request $request, string $tenantId, string $dealId): JsonResponse
    {
        $reseller = $this->reseller();
        $this->deal($tenantId, $dealId); // validates deal access

        $data = $request->validate([
            'requested_days' => 'required|integer|min:1|max:90',
            'reason'         => 'required|string|min:10|max:1000',
        ]);

        try {
            app(\App\Services\DealAssignmentExtensionService::class)->createRequest(
                tenantId:          $tenantId,
                dealId:            $dealId,
                requestedByUserId: (string) $reseller->id,
                requestedByRole:   'referrer',
                requestedDays:     (int) $data['requested_days'],
                reason:            $data['reason'],
                actorId:           (string) $reseller->id,
            );

            return response()->json([
                'message' => 'Extension request submitted. The Tenant Admin will review it shortly.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // ── Add Partner Split ────────────────────────────────────────────────────

    public function addPartnerSplit(Request $request, string $tenantId, string $dealId): JsonResponse
    {
        $reseller = $this->reseller();
        $lead     = $this->deal($tenantId, $dealId);
        $tenant   = \App\Models\Tenant::findOrFail($tenantId);

        $data = $request->validate([
            'partner_name'       => 'required|string|max:150',
            'partner_email'      => 'nullable|email|max:200', // optional — triggers invite when provided
            'split_share_value'  => 'required|numeric|min:0.01',
            'split_share_type'   => 'required|in:percentage,fixed_amount',
        ]);

        // ── Commission pool cap enforcement ───────────────────────────────────
        // Partner splits cannot exceed the commission pool (70% of added amount).
        // This applies regardless of default or explicit deal amounts.
        $calcSvc  = app(CommissionCalculationService::class);
        $poolData = $calcSvc->breakdownFromLead($lead);
        $pool     = (float) ($poolData['commission_pool'] ?? 0);

        $existingRows = DB::table('deal_partner_splits')
            ->where('deal_id', $dealId)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'removed')
            ->get();

        $existingTotal = $existingRows->sum(
            fn ($ps) => $calcSvc->partnerShare($pool, (float) $ps->split_share_value, $ps->split_share_type ?? 'percentage')
        );
        $newAmount = $calcSvc->partnerShare($pool, (float) $data['split_share_value'], $data['split_share_type']);
        $remaining = $pool - $existingTotal;

        if ($newAmount > $remaining + 0.05) {
            return response()->json([
                'error'           => 'This split exceeds the commission pool. Maximum you can allocate: ₱' . number_format(max(0, $remaining), 0) . '.',
                'max_allowed'     => max(0.0, round($remaining, 2)),
                'commission_pool' => $pool,
            ], 422);
        }

        $partnerEmail      = !empty($data['partner_email']) ? strtolower(trim($data['partner_email'])) : null;
        $inviteSent        = false;
        $alreadyHasAccount = false;
        $newPartner        = null;

        // Look up existing partner account BEFORE creating the split (no side effects yet)
        $existingPartner = $partnerEmail
            ? \App\Models\Partner::where('tenant_id', $tenantId)->whereRaw('LOWER(email) = ?', [$partnerEmail])->first()
            : null;

        if ($existingPartner && $existingPartner->isSetupComplete()) {
            $alreadyHasAccount = true;
        }

        // ── Create the split FIRST ─────────────────────────────────────────────
        // Invite email is sent AFTER the split is persisted so an orphaned invite
        // is impossible (email fail does not affect split creation).
        try {
            app(DealPartnerSplitService::class)->upsert(
                tenantId:    $tenantId,
                dealId:      $dealId,
                partnerName: $data['partner_name'],
                partnerEmail: $partnerEmail ?? '',
                splitValue:  (float) $data['split_share_value'],
                splitType:   $data['split_share_type'],
                currency:    'PHP',
                source:      'manual',
                actorId:     (string) $reseller->id,
            );
        } catch (\Throwable $e) {
            Log::error('ResellerDealController addPartnerSplit failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Could not add partner. Please try again.'], 500);
        }

        // Activity log AFTER split creation (never let logging failure roll back)
        try {
            app(DealActivityService::class)->record($lead->fresh(), 'Partner split added by referrer', 'partner', [
                'category'   => 'partner',
                'reseller'   => $reseller->name,
                'actor_name' => $reseller->name,
                'actor_role' => 'referrer',
                'new_values' => [
                    'partner'      => $data['partner_name'],
                    'split'        => $data['split_share_value'],
                    'type'         => $data['split_share_type'],
                ],
            ]);
        } catch (\Throwable) {}

        // ── Create partner user + send invite if email provided ────────────────
        if ($partnerEmail && !$existingPartner) {
            $nameParts  = preg_split('/\s+/', trim($data['partner_name']), 2);
            $newPartner = \App\Models\Partner::create([
                'id'              => (string) \Illuminate\Support\Str::uuid(),
                'tenant_id'       => $tenantId,
                'email'           => $partnerEmail,
                'first_name'      => $nameParts[0] ?? $data['partner_name'],
                'last_name'       => $nameParts[1] ?? null,
                'status'          => 'invited',
                'setup_token'     => \Illuminate\Support\Str::random(64),
                'invited_by_type' => 'reseller',
                'invited_by_id'   => (string) $reseller->id,
            ]);

            try {
                \Illuminate\Support\Facades\Mail::to($partnerEmail)
                    ->send(new \App\Mail\PartnerInviteMail($newPartner, $tenant, $reseller));
                $inviteSent = true;
            } catch (\Throwable $mailEx) {
                Log::warning('PartnerInviteMail send failed in addPartnerSplit', [
                    'tenant_id' => $tenantId, 'email' => $partnerEmail,
                    'error'     => $mailEx->getMessage(),
                ]);
            }
        }

        // Notify already-active partner via in-app (no email — they have an account)
        if ($alreadyHasAccount && $existingPartner) {
            try {
                app(NotificationDispatchService::class)->dispatch(
                    category:         'deal_pipeline',
                    priority:         'high',
                    title:            'You have been added as a Partner on a deal',
                    body:             $reseller->name . ' added you as a Partner on "' . $lead->name . '" with a ' . $data['split_share_value'] . ($data['split_share_type'] === 'percentage' ? '%' : ' PHP fixed') . ' commission split.',
                    notifiableType:   'partner',
                    notifiableId:     (string) $existingPartner->id,
                    tenantId:         $tenantId,
                    actionUrl:        url("/partner/deals/{$dealId}"),
                    actionLabel:      'View Deal',
                    deduplicationKey: $dealId . ':partner_added:' . (string) $existingPartner->id,
                );
            } catch (\Throwable) {}
        }

        // Notify admins
        try {
            app(NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'deal_pipeline',
                priority:     'normal',
                title:        'Partner added to deal',
                body:         $reseller->name . ' added ' . $data['partner_name']
                              . ($inviteSent ? ' and sent a partner invite' : ($alreadyHasAccount ? ' (active account)' : ''))
                              . ' to "' . $lead->name . '".',
                actionUrl:    url("/tenant/{$tenantId}/deals/{$dealId}"),
                actionLabel:  'Review Deal',
                dedupeSuffix: $dealId . ':partner:' . md5($partnerEmail ?? $data['partner_name']),
            );
        } catch (\Throwable) {}

        $message = match(true) {
            $inviteSent        => 'Partner added! Invite email sent to ' . $partnerEmail . '.',
            $alreadyHasAccount => 'Partner added. They already have an active account.',
            $partnerEmail      => 'Partner added. Invite email could not be sent — your admin can resend it.',
            default            => 'Partner added. Admins have been notified.',
        };

        return response()->json(['success' => true, 'message' => $message]);
    }

    // ── Add Co-Referrer ───────────────────────────────────────────────────────

    public function addReferrer(Request $request, string $tenantId, string $dealId): JsonResponse
    {
        $reseller = $this->reseller();
        $lead     = $this->deal($tenantId, $dealId);

        $data = $request->validate([
            'referrer_email' => 'required|email|max:200',
            'percentage'     => 'required|numeric|min:0|max:100',
        ]);

        $email      = strtolower(trim($data['referrer_email']));
        $percentage = (float) $data['percentage'];

        // Prevent adding yourself
        if ($email === strtolower(trim($reseller->email ?? ''))) {
            return response()->json(['error' => 'You cannot add yourself as a co-referrer.'], 422);
        }

        // Look up existing reseller by email in this tenant
        $targetReseller = Reseller::where('tenant_id', $tenantId)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        // Display name: use reseller name if found, otherwise use email
        $displayName = $targetReseller ? $targetReseller->name : $email;

        // Prevent duplicate (match by display name or email-as-name)
        $alreadySplit = CommissionSplit::where('lead_id', $lead->id)
            ->where(function ($q) use ($displayName, $email) {
                $q->whereRaw('LOWER(reseller_name) = ?', [strtolower($displayName)])
                  ->orWhereRaw('LOWER(reseller_name) = ?', [$email]);
            })
            ->exists();

        if ($alreadySplit) {
            return response()->json(['error' => 'This referrer is already associated with this deal.'], 422);
        }

        // Prevent total splits from exceeding 100%
        $existingTotal = CommissionSplit::where('lead_id', $lead->id)->sum('percentage');
        if ($existingTotal + $percentage > 100.005) {
            $available = max(0.0, round(100.0 - (float) $existingTotal, 2));
            return response()->json([
                'error' => "Total commission split cannot exceed 100%. You can allocate up to {$available}% to this co-referrer.",
                'max_percentage' => $available,
            ], 422);
        }

        // Look up tenant user (admin/manager) by email for notification
        $tenantUserByEmail = DB::table('tenant_users as tu')
            ->join('tenant_memberships as tm', 'tm.tenant_user_id', '=', 'tu.id')
            ->where('tm.tenant_id', $tenantId)
            ->where('tm.status', 'active')
            ->whereRaw('LOWER(tu.email) = ?', [$email])
            ->selectRaw("tu.id, tu.email, TRIM(CONCAT(COALESCE(tu.first_name,''), ' ', COALESCE(tu.last_name,''))) as name, tm.role")
            ->first();

        // Commit the split creation as its own atomic step
        DB::beginTransaction();
        try {
            CommissionSplit::create([
                'lead_id'         => $lead->id,
                'reseller_name'   => $displayName,
                'percentage'      => $percentage,
                'role'            => 'secondary',
                'activity_status' => 'active',
            ]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ResellerDealController addReferrer failed', ['error' => $e->getMessage(), 'email' => $email]);
            return response()->json(['error' => 'Could not add co-referrer. Please try again.'], 500);
        }

        // Activity log AFTER commit — never let logging failure roll back the split
        try {
            app(DealActivityService::class)->record($lead->fresh(), 'Co-referrer added by referrer: ' . $email, 'referrer', [
                'category'   => 'assignment',
                'reseller'   => $reseller->name,
                'actor_name' => $reseller->name,
                'actor_role' => 'referrer',
                'new_values' => ['added_referrer_email' => $email, 'percentage' => $percentage, 'display_name' => $displayName],
            ]);
        } catch (\Throwable) {}

        // Post-commit: notify or invite (best-effort — never rollback the split for these)
        $notified = false;
        $invited  = false;

        if ($targetReseller && in_array($targetReseller->status, ['active', 'nda_signed'])) {
            // Existing referrer — send in-app notification
            try {
                app(NotificationDispatchService::class)->dispatchToReseller(
                    resellerId:   (string) $targetReseller->id,
                    tenantId:     $tenantId,
                    category:     'deal_pipeline',
                    priority:     'high',
                    title:        'You were added as a co-referrer',
                    body:         $reseller->name . ' added you as a co-referrer on "' . $lead->name . '".',
                    actionUrl:    url("/reseller/{$tenantId}/deals/{$lead->id}"),
                    actionLabel:  'View Deal',
                    dedupeSuffix: $lead->id . ':coreferrer:' . (string) $targetReseller->id,
                );
                $notified = true;
            } catch (\Throwable) {}

        } elseif ($tenantUserByEmail) {
            // Tenant admin/manager — send in-app notification
            try {
                app(NotificationDispatchService::class)->dispatch(
                    category:         'deal_pipeline',
                    priority:         'normal',
                    title:            'You were added as a co-referrer on a deal',
                    body:             $reseller->name . ' added you as a co-referrer on "' . $lead->name . '".',
                    notifiableType:   'tenant_user',
                    notifiableId:     (string) $tenantUserByEmail->id,
                    tenantId:         $tenantId,
                    actionUrl:        url("/tenant/{$tenantId}/deals/{$lead->id}"),
                    actionLabel:      'View Deal',
                    deduplicationKey: $lead->id . ':coreferrer_admin:' . (string) $tenantUserByEmail->id,
                );
                $notified = true;
            } catch (\Throwable) {}

        } else {
            // Not found — create a pending Reseller record and send a proper setup invite.
            // /reseller/setup?token={token} is the correct activation URL (not /reseller/register).
            try {
                $inviteToken = \Illuminate\Support\Str::random(64);
                $tenant      = Tenant::find($tenantId);

                $newReseller = Reseller::create([
                    'tenant_id'   => $tenantId,
                    'name'        => $displayName !== $email ? $displayName : $email,
                    'email'       => $email,
                    'status'      => 'invited',
                    'setup_token' => $inviteToken,
                ]);

                $setupUrl = url("/reseller/setup?token={$inviteToken}");

                Mail::to($email)->send(new ResellerInvitation(
                    resellerName:  $newReseller->name,
                    resellerEmail: $email,
                    tenantName:    $tenant?->name ?? 'ReferralBunny',
                    setupUrl:      $setupUrl,
                    dealName:      $lead->name,
                    dealCount:     1,
                    dealNames:     [$lead->name],
                ));
                $invited = true;
            } catch (\Throwable $e) {
                Log::warning('addReferrer: invite email failed', ['email' => $email, 'error' => $e->getMessage()]);
            }
        }

        // Notify admins in all cases
        try {
            app(NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'deal_pipeline',
                priority:     'normal',
                title:        'Co-referrer added to deal',
                body:         $reseller->name . ' added ' . ($displayName !== $email ? $displayName . ' (' . $email . ')' : $email) . ' as a co-referrer (' . $percentage . '%) on "' . $lead->name . '".',
                actionUrl:    url("/tenant/{$tenantId}/deals/{$lead->id}"),
                actionLabel:  'Review Deal',
                dedupeSuffix: $lead->id . ':coreferrer:' . md5($email),
            );
        } catch (\Throwable) {}

        // Also notify the primary referrer on this deal (if different from the actor)
        // so they know their commission pool now has an additional split
        try {
            $primarySplit = CommissionSplit::where('lead_id', $lead->id)
                ->where('role', 'primary')
                ->first();
            if ($primarySplit && strtolower($primarySplit->reseller_name ?? '') !== strtolower($reseller->name ?? '')) {
                $primaryReseller = Reseller::where('tenant_id', $tenantId)
                    ->whereRaw('LOWER(name) = ?', [strtolower($primarySplit->reseller_name)])
                    ->first();
                if ($primaryReseller) {
                    app(NotificationDispatchService::class)->dispatchToReseller(
                        resellerId:   (string) $primaryReseller->id,
                        tenantId:     $tenantId,
                        category:     'deal_pipeline',
                        priority:     'normal',
                        title:        'Co-referrer added to your deal',
                        body:         $reseller->name . ' added ' . ($displayName !== $email ? $displayName : $email) . ' (' . $percentage . '%) as a co-referrer on "' . $lead->name . '".',
                        actionUrl:    url("/reseller/{$tenantId}/deals/{$lead->id}"),
                        actionLabel:  'View Deal',
                        dedupeSuffix: $lead->id . ':primary_coreferrer_notice:' . md5($email),
                    );
                }
            }
        } catch (\Throwable) {}

        $message = $notified ? 'Co-referrer added — they have been notified.'
            : ($invited  ? 'Co-referrer added — an invitation email has been sent.'
                         : 'Co-referrer added successfully.');

        return response()->json(['success' => true, 'message' => $message]);
    }

    // ── Approve / Reject deal approval (for tenant admins) ───────────────────

    public function approveRequest(Request $request, string $tenantId, string $approvalId): JsonResponse
    {
        // Tenant admin/manager only
        if (!Auth::guard('tenant')->check() && !Auth::guard('web')->check()) {
            abort(403);
        }

        $data = $request->validate(['reviewer_note' => 'nullable|string|max:1000']);

        $approval = DealApprovalRequest::where('id', $approvalId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->firstOrFail();

        $lead = Lead::find($approval->deal_id);

        DB::beginTransaction();
        try {
            $reviewerUser = Auth::guard('tenant')->user() ?? Auth::guard('web')->user();
            $reviewerName = $reviewerUser?->full_name ?? $reviewerUser?->name ?? $reviewerUser?->email ?? 'Admin';

            $approval->update([
                'status'        => 'approved',
                'reviewer_note' => $data['reviewer_note'] ?? null,
                'approved_at'   => now(),
                'reviewer_type' => Auth::guard('tenant')->check() ? 'tenant_user' : 'super_admin',
                'reviewer_id'   => (string) ($reviewerUser?->id ?? ''),
            ]);

            // Execute the approved action
            if ($approval->type === 'deal_stage_move' && $lead) {
                $targetStage = $approval->request_payload['target_stage'] ?? null;
                if ($targetStage) {
                    $oldStage = $lead->stage;
                    $lead->update(['stage' => $targetStage]);

                    app(DealActivityService::class)->record($lead, 'Stage move approved by admin', 'stage', [
                        'category'   => 'stage',
                        'actor_name' => $reviewerName,
                        'actor_role' => 'admin',
                        'old_values' => ['stage' => $oldStage],
                        'new_values' => ['stage' => $targetStage],
                    ]);
                }
            } elseif ($approval->type === 'deal_archive' && $lead) {
                // TEST — use archived_at + archive_reason instead of conflating with 'expired' status
                $archiveReason = $approval->reason ?? ($approval->request_payload['reason'] ?? null);
                $updateData = [
                    'status'         => 'declined',
                    'archive_reason' => $archiveReason,
                ];
                // archived_at column added in migration_v44; fall back gracefully if not yet migrated
                try {
                    $updateData['archived_at'] = now();
                } catch (\Throwable) {}
                $lead->update($updateData);

                app(DealActivityService::class)->record($lead, 'Deal archive approved and closed by ' . $reviewerName, 'archive', [
                    'category'   => 'archive',
                    'actor_name' => $reviewerName,
                    'actor_role' => 'admin',
                    'new_values' => ['archive_reason' => $archiveReason],
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ResellerDealController approveRequest failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Could not process approval. Please try again.'], 500);
        }

        // Notify after commit — never inside transaction
        if ($approval->requested_by_type === 'reseller') {
            try {
                $dealName = $lead?->name ?? ($approval->request_payload['deal_name'] ?? 'a deal');
                if ($approval->type === 'deal_stage_move') {
                    $targetStage = $approval->request_payload['target_stage'] ?? '';
                    app(NotificationDispatchService::class)->dispatchToReseller(
                        resellerId:   $approval->requested_by_id,
                        tenantId:     $tenantId,
                        category:     'deal_pipeline',
                        priority:     'high',
                        title:        'Stage move approved — ' . $dealName,
                        body:         '"' . $dealName . '" has been moved to ' . ucfirst(str_replace('_', ' ', $targetStage)) . '. Great progress!',
                        actionUrl:    url("/reseller/{$tenantId}/deals/{$approval->deal_id}"),
                        actionLabel:  'View Deal',
                        dedupeSuffix: $approvalId . ':stage_approved',
                    );
                } else {
                    app(NotificationDispatchService::class)->dispatchToReseller(
                        resellerId:   $approval->requested_by_id,
                        tenantId:     $tenantId,
                        category:     'deal_pipeline',
                        priority:     'high',
                        title:        'Archive request approved — ' . $dealName,
                        body:         'Your archive request for "' . $dealName . '" was approved. The deal has been closed.',
                        actionUrl:    url("/reseller/{$tenantId}/deals/" . $approval->deal_id),
                        actionLabel:  'View Deal',
                        dedupeSuffix: $approvalId . ':approved',
                    );
                }
            } catch (\Throwable) {}
        }

        return response()->json(['success' => true]);
    }

    public function rejectRequest(Request $request, string $tenantId, string $approvalId): JsonResponse
    {
        if (!Auth::guard('tenant')->check() && !Auth::guard('web')->check()) {
            abort(403);
        }

        $data = $request->validate(['reviewer_note' => 'required|string|max:1000']);

        $approval = DealApprovalRequest::where('id', $approvalId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->firstOrFail();

        $lead = Lead::find($approval->deal_id);

        // SWEEP — capture reviewer identity
        $reviewerUser = Auth::guard('tenant')->user() ?? Auth::guard('web')->user();
        $reviewerName = $reviewerUser?->full_name ?? $reviewerUser?->name ?? $reviewerUser?->email ?? 'Admin';

        DB::beginTransaction();
        try {
            $approval->update([
                'status'        => 'rejected',
                'reviewer_note' => $data['reviewer_note'],
                'rejected_at'   => now(),
                'reviewer_type' => Auth::guard('tenant')->check() ? 'tenant_user' : 'super_admin',
                'reviewer_id'   => (string) ($reviewerUser?->id ?? ''),
            ]);

            if ($lead) {
                $activityLabel = $approval->type === 'deal_stage_move'
                    ? 'Stage move request declined by ' . $reviewerName
                    : 'Archive request declined by ' . $reviewerName;
                app(DealActivityService::class)->record($lead, $activityLabel . ' — reason: ' . \Illuminate\Support\Str::limit($data['reviewer_note'], 100), $approval->type === 'deal_stage_move' ? 'stage' : 'archive', [
                    'category'   => $approval->type === 'deal_stage_move' ? 'stage' : 'archive',
                    'actor_name' => $reviewerName,
                    'actor_role' => 'admin',
                    'new_values' => ['rejection_reason' => $data['reviewer_note']],
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ResellerDealController rejectRequest failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Could not process rejection. Please try again.'], 500);
        }

        // Notify after commit — never inside transaction
        if ($approval->requested_by_type === 'reseller') {
            try {
                $dealName = $lead?->name ?? ($approval->request_payload['deal_name'] ?? 'a deal');
                if ($approval->type === 'deal_stage_move') {
                    $targetStage = $approval->request_payload['target_stage'] ?? '';
                    app(NotificationDispatchService::class)->dispatchToReseller(
                        resellerId:   $approval->requested_by_id,
                        tenantId:     $tenantId,
                        category:     'deal_pipeline',
                        priority:     'high',
                        title:        'Stage move request declined — ' . $dealName,
                        body:         'Your request to move "' . $dealName . '" to ' . ucfirst(str_replace('_', ' ', $targetStage)) . ' was declined. Admin note: ' . $data['reviewer_note'],
                        actionUrl:    url("/reseller/{$tenantId}/deals/" . $approval->deal_id),
                        actionLabel:  'View Deal',
                        dedupeSuffix: $approvalId . ':rejected',
                    );
                } else {
                    app(NotificationDispatchService::class)->dispatchToReseller(
                        resellerId:   $approval->requested_by_id,
                        tenantId:     $tenantId,
                        category:     'deal_pipeline',
                        priority:     'high',
                        title:        'Archive request declined — ' . $dealName,
                        body:         'Your archive request for "' . $dealName . '" was not approved. Admin note: ' . $data['reviewer_note'],
                        actionUrl:    url("/reseller/{$tenantId}/deals/" . $approval->deal_id),
                        actionLabel:  'View Deal',
                        dedupeSuffix: $approvalId . ':rejected',
                    );
                }
            } catch (\Throwable) {}
        }

        return response()->json(['success' => true]);
    }

    // ── Update Co-Referrer Split (Referrer-initiated) ─────────────────────────

    public function updateCoReferrerSplit(Request $request, string $tenantId, string $dealId, string $splitId): JsonResponse
    {
        $reseller = $this->reseller();
        $lead     = $this->deal($tenantId, $dealId);

        // Only the primary referrer on this deal may adjust co-referrer splits
        $isPrimary = CommissionSplit::where('lead_id', $lead->id)
            ->where('role', 'primary')
            ->whereRaw('LOWER(reseller_name) = ?', [strtolower($reseller->name)])
            ->exists();

        if (!$isPrimary) {
            return response()->json(['error' => 'Only the primary Referrer on this deal can adjust co-referrer shares.'], 403);
        }

        return $this->doUpdateSplit($request, $tenantId, $dealId, $splitId, $reseller->name, 'referrer');
    }

    // ── Update Co-Referrer Split (Admin-initiated) ────────────────────────────

    public function adminUpdateCoReferrerSplit(Request $request, string $tenantId, string $dealId, string $splitId): JsonResponse
    {
        // Admin/manager: must be authenticated as tenant user
        $actor     = \Illuminate\Support\Facades\Auth::guard('tenant')->user()
                  ?? \Illuminate\Support\Facades\Auth::guard('web')->user();
        if (!$actor) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        // Verify deal belongs to this tenant
        $lead = \App\Models\Lead::where('id', $dealId)->where('tenant_id', $tenantId)->firstOrFail();

        $actorName = method_exists($actor, 'full_name') ? $actor->full_name : ($actor->name ?? $actor->email ?? 'Admin');

        return $this->doUpdateSplit($request, $tenantId, $dealId, $splitId, $actorName, 'admin');
    }

    /** Shared logic for updating a co-referrer commission split. */
    private function doUpdateSplit(Request $request, string $tenantId, string $dealId, string $splitId, string $actorName, string $actorRole): JsonResponse
    {
        $data = $request->validate([
            'percentage' => 'required|numeric|min:0.01|max:100',
        ]);

        $newPct = round((float) $data['percentage'], 2);

        $split = CommissionSplit::where('id', $splitId)
            ->where('lead_id', $dealId)
            ->firstOrFail();

        // Only allow editing secondary (co-referrer) splits
        if ($split->role !== 'secondary') {
            return response()->json(['error' => 'Only co-referrer splits can be adjusted here.'], 422);
        }

        // Validate commission pool limit: total across all splits must not exceed 100%
        $otherTotal = CommissionSplit::where('lead_id', $dealId)
            ->where('id', '!=', $splitId)
            ->sum('percentage');

        if ($otherTotal + $newPct > 100.005) {
            $available = max(0.0, round(100.0 - (float) $otherTotal, 2));
            return response()->json([
                'error'          => "Cannot exceed 100% total. Maximum available for this co-referrer: {$available}%.",
                'max_percentage' => $available,
            ], 422);
        }

        $oldPct = (float) $split->percentage;
        $split->update(['percentage' => $newPct]);

        $lead = \App\Models\Lead::find($dealId);

        // Notify the co-referrer whose share changed
        try {
            $coRefReseller = Reseller::where('tenant_id', $tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower($split->reseller_name)])
                ->first();
            if ($coRefReseller) {
                app(NotificationDispatchService::class)->dispatchToReseller(
                    resellerId:   (string) $coRefReseller->id,
                    tenantId:     $tenantId,
                    category:     'deal_pipeline',
                    priority:     'high',
                    title:        'Your commission share was updated',
                    body:         $actorName . ' updated your commission share on "' . ($lead->name ?? 'a deal') . '" from ' . $oldPct . '% to ' . $newPct . '%.',
                    actionUrl:    url("/reseller/{$tenantId}/deals/{$dealId}"),
                    actionLabel:  'View Deal',
                    dedupeSuffix: $splitId . ':share_updated:' . now()->format('YmdH'),
                    metadata:     ['old_percentage' => $oldPct, 'new_percentage' => $newPct, 'actor' => $actorName],
                );
            }
        } catch (\Throwable) {}

        // Notify admins
        try {
            app(NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'deal_pipeline',
                priority:     'normal',
                title:        'Co-referrer share adjusted',
                body:         $actorName . ' changed ' . $split->reseller_name . '\'s share on "' . ($lead->name ?? 'a deal') . '" from ' . $oldPct . '% to ' . $newPct . '%.',
                actionUrl:    url("/tenant/{$tenantId}/deals/{$dealId}"),
                actionLabel:  'Review Deal',
                dedupeSuffix: $splitId . ':share_updated_admin:' . now()->format('YmdH'),
                metadata:     ['old_percentage' => $oldPct, 'new_percentage' => $newPct, 'actor' => $actorName, 'co_referrer' => $split->reseller_name],
            );
        } catch (\Throwable) {}

        // Activity log
        try {
            app(DealActivityService::class)->record($lead, 'Co-referrer share updated by ' . $actorRole, 'commission', [
                'category'   => 'financial',
                'actor_name' => $actorName,
                'actor_role' => $actorRole,
                'old_values' => ['percentage' => $oldPct, 'reseller_name' => $split->reseller_name],
                'new_values' => ['percentage' => $newPct, 'reseller_name' => $split->reseller_name],
            ]);
        } catch (\Throwable) {}

        return response()->json([
            'success'        => true,
            'split_id'       => $split->id,
            'new_percentage' => $newPct,
            'old_percentage' => $oldPct,
        ]);
    }

    // ── Remove Co-Referrer (Referrer-initiated) ───────────────────────────────

    public function removeCoReferrer(Request $request, string $tenantId, string $dealId, string $splitId): JsonResponse
    {
        $reseller = $this->reseller();
        $lead     = $this->deal($tenantId, $dealId);

        $isPrimary = CommissionSplit::where('lead_id', $lead->id)
            ->where('role', 'primary')
            ->whereRaw('LOWER(reseller_name) = ?', [strtolower($reseller->name)])
            ->exists();

        if (!$isPrimary) {
            return response()->json(['error' => 'Only the primary Referrer on this deal can remove co-referrers.'], 403);
        }

        return $this->doRemoveSplit($tenantId, $dealId, $splitId, $reseller->name, 'referrer', $lead);
    }

    // ── Remove Co-Referrer (Admin-initiated) ─────────────────────────────────

    public function adminRemoveCoReferrer(Request $request, string $tenantId, string $dealId, string $splitId): JsonResponse
    {
        $actor = Auth::guard('tenant')->user() ?? Auth::guard('web')->user();
        if (!$actor) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $lead      = Lead::where('id', $dealId)->where('tenant_id', $tenantId)->firstOrFail();
        $actorName = method_exists($actor, 'full_name') ? $actor->full_name : ($actor->name ?? $actor->email ?? 'Admin');

        return $this->doRemoveSplit($tenantId, $dealId, $splitId, $actorName, 'admin', $lead);
    }

    private function doRemoveSplit(string $tenantId, string $dealId, string $splitId, string $actorName, string $actorRole, Lead $lead): JsonResponse
    {
        $split = CommissionSplit::where('id', $splitId)
            ->where('lead_id', $dealId)
            ->where('role', 'secondary')
            ->firstOrFail();

        $removedName = $split->reseller_name;
        $split->delete();

        // System notification to the removed co-referrer
        try {
            $coRefReseller = Reseller::where('tenant_id', $tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower($removedName)])
                ->first();

            if ($coRefReseller && in_array($coRefReseller->status, ['active', 'nda_signed'])) {
                app(NotificationDispatchService::class)->dispatchToReseller(
                    resellerId:   (string) $coRefReseller->id,
                    tenantId:     $tenantId,
                    category:     'deal_pipeline',
                    priority:     'high',
                    title:        'You were removed as a co-referrer',
                    body:         $actorName . ' removed you as a co-referrer on "' . $lead->name . '".',
                    actionUrl:    url("/reseller/{$tenantId}/deals/{$dealId}"),
                    actionLabel:  'View Deal',
                    dedupeSuffix: $splitId . ':coreferrer_removed:' . now()->format('YmdH'),
                );

                // Simple email notification
                try {
                    \Illuminate\Support\Facades\Mail::send([], [], function ($msg) use ($coRefReseller, $lead, $actorName, $tenantId) {
                        $msg->to($coRefReseller->email, $coRefReseller->name)
                            ->subject("You've been removed as a co-referrer on \"{$lead->name}\"")
                            ->html(
                                "<p>Hi {$coRefReseller->name},</p>"
                                . "<p><strong>{$actorName}</strong> has removed you as a co-referrer on the deal <strong>\"{$lead->name}\"</strong>.</p>"
                                . "<p>If you have any questions, please contact your program administrator.</p>"
                                . "<p>— ReferralBunny.ai</p>"
                            );
                    });
                } catch (\Throwable) {}
            }
        } catch (\Throwable) {}

        // Notify admin if removal was by a referrer
        if ($actorRole === 'referrer') {
            try {
                app(NotificationDispatchService::class)->dispatchToTenantAdmins(
                    tenantId:     $tenantId,
                    category:     'deal_pipeline',
                    priority:     'normal',
                    title:        'Co-referrer removed',
                    body:         $actorName . ' removed ' . $removedName . ' as a co-referrer on "' . $lead->name . '".',
                    actionUrl:    url("/tenant/{$tenantId}/deals/{$dealId}"),
                    actionLabel:  'View Deal',
                    dedupeSuffix: $splitId . ':coreferrer_removed_admin:' . now()->format('YmdH'),
                );
            } catch (\Throwable) {}
        }

        // Activity log
        try {
            app(DealActivityService::class)->record($lead, 'Co-referrer removed by ' . $actorRole . ': ' . $removedName, 'assignment', [
                'category'   => 'assignment',
                'actor_name' => $actorName,
                'actor_role' => $actorRole,
                'old_values' => ['removed_co_referrer' => $removedName],
            ]);
        } catch (\Throwable) {}

        return response()->json(['success' => true, 'removed_name' => $removedName]);
    }
}
