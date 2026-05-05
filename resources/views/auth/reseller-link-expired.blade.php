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
        body {
            margin: 0; padding: 0; min-height: 100vh;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #052E2A 0%, #0F6B5F 55%, #042320 100%);
            display: flex; align-items: center; justify-content: center;
        }
        .page { width: 100%; padding: 1.5rem; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { width: 100%; max-width: 900px; display: flex; border-radius: 24px; overflow: hidden; box-shadow: 0 32px 80px rgba(0,0,0,.45); min-height: 520px; }
        .card-left { flex: 1; background: #ffffff; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 3rem; text-align: center; }
        .card-right {
            width: 380px; flex-shrink: 0;
            background: linear-gradient(145deg, #073D37 0%, #0F766E 60%, #052E2A 100%);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 2.5rem; text-align: center; position: relative; overflow: hidden;
        }
        @media (max-width: 767px) { .card-right { display: none !important; } .card-left { padding: 2.5rem 1.75rem; } }
        .btn-primary { display: inline-block; background: #0D9488; color: #fff; text-decoration: none; font-size: .875rem; font-weight: 600; padding: .8125rem 2rem; border-radius: 12px; transition: background .15s; margin-top: 1.5rem; }
        .btn-primary:hover { background: #0F766E; }
        .btn-ghost { display: inline-block; color: #6b7280; text-decoration: none; font-size: .8125rem; margin-top: .75rem; }
        .btn-ghost:hover { color: #374151; }
    </style>
</head>
<body>
<div class="page">
    <div class="card">

        {{-- LEFT --}}
        <div class="card-left">
            <div style="margin-bottom:2rem">
                <x-rb-logo variant="horizontal" size="sm" :priority="true" :decorative="true" style="margin:0 auto" />
            </div>

            <div style="width:72px;height:72px;border-radius:50%;background:#FEF2F2;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem">
                <svg width="32" height="32" fill="none" stroke="#EF4444" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>

            <h1 style="margin:0 0 .75rem;font-size:1.5rem;font-weight:700;color:#111827">Invitation link expired</h1>
            <p style="margin:0 0 .5rem;font-size:.9375rem;color:#4B5563;line-height:1.7;max-width:320px">
                This setup link is no longer valid. It may have already been used or the invitation has expired.
            </p>
            <p style="margin:0;font-size:.875rem;color:#9CA3AF;max-width:300px">
                Contact your program administrator to send you a new invitation.
            </p>

            <a href="{{ route('reseller.login') }}" class="btn-primary">Go to Sign In</a>
            <br>
            <a href="mailto:admin@referralbunny.ai" class="btn-ghost">Contact administrator</a>
        </div>

        {{-- RIGHT --}}
        <div class="card-right">
            <div style="position:absolute;width:260px;height:260px;border-radius:50%;top:-80px;right:-80px;opacity:.2;pointer-events:none;background:radial-gradient(circle,#0D9488,transparent 65%)"></div>
            <div style="position:absolute;width:200px;height:200px;border-radius:50%;bottom:-60px;left:-60px;opacity:.15;pointer-events:none;background:radial-gradient(circle,#14B8A6,transparent 65%)"></div>
            <div style="position:relative;z-index:1">
                <x-r-bunny variant="helper" size="lg" :decorative="true" style="display:block;margin:0 auto 1.25rem" />
                <x-rb-logo variant="white" size="xs" :priority="true" :decorative="true" style="display:block;margin:0 auto 1rem;opacity:.9" />
                <h2 style="margin:0 0 .5rem;font-size:1.125rem;font-weight:700;color:#fff;line-height:1.4">Need a new invite?</h2>
                <p style="margin:0 auto;font-size:.75rem;color:rgba(255,255,255,.5);line-height:1.6;max-width:200px">Ask your program administrator to resend your invitation from the referrers list.</p>
            </div>
        </div>

    </div>
</div>
</body>
</html>
