<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.google-analytics')
    <title>Forgot Password — ReferralBunny.ai Partner Portal</title>
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
    </style>
</head>
<body>
<div class="card">
    <div style="margin-bottom:2rem">
        <x-rb-logo variant="horizontal" size="sm" :priority="true" :decorative="true" />
    </div>

    <x-r-bunny variant="helper" size="sm" :decorative="true" style="display:block;margin:0 auto 1.25rem" />

    <h1 style="margin:0 0 .25rem;font-size:1.375rem;font-weight:700;color:#111827;text-align:center">Forgot your password?</h1>
    <p style="margin:0 0 2rem;font-size:.875rem;color:#6b7280;text-align:center">Enter your email and we'll send you a reset link.</p>

    @if (session('success'))
    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:.75rem 1rem;margin-bottom:1.25rem;font-size:.875rem;color:#166534">
        {{ session('success') }}
    </div>
    @endif

    @if ($errors->any())
    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:.75rem 1rem;margin-bottom:1.25rem;font-size:.875rem;color:#dc2626">
        {{ $errors->first() }}
    </div>
    @endif

    <form method="POST" action="{{ route('partner.forgot-password.post') }}" x-data="{ sub: false }" @submit="sub=true">
        @csrf
        <div style="margin-bottom:1.25rem">
            <label for="email" class="field-label">Email address</label>
            <input id="email" name="email" type="email" required value="{{ old('email') }}" class="field-input" placeholder="you@email.com">
        </div>
        <button type="submit" class="btn-primary" :disabled="sub" x-text="sub ? 'Sending...' : 'Send Reset Link'">Send Reset Link</button>
    </form>

    <p style="text-align:center;margin-top:1.25rem;font-size:.8125rem;color:#6b7280">
        <a href="{{ route('partner.login') }}" style="color:#2563EB;text-decoration:none">← Back to Sign In</a>
    </p>
</div>
</body>
</html>
