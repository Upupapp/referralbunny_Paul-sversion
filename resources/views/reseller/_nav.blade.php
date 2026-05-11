@php
    $tid = $tenant->id;
    try {
        $_rsThread  = \App\Models\MessageThread::where('tenant_id', $tid)
            ->where('reseller_id', auth('reseller')->id())
            ->select('reseller_unread')
            ->first();
        $_rsUnread = (int) ($_rsThread?->reseller_unread ?? 0);
    } catch (\Throwable) {
        $_rsUnread = 0;
    }
@endphp

<a href="{{ route('reseller.dashboard', $tid) }}"
   class="rs-sidebar-link {{ request()->routeIs('reseller.dashboard') ? 'active' : '' }}">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
    Dashboard
</a>

<p class="px-4 pt-3 pb-1 text-[10px] font-bold text-white/30 uppercase tracking-widest">My Activity</p>

<a href="{{ route('reseller.deals', $tid) }}"
   class="rs-sidebar-link pl-7 {{ request()->routeIs('reseller.deals') && !request()->routeIs('reseller.deals.imports*') ? 'active' : '' }}">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
    My Deals
</a>

<a href="{{ route('reseller.deals.imports', $tid) }}"
   class="rs-sidebar-link pl-7 {{ request()->routeIs('reseller.deals.imports*') ? 'active' : '' }}">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
    </svg>
    Import Deals
</a>

<a href="{{ route('reseller.commission', $tid) }}"
   class="rs-sidebar-link pl-7 {{ request()->routeIs('reseller.commission') ? 'active' : '' }}">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    My Commission
</a>

<a href="{{ route('reseller.partners', $tid) }}"
   class="rs-sidebar-link pl-7 {{ request()->routeIs('reseller.partners*') ? 'active' : '' }}">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
    </svg>
    My Partners
</a>

<a href="{{ route('reseller.contacts.imports', $tid) }}"
   class="rs-sidebar-link pl-7 {{ request()->routeIs('reseller.contacts*') ? 'active' : '' }}">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
    </svg>
    My Contacts
</a>

<a href="{{ route('reseller.request-forms', $tid) }}"
   class="rs-sidebar-link pl-7 {{ request()->routeIs('reseller.request-forms') ? 'active' : '' }}">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 17h.01"/>
    </svg>
    Request Forms
</a>

<a href="{{ route('reseller.messages', $tid) }}"
   class="rs-sidebar-link pl-7 {{ request()->routeIs('reseller.messages') ? 'active' : '' }}">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
    Messages
    @if($_rsUnread > 0)
        <span class="ml-auto min-w-[1.1rem] h-[1.1rem] px-0.5 rounded-full text-white text-[10px] font-bold flex items-center justify-center leading-none" style="background:#14B8A6">{{ $_rsUnread > 9 ? '9+' : $_rsUnread }}</span>
    @endif
</a>

<a href="{{ route('reseller.activity', $tid) }}"
   class="rs-sidebar-link pl-7 {{ request()->routeIs('reseller.activity') ? 'active' : '' }}">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
    </svg>
    Activity Log
</a>

<div class="mx-4 my-2 border-t border-white/10"></div>

<a href="{{ route('reseller.profile', $tid) }}"
   class="rs-sidebar-link {{ request()->routeIs('reseller.profile') ? 'active' : '' }}">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
    Profile
</a>
