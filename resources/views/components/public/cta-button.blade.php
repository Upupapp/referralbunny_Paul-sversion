@props([
    'route' => null,
    'routeParams' => [],
    'fallback' => '/login',
    'href' => null,
    'variant' => 'primary',
    'size' => 'md',
    'loadingText' => null,
    'event' => null,
    'eventParams' => null,
])

@php
    use Illuminate\Support\Facades\Route;

    $resolvedHref = $href
        ?? (($route && Route::has($route)) ? route($route, $routeParams) : $fallback);

    $base = 'rb-cta inline-flex items-center justify-center gap-2 rounded-xl font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2';

    $variants = [
        'primary'      => 'bg-brand text-white shadow-sm hover:bg-brand-hover hover:shadow-md',
        'secondary'    => 'bg-white text-heading border border-gray-200 shadow-sm hover:border-brand hover:text-brand',
        'ghost'        => 'text-heading hover:bg-white/60',
        'white'        => 'bg-white text-heading shadow-sm hover:shadow-md',
        'outline-white' => 'border border-white/40 text-white hover:bg-white/10',
    ];

    $sizes = [
        'sm' => 'text-sm px-4 py-2',
        'md' => 'text-sm px-6 py-3',
        'lg' => 'text-base px-8 py-4',
    ];

    $classes = trim($base . ' ' . ($variants[$variant] ?? $variants['primary']) . ' ' . ($sizes[$size] ?? $sizes['md']));
@endphp

<a
    href="{{ $resolvedHref }}"
    x-data="{ loading: false }"
    @click="loading = true"
    @if($event) data-rb-event="{{ $event }}" @endif
    @if($eventParams) data-rb-params='{{ json_encode($eventParams) }}' @endif
    data-stitch-action="navigate"
    data-stitch-target="{{ $resolvedHref }}"
    data-stitch-fallback="{{ $fallback }}"
    data-stitch-loading="{{ $loadingText ?? 'none' }}"
    {{ $attributes->merge(['class' => $classes]) }}
>
    @if($loadingText)
        <span x-show="!loading" class="inline-flex items-center gap-2">{{ $slot }}</span>
        <span x-show="loading" x-cloak class="inline-flex items-center gap-2">
            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            {{ $loadingText }}
        </span>
    @else
        <span class="inline-flex items-center gap-2">{{ $slot }}</span>
    @endif
</a>
