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
        /* ── Intro ──────────────────────────────────────────────── */
        #intro-screen {
            position: fixed; inset: 0; z-index: 50;
            display: flex; flex-direction: column;
            align-items: center; justify-content: space-between;
            padding: 2.5rem 1.5rem;
            background: linear-gradient(145deg, #1E1347 0%, #2D1B69 55%, #1a1040 100%);
            animation: introOut 0.5s ease-in 4.5s forwards;
        }
        #intro-screen.gone { display: none; }
        @keyframes introOut { to { opacity: 0; pointer-events: none; } }

        .il { animation: fadeUp .8s ease-out .2s both; }   /* logo */
        .im { animation: fadeUp .7s ease-out .4s both; }   /* mascot */
        .it { animation: fadeUp .6s ease-out 3.4s both; }  /* text */
        .ib { animation: fadeIn .5s ease-out .8s both; }   /* badge/skip */
        .g1 { animation: glow 5s ease-in-out infinite; }
        .g2 { animation: glow 5s ease-in-out 2.5s infinite; }

        @keyframes fadeUp { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:translateY(0)} }
        @keyframes fadeIn { from{opacity:0} to{opacity:1} }
        @keyframes glow   { 0%,100%{opacity:.18} 50%{opacity:.28} }

        @media (prefers-reduced-motion: reduce) { #intro-screen { display: none !important; } }
    </style>
</head>
<body class="font-sans antialiased" x-data="tenantLogin()" x-init="init()"
      style="background: linear-gradient(145deg, #1E1347 0%, #2D1B69 55%, #1a1040 100%); min-height:100vh">

{{-- ── Intro ────────────────────────────────────────────────── --}}
<div id="intro-screen" :class="{ gone: introGone }">
    <div class="g1 absolute w-[500px] h-[500px] rounded-full pointer-events-none -top-48 -left-48"
         style="background:radial-gradient(circle,#7C3AED,transparent 65%)"></div>
    <div class="g2 absolute w-[400px] h-[400px] rounded-full pointer-events-none -bottom-32 -right-32"
         style="background:radial-gradient(circle,#3B82F6,transparent 65%)"></div>

    {{-- Logo --}}
    <div class="il relative z-10">
        <x-rb-logo variant="white" size="sm" :priority="true" :decorative="true" />
    </div>

    {{-- Mascot --}}
    <div class="im relative z-10 flex items-center justify-center">
        <x-r-bunny variant="waving" size="lg" :decorative="true" />
    </div>

    {{-- Text + footer --}}
    <div class="relative z-10 w-full space-y-4 text-center">
        <div class="it">
            <h1 class="text-2xl font-bold text-white tracking-tight">Welcome to ReferralBunny.ai</h1>
            <p class="mt-1.5 text-white/60 text-sm">Set up, manage, and grow your referral programs.</p>
        </div>
        <div class="ib flex items-center justify-between">
            <span class="text-white/30 text-xs tracking-widest uppercase">Tenant Admin Portal</span>
            <button @click="skip()" class="text-white/40 hover:text-white/80 text-sm transition-colors px-2 py-1 rounded">Skip</button>
        </div>
    </div>
</div>

{{-- ── Login page ──────────────────────────────────────────── --}}
<div x-show="showForm"
     x-transition:enter="transition-opacity duration-500"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     class="min-h-screen flex items-center justify-center p-4 sm:p-6 lg:p-8"
     style="background:linear-gradient(145deg,#1E1347 0%,#2D1B69 55%,#1a1040 100%)">

    {{-- Background glow --}}
    <div class="fixed w-[600px] h-[600px] rounded-full pointer-events-none -top-60 -left-60 opacity-20"
         style="background:radial-gradient(circle,#7C3AED,transparent 65%)"></div>
    <div class="fixed w-[500px] h-[500px] rounded-full pointer-events-none -bottom-40 -right-40 opacity-15"
         style="background:radial-gradient(circle,#3B82F6,transparent 65%)"></div>

    {{-- ── Floating card ────────────────────────────────────── --}}
    <div class="relative z-10 w-full max-w-4xl bg-white rounded-3xl shadow-2xl overflow-hidden flex flex-col md:flex-row min-h-[520px]">

        {{-- LEFT — form ---------------------------------------- --}}
        <div class="flex-1 flex flex-col justify-center px-8 py-10 sm:px-10">

            {{-- Logo --}}
            <x-rb-logo variant="horizontal" size="sm" :priority="true" :decorative="true" class="mb-8" />

            {{-- Heading --}}
            <h1 class="text-2xl font-bold text-gray-900 mb-1">Sign in to your account</h1>
            <p class="text-sm text-gray-400 mb-8 tracking-wide uppercase text-xs font-medium">Tenant Admin Portal</p>

            {{-- Error --}}
            @if ($errors->any())
                <div class="mb-6 flex items-start gap-3 bg-red-50 border border-red-100 rounded-2xl px-4 py-3">
                    <x-r-bunny variant="warning" size="xs" :decorative="true" class="mt-0.5 shrink-0" />
                    <p class="text-sm text-red-600">{{ $errors->first() }}</p>
                </div>
            @endif

            {{-- Form --}}
            <form method="POST" action="{{ route('tenant.login.post') }}" class="space-y-4">
                @csrf

                {{-- Email --}}
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">Email address</label>
                    <input id="email" name="email" type="email" autocomplete="email" required
                           value="{{ old('email') }}"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent focus:bg-white transition"
                           placeholder="you@company.com">
                </div>

                {{-- Password --}}
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                    <div class="relative" x-data="{ show: false }">
                        <input id="password" name="password" :type="show ? 'text' : 'password'" required
                               autocomplete="current-password"
                               class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 pr-11 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent focus:bg-white transition"
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

                {{-- Submit --}}
                <button type="submit"
                        class="w-full rounded-xl bg-violet-600 hover:bg-violet-700 active:bg-violet-800 text-white font-semibold py-3 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 mt-2">
                    Sign In
                </button>
            </form>

            {{-- Divider + secondary actions --}}
            <div class="mt-6 space-y-3">
                <div class="flex items-center gap-3">
                    <div class="flex-1 h-px bg-gray-100"></div>
                    <span class="text-xs text-gray-400">or</span>
                    <div class="flex-1 h-px bg-gray-100"></div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <a href="{{ route('tenant.create') }}"
                       class="flex items-center justify-center rounded-xl border border-gray-200 hover:border-violet-300 hover:bg-violet-50 text-gray-600 hover:text-violet-700 font-medium py-2.5 text-xs transition-colors text-center">
                        Create a tenant
                    </a>
                    <a href="{{ route('tenant.join') }}"
                       class="flex items-center justify-center rounded-xl border border-gray-200 hover:border-violet-300 hover:bg-violet-50 text-gray-600 hover:text-violet-700 font-medium py-2.5 text-xs transition-colors text-center">
                        Join a tenant
                    </a>
                </div>
            </div>
        </div>

        {{-- RIGHT — brand visual --------------------------------- --}}
        <div class="hidden md:flex md:w-[42%] flex-col items-center justify-center gap-6 p-10 relative overflow-hidden"
             style="background:linear-gradient(145deg,#1E1347 0%,#2D1B69 60%,#1a1040 100%)">

            {{-- Glow inside panel --}}
            <div class="absolute w-72 h-72 rounded-full opacity-20 -top-20 -right-20 pointer-events-none"
                 style="background:radial-gradient(circle,#7C3AED,transparent 65%)"></div>
            <div class="absolute w-56 h-56 rounded-full opacity-15 -bottom-16 -left-16 pointer-events-none"
                 style="background:radial-gradient(circle,#3B82F6,transparent 65%)"></div>

            <div class="relative z-10 text-center">
                <x-r-bunny variant="waving" size="lg" :decorative="true" class="mx-auto mb-5" />
                <x-rb-logo variant="white" size="xs" :priority="true" :decorative="true" class="mx-auto mb-4 opacity-90" />
                <h2 class="text-lg font-bold text-white mb-2 leading-snug">Grow your referral<br>programs with ease.</h2>
                <p class="text-white/50 text-xs leading-relaxed max-w-[180px] mx-auto">
                    Leads, partners, commissions — all in one place.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
function tenantLogin() {
    const KEY  = 'referralbunny_tenant_intro_seen';
    const seen = sessionStorage.getItem(KEY) === '1';
    return {
        introGone: seen,
        showForm:  seen,
        init() {
            if (seen) return;
            // auto-advance after 5 s
            setTimeout(() => this.advance(), 5000);
        },
        advance() {
            sessionStorage.setItem('referralbunny_tenant_intro_seen', '1');
            this.introGone = true;
            this.showForm  = true;
        },
        skip() { this.advance(); },
    };
}
</script>
</body>
</html>
