<!DOCTYPE html>
<html lang="en" x-data="{ sidebarOpen: false }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Partner Portal') — ReferralBunny.ai</title>
    <link rel="icon" type="image/webp" href="/images/logos/referralbunny-favicon.webp">
    @include('partials.google-analytics')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @php
        $_ptBrandLogoUrl = null; $_ptBrandAccent = null;
        $_ptBrandSidebar = null; $_ptBrandName   = null;
        try {
            $_ptUser = auth('partner')->user();
            if ($_ptUser?->tenant_id) {
                $_ptTenant = \App\Models\Tenant::find($_ptUser->tenant_id);
                $_ptBrand  = \App\Models\TenantBrandProfile::where('tenant_id', $_ptUser->tenant_id)
                                 ->where('status', 'published')->first();
                $_ptSafeHex      = fn(?string $v) => preg_match('/^#[0-9A-Fa-f]{6}$/', (string)$v) ? $v : null;
                $_ptBrandLogoUrl = $_ptBrand?->logo_url ?? $_ptTenant?->logo_url;
                $_ptBrandAccent  = $_ptSafeHex($_ptBrand?->accent_color ?? $_ptTenant?->accent_color);
                $_ptBrandSidebar = $_ptSafeHex($_ptBrand?->sidebar_color);
                $_ptBrandName    = $_ptTenant?->program_name;
            }
        } catch (\Throwable) {}
    @endphp
    @if($_ptBrandAccent || $_ptBrandSidebar)
    <style>
        @if($_ptBrandAccent):root { --color-brand: {{ $_ptBrandAccent }}; }@endif
        @if($_ptBrandSidebar).pt-sidebar { background: {{ $_ptBrandSidebar }} !important; }@endif
    </style>
    @endif
    <style>
        .pt-sidebar { background: #1A2F50; }
        .pt-sidebar-link {
            display:flex;align-items:center;gap:.75rem;padding:.625rem 1rem;border-radius:.75rem;
            font-size:.875rem;font-weight:500;color:rgba(255,255,255,.65);
            text-decoration:none;transition:all .15s;
        }
        .pt-sidebar-link:hover { background:rgba(255,255,255,.1); color:#fff; }
        .pt-sidebar-link.active { background:rgba(255,255,255,.12); color:#fff; border-left:3px solid #3B82F6; }
        .pt-btn-primary { background:#2563EB;color:#fff;border:none;border-radius:.75rem;padding:.625rem 1rem;font-size:.875rem;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:.5rem;transition:background .15s;font-family:'Inter',sans-serif; }
        .pt-btn-primary:hover { background:#1D4ED8; }
        .pt-page-bg { background:#F0F4FF; }
    </style>
</head>
<body class="pt-page-bg font-sans antialiased" data-stitch-page="@yield('stitch_page', 'partner-dashboard')" data-stitch-fallback="partner-dashboard">

<div class="flex h-screen overflow-hidden">

    {{-- Mobile backdrop --}}
    <div x-show="sidebarOpen" @click="sidebarOpen = false"
         class="fixed inset-0 bg-black/50 z-20 lg:hidden" x-cloak></div>

    {{-- Sidebar --}}
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
           class="pt-sidebar fixed inset-y-0 left-0 z-30 w-64 flex flex-col transition-transform duration-200 lg:relative lg:translate-x-0">

        {{-- Logo --}}
        <a href="{{ route('partner.dashboard') }}"
           class="flex items-center gap-3 px-5 py-4 border-b border-white/10 hover:bg-white/5 transition-colors">
            @if($_ptBrandLogoUrl)
            <img src="{{ $_ptBrandLogoUrl }}" alt="Portal logo"
                 class="w-8 h-8 rounded object-contain shrink-0 bg-white/10 p-0.5">
            @else
            <x-rb-logo variant="icon" size="sm" :priority="true" :decorative="true" class="shrink-0" />
            @endif
            <div class="flex-1 min-w-0">
                <p class="text-white text-sm font-semibold leading-none tracking-tight">
                    {{ $_ptBrandName ?? 'referralbunny.ai' }}
                </p>
                <p class="text-white/50 text-xs mt-0.5 truncate">Partner Portal</p>
            </div>
            <button type="button" @click.prevent="sidebarOpen = false" class="ml-auto lg:hidden text-white/50 hover:text-white shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </a>

        {{-- Nav --}}
        <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
            @yield('nav')
        </nav>

        {{-- Bottom profile --}}
        @php
            $p           = auth('partner')->user() ?? ($partner ?? null);
            $displayName = $p ? \App\Services\UserDisplayNameService::resolve($p, false, 'partner') : 'Partner';
            $photoUrl    = $p ? \App\Services\UserDisplayNameService::photoUrl($p) : null;
            $initials    = $p ? \App\Services\UserDisplayNameService::initials($p) : 'P';
        @endphp
        <div class="px-4 py-4 border-t border-white/10">
            <a href="{{ route('partner.profile') }}"
               class="flex items-center gap-3 group">
                @if($photoUrl)
                    <img src="{{ $photoUrl }}" alt="Photo"
                         class="w-8 h-8 rounded-full object-cover shrink-0 ring-2 ring-white/20">
                @else
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0"
                         style="background: linear-gradient(135deg, #3B82F6, #2563EB)">
                        {{ $initials }}
                    </div>
                @endif
                <div class="flex-1 min-w-0">
                    <p class="text-white text-sm font-medium truncate leading-tight group-hover:text-blue-300 transition-colors">{{ $displayName }}</p>
                    <p class="text-white/40 text-[10px] truncate mt-0.5">Partner · My Profile</p>
                </div>
                <form action="{{ route('partner.logout') }}" method="POST" @click.stop>
                    @csrf
                    <button type="submit" class="text-white/40 hover:text-white transition-colors" title="Logout">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </button>
                </form>
            </a>
        </div>
    </aside>

    {{-- Main --}}
    <div class="flex-1 flex flex-col overflow-hidden min-w-0">

        {{-- Topbar --}}
        <header class="bg-white border-b border-gray-100 px-4 lg:px-6 h-14 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <button type="button" @click="sidebarOpen = true" class="lg:hidden p-2 rounded-xl hover:bg-gray-100 text-gray-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <h1 class="text-sm font-semibold" style="color:#1E1B4B">@yield('title', 'Dashboard')</h1>
            </div>
            <div class="flex items-center gap-2">
                @yield('topbar-actions')

                {{-- Notification bell --}}
                <a href="{{ route('partner.notifications') }}"
                   class="relative p-2 rounded-xl hover:bg-gray-100 text-gray-500 transition-colors"
                   x-data="{ count: 0, _timer: null }"
                   x-init="
                       const hdrs = {'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]')?.content??''};
                       const load = () => fetch('/api/notifications/mine/unread-count',{credentials:'same-origin',headers:hdrs}).then(r=>r.json()).then(d=>count=d.count??0).catch(()=>{});
                       load();
                       _timer = setInterval(() => { if (!document.hidden) load(); }, 90000);
                       document.addEventListener('visibilitychange', () => { if (!document.hidden) load(); });
                       window.addEventListener('notifications:updated', (e) => { if (typeof e.detail?.unreadCount === 'number') count = e.detail.unreadCount; });
                   "
                   @click.prevent="
                       count = 0;
                       window.dispatchEvent(new CustomEvent('notifications:updated', { detail: { unreadCount: 0 } }));
                       fetch('/api/notifications/mine/mark-all-read', { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '', 'X-Requested-With': 'XMLHttpRequest' } });
                       setTimeout(() => { window.location.href = $el.href; }, 150);
                   "
                   :aria-label="'Notifications' + (count > 0 ? ` (${count} unread)` : '')"
                   title="Notifications">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <span x-show="count > 0" x-cloak x-text="count > 9 ? '9+' : count"
                          class="absolute -top-0.5 -right-0.5 min-w-[1.1rem] h-[1.1rem] px-0.5 bg-blue-500 rounded-full text-white text-[10px] font-bold flex items-center justify-center leading-none"></span>
                </a>
            </div>
        </header>

        {{-- Content --}}
        <main class="flex-1 overflow-y-auto p-4 lg:p-6">
            @yield('content')
        </main>
    </div>
</div>

{{-- Bridge PHP session flash → Alpine toast --}}
@if (session('success'))
<script>
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'success', message: @json(session('success')) } })), 200);
    });
</script>
@endif

{{-- Toast --}}
<div x-data="{ toasts: [], add(d){ const id=Date.now()+Math.random(); this.toasts.push({...d,id}); setTimeout(()=>this.remove(id), d.duration||4000); }, remove(id){ this.toasts=this.toasts.filter(t=>t.id!==id); } }"
     @show-toast.window="add($event.detail)"
     aria-live="polite"
     aria-atomic="true"
     class="fixed bottom-5 right-4 left-4 sm:left-auto sm:right-5 z-[200] flex flex-col gap-2 items-end pointer-events-none"
     style="max-width:min(360px, 100%)">
    <template x-for="toast in toasts" :key="toast.id">
        <div class="pointer-events-auto flex items-center gap-3 w-full px-4 py-3.5 rounded-2xl shadow-2xl text-sm font-medium"
             role="alert"
             :class="{
                 'bg-emerald-600 text-white': toast.type === 'success',
                 'bg-red-600 text-white':     toast.type === 'error',
                 'bg-orange-500 text-white':  toast.type === 'warning',
                 'bg-[#1A2F50] text-white':   toast.type === 'info',
             }"
             x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="opacity-0 translate-y-3"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-x-0"
             x-transition:leave-end="opacity-0 translate-x-4">
            <div class="shrink-0">
                <svg x-show="toast.type==='success'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                <svg x-show="toast.type==='error'"   class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                <svg x-show="toast.type==='warning'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <svg x-show="toast.type==='info'"    class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <span class="flex-1 leading-relaxed" x-text="toast.message"></span>
            <button type="button" @click="remove(toast.id)" class="shrink-0 opacity-60 hover:opacity-100 transition-opacity ml-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </template>
</div>

{{-- R Bunny Onboarding Walkthrough (modal, fires on first sign-in) --}}
<x-brand.onboarding-walkthrough />
{{-- R Bunny Unified Assistant — bottom-left --}}
<x-brand.r-bunny-assistant />

{{-- Notification detail modal removed — was causing persistent gray overlay --}}

@stack('scripts')
</body>
</html>
