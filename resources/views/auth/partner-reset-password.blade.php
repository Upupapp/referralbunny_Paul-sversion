<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.google-analytics')
    <title>Reset Password — ReferralBunny.ai Partner Portal</title>
    <link rel="icon" type="image/webp" href="/images/logos/referralbunny-favicon.webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { box-sizing: border-box; }
        body { margin:0; padding:0; min-height:100vh; font-family:'Inter',sans-serif; background:linear-gradient(145deg,#0D1B2A 0%,#1A2F50 55%,#0A1628 100%); display:flex; align-items:center; justify-content:center; }
        .card { width:100%; max-width:440px; background:#fff; border-radius:24px; padding:3rem; box-shadow:0 32px 80px rgba(0,0,0,.45); margin:1.5rem; }
        .field-label { display:block; font-size:.8125rem; font-weight:500; color:#374151; margin-bottom:.375rem; }
        .field-input { width:100%; border:1px solid #e5e7eb; background:#f9fafb; border-radius:12px; padding:.75rem 1rem; font-size:.875rem; color:#111827; outline:none; transition:border-color .15s,box-shadow .15s; font-family:'Inter',sans-serif; }
        .field-input:focus { border-color:#3B82F6; box-shadow:0 0 0 3px rgba(37,99,235,.15); background:#fff; }
        .btn-primary { width:100%; background:#2563EB; color:#fff; border:none; border-radius:12px; padding:.8125rem 1rem; font-size:.875rem; font-weight:600; cursor:pointer; transition:background .15s; font-family:'Inter',sans-serif; margin-top:.5rem; }
        .btn-primary:hover { background:#1D4ED8; }
        .btn-primary:disabled { opacity:.65; cursor:not-allowed; }
        .pw-wrap { position:relative; }
        .pw-toggle { position:absolute; right:.875rem; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:#9ca3af; padding:0; display:flex; align-items:center; }
        .pw-toggle:hover { color:#6b7280; }
    </style>
</head>
<body>
<div class="card" x-data="{ show1: false, show2: false, sub: false }">
    <div style="margin-bottom:2rem">
        <x-rb-logo variant="horizontal" size="sm" :priority="true" :decorative="true" />
    </div>

    <h1 style="margin:0 0 .25rem;font-size:1.375rem;font-weight:700;color:#111827;text-align:center">Set a new password</h1>
    <p style="margin:0 0 2rem;font-size:.875rem;color:#6b7280;text-align:center">Choose a strong password for your Partner account.</p>

    @if ($errors->any())
    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:.75rem 1rem;margin-bottom:1.25rem;font-size:.875rem;color:#dc2626">
        {{ $errors->first() }}
    </div>
    @endif

    <form method="POST" action="{{ route('partner.reset-password.post') }}" @submit="sub=true">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ $email }}">
        <div style="margin-bottom:1rem">
            <label class="field-label">New Password <span style="font-size:.75rem;color:#9ca3af;font-weight:400">— min. 8 characters</span></label>
            <div class="pw-wrap">
                <input name="password" :type="show1 ? 'text' : 'password'" required minlength="8" class="field-input" style="padding-right:2.75rem" placeholder="At least 8 characters">
                <button type="button" class="pw-toggle" @click="show1 = !show1" tabindex="-1">
                    <svg x-show="!show1" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <svg x-show="show1"  width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/></svg>
                </button>
            </div>
        </div>
        <div style="margin-bottom:1.5rem">
            <label class="field-label">Confirm Password</label>
            <div class="pw-wrap">
                <input name="password_confirmation" :type="show2 ? 'text' : 'password'" required class="field-input" style="padding-right:2.75rem" placeholder="Repeat your password">
                <button type="button" class="pw-toggle" @click="show2 = !show2" tabindex="-1">
                    <svg x-show="!show2" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <svg x-show="show2"  width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/></svg>
                </button>
            </div>
        </div>
        <button type="submit" class="btn-primary" :disabled="sub" x-text="sub ? 'Updating...' : 'Update Password'">Update Password</button>
    </form>

    <p style="text-align:center;margin-top:1.25rem;font-size:.8125rem;color:#6b7280">
        <a href="{{ route('partner.login') }}" style="color:#2563EB;text-decoration:none">← Back to Sign In</a>
    </p>
</div>
</body>
</html>
