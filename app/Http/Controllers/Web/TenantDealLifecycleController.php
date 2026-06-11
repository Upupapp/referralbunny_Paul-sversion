<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DealApprovalRequest;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\TenantConfig;
use App\Models\TenantMembership;
use App\Services\DealActivityService;
use App\Services\DealExtensionDisplayService;
use App\Services\NotificationDispatchService;
use App\Services\ReferrerCacheService;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantDealLifecycleController extends Controller
{
    // ── Permission helpers ─────────────────────────────────────────────────────

    private function resolveRole(string $tenantId): string
    {
        if (Auth::guard('web')->check()) return 'super_admin';

        $userId = Auth::guard('tenant')->id();
        if (!$userId) return 'viewer';

        $role = TenantContext::role() ?? request()->attributes->get('_tenant_role');
        if ($role) return $role;

        return TenantMembership::where('tenant_user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->value('role') ?? 'viewer';
    }

    private function isAdminMgr(string $role): bool
    {
        return in_array($role, ['owner', 'admin', 'manager', 'super_admin']);
    }

    private function config(string $tenantId): ?TenantConfig
    {
        $cached = Cache::remember("tenant_config:{$tenantId}", 300, function () use ($tenantId) {
            return TenantConfig::where('tenant_id', $tenantId)->first()?->getAttributes();
        });
        return $cached ? (new TenantConfig())->setRawAttributes($cached) : null;
    }

    private function configMeta(string $tenantId): array
    {
        $cfg    = $this->config($tenantId);
        $fields = collect($cfg?->fields ?? []);
        return [
            'config'    => $cfg,
            'leadLabel' => $cfg?->lead_label ?? 'Deal',
        ];
    }

    // ── GET /tenant/{tenantId}/deals/expired ────────────────────────────────

    public function expired(Request $request, string $tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        $role   = $this->resolveRole($tenantId);
        if (!$this->isAdminMgr($role)) abort(403);

        $search   = $request->get('q', '');
        $stage    = $request->get('stage', '');
        $reseller = $request->get('referrer', '');
        $per      = 20;

        $query = Lead::where('leads.tenant_id', $tenantId)
            ->where('leads.status', 'expired')
            ->whereNull('leads.deleted_at')
            ->select('leads.*');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(leads.name) LIKE ?', ['%' . strtolower($search) . '%'])
                  ->orWhereRaw('LOWER(leads.reseller_name) LIKE ?', ['%' . strtolower($search) . '%']);
            });
        }
        if ($stage) $query->where('leads.stage', $stage);
        if ($reseller) $query->whereRaw('LOWER(leads.reseller_name) = ?', [strtolower($reseller)]);

        $deals = $query->orderBy('leads.updated_at', 'desc')->paginate($per)->withQueryString();

        $extensionViewerRole = DealExtensionDisplayService::normalizeViewerRole($role);
        $extensionSummaries  = app(DealExtensionDisplayService::class)
            ->getSummariesForDeals($deals->pluck('id')->all(), $tenantId, $extensionViewerRole);

        $metrics = Cache::remember("lifecycle_expired_metrics:{$tenantId}", 120, function () use ($tenantId) {
            return [
                'total'     => Lead::where('tenant_id', $tenantId)->where('status', 'expired')->whereNull('deleted_at')->count(),
                'this_week' => Lead::where('tenant_id', $tenantId)->where('status', 'expired')->whereNull('deleted_at')
                    ->where('updated_at', '>=', now()->startOfWeek())->count(),
                'total_value' => (float) Lead::where('tenant_id', $tenantId)->where('status', 'expired')->whereNull('deleted_at')
                    ->sum('deal_value'),
            ];
        });

        return view('tenant.deals.expired', array_merge(
            compact('tenant', 'deals', 'metrics', 'role', 'search', 'stage', 'reseller', 'extensionSummaries', 'extensionViewerRole'),
            $this->configMeta($tenantId)
        ));
    }

    // ── POST /tenant/{tenantId}/deals/expired/bulk-extend ───────────────────

    public function bulkExtendExpired(Request $request, string $tenantId): RedirectResponse
    {
        $role = $this->resolveRole($tenantId);
        if (!$this->isAdminMgr($role)) abort(403);

        $data = $request->validate([
            'deal_ids'        => 'required|array|min:1|max:50',
            'deal_ids.*'      => 'required|string|uuid',
            'extension_days'  => 'required|integer|min:1|max:90',
        ]);

        $days = (int) $data['extension_days'];

        $leads = Lead::where('tenant_id', $tenantId)
            ->where('status', 'expired')
            ->whereNull('deleted_at')
            ->whereIn('id', $data['deal_ids'])
            ->get();

        if ($leads->isEmpty()) {
            return back()->with('error', 'No matching expired deals found to extend.');
        }

        $extendedCount = 0;

        // Bust referrer-scoped caches for all candidate leads up front, in two
        // batched queries instead of one round-trip per lead.
        $primariesByLeadId = app(ReferrerCacheService::class)->bustForLeads($tenantId, $leads);

        foreach ($leads as $lead) {
            $newDaysLeft = DB::transaction(function () use ($lead, $days) {
                $lockedLead = DB::table('leads')->where('id', $lead->id)->lockForUpdate()->first();
                if (($lockedLead->status ?? null) !== 'expired') {
                    return null;
                }
                $newDaysLeft = max(0, ($lockedLead->days_left ?? 0)) + $days;
                DB::table('leads')->where('id', $lead->id)->update([
                    'days_left'  => $newDaysLeft,
                    'status'     => 'active',
                    'updated_at' => now(),
                ]);
                return $newDaysLeft;
            });

            if ($newDaysLeft === null) {
                continue;
            }

            $extendedCount++;

            try {
                app(DealActivityService::class)->record(
                    $lead,
                    "Deal assignment extended by {$days} day(s) by admin and reactivated.",
                    'deal_extended',
                    ['metadata' => ['extension_days' => $days, 'new_days_left' => $newDaysLeft]]
                );
            } catch (\Throwable) {}

            // Notify the primary referrer that their deal is active again — mirrors
            // LeadController's pattern for lead status/assignment changes (see
            // reassignReferrer ~line 1690). Cache-busting was already done in bulk above.
            try {
                $primaryReseller = $primariesByLeadId[$lead->id] ?? null;

                if ($primaryReseller) {
                    app(NotificationDispatchService::class)->dispatchToReseller(
                        resellerId:   (string) $primaryReseller->id,
                        tenantId:     $tenantId,
                        category:     'deal_pipeline',
                        priority:     'normal',
                        title:        'Deal extended: "' . $lead->name . '"',
                        body:         'Your deal "' . $lead->name . '" has been extended by ' . $days . ' day(s) and is active again.',
                        actionUrl:    url("/reseller/{$tenantId}/deals/{$lead->id}"),
                        actionLabel:  'View Deal',
                        dedupeSuffix: $lead->id . ':admin_extended:' . now()->format('Ymd'),
                    );
                    Cache::forget("notif_unread_reseller_{$primaryReseller->id}");
                }
            } catch (\Throwable) {}
        }

        Cache::deleteMultiple(["dash_counts:{$tenantId}", "lifecycle_expired_metrics:{$tenantId}", "subtab_badge_counts:{$tenantId}"]);
        try {
            app(\App\Services\CriticalActionService::class)->invalidateAllAdminBadges($tenantId);
        } catch (\Throwable) {}

        return redirect()
            ->route('tenant.deals.expired', $tenantId)
            ->with('success', "{$extendedCount} deal(s) extended by {$days} day(s) and moved back to active.");
    }

    // ── GET /tenant/{tenantId}/deals/archive-requests ───────────────────────

    public function archiveRequests(Request $request, string $tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        $role   = $this->resolveRole($tenantId);
        if (!$this->isAdminMgr($role)) abort(403);

        $search = $request->get('q', '');
        $status = $request->get('status', 'pending');
        $per    = 20;

        $query = DealApprovalRequest::where('deal_approval_requests.tenant_id', $tenantId)
            ->where('deal_approval_requests.type', 'deal_archive')
            ->with('lead:id,name,stage,deal_value,reseller_name,status');

        if ($status && $status !== 'all') {
            $query->where('deal_approval_requests.status', $status);
        }

        if ($search) {
            $query->whereHas('lead', function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']);
            });
        }

        $requests = $query->orderBy('deal_approval_requests.created_at', 'desc')->paginate($per)->withQueryString();

        $metrics = Cache::remember("lifecycle_archive_req_metrics:{$tenantId}", 60, function () use ($tenantId) {
            return [
                'pending'       => DealApprovalRequest::where('tenant_id', $tenantId)->where('type', 'deal_archive')->where(fn($q) => $q->where('status', 'pending')->orWhere(fn($q2) => $q2->where('status', 'clarification_requested')->whereNotNull('visible_response')))->count(),
                'approved'      => DealApprovalRequest::where('tenant_id', $tenantId)->where('type', 'deal_archive')->where('status', 'approved')->count(),
                'rejected'      => DealApprovalRequest::where('tenant_id', $tenantId)->where('type', 'deal_archive')->where('status', 'rejected')->count(),
                'clarification' => DealApprovalRequest::where('tenant_id', $tenantId)->where('type', 'deal_archive')->where('status', 'clarification_requested')->count(),
            ];
        });

        $subtabCounts = Cache::remember("subtab_badge_counts:{$tenantId}", 60, function () use ($tenantId) {
            return [
                'expired'          => Lead::where('tenant_id', $tenantId)->where('status', 'expired')->whereNull('deleted_at')->count(),
                'deleted_archived' => Lead::withTrashed()->where('tenant_id', $tenantId)
                    ->where(fn($q) => $q->where('status', 'archived')->orWhereNotNull('deleted_at'))
                    ->count(),
            ];
        });
        $subtabCounts['pending_archive'] = $metrics['pending'];

        return view('tenant.deals.archive-requests', array_merge(
            compact('tenant', 'requests', 'metrics', 'subtabCounts', 'role', 'search', 'status'),
            $this->configMeta($tenantId)
        ));
    }

    // ── GET /tenant/{tenantId}/deals/archive-requests/{requestId} ───────────

    public function archiveRequestShow(Request $request, string $tenantId, string $requestId)
    {
        $tenant  = Tenant::findOrFail($tenantId);
        $role    = $this->resolveRole($tenantId);
        if (!$this->isAdminMgr($role)) abort(403);

        $archiveRequest = DealApprovalRequest::where('id', $requestId)
            ->where('tenant_id', $tenantId)
            ->where('type', 'deal_archive')
            ->with('lead')
            ->firstOrFail();

        return view('tenant.deals.archive-request-show', array_merge(
            compact('tenant', 'archiveRequest', 'role'),
            $this->configMeta($tenantId)
        ));
    }

    // ── POST /tenant/{tenantId}/deals/archive-requests/{requestId}/clarify ──

    public function clarifyArchiveRequest(Request $request, string $tenantId, string $requestId): RedirectResponse
    {
        $role = $this->resolveRole($tenantId);
        if (!$this->isAdminMgr($role)) abort(403);

        $data = $request->validate([
            'clarification_message' => 'required|string|max:1000',
            'clarification_due_at'  => 'nullable|date|after:today',
        ]);

        $archiveRequest = DealApprovalRequest::where('id', $requestId)
            ->where('tenant_id', $tenantId)
            ->where('type', 'deal_archive')
            ->whereIn('status', ['pending', 'clarification_requested'])
            ->with('lead')
            ->firstOrFail();

        if ($archiveRequest->status === 'clarification_requested') {
            return redirect()
                ->route('tenant.deals.archive-requests.show', ['tenantId' => $tenantId, 'requestId' => $requestId])
                ->with('error', 'A clarification has already been requested. Wait for the referrer to respond before sending another.');
        }

        $archiveRequest->update([
            'status'                => 'clarification_requested',
            'clarification_message' => $data['clarification_message'],
            'clarification_due_at'  => $data['clarification_due_at'] ?? null,
        ]);

        // Notify the referrer who submitted the archive request
        try {
            $requestedById = $archiveRequest->requested_by_id;
            if ($requestedById) {
                app(NotificationDispatchService::class)->dispatchToReseller(
                    resellerId:   (string) $requestedById,
                    tenantId:     $tenantId,
                    category:     'deal_pipeline',
                    priority:     'normal',
                    title:        'Clarification needed on your archive request',
                    body:         'Your archive request for "' . ($archiveRequest->lead?->name ?? 'a deal') . '" needs clarification before it can be processed.',
                    actionUrl:    url("/reseller/{$tenantId}/deals/{$archiveRequest->deal_id}"),
                    actionLabel:  'Respond to Clarification',
                    dedupeSuffix: $requestId . ':clarify:' . now()->format('Ymd'),
                );
                Cache::forget("notif_unread_reseller_{$requestedById}");
            }
        } catch (\Throwable) {}

        // Send email notification
        try {
            $reseller       = \App\Models\Reseller::where('tenant_id', $tenantId)->find($archiveRequest->requested_by_id);
            $clarifyTenant  = \App\Models\Tenant::find($tenantId);
            if ($reseller?->email && $clarifyTenant) {
                $clarifyLead = $archiveRequest->lead ?? \App\Models\Lead::where('id', $archiveRequest->deal_id)->where('tenant_id', $tenantId)->first();
                \App\Services\EmailLogger::send(
                    mailable: new \App\Mail\ArchiveRequestClarificationMail(
                        recipientEmail:       $reseller->email,
                        dealName:             $clarifyLead?->name ?? ($archiveRequest->request_payload['deal_name'] ?? 'your deal'),
                        dealUrl:              $clarifyLead ? url("/reseller/{$tenantId}/deals/{$clarifyLead->id}") : null,
                        clarificationMessage: $archiveRequest->clarification_message,
                        clarificationDueAt:   $archiveRequest->clarification_due_at?->format('F d, Y'),
                        resellerName:         $reseller->name,
                        tenantName:           $clarifyTenant->name,
                    ),
                    recipientEmail: $reseller->email,
                    recipientType:  'reseller',
                    recipientId:    (string) $reseller->id,
                    emailKey:       'archive_clarification.' . $requestId . '.' . $reseller->id,
                    subject:        'Clarification needed on your archive request',
                    tenantId:       $tenantId,
                );
            }
        } catch (\Throwable) {}

        // Record deal activity
        try {
            if ($archiveRequest->lead) {
                app(DealActivityService::class)->record(
                    $archiveRequest->lead,
                    'Clarification requested for archive request: ' . $data['clarification_message'],
                    'archive_clarification'
                );
            }
        } catch (\Throwable) {}

        $actorUserId = Auth::guard('web')->id() ?? Auth::guard('tenant')->id();
        Cache::deleteMultiple(["dash_counts:{$tenantId}", "lifecycle_archive_req_metrics:{$tenantId}", "subtab_badge_counts:{$tenantId}"]);
        try {
            app(\App\Services\CriticalActionService::class)->invalidateAllAdminBadges($tenantId);
        } catch (\Throwable) {}

        return redirect()
            ->route('tenant.deals.archive-requests', $tenantId)
            ->with('success', 'Clarification request sent.');
    }

    // ── GET /tenant/{tenantId}/deals/deleted-archived ───────────────────────

    public function deletedArchived(Request $request, string $tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        $role   = $this->resolveRole($tenantId);
        if (!$this->isAdminMgr($role)) abort(403);

        $search = $request->get('q', '');
        $filter = $request->get('filter', 'all'); // all | archived | deleted
        $stage  = $request->get('stage', '');
        $per    = 20;

        if ($filter === 'archived') {
            $query = Lead::where('tenant_id', $tenantId)->where('status', 'archived')->whereNull('deleted_at');
        } elseif ($filter === 'deleted') {
            $query = Lead::onlyTrashed()->where('tenant_id', $tenantId);
        } else {
            $query = Lead::withTrashed()
                ->where('tenant_id', $tenantId)
                ->where(function ($q) {
                    $q->where('status', 'archived')
                      ->orWhereNotNull('deleted_at');
                });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%'])
                  ->orWhereRaw('LOWER(reseller_name) LIKE ?', ['%' . strtolower($search) . '%']);
            });
        }
        if ($stage) $query->where('stage', $stage);

        $deals = $query->orderByRaw('COALESCE(deleted_at, archived_at, updated_at) DESC')->paginate($per)->withQueryString();

        $metrics = Cache::remember("lifecycle_del_arch_metrics:{$tenantId}", 120, function () use ($tenantId) {
            return [
                'archived' => Lead::where('tenant_id', $tenantId)->where('status', 'archived')->whereNull('deleted_at')->count(),
                'deleted'  => Lead::onlyTrashed()->where('tenant_id', $tenantId)->count(),
                'archived_value' => (float) Lead::where('tenant_id', $tenantId)->where('status', 'archived')->whereNull('deleted_at')->sum('deal_value'),
            ];
        });

        return view('tenant.deals.deleted-archived', array_merge(
            compact('tenant', 'deals', 'metrics', 'role', 'search', 'filter', 'stage'),
            $this->configMeta($tenantId)
        ));
    }

    // ── POST /tenant/{tenantId}/deals/{dealId}/restore ───────────────────────

    public function restoreDeal(Request $request, string $tenantId, string $dealId): RedirectResponse
    {
        $role = $this->resolveRole($tenantId);
        if (!$this->isAdminMgr($role)) abort(403);

        $lead = Lead::withTrashed()
            ->where('id', $dealId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        DB::transaction(function () use ($lead, $tenantId) {
            if ($lead->trashed()) {
                $lead->restore();
                $lead->refresh();
            }
            // If archived, revert to active
            if ($lead->status === 'archived') {
                $lead->update([
                    'status'         => 'active',
                    'archived_at'    => null,
                    'archive_reason' => null,
                ]);
            } else {
                // Was only soft-deleted, ensure status is active
                $lead->update(['status' => 'active']);
            }
        });

        try {
            app(DealActivityService::class)->record($lead, 'Deal restored by admin.', 'deal_restored');
        } catch (\Throwable) {}

        // Notify the referrer that their deal has been restored
        try {
            if ($lead->reseller_name) {
                $reseller = \App\Models\Reseller::where('tenant_id', $tenantId)
                    ->whereRaw('LOWER(name) = ?', [strtolower($lead->reseller_name)])
                    ->first();
                if ($reseller) {
                    app(NotificationDispatchService::class)->dispatchToReseller(
                        resellerId:   (string) $reseller->id,
                        tenantId:     $tenantId,
                        category:     'deal_pipeline',
                        priority:     'normal',
                        title:        'Deal restored: "' . $lead->name . '"',
                        body:         'Your deal "' . $lead->name . '" has been restored to active status.',
                        actionUrl:    url("/reseller/{$tenantId}/deals/{$lead->id}"),
                        actionLabel:  'View Deal',
                        dedupeSuffix: $lead->id . ':restored:' . now()->format('Ymd'),
                    );
                    Cache::forget("notif_unread_reseller_{$reseller->id}");
                }
            }
        } catch (\Throwable) {}

        $actorUserId = Auth::guard('web')->id() ?? Auth::guard('tenant')->id();
        Cache::deleteMultiple(["dash_counts:{$tenantId}", "lifecycle_del_arch_metrics:{$tenantId}", "lifecycle_expired_metrics:{$tenantId}", "subtab_badge_counts:{$tenantId}"]);
        try {
            app(\App\Services\CriticalActionService::class)->invalidateAllAdminBadges($tenantId);
        } catch (\Throwable) {}

        return back()->with('success', '"' . $lead->name . '" has been restored.');
    }

    // ── DELETE /tenant/{tenantId}/deals/{dealId}/soft-delete ─────────────────

    public function softDeleteDeal(Request $request, string $tenantId, string $dealId): RedirectResponse
    {
        $role = $this->resolveRole($tenantId);
        if (!$this->isAdminMgr($role)) abort(403);

        $data = $request->validate(['reason' => 'nullable|string|max:500']);

        $lead = Lead::where('id', $dealId)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $actorId = Auth::guard('web')->id() ?? Auth::guard('tenant')->id();

        DB::transaction(function () use ($lead, $actorId, $data) {
            $lead->update([
                'deleted_by' => $actorId,
            ]);
            $lead->delete(); // SoftDeletes
        });

        try {
            app(DealActivityService::class)->record(
                $lead,
                'Deal deleted.' . (($data['reason'] ?? null) ? ' Reason: ' . $data['reason'] : ''),
                'deal_deleted'
            );
        } catch (\Throwable $e) {
            Log::warning('softDeleteDeal: activity log failed', ['deal_id' => $lead->id, 'error' => $e->getMessage()]);
        }

        Cache::deleteMultiple(["dash_counts:{$tenantId}", "lifecycle_del_arch_metrics:{$tenantId}", "lifecycle_expired_metrics:{$tenantId}", "subtab_badge_counts:{$tenantId}"]);
        try {
            app(\App\Services\CriticalActionService::class)->invalidateAllAdminBadges($tenantId);
        } catch (\Throwable) {}

        return redirect()
            ->route('tenant.deals.deleted-archived', $tenantId)
            ->with('success', '"' . $lead->name . '" has been deleted.');
    }
}
