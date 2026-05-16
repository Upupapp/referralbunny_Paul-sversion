<!DOCTYPE html>
<html lang="en" x-data="{ sidebarOpen: false }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Referrer Portal') — ReferralBunny.ai</title>
    <link rel="icon" type="image/webp" href="/images/logos/referralbunny-favicon.webp">
    @include('partials.google-analytics')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .rs-sidebar { background: #0C3D38; }

        /* ── Top-level nav items — visually dominant ─────────────────── */
        .rs-sidebar-link {
            display:flex;align-items:center;gap:.75rem;
            padding:.625rem 1rem;border-radius:.875rem;
            font-size:.9375rem;font-weight:600;
            color:rgba(255,255,255,.62);
            text-decoration:none;transition:all .15s;
            border-left:3px solid transparent;
        }
        .rs-sidebar-link:hover {
            background:rgba(255,255,255,.09);
            color:rgba(255,255,255,.95);
        }
        .rs-sidebar-link:focus-visible {
            outline:2px solid #14B8A6;
            outline-offset:2px;
        }
        .rs-sidebar-link.active {
            background:rgba(20,184,166,.18);
            color:#fff;
            border-left:3px solid #14B8A6;
        }
        /* Group parent when a child is active (but parent itself not a link) */
        .rs-group-active {
            color:rgba(255,255,255,.92) !important;
            background:rgba(255,255,255,.06);
        }

        /* ── Child nav items — visually secondary ────────────────────── */
        .rs-sidebar-child {
            display:flex;align-items:center;gap:.5rem;
            padding:.375rem .75rem;border-radius:.625rem;
            font-size:.8125rem;font-weight:500;
            color:rgba(255,255,255,.5);
            text-decoration:none;transition:all .15s;
        }
        .rs-sidebar-child:hover {
            background:rgba(255,255,255,.07);
            color:rgba(255,255,255,.88);
        }
        .rs-sidebar-child:focus-visible {
            outline:2px solid #14B8A6;
            outline-offset:2px;
        }
        .rs-sidebar-child.active {
            background:rgba(94,234,212,.12);
            color:#5EEAD4;
            font-weight:600;
        }

        .rs-btn-primary { background:#0D9488;color:#fff;border:none;border-radius:.75rem;padding:.625rem 1rem;font-size:.875rem;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:.5rem;transition:background .15s;font-family:'Inter',sans-serif; }
        .rs-btn-primary:hover { background:#0F766E; }
        .rs-page-bg { background:#F0FDFA; }
        /* Anonymous-mode onboarding animations */
        @keyframes rb-eye-pulse {
            0%,100% { box-shadow:0 0 0 0 rgba(20,184,166,.55); }
            50%      { box-shadow:0 0 0 6px rgba(20,184,166,.0); }
        }
        @keyframes rb-dot-ping {
            0%,100% { opacity:1; transform:scale(1); }
            50%      { opacity:.4; transform:scale(1.5); }
        }
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

                {{-- Anonymous mode toggle + onboarding guide --}}
                @if(isset($tenant) && $r)
                @php $rsOnboardAnon = !($r->anonymous_onboarded_at ?? null); @endphp
                <div x-data="{
                        open: false,
                        isAnon: {{ ($r->is_anonymous ?? false) ? 'true' : 'false' }},
                        busy: false,
                        showGuide: {{ $rsOnboardAnon ? 'true' : 'false' }},
                        async toggle() {
                            this.busy = true;
                            const fd = new FormData();
                            fd.append('_token', document.querySelector('meta[name=csrf-token]').content);
                            fd.append('is_anonymous', this.isAnon ? '0' : '1');
                            try {
                                const res = await fetch('{{ route('reseller.profile.anonymous', $tenant->id) }}', { method: 'POST', body: fd });
                                if (res.ok) {
                                    this.isAnon = !this.isAnon;
                                    this.showGuide = false;
                                    window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'success', message: this.isAnon ? 'Anonymous mode enabled.' : 'Anonymous mode disabled.' } }));
                                    this.open = false;
                                }
                            } catch(e) {}
                            this.busy = false;
                        }
                     }"
                     class="relative"
                     @click.outside="open = false"
                     @keydown.escape.window="open = false">

                    <button @click="open = !open"
                            type="button"
                            :title="isAnon ? 'Anonymous Mode: ON — click to change' : 'Anonymous Mode: OFF — click to enable'"
                            :aria-expanded="open"
                            class="relative p-2 rounded-xl transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-400"
                            :class="isAnon ? 'bg-amber-100 text-amber-600 hover:bg-amber-200' : 'hover:bg-gray-100 text-gray-400 hover:text-gray-600'">
                        {{-- Eye-slash when anonymous --}}
                        <svg x-show="isAnon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                        </svg>
                        {{-- Eye when visible --}}
                        <svg x-show="!isAnon" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        {{-- Amber dot indicator when anonymous --}}
                        <span x-show="isAnon" class="absolute top-0.5 right-0.5 w-2 h-2 bg-amber-500 rounded-full ring-2 ring-white"></span>
                        {{-- Onboarding pulse ring — shown until user clicks the eye --}}
                        <span x-show="showGuide && !isAnon" x-cloak
                              class="absolute inset-0 rounded-xl pointer-events-none"
                              style="box-shadow:0 0 0 0 rgba(20,184,166,.6);animation:rb-eye-pulse 1.8s ease-in-out infinite"></span>
                    </button>

                    {{-- Onboarding guide tooltip --}}
                    <div x-show="showGuide"
                         x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                         class="absolute right-0 z-50"
                         style="top:calc(100% + 10px);width:230px">
                        {{-- Upward arrow --}}
                        <div style="position:absolute;top:-5px;right:10px;width:10px;height:10px;background:#1E1B4B;transform:rotate(45deg);border-radius:2px"></div>
                        <div style="background:#1E1B4B;border-radius:14px;padding:14px 16px;box-shadow:0 8px 28px rgba(0,0,0,.22)">
                            <div style="display:flex;align-items:flex-start;gap:10px;margin-bottom:10px">
                                <div style="width:30px;height:30px;border-radius:8px;background:rgba(20,184,166,.15);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                    <svg style="width:15px;height:15px;color:#14B8A6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p style="font-size:12px;font-weight:700;color:#fff;margin:0 0 3px">Try Anonymous Mode</p>
                                    <p style="font-size:11px;color:rgba(255,255,255,.65);margin:0;line-height:1.5">Click the eye button above to hide your identity from other referrers.</p>
                                </div>
                            </div>
                            <div style="display:flex;align-items:center;gap:6px;padding-top:8px;border-top:1px solid rgba(255,255,255,.1)">
                                <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#14B8A6;animation:rb-dot-ping 1.4s ease-in-out infinite"></span>
                                <p style="font-size:10px;color:rgba(255,255,255,.45);margin:0">Required for all referrers — click the eye once to dismiss</p>
                            </div>
                        </div>
                    </div>

                    {{-- Popover --}}
                    <div x-show="open" x-cloak
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         class="absolute right-0 top-full mt-2 w-64 bg-white rounded-2xl shadow-xl border border-gray-100 p-4 z-50">

                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm font-bold text-[#1E1B4B]">Anonymous Mode</p>
                            <span class="text-[10px] px-2 py-0.5 rounded-full font-bold"
                                  :class="isAnon ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-500'"
                                  x-text="isAnon ? 'ON' : 'OFF'"></span>
                        </div>

                        <p class="text-xs text-gray-500 leading-relaxed mb-3"
                           x-text="isAnon
                               ? 'Your name and photo are hidden from other referrers. Platform admins can still see your identity.'
                               : 'Your profile is visible to other referrers. Enable to hide your name and photo.'">
                        </p>

                        <button @click="toggle()"
                                :disabled="busy"
                                class="w-full px-3 py-2 rounded-xl text-xs font-semibold transition-colors disabled:opacity-50"
                                :class="isAnon ? 'bg-teal-600 text-white hover:bg-teal-700' : 'bg-amber-500 text-white hover:bg-amber-600'"
                                x-text="busy ? 'Saving…' : (isAnon ? 'Disable Anonymous Mode' : 'Enable Anonymous Mode')">
                        </button>
                    </div>
                </div>
                @endif

                {{-- Notification bell --}}
                @if(isset($tenant))
                <a href="{{ route('reseller.notifications', $tenant->id) }}"
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
<div x-data="{ toasts: [], add(d){ const id=Date.now()+Math.random(); this.toasts.push({...d,id}); setTimeout(()=>this.remove(id), d.duration||4000); }, remove(id){ this.toasts=this.toasts.filter(t=>t.id!==id); } }"
     @show-toast.window="add($event.detail)"
     aria-live="polite"
     aria-atomic="true"
     class="fixed bottom-5 right-4 left-4 sm:left-auto sm:right-5 z-[200] flex flex-col gap-2 items-end pointer-events-none"
     style="max-width:min(360px, 100%)"
    <template x-for="toast in toasts" :key="toast.id">
        <div class="pointer-events-auto flex items-center gap-3 w-full px-4 py-3.5 rounded-2xl shadow-2xl text-sm font-medium"
             role="alert"
             :class="{
                 'bg-emerald-600 text-white': toast.type === 'success',
                 'bg-red-600 text-white':     toast.type === 'error',
                 'bg-orange-500 text-white':  toast.type === 'warning',
                 'bg-[#0D9488] text-white':   toast.type === 'info',
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
            <button @click="remove(toast.id)" class="shrink-0 opacity-60 hover:opacity-100 transition-opacity ml-1">
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
<script>
document.addEventListener('alpine:init', () => {
    Alpine.magic('rbLinkify', () => (text) => {
        if (!text) return '';
        const escaped = String(text)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        return escaped.replace(
            /(https?:\/\/[^\s<>"'&]+)/gi,
            '<a href="$1" target="_blank" rel="noopener noreferrer" class="text-teal-600 underline hover:text-teal-800 break-all">$1</a>'
        );
    });
});
</script>
</body>
</html>
