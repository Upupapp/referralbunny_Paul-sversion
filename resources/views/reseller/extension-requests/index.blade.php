@extends('layouts.reseller')
@section('title', 'My Extension Requests')
@section('nav') @include('reseller._nav') @endsection

@section('topbar-actions')
    <a href="{{ route('reseller.extension-requests.create', $tenantId) }}" class="rs-btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        <span class="hidden sm:inline">New Extension Request</span>
    </a>
@endsection

@section('content')
<div class="space-y-5" x-data="{ filter: '' }">

    {{-- Page header --}}
    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-violet-100 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-[#1E1B4B] font-bold text-lg">My Extension Requests</h2>
                    <p class="text-gray-400 text-xs mt-0.5">Bulk extension requests you've submitted for your assigned deals</p>
                </div>
            </div>
            <span class="text-sm text-gray-400">
                <span class="font-semibold text-[#1E1B4B]">{{ $batches->count() }}</span>
                batch{{ $batches->count() !== 1 ? 'es' : '' }}
            </span>
        </div>
    </div>

    {{-- Search --}}
    @if($batches->count() > 0)
    <div class="card space-y-3">
        <div class="search-group">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" x-model="filter" placeholder="Search by reference or reason…" class="w-full bg-transparent outline-none text-sm text-gray-700 placeholder-gray-400">
            <button x-show="filter" @click="filter=''" class="text-gray-400 hover:text-gray-600 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>
    @endif

    {{-- Batch list --}}
    @if($batches->isEmpty())
    <div class="card py-14 text-center">
        <div class="w-12 h-12 rounded-2xl bg-violet-50 flex items-center justify-center mx-auto mb-4">
            <svg class="w-6 h-6 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
        </div>
        <p class="text-gray-500 font-medium text-sm">No extension requests yet</p>
        <p class="text-gray-400 text-xs mt-1 mb-4">Submit a bulk extension request for your deals that need more time.</p>
        <a href="{{ route('reseller.extension-requests.create', $tenantId) }}" class="rs-btn-primary inline-flex">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Submit Extension Request
        </a>
    </div>
    @else
    <div class="space-y-3">
        @foreach($batches as $batch)
        @php
            $statusColors = [
                'pending'             => ['bg' => 'bg-amber-100',   'text' => 'text-amber-700',   'dot' => 'bg-amber-500'],
                'partially_approved'  => ['bg' => 'bg-blue-100',    'text' => 'text-blue-700',    'dot' => 'bg-blue-500'],
                'approved'            => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'dot' => 'bg-emerald-500'],
                'declined'            => ['bg' => 'bg-red-100',     'text' => 'text-red-700',     'dot' => 'bg-red-500'],
                'partially_declined'  => ['bg' => 'bg-orange-100',  'text' => 'text-orange-700',  'dot' => 'bg-orange-500'],
                'cancelled'           => ['bg' => 'bg-gray-100',    'text' => 'text-gray-500',    'dot' => 'bg-gray-400'],
            ];
            $sc = $statusColors[$batch->status] ?? $statusColors['pending'];
            $statusLabel = $batch->status_label ?? ucfirst(str_replace('_', ' ', $batch->status));
        @endphp
        <a href="{{ route('reseller.extension-requests.show', ['tenantId' => $tenantId, 'batchId' => $batch->id]) }}"
           class="card block hover:border-violet-200 hover:shadow-sm transition-all group"
           data-ref="{{ strtolower($batch->batch_reference) }}"
           data-reason="{{ strtolower($batch->shared_reason) }}"
           x-show="!filter || $el.dataset.ref.includes(filter.toLowerCase()) || $el.dataset.reason.includes(filter.toLowerCase())">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">

                {{-- Icon --}}
                <div class="w-9 h-9 rounded-xl bg-violet-50 flex items-center justify-center shrink-0 group-hover:bg-violet-100 transition-colors">
                    <svg class="w-4 h-4 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>

                {{-- Main info --}}
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <span class="font-semibold text-[#1E1B4B] text-sm">{{ $batch->batch_reference }}</span>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium {{ $sc['bg'] }} {{ $sc['text'] }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $sc['dot'] }}"></span>
                            {{ $statusLabel }}
                        </span>
                    </div>
                    <p class="text-gray-500 text-xs truncate">{{ \Illuminate\Support\Str::limit($batch->shared_reason, 80) }}</p>
                </div>

                {{-- Stats --}}
                <div class="flex items-center gap-4 shrink-0">
                    <div class="text-center">
                        <p class="text-lg font-bold text-[#1E1B4B]">{{ $batch->requested_extension_days }}</p>
                        <p class="text-[10px] text-gray-400 uppercase tracking-wide">days req.</p>
                    </div>
                    <div class="text-center">
                        <p class="text-lg font-bold text-[#1E1B4B]">{{ $batch->total_items }}</p>
                        <p class="text-[10px] text-gray-400 uppercase tracking-wide">deals</p>
                    </div>
                    @if($batch->approved_count > 0)
                    <div class="text-center">
                        <p class="text-lg font-bold text-emerald-600">{{ $batch->approved_count }}</p>
                        <p class="text-[10px] text-gray-400 uppercase tracking-wide">approved</p>
                    </div>
                    @endif
                    @if($batch->declined_count > 0)
                    <div class="text-center">
                        <p class="text-lg font-bold text-red-500">{{ $batch->declined_count }}</p>
                        <p class="text-[10px] text-gray-400 uppercase tracking-wide">declined</p>
                    </div>
                    @endif
                    <div class="text-gray-300">
                        <svg class="w-4 h-4 group-hover:text-violet-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </div>
                </div>
            </div>

            {{-- Submitted date --}}
            <div class="mt-2 pt-2 border-t border-gray-50 flex items-center gap-1 text-[11px] text-gray-400">
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Submitted {{ $batch->created_at->diffForHumans() }}
                @if($batch->pending_count > 0)
                    <span class="mx-1.5 text-gray-200">·</span>
                    <span class="text-amber-600 font-medium">{{ $batch->pending_count }} pending review</span>
                @elseif($batch->resolved_at)
                    <span class="mx-1.5 text-gray-200">·</span>
                    Resolved {{ $batch->resolved_at->diffForHumans() }}
                @endif
            </div>
        </a>
        @endforeach

    </div>
    @endif

</div>
@endsection
