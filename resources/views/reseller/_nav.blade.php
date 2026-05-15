@php
    $tid = $tenant->id;

    // Unread messages — best-effort, never blocks render
    try {
        $_rsThread = \App\Models\MessageThread::where('tenant_id', $tid)
            ->where('reseller_id', auth('reseller')->id())
            ->select('reseller_unread')
            ->first();
        $_rsUnread = (int) ($_rsThread?->reseller_unread ?? 0);
    } catch (\Throwable) {
        $_rsUnread = 0;
    }

    // New activity badge — count events since last time this reseller visited the activity log
    $_activityBadge = 0;
    if (!request()->routeIs('reseller.activity')) {
        try {
            $_rsId      = auth('reseller')->id();
            $_lastSeen  = session("rs_activity_seen_{$_rsId}", now()->subHours(24)->toIso8601String());
            $_leadIds   = \Illuminate\Support\Facades\DB::table('leads')
                ->where('tenant_id', $tid)
                ->whereRaw('LOWER(reseller_name) = ?', [strtolower(auth('reseller')->user()?->name ?? '')])
                ->pluck('id')
                ->map(fn($x) => (string) $x)
                ->toArray();

            if (count($_leadIds) > 0) {
                $_lhNew = (int) \Illuminate\Support\Facades\DB::table('lead_history')
                    ->where('tenant_id', $tid)
                    ->whereIn('lead_id', $_leadIds)
                    ->where('created_at', '>', $_lastSeen)
                    ->count();

                $_alNew = (int) \Illuminate\Support\Facades\DB::table('activity_logs')
                    ->where('tenant_id', $tid)
                    ->where(fn($q) =>
                        $q->where(fn($q2) => $q2->where('entity', 'reseller')->where('entity_id', (string) $_rsId))
                          ->orWhere(fn($q2) => $q2->where('entity', 'lead')->whereIn('entity_id', $_leadIds))
                    )
                    ->where('created_at', '>', $_lastSeen)
                    ->count();

                $_activityBadge = min($_lhNew + $_alNew, 99);
            }
        } catch (\Throwable) {
            $_activityBadge = 0;
        }
    } else {
        // Visiting the activity log — reset the seen timestamp
        session(["rs_activity_seen_" . auth('reseller')->id() => now()->toIso8601String()]);
    }

    // Open task badge for this reseller
    $_taskBadge = 0;
    if (!request()->routeIs('reseller.tasks')) {
        try {
            $_rsId2 = auth('reseller')->id();
            $_taskBadge = (int) \App\Models\Task::where('tenant_id', $tid)
                ->where('assigned_to_type', 'reseller')
                ->where('assigned_to_id', $_rsId2)
                ->whereNull('deleted_at')
                ->whereIn('status', ['open', 'in_progress', 'waiting'])
                ->count();
            $_taskBadge = min($_taskBadge, 99);
        } catch (\Throwable) {
            $_taskBadge = 0;
        }
    }

    // Active-group detection
    $_networkActive = request()->routeIs('reseller.partners*')
                   || request()->routeIs('reseller.contacts*');

    $_commsActive   = request()->routeIs('reseller.request-forms')
                   || request()->routeIs('reseller.messages');

    $_dealsActive   = request()->routeIs('reseller.deals')
                   || request()->routeIs('reseller.deals.show')
                   || request()->routeIs('reseller.deals.imports*');

    $_contactsActive = request()->routeIs('reseller.contacts')
                    || request()->routeIs('reseller.contacts.imports*');
@endphp

{{-- ── Dashboard ────────────────────────────────────────────── --}}
<a href="{{ route('reseller.dashboard', $tid) }}"
   class="rs-sidebar-link {{ request()->routeIs('reseller.dashboard') ? 'active' : '' }}"
   @if(request()->routeIs('reseller.dashboard')) aria-current="page" @endif>
    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
    </svg>
    Dashboard
</a>

{{-- ── My Deals + Import Deals ─────────────────────────────── --}}
<a href="{{ route('reseller.deals', $tid) }}"
   class="rs-sidebar-link {{ $_dealsActive ? 'active' : '' }}"
   @if($_dealsActive) aria-current="page" @endif>
    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
    </svg>
    My Deals
</a>

<div class="ml-3 pl-3 border-l border-white/10 space-y-0.5">
    <a href="{{ route('reseller.deals.imports', $tid) }}"
       class="rs-sidebar-child {{ request()->routeIs('reseller.deals.imports*') ? 'active' : '' }}"
       @if(request()->routeIs('reseller.deals.imports*')) aria-current="page" @endif>
        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
        </svg>
        My Imported Deals
    </a>
</div>

{{-- ── My Commission ───────────────────────────────────────── --}}
<a href="{{ route('reseller.commission', $tid) }}"
   class="rs-sidebar-link {{ request()->routeIs('reseller.commission') ? 'active' : '' }}"
   @if(request()->routeIs('reseller.commission')) aria-current="page" @endif>
    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    My Commission
</a>

{{-- ── My Network (expandable, defaults open so My Contacts is always reachable) --}}
<div x-data="{
        open: false,
        init() {
            const saved = localStorage.getItem('rs_nav_network');
            // Default to open so My Contacts and My Partners are always visible
            this.open = saved !== null ? (saved === 'true') : true;
            this.$watch('open', v => localStorage.setItem('rs_nav_network', String(v)));
        }
     }">

    <button type="button"
            @click="open = !open"
            :aria-expanded="String(open)"
            aria-controls="rs-network-panel"
            class="rs-sidebar-link w-full text-left {{ $_networkActive ? 'rs-group-active' : '' }}">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
        </svg>
        <span class="flex-1">My Network</span>
        <svg class="w-3 h-3 shrink-0 transition-transform duration-200 ml-auto opacity-50"
             :class="open ? 'rotate-180' : ''"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div id="rs-network-panel"
         role="group"
         x-show="open"
         x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0"
         class="mt-0.5 ml-3 pl-3 border-l border-white/10 space-y-0.5">

        <a href="{{ route('reseller.partners', $tid) }}"
           class="rs-sidebar-child {{ request()->routeIs('reseller.partners*') ? 'active' : '' }}"
           @if(request()->routeIs('reseller.partners*')) aria-current="page" @endif>
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
            My Partners
        </a>

        <a href="{{ route('reseller.contacts', $tid) }}"
           class="rs-sidebar-child {{ $_contactsActive ? 'active' : '' }}"
           @if($_contactsActive) aria-current="page" @endif>
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            My Contacts
        </a>

        <a href="{{ route('reseller.contacts.imports', $tid) }}"
           class="rs-sidebar-child {{ request()->routeIs('reseller.contacts.imports*') ? 'active' : '' }}"
           @if(request()->routeIs('reseller.contacts.imports*')) aria-current="page" @endif>
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
            My Imported Contacts
        </a>

    </div>
</div>

{{-- ── Requests & Messages (expandable) ────────────────────── --}}
<div x-data="{
        open: false,
        init() {
            const saved = localStorage.getItem('rs_nav_comms');
            this.open = saved !== null ? (saved === 'true') : {{ $_commsActive ? 'true' : 'false' }};
            this.$watch('open', v => localStorage.setItem('rs_nav_comms', String(v)));
        }
     }">

    <button type="button"
            @click="open = !open"
            :aria-expanded="String(open)"
            aria-controls="rs-comms-panel"
            class="rs-sidebar-link w-full text-left {{ $_commsActive ? 'rs-group-active' : '' }}">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
        </svg>
        <span class="flex-1">Requests &amp; Messages</span>
        @if($_rsUnread > 0)
            <span class="min-w-[1.1rem] h-[1.1rem] px-0.5 rounded-full text-white text-[10px] font-bold flex items-center justify-center leading-none shrink-0"
                  style="background:#14B8A6"
                  aria-label="{{ $_rsUnread }} unread {{ $_rsUnread === 1 ? 'message' : 'messages' }}">
                {{ $_rsUnread > 9 ? '9+' : $_rsUnread }}
            </span>
        @endif
        <svg class="w-3 h-3 shrink-0 transition-transform duration-200 opacity-50 {{ $_rsUnread > 0 ? '' : 'ml-auto' }}"
             :class="open ? 'rotate-180' : ''"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div id="rs-comms-panel"
         role="group"
         x-show="open"
         x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0"
         class="mt-0.5 ml-3 pl-3 border-l border-white/10 space-y-0.5">

        <a href="{{ route('reseller.request-forms', $tid) }}"
           class="rs-sidebar-child {{ request()->routeIs('reseller.request-forms') ? 'active' : '' }}"
           @if(request()->routeIs('reseller.request-forms')) aria-current="page" @endif>
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 17h.01"/>
            </svg>
            Request Forms
        </a>

        <a href="{{ route('reseller.messages', $tid) }}"
           class="rs-sidebar-child {{ request()->routeIs('reseller.messages') ? 'active' : '' }}"
           @if(request()->routeIs('reseller.messages')) aria-current="page" @endif>
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
            </svg>
            Messages
            @if($_rsUnread > 0)
                <span class="ml-auto min-w-[1.1rem] h-[1.1rem] px-0.5 rounded-full text-white text-[10px] font-bold flex items-center justify-center leading-none"
                      style="background:#14B8A6"
                      aria-label="{{ $_rsUnread }} unread">
                    {{ $_rsUnread > 9 ? '9+' : $_rsUnread }}
                </span>
            @endif
        </a>

    </div>
</div>

{{-- ── My Tasks ─────────────────────────────────────────────── --}}
<a href="{{ route('reseller.tasks', $tid) }}"
   class="rs-sidebar-link {{ request()->routeIs('reseller.tasks') ? 'active' : '' }}"
   @if(request()->routeIs('reseller.tasks')) aria-current="page" @endif>
    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 17h.01"/>
    </svg>
    <span class="flex-1">My Tasks</span>
    @if($_taskBadge > 0)
        <span class="min-w-[1.1rem] h-[1.1rem] px-0.5 rounded-full text-white text-[10px] font-bold flex items-center justify-center leading-none shrink-0"
              style="background:#7B61FF"
              aria-label="{{ $_taskBadge }} open {{ $_taskBadge === 1 ? 'task' : 'tasks' }}">
            {{ $_taskBadge > 9 ? '9+' : $_taskBadge }}
        </span>
    @endif
</a>

{{-- ── Calendar ─────────────────────────────────────────────── --}}
<a href="{{ route('reseller.calendar', $tid) }}"
   class="rs-sidebar-link {{ request()->routeIs('reseller.calendar*') ? 'active' : '' }}"
   @if(request()->routeIs('reseller.calendar*')) aria-current="page" @endif>
    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
    </svg>
    Calendar
</a>

{{-- ── Activity Log ─────────────────────────────────────────── --}}
<a href="{{ route('reseller.activity', $tid) }}"
   class="rs-sidebar-link {{ request()->routeIs('reseller.activity') ? 'active' : '' }}"
   @if(request()->routeIs('reseller.activity')) aria-current="page" @endif>
    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
    </svg>
    <span class="flex-1">Activity Log</span>
    @if($_activityBadge > 0)
        <span class="min-w-[1.1rem] h-[1.1rem] px-0.5 rounded-full text-white text-[10px] font-bold flex items-center justify-center leading-none shrink-0"
              style="background:#0D9488"
              aria-label="{{ $_activityBadge }} new {{ $_activityBadge === 1 ? 'event' : 'events' }}">
            {{ $_activityBadge > 9 ? '9+' : $_activityBadge }}
        </span>
    @endif
</a>

{{-- ── Divider ──────────────────────────────────────────────── --}}
<div class="mx-1 my-2 border-t border-white/10" role="separator"></div>

{{-- ── Profile ──────────────────────────────────────────────── --}}
<a href="{{ route('reseller.profile', $tid) }}"
   class="rs-sidebar-link {{ request()->routeIs('reseller.profile') ? 'active' : '' }}"
   @if(request()->routeIs('reseller.profile')) aria-current="page" @endif>
    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
    </svg>
    Profile
</a>
