@extends('layouts.app')
@section('title', 'Extension Request Batches')
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
@endphp
<div class="space-y-5" x-data="{ search: '', statusFilter: {{ Js::from($statusFilter ?? '') }} }">

    {{-- Page header --}}
    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-violet-100 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-[#1E1B4B] font-bold text-lg">Extension Request Batches</h2>
                    <p class="text-gray-400 text-xs mt-0.5">Bulk extension requests from Referrers — review, approve, decline, or skip individual deals</p>
                </div>
            </div>
            <span class="text-sm text-gray-400">
                <span class="font-semibold text-[#1E1B4B]">{{ $batches->count() }}</span>
                batch{{ $batches->count() !== 1 ? 'es' : '' }}
                @if($statusFilter) <span class="text-xs ml-1">(filtered)</span> @endif
            </span>
        </div>
    </div>

    {{-- Search + filters --}}
    <div class="card space-y-3">
        <div class="search-group">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" x-model="search" placeholder="Search by reference, reason, or referrer…" class="w-full bg-transparent outline-none text-sm text-gray-700 placeholder-gray-400">
            <button x-show="search" @click="search=''" class="text-gray-400 hover:text-gray-600 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="filter-bar">
            @foreach(['', 'pending', 'partially_approved', 'approved', 'declined', 'partially_declined'] as $s)
            @php
                $isAll = $s === '';
                $isActive = ($statusFilter ?? '') === $s;
                $label = $isAll ? 'All' : ($statusColors[$s]['label'] ?? ucfirst($s));
            @endphp
            <a href="{{ route('tenant.extension-requests.index', $tenantId) }}{{ $s ? '?status=' . $s : '' }}"
               class="filter-pill {{ $isActive ? 'active' : '' }}">
                @if(!$isAll)
                <span class="w-1.5 h-1.5 rounded-full {{ $statusColors[$s]['dot'] }}"></span>
                @endif
                {{ $label }}
            </a>
            @endforeach
        </div>
    </div>

    {{-- Batch list --}}
    @if($batches->isEmpty())
    <div class="card py-14 text-center">
        <div class="w-12 h-12 rounded-2xl bg-violet-50 flex items-center justify-center mx-auto mb-4">
            <svg class="w-6 h-6 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
        </div>
        @if($statusFilter)
        <p class="text-gray-500 font-medium text-sm">No {{ $statusColors[$statusFilter]['label'] ?? $statusFilter }} batches</p>
        <p class="text-gray-400 text-xs mt-1 mb-4">Try a different filter to see more results.</p>
        <a href="{{ route('tenant.extension-requests.index', $tenantId) }}" class="btn-secondary text-sm inline-flex">
            Clear filter
        </a>
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
           class="card block hover:border-violet-200 hover:shadow-sm transition-all group"
           data-ref="{{ strtolower($batch->batch_reference) }}"
           data-reason="{{ strtolower($batch->shared_reason) }}"
           data-referrer="{{ strtolower($referrerName) }}"
           x-show="!search || $el.dataset.ref.includes(search.toLowerCase()) || $el.dataset.reason.includes(search.toLowerCase()) || $el.dataset.referrer.includes(search.toLowerCase())">

            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                {{-- Icon --}}
                <div class="w-9 h-9 rounded-xl bg-violet-50 flex items-center justify-center shrink-0 group-hover:bg-violet-100 transition-colors">
                    <svg class="w-4 h-4 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
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
                            {{ $batch->pending_count }} pending
                        </span>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs text-gray-500">
                        <span>
                            <svg class="w-3 h-3 inline-block mr-0.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0M12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            {{ $referrerName }}
                        </span>
                        <span class="text-gray-300">·</span>
                        <span class="truncate max-w-[200px]">{{ \Illuminate\Support\Str::limit($batch->shared_reason, 60) }}</span>
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

            <div class="mt-2 pt-2 border-t border-gray-50 text-[11px] text-gray-400">
                Submitted {{ $batch->created_at->diffForHumans() }}
                @if($batch->resolved_at)
                    · Resolved {{ $batch->resolved_at->diffForHumans() }}
                @endif
            </div>
        </a>
        @endforeach
    </div>
    @endif

</div>
@endsection
