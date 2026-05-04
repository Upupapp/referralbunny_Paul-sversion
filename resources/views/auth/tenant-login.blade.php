<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — ReferralBunny.ai Tenant Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* ── Intro animation ───────────────────────── */
        #intro-screen {
            position: fixed; inset: 0; z-index: 50;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            background: linear-gradient(135deg, #1E1347 0%, #2D1B69 50%, #1a1040 100%);
            animation: introFadeOut 0.6s ease-in 4.4s forwards;
        }
        #intro-screen.hidden { display: none; }
        @keyframes introFadeOut { to { opacity: 0; pointer-events: none; } }

        .intro-logo  { animation: fadeUp 0.8s ease-out 0.2s both; }
        .intro-bunny { animation: fadeUp 0.7s ease-out 0.5s both; }
        .intro-text  { animation: fadeUp 0.6s ease-out 3.5s both; }
        .intro-badge { animation: fadeIn 0.6s ease-out 0.8s both; }
        .intro-skip  { animation: fadeIn 0.4s ease-out 1s both; }
        .glow-1 { animation: pulse 4s ease-in-out infinite; }
        .glow-2 { animation: pulse 4s ease-in-out 2s infinite; }

        @keyframes fadeUp  { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
        @keyframes fadeIn  { from { opacity:0; } to { opacity:1; } }
        @keyframes pulse   { 0%,100%{opacity:.15;} 50%{opacity:.25;} }

        @media (prefers-reduced-motion: reduce) { #intro-screen { display: none !important; } }
    </style>
</head>
<body class="bg-white font-sans antialiased" x-data="tenantLogin()" x-init="init()">

{{-- ── Intro animation ─────────────────────────────────── --}}
<div id="intro-screen" :class="{ hidden: introSkipped }"
     class="fixed inset-0 z-50 flex flex-col items-center justify-between py-10 px-6 overflow-hidden"
     style="background:linear-gradient(145deg,#1E1347 0%,#2D1B69 55%,#1a1040 100%)">

    {{-- Glow orbs --}}
    <div class="absolute w-[500px] h-[500px] rounded-full pointer-events-none -top-48 -left-48 opacity-25"
         style="background:radial-gradient(circle,#7C3AED,transparent 65%)"></div>
    <div class="absolute w-[400px] h-[400px] rounded-full pointer-events-none -bottom-32 -right-32 opacity-15"
         style="background:radial-gradient(circle,#3B82F6,transparent 65%)"></div>

    {{-- Top: logo wordmark --}}
    <div class="intro-logo relative z-10">
        <x-rb-logo variant="white" size="sm" :priority="true" :decorative="true" />
    </div>

    {{-- Middle: mascot (lg = 220px, fits comfortably without clipping) --}}
    <div class="intro-bunny relative z-10 flex-1 flex items-center justify-center py-4">
        <x-r-bunny variant="waving" size="lg" :decorative="true" />
    </div>

    {{-- Bottom: welcome text + footer row --}}
    <div class="relative z-10 w-full text-center space-y-5">
        <div class="intro-text">
            <h1 class="text-2xl font-bold text-white tracking-tight leading-tight">
                Welcome to ReferralBunny.ai
            </h1>
            <p class="mt-1.5 text-white/60 text-sm">
                Set up, manage, and grow your referral programs.
            </p>
        </div>
        <div class="intro-badge flex items-center justify-between">
            <span class="text-white/30 text-xs tracking-widest uppercase">Tenant Admin Portal</span>
            <button @click="skipIntro()"
                    class="intro-skip text-white/40 hover:text-white/80 text-sm transition-colors px-2 py-1 rounded focus:outline-none">
                Skip
            </button>
        </div>
    </div>
</div>

{{-- ── Login page ──────────────────────────────────────── --}}
<div class="min-h-screen flex" x-show="!introVisible" x-transition:enter="transition-opacity duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">

    {{-- Left brand panel (desktop) --}}
    <div class="hidden lg:flex lg:w-[45%] flex-col items-center justify-center p-12 relative overflow-hidden"
         style="background:linear-gradient(145deg,#1E1347 0%,#2D1B69 55%,#1a1040 100%)">
        <div class="absolute w-[500px] h-[500px] rounded-full opacity-20 -top-40 -left-40"
             style="background:radial-gradient(circle,#7C3AED,transparent 70%)"></div>
        <div class="absolute w-[400px] h-[400px] rounded-full opacity-10 -bottom-32 -right-32"
             style="background:radial-gradient(circle,#3B82F6,transparent 70%)"></div>
        <div class="relative z-10 text-center max-w-sm">
            <x-r-bunny variant="waving" size="xl" :decorative="true" class="mx-auto mb-6" />
            <x-rb-logo variant="white" size="sm" :priority="true" :decorative="true" class="mx-auto mb-4" />
            <h2 class="text-xl font-bold text-white mb-2">Welcome back.</h2>
            <p class="text-white/60 text-sm leading-relaxed">Manage your referral programs, leads, partners, and commissions from one place.</p>
        </div>
        <p class="absolute bottom-8 text-white/30 text-xs tracking-widest uppercase">Tenant Admin Portal</p>
    </div>

    {{-- Right form panel --}}
    <div class="flex-1 flex flex-col items-center justify-center px-6 py-12 bg-white">

        {{-- Mobile logo --}}
        <div class="lg:hidden mb-8 text-center">
            <x-rb-logo variant="horizontal" size="md" :priority="true" :decorative="true" class="mx-auto mb-2" />
            <p class="text-xs text-gray-400 uppercase tracking-widest">Tenant Admin Portal</p>
        </div>

        <div class="w-full max-w-sm">
            <h1 class="text-2xl font-bold text-gray-900 mb-1">Sign in to your Tenant Admin Portal</h1>
            <p class="text-sm text-gray-500 mb-8">Manage your referral programs, leads, partners, and commissions.</p>

            {{-- Error --}}
            @if ($errors->any())
                <div class="mb-5 flex items-start gap-3 bg-red-50 border border-red-200 rounded-xl px-4 py-3">
                    <x-r-bunny variant="warning" size="xs" :decorative="true" class="mt-0.5 shrink-0" />
                    <p class="text-sm text-red-700">{{ $errors->first() }}</p>
                </div>
            @endif

            <form method="POST" action="{{ route('tenant.login.post') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email address</label>
                    <input id="email" name="email" type="email" autocomplete="email" required
                           value="{{ old('email') }}"
                           class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition"
                           placeholder="you@company.com">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <div class="relative" x-data="{ show: false }">
                        <input id="password" name="password" :type="show ? 'text' : 'password'" required
                               autocomplete="current-password"
                               class="w-full rounded-xl border border-gray-200 px-4 py-2.5 pr-10 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition"
                               placeholder="••••••••">
                        <button type="button" @click="show = !show"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                </div>

                <button type="submit"
                        class="w-full flex items-center justify-center rounded-xl bg-violet-600 hover:bg-violet-700 text-white font-semibold py-2.5 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2">
                    Sign In
                </button>
            </form>

            <div class="mt-8 space-y-3">
                <div class="flex items-center gap-3">
                    <div class="flex-1 h-px bg-gray-100"></div>
                    <span class="text-xs text-gray-400">or</span>
                    <div class="flex-1 h-px bg-gray-100"></div>
                </div>
                <a href="{{ route('tenant.create') }}"
                   class="flex items-center justify-center w-full rounded-xl border border-gray-200 hover:border-violet-300 hover:bg-violet-50 text-gray-700 hover:text-violet-700 font-medium py-2.5 text-sm transition-colors">
                    Create a new tenant
                </a>
                <a href="{{ route('tenant.join') }}"
                   class="flex items-center justify-center w-full rounded-xl border border-gray-200 hover:border-violet-300 hover:bg-violet-50 text-gray-700 hover:text-violet-700 font-medium py-2.5 text-sm transition-colors">
                    Join an existing tenant
                </a>
            </div>

            <p class="mt-8 text-center text-xs text-gray-400">ReferralBunny.ai · Tenant Admin Portal</p>
        </div>
    </div>
</div>

<script>
function tenantLogin() {
    const KEY = 'referralbunny_tenant_intro_seen';
    const seen = sessionStorage.getItem(KEY) === '1';
    return {
        introVisible: !seen,
        introSkipped: seen,
        init() {
            if (seen) return;
            setTimeout(() => {
                sessionStorage.setItem(KEY, '1');
                this.introVisible = false;
            }, 5000);
        },
        skipIntro() {
            sessionStorage.setItem(KEY, '1');
            this.introSkipped = true;
            this.introVisible = false;
        },
    };
}
</script>
</body>
</html>
