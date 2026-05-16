<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.google-analytics')
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
                <img src="/images/logos/referralbunny-icon-only.webp"
                     class="w-12 h-12 rounded-xl object-cover" alt="ReferralBunny.ai">
            </div>
            <h1 class="text-white font-bold text-2xl leading-tight">ReferralBunny.ai</h1>
            <p class="text-white/50 text-sm mt-1">Build your own referral program or join an existing one.</p>
        </div>

        {{-- Portal cards --}}
        <div class="flex flex-col gap-3">

            {{-- Build a Referral Program --}}
            <a href="{{ route('tenant.create') }}" class="portal-card portal-card-tenant a2">
                <div class="flex items-center justify-between w-full">
                    <div class="icon-wrap" style="background: rgba(123,97,255,0.2)">
                        <svg class="w-6 h-6" style="color: #a78bfa" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                                  d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                        </svg>
                    </div>
                    <svg class="w-5 h-5 text-white card-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </div>
                <div>
                    <p class="text-white font-semibold text-base leading-tight">Build a Referral Program</p>
                    <p class="text-white/50 text-sm mt-0.5">Create a workspace and manage your referrers, deals, and commissions.</p>
                </div>
            </a>

            {{-- Join a Referral Program --}}
            <a href="{{ route('tenant.join') }}" class="portal-card portal-card-reseller a3">
                <div class="flex items-center justify-between w-full">
                    <div class="icon-wrap" style="background: rgba(20,184,166,0.2)">
                        <svg class="w-6 h-6" style="color: #2dd4bf" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                                  d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                        </svg>
                    </div>
                    <svg class="w-5 h-5 text-white card-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </div>
                <div>
                    <p class="text-white font-semibold text-base leading-tight">Join a Referral Program</p>
                    <p class="text-white/50 text-sm mt-0.5">Accept an invite, enter a program code, or find an existing program.</p>
                </div>
            </a>

            {{-- Sign In button --}}
            <div class="a4" style="margin-top:.75rem">
                <a href="{{ route('signin.select') }}"
                   style="display:flex;align-items:center;justify-content:center;gap:.5rem;width:100%;padding:.875rem 1rem;border-radius:1.25rem;border:1.5px solid rgba(255,255,255,0.18);background:rgba(255,255,255,0.06);color:#fff;font-size:.875rem;font-weight:600;text-decoration:none;transition:all .2s ease;font-family:'Inter',sans-serif"
                   onmouseover="this.style.background='rgba(255,255,255,0.10)';this.style.borderColor='rgba(255,255,255,0.28)'"
                   onmouseout="this.style.background='rgba(255,255,255,0.06)';this.style.borderColor='rgba(255,255,255,0.18)'">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                    </svg>
                    Sign In
                </a>
            </div>

        </div>

        {{-- Footer --}}
        <p class="text-center text-white/30 text-xs mt-8 a4">
            © {{ date('Y') }} ReferralBunny.ai · All rights reserved
        </p>

    </div>
</body>
</html>
