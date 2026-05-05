<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — ReferralBunny.ai Tenant Admin</title>
    <link rel="icon" type="image/webp" href="/images/logos/referralbunny-favicon.webp">
    <link rel="apple-touch-icon" href="/images/logos/referralbunny-app-icon.webp">

    {{-- Open Graph / Social preview --}}
    <meta property="og:type"        content="website">
    <meta property="og:site_name"   content="ReferralBunny.ai">
    <meta property="og:title"       content="ReferralBunny.ai — Tenant Admin Portal">
    <meta property="og:description" content="Sign in to your ReferralBunny.ai Tenant Admin Portal. Manage your referral programs, leads, partners, and commissions.">
    <meta property="og:image"       content="https://referralbunny.ai/images/logos/referralbunny-social-avatar.png">
    <meta property="og:url"         content="{{ url()->current() }}">
    <meta name="twitter:card"       content="summary_large_image">
    <meta name="twitter:title"      content="ReferralBunny.ai — Tenant Admin Portal">
    <meta name="twitter:description" content="Sign in to your ReferralBunny.ai Tenant Admin Portal.">
    <meta name="twitter:image"      content="https://referralbunny.ai/images/logos/referralbunny-social-avatar.png">

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
            padding: 3rem 3rem;
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

        /* ── Form elements ──────────────────────────────────────── */
        .form-wrap { max-width: 340px; }
        .field-label {
            display: block;
            font-size: .8125rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: .375rem;
        }
        .field-input {
            width: 100%;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            border-radius: 12px;
            padding: .75rem 1rem;
            font-size: .875rem;
            color: #111827;
            outline: none;
            transition: border-color .15s, box-shadow .15s, background .15s;
            font-family: 'Inter', sans-serif;
        }
        .field-input:focus {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139,92,246,.15);
            background: #fff;
        }
        .field-input::placeholder { color: #9ca3af; }
        .btn-primary {
            width: 100%;
            background: #7c3aed;
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: .8125rem 1rem;
            font-size: .875rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s;
            font-family: 'Inter', sans-serif;
            margin-top: .5rem;
        }
        .btn-primary:hover  { background: #6d28d9; }
        .btn-primary:active { background: #5b21b6; }
        .btn-secondary {
            flex: 1;
            border: 1px solid #e5e7eb;
            background: #fff;
            border-radius: 12px;
            padding: .625rem 1rem;
            font-size: .75rem;
            font-weight: 500;
            color: #4b5563;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: border-color .15s, color .15s, background .15s;
            font-family: 'Inter', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .btn-secondary:hover { border-color: #8b5cf6; color: #7c3aed; background: #f5f3ff; }
        .divider {
            display: flex; align-items: center; gap: .75rem;
            margin: 1.25rem 0 1rem;
        }
        .divider::before, .divider::after {
            content: ''; flex: 1; height: 1px; background: #f3f4f6;
        }
        .divider span { font-size: .75rem; color: #9ca3af; }
        .pw-wrap { position: relative; }
        .pw-toggle {
            position: absolute; right: .875rem; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; color: #9ca3af; padding: 0;
            display: flex; align-items: center;
        }
        .pw-toggle:hover { color: #6b7280; }
        .forgot {
            display: block; text-align: right; font-size: .75rem;
            color: #7c3aed; text-decoration: none; margin-top: .375rem;
        }
        .forgot:hover { text-decoration: underline; }
        .error-box {
            display: flex; align-items: flex-start; gap: .75rem;
            background: #fef2f2; border: 1px solid #fecaca;
            border-radius: 12px; padding: .75rem 1rem; margin-bottom: 1.25rem;
        }
        .error-box p { margin: 0; font-size: .875rem; color: #dc2626; }
    </style>
</head>
<body>

<div class="login-page">
    <div class="login-card">

        {{-- LEFT — form --}}
        <div class="card-left">
            <div style="margin-bottom:2rem">
                <x-rb-logo variant="horizontal" size="sm" :priority="true" :decorative="true" />
            </div>

            <h1 style="margin:0 0 .25rem;font-size:1.5rem;font-weight:700;color:#111827">Sign in to your account</h1>
            <p style="margin:0 0 2rem;font-size:.6875rem;font-weight:600;color:#9ca3af;letter-spacing:.1em;text-transform:uppercase">Tenant Admin Portal</p>

            @if ($errors->any())
                <div class="error-box">
                    <x-r-bunny variant="warning" size="xs" :decorative="true" style="flex-shrink:0;margin-top:2px" />
                    <p>{{ $errors->first() }}</p>
                </div>
            @endif

            <form method="POST" action="{{ route('tenant.login.post') }}" class="form-wrap">
                @csrf
                <div style="margin-bottom:1rem">
                    <label for="email" class="field-label">Email address</label>
                    <input id="email" name="email" type="email" autocomplete="email" required
                           value="{{ old('email') }}" class="field-input" placeholder="you@company.com">
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
                    <a href="#" class="forgot">Forgot password?</a>
                </div>

                <div style="display:flex;align-items:flex-start;gap:.625rem;margin:.875rem 0 .75rem">
                    <input type="checkbox" id="remember_tenant" name="remember"
                           style="margin-top:2px;width:16px;height:16px;accent-color:#7c3aed;cursor:pointer;flex-shrink:0"
                           aria-describedby="remember_tenant_hint">
                    <div>
                        <label for="remember_tenant" style="font-size:.8125rem;font-weight:500;color:#374151;cursor:pointer;display:block">
                            Keep me signed in on this device
                        </label>
                        <p id="remember_tenant_hint" style="margin:.125rem 0 0;font-size:.6875rem;color:#9CA3AF;line-height:1.4">
                            Use this only on your personal or trusted device.
                        </p>
                    </div>
                </div>
                <button type="submit" class="btn-primary">Sign In</button>
            </form>

            <div class="divider form-wrap"><span>or</span></div>

            <div class="form-wrap" style="display:flex;gap:.5rem">
                <a href="{{ route('tenant.create') }}" class="btn-secondary">Create a tenant</a>
                <a href="{{ route('tenant.join') }}"   class="btn-secondary">Join a tenant</a>
            </div>

            <div class="form-wrap" style="padding-top:.5rem;border-top:1px solid #f3f4f6;margin-top:.25rem">
                <a href="{{ route('portal.select') }}"
                   style="display:inline-flex;align-items:center;gap:.375rem;font-size:.75rem;color:#9ca3af;text-decoration:none;transition:color .15s"
                   onmouseover="this.style.color='#7B61FF'" onmouseout="this.style.color='#9ca3af'">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to portal selection
                </a>
            </div>
        </div>

        {{-- RIGHT — brand panel --}}
        <div class="card-right">
            <div style="position:absolute;width:260px;height:260px;border-radius:50%;top:-80px;right:-80px;opacity:.25;pointer-events:none;background:radial-gradient(circle,#7C3AED,transparent 65%)"></div>
            <div style="position:absolute;width:200px;height:200px;border-radius:50%;bottom:-60px;left:-60px;opacity:.2;pointer-events:none;background:radial-gradient(circle,#3B82F6,transparent 65%)"></div>
            <div style="position:relative;z-index:1">
                <x-r-bunny variant="portal" size="lg" :decorative="true" style="display:block;margin:0 auto 1.25rem" />
                <x-rb-logo variant="white" size="xs" :priority="true" :decorative="true" style="display:block;margin:0 auto 1rem;opacity:.9" />
                <h2 style="margin:0 0 .5rem;font-size:1.125rem;font-weight:700;color:#fff;line-height:1.4">Grow your referral<br>programs with ease.</h2>
                <p style="margin:0;font-size:.75rem;color:rgba(255,255,255,.5);line-height:1.6;max-width:180px;margin:0 auto">Leads, partners, commissions — all in one place.</p>
            </div>
        </div>
    </div>
</div>

</body>
</html>
