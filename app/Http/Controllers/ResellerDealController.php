<?php

namespace App\Http\Controllers;

use App\Events\DealStageMoved;
use App\Mail\ResellerInvitation;
use App\Models\DealApprovalRequest;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\LeadHistory;
use App\Models\CommissionSplit;
use App\Models\Reseller;
use App\Models\Tenant;
use App\Models\TenantConfig;
use App\Models\TenantMembership;
use App\Services\CommissionCalculationService;
use App\Services\DealActivityService;
use App\Services\DealPartnerSplitService;
use App\Services\EmailLogger;
use App\Services\NotificationDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

    private function tenantStageConfig(string $tenantId): array
    {
        return \Illuminate\Support\Facades\Cache::remember("tenant_config_stages:{$tenantId}", 300, function () use ($tenantId) {
            $cfg    = TenantConfig::where('tenant_id', $tenantId)->first();
            $stages = array_values(array_filter($cfg?->stages ?? [], fn($s) => is_array($s) && isset($s['key'])));
            $seen   = [];
            $stages = array_values(array_filter($stages, function ($s) use (&$seen) {
                if (isset($seen[$s['key']])) return false;
                return $seen[$s['key']] = true;
            }));
            if (empty($stages)) {
                return [
                    'keys'   => ['introduction', 'presentation', 'contract_sent', 'signed', 'paid'],
                    'labels' => ['introduction' => 'Introduction', 'presentation' => 'Presentation', 'contract_sent' => 'Contract Sent', 'signed' => 'Signed', 'paid' => 'Paid'],
                ];
            }
            return [
                'keys'   => array_column($stages, 'key'),
                'labels' => array_combine(
                    array_column($stages, 'key'),
                    array_map(fn($s) => $s['label'] ?? ucwords(str_replace('_', ' ', $s['key'])), $stages)
                ),
            ];
        });
    }

    private function resellerCanAccessDeal(Reseller $reseller, Lead $lead): bool
    {
        // Primary assignment — case-insensitive to handle import name-casing differences
        if (strtolower((string) $reseller->name) === strtolower((string) $lead->reseller_name)) return true;

        // Secondary: commission split (additional referrer) — tenant-scoped via lead join
        return CommissionSplit::where('lead_id', $lead->id)
            ->whereRaw('LOWER(reseller_name) = ?', [strtolower($reseller->name)])
            ->whereExists(fn($q) => $q->from('leads')
                ->whereColumn('leads.id', 'commission_splits.lead_id')
                ->where('leads.tenant_id', $lead->tenant_id)
                ->whereNull('leads.deleted_at'))
            ->exists();
    }

    // ── Deal Detail (Show) ────────────────────────────────────────────────────

    public function show(string $tenantId, string $dealId)
    {
        $reseller = $this->reseller();
        $lead     = $this->deal($tenantId, $dealId);

        $tenant = DB::table('tenants')->where('id', $tenantId)->first();

        // Pending approval requests for this deal (include clarification_requested so referrer sees the response form)
        $pendingApprovals = [];
        try {
            $pendingApprovals = DealApprovalRequest::where('deal_id', $dealId)
                ->where('tenant_id', $tenantId)
                ->whereIn('status', ['pending', 'clarification_requested'])
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
            // No tenant subquery needed — $lead is already verified to belong to $tenantId by $this->deal()
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

        // Commission splits — anchor through tenant-scoped $lead to prevent IDOR
        $splits = CommissionSplit::where('lead_id', $lead->id)->get();

        // Attach is_anonymous flag so the view can mask co-referrer names per anonymity rule.
        // Also include the lead's primary reseller_name so the implicit-primary row is masked too.
        // The viewing reseller's own record is never masked regardless of their flag.
        $namesToCheck = $splits->pluck('reseller_name')
            ->push($lead->reseller_name)
            ->map(fn($n) => strtolower($n ?? ''))
            ->filter()->values()->toArray();

        // selectRaw alias avoids the `$row->LOWER(name)` property-access bug when using
        // DB::raw() as the $key argument to pluck().
        $anonymousSet = $namesToCheck
            ? \App\Models\Reseller::where('tenant_id', $tenantId)
                ->whereIn(DB::raw('LOWER(name)'), $namesToCheck)
                ->where('is_anonymous', true)
                ->selectRaw('LOWER(name) as lower_name')
                ->pluck('lower_name')
                ->toArray()
            : [];

        $splits = $splits->map(function ($split) use ($anonymousSet, $reseller) {
            $isOwn = strtolower($split->reseller_name ?? '') === strtolower($reseller->name ?? '');
            $split->is_anonymous = !$isOwn && in_array(strtolower($split->reseller_name ?? ''), $anonymousSet);
            return $split;
        });

        // Anonymity of the implicit primary row (when no explicit split record exists)
        $primaryIsOwn = strtolower($lead->reseller_name ?? '') === strtolower($reseller->name ?? '');
        $primaryResellerIsAnonymous = !$primaryIsOwn && in_array(strtolower($lead->reseller_name ?? ''), $anonymousSet);

        // Partner splits
        $partnerSplits = [];
        try {
            $partnerSplits = DB::table('deal_partner_splits')
                ->where('deal_id', $dealId)
                ->whereNull('deleted_at')
                ->where('status', '!=', 'removed')
                ->select(['id', 'partner_name', 'split_share_value', 'split_share_type', 'status', 'currency', 'created_at'])
                ->get()
                ->toArray();
        } catch (\Throwable) {}

        // Attachments
        $attachments = [];
        try {
            $attachments = DB::table('lead_attachments')
                ->where('lead_id', $lead->id)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get()
                ->toArray();
        } catch (\Throwable) {}

        // Activity (lead_history) — internal/admin categories hidden from referrers
        $history = [];
        try {
            $history = DB::table('lead_history')
                ->where('lead_id', $lead->id)
                ->whereNotIn('category', ['internal', 'admin_note', 'admin_only'])
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
            ?? ($addedAmountFallback > 0 ? round($addedAmountFallback * \App\Services\CommissionCalculationService::COMMISSION_POOL_RATE, 2) : 0));

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
            $mySplitRecord = $splits->first(fn($s) => strtolower((string) $s->reseller_name) === strtolower((string) $reseller->name));
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

        // Dynamic stages for view — drives stage tracker and Move Stage modal dropdown.
        $stageCfg       = $this->tenantStageConfig($tenantId);
        $cfgStageOrder  = $stageCfg['keys'];
        $cfgStageLabels = $stageCfg['labels'];

        // no-store: ensures every page load fetches fresh DB data.
        // This guarantees that after a referrer updates the deal amount and the
        // page reloads, the browser never serves a cached (stale) response.
        unset($lead->days_left);
        return response()
            ->view('reseller.deals.show', compact(
                'reseller', 'tenant', 'lead', 'tenantId',
                'pendingApprovals', 'notes', 'splits', 'partnerSplits',
                'attachments', 'history', 'breakdown',
                'myCommission', 'partnersCommission',
                'commissionPool', 'remainingPool',
                'stageRequirements', 'cfgStageOrder', 'cfgStageLabels',
                'primaryResellerIsAnonymous'
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
            $attachments      = [];
            $requestedFiles   = count($request->file('files') ?? []);
            if ($hasFiles) {
                $attachments = \App\Http\Controllers\DealNoteAttachmentController::storeFiles(
                    $request->file('files'), $tenantId, $dealId, $comment->id, (string) $reseller->id, 'referrer'
                );
            }
        } catch (\Throwable $e) {
            Log::error('ResellerDealController addNote failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Could not save note. Please try again.'], 500);
        }

        $attachmentWarning = $hasFiles && count($attachments) < $requestedFiles;

        // Activity + admin notification — outside transaction so failures don't roll back
        try {
            app(DealActivityService::class)->record($lead, 'Note added by referrer', 'note', [
                'category'   => 'note',
                'reseller'   => $reseller->name,
                'actor_name' => $reseller->name,
                'actor_role' => 'referrer',
            ]);
        } catch (\Throwable) {}

        // LGU IDS: auto-complete open note tasks for this deal
        if ($lead->tenant_id === 'lgu-ids') {
            try {
                app(\App\Services\LguIds\LguIdsDealNoteTaskService::class)
                    ->autocompleteForDeal($tenantId, $dealId, $reseller, (string) $reseller->id);
            } catch (\Throwable) {}
        }

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

        $response = [
            'success' => true,
            'note'    => [
                'id'          => $comment->id,
                'body'        => $comment->body,
                'author'      => $reseller->name,
                'attachments' => count($attachments),
                'created_ago' => 'just now',
            ],
        ];
        if ($attachmentWarning) {
            $response['attachment_warning'] = 'Some files could not be attached. Please try re-uploading.';
        }

        return response()->json($response);
    }

    // ── Update Deal Amount ────────────────────────────────────────────────────

    public function updateAmount(Request $request, string $tenantId, string $dealId): JsonResponse
    {
        $reseller = $this->reseller();
        $lead     = $this->deal($tenantId, $dealId);

        // Only the primary referrer may change the deal amount — same guard as addReferrer().
        $isPrimary = strtolower($reseller->name ?? '') === strtolower($lead->reseller_name ?? '')
            || CommissionSplit::where('lead_id', $lead->id)
                ->where('role', 'primary')
                ->whereRaw('LOWER(reseller_name) = ?', [strtolower($reseller->name)])
                ->exists();

        if (!$isPrimary) {
            return response()->json(['error' => 'Only the primary Referrer on this deal can update the deal amount.'], 403);
        }

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
            $locked = Lead::where('id', $lead->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->commission_status ?? '', ['locked', 'paid'])) {
                DB::rollBack();
                return response()->json(['error' => 'Deal amount cannot be changed after commission has been ' . $locked->commission_status . '. Contact your admin.'], 422);
            }
            $locked->update([
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
            app(DealActivityService::class)->record($lead->fresh(), 'Deal amount updated by referrer', 'financial', [
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

        $stageKeys  = $this->tenantStageConfig($tenantId)['keys'];

        $data = $request->validate([
            'stage'  => ['required', Rule::in($stageKeys)],
            'reason' => 'nullable|string|max:1000',
        ]);

        $oldStage    = $lead->stage;
        $targetStage = $data['stage'];

        if ($oldStage === $targetStage) {
            return response()->json(['error' => 'Deal is already at this stage.'], 422);
        }

        // Server-side forward-only guard — UI enforces this too but API must also validate
        $stageOrder = $stageKeys;
        $currentIdx = array_search($oldStage, $stageOrder, true);
        $targetIdx  = array_search($targetStage, $stageOrder, true);
        if ($targetIdx !== false && $currentIdx !== false && $targetIdx <= $currentIdx) {
            return response()->json(['error' => 'Stage moves must progress forward. You cannot move a deal to an earlier stage.'], 422);
        }

        // Check for a pending approval for this deal + stage
        $pendingExists = DealApprovalRequest::where('deal_id', $dealId)
            ->where('tenant_id', $tenantId)
            ->where('type', 'deal_stage_move')
            ->whereIn('status', ['pending', 'clarification_requested'])
            ->exists();

        if ($pendingExists) {
            return response()->json(['error' => 'A stage move request is already pending approval.'], 422);
        }

        if ($lead->commission_status === 'locked' || $lead->commission_status === 'paid') {
            return response()->json(['error' => 'Cannot move stage: commission is already locked or paid.'], 422);
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

        // HandleDealStageMoved listener notifies admin + reseller in-app + reseller email
        try {
            DealStageMoved::dispatch(
                leadId:       (string) $lead->id,
                leadName:     $lead->name,
                tenantId:     $tenantId,
                resellerName: $reseller->name,
                resellerId:   (string) $reseller->id,
                resellerEmail: $reseller->email,
                fromStage:    $oldStage,
                toStage:      $targetStage,
                dealValue:    (float) ($lead->deal_value ?? 0),
                movedByName:  $reseller->name,
            );
        } catch (\Throwable) {}

        // Notify active partners on this deal
        try {
            $stageName = ucwords(str_replace('_', ' ', $targetStage));
            $fromName  = ucwords(str_replace('_', ' ', $oldStage));
            $priority  = in_array($targetStage, ['signed', 'paid']) ? 'high' : 'normal';
            $minute    = now()->format('YmdH');

            $partnerSplits = DB::table('deal_partner_splits')
                ->where('deal_id', $lead->id)
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where('status', '!=', 'removed')
                ->whereNotNull('partner_user_id')
                ->pluck('partner_user_id');

            foreach ($partnerSplits as $partnerUserId) {
                app(NotificationDispatchService::class)->dispatchToPartner(
                    partnerId:    (string) $partnerUserId,
                    tenantId:     $tenantId,
                    category:     'deal_pipeline',
                    priority:     $priority,
                    title:        "Deal moved to {$stageName}",
                    body:         "\"{$lead->name}\" has been moved from {$fromName} to {$stageName}.",
                    actionUrl:    "/partner/deals/{$lead->id}",
                    actionLabel:  'View Deal',
                    dedupeSuffix: "{$lead->id}:stage:{$targetStage}:p:{$minute}",
                );
                Cache::forget("notif_unread_partner_{$partnerUserId}");
                Cache::forget("partner_notif_unread:{$partnerUserId}");
            }
        } catch (\Throwable) {}

        return response()->json(['success' => true, 'stage' => $targetStage]);
    }

    // ── Request Stage Approval (when docs incomplete) ────────────────────────

    public function requestStageApproval(Request $request, string $tenantId, string $dealId): JsonResponse
    {
        $reseller = $this->reseller();
        $lead     = $this->deal($tenantId, $dealId);

        $stageKeysApproval  = $this->tenantStageConfig($tenantId)['keys'];

        $data = $request->validate([
            'target_stage'           => ['required', Rule::in($stageKeysApproval)],
            'reason'                 => 'required|string|max:2000',
            'missing_requirements'   => 'nullable|array',
            'missing_requirements.*' => 'string|max:200',
        ]);

        // Block if already pending or awaiting clarification
        $alreadyPending = DealApprovalRequest::where('deal_id', $dealId)
            ->where('tenant_id', $tenantId)
            ->where('type', 'deal_stage_move')
            ->whereIn('status', ['pending', 'clarification_requested'])
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

        // Block if already pending or awaiting clarification
        $alreadyPending = DealApprovalRequest::where('deal_id', $dealId)
            ->where('tenant_id', $tenantId)
            ->where('type', 'deal_archive')
            ->whereIn('status', ['pending', 'clarification_requested'])
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

        try {
            \Illuminate\Support\Facades\Cache::deleteMultiple(["lifecycle_archive_req_metrics:{$tenantId}", "lifecycle_del_arch_metrics:{$tenantId}", "subtab_badge_counts:{$tenantId}"]);
            app(\App\Services\CriticalActionService::class)->invalidateAllAdminBadges($tenantId);
        } catch (\Throwable) {}

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

        // Email tenant owners/admins about new archive request
        try {
            $archiveTenant = Tenant::find($tenantId);
            if ($archiveTenant) {
                $mailDealName   = $lead->name;
                $mailStage      = ucwords(str_replace('_', ' ', $lead->stage ?? ''));
                $mailDealValue  = '₱' . number_format((float)($lead->deal_value ?? 0), 0);
                $mailReason     = $approval->reason;
                $mailReviewUrl  = url("/tenant/{$tenantId}/deals/archive-requests/{$approval->id}");
                $mailTenantName = $archiveTenant->name;
                $mailApprovalId = $approval->id;
                \App\Models\TenantMembership::where('tenant_id', $tenantId)
                    ->whereIn('role', ['owner', 'admin'])
                    ->where('status', 'active')
                    ->with('tenantUser')
                    ->get()
                    ->each(function ($membership) use ($mailDealName, $mailStage, $mailDealValue, $mailReason, $mailReviewUrl, $mailTenantName, $tenantId, $mailApprovalId) {
                        $tu = $membership->tenantUser;
                        if ($tu?->email) {
                            EmailLogger::send(
                                mailable:      new \App\Mail\ArchiveRequestSubmittedMail(
                                    recipientEmail: $tu->email,
                                    dealName:       $mailDealName,
                                    stage:          $mailStage,
                                    dealValue:      $mailDealValue,
                                    reason:         $mailReason,
                                    reviewUrl:      $mailReviewUrl,
                                    tenantName:     $mailTenantName,
                                    adminName:      $tu->full_name ?: $tu->email,
                                ),
                                recipientEmail: $tu->email,
                                recipientType:  'tenant_user',
                                emailKey:       'archive_submitted.' . $mailApprovalId . '.' . $tu->id,
                                subject:        "Archive request submitted for \"{$mailDealName}\"",
                                recipientId:    (string) $tu->id,
                                tenantId:       $tenantId,
                            );
                        }
                    });
            }
        } catch (\Throwable) {}

        return response()->json(['success' => true, 'approval_id' => $approval->id]);
    }

    // ── Referrer responds to admin clarification on an archive request ────────

    public function respondToArchiveRequestClarification(Request $request, string $tenantId, string $requestId): RedirectResponse
    {
        $reseller = $this->reseller();

        $data = $request->validate(['visible_response' => 'required|string|max:1000']);

        $approval = DealApprovalRequest::where('id', $requestId)
            ->where('tenant_id', $tenantId)
            ->where('type', 'deal_archive')
            ->where('status', 'clarification_requested')
            ->where('requested_by_id', (string) $reseller->id)
            ->where('requested_by_type', 'reseller')
            ->firstOrFail();

        if ($approval->visible_response) {
            return redirect()
                ->route('reseller.deals.show', [$tenantId, $approval->deal_id])
                ->with('error', 'You have already responded to this clarification.');
        }

        $approval->update([
            'visible_response' => $data['visible_response'],
        ]);

        try {
            app(\App\Services\CriticalActionService::class)->invalidateAllAdminBadges($tenantId);
        } catch (\Throwable) {}
        \Illuminate\Support\Facades\Cache::deleteMultiple(["subtab_badge_counts:{$tenantId}", "lifecycle_archive_req_metrics:{$tenantId}"]);

        $dealId = $approval->deal_id;
        $lead   = null;
        try { $lead = Lead::where('id', $dealId)->where('tenant_id', $tenantId)->first(); } catch (\Throwable) {}

        // Activity log
        try {
            if ($lead) {
                app(DealActivityService::class)->record($lead, 'Referrer responded to archive clarification: ' . \Illuminate\Support\Str::limit($data['visible_response'], 100), 'archive', [
                    'category'   => 'archive',
                    'actor_name' => $reseller->name ?? 'Referrer',
                    'actor_role' => 'referrer',
                    'new_values' => ['visible_response' => $data['visible_response']],
                ]);
            }
        } catch (\Throwable) {}

        // Notify all tenant admins/managers about the referrer's response
        try {
            $dealName = $lead?->name ?? ($approval->request_payload['deal_name'] ?? 'a deal');

            app(NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'deal_pipeline',
                priority:     'high',
                title:        'Referrer responded to clarification — ' . $dealName,
                body:         ($reseller->name ?? 'Referrer') . ' replied to your clarification request for "' . $dealName . '". Review and decide.',
                actionUrl:    url("/tenant/{$tenantId}/deals/archive-requests/{$requestId}"),
                actionLabel:  'Review Request',
                dedupeSuffix: $requestId . ':clarification_responded:' . now()->format('Ymd'),
            );
        } catch (\Throwable) {}

        // Email all tenant admins/managers about the referrer's response
        try {
            $archiveTenant = Tenant::find($tenantId);
            if ($archiveTenant) {
                $mailDealName     = $lead?->name ?? ($approval->request_payload['deal_name'] ?? 'a deal');
                $mailDealUrl      = url("/tenant/{$tenantId}/deals/archive-requests/{$requestId}");
                $mailVisibleResp  = $approval->visible_response;
                $mailTenantName   = $archiveTenant->name;
                $mailResellerName = $reseller->name;
                TenantMembership::where('tenant_id', $tenantId)
                    ->whereIn('role', ['owner', 'admin', 'manager'])
                    ->where('status', 'active')
                    ->with('tenantUser')
                    ->get()
                    ->each(function ($membership) use ($mailDealName, $mailDealUrl, $mailVisibleResp, $mailTenantName, $mailResellerName, $tenantId, $requestId) {
                        $tu = $membership->tenantUser;
                        if ($tu?->email) {
                            EmailLogger::send(
                                mailable:      new \App\Mail\ArchiveRequestRespondedMail(
                                    recipientEmail:  $tu->email,
                                    dealName:        $mailDealName,
                                    dealUrl:         $mailDealUrl,
                                    visibleResponse: $mailVisibleResp,
                                    tenantName:      $mailTenantName,
                                    resellerName:    $mailResellerName,
                                    adminName:       $tu->full_name ?: $tu->email,
                                ),
                                recipientEmail: $tu->email,
                                recipientType:  'tenant_user',
                                emailKey:       'archive_responded.' . $requestId . '.' . $tu->id,
                                subject:        "Referrer responded to archive clarification — \"{$mailDealName}\"",
                                recipientId:    (string) $tu->id,
                                tenantId:       $tenantId,
                            );
                        }
                    });
            }
        } catch (\Throwable) {}

        return redirect()
            ->route('reseller.deals.show', [$tenantId, $dealId])
            ->with('success', 'Your response has been sent. The admin will review and decide on the archive request.');
    }

    // ── Admin: Add Co-Referrer ───────────────────────────────────────────────

    public function adminAddReferrer(Request $request, string $tenantId, string $dealId): JsonResponse
    {
        // Only tenant owners, admins, and managers (plus SA) may add co-referrers
        $role = \App\Services\TenantContext::role();
        if (!\App\Services\TenantContext::isSuperAdmin() && !in_array($role, ['owner', 'admin', 'manager'])) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        // Enforce tenant context — prevents supplying a tenantId belonging to another tenant
        if (!\App\Services\TenantContext::isSuperAdmin()) {
            $ctxId = \App\Services\TenantContext::requireId();
            if ($ctxId !== $tenantId) {
                return response()->json(['error' => 'Forbidden.'], 403);
            }
        }

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

        $existingSecondary = CommissionSplit::where('lead_id', $lead->id)
            ->where('role', 'secondary')
            ->sum('percentage');
        if ($existingSecondary + $percentage > 100.005) {
            $available = max(0.0, round(100.0 - (float) $existingSecondary, 2));
            return response()->json(['error' => "Co-referrer splits cannot exceed 100% combined. Available: {$available}%.", 'max_percentage' => $available], 422);
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
                Cache::forget("notif_unread_reseller_{$targetReseller->id}");
            } catch (\Throwable) {}
        }

        // Notify primary referrer — same as reseller-initiated path.
        // Look up implicit primary via lead->reseller_name; no CommissionSplit(role='primary') in production.
        // Skip if the admin actor happens to be the primary referrer by name.
        try {
            $primaryReseller = Reseller::where('tenant_id', $tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower($lead->reseller_name ?? '')])
                ->first();
            if ($primaryReseller && strtolower($primaryReseller->name ?? '') !== strtolower($actorName ?? '')) {
                app(NotificationDispatchService::class)->dispatchToReseller(
                    resellerId:   (string) $primaryReseller->id,
                    tenantId:     $tenantId,
                    category:     'deal_pipeline',
                    priority:     'normal',
                    title:        'Co-referrer added to your deal',
                    body:         $actorName . ' added ' . ($displayName !== $email ? $displayName : $email) . ' (' . $percentage . '%) as a co-referrer on "' . $lead->name . '".',
                    actionUrl:    url("/reseller/{$tenantId}/deals/{$lead->id}"),
                    actionLabel:  'View Deal',
                    dedupeSuffix: $lead->id . ':primary_coreferrer_notice:' . md5($email),
                );
                Cache::forget("notif_unread_reseller_{$primaryReseller->id}");
            }
        } catch (\Throwable) {}

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

            $partnerInviteKey = 'partner_invite.' . $newPartner->id;
            $inviteSent = EmailLogger::send(
                mailable:      new \App\Mail\PartnerInviteMail(
                    recipientEmail:   $partnerEmail,
                    partnerFirstName: $newPartner->first_name ?? 'there',
                    tenantName:       $tenant->name,
                    inviterName:      $reseller->name ?? 'A referrer',
                    setupUrl:         url('/partner/invite/' . $newPartner->setup_token),
                ),
                recipientEmail: $partnerEmail,
                recipientType:  'partner',
                emailKey:       $partnerInviteKey,
                subject:        "You're invited as a Partner — {$tenant->name}",
                recipientId:    (string) $newPartner->id,
                tenantId:       $tenantId,
                dailyDedup:     true,
            );
            if (!$inviteSent) {
                Log::warning('PartnerInviteMail queue failed in addPartnerSplit', [
                    'tenant_id' => $tenantId,
                    'email'     => $partnerEmail,
                    'email_key' => $partnerInviteKey,
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
                \Illuminate\Support\Facades\Cache::forget("notif_unread_partner_{$existingPartner->id}");
                \Illuminate\Support\Facades\Cache::forget("partner_notif_unread:{$existingPartner->id}");
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

        // Notify primary referrer when someone else adds a partner — partner share comes out of
        // the pool before referrers are paid, so the primary's net commission decreases.
        try {
            $primaryReseller = Reseller::where('tenant_id', $tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower($lead->reseller_name ?? '')])
                ->first();
            if ($primaryReseller && strtolower($primaryReseller->name ?? '') !== strtolower($reseller->name ?? '')) {
                app(NotificationDispatchService::class)->dispatchToReseller(
                    resellerId:   (string) $primaryReseller->id,
                    tenantId:     $tenantId,
                    category:     'deal_pipeline',
                    priority:     'normal',
                    title:        'Partner added to your deal',
                    body:         $reseller->name . ' added ' . $data['partner_name'] . ' as a partner on "' . $lead->name . '".',
                    actionUrl:    url("/reseller/{$tenantId}/deals/{$lead->id}"),
                    actionLabel:  'View Deal',
                    dedupeSuffix: $lead->id . ':partner_added_primary:' . md5($data['partner_name'] . ($partnerEmail ?? '')),
                );
                Cache::forget("notif_unread_reseller_{$primaryReseller->id}");
            }
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

        // Only the primary referrer on this deal may add co-referrers
        $isPrimary = strtolower($reseller->name ?? '') === strtolower($lead->reseller_name ?? '')
            || CommissionSplit::where('lead_id', $lead->id)
                ->where('role', 'primary')
                ->whereRaw('LOWER(reseller_name) = ?', [strtolower($reseller->name)])
                ->exists();

        if (!$isPrimary) {
            return response()->json(['error' => 'Only the primary Referrer on this deal can add co-referrers.'], 403);
        }

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

        // Look up tenant user (admin/manager) by email for notification
        $tenantUserByEmail = DB::table('tenant_users as tu')
            ->join('tenant_memberships as tm', 'tm.tenant_user_id', '=', 'tu.id')
            ->where('tm.tenant_id', $tenantId)
            ->where('tm.status', 'active')
            ->whereRaw('LOWER(tu.email) = ?', [$email])
            ->selectRaw("tu.id, tu.email, TRIM(CONCAT(COALESCE(tu.first_name,''), ' ', COALESCE(tu.last_name,''))) as name, tm.role")
            ->first();

        // Commit the split creation — 100% cap check is inside the transaction with
        // lockForUpdate to prevent a race condition between two concurrent adds.
        DB::beginTransaction();
        try {
            $existingSecondary = CommissionSplit::where('lead_id', $lead->id)
                ->where('role', 'secondary')
                ->lockForUpdate()
                ->sum('percentage');
            if ($existingSecondary + $percentage > 100.005) {
                DB::rollBack();
                $available = max(0.0, round(100.0 - (float) $existingSecondary, 2));
                return response()->json([
                    'error' => "Co-referrer splits cannot exceed 100% combined. You can allocate up to {$available}% to this co-referrer.",
                    'max_percentage' => $available,
                ], 422);
            }
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
                Cache::forget("notif_unread_reseller_{$targetReseller->id}");
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
                    notifiableType:   'tenant_admin',
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

                $setupUrl       = url("/reseller/setup?token={$inviteToken}");
                $inviteEmailKey = 'reseller_invite.' . $newReseller->id;
                $sent = EmailLogger::send(
                    mailable:      new ResellerInvitation(
                        resellerName:  $newReseller->name,
                        resellerEmail: $email,
                        tenantName:    $tenant?->name ?? 'ReferralBunny',
                        setupUrl:      $setupUrl,
                        dealName:      $lead->name,
                        dealCount:     1,
                        dealNames:     [$lead->name],
                    ),
                    recipientEmail: $email,
                    recipientType:  'reseller',
                    emailKey:       $inviteEmailKey,
                    subject:        "You've been invited as a referrer for " . ($tenant?->name ?? 'ReferralBunny'),
                    recipientId:    (string) $newReseller->id,
                    tenantId:       $tenantId,
                    dailyDedup:     true,
                );
                if (!$sent) {
                    Log::warning('addReferrer: invite email failed to queue', [
                        'email'     => $email,
                        'email_key' => $inviteEmailKey,
                    ]);
                }
                $invited = true;
            } catch (\Throwable $e) {
                Log::warning('addReferrer: reseller creation failed', ['email' => $email, 'error' => $e->getMessage()]);
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
        // so they know their commission pool now has an additional split.
        // Look up the implicit primary via lead->reseller_name — no explicit CommissionSplit(role='primary')
        // record exists in production, so checking that table would always miss.
        try {
            $primaryReseller = Reseller::where('tenant_id', $tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower($lead->reseller_name ?? '')])
                ->first();
            if ($primaryReseller && strtolower($primaryReseller->name ?? '') !== strtolower($reseller->name ?? '')) {
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
                Cache::forget("notif_unread_reseller_{$primaryReseller->id}");
            }
        } catch (\Throwable) {}

        $message = $notified ? 'Co-referrer added — they have been notified.'
            : ($invited  ? 'Co-referrer added — an invitation email has been sent.'
                         : 'Co-referrer added successfully.');

        return response()->json(['success' => true, 'message' => $message]);
    }

    // ── Approve / Reject deal approval (for tenant admins) ───────────────────

    public function approveRequest(Request $request, string $tenantId, string $approvalId): JsonResponse|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            // super_admin — unconditionally allowed
        } elseif (Auth::guard('tenant')->check()) {
            $userId     = Auth::guard('tenant')->id();
            $memberRole = \App\Models\TenantMembership::where('tenant_user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->value('role') ?? 'viewer';
            if (!in_array($memberRole, ['owner', 'admin', 'manager'])) {
                abort(403);
            }
        } else {
            abort(403);
        }

        $data = $request->validate(['reviewer_note' => 'nullable|string|max:1000']);

        $approval = DealApprovalRequest::where('id', $approvalId)
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['pending', 'clarification_requested'])
            ->firstOrFail();

        $lead = Lead::where('id', $approval->deal_id)->where('tenant_id', $tenantId)->first();

        // Resolve reviewer identity OUTSIDE the transaction — mirrors rejectRequest pattern
        $reviewerUser = Auth::guard('tenant')->user() ?? Auth::guard('web')->user();
        $reviewerName = $reviewerUser?->full_name ?? $reviewerUser?->name ?? $reviewerUser?->email ?? 'Admin';

        $oldStage      = null;
        $targetStage   = null;
        $archiveReason = null;

        DB::beginTransaction();
        try {
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
                    $oldStage  = $lead->stage;
                    $stageCfg  = $this->tenantStageConfig($tenantId);
                    $stageKeys = $stageCfg['keys'];
                    $oldIdx    = array_search($oldStage, $stageKeys);
                    $newIdx    = array_search($targetStage, $stageKeys);
                    if ($oldIdx !== false && $newIdx !== false && $newIdx <= $oldIdx) {
                        throw new \RuntimeException('Stage move must be forward only.');
                    }
                    $lead->update(['stage' => $targetStage]);
                }
            } elseif ($approval->type === 'deal_archive' && $lead) {
                $archiveReason = $approval->reason ?? ($approval->request_payload['reason'] ?? null);
                $lead->update([
                    'status'         => 'archived',
                    'archive_reason' => $archiveReason,
                    'archived_at'    => now(),
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ResellerDealController approveRequest failed', ['error' => $e->getMessage()]);
            if (!$request->wantsJson()) {
                return back()->with('error', 'Could not process approval. Please try again.');
            }
            return response()->json(['error' => 'Could not process approval. Please try again.'], 500);
        }

        // Activity log + event dispatch — must run after DB commit
        if ($approval->type === 'deal_stage_move' && $lead && $oldStage !== null && $targetStage !== null) {
            try {
                app(DealActivityService::class)->record($lead, 'Stage move approved by admin', 'stage', [
                    'category'   => 'stage',
                    'actor_name' => $reviewerName,
                    'actor_role' => 'admin',
                    'old_values' => ['stage' => $oldStage],
                    'new_values' => ['stage' => $targetStage],
                ]);
                $approvalResellerId    = $approval->requested_by_type === 'reseller' ? $approval->requested_by_id : null;
                $approvalResellerEmail = $approvalResellerId
                    ? DB::table('resellers')->where('id', $approvalResellerId)->value('email')
                    : null;
                DealStageMoved::dispatch(
                    leadId:        $lead->id,
                    leadName:      $lead->name,
                    tenantId:      $lead->tenant_id,
                    resellerName:  $lead->reseller_name ?? '',
                    resellerId:    $approvalResellerId,
                    resellerEmail: $approvalResellerEmail,
                    fromStage:     $oldStage,
                    toStage:       $targetStage,
                    dealValue:     (float) ($lead->deal_value ?? 0),
                    movedByName:   $reviewerName,
                );
            } catch (\Throwable) {}
        } elseif ($approval->type === 'deal_archive' && $lead) {
            try {
                app(DealActivityService::class)->record($lead, 'Deal archive approved and closed by ' . $reviewerName, 'archive', [
                    'category'   => 'archive',
                    'actor_name' => $reviewerName,
                    'actor_role' => 'admin',
                    'new_values' => ['archive_reason' => $archiveReason],
                ]);
            } catch (\Throwable) {}
        }

        \Illuminate\Support\Facades\Cache::deleteMultiple(["dash_counts:{$tenantId}", "lifecycle_archive_req_metrics:{$tenantId}", "lifecycle_del_arch_metrics:{$tenantId}", "subtab_badge_counts:{$tenantId}"]);
        try {
            app(\App\Services\CriticalActionService::class)->invalidateAllAdminBadges($tenantId);
        } catch (\Throwable) {}

        // LGU IDS: create note task if deal moved to a target stage with no notes
        if ($approval->type === 'deal_stage_move' && $lead && $lead->tenant_id === 'lgu-ids') {
            try {
                app(\App\Services\LguIds\LguIdsDealNoteTaskService::class)
                    ->createForDeal($lead->fresh(), 'stage_move_approved');
            } catch (\Throwable) {}
        }

        // Notify after commit — never inside transaction
        // deal_stage_move: HandleDealStageMoved listener already notifies the referrer via DealStageMoved::dispatch above
        if ($approval->requested_by_type === 'reseller') {
            try {
                $dealName = $lead?->name ?? ($approval->request_payload['deal_name'] ?? 'a deal');
                if ($approval->type === 'deal_archive') {
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
                    Cache::forget("notif_unread_reseller_{$approval->requested_by_id}");
                }
            } catch (\Throwable) {}

            // Email referrer on archive approval
            try {
                if ($approval->type === 'deal_archive') {
                    $approvedReseller = Reseller::where('id', $approval->requested_by_id)->where('tenant_id', $tenantId)->first();
                    $approvedTenant   = Tenant::find($tenantId);
                    if ($approvedReseller?->email && $approvedTenant) {
                        $dealName = $lead?->name ?? ($approval->request_payload['deal_name'] ?? 'your deal');
                        EmailLogger::send(
                            mailable:      new \App\Mail\ArchiveRequestApprovedMail(
                                recipientEmail: $approvedReseller->email,
                                dealName:       $dealName,
                                stage:          $lead ? ucwords(str_replace('_', ' ', $lead->stage ?? '')) : '—',
                                dealValue:      $lead ? '₱' . number_format((float)($lead->deal_value ?? 0), 0) : '—',
                                reviewerNote:   $approval->reviewer_note,
                                resellerName:   $approvedReseller->name,
                                tenantName:     $approvedTenant->name,
                            ),
                            recipientEmail: $approvedReseller->email,
                            recipientType:  'reseller',
                            emailKey:       'archive_approved.' . $approvalId . '.' . $approvedReseller->id,
                            subject:        "Archive request approved for \"{$dealName}\"",
                            recipientId:    (string) $approvedReseller->id,
                            tenantId:       $tenantId,
                        );
                    }
                }
            } catch (\Throwable) {}
        }

        if (!$request->wantsJson()) {
            if ($approval->type === 'deal_archive') {
                return redirect()
                    ->route('tenant.deals.archive-requests', $tenantId)
                    ->with('success', '"' . ($lead?->name ?? 'Deal') . '" has been archived.');
            }
            return redirect()
                ->route('tenant.deals', $tenantId)
                ->with('success', 'Stage move approved for "' . ($lead?->name ?? 'deal') . '".');
        }
        return response()->json(['success' => true, 'type' => $approval->type]);
    }

    public function rejectRequest(Request $request, string $tenantId, string $approvalId): JsonResponse|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            // super_admin — unconditionally allowed
        } elseif (Auth::guard('tenant')->check()) {
            $userId     = Auth::guard('tenant')->id();
            $memberRole = \App\Models\TenantMembership::where('tenant_user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->value('role') ?? 'viewer';
            if (!in_array($memberRole, ['owner', 'admin', 'manager'])) {
                abort(403);
            }
        } else {
            abort(403);
        }

        $data = $request->validate(['reviewer_note' => 'required|string|max:1000']);

        $approval = DealApprovalRequest::where('id', $approvalId)
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['pending', 'clarification_requested'])
            ->firstOrFail();

        $lead = Lead::where('id', $approval->deal_id)->where('tenant_id', $tenantId)->first();

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

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ResellerDealController rejectRequest failed', ['error' => $e->getMessage()]);
            if (!$request->wantsJson()) {
                return back()->with('error', 'Could not process rejection. Please try again.');
            }
            return response()->json(['error' => 'Could not process rejection. Please try again.'], 500);
        }

        // Activity log — must run after DB commit
        if ($lead) {
            try {
                $activityLabel = $approval->type === 'deal_stage_move'
                    ? 'Stage move request declined by ' . $reviewerName
                    : 'Archive request declined by ' . $reviewerName;
                app(DealActivityService::class)->record($lead, $activityLabel . ' — reason: ' . \Illuminate\Support\Str::limit($data['reviewer_note'], 100), $approval->type === 'deal_stage_move' ? 'stage' : 'archive', [
                    'category'   => $approval->type === 'deal_stage_move' ? 'stage' : 'archive',
                    'actor_name' => $reviewerName,
                    'actor_role' => 'admin',
                    'new_values' => ['rejection_reason' => $data['reviewer_note']],
                ]);
            } catch (\Throwable) {}
        }

        \Illuminate\Support\Facades\Cache::deleteMultiple(["dash_counts:{$tenantId}", "lifecycle_archive_req_metrics:{$tenantId}", "lifecycle_del_arch_metrics:{$tenantId}", "subtab_badge_counts:{$tenantId}"]);
        try {
            app(\App\Services\CriticalActionService::class)->invalidateAllAdminBadges($tenantId);
        } catch (\Throwable) {}

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
                Cache::forget("notif_unread_reseller_{$approval->requested_by_id}");
            } catch (\Throwable) {}

            // Email referrer on archive rejection
            try {
                if ($approval->type === 'deal_archive') {
                    $rejectedReseller = Reseller::where('id', $approval->requested_by_id)->where('tenant_id', $tenantId)->first();
                    $rejectedTenant   = Tenant::find($tenantId);
                    if ($rejectedReseller?->email && $rejectedTenant) {
                        $dealName = $lead?->name ?? ($approval->request_payload['deal_name'] ?? 'your deal');
                        EmailLogger::send(
                            mailable:      new \App\Mail\ArchiveRequestRejectedMail(
                                recipientEmail: $rejectedReseller->email,
                                dealName:       $dealName,
                                stage:          $lead ? ucwords(str_replace('_', ' ', $lead->stage ?? '')) : '—',
                                dealUrl:        $lead ? url("/reseller/{$tenantId}/deals/{$lead->id}") : null,
                                reviewerNote:   $approval->reviewer_note,
                                resellerName:   $rejectedReseller->name,
                                tenantName:     $rejectedTenant->name,
                            ),
                            recipientEmail: $rejectedReseller->email,
                            recipientType:  'reseller',
                            emailKey:       'archive_rejected.' . $approvalId . '.' . $rejectedReseller->id,
                            subject:        "Archive request rejected for \"{$dealName}\"",
                            recipientId:    (string) $rejectedReseller->id,
                            tenantId:       $tenantId,
                        );
                    }
                }
            } catch (\Throwable) {}
        }

        if (!$request->wantsJson()) {
            $msg = $approval->type === 'deal_archive'
                ? 'Archive request for "' . ($lead?->name ?? 'deal') . '" has been rejected.'
                : 'Stage move request has been rejected.';
            return redirect()->route('tenant.deals.archive-requests', $tenantId)->with('success', $msg);
        }
        return response()->json(['success' => true]);
    }

    // ── Update Co-Referrer Split (Referrer-initiated) ─────────────────────────

    public function updateCoReferrerSplit(Request $request, string $tenantId, string $dealId, string $splitId): JsonResponse
    {
        $reseller = $this->reseller();
        $lead     = $this->deal($tenantId, $dealId);

        // Only the primary referrer on this deal may adjust co-referrer splits.
        // Matches both the implicit primary (lead->reseller_name) and any explicit CommissionSplit(role='primary').
        $isPrimary = strtolower($reseller->name ?? '') === strtolower($lead->reseller_name ?? '')
            || CommissionSplit::where('lead_id', $lead->id)
                ->where('role', 'primary')
                ->whereRaw('LOWER(reseller_name) = ?', [strtolower($reseller->name)])
                ->exists();

        if (!$isPrimary) {
            return response()->json(['error' => 'Only the primary Referrer on this deal can adjust co-referrer shares.'], 403);
        }

        return $this->doUpdateSplit($request, $tenantId, $dealId, $splitId, $reseller->name, 'referrer', $lead);
    }

    // ── Update Co-Referrer Split (Admin-initiated) ────────────────────────────

    public function adminUpdateCoReferrerSplit(Request $request, string $tenantId, string $dealId, string $splitId): JsonResponse
    {
        $actor = \Illuminate\Support\Facades\Auth::guard('tenant')->user()
              ?? \Illuminate\Support\Facades\Auth::guard('web')->user();
        if (!$actor) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        // Only owner/admin/manager or super admin may modify co-referrer splits
        if (!\Illuminate\Support\Facades\Auth::guard('web')->check()) {
            $role = \App\Services\TenantContext::role();
            if (!in_array($role, ['owner', 'admin', 'manager'])) {
                return response()->json(['error' => 'Forbidden.'], 403);
            }
        }

        if (!\Illuminate\Support\Facades\Auth::guard('web')->check()) {
            $ctxId = \App\Services\TenantContext::requireId();
            if ($ctxId !== $tenantId) {
                return response()->json(['error' => 'Forbidden.'], 403);
            }
        }

        $lead      = \App\Models\Lead::where('id', $dealId)->where('tenant_id', $tenantId)->firstOrFail();
        $actorName = $actor->full_name ?: $actor->email ?? 'Admin';

        return $this->doUpdateSplit($request, $tenantId, $dealId, $splitId, $actorName, 'admin', $lead);
    }

    /** Shared logic for updating a co-referrer commission split. */
    private function doUpdateSplit(Request $request, string $tenantId, string $dealId, string $splitId, string $actorName, string $actorRole, Lead $lead): JsonResponse
    {
        $data = $request->validate([
            'percentage' => 'required|numeric|min:0.01|max:100',
        ]);

        $newPct = round((float) $data['percentage'], 2);

        // Block edits when commission is finalised
        if (in_array($lead->commission_status, ['locked', 'paid'], true)) {
            return response()->json(['error' => 'Commission splits cannot be modified after commission is locked or paid.'], 422);
        }

        // Quick pre-check before acquiring lock
        $split = CommissionSplit::where('id', $splitId)->where('lead_id', $lead->id)->firstOrFail();
        if ($split->role !== 'secondary') {
            return response()->json(['error' => 'Only co-referrer splits can be adjusted here.'], 422);
        }

        $oldPct = DB::transaction(function () use ($splitId, $lead, $newPct) {
            // Lock all splits for this lead to prevent concurrent percentage races
            CommissionSplit::where('lead_id', $lead->id)->lockForUpdate()->get();
            // Re-read lead under lock to prevent race with concurrent commission_status change
            $freshLead = Lead::where('id', $lead->id)->lockForUpdate()->first();
            if ($freshLead && in_array($freshLead->commission_status, ['locked', 'paid'], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'commission_status' => 'Commission was locked by another process. Splits cannot be modified.',
                ]);
            }

            $split = CommissionSplit::where('id', $splitId)->where('lead_id', $lead->id)->firstOrFail();

            // Cap check: secondary splits combined must not exceed 100%
            $otherSecondaryTotal = CommissionSplit::where('lead_id', $lead->id)
                ->where('id', '!=', $splitId)
                ->where('role', 'secondary')
                ->sum('percentage');

            if ($otherSecondaryTotal + $newPct > 100.005) {
                $available = max(0.0, round(100.0 - (float) $otherSecondaryTotal, 2));
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'percentage' => "Cannot exceed 100% total. Maximum available for this co-referrer: {$available}%.",
                ]);
            }

            $oldPct = (float) $split->percentage;
            $split->update(['percentage' => $newPct]);

            return $oldPct;
        });

        $split->refresh();

        // Notify the co-referrer whose share changed
        $hour = now()->format('YmdH');
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
                    dedupeSuffix: $splitId . ':share_updated:' . $hour,
                    metadata:     ['old_percentage' => $oldPct, 'new_percentage' => $newPct, 'actor' => $actorName],
                );
                Cache::forget("notif_unread_reseller_{$coRefReseller->id}");
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
                dedupeSuffix: $splitId . ':share_updated_admin:' . $hour,
                metadata:     ['old_percentage' => $oldPct, 'new_percentage' => $newPct, 'actor' => $actorName, 'co_referrer' => $split->reseller_name],
            );
        } catch (\Throwable) {}

        // Notify primary referrer — their effective commission changes when a co-referrer's share is updated.
        // Skip if the actor IS the primary referrer (they made the change; no need to notify themselves).
        try {
            $primaryReseller = Reseller::where('tenant_id', $tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower($lead->reseller_name ?? '')])
                ->first();
            if ($primaryReseller
                && strtolower($primaryReseller->name ?? '') !== strtolower($split->reseller_name ?? '')
                && strtolower($primaryReseller->name ?? '') !== strtolower($actorName ?? '')) {
                app(NotificationDispatchService::class)->dispatchToReseller(
                    resellerId:   (string) $primaryReseller->id,
                    tenantId:     $tenantId,
                    category:     'deal_pipeline',
                    priority:     'normal',
                    title:        'Co-referrer share updated on your deal',
                    body:         $actorName . ' updated ' . $split->reseller_name . '\'s commission share on "' . ($lead->name ?? 'a deal') . '" from ' . $oldPct . '% to ' . $newPct . '%.',
                    actionUrl:    url("/reseller/{$tenantId}/deals/{$dealId}"),
                    actionLabel:  'View Deal',
                    dedupeSuffix: $splitId . ':primary_share_updated:' . $hour,
                    metadata:     ['old_percentage' => $oldPct, 'new_percentage' => $newPct, 'co_referrer' => $split->reseller_name],
                );
                Cache::forget("notif_unread_reseller_{$primaryReseller->id}");
            }
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

        // Matches both the implicit primary (lead->reseller_name) and any explicit CommissionSplit(role='primary').
        $isPrimary = strtolower($reseller->name ?? '') === strtolower($lead->reseller_name ?? '')
            || CommissionSplit::where('lead_id', $lead->id)
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

        // Only owner/admin/manager or super admin may remove co-referrers
        if (!Auth::guard('web')->check()) {
            $role = \App\Services\TenantContext::role();
            if (!in_array($role, ['owner', 'admin', 'manager'])) {
                return response()->json(['error' => 'Forbidden.'], 403);
            }
        }

        if (!Auth::guard('web')->check()) {
            $ctxId = \App\Services\TenantContext::requireId();
            if ($ctxId !== $tenantId) {
                return response()->json(['error' => 'Forbidden.'], 403);
            }
        }

        $lead      = Lead::where('id', $dealId)->where('tenant_id', $tenantId)->firstOrFail();
        $actorName = $actor->full_name ?: $actor->email ?? 'Admin';

        return $this->doRemoveSplit($tenantId, $dealId, $splitId, $actorName, 'admin', $lead);
    }

    private function doRemoveSplit(string $tenantId, string $dealId, string $splitId, string $actorName, string $actorRole, Lead $lead): JsonResponse
    {
        // Anchor through tenant-scoped $lead->id to prevent cross-tenant CommissionSplit access
        $split = CommissionSplit::where('id', $splitId)
            ->where('lead_id', $lead->id)
            ->where('role', 'secondary')
            ->firstOrFail();

        if (in_array($lead->commission_status, ['locked', 'paid'], true)) {
            return response()->json(['error' => 'Commission splits cannot be modified after commission is locked or paid.'], 422);
        }

        $removedName = $split->reseller_name;
        $split->delete();

        // Resolve removed co-referrer — isolated so a DB failure doesn't suppress notification or email
        $coRefReseller = null;
        $coRefHour     = now()->format('YmdH');
        try {
            $coRefReseller = Reseller::where('tenant_id', $tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower($removedName)])
                ->first();
        } catch (\Throwable) {}

        // In-app notification to the removed co-referrer
        try {
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
                    dedupeSuffix: $splitId . ':coreferrer_removed:' . $coRefHour,
                );
                Cache::forget("notif_unread_reseller_{$coRefReseller->id}");
            }
        } catch (\Throwable) {}

        // Email to the removed co-referrer
        try {
            if ($coRefReseller && in_array($coRefReseller->status, ['active', 'nda_signed'])) {
                EmailLogger::send(
                    mailable:      new \App\Mail\CoReferrerRemovedMail(
                        resellerName:  $coRefReseller->name,
                        resellerEmail: $coRefReseller->email,
                        dealName:      $lead->name,
                        actorName:     $actorName,
                    ),
                    recipientEmail: $coRefReseller->email,
                    recipientType:  'reseller',
                    emailKey:       'coreferrer_removed.' . $dealId . '.' . $coRefReseller->id . '.' . $coRefHour,
                    subject:        "You've been removed as a co-referrer on \"{$lead->name}\"",
                    recipientId:    (string) $coRefReseller->id,
                    tenantId:       $tenantId,
                );
            }
        } catch (\Throwable) {}

        // Notify admins in all cases
        try {
            app(NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'deal_pipeline',
                priority:     'normal',
                title:        'Co-referrer removed',
                body:         $actorName . ' removed ' . $removedName . ' as a co-referrer on "' . $lead->name . '".',
                actionUrl:    url("/tenant/{$tenantId}/deals/{$dealId}"),
                actionLabel:  'View Deal',
                dedupeSuffix: $splitId . ':coreferrer_removed_admin:' . $coRefHour,
            );
        } catch (\Throwable) {}

        // Notify primary referrer so they know their commission pool changed.
        // Skip if the actor IS the primary referrer (they made the change; no need to notify themselves).
        try {
            $primaryReseller = Reseller::where('tenant_id', $tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower($lead->reseller_name ?? '')])
                ->first();
            if ($primaryReseller
                && strtolower($primaryReseller->name ?? '') !== strtolower($removedName)
                && strtolower($primaryReseller->name ?? '') !== strtolower($actorName ?? '')) {
                app(NotificationDispatchService::class)->dispatchToReseller(
                    resellerId:   (string) $primaryReseller->id,
                    tenantId:     $tenantId,
                    category:     'deal_pipeline',
                    priority:     'normal',
                    title:        'Co-referrer removed from your deal',
                    body:         $actorName . ' removed ' . $removedName . ' as a co-referrer on "' . $lead->name . '".',
                    actionUrl:    url("/reseller/{$tenantId}/deals/{$dealId}"),
                    actionLabel:  'View Deal',
                    dedupeSuffix: $splitId . ':primary_coreferrer_removed:' . $coRefHour,
                );
                Cache::forget("notif_unread_reseller_{$primaryReseller->id}");
            }
        } catch (\Throwable) {}

        // Activity log
        try {
            app(DealActivityService::class)->record($lead, 'Co-referrer removed by ' . $actorRole . ': ' . $removedName, 'assignment', [
                'category'   => 'assignment',
                'actor_name' => $actorName,
                'actor_role' => $actorRole,
                'old_values' => ['removed_co_referrer' => $removedName],
            ]);
        } catch (\Throwable) {}

        try {
            app(\App\Services\CriticalActionService::class)->invalidateAllAdminBadges($tenantId);
        } catch (\Throwable) {}

        return response()->json(['success' => true, 'removed_name' => $removedName]);
    }
}
