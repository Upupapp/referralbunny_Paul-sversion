@extends('layouts.reseller')
@section('title', 'Extension Request — ' . ($batch->batch_reference ?? 'Detail'))
@section('nav') @include('reseller._nav') @endsection

@section('topbar-actions')
    <a href="{{ route('reseller.extension-requests.index', $tenantId) }}" class="rs-btn-primary text-sm py-1.5 px-3">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        <span class="hidden sm:inline">My Requests</span>
    </a>
@endsection

@section('content')
@php
    $statusColors = [
        'pending'             => ['bg' => 'bg-amber-100',   'text' => 'text-amber-700',   'dot' => 'bg-amber-500',   'label' => 'Pending Review'],
        'partially_approved'  => ['bg' => 'bg-blue-100',    'text' => 'text-blue-700',    'dot' => 'bg-blue-500',    'label' => 'Partially Approved'],
        'approved'            => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'dot' => 'bg-emerald-500', 'label' => 'Approved'],
        'declined'            => ['bg' => 'bg-red-100',     'text' => 'text-red-700',     'dot' => 'bg-red-500',     'label' => 'Declined'],
        'partially_declined'  => ['bg' => 'bg-orange-100',  'text' => 'text-orange-700',  'dot' => 'bg-orange-500',  'label' => 'Partially Declined'],
        'cancelled'           => ['bg' => 'bg-gray-100',    'text' => 'text-gray-500',    'dot' => 'bg-gray-400',    'label' => 'Cancelled'],
    ];
    $sc = $statusColors[$batch->status] ?? $statusColors['pending'];
    $itemStatusColors = [
        'pending_review' => ['bg' => 'bg-amber-50',   'border' => 'border-amber-200',   'text' => 'text-amber-700',   'dot' => 'bg-amber-400',   'label' => 'Pending Review'],
        'approved'       => ['bg' => 'bg-emerald-50', 'border' => 'border-emerald-200', 'text' => 'text-emerald-700', 'dot' => 'bg-emerald-400', 'label' => 'Approved'],
        'rejected'       => ['bg' => 'bg-red-50',     'border' => 'border-red-200',     'text' => 'text-red-700',     'dot' => 'bg-red-400',     'label' => 'Declined'],
        'skipped'        => ['bg' => 'bg-gray-50',    'border' => 'border-gray-200',    'text' => 'text-gray-500',    'dot' => 'bg-gray-300',    'label' => 'Skipped for Now'],
    ];
@endphp
<div class="space-y-5 max-w-3xl mx-auto">

    {{-- Header card --}}
    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-violet-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-1">
                    <h2 class="text-[#1E1B4B] font-bold text-lg">{{ $batch->batch_reference }}</h2>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $sc['bg'] }} {{ $sc['text'] }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $sc['dot'] }}"></span>
                        {{ $sc['label'] }}
                    </span>
                </div>
                <p class="text-gray-500 text-sm">{{ $batch->shared_reason }}</p>
                <p class="text-gray-400 text-xs mt-1">Submitted {{ $batch->created_at->diffForHumans() }} · {{ $batch->created_at->format('M j, Y g:i A') }}</p>
            </div>
        </div>

        {{-- Stats row --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4 pt-4 border-t border-gray-100">
            <div class="text-center p-3 rounded-xl bg-gray-50">
                <p class="text-2xl font-bold text-[#1E1B4B]">{{ $batch->requested_extension_days }}</p>
                <p class="text-[11px] text-gray-400 uppercase tracking-wide mt-0.5">Days Requested</p>
            </div>
            <div class="text-center p-3 rounded-xl bg-gray-50">
                <p class="text-2xl font-bold text-[#1E1B4B]">{{ $batch->total_items }}</p>
                <p class="text-[11px] text-gray-400 uppercase tracking-wide mt-0.5">Total Deals</p>
            </div>
            <div class="text-center p-3 rounded-xl {{ $batch->approved_count > 0 ? 'bg-emerald-50' : 'bg-gray-50' }}">
                <p class="text-2xl font-bold {{ $batch->approved_count > 0 ? 'text-emerald-600' : 'text-gray-400' }}">{{ $batch->approved_count }}</p>
                <p class="text-[11px] text-gray-400 uppercase tracking-wide mt-0.5">Approved</p>
            </div>
            <div class="text-center p-3 rounded-xl {{ $batch->declined_count > 0 ? 'bg-red-50' : 'bg-gray-50' }}">
                <p class="text-2xl font-bold {{ $batch->declined_count > 0 ? 'text-red-500' : 'text-gray-400' }}">{{ $batch->declined_count }}</p>
                <p class="text-[11px] text-gray-400 uppercase tracking-wide mt-0.5">Declined</p>
            </div>
        </div>

        {{-- Pending reminder --}}
        @if($batch->pending_count > 0)
        <div class="mt-3 flex items-center gap-2 px-3 py-2 rounded-xl bg-amber-50 border border-amber-100 text-xs text-amber-700">
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>{{ $batch->pending_count }} deal{{ $batch->pending_count !== 1 ? 's' : '' }} still pending admin review</span>
        </div>
        @endif
    </div>

    {{-- Items --}}
    <div class="card">
        <h3 class="font-semibold text-[#1E1B4B] text-sm mb-3">Deals in this request</h3>
        <div class="space-y-3">
            @forelse($batch->items as $item)
            @php
                $ic = $itemStatusColors[$item->status] ?? $itemStatusColors['pending_review'];
                $deal = $item->lead;
            @endphp
            <div class="rounded-xl border {{ $ic['border'] }} {{ $ic['bg'] }} px-4 py-3">
                <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                    {{-- Deal info --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            @if($deal)
                            <a href="{{ route('reseller.deals.show', ['tenantId' => $tenantId, 'dealId' => $deal->id]) }}"
                               class="font-semibold text-[#1E1B4B] text-sm hover:underline">
                                {{ $deal->name }}
                            </a>
                            @else
                            <span class="font-semibold text-gray-400 text-sm italic">Deal removed</span>
                            @endif
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium {{ $ic['bg'] }} {{ $ic['text'] }} border {{ $ic['border'] }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $ic['dot'] }}"></span>
                                {{ $ic['label'] }}
                            </span>
                        </div>
                        @if($deal)
                        <div class="flex flex-wrap gap-3 mt-1">
                            <span class="text-xs text-gray-500">Stage: <span class="font-medium">{{ ucfirst(str_replace('_', ' ', $deal->stage)) }}</span></span>
                            <span class="text-xs text-gray-500">Days left at request: <span class="font-medium">{{ $item->current_days_left }}</span></span>
                        </div>
                        @endif
                        @if($item->per_deal_note)
                        <p class="text-xs text-gray-500 mt-1 italic">"{{ $item->per_deal_note }}"</p>
                        @endif
                    </div>

                    {{-- Decision details --}}
                    <div class="shrink-0 text-right">
                        @if($item->status === 'approved')
                            <p class="text-emerald-700 font-semibold text-sm">+{{ $item->approved_days }} days added</p>
                            <p class="text-[11px] text-gray-400">Approved {{ $item->reviewed_at?->diffForHumans() }}</p>
                        @elseif($item->status === 'rejected')
                            <p class="text-red-600 font-semibold text-sm">Request declined</p>
                            @if($item->admin_note)
                            <p class="text-[11px] text-gray-500 mt-0.5 max-w-[200px]">{{ \Illuminate\Support\Str::limit($item->admin_note, 60) }}</p>
                            @endif
                            <p class="text-[11px] text-gray-400">{{ $item->reviewed_at?->diffForHumans() }}</p>
                        @elseif($item->status === 'skipped')
                            <p class="text-gray-500 text-sm">Skipped for now</p>
                            <p class="text-[11px] text-gray-400">May still be reviewed</p>
                        @else
                            <p class="text-amber-600 text-sm font-medium">Awaiting review</p>
                        @endif
                    </div>
                </div>
            </div>
            @empty
            <p class="text-gray-400 text-sm text-center py-4">No items found in this batch.</p>
            @endforelse
        </div>
    </div>

    {{-- Bottom actions --}}
    <div class="flex flex-col sm:flex-row gap-3">
        <a href="{{ route('reseller.extension-requests.create', $tenantId) }}"
           class="rs-btn-primary text-sm justify-center">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Submit Another Request
        </a>
        <a href="{{ route('reseller.deals', $tenantId) }}"
           class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl border border-gray-200 text-sm text-gray-600 hover:bg-gray-50 transition-colors font-medium">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/>
            </svg>
            View My Deals
        </a>
    </div>

</div>
@endsection
