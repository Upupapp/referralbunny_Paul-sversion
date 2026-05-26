@extends('layouts.app')
@section('title', 'Extension Requests')
@section('nav') @include('tenant._nav') @endsection

@section('content')
@php
    $statusColors = [
        'pending'             => ['bg' => 'bg-amber-100',   'text' => 'text-amber-700',   'dot' => 'bg-amber-500',   'label' => 'Pending'],
        'partially_approved'  => ['bg' => 'bg-blue-100',    'text' => 'text-blue-700',    'dot' => 'bg-blue-500',    'label' => 'Partially Approved'],
        'approved'            => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'dot' => 'bg-emerald-500', 'label' => 'Approved'],
        'declined'            => ['bg' => 'bg-red-100',     'text' => 'text-red-700',     'dot' => 'bg-red-500',     'label' => 'Declined'],
        'partially_declined'  => ['bg' => 'bg-orange-100',  'text' => 'text-orange-700',  'dot' => 'bg-orange-500',  'label' => 'Partially Declined'],
        'cancelled'           => ['bg' => 'bg-gray-100',    'text' => 'text-gray-500',    'dot' => 'bg-gray-400',    'label' => 'Cancelled'],
    ];
    $tabs = [
        'pending'  => ['label' => 'Pending',            'count' => $metrics['pending_batches'],            'color' => 'text-amber-600',   'dot' => 'bg-amber-500'],
        'approved' => ['label' => 'Approved',            'count' => $metrics['approved_batches'],           'color' => 'text-emerald-600', 'dot' => 'bg-emerald-500'],
        'partial'  => ['label' => 'Partially Approved', 'count' => $metrics['partially_approved_batches'], 'color' => 'text-blue-600',    'dot' => 'bg-blue-500'],
        'declined' => ['label' => 'Declined',            'count' => $metrics['declined_batches'],           'color' => 'text-red-600',     'dot' => 'bg-red-500'],
        'all'      => ['label' => 'All History',         'count' => null,                                   'color' => 'text-gray-500',    'dot' => null],
    ];
    $baseUrl = route('tenant.extension-requests.index', $tenantId);
    $tabUrl  = fn(string $t) => $baseUrl . '?tab=' . $t . ($search ? '&search=' . urlencode($search) : '');
@endphp

<div class="space-y-5">

    {{-- Page header --}}
    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-violet-100 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-[#1E1B4B] font-bold text-lg">Extension Requests</h2>
                    <p class="text-gray-400 text-xs mt-0.5">Bulk extension requests from Referrers — review, approve, and manage per deal</p>
                </div>
            </div>
            @if($metrics['pending_batches'] > 0)
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-semibold bg-amber-100 text-amber-700">
                <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                {{ $metrics['pending_batches'] }} pending {{ $metrics['pending_batches'] === 1 ? 'batch' : 'batches' }} need review
            </span>
            @endif
        </div>
    </div>

    {{-- Metric cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <a href="{{ $tabUrl('pending') }}"
           class="card py-4 text-center hover:border-amber-200 hover:shadow-sm transition-all cursor-pointer {{ $tab === 'pending' ? 'border-amber-200 ring-1 ring-amber-200' : '' }}">
            <p class="text-2xl font-bold {{ $metrics['pending_batches'] > 0 ? 'text-amber-600' : 'text-gray-400' }}">{{ $metrics['pending_batches'] }}</p>
            <p class="text-[11px] text-gray-400 mt-1 uppercase tracking-wide">Pending Batches</p>
            @if($metrics['total_pending_deals'] > 0)
            <p class="text-[10px] text-amber-500 mt-0.5">{{ $metrics['total_pending_deals'] }} deal{{ $metrics['total_pending_deals'] !== 1 ? 's' : '' }}</p>
            @endif
        </a>
        <a href="{{ $tabUrl('approved') }}"
           class="card py-4 text-center hover:border-emerald-200 hover:shadow-sm transition-all cursor-pointer {{ $tab === 'approved' ? 'border-emerald-200 ring-1 ring-emerald-200' : '' }}">
            <p class="text-2xl font-bold {{ $metrics['approved_batches'] > 0 ? 'text-emerald-600' : 'text-gray-400' }}">{{ $metrics['approved_batches'] }}</p>
            <p class="text-[11px] text-gray-400 mt-1 uppercase tracking-wide">Approved</p>
            @if($metrics['total_approved_deals'] > 0)
            <p class="text-[10px] text-emerald-500 mt-0.5">{{ $metrics['total_approved_deals'] }} deal{{ $metrics['total_approved_deals'] !== 1 ? 's' : '' }}</p>
            @endif
        </a>
        <a href="{{ $tabUrl('partial') }}"
           class="card py-4 text-center hover:border-blue-200 hover:shadow-sm transition-all cursor-pointer {{ $tab === 'partial' ? 'border-blue-200 ring-1 ring-blue-200' : '' }}">
            <p class="text-2xl font-bold {{ $metrics['partially_approved_batches'] > 0 ? 'text-blue-600' : 'text-gray-400' }}">{{ $metrics['partially_approved_batches'] }}</p>
            <p class="text-[11px] text-gray-400 mt-1 uppercase tracking-wide">Partial</p>
            <p class="text-[10px] text-gray-300 mt-0.5">Partially approved</p>
        </a>
        <a href="{{ $tabUrl('declined') }}"
           class="card py-4 text-center hover:border-red-200 hover:shadow-sm transition-all cursor-pointer {{ $tab === 'declined' ? 'border-red-200 ring-1 ring-red-200' : '' }}">
            <p class="text-2xl font-bold {{ $metrics['declined_batches'] > 0 ? 'text-red-500' : 'text-gray-400' }}">{{ $metrics['declined_batches'] }}</p>
            <p class="text-[11px] text-gray-400 mt-1 uppercase tracking-wide">Rejected</p>
            @if($metrics['total_declined_deals'] > 0)
            <p class="text-[10px] text-red-400 mt-0.5">{{ $metrics['total_declined_deals'] }} deal{{ $metrics['total_declined_deals'] !== 1 ? 's' : '' }}</p>
            @endif
        </a>
        <a href="{{ $tabUrl('all') }}"
           class="card py-4 text-center hover:border-violet-200 hover:shadow-sm transition-all cursor-pointer col-span-2 sm:col-span-1 {{ $tab === 'all' ? 'border-violet-200 ring-1 ring-violet-200' : '' }}">
            <p class="text-2xl font-bold text-violet-500">
                {{ $metrics['pending_batches'] + $metrics['approved_batches'] + $metrics['partially_approved_batches'] + $metrics['declined_batches'] + $metrics['partially_declined_batches'] }}
            </p>
            <p class="text-[11px] text-gray-400 mt-1 uppercase tracking-wide">Total Batches</p>
            <p class="text-[10px] text-gray-300 mt-0.5">All history</p>
        </a>
    </div>

    {{-- R Bunny guidance — shown only when there are pending batches to review --}}
    @if($tab === 'pending' && $metrics['pending_batches'] > 0)
    <div class="card bg-violet-50 border border-violet-100">
        <div class="flex items-start gap-4">
            <div class="w-11 h-11 rounded-2xl bg-white flex items-center justify-center shrink-0 shadow-sm border border-violet-100">
                <img src="/images/mascots/r-bunny-thinking.png" alt="" class="w-9 h-9 object-contain"
                     onerror="this.parentElement.innerHTML='<svg class=\'w-5 h-5 text-violet-400\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z\'/></svg>'">
            </div>
            <div>
                <p class="font-semibold text-violet-800 text-sm">
                    @if($metrics['pending_batches'] === 1)
                    You have 1 batch waiting for review.
                    @else
                    You have {{ $metrics['pending_batches'] }} batches waiting for review.
                    @endif
                </p>
                <p class="text-violet-600 text-xs mt-1 leading-relaxed">
                    Open each batch to review individual deals — approve the eligible ones and decline any that don't qualify.
                    @if($metrics['total_pending_deals'] > 0)
                    There are <strong>{{ $metrics['total_pending_deals'] }}</strong> deal{{ $metrics['total_pending_deals'] !== 1 ? 's' : '' }} pending your decision across all batches.
                    @endif
                </p>
            </div>
        </div>
    </div>
    @endif

    {{-- Search + Tab navigation --}}
    <div class="card space-y-3">
        {{-- Search --}}
        <form method="GET" action="{{ $baseUrl }}" class="search-group">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="text" name="search" value="{{ $search }}"
                   placeholder="Search by reference or reason…"
                   class="w-full bg-transparent outline-none text-sm text-gray-700 placeholder-gray-400">
            @if($search)
            <a href="{{ $tabUrl($tab) }}" class="text-gray-400 hover:text-gray-600 shrink-0" title="Clear search">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </a>
            @endif
        </form>

        {{-- History tabs --}}
        <div class="filter-bar">
            @foreach($tabs as $key => $tabData)
            <a href="{{ $tabUrl($key) }}"
               class="filter-pill {{ $tab === $key ? 'active' : '' }}">
                @if($tabData['dot'])
                <span class="w-1.5 h-1.5 rounded-full {{ $tabData['dot'] }}"></span>
                @endif
                {{ $tabData['label'] }}
                @if($tabData['count'] !== null && $tabData['count'] > 0)
                <span class="inline-flex items-center justify-center w-4 h-4 text-[10px] font-bold rounded-full
                    {{ $tab === $key ? 'bg-white/30 text-current' : 'bg-gray-100 text-gray-500' }}">
                    {{ $tabData['count'] > 99 ? '99+' : $tabData['count'] }}
                </span>
                @endif
            </a>
            @endforeach
        </div>
    </div>

    {{-- Batch list --}}
    @if($batches->isEmpty())
    <div class="card py-14 text-center">
        <div class="w-14 h-14 rounded-2xl bg-violet-50 flex items-center justify-center mx-auto mb-4">
            <img src="/images/mascots/r-bunny-happy.png" alt="" class="w-10 h-10 object-contain"
                 onerror="this.parentElement.innerHTML='<svg class=\'w-7 h-7 text-violet-300\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z\'/></svg>'">
        </div>
        @if($search)
        <p class="text-gray-500 font-medium text-sm">No results for "{{ $search }}"</p>
        <p class="text-gray-400 text-xs mt-1 mb-4">Try a different search term.</p>
        <a href="{{ $tabUrl($tab) }}" class="btn-secondary text-sm inline-flex">Clear search</a>
        @elseif($tab === 'pending')
        <p class="text-gray-500 font-medium text-sm">All caught up!</p>
        <p class="text-gray-400 text-xs mt-1">No extension request batches pending review. Great work!</p>
        @elseif($tab === 'approved')
        <p class="text-gray-500 font-medium text-sm">No approved batches yet</p>
        <p class="text-gray-400 text-xs mt-1">Approved batches will appear here once you review pending requests.</p>
        @elseif($tab === 'declined')
        <p class="text-gray-500 font-medium text-sm">No declined batches yet</p>
        <p class="text-gray-400 text-xs mt-1">Declined batches will appear here after review.</p>
        @elseif($tab === 'partial')
        <p class="text-gray-500 font-medium text-sm">No partially approved batches</p>
        <p class="text-gray-400 text-xs mt-1">These appear when only some deals in a batch are approved.</p>
        @else
        <p class="text-gray-500 font-medium text-sm">No extension request batches yet</p>
        <p class="text-gray-400 text-xs mt-1">Referrers can submit bulk extension requests from their portal.</p>
        @endif
    </div>
    @else
    <div class="space-y-3">
        @foreach($batches as $batch)
        @php
            $sc = $statusColors[$batch->status] ?? $statusColors['pending'];
            $referrerName = $batch->requestedByReseller?->name ?? 'Unknown Referrer';
        @endphp
        <a href="{{ route('tenant.extension-requests.show', ['tenantId' => $tenantId, 'batchId' => $batch->id]) }}"
           class="card block hover:border-violet-200 hover:shadow-sm transition-all group">

            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                {{-- Status icon --}}
                <div class="w-9 h-9 rounded-xl {{ $sc['bg'] }} flex items-center justify-center shrink-0 group-hover:opacity-80 transition-opacity">
                    @if($batch->status === 'approved')
                    <svg class="w-4 h-4 {{ $sc['text'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    @elseif($batch->status === 'declined')
                    <svg class="w-4 h-4 {{ $sc['text'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    @elseif($batch->status === 'pending')
                    <svg class="w-4 h-4 {{ $sc['text'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    @else
                    <svg class="w-4 h-4 {{ $sc['text'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    @endif
                </div>

                {{-- Main info --}}
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-0.5">
                        <span class="font-semibold text-[#1E1B4B] text-sm">{{ $batch->batch_reference }}</span>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium {{ $sc['bg'] }} {{ $sc['text'] }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $sc['dot'] }}"></span>
                            {{ $sc['label'] }}
                        </span>
                        @if($batch->pending_count > 0)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-amber-100 text-amber-700">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                            {{ $batch->pending_count }} pending
                        </span>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs text-gray-500">
                        <span class="flex items-center gap-1">
                            <svg class="w-3 h-3 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0M12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            {{ $referrerName }}
                        </span>
                        <span class="text-gray-300">·</span>
                        <span class="truncate max-w-[200px] sm:max-w-xs">{{ \Illuminate\Support\Str::limit($batch->shared_reason, 70) }}</span>
                    </div>
                </div>

                {{-- Stats --}}
                <div class="flex items-center gap-4 shrink-0">
                    <div class="text-center">
                        <p class="text-base font-bold text-[#1E1B4B]">{{ $batch->requested_extension_days }}d</p>
                        <p class="text-[10px] text-gray-400 uppercase tracking-wide">Req.</p>
                    </div>
                    <div class="text-center">
                        <p class="text-base font-bold text-[#1E1B4B]">{{ $batch->total_items }}</p>
                        <p class="text-[10px] text-gray-400 uppercase tracking-wide">Deals</p>
                    </div>
                    @if($batch->approved_count > 0)
                    <div class="text-center">
                        <p class="text-base font-bold text-emerald-600">{{ $batch->approved_count }}</p>
                        <p class="text-[10px] text-gray-400 uppercase tracking-wide">Approved</p>
                    </div>
                    @endif
                    @if($batch->declined_count > 0)
                    <div class="text-center">
                        <p class="text-base font-bold text-red-500">{{ $batch->declined_count }}</p>
                        <p class="text-[10px] text-gray-400 uppercase tracking-wide">Declined</p>
                    </div>
                    @endif
                    <div class="text-gray-300 group-hover:text-violet-400 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="mt-2 pt-2 border-t border-gray-50 text-[11px] text-gray-400 flex flex-wrap gap-3">
                <span>Submitted {{ $batch->created_at->diffForHumans() }}</span>
                @if($batch->resolved_at)
                <span class="text-gray-300">·</span>
                <span>Resolved {{ $batch->resolved_at->diffForHumans() }}</span>
                @endif
                <span class="text-gray-300">·</span>
                <span>Ref: {{ $batch->batch_reference }}</span>
            </div>
        </a>
        @endforeach
    </div>

    {{-- Pagination --}}
    @if($batches->hasPages())
    <div class="flex justify-center">
        {{ $batches->appends(array_filter(['tab' => $tab, 'search' => $search]))->links() }}
    </div>
    @endif

    {{-- Result count --}}
    <p class="text-center text-xs text-gray-400">
        Showing {{ $batches->firstItem() }}–{{ $batches->lastItem() }} of {{ $batches->total() }}
        {{ $tab === 'all' ? 'total' : ($tabs[$tab]['label'] ?? '') }} batch{{ $batches->total() !== 1 ? 'es' : '' }}
        @if($search) matching "{{ $search }}" @endif
    </p>
    @endif

</div>
@endsection
