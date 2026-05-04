<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin — ReferralBunny.ai</title>
    <link rel="icon" type="image/webp" href="/images/logos/referralbunny-favicon.webp">
    <link rel="apple-touch-icon" href="/images/logos/referralbunny-app-icon.webp">

    {{-- Open Graph / Social preview --}}
    <meta property="og:type"        content="website">
    <meta property="og:site_name"   content="ReferralBunny.ai">
    <meta property="og:title"       content="ReferralBunny.ai — Referral Management Platform">
    <meta property="og:description" content="Set up, manage, and grow your referral programs. Leads, partners, and commissions — all in one place.">
    <meta property="og:image"       content="{{ config('app.url') }}/images/logos/referralbunny-social-avatar.webp">
    <meta property="og:url"         content="{{ url()->current() }}">
    <meta name="twitter:card"       content="summary_large_image">
    <meta name="twitter:title"      content="ReferralBunny.ai — Referral Management Platform">
    <meta name="twitter:description" content="Set up, manage, and grow your referral programs. Leads, partners, and commissions — all in one place.">
    <meta name="twitter:image"      content="{{ config('app.url') }}/images/logos/referralbunny-social-avatar.webp">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
        }

        /* ── Intro overlay ──────────────────────────────────────── */
        #intro {
            position: fixed; inset: 0; z-index: 100;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            padding: 2rem 1.5rem;
            background: linear-gradient(145deg, #1E1347 0%, #2D1B69 55%, #1a1040 100%);
            transition: opacity .5s ease;
        }
        #intro.fading { opacity: 0; pointer-events: none; }
        #intro.gone   { display: none !important; }
        #intro-footer {
            position: absolute;
            bottom: 2rem; left: 1.5rem; right: 1.5rem;
            display: flex; align-items: center; justify-content: space-between;
        }

        .al { animation: aUp .8s ease-out .2s both; }
        .am { animation: aUp .8s ease-out .4s both; }
        .at { animation: aIn .6s ease-out .3s both; }
        .af { animation: aIn .5s ease-out .5s both; }
        .g1 { animation: gp 5s ease-in-out infinite; }
        .g2 { animation: gp 5s ease-in-out 2.5s infinite; }

        @keyframes aUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:none} }
        @keyframes aIn { from{opacity:0} to{opacity:1} }
        @keyframes gp  { 0%,100%{opacity:.18} 50%{opacity:.3} }
        @media (prefers-reduced-motion: reduce) { #intro { display: none !important; } }

        /* ── Card ───────────────────────────────────────────────── */
        .login-page {
            width: 100%;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .login-card {
            width: 100%;
            max-width: 900px;
            display: flex;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 32px 80px rgba(0,0,0,.45);
            min-height: 580px;
        }
        .card-left {
            flex: 1;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 3rem;
        }
        .card-right {
            width: 380px;
            flex-shrink: 0;
            background: linear-gradient(145deg, #1E1347 0%, #2D1B69 60%, #1a1040 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2.5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        @media (max-width: 767px) {
            .card-right { display: none !important; }
            .card-left  { padding: 2.5rem 1.75rem; }
        }

        /* ── Form ───────────────────────────────────────────────── */
        .form-wrap { max-width: 340px; }
        .field-label {
            display: block; font-size: .8125rem; font-weight: 500;
            color: #374151; margin-bottom: .375rem;
        }
        .field-input {
            width: 100%; border: 1px solid #e5e7eb; background: #f9fafb;
            border-radius: 12px; padding: .75rem 1rem; font-size: .875rem;
            color: #111827; outline: none; font-family: 'Inter', sans-serif;
            transition: border-color .15s, box-shadow .15s, background .15s;
        }
        .field-input:focus {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139,92,246,.15);
            background: #fff;
        }
        .field-input::placeholder { color: #9ca3af; }
        .btn-primary {
            width: 100%; background: #7c3aed; color: #fff;
            border: none; border-radius: 12px; padding: .8125rem 1rem;
            font-size: .875rem; font-weight: 600; cursor: pointer;
            transition: background .15s; font-family: 'Inter', sans-serif;
            margin-top: .5rem;
        }
        .btn-primary:hover  { background: #6d28d9; }
        .btn-primary:active { background: #5b21b6; }
        .pw-wrap { position: relative; }
        .pw-toggle {
            position: absolute; right: .875rem; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; color: #9ca3af;
            padding: 0; display: flex; align-items: center;
        }
        .pw-toggle:hover { color: #6b7280; }
        .error-box {
            display: flex; align-items: flex-start; gap: .75rem;
            background: #fef2f2; border: 1px solid #fecaca;
            border-radius: 12px; padding: .75rem 1rem; margin-bottom: 1.25rem;
        }
        .error-box p { margin: 0; font-size: .875rem; color: #dc2626; }
    </style>
</head>
<body>

{{-- ═══════════ INTRO ═══════════════════════════════════════ --}}
<div id="intro">
    <div class="g1" style="position:absolute;width:500px;height:500px;border-radius:50%;top:-200px;left:-200px;pointer-events:none;background:radial-gradient(circle,#7C3AED,transparent 65%)"></div>
    <div class="g2" style="position:absolute;width:400px;height:400px;border-radius:50%;bottom:-150px;right:-150px;pointer-events:none;background:radial-gradient(circle,#3B82F6,transparent 65%)"></div>

    <div class="al" style="position:absolute;top:2rem;left:50%;transform:translateX(-50%);z-index:1">
        <x-rb-logo variant="white" size="sm" :priority="true" :decorative="true" />
    </div>

    <div style="position:relative;z-index:1;display:flex;flex-direction:column;align-items:center;gap:.25rem;text-align:center">
        <div class="at" style="padding:0 1rem">
            <h1 style="margin:0;font-size:1.5rem;font-weight:700;color:#ffffff !important;letter-spacing:-.02em;text-shadow:0 1px 8px rgba(0,0,0,.3)">Welcome to ReferralBunny.ai</h1>
            <p style="margin:.375rem 0 0;font-size:.875rem;color:rgba(255,255,255,.7) !important">The referral management platform.</p>
        </div>
        <div class="am" style="width:220px;height:220px;flex-shrink:0">
            <x-r-bunny variant="rocket" size="lg" :decorative="true" style="width:220px;height:220px;object-fit:contain" />
        </div>
    </div>

    <div id="intro-footer" class="af">
        <span style="font-size:.625rem;color:rgba(255,255,255,.3);letter-spacing:.15em;text-transform:uppercase">Super Admin Portal</span>
        <button onclick="skipIntro()" style="background:none;border:none;color:rgba(255,255,255,.4);font-size:.875rem;cursor:pointer;padding:.25rem .5rem;border-radius:6px;font-family:'Inter',sans-serif" onmouseover="this.style.color='rgba(255,255,255,.8)'" onmouseout="this.style.color='rgba(255,255,255,.4)'">Skip</button>
    </div>
</div>

{{-- ═══════════ LOGIN PAGE ════════════════════════════════════ --}}
<div class="login-page">
    <div class="login-card">

        {{-- LEFT — form --}}
        <div class="card-left">
            <div style="margin-bottom:2rem">
                <x-rb-logo variant="horizontal" size="sm" :priority="true" :decorative="true" />
            </div>

            <h1 style="margin:0 0 .25rem;font-size:1.5rem;font-weight:700;color:#111827">Sign in to your account</h1>
            <p style="margin:0 0 2rem;font-size:.6875rem;font-weight:600;color:#9ca3af;letter-spacing:.1em;text-transform:uppercase">Super Admin Portal</p>

            @if ($errors->any())
                <div class="error-box">
                    <x-r-bunny variant="warning" size="xs" :decorative="true" style="flex-shrink:0;margin-top:2px" />
                    <p>{{ $errors->first() }}</p>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="form-wrap">
                @csrf
                <div style="margin-bottom:1rem">
                    <label for="email" class="field-label">Email address</label>
                    <input id="email" name="email" type="email" autocomplete="email" required autofocus
                           value="{{ old('email') }}" class="field-input" placeholder="admin@referralbunny.com">
                </div>

                <div style="margin-bottom:.25rem">
                    <label for="password" class="field-label">Password</label>
                    <div class="pw-wrap" x-data="{ show: false }">
                        <input id="password" name="password" :type="show ? 'text' : 'password'"
                               autocomplete="current-password" required
                               class="field-input" style="padding-right:2.75rem"
                               placeholder="••••••••">
                        <button type="button" class="pw-toggle" @click="show = !show" tabindex="-1">
                            <svg x-show="!show" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="show"  width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                </div>

                <div style="display:flex;align-items:center;margin:.75rem 0">
                    <label style="display:flex;align-items:center;gap:.5rem;font-size:.8125rem;color:#6b7280;cursor:pointer">
                        <input type="checkbox" name="remember" style="accent-color:#7c3aed;width:14px;height:14px">
                        Remember me
                    </label>
                </div>

                <button type="submit" class="btn-primary">Sign In</button>
            </form>
        </div>

        {{-- RIGHT — brand panel --}}
        <div class="card-right">
            <div style="position:absolute;width:260px;height:260px;border-radius:50%;top:-80px;right:-80px;opacity:.25;pointer-events:none;background:radial-gradient(circle,#7C3AED,transparent 65%)"></div>
            <div style="position:absolute;width:200px;height:200px;border-radius:50%;bottom:-60px;left:-60px;opacity:.2;pointer-events:none;background:radial-gradient(circle,#3B82F6,transparent 65%)"></div>
            <div style="position:relative;z-index:1">
                <x-r-bunny variant="portal" size="lg" :decorative="true" style="display:block;margin:0 auto 1.25rem" />
                <x-rb-logo variant="white" size="xs" :priority="true" :decorative="true" style="display:block;margin:0 auto 1rem;opacity:.9" />
                <h2 style="margin:0 0 .5rem;font-size:1.125rem;font-weight:700;color:#fff;line-height:1.4">Manage the entire<br>platform from here.</h2>
                <p style="margin:0 auto;font-size:.75rem;color:rgba(255,255,255,.5);line-height:1.6;max-width:180px">Tenants, billing, analytics — full control.</p>
            </div>
        </div>
    </div>
</div>

<script>
    var intro = document.getElementById('intro');
    var timer = setTimeout(advance, 5000);
    function advance() {
        clearTimeout(timer);
        if (!intro) return;
        intro.classList.add('fading');
        setTimeout(function(){ intro.classList.add('gone'); }, 500);
    }
    function skipIntro() { advance(); }
</script>
</body>
</html>
