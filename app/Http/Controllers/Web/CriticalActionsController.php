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
                ? now()->parse($request->input('since'))->startOfDay()
                : null,
            'until'            => $request->filled('until')
                ? now()->parse($request->input('until'))->endOfDay()
                : null,
        ];

        $result = $this->service->masterList($tenantId, $filters, 25);

        // Track "last seen" so new items can be highlighted.
        // Read the previous timestamp BEFORE updating it.
        $userId      = $actingUser?->id ?? ($isSuperAdmin ? Auth::guard('web')->id() : null);
        $seenCacheKey = "ca_last_seen_{$tenantId}_{$userId}";
        $lastSeenAt   = $userId ? Cache::get($seenCacheKey) : null;

        // Record that the user has now seen all current items.
        // Also bust the badge cache so the counter resets in the nav.
        if ($userId) {
            Cache::put($seenCacheKey, now()->toIso8601String(), now()->addDays(30));
            Cache::forget("ca_badge_{$tenantId}");
        }

        $categories = [
            ''          => 'All Categories',
            'deal'      => 'Deals',
            'import'    => 'Imports',
            'task'      => 'Tasks',
            'messaging' => 'Messages',
            'user'      => 'Users & Invitations',
            'export'    => 'Exports',
            'billing'   => 'Billing',
            'activity'  => 'Activity',
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
}
