<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ReferralBunny.ai — Referral Management Platform</title>

    <link rel="icon" type="image/webp" href="/images/logos/referralbunny-favicon.webp">
    <link rel="apple-touch-icon" href="/images/logos/referralbunny-app-icon.webp">

    {{-- Open Graph --}}
    <meta property="og:type"        content="website">
    <meta property="og:site_name"   content="ReferralBunny.ai">
    <meta property="og:title"       content="ReferralBunny.ai — Referral Management Platform">
    <meta property="og:description" content="Set up, manage, and grow your referral programs. Leads, partners, and commissions — all in one place.">
    <meta property="og:image"       content="https://referralbunny.ai/images/logos/referralbunny-social-avatar.png">
    <meta property="og:image:width"  content="1200">
    <meta property="og:image:height" content="1200">
    <meta property="og:image:type"   content="image/png">
    <meta property="og:url"         content="https://referralbunny.ai">
    <meta name="description"        content="Set up, manage, and grow your referral programs. Leads, partners, and commissions — all in one place.">

    {{-- Twitter / X --}}
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="ReferralBunny.ai — Referral Management Platform">
    <meta name="twitter:description" content="Set up, manage, and grow your referral programs. Leads, partners, and commissions — all in one place.">
    <meta name="twitter:image"       content="https://referralbunny.ai/images/logos/referralbunny-social-avatar.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])

    <style>
        body {
            margin: 0; font-family: 'Inter', sans-serif;
            min-height: 100vh; display: flex; flex-direction: column;
            align-items: center; justify-content: center; text-align: center;
            background: linear-gradient(145deg, #1E1347 0%, #2D1B69 55%, #1a1040 100%);
            padding: 2rem;
        }
    </style>

    {{-- Redirect authenticated users immediately --}}
    <script>
        // Non-scraper visitors: redirect to login
        if (!navigator.userAgent.includes('facebookexternalhit') &&
            !navigator.userAgent.includes('Twitterbot') &&
            !navigator.userAgent.includes('LinkedInBot')) {
            window.location.replace('/tenant/login');
        }
    </script>
</head>
<body>
    <div>
        <x-rb-logo variant="stacked" size="lg" :priority="true" :decorative="true" class="mx-auto mb-6" />
        <h1 style="color:#fff;font-size:1.75rem;font-weight:700;margin:0 0 .75rem">
            Referral Management Platform
        </h1>
        <p style="color:rgba(255,255,255,.6);font-size:1rem;max-width:420px;margin:0 auto 2rem;line-height:1.6">
            Set up, manage, and grow your referral programs. Leads, partners, and commissions — all in one place.
        </p>
        <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap">
            <a href="/tenant/login"
               style="background:#7c3aed;color:#fff;text-decoration:none;padding:.75rem 1.5rem;border-radius:12px;font-weight:600;font-size:.875rem">
                Sign In
            </a>
            <a href="/tenant/create"
               style="background:rgba(255,255,255,.1);color:#fff;text-decoration:none;padding:.75rem 1.5rem;border-radius:12px;font-weight:600;font-size:.875rem;border:1px solid rgba(255,255,255,.2)">
                Get Started
            </a>
        </div>
    </div>
</body>
</html>
