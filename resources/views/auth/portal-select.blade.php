<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome — ReferralBunny.ai</title>
    <link rel="icon" type="image/webp" href="/images/logos/referralbunny-favicon.webp">
    <link rel="apple-touch-icon" href="/images/logos/referralbunny-app-icon.webp">

    <meta property="og:type"        content="website">
    <meta property="og:site_name"   content="ReferralBunny.ai">
    <meta property="og:title"       content="ReferralBunny.ai — Welcome">
    <meta property="og:description" content="Sign in to your ReferralBunny.ai portal.">
    <meta property="og:image"       content="https://referralbunny.ai/images/logos/referralbunny-social-avatar.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 0;
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #1E1347 0%, #2D1B69 55%, #1a1040 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .portal-card {
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.12);
            border-radius: 1.25rem;
            padding: 2rem 1.75rem;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.75rem;
            position: relative;
            overflow: hidden;
        }
        .portal-card:hover {
            background: rgba(255,255,255,0.10);
            border-color: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }
        .portal-card:hover .card-arrow {
            transform: translateX(4px);
            opacity: 1;
        }
        .card-arrow {
            transition: all 0.2s ease;
            opacity: 0.5;
        }
        .portal-card-tenant:hover {
            border-color: rgba(123,97,255,0.5);
            box-shadow: 0 20px 40px rgba(123,97,255,0.15);
        }
        .portal-card-reseller:hover {
            border-color: rgba(20,184,166,0.5);
            box-shadow: 0 20px 40px rgba(20,184,166,0.15);
        }
        .icon-wrap {
            width: 3rem; height: 3rem;
            border-radius: 0.875rem;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .a1 { animation: fadeUp 0.6s ease-out 0.1s both; }
        .a2 { animation: fadeUp 0.6s ease-out 0.25s both; }
        .a3 { animation: fadeUp 0.6s ease-out 0.4s both; }
        .a4 { animation: fadeUp 0.6s ease-out 0.55s both; }
    </style>
</head>
<body>
    <div class="w-full max-w-sm">

        {{-- Logo + mascot --}}
        <div class="flex flex-col items-center text-center mb-8 a1">
            <div class="w-16 h-16 rounded-2xl flex items-center justify-center mb-4 shadow-lg"
                 style="background: linear-gradient(135deg, #FF6CAB, #7B61FF)">
                <img src="/images/logos/referralbunny-social-avatar.png"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'"
                     class="w-12 h-12 rounded-xl object-cover" alt="R Bunny">
                <span class="text-3xl hidden">🐰</span>
            </div>
            <h1 class="text-white font-bold text-2xl leading-tight">ReferralBunny.ai</h1>
            <p class="text-white/50 text-sm mt-1">Choose how you'd like to sign in</p>
        </div>

        {{-- Portal cards --}}
        <div class="flex flex-col gap-3">

            {{-- Workspace Admin --}}
            <a href="{{ route('tenant.login') }}" class="portal-card portal-card-tenant a2">
                <div class="flex items-center justify-between w-full">
                    <div class="icon-wrap" style="background: rgba(123,97,255,0.2)">
                        <svg class="w-6 h-6" style="color: #a78bfa" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                                  d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                    <svg class="w-5 h-5 text-white card-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </div>
                <div>
                    <p class="text-white font-semibold text-base leading-tight">Workspace Admin</p>
                    <p class="text-white/50 text-sm mt-0.5">Manage deals, referrers, and commissions</p>
                </div>
            </a>

            {{-- Referrer / Reseller --}}
            <a href="{{ route('reseller.login') }}" class="portal-card portal-card-reseller a3">
                <div class="flex items-center justify-between w-full">
                    <div class="icon-wrap" style="background: rgba(20,184,166,0.2)">
                        <svg class="w-6 h-6" style="color: #2dd4bf" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                                  d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <svg class="w-5 h-5 text-white card-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </div>
                <div>
                    <p class="text-white font-semibold text-base leading-tight">Referrer / Reseller</p>
                    <p class="text-white/50 text-sm mt-0.5">Track your deals and commission status</p>
                </div>
            </a>

        </div>

        {{-- Footer --}}
        <p class="text-center text-white/30 text-xs mt-8 a4">
            © {{ date('Y') }} ReferralBunny.ai · All rights reserved
        </p>

    </div>
</body>
</html>
