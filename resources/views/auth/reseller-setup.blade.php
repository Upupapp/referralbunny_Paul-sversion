<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Up Your Account — ReferralBunny.ai</title>
    <link rel="icon" type="image/webp" href="/images/logos/referralbunny-favicon.webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; min-height: 100vh; font-family: 'Inter', sans-serif; background: linear-gradient(145deg, #052E2A 0%, #0F6B5F 55%, #042320 100%); display: flex; align-items: center; justify-content: center; }
        .setup-page { width: 100%; padding: 1.5rem; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .setup-card { width: 100%; max-width: 480px; background: #fff; border-radius: 24px; padding: 3rem; box-shadow: 0 32px 80px rgba(0,0,0,.45); }
        .field-label { display: block; font-size: .8125rem; font-weight: 500; color: #374151; margin-bottom: .375rem; }
        .field-input { width: 100%; border: 1px solid #e5e7eb; background: #f9fafb; border-radius: 12px; padding: .75rem 1rem; font-size: .875rem; color: #111827; outline: none; transition: border-color .15s, box-shadow .15s; font-family: 'Inter', sans-serif; }
        .field-input:focus { border-color: #2DD4BF; box-shadow: 0 0 0 3px rgba(13,148,136,.15); background: #fff; }
        .btn-primary { width: 100%; background: #0D9488; color: #fff; border: none; border-radius: 12px; padding: .8125rem 1rem; font-size: .875rem; font-weight: 600; cursor: pointer; transition: background .15s; font-family: 'Inter', sans-serif; margin-top: .5rem; }
        .btn-primary:hover { background: #0F766E; }
        .pw-wrap { position: relative; }
        .pw-toggle { position: absolute; right: .875rem; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #9ca3af; padding: 0; display: flex; align-items: center; }
        .error-box { background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: .75rem 1rem; margin-bottom: 1.25rem; font-size: .875rem; color: #dc2626; }
    </style>
</head>
<body>
<div class="setup-page">
    <div class="setup-card">
        <div style="margin-bottom:2rem;text-align:center">
            <x-rb-logo variant="horizontal" size="sm" :priority="true" :decorative="true" style="margin:0 auto" />
        </div>

        <div style="text-align:center;margin-bottom:2rem">
            <x-r-bunny variant="portal" size="sm" :decorative="true" style="margin:0 auto 1rem" />
            <h1 style="margin:0 0 .375rem;font-size:1.375rem;font-weight:700;color:#111827">Hi, {{ $reseller->name }}!</h1>
            <p style="margin:0 0 1rem;font-size:.875rem;color:#6b7280">Set up your account to start claiming deals.</p>
            <div style="background:#F0FDFA;border:1px solid #99F6E4;border-radius:12px;padding:.75rem 1rem">
                <p style="margin:0;font-size:.8125rem;font-weight:600;color:#0D9488">Every municipality needs a champion.</p>
                <p style="margin:.25rem 0 0;font-size:.75rem;color:#6b7280">Set your password and take on the challenge.</p>
            </div>
        </div>

        @if ($errors->any())
        <div class="error-box">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('reseller.setup.post') }}" x-data="{ show1: false, show2: false }">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div style="margin-bottom:1rem">
                <label class="field-label">New Password</label>
                <div class="pw-wrap">
                    <input name="password" :type="show1 ? 'text' : 'password'" required minlength="8"
                           class="field-input" style="padding-right:2.75rem" placeholder="At least 8 characters">
                    <button type="button" class="pw-toggle" @click="show1 = !show1" tabindex="-1">
                        <svg x-show="!show1" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <svg x-show="show1" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/></svg>
                    </button>
                </div>
            </div>

            <div style="margin-bottom:1.5rem">
                <label class="field-label">Confirm Password</label>
                <div class="pw-wrap">
                    <input name="password_confirmation" :type="show2 ? 'text' : 'password'" required
                           class="field-input" style="padding-right:2.75rem" placeholder="Repeat your password">
                    <button type="button" class="pw-toggle" @click="show2 = !show2" tabindex="-1">
                        <svg x-show="!show2" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <svg x-show="show2" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/></svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-primary">Activate My Account</button>
        </form>
    </div>
</div>
</body>
</html>
