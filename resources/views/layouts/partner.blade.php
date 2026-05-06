<!DOCTYPE html>
<html lang="en" x-data="{ sidebarOpen: false }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Partner Portal') — ReferralBunny.ai</title>
    <link rel="icon" type="image/webp" href="/images/logos/referralbunny-favicon.webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
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
<body class="pt-page-bg font-sans antialiased">

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
            <x-rb-logo variant="icon" size="sm" :priority="true" :decorative="true" class="shrink-0" />
            <div class="flex-1 min-w-0">
                <p class="text-white text-sm font-semibold leading-none tracking-tight">referralbunny.ai</p>
                <p class="text-white/50 text-xs mt-0.5 truncate">Partner Portal</p>
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
            $p           = auth('partner')->user() ?? ($partner ?? null);
            $firstName   = $p?->first_name ?? '';
            $lastName    = $p?->last_name ?? '';
            $displayName = trim($firstName . ' ' . $lastName) ?: 'Partner';
            $photoUrl    = $p?->profile_photo_path ? asset('storage/' . $p->profile_photo_path) : null;
            $initials    = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: 'P';
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
                <button @click="sidebarOpen = true" class="lg:hidden p-2 rounded-xl hover:bg-gray-100 text-gray-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <h1 class="text-sm font-semibold" style="color:#1E1B4B">@yield('title', 'Dashboard')</h1>
            </div>
            <div class="flex items-center gap-2">
                @yield('topbar-actions')
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
     @show-toast.window="toasts.push($event.detail); setTimeout(() => toasts.shift(), 4000)"
     class="fixed bottom-5 right-5 z-50 space-y-2 pointer-events-none">
    <template x-for="(t, i) in toasts" :key="i">
        <div class="flex items-center gap-3 px-4 py-3 rounded-2xl shadow-lg text-sm font-medium pointer-events-auto"
             :class="t.type === 'success' ? 'text-white' : 'bg-red-500 text-white'"
             :style="t.type === 'success' ? 'background:#2563EB' : ''"
             x-text="t.message"></div>
    </template>
</div>

{{-- R Bunny Onboarding — first sign-in walkthrough + bot (bottom-left) --}}
<x-brand.onboarding-walkthrough />
<x-brand.onboarding-bot />

@stack('scripts')
</body>
</html>
