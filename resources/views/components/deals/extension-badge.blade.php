@props([
    'summary'    => [],
    'viewerRole' => 'referrer',
    'reviewUrl'  => null,
    'statusUrl'  => null,
    'compact'    => false,
])
@php
    $extStatus    = $summary['status']        ?? 'none';
    $hasExtension = $summary['has_extension'] ?? false;
    $pendingCount = $summary['pending_count'] ?? 0;
    $totalDays    = $summary['total_days']    ?? 0;
    $label        = $summary['label']         ?? '';
    $tooltipItems = $summary['tooltip_items'] ?? [];

    $ctaUrl   = null;
    $ctaLabel = null;
    if (in_array($viewerRole, ['admin', 'manager']) && $reviewUrl) {
        $ctaUrl   = $reviewUrl;
        $ctaLabel = 'Review Extension';
    } elseif ($viewerRole === 'referrer' && $statusUrl) {
        $ctaUrl   = $statusUrl;
        $ctaLabel = 'View Request Status';
    }
@endphp
@if($extStatus !== 'none')
<div x-data="{ extBadgeOpen: false }" class="relative inline-flex items-center" style="line-height:1">
    @if($hasExtension)
    <button type="button"
            @click.stop="extBadgeOpen = !extBadgeOpen"
            @click.outside="extBadgeOpen = false"
            @keydown.escape.window="extBadgeOpen = false"
            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-orange-100 text-orange-700 border border-orange-200 hover:bg-orange-200 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-400"
            aria-haspopup="dialog"
            :aria-expanded="extBadgeOpen"
            aria-label="{{ $label }}">
        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 11l5-5m0 0l5 5m-5-5v12"/>
        </svg>
        @if(!$compact)<span>{{ $label }}</span>@else<span>+{{ $totalDays }}d</span>@endif
    </button>
    @endif
    @if($pendingCount > 0)
    <button type="button"
            @click.stop="extBadgeOpen = !extBadgeOpen"
            @click.outside="extBadgeOpen = false"
            @keydown.escape.window="extBadgeOpen = false"
            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700 border border-amber-200 hover:bg-amber-200 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400{{ $hasExtension ? ' ml-1' : '' }}"
            aria-haspopup="dialog"
            :aria-expanded="extBadgeOpen"
            aria-label="{{ $pendingCount === 1 ? '1 extension pending' : $pendingCount . ' extensions pending' }}">
        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        @if(!$compact)<span>{{ $pendingCount === 1 ? '1 pending' : $pendingCount . ' pending' }}</span>@else<span>{{ $pendingCount }}p</span>@endif
    </button>
    @endif
    <div x-show="extBadgeOpen"
         x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95 translate-y-1"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-1"
         class="absolute z-50 left-0 top-full mt-2 w-72 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden"
         role="dialog"
         aria-modal="true"
         aria-label="Extension details"
         style="min-width:240px;max-width:300px">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-2">
            <svg class="w-4 h-4 text-orange-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm font-semibold text-[#1E1B4B]">Extension Details</p>
        </div>
        <div class="px-4 py-3 space-y-2">
            @foreach($tooltipItems as $item)
            <div class="flex items-start justify-between gap-3">
                <span class="text-xs text-gray-500 shrink-0">{{ $item['label'] }}</span>
                <span class="text-xs font-medium text-gray-800 text-right">{{ $item['value'] }}</span>
            </div>
            @endforeach
            @if(empty($tooltipItems))
            <p class="text-xs text-gray-400 italic">No extension details available.</p>
            @endif
        </div>
        @if($ctaUrl)
        <div class="px-4 pb-3 pt-1 border-t border-gray-100">
            <a href="{{ $ctaUrl }}"
               class="inline-flex items-center gap-1.5 w-full justify-center px-3 py-2 rounded-lg text-xs font-semibold transition-colors {{ in_array($viewerRole, ['admin','manager']) ? 'bg-[#7B61FF] text-white hover:bg-[#6B51EF]' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                {{ $ctaLabel }}
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
        @endif
    </div>
</div>
@endif
