<?php

namespace App\Http\Controllers;

use App\Models\CommissionSplit;
use App\Models\DealPartnerSplit;
use App\Models\Lead;
use App\Models\Reseller;
use App\Models\Tenant;
use App\Services\CommissionCalculationService;
use App\Services\DealActivityService;
use App\Services\DealPartnerSplitService;
use App\Services\NotificationDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ReferrerPartnerController extends Controller
{
    // ── Auth helpers ──────────────────────────────────────────────────────────

    private function reseller(): Reseller
    {
        $r = Auth::guard('reseller')->user();
        if ($r instanceof Reseller) return $r;
        abort(403, 'Reseller authentication required.');
    }

    /** Returns all Lead IDs assigned to this reseller in this tenant (primary or co-referrer). */
    private function getMyLeadIds(string $tenantId, Reseller $reseller): array
    {
        // Single query via scopeForResellerOrSplit: covers both leads.reseller_name
        // and commission_splits in one orWhereExists, case-insensitive.
        return Lead::where('tenant_id', $tenantId)
            ->forResellerOrSplit($reseller->name)
            ->pluck('id')
            ->all();
    }

    /** Verify reseller can access a specific deal. */
    private function resellerCanAccessDeal(Reseller $reseller, Lead $lead): bool
    {
        if (strtolower($reseller->name) === strtolower($lead->reseller_name ?? '')) return true;
        return CommissionSplit::where('lead_id', $lead->id)
            ->whereRaw('LOWER(reseller_name) = ?', [strtolower($reseller->name)])
            ->exists();
    }

    // ── My Partners List ──────────────────────────────────────────────────────

    public function index(string $tenantId)
    {
        $reseller = $this->reseller();
        $tenant   = Tenant::findOrFail($tenantId);
        $leadIds  = $this->getMyLeadIds($tenantId, $reseller);
        $calc     = app(CommissionCalculationService::class);

        // All active partner splits across my deals
        $splits = [];
        try {
            $splits = DealPartnerSplit::where('tenant_id', $tenantId)
                ->whereIn('deal_id', $leadIds ?: ['__none__'])
                ->whereNull('deleted_at')
                ->where('status', '!=', 'removed')
                ->with('lead:id,name,stage,deal_value,base_cost,added_amount,commission_status')
                ->orderByDesc('created_at')
                ->get();
        } catch (\Throwable) {
            $splits = collect();
        }

        // Group by partner_email for unique partners list
        $partners = collect($splits)->groupBy('partner_email')->map(function ($partnerSplits, $email) use ($calc) {
            $first = $partnerSplits->first();

            $totalCommission = $partnerSplits->sum(function ($split) use ($calc) {
                if (!$split->lead) return 0;
                try {
                    $bd = $calc->breakdownFromLead($split->lead);
                    return $calc->partnerShare(
                        (float) ($bd['commission_pool'] ?? 0),
                        (float) $split->split_share_value,
                        $split->split_share_type
                    );
                } catch (\Throwable) {
                    return 0;
                }
            });

            // Best status: active > pending_invite > provisional
            $bestStatus = 'provisional';
            foreach ($partnerSplits as $s) {
                if ($s->status === 'active') { $bestStatus = 'active'; break; }
                if ($s->status === 'pending_invite') $bestStatus = 'pending_invite';
            }

            $latestDeal = $partnerSplits->first()?->lead?->name;
            $addedAt    = $partnerSplits->min('created_at');

            return [
                'email'            => $email,
                'name'             => $first->partner_name,
                'status'           => $bestStatus,
                'deal_count'       => $partnerSplits->count(),
                'total_commission' => round($totalCommission, 2),
                'latest_deal'      => $latestDeal,
                'added_at'         => $addedAt,
                'partner_user_id'  => $first->partner_user_id,
                'splits'           => $partnerSplits,
            ];
        })->values();

        // KPI cards
        $totalPartners   = $partners->count();
        $activePartners  = $partners->where('status', 'active')->count();
        $pendingInvites  = $partners->where('status', 'pending_invite')->count();
        $totalCommission = round($partners->sum('total_commission'), 2);

        // My deals for deal selector in Add Partner modal
        $myDeals = [];
        try {
            $myDeals = Lead::where('tenant_id', $tenantId)
                ->whereIn('id', $leadIds ?: ['__none__'])
                ->whereNotIn('status', ['archived'])
                ->select('id', 'name', 'stage', 'commission_status')
                ->orderBy('name')
                ->get();
        } catch (\Throwable) {
            $myDeals = collect();
        }

        return view('reseller.partners.index', compact(
            'reseller', 'tenant', 'tenantId',
            'partners', 'totalPartners', 'activePartners', 'pendingInvites', 'totalCommission',
            'myDeals'
        ));
    }

    // ── Partner Detail ────────────────────────────────────────────────────────

    public function show(string $tenantId, string $partnerSlug)
    {
        $reseller  = $this->reseller();
        $tenant    = Tenant::findOrFail($tenantId);
        $leadIds   = $this->getMyLeadIds($tenantId, $reseller);
        $calc      = app(CommissionCalculationService::class);

        // Decode slug → email
        $email = strtolower(trim(base64_decode($partnerSlug) ?: ''));
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) abort(404);

        // Get splits for this partner across MY deals only
        $splits = collect();
        try {
            $splits = DealPartnerSplit::where('tenant_id', $tenantId)
                ->where('partner_email', $email)
                ->whereIn('deal_id', $leadIds ?: ['__none__'])
                ->whereNull('deleted_at')
                ->where('status', '!=', 'removed')
                ->with('lead:id,name,stage,deal_value,base_cost,added_amount,commission_status,data')
                ->orderByDesc('created_at')
                ->get();
        } catch (\Throwable) {}

        if ($splits->isEmpty()) abort(404, 'Partner not found on your deals.');

        $first = $splits->first();

        // Per-deal data with commission
        $sharedDeals = $splits->map(function ($split) use ($calc) {
            $bd            = [];
            $partnerComm   = 0;
            $commissionPool = 0;
            try {
                if ($split->lead) {
                    $bd             = $calc->breakdownFromLead($split->lead);
                    $commissionPool = (float) ($bd['commission_pool'] ?? 0);
                    $partnerComm    = $calc->partnerShare(
                        $commissionPool,
                        (float) $split->split_share_value,
                        $split->split_share_type
                    );
                }
            } catch (\Throwable) {}

            return [
                'split'              => $split,
                'lead'               => $split->lead,
                'partner_commission' => $partnerComm,
                'commission_pool'    => $commissionPool,
            ];
        });

        // Commission summary
        $totalCommission   = round($sharedDeals->sum('partner_commission'), 2);
        $pendingCommission = round($sharedDeals->filter(fn($d) => ($d['lead']?->commission_status ?? '') === 'pending')->sum('partner_commission'), 2);
        $lockedCommission  = round($sharedDeals->filter(fn($d) => ($d['lead']?->commission_status ?? '') === 'locked')->sum('partner_commission'), 2);
        $paidCommission    = round($sharedDeals->filter(fn($d) => ($d['lead']?->commission_status ?? '') === 'paid')->sum('partner_commission'), 2);

        // Best status
        $status = 'provisional';
        foreach ($splits as $s) {
            if ($s->status === 'active') { $status = 'active'; break; }
            if ($s->status === 'pending_invite') $status = 'pending_invite';
        }

        // Activity from lead_history for shared deals
        $activity = [];
        try {
            $dealIds  = $splits->pluck('deal_id')->filter()->unique()->toArray();
            $activity = DB::table('lead_history')
                ->whereIn('lead_id', $dealIds ?: ['__none__'])
                ->where(function ($q) use ($email, $first) {
                    $q->where('type', 'partner')
                      ->orWhere('action', 'like', '%partner%')
                      ->orWhereRaw("metadata::text ILIKE ?", ['%' . $first->partner_name . '%']);
                })
                ->orderByDesc('created_at')
                ->limit(30)
                ->get()
                ->toArray();
        } catch (\Throwable) {}

        // My other deals (for "Add to another deal" selector)
        $myDeals = [];
        try {
            $alreadyOnDeals = $splits->pluck('deal_id')->toArray();
            $myDeals = Lead::where('tenant_id', $tenantId)
                ->whereIn('id', $leadIds ?: ['__none__'])
                ->whereNotIn('status', ['archived'])
                ->select('id', 'name', 'stage', 'commission_status')
                ->orderBy('name')
                ->get();
        } catch (\Throwable) {
            $myDeals = collect();
        }

        return view('reseller.partners.show', compact(
            'reseller', 'tenant', 'tenantId',
            'first', 'email', 'partnerSlug', 'status', 'splits',
            'sharedDeals', 'totalCommission', 'pendingCommission', 'lockedCommission', 'paidCommission',
            'activity', 'myDeals'
        ));
    }

    // ── Add Partner to Deal ───────────────────────────────────────────────────

    public function store(Request $request, string $tenantId): JsonResponse
    {
        $reseller = $this->reseller();
        $tenant   = \App\Models\Tenant::findOrFail($tenantId);

        $data = $request->validate([
            'deal_id'           => 'required|string',
            'partner_name'      => 'required|string|max:150',
            'partner_email'     => 'nullable|email|max:200', // optional — triggers invite when provided
            'split_share_value' => 'required|numeric|min:0.01',
            'split_share_type'  => 'required|in:percentage,fixed_amount',
        ]);

        // Verify deal belongs to tenant and is assigned to referrer
        $lead = Lead::where('id', $data['deal_id'])
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$lead) {
            return response()->json(['error' => 'Deal not found.'], 404);
        }

        if (!$this->resellerCanAccessDeal($reseller, $lead)) {
            return response()->json(['error' => 'You can only add Partners to deals assigned to you.'], 403);
        }

        if ($lead->commission_status === 'paid') {
            return response()->json(['error' => 'Commission on this deal is already paid. Contact your admin to add a Partner.'], 422);
        }

        // ── Partner invite (email provided) ──────────────────────────────────
        $inviteSent        = false;
        $alreadyHasAccount = false;
        $partnerEmail      = !empty($data['partner_email']) ? strtolower(trim($data['partner_email'])) : null;

        if ($partnerEmail) {
            $existingPartner = \App\Models\Partner::where('tenant_id', $tenantId)
                ->whereRaw('LOWER(email) = ?', [$partnerEmail])
                ->first();

            if ($existingPartner && $existingPartner->isSetupComplete()) {
                // Partner already has a full account — split links them, no re-invite needed
                $alreadyHasAccount = true;
            } elseif (!$existingPartner) {
                // New partner: create invited record and send setup email
                $setupToken     = \Illuminate\Support\Str::random(64);
                $nameParts      = preg_split('/\s+/', trim($data['partner_name']), 2);
                $newPartner     = \App\Models\Partner::create([
                    'id'              => (string) \Illuminate\Support\Str::uuid(),
                    'tenant_id'       => $tenantId,
                    'email'           => $partnerEmail,
                    'first_name'      => $nameParts[0] ?? $data['partner_name'],
                    'last_name'       => $nameParts[1] ?? null,
                    'status'          => 'invited',
                    'setup_token'     => $setupToken,
                    'invited_by_type' => 'reseller',
                    'invited_by_id'   => (string) $reseller->id,
                ]);

                try {
                    \Illuminate\Support\Facades\Mail::queue(new \App\Mail\PartnerInviteMail(
                        recipientEmail:   $partnerEmail,
                        partnerFirstName: $newPartner->first_name ?? 'there',
                        tenantName:       $tenant->name,
                        inviterName:      $reseller->name ?? 'A referrer',
                        setupUrl:         url('/partner/invite/' . $newPartner->setup_token),
                    ));
                    $inviteSent = true;
                } catch (\Throwable $mailEx) {
                    Log::warning('PartnerInviteMail queue failed', [
                        'tenant_id' => $tenantId,
                        'email'     => $partnerEmail,
                        'error'     => $mailEx->getMessage(),
                    ]);
                }
            }
            // If partner exists but hasn't set up yet (status=invited), no re-send here;
            // admin can re-invite. Split still links them to the deal.
        }

        // ── Create the deal split ─────────────────────────────────────────────
        try {
            app(DealPartnerSplitService::class)->upsert(
                tenantId:    $tenantId,
                dealId:      $data['deal_id'],
                partnerName: $data['partner_name'],
                partnerEmail: $partnerEmail ?? '',
                splitValue:  (float) $data['split_share_value'],
                splitType:   $data['split_share_type'],
                currency:    'PHP',
                source:      'manual',
                actorId:     (string) $reseller->id,
            );

            app(DealActivityService::class)->record($lead, 'Partner added by Referrer', 'partner', [
                'category'   => 'partner',
                'reseller'   => $reseller->name,
                'actor_name' => $reseller->name,
                'actor_role' => 'referrer',
                'new_values' => [
                    'partner_name'  => $data['partner_name'],
                    'partner_email' => $partnerEmail,
                    'invite_sent'   => $inviteSent,
                    'split_value'   => $data['split_share_value'],
                    'split_type'    => $data['split_share_type'],
                ],
            ]);

            app(NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'deal_pipeline',
                priority:     'normal',
                title:        'Partner added by Referrer',
                body:         $reseller->name . ' added ' . $data['partner_name']
                              . ($inviteSent ? ' and sent a partner invite' : '')
                              . ' to "' . $lead->name . '".',
                actionUrl:    url("/tenant/{$tenantId}/deals/{$lead->id}"),
                actionLabel:  'Review Deal',
                dedupeSuffix: $lead->id . ':partner:' . md5($partnerEmail ?? $data['partner_name']),
            );
        } catch (\Throwable $e) {
            Log::error('ReferrerPartnerController::store failed', [
                'tenant_id' => $tenantId,
                'deal_id'   => $data['deal_id'],
                'error'     => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Could not add Partner. Please try again.'], 500);
        }

        $message = match(true) {
            $inviteSent        => 'Partner added! An invite email has been sent to ' . $partnerEmail . '.',
            $alreadyHasAccount => 'Partner added. They already have an active account.',
            $partnerEmail      => 'Partner added. The invite email could not be sent — your admin can resend it.',
            default            => 'Partner added. Admins have been notified.',
        };

        return response()->json([
            'success'  => true,
            'message'  => $message,
            'redirect' => route('reseller.partners', $tenantId),
        ]);
    }

    // ── Remove Partner from Deal ──────────────────────────────────────────────

    public function removeFromDeal(Request $request, string $tenantId, string $splitId): JsonResponse
    {
        $reseller = $this->reseller();

        $split = DealPartnerSplit::where('id', $splitId)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'removed')
            ->first();

        if (!$split) {
            return response()->json(['error' => 'Partner split not found.'], 404);
        }

        $lead = Lead::where('id', $split->deal_id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$lead || !$this->resellerCanAccessDeal($reseller, $lead)) {
            return response()->json(['error' => 'You do not have access to this deal.'], 403);
        }

        if (in_array($lead->commission_status, ['locked', 'paid'])) {
            return response()->json(['error' => 'Commission on this deal is locked or paid. Contact your admin to remove a Partner.'], 422);
        }

        $reason = $request->input('reason', '');

        try {
            app(DealPartnerSplitService::class)->remove($tenantId, $splitId, (string) $reseller->id);

            app(DealActivityService::class)->record($lead, 'Partner removed by Referrer', 'partner', [
                'category'   => 'partner',
                'reseller'   => $reseller->name,
                'actor_name' => $reseller->name,
                'actor_role' => 'referrer',
                'old_values' => [
                    'partner_name'  => $split->partner_name,
                    'split_value'   => $split->split_share_value,
                    'split_type'    => $split->split_share_type,
                ],
                'metadata'   => $reason ? ['reason' => $reason] : [],
            ]);

            // Dedup key is scoped to deal + actor + 5-minute window so removing multiple
            // partners from the same deal in quick succession produces ONE notification.
            // Body is generic ("a partner") because only the first removal fires within the window;
            // admins can see the full partner list via the "Review Deal" CTA link.
            $dedupWindow = (int) floor(time() / 300); // bucket changes every 5 minutes
            app(NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'deal_pipeline',
                priority:     'normal',
                title:        'Partner removed from deal',
                body:         $reseller->name . ' removed a partner from "' . $lead->name . '". Review the deal to see the current partner list.',
                actionUrl:    url("/tenant/{$tenantId}/deals/{$lead->id}"),
                actionLabel:  'Review Deal',
                dedupeSuffix: $lead->id . ':partner_removed:' . $reseller->id . ':' . $dedupWindow,
            );
        } catch (\Throwable $e) {
            Log::error('ReferrerPartnerController::removeFromDeal failed', [
                'split_id' => $splitId,
                'error'    => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Could not remove Partner. Please try again.'], 500);
        }

        return response()->json(['success' => true, 'message' => 'Partner removed from deal.']);
    }
}
