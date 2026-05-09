<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — ReferralBunny.ai</title>
    <link rel="icon" type="image/webp" href="/images/logos/referralbunny-favicon.webp">
    <link rel="apple-touch-icon" href="/images/logos/referralbunny-app-icon.webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 0; min-height: 100vh;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #1E1347 0%, #2D1B69 55%, #1a1040 100%);
            display: flex; align-items: center; justify-content: center;
            padding: 1.5rem;
        }

        .signin-card {
            background: rgba(255,255,255,0.05);
            border: 1.5px solid rgba(255,255,255,0.10);
            border-radius: 1.5rem;
            padding: 2rem 1.75rem;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .signin-card:hover {
            background: rgba(255,255,255,0.09);
            transform: translateY(-3px);
            box-shadow: 0 24px 48px rgba(0,0,0,0.3);
        }
        .signin-card-admin:hover    { border-color: rgba(123,97,255,0.5);  box-shadow: 0 24px 48px rgba(123,97,255,0.15); }
        .signin-card-referrer:hover { border-color: rgba(20,184,166,0.5);  box-shadow: 0 24px 48px rgba(20,184,166,0.12); }
        .signin-card-partner:hover  { border-color: rgba(37,99,235,0.5);   box-shadow: 0 24px 48px rgba(37,99,235,0.15); }

        .mascot-wrap {
            width: 100px; height: 100px;
            border-radius: 1.25rem;
            display: flex; align-items: center; justify-content: center;
            overflow: hidden;
        }
        .mascot-wrap-admin    { background: rgba(123,97,255,0.15); }
        .mascot-wrap-referrer { background: rgba(20,184,166,0.12); }
        .mascot-wrap-partner  { background: rgba(37,99,235,0.12);  }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .a1 { animation: fadeUp 0.6s ease-out 0.05s both; }
        .a2 { animation: fadeUp 0.6s ease-out 0.18s both; }
        .a3 { animation: fadeUp 0.6s ease-out 0.30s both; }
        .a4 { animation: fadeUp 0.6s ease-out 0.44s both; }
        .a5 { animation: fadeUp 0.6s ease-out 0.56s both; }
    </style>
</head>
<body>
    <div class="w-full" style="max-width: 760px;">

        {{-- Logo --}}
        <div class="flex flex-col items-center text-center mb-8 a1">
            <x-rb-logo variant="primary" size="sm" :priority="true" :decorative="true" class="mb-5" style="filter: brightness(0) invert(1); opacity: 0.95;" />
            <h1 class="text-white font-bold text-2xl leading-tight">Welcome back</h1>
            <p class="text-white/50 text-sm mt-1">Sign in to your account — choose your role below.</p>
        </div>

        {{-- Three sign-in cards: stacked on mobile, side by side on sm+ --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 a2">

            {{-- Workspace Admin --}}
            <a href="{{ route('tenant.login') }}" class="signin-card signin-card-admin">
                {{-- R Bunny mascot --}}
                <div class="mascot-wrap mascot-wrap-admin">
                    <x-r-bunny variant="portal" size="sm" :decorative="true"
                                style="width:90px;height:90px;object-fit:contain" />
                </div>

                <div>
                    <p class="text-white font-bold text-base leading-snug">Workspace Admin</p>
                    <p class="text-white/45 text-xs mt-1 leading-relaxed">
                        Manage deals, referrers, and commissions for your program.
                    </p>
                </div>

                {{-- Pill label --}}
                <span style="display:inline-block;padding:.25rem .875rem;border-radius:9999px;background:rgba(123,97,255,0.2);color:#a78bfa;font-size:.6875rem;font-weight:600;letter-spacing:.04em">
                    Admin Portal
                </span>
            </a>

            {{-- Referrer / Reseller --}}
            <a href="{{ route('reseller.login') }}" class="signin-card signin-card-referrer">
                {{-- R Bunny mascot --}}
                <div class="mascot-wrap mascot-wrap-referrer">
                    <x-r-bunny variant="rocket" size="sm" :decorative="true"
                                style="width:90px;height:90px;object-fit:contain" />
                </div>

                <div>
                    <p class="text-white font-bold text-base leading-snug">Referrer</p>
                    <p class="text-white/45 text-xs mt-1 leading-relaxed">
                        Submit deals, track your pipeline, and earn commissions.
                    </p>
                </div>

                {{-- Pill label --}}
                <span style="display:inline-block;padding:.25rem .875rem;border-radius:9999px;background:rgba(20,184,166,0.15);color:#2dd4bf;font-size:.6875rem;font-weight:600;letter-spacing:.04em">
                    Referrer Portal
                </span>
            </a>

            {{-- Partner --}}
            <a href="{{ route('partner.login') }}" class="signin-card signin-card-partner">
                {{-- R Bunny mascot --}}
                <div class="mascot-wrap mascot-wrap-partner">
                    <x-r-bunny variant="rocket" size="sm" :decorative="true"
                                style="width:90px;height:90px;object-fit:contain" />
                </div>

                <div>
                    <p class="text-white font-bold text-base leading-snug">Partner Portal</p>
                    <p class="text-white/45 text-xs mt-1 leading-relaxed">
                        View deals you collaborate on as a Partner.
                    </p>
                </div>

                {{-- Pill label --}}
                <span style="display:inline-block;padding:.25rem .875rem;border-radius:9999px;background:rgba(37,99,235,0.2);color:#93c5fd;font-size:.6875rem;font-weight:600;letter-spacing:.04em">
                    Partner
                </span>
            </a>

        </div>

        {{-- Divider --}}
        <div class="flex items-center gap-3 my-5 a3">
            <div style="flex:1;height:1px;background:rgba(255,255,255,0.08)"></div>
            <span class="text-white/30 text-xs">or</span>
            <div style="flex:1;height:1px;background:rgba(255,255,255,0.08)"></div>
        </div>

        {{-- New here? --}}
        <div class="flex flex-col items-center gap-2.5 a4">
            <p class="text-white/40 text-xs text-center">New to ReferralBunny.ai?</p>
            <div class="flex gap-2 w-full">
                <a href="{{ route('tenant.create') }}"
                   style="flex:1;display:flex;align-items:center;justify-content:center;gap:.375rem;padding:.75rem 1rem;border-radius:1rem;border:1.5px solid rgba(255,255,255,0.12);background:rgba(255,255,255,0.05);color:rgba(255,255,255,0.7);font-size:.75rem;font-weight:600;text-decoration:none;transition:all .2s;font-family:'Inter',sans-serif"
                   onmouseover="this.style.background='rgba(255,255,255,0.09)';this.style.color='#fff'"
                   onmouseout="this.style.background='rgba(255,255,255,0.05)';this.style.color='rgba(255,255,255,0.7)'">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Build a Program
                </a>
                <a href="{{ route('tenant.join') }}"
                   style="flex:1;display:flex;align-items:center;justify-content:center;gap:.375rem;padding:.75rem 1rem;border-radius:1rem;border:1.5px solid rgba(255,255,255,0.12);background:rgba(255,255,255,0.05);color:rgba(255,255,255,0.7);font-size:.75rem;font-weight:600;text-decoration:none;transition:all .2s;font-family:'Inter',sans-serif"
                   onmouseover="this.style.background='rgba(255,255,255,0.09)';this.style.color='#fff'"
                   onmouseout="this.style.background='rgba(255,255,255,0.05)';this.style.color='rgba(255,255,255,0.7)'">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                    </svg>
                    Join a Program
                </a>
            </div>
        </div>

        {{-- Back + footer --}}
        <div class="flex flex-col items-center gap-3 mt-6 a5">
            <a href="{{ route('portal.select') }}"
               style="display:inline-flex;align-items:center;gap:.375rem;font-size:.75rem;color:rgba(255,255,255,.3);text-decoration:none;transition:color .15s"
               onmouseover="this.style.color='rgba(255,255,255,.6)'" onmouseout="this.style.color='rgba(255,255,255,.3)'">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back
            </a>
            <p class="text-white/20 text-xs">© {{ date('Y') }} ReferralBunny.ai · All rights reserved</p>
        </div>

    </div>
</body>
</html>
