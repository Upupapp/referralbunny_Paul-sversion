{{-- Dashboard --}}
<a href="{{ route('partner.dashboard') }}"
   class="pt-sidebar-link {{ request()->routeIs('partner.dashboard') ? 'active' : '' }}">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
    </svg>
    Dashboard
</a>

<p class="px-4 pt-3 pb-1 text-[10px] font-bold text-white/30 uppercase tracking-widest">My Activity</p>

{{-- Deals --}}
<a href="{{ route('partner.deals') }}"
   class="pt-sidebar-link {{ request()->routeIs('partner.deals*') ? 'active' : '' }}">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
    </svg>
    My Deals
</a>

{{-- Messages with unread badge --}}
<a href="{{ route('partner.messages') }}"
   class="pt-sidebar-link {{ request()->routeIs('partner.messages*') ? 'active' : '' }}">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
    </svg>
    <span class="flex-1">Messages</span>
    @if(($partnerUnread ?? 0) > 0)
    <span class="bg-blue-500 text-white text-[9px] font-bold w-4 h-4 rounded-full flex items-center justify-center shrink-0">
        {{ $partnerUnread > 9 ? '9+' : $partnerUnread }}
    </span>
    @endif
</a>

{{-- My Commissions --}}
<a href="{{ route('partner.commissions') }}"
   class="pt-sidebar-link {{ request()->routeIs('partner.commissions*') ? 'active' : '' }}">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    My Commissions
</a>

{{-- Request Forms --}}
<a href="{{ route('partner.forms') }}"
   class="pt-sidebar-link {{ request()->routeIs('partner.forms*') ? 'active' : '' }}">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
    </svg>
    Request Forms
</a>

<div class="mx-4 my-2 border-t border-white/10"></div>

{{-- Profile --}}
<a href="{{ route('partner.profile') }}"
   class="pt-sidebar-link {{ request()->routeIs('partner.profile') ? 'active' : '' }}">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
    </svg>
    Profile
</a>
