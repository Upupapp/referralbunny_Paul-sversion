@php($dashboardUrl = $dashboardUrl ?? null)
<header class="sticky top-0 z-50 border-b border-gray-100/80 bg-base/80 backdrop-blur-md">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
        <a href="{{ route('public.home') }}" class="flex items-center gap-2" aria-label="ReferralBunny.ai home">
            <x-rb-logo variant="horizontal" size="sm" :priority="true" data-rb-critical />
        </a>

        <nav aria-label="Primary" class="hidden items-center gap-8 text-sm font-medium text-heading lg:flex">
            <a href="#how-it-works" class="transition hover:text-brand">How It Works</a>
            <a href="#features" class="transition hover:text-brand">Features</a>
            <a href="#use-cases" class="transition hover:text-brand">Use Cases</a>
            <a href="#faq" class="transition hover:text-brand">FAQ</a>
        </nav>

        <div class="hidden items-center gap-3 lg:flex">
            @if($dashboardUrl)
                <a href="{{ $dashboardUrl }}" class="text-sm font-semibold text-heading transition hover:text-brand">Go to Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="text-sm font-semibold text-heading transition hover:text-brand">Log In</a>
                <x-public.cta-button route="join" fallback="/login" variant="secondary" size="sm" loadingText="Finding your join path…" event="header_join_clicked">
                    Join a Referral Program
                </x-public.cta-button>
                <x-public.cta-button route="signup.build" fallback="/login" variant="primary" size="sm" loadingText="Opening your setup…" event="header_build_clicked">
                    Build a Referral Program
                </x-public.cta-button>
            @endif
        </div>

        {{-- Mobile menu: native <details> so it works with zero JS; Alpine adds aria-expanded + Escape-to-close --}}
        <details class="relative lg:hidden" x-data="{ open: false }" @toggle="open = $event.target.open" :open="open" @keydown.escape.window="open = false">
            <summary aria-controls="mobile-menu-panel" :aria-expanded="open ? 'true' : 'false'" class="flex cursor-pointer list-none items-center justify-center rounded-lg p-2 text-heading hover:bg-white/60 [&::-webkit-details-marker]:hidden">
                <span class="sr-only">Toggle menu</span>
                <svg x-show="!open" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                </svg>
                <svg x-show="open" x-cloak class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </summary>

            <div id="mobile-menu-panel" class="absolute right-0 top-full mt-2 w-64 rounded-2xl border border-gray-100 bg-white p-4 shadow-xl">
                <nav aria-label="Mobile" class="flex flex-col gap-1 text-sm font-medium text-heading">
                    <a href="#how-it-works" class="rounded-lg px-3 py-2 hover:bg-base">How It Works</a>
                    <a href="#features" class="rounded-lg px-3 py-2 hover:bg-base">Features</a>
                    <a href="#use-cases" class="rounded-lg px-3 py-2 hover:bg-base">Use Cases</a>
                    <a href="#faq" class="rounded-lg px-3 py-2 hover:bg-base">FAQ</a>
                </nav>
                <div class="mt-3 flex flex-col gap-2 border-t border-gray-100 pt-3">
                    @if($dashboardUrl)
                        <a href="{{ $dashboardUrl }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-heading hover:bg-base">Go to Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-heading hover:bg-base">Log In</a>
                        <x-public.cta-button route="join" fallback="/login" variant="secondary" size="sm" loadingText="Finding your join path…" event="mobile_join_clicked" class="w-full">
                            Join a Referral Program
                        </x-public.cta-button>
                        <x-public.cta-button route="signup.build" fallback="/login" variant="primary" size="sm" loadingText="Opening your setup…" event="mobile_build_clicked" class="w-full">
                            Build a Referral Program
                        </x-public.cta-button>
                    @endif
                </div>
            </div>
        </details>
    </div>
</header>
