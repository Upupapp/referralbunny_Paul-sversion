<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.google-analytics')
    <title>{{ $page }} — ReferralBunny.ai</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 flex flex-col items-center justify-center px-6 py-12 text-center font-sans" data-stitch-page="tenant-selector" data-stitch-fallback="{{ route('public.home') }}">
    <x-rb-logo variant="horizontal" size="md" :priority="true" :decorative="true" class="mx-auto mb-8 opacity-80" />
    <x-r-bunny variant="portal" size="lg" :decorative="true" class="mx-auto mb-6" />
    <h1 class="text-xl font-bold text-gray-900 mb-2">{{ $page }}</h1>
    <p class="text-gray-500 text-sm max-w-xs mb-8">This page is coming soon. You can sign in to your existing tenant workspace now.</p>
    <a href="{{ route('tenant.login') }}"
       data-stitch-action="navigate"
       data-stitch-target="{{ route('tenant.login') }}"
       data-stitch-fallback="{{ route('public.home') }}"
       data-stitch-loading="none"
       class="rounded-xl bg-violet-600 hover:bg-violet-700 text-white font-semibold px-6 py-2.5 text-sm transition-colors">
        Back to Login
    </a>
</body>
</html>
