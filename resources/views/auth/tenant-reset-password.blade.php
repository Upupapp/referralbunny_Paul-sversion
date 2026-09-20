<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.google-analytics')
    <title>Reset Password — ReferralBunny.ai Admin Portal</title>
    <link rel="icon" type="image/webp" href="/images/logos/referralbunny-favicon.webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { box-sizing: border-box; }
        body { margin:0; padding:0; min-height:100vh; font-family:'Inter',sans-serif; background:linear-gradient(135deg,#1E1B4B 0%,#4C3FA0 50%,#1E1B4B 100%); display:flex; align-items:center; justify-content:center; }
        .card { width:100%; max-width:440px; background:#fff; border-radius:24px; padding:3rem; box-shadow:0 32px 80px rgba(0,0,0,.45); margin:1.5rem; }
        .field-label { display:block; font-size:.8125rem; font-weight:500; color:#374151; margin-bottom:.375rem; }
        .field-input { width:100%; border:1px solid #e5e7eb; background:#f9fafb; border-radius:12px; padding:.75rem 1rem; font-size:.875rem; color:#111827; outline:none; transition:border-color .15s,box-shadow .15s; font-family:'Inter',sans-serif; }
        .field-input:focus { border-color:#7B61FF; box-shadow:0 0 0 3px rgba(123,97,255,.15); background:#fff; }
        .btn-primary { width:100%; background:#7B61FF; color:#fff; border:none; border-radius:12px; padding:.8125rem 1rem; font-size:.875rem; font-weight:600; cursor:pointer; transition:background .15s; font-family:'Inter',sans-serif; margin-top:.5rem; }
        .btn-primary:hover { background:#6D4FE8; }
        .error-msg { font-size:.8125rem; color:#EF4444; margin-top:.375rem; }
    </style>
</head>
<body>
<div class="card">
    <div style="margin-bottom:2rem">
        <x-rb-logo variant="horizontal" size="sm" :priority="true" :decorative="true" />
    </div>

    <h1 style="font-size:1.375rem;font-weight:700;color:#1E1B4B;margin:0 0 .5rem">Set new password</h1>
    <p style="font-size:.875rem;color:#6B7280;margin:0 0 1.75rem;line-height:1.6">Choose a strong password for your admin account.</p>

    @if($errors->any())
    <p class="error-msg" style="margin-bottom:1rem">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('tenant.reset-password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div style="margin-bottom:1.25rem">
            <label class="field-label" for="password">New password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required
                   class="field-input" placeholder="Minimum 8 characters">
        </div>

        <div style="margin-bottom:1.5rem">
            <label class="field-label" for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required
                   class="field-input" placeholder="Repeat your new password">
        </div>

        <button type="submit" class="btn-primary">Update password</button>
    </form>

    <p style="text-align:center;margin-top:1.5rem;font-size:.8125rem;color:#9CA3AF">
        <a href="{{ route('login') }}" style="color:#7B61FF;font-weight:600;text-decoration:none">Back to sign in</a>
    </p>
</div>
</body>
</html>
