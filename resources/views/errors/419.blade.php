@extends('layouts.public')

@section('title', 'Session Expired — ReferralBunny.ai')
@section('meta_description', 'Your session has expired. Please refresh and try again.')
@section('canonical', 'https://referralbunny.ai/')

@section('content')
    <main id="main-content" class="flex min-h-screen items-center justify-center bg-base px-4 py-16">
        <div class="reveal mx-auto max-w-lg text-center">
            <a href="{{ route('public.home') }}" class="inline-flex items-center justify-center" aria-label="ReferralBunny.ai home">
                <x-rb-logo variant="horizontal" size="sm" />
            </a>

            <x-r-bunny variant="warning" size="lg" :decorative="true" class="rb-float mx-auto mt-6" />

            <h1 class="mt-6 text-3xl font-bold text-heading sm:text-4xl">R Bunny dropped a carrot.</h1>
            <p class="mt-3 text-body">Your session expired for security. Please refresh the page and try again.</p>
            <p class="mt-1 text-sm text-muted">Error 419</p>

            <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <button type="button" onclick="window.location.reload()" class="btn-secondary">Refresh page</button>
                <x-public.cta-button
                    route="login"
                    variant="primary"
                    size="md"
                    event="error_419_login_clicked"
                >
                    Log In
                </x-public.cta-button>
            </div>
        </div>
    </main>
@endsection
