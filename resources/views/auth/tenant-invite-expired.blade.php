<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.google-analytics')
    <title>Invitation Expired — ReferralBunny.ai</title>
    <link rel="icon" type="image/webp" href="/images/logos/referralbunny-favicon.webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 0; min-height: 100vh;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #1E1347 0%, #2D1B69 55%, #1a1040 100%);
            display: flex; align-items: center; justify-content: center;
        }
        .card {
            width: 100%; max-width: 400px; margin: 2rem 1.25rem;
            background: #fff; border-radius: 24px; overflow: hidden;
            box-shadow: 0 32px 80px rgba(0,0,0,.45);
            padding: 2.5rem 2rem; text-align: center;
        }
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            gap: .5rem; background: #7c3aed; color: #fff; border: none;
            border-radius: 12px; padding: .75rem 1.5rem; font-size: .875rem;
            font-weight: 600; cursor: pointer; text-decoration: none;
            transition: background .15s; font-family: 'Inter', sans-serif;
        }
        .btn:hover { background: #6d28d9; }
    </style>
</head>
<body>
<div style="display:flex;align-items:center;justify-content:center;min-height:100vh;padding:2rem 1.25rem">
    <div class="card">
        <div style="margin-bottom:1.25rem">
            <x-rb-logo variant="horizontal" size="sm" :priority="true" :decorative="true" />
        </div>
        <div style="margin:0 auto 1rem;display:flex;justify-content:center">
            <img src="/images/mascots/r-bunny-warning-error.webp"
                 alt="" width="80" height="80"
                 style="width:80px;height:80px;object-fit:contain">
        </div>
        <h1 style="margin:0 0 .5rem;font-size:1.25rem;font-weight:700;color:#111827">Invitation Unavailable</h1>
        <p style="margin:0 0 1.75rem;font-size:.875rem;color:#6b7280;line-height:1.6">
            This invitation link is invalid, has expired, or has already been accepted.
            Please contact the workspace administrator to send a new invitation.
        </p>
        <a href="{{ route('tenant.login') }}" class="btn">Back to Sign In</a>
    </div>
</div>
</body>
</html>
