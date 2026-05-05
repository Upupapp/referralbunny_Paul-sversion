@php $tid = $tenant->id; @endphp

<a href="{{ route('reseller.dashboard', $tid) }}"
   class="rs-sidebar-link {{ request()->routeIs('reseller.dashboard') ? 'active' : '' }}">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
    Dashboard
</a>

<p class="px-4 pt-3 pb-1 text-[10px] font-bold text-white/30 uppercase tracking-widest">My Activity</p>

<a href="{{ route('reseller.deals', $tid) }}"
   class="rs-sidebar-link pl-7 {{ request()->routeIs('reseller.deals') ? 'active' : '' }}">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
    My Deals
</a>

<a href="{{ route('reseller.commission', $tid) }}"
   class="rs-sidebar-link pl-7 {{ request()->routeIs('reseller.commission') ? 'active' : '' }}">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    My Commission
</a>

<a href="{{ route('reseller.messages', $tid) }}"
   class="rs-sidebar-link pl-7 {{ request()->routeIs('reseller.messages') ? 'active' : '' }}">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
    Messages
</a>

<a href="{{ route('reseller.notifications', $tid) }}"
   class="rs-sidebar-link pl-7 {{ request()->routeIs('reseller.notifications') ? 'active' : '' }}"
   x-data="{ count: 0 }" x-init="fetch('/api/notifications/mine/unread-count',{headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(d=>count=d.count??0).catch(()=>{})">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
    <span class="flex-1">Notifications</span>
    <span x-show="count > 0" x-text="count > 9 ? '9+' : count"
          class="text-[9px] font-bold bg-teal-400 text-white rounded-full min-w-[1.1rem] h-[1.1rem] flex items-center justify-center px-0.5"></span>
</a>

<div class="mx-4 my-2 border-t border-white/10"></div>

<a href="{{ route('reseller.profile', $tid) }}"
   class="rs-sidebar-link {{ request()->routeIs('reseller.profile') ? 'active' : '' }}">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
    Profile
</a>
