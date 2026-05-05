<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Expired — ReferralBunny.ai</title>
    <link rel="icon" type="image/webp" href="/images/logos/referralbunny-favicon.webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; min-height: 100vh; font-family: 'Inter', sans-serif; background: linear-gradient(145deg, #052E2A 0%, #0F6B5F 55%, #042320 100%); display: flex; align-items: center; justify-content: center; }
        .card { width: 100%; max-width: 480px; background: #fff; border-radius: 24px; padding: 3rem; box-shadow: 0 32px 80px rgba(0,0,0,.45); margin: 1.5rem; text-align: center; }
        .btn { display: inline-block; background: #0D9488; color: #fff; text-decoration: none; font-size: .875rem; font-weight: 600; padding: .8125rem 2rem; border-radius: 12px; transition: background .15s; margin-top: 1.5rem; }
        .btn:hover { background: #0F766E; }
    </style>
</head>
<body>
<div class="card">
    <div style="margin-bottom:1.5rem">
        <x-rb-logo variant="horizontal" size="sm" :priority="true" :decorative="true" style="margin:0 auto" />
    </div>

    <x-r-bunny variant="warning" size="sm" :decorative="true" style="margin:0 auto 1.25rem" />

    <h1 style="margin:0 0 .75rem;font-size:1.375rem;font-weight:700;color:#111827">This link has expired</h1>
    <p style="margin:0 0 .5rem;font-size:.9375rem;color:#6b7280;line-height:1.6">
        This setup link is no longer valid — it may have already been used or has expired.
    </p>
    <p style="margin:0;font-size:.875rem;color:#9ca3af">
        Please contact your program administrator to receive a new invitation.
    </p>

    <a href="{{ route('reseller.login') }}" class="btn">Go to Sign In</a>
</div>
</body>
</html>
