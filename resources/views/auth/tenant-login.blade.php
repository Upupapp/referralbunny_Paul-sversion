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
        body {
            min-height: 100vh;
            background: linear-gradient(145deg, #1E1347 0%, #2D1B69 55%, #1a1040 100%);
            font-family: 'Inter', sans-serif;
        }

        /* ── Intro overlay ──────────────────────────────────────── */
        #intro {
            position: fixed; inset: 0; z-index: 50;
            display: flex; flex-direction: column;
            align-items: center; justify-content: space-between;
            padding: 2.5rem 1.5rem 2rem;
            background: linear-gradient(145deg, #1E1347 0%, #2D1B69 55%, #1a1040 100%);
            transition: opacity .5s ease;
        }
        #intro.fading { opacity: 0; pointer-events: none; }
        #intro.gone   { display: none; }

        .anim-logo   { animation: aUp .8s ease-out .2s both; }
        .anim-mascot { animation: aUp .7s ease-out .5s both; }
        .anim-text   { animation: aUp .6s ease-out 3.4s both; }
        .anim-foot   { animation: aIn .5s ease-out .8s  both; }
        .glow-a      { animation: gPulse 5s ease-in-out infinite; }
        .glow-b      { animation: gPulse 5s ease-in-out 2.5s infinite; }

        @keyframes aUp    { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:none} }
        @keyframes aIn    { from{opacity:0} to{opacity:1} }
        @keyframes gPulse { 0%,100%{opacity:.18} 50%{opacity:.3} }
        @media (prefers-reduced-motion: reduce) { #intro { display: none !important; } }
    </style>
</head>
<body>

{{-- ═══════════════ INTRO OVERLAY ═══════════════════════════ --}}
<div id="intro">
    <div class="glow-a absolute w-[500px] h-[500px] rounded-full pointer-events-none -top-48 -left-48"
         style="background:radial-gradient(circle,#7C3AED,transparent 65%)"></div>
    <div class="glow-b absolute w-[400px] h-[400px] rounded-full pointer-events-none -bottom-32 -right-32"
         style="background:radial-gradient(circle,#3B82F6,transparent 65%)"></div>

    {{-- Logo --}}
    <div class="anim-logo relative z-10">
        <x-rb-logo variant="white" size="sm" :priority="true" :decorative="true" />
    </div>

    {{-- Mascot --}}
    <div class="anim-mascot relative z-10">
        <x-r-bunny variant="waving" size="lg" :decorative="true" />
    </div>

    {{-- Text + footer --}}
    <div class="relative z-10 w-full text-center space-y-4">
        <div class="anim-text">
            <h1 class="text-2xl font-bold text-white">Welcome to ReferralBunny.ai</h1>
            <p class="mt-1 text-sm text-white/60">Set up, manage, and grow your referral programs.</p>
        </div>
        <div class="anim-foot flex items-center justify-between">
            <span class="text-xs text-white/30 tracking-widest uppercase">Tenant Admin Portal</span>
            <button onclick="skipIntro()" class="text-sm text-white/40 hover:text-white/80 transition-colors px-2 py-1 rounded">Skip</button>
        </div>
    </div>
</div>

{{-- ═══════════════ LOGIN PAGE ════════════════════════════════ --}}
<div class="min-h-screen flex items-center justify-center p-4 sm:p-6 lg:p-10">

    {{-- Floating card --}}
    <div class="w-full max-w-4xl rounded-3xl shadow-2xl overflow-hidden" style="min-height:560px; display:flex;">

        {{-- ── LEFT: form ────────────────────────────────────── --}}
        <div class="bg-white flex flex-col justify-center px-8 py-10 sm:px-12" style="flex:1; min-width:0;">

            {{-- Logo --}}
            <div class="mb-8">
                <x-rb-logo variant="horizontal" size="sm" :priority="true" :decorative="true" />
            </div>

            {{-- Heading --}}
            <h1 class="text-2xl font-bold text-gray-900 mb-1">Sign in to your account</h1>
            <p class="text-xs font-semibold text-gray-400 tracking-widest uppercase mb-8">Tenant Admin Portal</p>

            {{-- Error --}}
            @if ($errors->any())
                <div class="mb-6 flex items-start gap-3 bg-red-50 border border-red-100 rounded-2xl px-4 py-3">
                    <x-r-bunny variant="warning" size="xs" :decorative="true" class="mt-0.5 shrink-0" />
                    <p class="text-sm text-red-600">{{ $errors->first() }}</p>
                </div>
            @endif

            {{-- Form --}}
            <form method="POST" action="{{ route('tenant.login.post') }}" class="space-y-5" style="max-width:380px;">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">Email address</label>
                    <input id="email" name="email" type="email" autocomplete="email" required
                           value="{{ old('email') }}"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50/60 px-4 py-3 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent focus:bg-white transition"
                           placeholder="you@company.com">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                    <div class="relative" x-data="{ show: false }">
                        <input id="password" name="password" :type="show ? 'text' : 'password'" required
                               autocomplete="current-password"
                               class="w-full rounded-xl border border-gray-200 bg-gray-50/60 px-4 py-3 pr-11 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent focus:bg-white transition"
                               placeholder="••••••••">
                        <button type="button" @click="show = !show" tabindex="-1"
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                            <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="show"  class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                    <div class="flex justify-end mt-2">
                        <a href="#" class="text-xs text-violet-600 hover:text-violet-700 hover:underline">Forgot password?</a>
                    </div>
                </div>

                <button type="submit"
                        class="w-full rounded-xl bg-violet-600 hover:bg-violet-700 active:bg-violet-800 text-white font-semibold py-3 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2">
                    Sign In
                </button>
            </form>

            {{-- Divider + secondary --}}
            <div class="mt-6 space-y-3" style="max-width:380px;">
                <div class="flex items-center gap-3">
                    <div class="flex-1 h-px bg-gray-100"></div>
                    <span class="text-xs text-gray-400">or</span>
                    <div class="flex-1 h-px bg-gray-100"></div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <a href="{{ route('tenant.create') }}"
                       class="flex items-center justify-center rounded-xl border border-gray-200 hover:border-violet-300 hover:bg-violet-50 text-gray-600 hover:text-violet-700 font-medium py-2.5 text-xs transition-colors">
                        Create a tenant
                    </a>
                    <a href="{{ route('tenant.join') }}"
                       class="flex items-center justify-center rounded-xl border border-gray-200 hover:border-violet-300 hover:bg-violet-50 text-gray-600 hover:text-violet-700 font-medium py-2.5 text-xs transition-colors">
                        Join a tenant
                    </a>
                </div>
            </div>
        </div>

        {{-- ── RIGHT: brand panel ─────────────────────────────── --}}
        <div class="relative overflow-hidden flex-col items-center justify-center p-10 text-center"
             style="width:400px; display:none; background:linear-gradient(145deg,#1E1347 0%,#2D1B69 60%,#1a1040 100%);"
             id="brand-panel">
            <div class="absolute w-64 h-64 rounded-full opacity-25 -top-16 -right-16 pointer-events-none"
                 style="background:radial-gradient(circle,#7C3AED,transparent 65%)"></div>
            <div class="absolute w-52 h-52 rounded-full opacity-15 -bottom-12 -left-12 pointer-events-none"
                 style="background:radial-gradient(circle,#3B82F6,transparent 65%)"></div>
            <div class="relative z-10">
                <x-r-bunny variant="waving" size="lg" :decorative="true" class="mx-auto mb-5" />
                <x-rb-logo variant="white" size="xs" :priority="true" :decorative="true" class="mx-auto mb-4" />
                <h2 class="text-lg font-bold text-white mb-2 leading-snug">Grow your referral<br>programs with ease.</h2>
                <p class="text-white/50 text-xs leading-relaxed max-w-[180px] mx-auto">
                    Leads, partners, commissions — all in one place.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
    // Show brand panel on md+ screens
    (function () {
        var panel = document.getElementById('brand-panel');
        if (panel && window.innerWidth >= 768) {
            panel.style.display = 'flex';
        }
        window.addEventListener('resize', function () {
            if (!panel) return;
            panel.style.display = window.innerWidth >= 768 ? 'flex' : 'none';
        });
    })();

    // Intro — always plays on every page load / refresh
    var intro = document.getElementById('intro');
    var timer = setTimeout(advance, 5000);

    function advance() {
        clearTimeout(timer);
        if (!intro) return;
        intro.classList.add('fading');
        setTimeout(function () { intro.classList.add('gone'); }, 500);
    }

    function skipIntro() { advance(); }
</script>
</body>
</html>
