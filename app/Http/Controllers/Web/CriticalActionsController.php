<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\CriticalActionService;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CriticalActionsController extends Controller
{
    public function __construct(
        private CriticalActionService $service,
        private PermissionService     $permissions,
    ) {}

    public function index(string $tenantId, Request $request)
    {
        $tenant     = Tenant::findOrFail($tenantId);
        $actingUser = Auth::guard('tenant')->user();
        $membership = null;

        if ($actingUser) {
            $membership = TenantMembership::where('tenant_user_id', $actingUser->id)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->first();
        }

        // Manager permission check — must have view_dashboard at minimum
        if ($membership && $membership->role === 'member') {
            abort(403, 'Access to the critical actions list requires Admin or Manager access.');
        }

        $isSuperAdmin = Auth::guard('web')->check();

        $canSeeBilling = $membership
            ? $this->permissions->can($membership, 'manage_billing_and_subscription')
            : $isSuperAdmin;

        // Manager-scoped category gates: what categories this user may see.
        // Use actual MANAGER_DEFAULTS keys: invite_tenant_staff (true by default) and
        // approve_export_requests (false by default). The former 'manage_team',
        // 'manage_exports', 'manage_deals' keys did not exist in MANAGER_DEFAULTS
        // causing can() to always return false for managers.
        $canSeeUsers   = $isSuperAdmin || !$membership || in_array($membership->role, ['owner', 'admin'])
            || $this->permissions->can($membership, 'invite_tenant_staff');
        $canSeeExports = $isSuperAdmin || !$membership || in_array($membership->role, ['owner', 'admin'])
            || $this->permissions->can($membership, 'approve_export_requests');

        $userId     = $actingUser?->id ?? ($isSuperAdmin ? Auth::guard('web')->id() : null);
        $userType   = $isSuperAdmin && !$actingUser ? 'web' : 'tenant_user';

        $validSorts = ['recency_desc', 'recency_asc'];
        $filters = [
            'search'           => $request->input('search'),
            'severity'         => $request->input('severity'),
            'category'         => $request->input('category'),
            'sort'             => in_array($request->input('sort'), $validSorts)
                                    ? $request->input('sort') : 'recency_desc',
            'page'             => (int) $request->input('page', 1),
            'can_see_billing'  => $canSeeBilling,
            'can_see_users'    => $canSeeUsers,
            'can_see_exports'  => $canSeeExports,
            'since'            => $request->filled('since')
                ? Carbon::parse($request->input('since'))->startOfDay()
                : null,
            'until'            => $request->filled('until')
                ? Carbon::parse($request->input('until'))->endOfDay()
                : null,
            'user_id'          => $userId,
            'user_type'        => $userType,
        ];

        $result = $this->service->masterList($tenantId, $filters, 25);

        // Track "last seen" so new items can be highlighted.
        // Read the previous timestamp BEFORE updating it.
        $seenCacheKey = "ca_last_seen_{$tenantId}_{$userId}";
        $lastSeenAt   = $userId ? Cache::get($seenCacheKey) : null;

        // Record that the user has now seen all current items.
        // Bust both legacy and new fast-badge cache keys so the nav counter resets.
        if ($userId) {
            Cache::put($seenCacheKey, now()->toIso8601String(), now()->addDays(30));
            // Forget both key formats and suppress for 5 min — opening the page = "seen all"
            Cache::forget("ca_badge_{$tenantId}_{$userId}");
            Cache::forget("ca_badge_fast:{$tenantId}:{$userId}");
            Cache::forget("ca_badge_urgent:{$tenantId}:{$userId}");
            Cache::put("ca_badge_suppressed:{$tenantId}:{$userId}", 1, 300);
        }

        $categories = [
            ''           => 'All Categories',
            'deal'       => 'Deals',
            'commission' => 'Commission',
            'import'     => 'Imports',
            'task'       => 'Tasks',
            'messaging'  => 'Messages',
            'user'       => 'Users & Invitations',
            'export'     => 'Exports',
            'billing'    => 'Billing',
            'activity'   => 'Activity',
        ];

        $severities = [
            ''        => 'All Severities',
            'urgent'  => 'Urgent',
            'high'    => 'High',
            'medium'  => 'Medium',
            'low'     => 'Low',
            'info'    => 'Info',
        ];

        return view('tenant.critical-actions.index', [
            'tenant'      => $tenant,
            'result'      => $result,
            'filters'     => $filters,
            'categories'  => $categories,
            'severities'  => $severities,
            'actingRole'  => $membership?->role ?? ($canSeeBilling ? 'super_admin' : 'admin'),
            'lastSeenAt'  => $lastSeenAt ? Carbon::parse($lastSeenAt) : null,
        ]);
    }

    public function markAllRead(string $tenantId, Request $request): \Illuminate\Http\JsonResponse
    {
        $isSuperAdmin = Auth::guard('web')->check();
        $actingUser   = Auth::guard('tenant')->user();

        // Tenant isolation: verify the caller belongs to this tenant (prevents IDOR).
        // Super-admins are exempt — they have cross-tenant access.
        if (! $isSuperAdmin && $actingUser) {
            $hasMembership = TenantMembership::where('tenant_user_id', $actingUser->id)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->exists();

            if (! $hasMembership) {
                return response()->json(['success' => false, 'message' => 'Access denied.'], 403);
            }
        } elseif (! $isSuperAdmin) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $userId = $actingUser?->id ?? Auth::guard('web')->id();

        if ($userId) {
            Cache::put("ca_last_seen_{$tenantId}_{$userId}", now()->toIso8601String(), now()->addDays(30));
            // Bust both legacy and new fast-badge keys
            Cache::forget("ca_badge_{$tenantId}_{$userId}");
            Cache::forget("ca_badge_fast:{$tenantId}:{$userId}");
            Cache::forget("ca_badge_urgent:{$tenantId}:{$userId}");
            // Suppress badge for 5 minutes so it doesn't instantly reappear
            Cache::put("ca_badge_suppressed:{$tenantId}:{$userId}", 1, 300);
        }

        return response()->json(['success' => true, 'message' => 'All critical actions marked as seen.']);
    }

    /**
     * Dismiss a single dismissible critical action for the current user.
     * POST /tenant/{tenantId}/critical-actions/dismiss
     * Body: { fingerprint, action_type }
     */
    public function dismiss(string $tenantId, Request $request): \Illuminate\Http\JsonResponse
    {
        $isSuperAdmin = Auth::guard('web')->check();
        $actingUser   = Auth::guard('tenant')->user();

        if (! $isSuperAdmin && $actingUser) {
            $hasMembership = TenantMembership::where('tenant_user_id', $actingUser->id)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->exists();
            if (! $hasMembership) {
                return response()->json(['success' => false, 'message' => 'Access denied.'], 403);
            }
        } elseif (! $isSuperAdmin) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $fingerprint = $request->input('fingerprint');
        // Truncate action_type defensively — it's informational metadata only, not a key.
        $actionType  = substr((string) $request->input('action_type', ''), 0, 100);

        // MD5 fingerprints are always exactly 32 lowercase hex characters.
        // Reject anything else to prevent garbage from entering the dismissals table.
        if (! $fingerprint || ! preg_match('/^[a-f0-9]{32}$/', $fingerprint)) {
            return response()->json(['success' => false, 'message' => 'Invalid fingerprint.'], 422);
        }

        $userId   = $actingUser?->id ?? Auth::guard('web')->id();
        $userType = ($isSuperAdmin && ! $actingUser) ? 'web' : 'tenant_user';

        \Illuminate\Support\Facades\DB::table('critical_action_dismissals')->upsert(
            [
                'tenant_id'    => $tenantId,
                'user_id'      => $userId,
                'user_type'    => $userType,
                'fingerprint'  => $fingerprint,
                'action_type'  => $actionType,
                'dismissed_at' => now(),
                'expires_at'   => null,
            ],
            ['tenant_id', 'user_id', 'user_type', 'fingerprint'],
            ['dismissed_at', 'action_type']
        );

        // Bust badge cache so count updates promptly (mirrors markAllRead key set)
        Cache::forget("ca_badge_{$tenantId}_{$userId}");
        Cache::forget("ca_badge_fast:{$tenantId}:{$userId}");
        Cache::forget("ca_badge_urgent:{$tenantId}:{$userId}");
        // Bust dismissed-fingerprint cache so the next masterList() reflects this
        // dismissal immediately without waiting for the 30 s TTL to expire.
        Cache::forget("ca_dismissed:{$tenantId}:{$userId}:{$userType}");

        return response()->json(['success' => true]);
    }

    /**
     * Lightweight JSON endpoint for the nav badge counter.
     * Returns { count: N } — used by client-side polling so the badge
     * refreshes without a full page reload.
     *
     * Tenant isolation: tenantId comes from the authenticated route param, never
     * from the request body. Role-scoping mirrors _nav.blade.php exactly.
     */
    public function badge(string $tenantId, Request $request): \Illuminate\Http\JsonResponse
    {
        // Only admin/manager roles may see the badge.
        $actingUser = Auth::guard('tenant')->user();
        $isSuperAdmin = Auth::guard('web')->check();
        $userId = $actingUser?->id ?? ($isSuperAdmin ? Auth::guard('web')->id() : null);

        if (! $isSuperAdmin && ! $actingUser) {
            return response()->json(['count' => 0]);
        }

        // Resolve role — must be admin-level to get a non-zero badge.
        $navRole = 'viewer';
        if ($isSuperAdmin) {
            $navRole = 'super_admin';
        } elseif ($actingUser) {
            $membership = \App\Models\TenantMembership::where('tenant_user_id', $actingUser->id)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->first();

            if (! $membership) {
                // User has no membership in this tenant — return 0 (cross-tenant isolation)
                return response()->json(['count' => 0]);
            }

            $navRole = $membership->role ?? 'viewer';
        }

        $isAdminMgr = in_array($navRole, ['owner', 'admin', 'manager', 'super_admin']);
        if (! $isAdminMgr) {
            return response()->json(['count' => 0]);
        }

        // Derive permission flags (mirrors _nav.blade.php logic exactly)
        $_canSeeBilling = in_array($navRole, ['owner', 'admin', 'super_admin']);
        $_canSeeExports = in_array($navRole, ['owner', 'admin', 'super_admin']);
        $_canSeeUsers   = in_array($navRole, ['owner', 'admin', 'super_admin']);

        if ($navRole === 'manager' && $userId && isset($membership)) {
            $_permSvc       = app(\App\Services\PermissionService::class);
            $_canSeeExports = $_permSvc->can($membership, 'approve_export_requests');
            $_canSeeUsers   = $_permSvc->can($membership, 'invite_tenant_staff');
        }

        try {
            // Cache key matches _nav.blade.php so both share the same warm cache.
            $_caBadgeKey    = "ca_badge_{$tenantId}_{$userId}";
            $_suppressedKey = "ca_badge_suppressed:{$tenantId}:{$userId}";
            $_suppressed    = Cache::has($_suppressedKey);

            if ($_suppressed) {
                // Even when suppressed, surface urgent items.
                // badgeCount() returns has_urgent — no separate urgentBadgeCount() needed.
                $_urgentKey = "ca_badge_urgent:{$tenantId}:{$userId}";
                $badge = Cache::remember(
                    $_urgentKey, 120,
                    fn() => $this->service->badgeCount($tenantId, $_canSeeBilling, $_canSeeExports, $_canSeeUsers)
                );
                $count     = ($badge['has_urgent'] ?? false) ? (int)($badge['count'] ?? 0) : 0;
                $hasUrgent = (bool)($badge['has_urgent'] ?? false);
            } else {
                // Cache the full badge array for 60 s — gives both count + has_urgent in one lookup.
                $badge = Cache::remember(
                    $_caBadgeKey, 60,
                    fn() => $this->service->badgeCount($tenantId, $_canSeeBilling, $_canSeeExports, $_canSeeUsers)
                );
                $count     = (int)($badge['count']      ?? 0);
                $hasUrgent = (bool)($badge['has_urgent'] ?? false);
            }

            return response()->json(['count' => $count, 'has_urgent' => $hasUrgent]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[CriticalActionsController] badge() failed', [
                'tenant_id' => $tenantId,
                'error'     => $e->getMessage(),
            ]);
            return response()->json(['count' => null, 'has_urgent' => false, 'error' => true], 200);
        }
    }
}
