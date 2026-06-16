<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'ReferralBunny.ai — Build and Manage Referral Programs')</title>
    <meta name="description" content="@yield('meta_description', 'ReferralBunny.ai is the platform to build and manage referral programs — recruit referrers and partners, track deals, and automate commissions.')">
    <link rel="canonical" href="@yield('canonical', 'https://referralbunny.ai/')">
    <meta name="robots" content="index, follow">

    <link rel="icon" type="image/webp" href="/images/logos/referralbunny-favicon.webp">
    @include('partials.google-analytics')
    <link rel="apple-touch-icon" href="/images/logos/referralbunny-app-icon.webp">

    {{-- Open Graph / Social preview --}}
    <meta property="og:type"        content="website">
    <meta property="og:site_name"   content="ReferralBunny.ai">
    <meta property="og:title"       content="@yield('og_title', 'ReferralBunny.ai — Build and Manage Referral Programs')">
    <meta property="og:description" content="@yield('og_description', 'ReferralBunny.ai is the platform to build and manage referral programs — recruit referrers and partners, track deals, and automate commissions.')">
    <meta property="og:image"       content="https://referralbunny.ai/images/logos/referralbunny-social-avatar.png">
    <meta property="og:url"         content="@yield('canonical', 'https://referralbunny.ai/')">
    <meta name="twitter:card"       content="summary_large_image">
    <meta name="twitter:title"      content="@yield('og_title', 'ReferralBunny.ai — Build and Manage Referral Programs')">
    <meta name="twitter:description" content="@yield('og_description', 'ReferralBunny.ai is the platform to build and manage referral programs — recruit referrers and partners, track deals, and automate commissions.')">
    <meta name="twitter:image"      content="https://referralbunny.ai/images/logos/referralbunny-social-avatar.png">

    {{-- Structured data — real product/org info only, no fake reviews/ratings/pricing --}}
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@type": "Organization",
        "name": "ReferralBunny.ai",
        "url": "https://referralbunny.ai/",
        "logo": "https://referralbunny.ai/images/logos/referralbunny-primary-logo.webp"
    }
    </script>
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@type": "SoftwareApplication",
        "name": "ReferralBunny",
        "applicationCategory": "BusinessApplication",
        "operatingSystem": "Web",
        "url": "https://referralbunny.ai/",
        "description": "Referral program management platform for building, launching, and growing referral programs with referrers and partners."
    }
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/public-landing.js'])

    <style>
        /* Guaranteed x-cloak — inline so it never depends on compiled CSS */
        [x-cloak] { display: none !important; }

        /* Sitewide reduced-motion override for the new landing page animations */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
                scroll-behavior: auto !important;
            }
        }
    </style>

    @stack('head')
</head>
<body class="bg-base text-body antialiased" data-stitch-page="@yield('stitch_page', 'public-landing')" data-stitch-fallback="{{ route('public.home') }}">
    <a href="#main-content"
       class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[100] focus:rounded-lg focus:bg-heading focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white focus:shadow-lg">
        Skip to main content
    </a>

    @yield('content')

    <script>
        // Thin analytics wrapper used by public CTAs/sections — no-ops if GA
        // isn't configured (e.g. local dev), never sends PII.
        window.rbTrack = function (eventName, params = {}) {
            if (typeof window.gtag === 'function') {
                window.gtag('event', eventName, params);
            }
        };
    </script>

    @stack('scripts')
</body>
</html>
