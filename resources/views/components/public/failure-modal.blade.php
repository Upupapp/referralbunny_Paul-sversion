@props([
    'title' => 'R Bunny dropped a carrot.',
    'message' => "A few visuals on this page didn't load. Everything still works — keep going, or refresh to try again.",
    'retryLabel' => 'Refresh page',
    'primaryRoute' => 'signup.build',
    'primaryFallback' => '/login',
    'primaryLabel' => 'Build a Referral Program',
    'secondaryRoute' => 'login',
    'secondaryFallback' => '/login',
    'secondaryLabel' => 'Go to Login',
    'dismissLabel' => 'Continue',
])

@php
    use Illuminate\Support\Facades\Route;

    $primaryHref = Route::has($primaryRoute) ? route($primaryRoute) : $primaryFallback;
    $secondaryHref = Route::has($secondaryRoute) ? route($secondaryRoute) : $secondaryFallback;
@endphp

{{--
    Generic branded failure modal. Stays hidden until something
    dispatches a `rb-modal-open` window event (see public-landing.js,
    e.g. a critical brand image failing to load).
--}}
<div
    x-data="{ open: false }"
    @rb-modal-open.window="open = true"
    @keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-[110] flex items-center justify-center p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="rb-failure-modal-title"
>
    <div class="absolute inset-0 bg-heading/50" @click="open = false"></div>

    <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
        <button
            type="button"
            @click="open = false"
            aria-label="Close"
            class="absolute right-3 top-3 rounded-full p-1.5 text-muted hover:bg-base hover:text-heading transition"
        >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <div class="flex items-start gap-4">
            <x-r-bunny variant="warning" size="sm" class="shrink-0" />
            <div class="flex-1 pt-1">
                <h2 id="rb-failure-modal-title" class="text-lg font-semibold text-heading">{{ $title }}</h2>
                <p class="mt-1 text-sm text-body">{{ $message }}</p>
            </div>
        </div>

        <div class="mt-6 flex flex-col gap-2 sm:flex-row">
            <button
                type="button"
                @click="window.location.reload()"
                class="inline-flex items-center justify-center rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-heading transition hover:border-brand hover:text-brand"
            >
                {{ $retryLabel }}
            </button>
            <a href="{{ $primaryHref }}" class="inline-flex items-center justify-center rounded-xl bg-brand px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-hover">
                {{ $primaryLabel }}
            </a>
            <a href="{{ $secondaryHref }}" class="inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold text-heading transition hover:bg-base">
                {{ $secondaryLabel }}
            </a>
            <button type="button" @click="open = false" class="inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold text-muted transition hover:bg-base">
                {{ $dismissLabel }}
            </button>
        </div>
    </div>
</div>
