<!DOCTYPE html>
<html lang="en" x-data="{ sidebarOpen: false }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Referrer Portal') — ReferralBunny.ai</title>
    <link rel="icon" type="image/webp" href="/images/logos/referralbunny-favicon.webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .rs-sidebar { background: #0C3D38; }
        .rs-sidebar-link {
            display:flex;align-items:center;gap:.75rem;padding:.625rem 1rem;border-radius:.75rem;
            font-size:.875rem;font-weight:500;color:rgba(255,255,255,.65);
            text-decoration:none;transition:all .15s;
        }
        .rs-sidebar-link:hover { background:rgba(255,255,255,.1); color:#fff; }
        .rs-sidebar-link.active { background:rgba(255,255,255,.12); color:#fff; border-left:3px solid #14B8A6; }
        .rs-btn-primary { background:#0D9488;color:#fff;border:none;border-radius:.75rem;padding:.625rem 1rem;font-size:.875rem;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:.5rem;transition:background .15s;font-family:'Inter',sans-serif; }
        .rs-btn-primary:hover { background:#0F766E; }
        .rs-page-bg { background:#F0FDFA; }
    </style>
</head>
<body class="rs-page-bg font-sans antialiased">

<div class="flex h-screen overflow-hidden">

    {{-- Mobile backdrop --}}
    <div x-show="sidebarOpen" @click="sidebarOpen = false"
         class="fixed inset-0 bg-black/50 z-20 lg:hidden" x-cloak></div>

    {{-- Sidebar --}}
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
           class="rs-sidebar fixed inset-y-0 left-0 z-30 w-64 flex flex-col transition-transform duration-200 lg:relative lg:translate-x-0">

        {{-- Logo --}}
        <a href="{{ route('reseller.dashboard', $tenant->id) }}"
           class="flex items-center gap-3 px-5 py-4 border-b border-white/10 hover:bg-white/5 transition-colors">
            <x-rb-logo variant="icon" size="sm" :priority="true" :decorative="true" class="shrink-0" />
            <div class="flex-1 min-w-0">
                <p class="text-white text-sm font-semibold leading-none tracking-tight">referralbunny.ai</p>
                <p class="text-white/50 text-xs mt-0.5 truncate">{{ $tenant->name }}</p>
            </div>
            <button @click.prevent="sidebarOpen = false" class="ml-auto lg:hidden text-white/50 hover:text-white shrink-0">
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
            $r           = auth('reseller')->user() ?? ($reseller ?? null);
            $displayName = $r ? (\App\Services\UserDisplayNameService::resolve($r, false, 'referrer')) : 'Referrer';
            $photoUrl    = $r?->profile_photo_path ? asset('storage/'.$r->profile_photo_path) : null;
            $initials    = $r ? \App\Services\UserDisplayNameService::initials($r, false) : 'R';
        @endphp
        <div class="px-4 py-4 border-t border-white/10">
            <a href="{{ isset($tenant) ? route('reseller.profile', $tenant->id) : '#' }}"
               class="flex items-center gap-3 group">
                @if($photoUrl)
                    <img src="{{ $photoUrl }}" alt="Photo"
                         class="w-8 h-8 rounded-full object-cover shrink-0 ring-2 ring-white/20">
                @else
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0"
                         style="background: linear-gradient(135deg, #14B8A6, #0D9488)">
                        {{ $initials }}
                    </div>
                @endif
                <div class="flex-1 min-w-0">
                    <p class="text-white text-sm font-medium truncate leading-tight group-hover:text-teal-300 transition-colors">{{ $displayName }}</p>
                    <p class="text-white/40 text-[10px] truncate mt-0.5">Referrer · My Profile</p>
                </div>
                <form action="{{ route('reseller.logout') }}" method="POST" @click.stop>
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
                <button @click="sidebarOpen = true" class="lg:hidden p-2 rounded-xl hover:bg-gray-100 text-gray-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <h1 class="text-sm font-semibold" style="color:#1E1B4B">@yield('title', 'Dashboard')</h1>
            </div>
            <div class="flex items-center gap-2">
                @yield('topbar-actions')

                {{-- Notification bell --}}
                @if(isset($tenant))
                <a href="{{ route('reseller.notifications', $tenant->id) }}"
                   class="relative p-2 rounded-xl hover:bg-gray-100 text-gray-500 transition-colors"
                   x-data="{ count: 0, _timer: null }"
                   x-init="
                       const hdrs = {'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]')?.content??''};
                       const load = () => fetch('/api/notifications/mine/unread-count',{credentials:'same-origin',headers:hdrs}).then(r=>r.json()).then(d=>count=d.count??0).catch(()=>{});
                       load();
                       _timer = setInterval(load, 90000);
                       document.addEventListener('visibilitychange', () => { if (!document.hidden) load(); });
                       window.addEventListener('notifications:updated', (e) => { if (typeof e.detail?.unreadCount === 'number') count = e.detail.unreadCount; });
                   "
                   :aria-label="'Notifications' + (count > 0 ? ` (${count} unread)` : '')"
                   title="Notifications">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <span x-show="count > 0" x-text="count > 9 ? '9+' : count"
                          class="absolute -top-0.5 -right-0.5 min-w-[1.1rem] h-[1.1rem] px-0.5 bg-teal-500 rounded-full text-white text-[10px] font-bold flex items-center justify-center leading-none"></span>
                </a>
                @endif
            </div>
        </header>

        {{-- Content --}}
        <main class="flex-1 overflow-y-auto p-4 lg:p-6">
            @yield('content')
        </main>
    </div>
</div>

{{-- Toast --}}
<div x-data="{ toasts: [] }"
     @show-toast.window="toasts.push({...$event.detail, _id: Date.now()}); setTimeout(() => toasts.shift(), 4000)"
     aria-live="polite"
     aria-atomic="true"
     class="fixed bottom-5 right-5 z-50 space-y-2 pointer-events-none">
    <template x-for="(t, i) in toasts" :key="t._id ?? i">
        <div class="flex items-center gap-3 px-4 py-3 rounded-2xl shadow-lg text-sm font-medium pointer-events-auto"
             role="alert"
             :class="t.type === 'success' ? 'bg-[#0D9488] text-white' : (t.type === 'warning' ? 'bg-orange-500 text-white' : 'bg-red-500 text-white')"
             x-text="t.message"></div>
    </template>
</div>

{{-- R Bunny Onboarding Walkthrough (modal, fires on first sign-in) --}}
<x-brand.onboarding-walkthrough />
{{-- R Bunny Unified Assistant — bottom-left --}}
<x-brand.r-bunny-assistant />

{{-- Flash → Toast bridge (converts Laravel session flash to Alpine toast) --}}
@if(session('success') || session('error') || session('warning'))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        @if(session('success'))
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'success', message: @js(session('success')) } }));
        @endif
        @if(session('error'))
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'error', message: @js(session('error')) } }));
        @endif
        @if(session('warning'))
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'warning', message: @js(session('warning')) } }));
        @endif
    });
</script>
@endif

@stack('scripts')
</body>
</html>
