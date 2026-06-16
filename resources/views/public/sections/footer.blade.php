@php
    $buildHref = \Illuminate\Support\Facades\Route::has('signup.build') ? route('signup.build') : '/tenant/create';
    $joinHref = \Illuminate\Support\Facades\Route::has('join') ? route('join') : '/tenant/join';
    $loginHref = \Illuminate\Support\Facades\Route::has('login') ? route('login') : '/login';
    $termsHref = \Illuminate\Support\Facades\Route::has('terms') ? route('terms') : '/terms';
    $privacyHref = \Illuminate\Support\Facades\Route::has('privacy') ? route('privacy') : '/privacy';
@endphp

<footer class="bg-sidebar">
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="grid gap-10 lg:grid-cols-[2fr_1fr_1fr_1fr]">
            <div>
                <a href="{{ route('public.home') }}" class="inline-flex items-center" aria-label="ReferralBunny.ai home">
                    <x-rb-logo variant="horizontal" size="sm" />
                </a>
                <p class="mt-4 max-w-sm text-sm text-white/60">Build and manage referral programs — guided setup, dedicated portals for referrers and partners, and automated commissions.</p>
            </div>

            <div>
                <p class="text-sm font-semibold text-white">Product</p>
                <ul class="mt-4 space-y-2 text-sm text-white/60">
                    <li><a href="#how-it-works" class="hover:text-white">How It Works</a></li>
                    <li><a href="#features" class="hover:text-white">Features</a></li>
                    <li><a href="#use-cases" class="hover:text-white">Use Cases</a></li>
                    <li><a href="#pricing" class="hover:text-white">Pricing</a></li>
                </ul>
            </div>

            <div>
                <p class="text-sm font-semibold text-white">Get Started</p>
                <ul class="mt-4 space-y-2 text-sm text-white/60">
                    <li><a href="{{ $buildHref }}" class="hover:text-white" data-rb-event="footer_build_clicked">Build a Referral Program</a></li>
                    <li><a href="{{ $joinHref }}" class="hover:text-white" data-rb-event="footer_join_clicked">Join a Referral Program</a></li>
                    <li><a href="{{ $loginHref }}" class="hover:text-white" data-rb-event="footer_login_clicked">Log In</a></li>
                </ul>
            </div>

            <div>
                <p class="text-sm font-semibold text-white">Legal</p>
                <ul class="mt-4 space-y-2 text-sm text-white/60">
                    <li><a href="{{ $termsHref }}" class="hover:text-white">Terms of Service</a></li>
                    <li><a href="{{ $privacyHref }}" class="hover:text-white">Privacy Policy</a></li>
                </ul>
            </div>
        </div>

        <div class="mt-10 border-t border-white/10 pt-6">
            <p class="text-xs text-white/50">&copy; {{ date('Y') }} ReferralBunny.ai. All rights reserved.</p>
        </div>
    </div>
</footer>
