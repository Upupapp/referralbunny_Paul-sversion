<!DOCTYPE html>
<html lang="en" x-data="{ sidebarOpen: false }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Referral Bunny') — {{ config('app.name') }}</title>
    <link rel="icon" type="image/webp" href="/images/logos/referralbunny-favicon.webp">
    <link rel="apple-touch-icon" href="/images/logos/referralbunny-app-icon.webp">

    {{-- Open Graph / Social preview --}}
    <meta property="og:type"        content="website">
    <meta property="og:site_name"   content="ReferralBunny.ai">
    <meta property="og:title"       content="ReferralBunny.ai — Referral Management Platform">
    <meta property="og:description" content="Set up, manage, and grow your referral programs. Leads, partners, and commissions — all in one place.">
    <meta property="og:image"       content="https://referralbunny.ai/images/logos/referralbunny-social-avatar.png">
    <meta property="og:url"         content="{{ url()->current() }}">
    <meta name="twitter:card"       content="summary_large_image">
    <meta name="twitter:title"      content="ReferralBunny.ai — Referral Management Platform">
    <meta name="twitter:description" content="Set up, manage, and grow your referral programs. Leads, partners, and commissions — all in one place.">
    <meta name="twitter:image"      content="https://referralbunny.ai/images/logos/referralbunny-social-avatar.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#F0EFFA] font-sans antialiased">

<div class="flex h-screen overflow-hidden">

    {{-- Mobile backdrop --}}
    <div x-show="sidebarOpen"
         x-transition:enter="transition-opacity ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="sidebarOpen = false"
         class="fixed inset-0 bg-black/50 z-20 lg:hidden"
         x-cloak></div>

    {{-- Sidebar --}}
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
           class="sidebar fixed inset-y-0 left-0 z-30 w-64 flex flex-col transition-transform duration-200 lg:relative lg:translate-x-0">

        {{-- Logo --}}
        <a href="@isset($tenant){{ route('tenant.dashboard', $tenant->id) }}@else{{ route('platform.dashboard') }}@endisset"
           class="flex items-center gap-3 px-5 py-4 border-b border-white/10 hover:bg-white/5 transition-colors">
            <x-rb-logo variant="icon" size="sm" :priority="true" :decorative="true" class="shrink-0" />
            <div class="flex-1 min-w-0">
                <p class="text-white text-sm font-semibold leading-none tracking-tight">referralbunny.ai</p>
                <p class="text-white/50 text-xs mt-0.5 truncate">@isset($tenant){{ $tenant->name }}@else{{ $platformLabel ?? 'Super Admin' }}@endisset</p>
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

        {{-- R Bunny sidebar widget --}}
        <div class="px-3 pb-3 shrink-0">
            <div class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl" style="background:rgba(255,255,255,0.08)">
                <x-r-bunny variant="waving" size="xs" :decorative="true" />
                <div class="min-w-0">
                    <p class="text-white text-xs font-semibold leading-none">R Bunny</p>
                    <p class="text-white/40 text-xs mt-0.5">Your referral assistant</p>
                </div>
            </div>
        </div>

        {{-- Bottom --}}
        <div class="px-4 py-4 border-t border-white/10">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-pink-400 to-purple-500 flex items-center justify-center text-white text-xs font-bold shrink-0">
                    SA
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-white text-sm font-medium truncate">Super Admin</p>
                    <p class="text-white/40 text-xs truncate">admin@referralbunny.com</p>
                </div>
                <a href="{{ route('logout') }}"
                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                   class="text-white/40 hover:text-white transition-colors" title="Logout">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                </a>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                    @csrf
                </form>
            </div>
        </div>
    </aside>

    {{-- Main --}}
    <div class="flex-1 flex flex-col overflow-hidden min-w-0">

        {{-- Global search overlay --}}
        <div x-data="globalSearch()" x-init="initSearch()"
             @keydown.window="handleKey($event)">

            {{-- Search overlay backdrop --}}
            <div x-show="open" x-cloak @click="open = false"
                 class="fixed inset-0 bg-black/40 z-40 backdrop-blur-sm"
                 x-transition:enter="transition-opacity duration-150"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity duration-100"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"></div>

            {{-- Search modal --}}
            <div x-show="open" x-cloak @click.stop
                 class="fixed top-16 left-1/2 -translate-x-1/2 z-50 w-full max-w-2xl px-4"
                 x-transition:enter="transition duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0">
                <div class="bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden">

                    {{-- Input --}}
                    <div class="flex items-center gap-3 px-4 py-3 border-b border-gray-100">
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input x-ref="searchInput" type="text" x-model="query"
                               @input.debounce.200ms="liveSearch()"
                               @keydown.arrow-down.prevent="moveDown()"
                               @keydown.arrow-up.prevent="moveUp()"
                               @keydown.enter.prevent="selectCurrent()"
                               placeholder="Search tenants, invoices, promos… or type a command"
                               class="flex-1 text-sm text-gray-900 placeholder-gray-400 bg-transparent outline-none">
                        <div class="flex items-center gap-1.5 shrink-0">
                            <kbd class="px-1.5 py-0.5 text-xs text-gray-400 bg-gray-100 rounded border border-gray-200">Esc</kbd>
                        </div>
                    </div>

                    {{-- Results --}}
                    <div class="max-h-96 overflow-y-auto">

                        {{-- Recent (when empty query) --}}
                        <template x-if="!query && recent.length > 0">
                            <div class="p-2">
                                <p class="px-3 py-1 text-xs font-semibold text-gray-400 uppercase tracking-wide">Recent</p>
                                <template x-for="(r, i) in recent.slice(0,5)" :key="r.query">
                                    <button @click="query = r.query; liveSearch()"
                                            class="w-full flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-[#F0EFFA] transition-colors text-left">
                                        <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <span class="text-sm text-gray-700" x-text="r.query"></span>
                                        <span class="ml-auto text-xs text-gray-400" x-text="r.result_count + ' results'"></span>
                                    </button>
                                </template>
                            </div>
                        </template>

                        {{-- Command detected --}}
                        <template x-if="command && query">
                            <div class="p-2 border-b border-gray-100">
                                <div class="flex items-center gap-3 px-3 py-2 bg-purple-50 rounded-xl">
                                    <svg class="w-4 h-4 text-purple-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    <span class="text-sm text-purple-800 flex-1" x-text="'Command: ' + command.action?.replace(/_/g,' ')"></span>
                                    <a x-show="command.action === 'navigate'" :href="command.url"
                                       class="text-xs font-medium text-purple-700 hover:text-purple-900">Go →</a>
                                    <button x-show="command.sensitive" @click="openConfirm(command)"
                                            class="text-xs font-medium text-purple-700 hover:text-purple-900">Execute</button>
                                    <button x-show="command.filter" @click="applyFilter(command.filter)"
                                            class="text-xs font-medium text-purple-700 hover:text-purple-900">Filter</button>
                                </div>
                            </div>
                        </template>

                        {{-- Loading --}}
                        <template x-if="loading">
                            <div class="flex items-center justify-center py-8 text-gray-400 text-sm gap-2">
                                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                Searching…
                            </div>
                        </template>

                        {{-- Results --}}
                        <template x-if="!loading && results.length > 0">
                            <div class="p-2">
                                <p class="px-3 py-1 text-xs font-semibold text-gray-400 uppercase tracking-wide" x-text="total + ' results'"></p>
                                <template x-for="(result, i) in results.slice(0,8)" :key="result.entity_type+result.id">
                                    <a :href="result.url"
                                       @click="open = false"
                                       @mouseenter="activeIndex = i"
                                       :class="activeIndex === i ? 'bg-[#F0EFFA]' : ''"
                                       class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-[#F0EFFA] transition-colors">
                                        <span class="text-base shrink-0" x-text="entityEmoji(result.entity_type)"></span>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <span class="text-sm font-medium text-[#1E1B4B] truncate" x-text="result.title"></span>
                                                <span class="text-xs text-gray-400 shrink-0" x-text="result.entity_label"></span>
                                            </div>
                                            <p class="text-xs text-gray-400 truncate" x-text="result.description"></p>
                                        </div>
                                        <span :class="{
                                            'badge badge-green':  ['active','paid','approved'].includes(result.status),
                                            'badge badge-blue':   result.status === 'trial',
                                            'badge badge-orange': ['open','pending'].includes(result.status),
                                            'badge badge-red':    ['failed','suspended','at_risk'].includes(result.status),
                                            'badge badge-gray':   true,
                                        }" class="shrink-0 text-xs" x-text="result.status"></span>
                                    </a>
                                </template>
                            </div>
                        </template>

                        {{-- No results --}}
                        <template x-if="!loading && query && results.length === 0">
                            <div class="text-center py-8 text-gray-400 text-sm">
                                No results for "<span x-text="query"></span>"
                                <div class="mt-2">
                                    <a :href="'/platform/search?q=' + encodeURIComponent(query)"
                                       @click="open = false"
                                       class="text-purple-600 hover:text-purple-700 text-xs font-medium">
                                        Search all data →
                                    </a>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Footer --}}
                    <div class="px-4 py-2.5 border-t border-gray-100 flex items-center justify-between text-xs text-gray-400">
                        <div class="flex gap-3">
                            <span><kbd class="bg-gray-100 px-1 rounded border border-gray-200">↑↓</kbd> navigate</span>
                            <span><kbd class="bg-gray-100 px-1 rounded border border-gray-200">↵</kbd> open</span>
                        </div>
                        <a :href="'/platform/search?q=' + encodeURIComponent(query)"
                           x-show="query" @click="open = false"
                           class="text-purple-600 hover:text-purple-700 font-medium">
                            Full search →
                        </a>
                    </div>
                </div>
            </div>

            {{-- Confirm modal --}}
            <div x-show="confirmOpen" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 space-y-4" @click.stop>
                    <p class="font-semibold text-[#1E1B4B]" x-text="'Confirm: ' + (pendingAction?.label || pendingAction?.action)"></p>
                    <input type="text" x-model="confirmReason" class="form-input text-sm" placeholder="Reason (optional)">
                    <div class="flex justify-end gap-3">
                        <button @click="confirmOpen = false" class="btn-secondary text-sm">Cancel</button>
                        <button @click="doAction()" :disabled="actionBusy" class="btn-primary text-sm" x-text="actionBusy ? 'Executing...' : 'Confirm'"></button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Top bar --}}
        <header class="h-16 bg-white border-b border-gray-100 flex items-center px-4 lg:px-6 gap-3 shrink-0 z-10">

            {{-- Mobile: sidebar toggle --}}
            <button @click="sidebarOpen = true"
                    class="lg:hidden p-2 rounded-xl hover:bg-gray-100 text-gray-500 transition-colors shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            {{-- Page title --}}
            <div class="min-w-0 flex-1 lg:flex-none">
                <h1 class="text-[#1E1B4B] font-semibold text-sm lg:text-base truncate leading-tight">@yield('title', 'Dashboard')</h1>
                @hasSection('subtitle')
                    <p class="text-gray-400 text-xs truncate hidden sm:block">@yield('subtitle')</p>
                @endif
            </div>

            {{-- Desktop search button --}}
            <div class="hidden lg:flex flex-1 max-w-lg mx-4">
                <button @click="$dispatch('open-search')"
                        class="w-full flex items-center gap-2.5 px-3.5 py-2 rounded-xl border border-gray-200 bg-gray-50 hover:bg-white hover:border-purple-300 hover:shadow-sm transition-all text-sm text-gray-400 group">
                    <svg class="w-4 h-4 text-gray-400 group-hover:text-purple-400 transition-colors shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <span class="flex-1 text-left">Search tenants, invoices, promos…</span>
                    <kbd class="hidden xl:inline-flex items-center px-1.5 py-0.5 text-xs bg-white border border-gray-200 rounded text-gray-400 shadow-sm">⌘K</kbd>
                </button>
            </div>

            {{-- Right group — ml-auto pushes to far right --}}
            <div class="flex items-center gap-1 ml-auto shrink-0">

                {{-- Page-specific action (e.g. New Tenant) --}}
                @yield('topbar-actions')

                {{-- Mobile search icon --}}
                <button @click="$dispatch('open-search')"
                        class="lg:hidden p-2 rounded-xl hover:bg-gray-100 text-gray-500 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </button>

                {{-- Divider --}}
                <div class="w-px h-5 bg-gray-200 mx-1.5"></div>

                {{-- Notifications --}}
                <div x-data="notifPanel()" x-init="load()" class="relative">
                    <button @click="open = !open"
                            class="relative p-2 rounded-xl hover:bg-gray-100 text-gray-500 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        <span x-show="count > 0"
                              x-text="count > 9 ? '9+' : count"
                              class="absolute -top-0.5 -right-0.5 min-w-[1.1rem] h-[1.1rem] px-0.5 bg-[#FF5733] rounded-full text-white text-[10px] font-bold flex items-center justify-center leading-none"></span>
                    </button>

                    {{-- Notification dropdown --}}
                    <div x-show="open" @click.outside="open = false" x-cloak
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="absolute right-0 top-full mt-2 w-80 bg-white rounded-2xl shadow-xl border border-gray-100 z-50 overflow-hidden">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                            <h3 class="font-semibold text-[#1E1B4B] text-sm">Notifications</h3>
                            <span x-show="count > 0" class="badge badge-red text-xs" x-text="count + ' unread'"></span>
                        </div>
                        <div class="max-h-80 overflow-y-auto divide-y divide-gray-50">
                            <template x-if="notifs.length === 0">
                                <div class="px-4 py-10 text-center text-sm text-gray-400">
                                    <svg class="w-8 h-8 text-gray-200 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                    All caught up
                                </div>
                            </template>
                            <template x-for="n in notifs" :key="n.id">
                                <a :href="n.action_url || '#'" @click="open = false"
                                   class="flex items-start gap-3 px-4 py-3 hover:bg-[#F0EFFA] transition-colors">
                                    <span :class="{
                                        'w-2 h-2 rounded-full mt-1.5 shrink-0': true,
                                        'bg-red-500':    n.priority === 'critical',
                                        'bg-orange-500': n.priority === 'high',
                                        'bg-blue-400':   n.priority === 'medium',
                                        'bg-gray-400':   !['critical','high','medium'].includes(n.priority),
                                    }"></span>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs text-gray-700 leading-relaxed line-clamp-2" x-text="n.message"></p>
                                        <p class="text-xs text-gray-400 mt-0.5" x-text="n.created_at ? new Date(n.created_at).toLocaleDateString('en',{month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'}) : ''"></p>
                                    </div>
                                </a>
                            </template>
                        </div>
                        <div class="px-4 py-3 border-t border-gray-100 bg-gray-50">
                            <a href="{{ route('platform.dashboard') }}" @click="open = false"
                               class="text-xs text-purple-600 hover:text-purple-700 font-medium">View all notifications</a>
                        </div>
                    </div>
                </div>

                {{-- Avatar + profile dropdown --}}
                <div x-data="{ open: false }" class="relative ml-1">
                    <button @click="open = !open"
                            class="flex items-center gap-1.5 pl-1 pr-2 py-1 rounded-xl hover:bg-gray-100 transition-colors">
                        <div class="w-7 h-7 rounded-full bg-gradient-to-br from-pink-400 to-purple-500 flex items-center justify-center text-white text-xs font-bold shrink-0">
                            SA
                        </div>
                        <svg class="w-3 h-3 text-gray-400 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div x-show="open" @click.outside="open = false" x-cloak
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="absolute right-0 top-full mt-2 w-52 bg-white rounded-xl shadow-lg border border-gray-100 z-50 overflow-hidden">
                        <div class="px-4 py-3 bg-gray-50 border-b border-gray-100">
                            <p class="text-xs font-semibold text-[#1E1B4B]">Super Admin</p>
                            <p class="text-xs text-gray-400 mt-0.5 truncate">admin@referralbunny.com</p>
                        </div>
                        <div class="py-1">
                            <a href="{{ route('platform.dashboard') }}" @click="open = false"
                               class="flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                Dashboard
                            </a>
                            <a href="{{ route('platform.billing') }}" @click="open = false"
                               class="flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                Billing
                            </a>
                        </div>
                        <div class="border-t border-gray-100 py-1">
                            <form method="POST" action="{{ route('logout') }}" @click.stop>
                                @csrf
                                <button type="submit"
                                        class="flex items-center gap-2.5 w-full px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                    Sign out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </header>

        {{-- Flash messages --}}
        @if(session('success'))
            <div class="mx-4 lg:mx-6 mt-4 p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-700 text-sm flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mx-4 lg:mx-6 mt-4 p-3 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                {{ session('error') }}
            </div>
        @endif

        {{-- Content --}}
        <main class="flex-1 overflow-auto p-4 lg:p-6">
            @yield('content')
        </main>
    </div>
</div>

{{-- ── Global Toast Notifications ── --}}
<div x-data="toastSystem()"
     @show-toast.window="add($event.detail)"
     class="fixed bottom-5 right-5 z-[200] flex flex-col gap-2 items-end pointer-events-none"
     style="max-width:360px">
    <template x-for="toast in toasts" :key="toast.id">
        <div class="pointer-events-auto flex items-center gap-3 w-full px-4 py-3.5 rounded-2xl shadow-2xl text-sm font-medium"
             :class="{
                 'bg-emerald-600 text-white': toast.type === 'success',
                 'bg-red-600 text-white':     toast.type === 'error',
                 'bg-orange-500 text-white':  toast.type === 'warning',
                 'bg-[#1E1B4B] text-white':   toast.type === 'info',
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

<script>
function notifPanel() {
    return {
        open: false,
        notifs: [],
        count: 0,
        async load() {
            try {
                const res   = await fetch('/api/notifications?is_read=false&limit=6');
                const data  = await res.json();
                this.notifs = Array.isArray(data) ? data.slice(0, 6) : [];
                this.count  = this.notifs.length;
            } catch(e) { this.notifs = []; this.count = 0; }
        },
    }
}

function globalSearch() {
    return {
        open: false, query: '', results: [], total: 0, loading: false,
        recent: [], command: null, activeIndex: -1,
        confirmOpen: false, pendingAction: null, confirmReason: '', actionBusy: false,

        async initSearch() {
            window.addEventListener('open-search', () => {
                this.open = true;
                this.$nextTick(() => this.$refs.searchInput?.focus());
            });
            try {
                const res  = await fetch('/api/search/recent');
                this.recent = await res.json();
            } catch(e) {}
        },

        handleKey(e) {
            if ((e.key === '/' || ((e.metaKey || e.ctrlKey) && e.key === 'k'))
                && !['INPUT','TEXTAREA'].includes(document.activeElement?.tagName)) {
                e.preventDefault();
                this.open = true;
                this.$nextTick(() => this.$refs.searchInput?.focus());
            }
            if (e.key === 'Escape') { this.open = false; this.query = ''; }
        },

        async liveSearch() {
            if (!this.query.trim()) { this.results = []; this.command = null; return; }
            this.loading = true;
            try {
                const res  = await fetch('/api/search?q=' + encodeURIComponent(this.query) + '&limit=8');
                const data = await res.json();
                this.results     = data.results  || [];
                this.total       = data.total    || 0;
                this.command     = data.command  || null;
                this.activeIndex = -1;
            } finally { this.loading = false; }
        },

        moveDown() { this.activeIndex = Math.min(this.activeIndex + 1, this.results.slice(0,8).length - 1); },
        moveUp()   { this.activeIndex = Math.max(this.activeIndex - 1, -1); },

        selectCurrent() {
            if (this.activeIndex >= 0 && this.results[this.activeIndex]) {
                window.location.href = this.results[this.activeIndex].url;
            } else if (this.command?.action === 'navigate') {
                window.location.href = this.command.url;
            } else {
                window.location.href = '/platform/search?q=' + encodeURIComponent(this.query);
            }
            this.open = false;
        },

        openConfirm(action) {
            this.pendingAction = action;
            this.confirmReason = '';
            this.confirmOpen   = true;
        },

        async doAction() {
            if (!this.pendingAction) return;
            this.actionBusy = true;
            try {
                const res = await fetch('/api/search/actions', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({
                        action:      this.pendingAction.action,
                        entity_type: this.pendingAction.entity_type || 'tenant',
                        entity_id:   this.pendingAction.entity_id   || '',
                        reason:      this.confirmReason,
                    }),
                });
                const data = await res.json();
                this.confirmOpen = false;
                if (!data.success) alert('Error: ' + data.message);
            } finally { this.actionBusy = false; }
        },

        applyFilter(filter) {
            this.open = false;
            const params = new URLSearchParams(filter);
            window.location.href = '/platform/search?' + params;
        },

        entityEmoji(type) {
            const m = { tenant:'🏢', invoice:'📄', payment:'💳', promo_code:'🏷️', promotion:'✨', approval_request:'⏳', subscription:'🔄', lead:'👤', reseller:'👥', notification:'🔔' };
            return m[type] || '📌';
        },
    }
}

function toastSystem() {
    return {
        toasts: [],
        add({ type = 'info', message, duration = 4000 }) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, type, message });
            setTimeout(() => this.remove(id), duration);
        },
        remove(id) { this.toasts = this.toasts.filter(t => t.id !== id); },
    }
}
</script>
</body>
</html>
