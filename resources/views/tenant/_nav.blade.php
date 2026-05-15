@php
// ── Role resolution ────────────────────────────────────────────────────────
$tenantId = $tenant->id;

if (auth('web')->check()) {
    $navRole = 'super_admin';
} elseif (request()->attributes->has('_tenant_role')) {
    $navRole = request()->attributes->get('_tenant_role');
} elseif (auth('tenant')->check()) {
    $navRole = \App\Models\TenantMembership::where('tenant_user_id', auth('tenant')->id())
        ->where('tenant_id', $tenantId)
        ->where('status', 'active')
        ->value('role') ?? 'viewer';
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

    if ($isAdminMgr) {
        // Admins/managers: count ALL new open tasks in tenant since last visit
        $taskQ = \Illuminate\Support\Facades\DB::table('tasks')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['completed', 'cancelled', 'archived']);
        if ($_taskLastSeen) {
            $taskQ->where('created_at', '>', $_taskLastSeen);
        }
        $taskBadge = (int) $taskQ->count();
    } else {
        // Non-admins: count only tasks assigned to them since last visit
        $taskQ = \Illuminate\Support\Facades\DB::table('tasks')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('assigned_to_type', $_navActorType)
            ->where('assigned_to_id', $_navActorId)
            ->whereNotIn('status', ['completed', 'cancelled', 'archived']);
        if ($_taskLastSeen) {
            $taskQ->where('created_at', '>', $_taskLastSeen);
        }
        $taskBadge = (int) $taskQ->count();
    }

    // Reset badge when user is on the tasks page
    if (request()->routeIs('tenant.tasks*')) {
        session([$_taskSeenKey => now()->toIso8601String()]);
        $taskBadge = 0;
    }
} catch (\Throwable) {}

try {
    $msgBadge = (int) \Illuminate\Support\Facades\DB::table('message_threads')
            ->where('tenant_id', $tenantId)->where('admin_unread', '>', 0)->count()
        + (int) \Illuminate\Support\Facades\DB::table('partner_threads')
            ->where('tenant_id', $tenantId)->where('admin_unread', '>', 0)->count();
} catch (\Throwable) {}

// Note: $criticalBadge is added to workspaceBadge after it's computed below
$workspaceBadge = $taskBadge + $msgBadge;

// Critical actions badge — count all action_needed=true items.
// Cached per tenant for 2 min so the full service isn't called on every page load.
$criticalBadge = 0;
if ($isAdminMgr) {
    try {
        $_caUserId   = auth('tenant')->id() ?? auth('web')->id();
        $_caBadgeKey = "ca_badge_{$tenantId}";
        $criticalBadge = (int) \Illuminate\Support\Facades\Cache::remember($_caBadgeKey, 120, function () use ($tenantId) {
            $actions = app(\App\Services\CriticalActionService::class)->dashboardSummary($tenantId, 100);
            return count(array_filter($actions, fn($a) => !empty($a['action_needed'])));
        });
        // Clear badge cache when admin visits the critical actions page (they "see" them)
        if (request()->routeIs('tenant.critical-actions')) {
            \Illuminate\Support\Facades\Cache::forget($_caBadgeKey);
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
    <div>
        <button @click="toggle('workspace')"
                :aria-expanded="open.workspace.toString()"
                aria-controls="nav-workspace"
                class="nav-group {{ $activeGroup === 'workspace' ? 'nav-group-active' : '' }}">
            <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
            </svg>
            <span class="flex-1 text-left">Workspace</span>
            @if($workspaceBadge > 0)
                <span class="nav-badge nav-badge-orange mr-1"
                      aria-label="{{ $workspaceBadge }} item{{ $workspaceBadge === 1 ? '' : 's' }} need attention">
                    {{ $workspaceBadge > 99 ? '99+' : $workspaceBadge }}
                </span>
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
            <a href="{{ route('tenant.critical-actions', $tenantId) }}"
               aria-current="{{ request()->routeIs('tenant.critical-actions') ? 'page' : 'false' }}"
               @click="window.dispatchEvent(new CustomEvent('sidebar-close'))"
               class="nav-child {{ request()->routeIs('tenant.critical-actions') ? 'nav-child-active' : '' }}">
                <svg class="nav-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span class="flex-1">Critical Actions</span>
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
