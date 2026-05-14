@extends('layouts.app')

@section('title', 'Integrations')

@section('nav')
    @include('tenant._nav')
@endsection

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <div>
        <h1 class="text-2xl font-bold text-[#1E1B4B]">Integrations</h1>
        <p class="text-sm text-gray-500 mt-1">Connect third-party services to sync your workspace data.</p>
    </div>

    {{-- ── Flash messages ──────────────────────────────────────────────────── --}}
    @if(session('success'))
        <div class="flex items-center gap-3 p-4 bg-emerald-50 border border-emerald-200 rounded-2xl text-sm text-emerald-700">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="flex items-center gap-3 p-4 bg-red-50 border border-red-200 rounded-2xl text-sm text-red-700">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ $errors->first() }}
        </div>
    @endif

    {{-- ── Google Calendar card ──────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6">
            <div class="flex items-start gap-4">
                {{-- Google Calendar icon --}}
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0" style="background:#f0f4ff">
                    <svg viewBox="0 0 32 32" class="w-7 h-7" fill="none">
                        <rect x="4" y="4" width="24" height="24" rx="3" fill="#4285F4"/>
                        <rect x="4" y="4" width="24" height="8" rx="3" fill="#1a73e8"/>
                        <rect x="4" y="10" width="24" height="2" fill="#1a73e8"/>
                        <rect x="9" y="2" width="2" height="5" rx="1" fill="#5f6368"/>
                        <rect x="21" y="2" width="2" height="5" rx="1" fill="#5f6368"/>
                        <text x="16" y="24" text-anchor="middle" font-size="9" font-weight="bold" fill="white" font-family="sans-serif">{{ now()->format('j') }}</text>
                    </svg>
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-base font-bold text-[#1E1B4B]">Google Calendar</h2>
                        @if($integration && $integration->is_active)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-700">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span>
                                Connected
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-500">
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400 inline-block"></span>
                                Not connected
                            </span>
                        @endif
                    </div>

                    <p class="text-sm text-gray-500 mt-1 leading-relaxed">
                        Sync your tasks and deal expiry deadlines to your personal Google Calendar. Events are created automatically when tasks have due dates or deals are expiring.
                    </p>

                    @if($integration && $integration->is_active)
                        <div class="mt-3 space-y-1 text-xs text-gray-500">
                            @if($integration->google_email)
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/></svg>
                                    <span>{{ $integration->google_email }}</span>
                                </div>
                            @endif
                            @if($integration->connected_at)
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    <span>Connected {{ $integration->connected_at->diffForHumans() }}</span>
                                </div>
                            @endif
                            @if($integration->last_synced_at)
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    <span>Last synced {{ $integration->last_synced_at->diffForHumans() }}</span>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="mt-3">
                            <p class="text-xs text-gray-400">What gets synced:</p>
                            <ul class="mt-1.5 space-y-1 text-xs text-gray-500">
                                <li class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-purple-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    Tasks with due dates → calendar events on the due date
                                </li>
                                <li class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-purple-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    Deal expiry dates → calendar reminders (admins & managers)
                                </li>
                                <li class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-purple-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    Events update automatically when tasks change
                                </li>
                            </ul>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center gap-2.5 mt-5 pt-4 border-t border-gray-100">
                @if($integration && $integration->is_active)
                    <form method="POST" action="{{ route('tenant.google.calendar.sync-now', $tenantId) }}"
                          x-data="{ syncing: false }" @submit="syncing = true">
                        @csrf
                        <button type="submit" :disabled="syncing"
                                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border border-[#7B61FF] text-[#7B61FF] text-sm font-semibold hover:bg-purple-50 transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                            <svg class="w-4 h-4" :class="syncing ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            <span x-text="syncing ? 'Syncing…' : 'Sync Now'">Sync Now</span>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('tenant.google.calendar.disconnect', $tenantId) }}" onsubmit="return confirm('Disconnect Google Calendar? Synced events will be removed from your calendar.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border border-red-200 text-red-600 text-sm font-semibold hover:bg-red-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            Disconnect
                        </button>
                    </form>
                @else
                    <a href="{{ route('tenant.google.calendar.connect', $tenantId) }}"
                       x-data="{ connecting: false }"
                       @click="connecting = true"
                       :class="connecting ? 'opacity-75 pointer-events-none' : ''"
                       class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white transition-all hover:opacity-90"
                       style="background:linear-gradient(135deg,#4285F4,#1a73e8)">
                        <svg x-show="connecting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <svg x-show="!connecting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                        <span x-text="connecting ? 'Redirecting to Google…' : 'Connect Google Calendar'">Connect Google Calendar</span>
                    </a>
                @endif
            </div>
        </div>

        {{-- Setup guide (shown only when not connected) --}}
        @if(!$integration || !$integration->is_active)
        <div class="border-t border-gray-100 bg-gray-50 px-6 py-4">
            <details class="group">
                <summary class="flex items-center gap-2 text-xs font-semibold text-gray-500 cursor-pointer hover:text-gray-700 select-none">
                    <svg class="w-3.5 h-3.5 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    First time? Setup guide
                </summary>
                <ol class="mt-3 space-y-2 text-xs text-gray-500 list-decimal list-inside pl-1">
                    <li>Go to <a href="https://console.cloud.google.com/" target="_blank" class="text-blue-600 hover:underline">Google Cloud Console</a> and create a project.</li>
                    <li>Enable the <strong>Google Calendar API</strong> in APIs &amp; Services.</li>
                    <li>Create OAuth 2.0 credentials (Web application type).</li>
                    <li>Add <code class="bg-gray-100 px-1 rounded font-mono">{{ config('services.google_calendar.redirect_uri') }}</code> as an authorized redirect URI.</li>
                    <li>Set <code class="bg-gray-100 px-1 rounded font-mono">GOOGLE_CALENDAR_CLIENT_ID</code> and <code class="bg-gray-100 px-1 rounded font-mono">GOOGLE_CALENDAR_CLIENT_SECRET</code> in your <code class="bg-gray-100 px-1 rounded font-mono">.env</code> file.</li>
                    <li>Click <strong>Connect Google Calendar</strong> above.</li>
                </ol>
            </details>
        </div>
        @endif
    </div>

    {{-- ── Coming soon cards ─────────────────────────────────────────────── --}}
    <div>
        <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Coming soon</h2>
        <div class="grid sm:grid-cols-2 gap-3">
            @foreach([
                ['name' => 'Slack', 'desc' => 'Post deal and task updates to Slack channels.', 'color' => '#4A154B', 'icon' => 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z'],
                ['name' => 'Zapier', 'desc' => 'Automate workflows with 5,000+ apps via Zapier.', 'color' => '#FF4A00', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z'],
                ['name' => 'Webhook', 'desc' => 'Send real-time event payloads to any URL.', 'color' => '#6366F1', 'icon' => 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4'],
            ] as $app)
            <div class="flex items-start gap-3 p-4 bg-white rounded-2xl border border-gray-100 opacity-60">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0" style="background:{{ $app['color'] }}1a">
                    <svg class="w-5 h-5" fill="none" stroke="{{ $app['color'] }}" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $app['icon'] }}"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-700">{{ $app['name'] }}</span>
                        <span class="text-[10px] font-semibold text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded-full">Soon</span>
                    </div>
                    <p class="text-xs text-gray-400 mt-0.5">{{ $app['desc'] }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>

</div>
@endsection
