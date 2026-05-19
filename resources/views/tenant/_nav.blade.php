@php
// ── Role resolution ────────────────────────────────────────────────────────
$tenantId = $tenant->id;

if (auth('web')->check()) {
    $navRole = 'super_admin';
} elseif (request()->attributes->has('_tenant_role')) {
    $navRole = request()->attributes->get('_tenant_role');
} elseif (auth('tenant')->check()) {
    $_navUserId = auth('tenant')->id();
    $navRole = \Illuminate\Support\Facades\Cache::remember(
        "nav_role:{$tenantId}:{$_navUserId}",
        60,
        fn() => \App\Models\TenantMembership::where('tenant_user_id', $_navUserId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->value('role') ?? 'viewer'
    );
} else {
    $navRole = 'viewer';
}

$isPartner  = $navRole === 'partner';
$isAdminMgr = in_array($navRole, ['owner', 'admin', 'manager', 'super_admin']);

// ── Active group detection ─────────────────────────────────────────────────
$activeGroup = null;
if (request()->routeIs([
    'tenant.deals', 'tenant.deals.*',
    'tenant.contacts', 'tenant.contacts.*',
    'tenant.organizations', 'tenant.organizations.*',
    'tenant.referrers', 'tenant.referrers.*',
    'tenant.records', 'tenant.records.*',
    'tenant.leads', 'tenant.leads.*',
])) {
    $activeGroup = 'pipeline';
} elseif (request()->routeIs([
    'tenant.tasks', 'tenant.tasks.*',
    'tenant.request-forms', 'tenant.request-forms.*',
    'tenant.messages',
    'tenant.critical-actions',
    'tenant.notifications',
    'tenant.resources', 'tenant.resources.*',
])) {
    $activeGroup = 'workspace';
} elseif (request()->routeIs([
    'tenant.imports', 'tenant.imports.*',
    'tenant.exports', 'tenant.exports.*',
    'tenant.reports',
    'tenant.commission', 'tenant.commission.*',
])) {
    $activeGroup = 'data';
} elseif (request()->routeIs([
    'tenant.users',
    'tenant.agreements',
    'tenant.billing',
    'tenant.settings', 'tenant.settings.*',
    'tenant.profile',
])) {
    $activeGroup = 'admin';
}

// ── Badge counts (server-side, all try/catch so nav never crashes) ─────────
$taskBadge = $msgBadge = 0;
try {
    // Resolve current actor for personalized task badge
    $_navActorId   = auth('tenant')->id() ?? auth('web')->id();
    $_navActorType = auth('tenant')->check() ? 'tenant_user' : 'web';

    // Last time this user visited the tasks page — badge only shows NEW tasks
    $_taskSeenKey = 'tenant_task_seen_' . $tenantId . '_' . $_navActorId;
    $_taskLastSeen = session($_taskSeenKey);

    $_taskBadgeCacheKey = "nav_task_badge:{$tenantId}:{$_navActorId}:" . md5((string) $_taskLastSeen);
    $taskBadge = (int) \Illuminate\Support\Facades\Cache::remember($_taskBadgeCacheKey, 60, function () use ($tenantId, $isAdminMgr, $_taskLastSeen, $_navActorType, $_navActorId) {
        if ($isAdminMgr) {
            $taskQ = \Illuminate\Support\Facades\DB::table('tasks')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->whereNotIn('status', ['completed', 'cancelled', 'archived']);
            if ($_taskLastSeen) {
                $taskQ->where('created_at', '>', $_taskLastSeen);
            }
            return $taskQ->count();
        } else {
            $taskQ = \Illuminate\Support\Facades\DB::table('tasks')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where('assigned_to_type', $_navActorType)
                ->where('assigned_to_id', $_navActorId)
                ->whereNotIn('status', ['completed', 'cancelled', 'archived']);
            if ($_taskLastSeen) {
                $taskQ->where('created_at', '>', $_taskLastSeen);
            }
            return $taskQ->count();
        }
    });

    // Reset badge when user is on the tasks page
    if (request()->routeIs('tenant.tasks*')) {
        session([$_taskSeenKey => now()->toIso8601String()]);
        $taskBadge = 0;
    }
} catch (\Throwable) {}

try {
    $msgBadge = (int) \Illuminate\Support\Facades\Cache::remember("nav_msg_badge:{$tenantId}", 30, function () use ($tenantId) {
        return \Illuminate\Support\Facades\DB::table('message_threads')
                ->where('tenant_id', $tenantId)->where('admin_unread', '>', 0)->count()
            + \Illuminate\Support\Facades\DB::table('partner_threads')
                ->where('tenant_id', $tenantId)->where('admin_unread', '>', 0)->count();
    });
} catch (\Throwable) {}

// Note: $criticalBadge is added to workspaceBadge after it's computed below
$workspaceBadge = $taskBadge + $msgBadge;

// Critical actions badge — uses lightweight badgeCount() (~10 targeted COUNT queries)
// instead of loading all action objects. Cached per USER (not per tenant) so one admin's
// page visit does not reset another admin's badge. TTL: 60 s.
// badgeCount() returns ['count' => N, 'has_urgent' => bool] so the suppressed-badge path
// can surface urgent items without calling a separate method.
$criticalBadge = 0;
if ($isAdminMgr) {
    try {
        $_caUserId   = auth('tenant')->id() ?? auth('web')->id();
        $_caBadgeKey = "ca_badge_{$tenantId}_{$_caUserId}";
        // Derive permission flags matching CriticalActionsController gates exactly.
        // Owners/admins/super_admins see everything. Managers are checked per-permission.
        $_canSeeBilling = in_array($navRole, ['owner', 'super_admin']);
        $_canSeeExports = in_array($navRole, ['owner', 'admin', 'super_admin']);
        $_canSeeUsers   = in_array($navRole, ['owner', 'admin', 'super_admin']);
        if ($navRole === 'manager' && $_caUserId) {
            $_mgMembership = \Illuminate\Support\Facades\Cache::remember(
                "nav_membership:{$tenantId}:{$_caUserId}", 60,
                fn() => \App\Models\TenantMembership::where('tenant_user_id', $_caUserId)
                    ->where('tenant_id', $tenantId)->where('status', 'active')->first()
            );
            if ($_mgMembership) {
                $_permSvc       = app(\App\Services\PermissionService::class);
                $_canSeeExports = $_permSvc->can($_mgMembership, 'approve_export_requests');
                $_canSeeUsers   = $_permSvc->can($_mgMembership, 'invite_tenant_staff');
            }
        }

        // Bypass suppressor for urgent-severity actions so critical alerts always show.
        // badgeCount() returns has_urgent so we don't need a separate urgentBadgeCount() call.
        $_suppressedKey = "ca_badge_suppressed:{$tenantId}:{$_caUserId}";
        $_suppressed    = \Illuminate\Support\Facades\Cache::has($_suppressedKey);
        if ($_suppressed) {
            // Re-use the badge cache (TTL 120 s for suppressed path) to avoid uncached DB round-trip.
            $_badge        = \Illuminate\Support\Facades\Cache::remember(
                "ca_badge_urgent:{$tenantId}:{$_caUserId}", 120,
                fn() => app(\App\Services\CriticalActionService::class)
                    ->badgeCount($tenantId, $_canSeeBilling, $_canSeeExports, $_canSeeUsers)
            );
            // Show full count when urgents exist; otherwise show 0 (everything else is "seen").
            $criticalBadge = ($_badge['has_urgent'] ?? false) ? (int)($_badge['count'] ?? 0) : 0;
        } else {
            // Cache the full badge array for 60 s — a single cache lookup gives both count + has_urgent.
            $_badge        = \Illuminate\Support\Facades\Cache::remember(
                $_caBadgeKey, 60,
                fn() => app(\App\Services\CriticalActionService::class)
                    ->badgeCount($tenantId, $_canSeeBilling, $_canSeeExports, $_canSeeUsers)
            );
            $criticalBadge = (int)($_badge['count'] ?? 0);
        }
        // Clear THIS USER's badge when they visit the critical actions page.
        if (request()->routeIs('tenant.critical-actions')) {
            \Illuminate\Support\Facades\Cache::forget($_caBadgeKey);
            \Illuminate\Support\Facades\Cache::forget("ca_badge_urgent:{$tenantId}:{$_caUserId}");
            $criticalBadge = 0;
        }
    } catch (\Throwable) {}
}

$workspaceBadge += $criticalBadge;
@endphp

{{-- ═══════════════════════════════════════════════════════════════════════
     Expandable sidebar nav — Alpine.js + localStorage persistence
     ═══════════════════════════════════════════════════════════════════════ --}}
<div x-data="{
        open: { pipeline: false, workspace: false, data: false, admin: false },
        init() {
            try {
                const s = localStorage.getItem('rb.nav.v2');
                if (s) Object.assign(this.open, JSON.parse(s));
            } catch {}
            const ag = @js($activeGroup);
            if (ag) this.open[ag] = true;
        },
        toggle(g) {
            this.open[g] = !this.open[g];
            try { localStorage.setItem('rb.nav.v2', JSON.stringify(this.open)); } catch {}
        },
    }"
     class="space-y-0.5">

    {{-- ── Dashboard ──────────────────────────────────────────────────────── --}}
    <a href="{{ route('tenant.dashboard', $tenantId) }}"
       aria-current="{{ request()->routeIs('tenant.dashboard') ? 'page' : 'false' }}"
       @click="window.dispatchEvent(new CustomEvent('sidebar-close'))"
       class="nav-top {{ request()->routeIs('tenant.dashboard') ? 'nav-top-active' : '' }}">
        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
        </svg>
        Dashboard
    </a>

    {{-- ── Calendar ─────────────────────────────────────────────────────────── --}}
    <a href="{{ route('tenant.calendar', $tenantId) }}"
       aria-current="{{ request()->routeIs('tenant.calendar*') ? 'page' : 'false' }}"
       @click="window.dispatchEvent(new CustomEvent('sidebar-close'))"
       class="nav-top {{ request()->routeIs('tenant.calendar*') ? 'nav-top-active' : '' }}">
        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
        Calendar
    </a>

    {{-- ── Pipeline ────────────────────────────────────────────────────────── --}}
    <div>
        <button @click="toggle('pipeline')"
                :aria-expanded="open.pipeline.toString()"
                aria-controls="nav-pipeline"
                class="nav-group {{ $activeGroup === 'pipeline' ? 'nav-group-active' : '' }}">
            <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
            </svg>
            <span class="flex-1 text-left">Pipeline</span>
            <svg class="nav-chevron" :class="open.pipeline ? 'rotate-90' : ''"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
            </svg>
        </button>

        <div id="nav-pipeline"
             x-show="open.pipeline" x-cloak
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="nav-children">

            <a href="{{ route('tenant.deals', $tenantId) }}"
               aria-current="{{ request()->routeIs('tenant.deals*') ? 'page' : 'false' }}"
               @click="window.dispatchEvent(new CustomEvent('sidebar-close'))"
               class="nav-child {{ request()->routeIs('tenant.deals*') ? 'nav-child-active' : '' }}">
                <svg class="nav-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                </svg>
                Deals
            </a>

            <a href="{{ route('tenant.organizations', $tenantId) }}"
               aria-current="{{ request()->routeIs('tenant.organizations*') ? 'page' : 'false' }}"
               @click="window.dispatchEvent(new CustomEvent('sidebar-close'))"
               class="nav-child {{ request()->routeIs('tenant.organizations*') ? 'nav-child-active' : '' }}">
                <svg class="nav-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                Organizations
            </a>

            <a href="{{ route('tenant.contacts', $tenantId) }}"
               aria-current="{{ request()->routeIs('tenant.contacts*') ? 'page' : 'false' }}"
               @click="window.dispatchEvent(new CustomEvent('sidebar-close'))"
               class="nav-child {{ request()->routeIs('tenant.contacts*') ? 'nav-child-active' : '' }}">
                <svg class="nav-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                Contacts
            </a>

            @if(!$isPartner)
            <a href="{{ route('tenant.referrers', $tenantId) }}"
               aria-current="{{ request()->routeIs('tenant.referrers*') ? 'page' : 'false' }}"
               @click="window.dispatchEvent(new CustomEvent('sidebar-close'))"
               class="nav-child {{ request()->routeIs('tenant.referrers*') ? 'nav-child-active' : '' }}">
                <svg class="nav-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                Referrers
            </a>
            @endif
        </div>
    </div>

    {{-- ── Workspace ───────────────────────────────────────────────────────── --}}
    {{-- x-data tracks the live workspace total so the group button badge updates
         when the 60s critical-badge poller fires without a full page reload. --}}
    <div x-data="{
            wsTotal: {{ (int) $workspaceBadge }},
            init() {
                window.addEventListener('critical-badge:updated', (e) => {
                    const newCritical = typeof e.detail?.count === 'number' ? e.detail.count : {{ (int) $criticalBadge }};
                    this.wsTotal = Math.max(0, {{ (int) ($taskBadge + $msgBadge) }} + newCritical);
                });
            }
         }">
        <button @click="toggle('workspace')"
                :aria-expanded="open.workspace.toString()"
                aria-controls="nav-workspace"
                class="nav-group {{ $activeGroup === 'workspace' ? 'nav-group-active' : '' }}">
            <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
            </svg>
            <span class="flex-1 text-left">Workspace</span>
            {{-- Live total: hidden at 0, capped at 9+ (consistent with notification bell). --}}
            <span x-show="wsTotal > 0" x-cloak
                  class="nav-badge nav-badge-orange mr-1"
                  :aria-label="wsTotal + ' item' + (wsTotal === 1 ? '' : 's') + ' need attention'"
                  x-text="wsTotal > 99 ? '99+' : wsTotal"></span>
            @if($workspaceBadge > 0)
                <noscript><span class="nav-badge nav-badge-orange mr-1">{{ $workspaceBadge > 99 ? '99+' : $workspaceBadge }}</span></noscript>
            @endif
            <svg class="nav-chevron" :class="open.workspace ? 'rotate-90' : ''"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
            </svg>
        </button>

        <div id="nav-workspace"
             x-show="open.workspace" x-cloak
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="nav-children">

            <a href="{{ route('tenant.tasks', $tenantId) }}"
               aria-current="{{ request()->routeIs('tenant.tasks*') ? 'page' : 'false' }}"
               @click="window.dispatchEvent(new CustomEvent('sidebar-close'))"
               class="nav-child {{ request()->routeIs('tenant.tasks*') ? 'nav-child-active' : '' }}">
                <svg class="nav-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="flex-1">Tasks</span>
                @if($taskBadge > 0)
                    <span class="nav-badge nav-badge-purple" aria-label="{{ $taskBadge }} new {{ $taskBadge === 1 ? 'task' : 'tasks' }} since last visit">
                        {{ $taskBadge > 99 ? '99+' : $taskBadge }}
                    </span>
                @endif
            </a>

            <a href="{{ route('tenant.request-forms', $tenantId) }}"
               aria-current="{{ request()->routeIs('tenant.request-forms*') ? 'page' : 'false' }}"
               @click="window.dispatchEvent(new CustomEvent('sidebar-close'))"
               class="nav-child {{ request()->routeIs('tenant.request-forms*') ? 'nav-child-active' : '' }}">
                <svg class="nav-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 17h.01"/>
                </svg>
                Request Forms
            </a>

            <a href="{{ route('tenant.messages', $tenantId) }}"
               aria-current="{{ request()->routeIs('tenant.messages') ? 'page' : 'false' }}"
               @click="window.dispatchEvent(new CustomEvent('sidebar-close'))"
               class="nav-child {{ request()->routeIs('tenant.messages') ? 'nav-child-active' : '' }}">
                <svg class="nav-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                </svg>
                <span class="flex-1">Messages</span>
                @if($msgBadge > 0)
                    <span class="nav-badge nav-badge-teal" aria-label="{{ $msgBadge }} unread conversation{{ $msgBadge === 1 ? '' : 's' }}">
                        {{ $msgBadge > 99 ? '99+' : $msgBadge }}
                    </span>
                @endif
            </a>

            <a href="{{ route('tenant.resources', $tenantId) }}"
               aria-current="{{ request()->routeIs('tenant.resources*') ? 'page' : 'false' }}"
               @click="window.dispatchEvent(new CustomEvent('sidebar-close'))"
               class="nav-child {{ request()->routeIs('tenant.resources*') ? 'nav-child-active' : '' }}">
                <svg class="nav-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                </svg>
                Resources
            </a>

            @if($isAdminMgr)
            {{-- Critical Actions nav item with live-polling badge.
                 x-data initialises from the server-rendered $criticalBadge so there is
                 no flash-of-zero on load. A 60s polling interval keeps it fresh without
                 hammering the server. On error the badge shows "!" to signal staleness. --}}
            <a href="{{ route('tenant.critical-actions', $tenantId) }}"
               aria-current="{{ request()->routeIs('tenant.critical-actions') ? 'page' : 'false' }}"
               @click="window.dispatchEvent(new CustomEvent('sidebar-close'))"
               class="nav-child {{ request()->routeIs('tenant.critical-actions') ? 'nav-child-active' : '' }}"
               x-data="criticalBadgePoller(
                   '{{ route('tenant.critical-actions.badge', $tenantId) }}',
                   {{ (int) $criticalBadge }},
                   {{ request()->routeIs('tenant.critical-actions') ? 'true' : 'false' }}
               )"
               x-init="init()">
                <svg class="nav-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span class="flex-1">Critical Actions</span>

                {{-- Loading skeleton (shown only on very first fetch if server rendered 0) --}}
                <span x-show="loading && count === 0" x-cloak
                      class="inline-block w-5 h-4 rounded-full bg-white/20 animate-pulse"
                      aria-hidden="true"></span>

                {{-- Error fallback: "!" badge when fetch fails --}}
                <span x-show="fetchError && count === 0" x-cloak
                      class="nav-badge nav-badge-orange"
                      aria-label="Critical actions count unavailable"
                      title="Could not load count — click to view">!</span>

                {{-- Normal count badge --}}
                <span x-show="count > 0" x-cloak
                      class="nav-badge nav-badge-orange"
                      :aria-label="count + ' critical item' + (count === 1 ? '' : 's') + ' need attention'"
                      x-text="count > 99 ? '99+' : count"></span>

                {{-- SSR fallback (no-JS / first render) — hidden once Alpine takes over --}}
                @if($criticalBadge > 0)
                    <span class="nav-badge nav-badge-orange"
                          aria-label="{{ $criticalBadge }} critical item{{ $criticalBadge === 1 ? '' : 's' }} need attention">
                        {{ $criticalBadge > 99 ? '99+' : $criticalBadge }}
                    </span>
                @endif
            </a>
            @endif
        </div>
    </div>

    {{-- ── Data & Reports ──────────────────────────────────────────────────── --}}
    @if($isAdminMgr)
    <div>
        <button @click="toggle('data')"
                :aria-expanded="open.data.toString()"
                aria-controls="nav-data"
                class="nav-group {{ $activeGroup === 'data' ? 'nav-group-active' : '' }}">
            <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <span class="flex-1 text-left">Data & Reports</span>
            <svg class="nav-chevron" :class="open.data ? 'rotate-90' : ''"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
            </svg>
        </button>

        <div id="nav-data"
             x-show="open.data" x-cloak
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="nav-children">

            <a href="{{ route('tenant.imports', $tenantId) }}"
               aria-current="{{ request()->routeIs('tenant.imports*') ? 'page' : 'false' }}"
               @click="window.dispatchEvent(new CustomEvent('sidebar-close'))"
               class="nav-child {{ request()->routeIs('tenant.imports*') ? 'nav-child-active' : '' }}">
                <svg class="nav-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Imports
            </a>

            <a href="{{ route('tenant.exports', $tenantId) }}"
               aria-current="{{ request()->routeIs('tenant.exports*') ? 'page' : 'false' }}"
               @click="window.dispatchEvent(new CustomEvent('sidebar-close'))"
               class="nav-child {{ request()->routeIs('tenant.exports*') ? 'nav-child-active' : '' }}">
                <svg class="nav-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l4 4m0 0l4-4m-4 4V4"/>
                </svg>
                Exports
            </a>

            <a href="{{ route('tenant.reports', $tenantId) }}"
               aria-current="{{ request()->routeIs('tenant.reports') ? 'page' : 'false' }}"
               @click="window.dispatchEvent(new CustomEvent('sidebar-close'))"
               class="nav-child {{ request()->routeIs('tenant.reports') ? 'nav-child-active' : '' }}">
                <svg class="nav-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Reports
            </a>
        </div>
    </div>
    @endif

    {{-- ── Admin ────────────────────────────────────────────────────────────── --}}
    @if($isAdminMgr)
    <div>
        <button @click="toggle('admin')"
                :aria-expanded="open.admin.toString()"
                aria-controls="nav-admin"
                class="nav-group {{ $activeGroup === 'admin' ? 'nav-group-active' : '' }}">
            <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span class="flex-1 text-left">Admin</span>
            <svg class="nav-chevron" :class="open.admin ? 'rotate-90' : ''"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
            </svg>
        </button>

        <div id="nav-admin"
             x-show="open.admin" x-cloak
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="nav-children">

            <a href="{{ route('tenant.users', $tenantId) }}"
               aria-current="{{ request()->routeIs('tenant.users') ? 'page' : 'false' }}"
               @click="window.dispatchEvent(new CustomEvent('sidebar-close'))"
               class="nav-child {{ request()->routeIs('tenant.users') ? 'nav-child-active' : '' }}">
                <svg class="nav-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Users & Roles
            </a>

            <a href="{{ route('tenant.agreements', $tenantId) }}"
               aria-current="{{ request()->routeIs('tenant.agreements') ? 'page' : 'false' }}"
               @click="window.dispatchEvent(new CustomEvent('sidebar-close'))"
               class="nav-child {{ request()->routeIs('tenant.agreements') ? 'nav-child-active' : '' }}">
                <svg class="nav-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Agreements
            </a>

            <a href="{{ route('tenant.billing', $tenantId) }}"
               aria-current="{{ request()->routeIs('tenant.billing') ? 'page' : 'false' }}"
               @click="window.dispatchEvent(new CustomEvent('sidebar-close'))"
               class="nav-child {{ request()->routeIs('tenant.billing') ? 'nav-child-active' : '' }}">
                <svg class="nav-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                </svg>
                Billing & Usage
            </a>

            <a href="{{ route('tenant.settings', $tenantId) }}"
               aria-current="{{ request()->routeIs('tenant.settings') ? 'page' : 'false' }}"
               @click="window.dispatchEvent(new CustomEvent('sidebar-close'))"
               class="nav-child {{ request()->routeIs('tenant.settings') ? 'nav-child-active' : '' }}">
                <svg class="nav-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Settings
            </a>
        </div>
    </div>
    @endif

    {{-- ── Back to Platform — super admin only ───────────────────────────── --}}
    @if(auth('web')->check())
    <div class="mt-2 pt-3 border-t border-white/10">
        <a href="{{ route('platform.dashboard') }}"
           class="nav-top opacity-50 hover:opacity-100 text-xs">
            <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back to Platform
        </a>
    </div>
    @endif

</div>

{{-- ── Critical Actions Badge Poller ─────────────────────────────────────────
     Polls /tenant/{id}/critical-actions/badge every 60 s (only when tab is
     visible). Exposes count, loading, and fetchError to the nav item above.
     On success fires 'critical-badge:updated' so the Workspace group button
     can update its own aggregate badge without a full page reload.
     ─────────────────────────────────────────────────────────────────────── --}}
<script>
function criticalBadgePoller(badgeUrl, initialCount, onActionsPage) {
    return {
        count:      initialCount,
        hasUrgent:  false,
        loading:    false,
        fetchError: false,
        _timer:     null,
        _visHandler: null,

        async fetchCount() {
            // If the user is on the critical-actions page the server already cleared the
            // badge. Skip the fetch to avoid showing a stale cached count.
            if (onActionsPage) { this.count = 0; this.hasUrgent = false; return; }

            this.loading = true;
            try {
                const res  = await fetch(badgeUrl, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!res.ok) { this.fetchError = true; return; }
                const data = await res.json();
                if (data.error) {
                    this.fetchError = true;
                } else {
                    this.count      = typeof data.count === 'number' ? data.count : 0;
                    this.hasUrgent  = !!data.has_urgent;
                    this.fetchError = false;
                    // Broadcast so the Workspace group button can update its aggregate
                    window.dispatchEvent(new CustomEvent('critical-badge:updated', {
                        detail: { count: this.count, hasUrgent: this.hasUrgent }
                    }));
                }
            } catch (e) {
                this.fetchError = true;
            } finally {
                this.loading = false;
            }
        },

        init() {
            // On first load the SSR value is authoritative — no extra fetch needed.
            // Poll every 60 s; only when tab is visible to avoid background flooding.
            // Polling is skipped entirely when already on the critical-actions page.
            if (!onActionsPage) {
                this._timer = setInterval(() => {
                    if (!document.hidden) this.fetchCount();
                }, 60_000);

                // Re-fetch when the user switches back to this tab.
                // Store the handler so Alpine can remove it on destroy() to prevent leaks.
                this._visHandler = () => { if (!document.hidden) this.fetchCount(); };
                document.addEventListener('visibilitychange', this._visHandler);
            }

            // Re-fetch when "Mark all as seen" clears the server cache
            window.addEventListener('critical-badge:cleared', () => {
                this.count      = 0;
                this.hasUrgent  = false;
                this.fetchError = false;
            });
        },

        destroy() {
            // Clean up timers and listeners to prevent memory leaks on SPA navigation.
            if (this._timer)      { clearInterval(this._timer); this._timer = null; }
            if (this._visHandler) { document.removeEventListener('visibilitychange', this._visHandler); this._visHandler = null; }
        },
    };
}
</script>
