<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\TenantConfig;
use App\Models\TenantMembership;
use App\Models\Reseller;
use App\Services\CriticalActionService;
use App\Services\PermissionService;
use App\Services\ReferrerPerformanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TenantAdminController extends Controller
{
    private function config(string $tenantId): ?TenantConfig
    {
        // Cache raw DB attributes (pre-cast JSON strings) to avoid __PHP_Incomplete_Class
        // on deserialization. forceFill(toArray()) would store already-decoded PHP arrays
        // as raw values, causing the array cast to json_decode(array) → null.
        // getAttributes() returns the raw JSON strings; setRawAttributes() restores them
        // so that cast access (->fields, ->stages, etc.) works correctly on retrieval.
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
            'config'       => $cfg,
            'leadLabel'    => $cfg?->lead_label ?? 'Deal',
            'showLocation' => $fields->contains('key', 'province') && $fields->contains('key', 'municipality'),
        ];
    }

    public function dashboard($tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        $tenant->load(['metric']);
        $metric = $tenant->metric;

        $accessExtendedNotif = Notification::where('tenant_id', $tenantId)
            ->where('type', 'access_extended')
            ->where('is_dismissed', false)
            ->where('is_read', false)
            ->latest()
            ->first();

        // Daily dashboard briefing — shown once per calendar day
        $seenTodayKey  = "dash_seen_{$tenantId}_" . now()->format('Y-m-d');
        $lastSeenKey   = "dash_last_seen_{$tenantId}";
        $dailyBriefing = null;

        if (!session()->has($seenTodayKey)) {
            // "Since" = last time the briefing was shown (default: 24h ago on first visit)
            $since = session($lastSeenKey, now()->subHours(24));

            // 1. Expiring deals
            $expiringDeals = DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where('status', 'expiring')
                ->select('id', 'name', 'days_left', 'deal_value', 'reseller_name')
                ->orderBy('days_left')
                ->limit(50)
                ->get();

            // 2. New deals since last visit
            $newDeals = DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where('created_at', '>', $since)
                ->select('id', 'name', 'stage', 'deal_value', 'reseller_name', 'created_at')
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();

            // 3. New resellers invited since last visit (status = invited, pending acceptance)
            $newInvited = DB::table('resellers')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where('status', 'invited')
                ->where('created_at', '>', $since)
                ->select('id', 'name', 'email', 'created_at')
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();

            // 4. Resellers who became active since last visit (joined_date or created_at)
            $newActive = DB::table('resellers')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where('status', 'active')
                ->where('created_at', '>', $since)
                ->select('id', 'name', 'email', 'joined_date', 'created_at')
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();

            // Only show if at least one section has data
            if ($expiringDeals->isNotEmpty() || $newDeals->isNotEmpty() || $newInvited->isNotEmpty() || $newActive->isNotEmpty()) {
                $dailyBriefing = compact('expiringDeals', 'newDeals', 'newInvited', 'newActive');
            }

            session()->put($seenTodayKey, true);
            session()->put($lastSeenKey, now());
        }

        // Current user's reseller name — auto-populates the new deal form
        $currentResellerName = null;
        if (auth('tenant')->check()) {
            $currentResellerName = DB::table('resellers')
                ->where('tenant_id', $tenantId)
                ->where('email', auth('tenant')->user()->email)
                ->value('name');
        }

        // Permission check for billing metrics in critical actions
        $canSeeBilling = false;
        if (auth('tenant')->check()) {
            $actingMembership = TenantMembership::where('tenant_user_id', auth('tenant')->id())
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->first();
            if ($actingMembership) {
                $canSeeBilling = app(PermissionService::class)->can($actingMembership, 'manage_billing_and_subscription');
            }
        } elseif (auth('web')->check()) {
            $canSeeBilling = true;
        }

        // Manager-scoped category gates for dashboard widget.
        // Use actual MANAGER_DEFAULTS keys — 'manage_exports'/'manage_deals'/'manage_team'
        // were undefined, causing can() to always return false for managers.
        $canSeeExports = true;
        $canSeeUsers   = true;
        if (isset($actingMembership) && $actingMembership && $actingMembership->role === 'manager') {
            $permSvc       = app(\App\Services\PermissionService::class);
            $canSeeExports = $permSvc->can($actingMembership, 'approve_export_requests');
            $canSeeUsers   = $permSvc->can($actingMembership, 'invite_tenant_staff');
        }

        // Critical actions for dashboard widget — wrapped so any DB issue never breaks the dashboard
        try {
            $criticalActions = app(CriticalActionService::class)
                ->dashboardSummary($tenantId, 6, $canSeeBilling, $canSeeExports, $canSeeUsers);
        } catch (\Throwable) {
            $criticalActions = [];
        }

        // Extra dashboard counts — cached 60s; short TTL keeps the KPI badges fresh without
        // hitting the DB on every page load (4 queries → 0 queries for non-first visitors)
        try {
            $dashboardCounts = Cache::remember("dash_counts:{$tenantId}", 60, function () use ($tenantId) {
                return [
                    'expiring_deals'   => DB::table('leads')->where('tenant_id', $tenantId)->whereNull('deleted_at')->where('status', 'expiring')->count(),
                    'pending_invites'  => DB::table('tenant_invitations')->where('tenant_id', $tenantId)->where('status', 'pending')->where('expires_at', '>', now())->count(),
                    'import_warnings'  => DB::table('import_batches')->where('tenant_id', $tenantId)->where('status', 'completed_with_warnings')->where('created_at', '>', now()->subDays(14))->count(),
                    'missing_referrer' => DB::table('leads')->where('tenant_id', $tenantId)->whereNull('deleted_at')->where(fn($q) => $q->whereNull('reseller_name')->orWhere('reseller_name', ''))->whereIn('status', ['active', 'expiring'])->count(),
                ];
            });
        } catch (\Throwable) {
            $dashboardCounts = ['expiring_deals' => 0, 'pending_invites' => 0, 'import_warnings' => 0, 'missing_referrer' => 0];
        }

        // Pending tasks widget — shown on every dashboard load for lgu-ids
        $pendingTasks = collect();
        $newDealsSinceLastSession = collect();
        if ($tenantId === 'lgu-ids') {
            try {
                $pendingTasks = DB::table('tasks')
                    ->where('tenant_id', $tenantId)
                    ->whereIn('status', ['open', 'in_progress', 'waiting'])
                    ->whereNull('deleted_at')
                    ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
                    ->orderBy('due_at')
                    ->limit(50)
                    ->get(['id', 'title', 'priority', 'status', 'due_at', 'assigned_to_type', 'assigned_to_id']);
            } catch (\Throwable) {}

            // New deals since the current user last visited the dashboard (per-user cache)
            try {
                $userId = auth('tenant')->id() ?? auth('web')->id() ?? 'anon';
                $lastSeenDashKey = "lgu_dash_last_seen_{$tenantId}_{$userId}";
                $lastSeenAt = \Illuminate\Support\Facades\Cache::get($lastSeenDashKey, now()->subHours(24));

                $newDealsSinceLastSession = DB::table('leads')
                    ->where('tenant_id', $tenantId)
                    ->where('created_at', '>', $lastSeenAt)
                    ->whereNull('deleted_at')
                    ->orderByDesc('created_at')
                    ->limit(20)
                    ->get(['id', 'name', 'stage', 'deal_value', 'reseller_name', 'created_at']);

                // Update "last seen" for next visit
                \Illuminate\Support\Facades\Cache::put($lastSeenDashKey, now(), now()->addDays(30));
            } catch (\Throwable) {}
        }

        return view('tenant.dashboard', array_merge(
            compact('tenant', 'metric', 'accessExtendedNotif', 'dailyBriefing',
                    'currentResellerName', 'criticalActions', 'dashboardCounts', 'canSeeBilling',
                    'pendingTasks', 'newDealsSinceLastSession'),
            $this->configMeta($tenantId)
        ));
    }

    public function deals($tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);

        // Resolve referrer visibility for current user.
        // Super admin (web guard) and owner/admin always see referrer names.
        // Tenant Manager respects their view_referrers permission.
        $canViewReferrers = true;
        if (Auth::guard('tenant')->check()) {
            $userId     = Auth::guard('tenant')->id();
            $membership = TenantMembership::where('tenant_user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->first();

            if ($membership && !in_array($membership->role, ['owner', 'admin'])) {
                $canViewReferrers = app(PermissionService::class)->can($membership, 'view_referrers');
            }
        }

        return view('tenant.deals.index', array_merge(
            ['tenant' => $tenant, 'canViewReferrers' => $canViewReferrers],
            $this->configMeta($tenantId)
        ));
    }

    public function dealShow($tenantId, $dealId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        $lead   = \App\Models\Lead::where('id', $dealId)
            ->where('tenant_id', $tenantId)
            ->with(['commissionSplits', 'notes', 'history'])
            ->first();
        $ssrLead = $lead?->toArray();

        // Referrer list for the reassign picker — active + invited only
        $referrers = DB::table('resellers')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereIn('status', ['active', 'nda_signed', 'invited'])
            ->select('id', 'name', 'email', 'status')
            ->orderBy('name')
            ->get()
            ->toArray();

        // Load pending approval requests for admin review panel
        $pendingApprovals = [];
        try {
            $pendingApprovals = \App\Models\DealApprovalRequest::where('deal_id', $dealId)
                ->where('tenant_id', $tenantId)
                ->whereIn('status', ['pending', 'clarification_requested'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->toArray();
        } catch (\Throwable) {}

        $viewerUserId = auth('web')->id() ?? auth('tenant')->id() ?? '';
        $viewerRole   = auth('web')->check() ? 'super_admin' : 'tenant_admin';

        return view('tenant.deals.show', array_merge(
            ['tenant' => $tenant, 'dealId' => $dealId, 'ssrLead' => $ssrLead, 'pendingApprovals' => $pendingApprovals, 'referrers' => $referrers, 'viewerUserId' => $viewerUserId, 'viewerRole' => $viewerRole],
            $this->configMeta($tenantId)
        ));
    }

    public function contacts($tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        return view('tenant.contacts.index', compact('tenant'));
    }

    public function organizations($tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        return view('tenant.organizations.index', compact('tenant'));
    }

    public function referrers($tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);

        // Resolve referrer visibility for current user.
        $canViewReferrers = true;
        if (Auth::guard('tenant')->check()) {
            $userId     = Auth::guard('tenant')->id();
            $membership = TenantMembership::where('tenant_user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->first();

            if ($membership && !in_array($membership->role, ['owner', 'admin'])) {
                $canViewReferrers = app(\App\Services\PermissionService::class)->can($membership, 'view_referrers');
            }
        }

        // Status breakdown for initial page load (avoids blank KPI cards on first render)
        try {
            $referrerSummary = DB::table('resellers')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN status IN ('active','nda_signed') THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'invited' THEN 1 ELSE 0 END) as invited,
                    SUM(CASE WHEN email IS NULL THEN 1 ELSE 0 END) as no_email
                ")
                ->first();
        } catch (\Throwable) {
            $referrerSummary = (object) ['total' => 0, 'active' => 0, 'invited' => 0, 'no_email' => 0];
        }

        return view('tenant.referrers.index', array_merge(
            ['tenant' => $tenant, 'canViewReferrers' => $canViewReferrers, 'referrerSummary' => $referrerSummary],
            $this->configMeta($tenantId)
        ));
    }

    public function referrerDetail($tenantId, string $referrerId)
    {
        $tenant   = Tenant::findOrFail($tenantId);
        $reseller = Reseller::where('id', $referrerId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $perfService = app(ReferrerPerformanceService::class);
        $performance = $perfService->forReseller($tenantId, $reseller->name, $reseller->id);
        $completeness = $perfService->completenessStatus($reseller);

        // Recent deals (latest 10, non-archived) — includes co-referrer deals via commission_splits
        $recentDeals = collect();
        try {
            $lower = strtolower($reseller->name);
            $recentDeals = DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where('status', '!=', 'archived')
                ->where(fn($q) => $q
                    ->whereRaw('LOWER(reseller_name) = ?', [$lower])
                    ->orWhereExists(fn($sub) => $sub
                        ->from('commission_splits')
                        ->whereColumn('commission_splits.lead_id', 'leads.id')
                        ->whereRaw('LOWER(commission_splits.reseller_name) = ?', [$lower])
                    )
                )
                ->orderByDesc('created_at')
                ->limit(10)
                ->get();
        } catch (\Throwable $e) {
            \Log::warning('referrerDetail: failed to load recent deals', ['error' => $e->getMessage(), 'tenant' => $tenantId]);
        }

        // Recent activity from CriticalActionService
        $recentActivity = [];
        try {
            $recentActivity = app(CriticalActionService::class)
                ->forReseller($tenantId, $reseller->name, 8, $reseller->id);
        } catch (\Throwable) {}

        // Agreements — try new tenant_legal_agreements system first, fall back to legacy
        $agreements = collect();
        try {
            $agreements = DB::table('tenant_legal_agreements as ag')
                ->leftJoin('tenant_legal_agreement_acceptances as acc', function ($join) use ($reseller) {
                    $join->on('ag.id', '=', 'acc.tenant_legal_agreement_id')
                         ->where('acc.user_type', '=', 'reseller')
                         ->where('acc.user_id', '=', (string) $reseller->id);
                })
                ->where('ag.tenant_id', $tenantId)
                ->where('ag.is_active', true)
                ->select(
                    'ag.id',
                    DB::raw("ag.title as label"),
                    DB::raw("NULL as description"),
                    'ag.is_required',
                    DB::raw("NULL as file_url"),
                    'ag.version',
                    'ag.display_order',
                    DB::raw("acc.accepted_at as agreed_at"),
                    DB::raw("NULL as agreed_by_name")
                )
                ->orderBy('ag.display_order')
                ->orderBy('ag.created_at')
                ->get();
        } catch (\Throwable) {}

        // If no new-system agreements, try legacy reseller_agreement_files table
        if ($agreements->isEmpty()) {
            try {
                $agreements = DB::table('reseller_agreement_files as af')
                    ->leftJoin('reseller_agreement_acknowledgments as ack', function ($join) use ($reseller) {
                        $join->on('af.id', '=', 'ack.agreement_file_id')
                             ->where('ack.reseller_id', '=', $reseller->id);
                    })
                    ->where('af.tenant_id', $tenantId)
                    ->where('af.is_active', true)
                    ->select('af.id', 'af.label', 'af.description', 'af.is_required',
                             'af.file_url', 'af.version', 'af.display_order',
                             'ack.agreed_at', 'ack.agreed_by_name')
                    ->orderBy('af.display_order')
                    ->orderBy('af.created_at')
                    ->get();
            } catch (\Throwable) {}
        }

        $requiredTotal      = $agreements->where('is_required', true)->count();
        $signedRequired     = $agreements->where('is_required', true)->whereNotNull('agreed_at')->count();
        $agreementCompliant = $requiredTotal === 0 || $signedRequired >= $requiredTotal;

        return view('tenant.referrers.show', array_merge(
            [
                'tenant'        => $tenant,
                'reseller'      => $reseller,
                'performance'   => $performance,
                'completeness'  => $completeness,
                'recentDeals'        => $recentDeals,
                'recentActivity'     => $recentActivity,
                'agreements'         => $agreements,
                'requiredTotal'      => $requiredTotal,
                'signedRequired'     => $signedRequired,
                'agreementCompliant' => $agreementCompliant,
            ],
            $this->configMeta($tenantId)
        ));
    }

    public function resendReferrerInvite(string $tenantId, string $referrerId): \Illuminate\Http\RedirectResponse
    {
        $tenant   = Tenant::findOrFail($tenantId);
        $reseller = Reseller::where('id', $referrerId)->where('tenant_id', $tenantId)->firstOrFail();

        if (!in_array($reseller->status, ['invited', 'active'])) {
            return back()->withErrors(['invite' => 'Invite can only be resent for referrers with Invited or Active status.']);
        }

        // Regenerate setup token and resend invitation email
        $setupToken = \Illuminate\Support\Str::random(64);
        $reseller->update(['setup_token' => $setupToken, 'status' => 'invited']);

        $setupUrl = url('/reseller/setup?token=' . $setupToken);

        // Per-hour dedup key prevents double-click duplicate emails while still
        // allowing intentional resends after an hour.
        $sent = \App\Services\EmailLogger::send(
            mailable:       new \App\Mail\ResellerInvitation(
                resellerName:  $reseller->name,
                resellerEmail: $reseller->email,
                tenantName:    $tenant->name ?? 'ReferralBunny',
                setupUrl:      $setupUrl,
            ),
            recipientEmail: $reseller->email,
            recipientType:  'reseller',
            emailKey:       "reseller_invite_resend.{$reseller->id}",
            dailyDedup:     true,
            subject:        'Your ReferralBunny.ai referrer account invitation',
            tenantId:       $tenantId,
        );

        if (!$sent) {
            return back()->withErrors(['invite' => 'Could not send invite email. Please try again or check your mail configuration.']);
        }

        try {
            app(\App\Services\NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'tenant_workspace',
                priority:     'normal',
                title:        'Referrer invite resent',
                body:         'Invite resent to ' . $reseller->name . ' (' . $reseller->email . ').',
                actionUrl:    url("/tenant/{$tenantId}/referrers/{$referrerId}"),
                actionLabel:  'View Referrer',
                dedupeSuffix: $referrerId . ':invite_resent:' . now()->format('YmdH'),
            );
        } catch (\Throwable) {}

        return back()->with('success', 'Invite resent to ' . $reseller->email . '.');
    }

    public function tasks($tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        return view('tenant.tasks.index', compact('tenant'));
    }

    public function messages($tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);

        $resellers = DB::table('resellers')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereIn('status', ['active', 'invited'])
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        $partners = DB::table('partner_users')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->select('id', 'first_name', 'last_name', 'email')
            ->orderBy('first_name')
            ->get()
            ->map(fn($p) => (object) [
                'id'    => $p->id,
                'name'  => trim(($p->first_name ?? '') . ' ' . ($p->last_name ?? '')) ?: $p->email,
                'email' => $p->email,
            ]);

        $tenantUsers = DB::table('tenant_users as tu')
            ->join('tenant_memberships as tm', 'tm.tenant_user_id', '=', 'tu.id')
            ->where('tm.tenant_id', $tenantId)
            ->where('tm.status', 'active')
            ->whereIn('tm.role', ['owner', 'admin', 'manager'])
            ->select('tu.id', 'tu.first_name', 'tu.last_name', 'tu.email', 'tm.role')
            ->orderBy('tm.role')
            ->orderBy('tu.first_name')
            ->get()
            ->map(fn($u) => (object) [
                'id'    => $u->id,
                'name'  => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: $u->email,
                'email' => $u->email,
                'role'  => $u->role,
            ]);

        $contacts = DB::table('contacts')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->select('id', 'first_name', 'last_name', 'email')
            ->orderBy('first_name')
            ->limit(200)
            ->get()
            ->map(fn($c) => (object) [
                'id'    => $c->id,
                'name'  => trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) ?: $c->email,
                'email' => $c->email,
            ]);

        return view('tenant.messages.index', compact('tenant', 'resellers', 'partners', 'tenantUsers', 'contacts'));
    }

    public function agreements($tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        return view('tenant.agreements.index', compact('tenant'));
    }

    public function reports($tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        return view('tenant.reports.index', array_merge(['tenant' => $tenant], $this->configMeta($tenantId)));
    }

    public function imports($tenantId)
    {
        $tenant  = Tenant::findOrFail($tenantId);
        $batches = null;
        $pendingInvites = 0;
        if ($tenantId === 'lgu-ids') {
            $batches = \App\Models\ImportBatch::where('tenant_id', $tenantId)
                ->orderByDesc('created_at')->limit(5)->get();
            $pendingInvites = \App\Models\PendingReferrerInvite::where('tenant_id', $tenantId)
                ->where('status', 'pending_invite')->count();
        }
        return view('tenant.imports.index', compact('tenant', 'batches', 'pendingInvites'));
    }

    public function users($tenantId)
    {
        return app(\App\Http\Controllers\Web\TenantUserManagementController::class)->index($tenantId);
    }

    public function billing($tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        return view('tenant.billing.index', compact('tenant'));
    }

    public function settings($tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        return view('tenant.settings.index', compact('tenant'));
    }

    public function updateSettings(Request $request, string $tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);

        // Only owner/admin may update workspace settings
        if ($userId = Auth::guard('tenant')->id()) {
            $membership = TenantMembership::where('tenant_user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->first();
            if (!$membership || !in_array($membership->role, ['owner', 'admin'])) {
                abort(403, 'Only Owners and Admins can update workspace settings.');
            }
        }

        $data = $request->validate([
            'program_name'  => 'required|string|max:200',
            'business_name' => 'nullable|string|max:200',
            'description'   => 'nullable|string|max:1000',
            'accent_color'  => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $tenant->update($data);

        return back()->with('settings_saved', 'Workspace settings saved successfully.');
    }
}
