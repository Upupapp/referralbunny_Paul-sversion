@extends('layouts.partner')
@section('title', $lead->name)
@section('nav') @include('partner._nav') @endsection

@section('content')
<div class="space-y-5 max-w-3xl">

    {{-- Breadcrumb --}}
    <div class="flex items-center gap-2 text-sm text-gray-400">
        <a href="{{ route('partner.deals') }}" class="hover:text-gray-600 transition-colors">My Deals</a>
        <span>›</span>
        <span class="text-[#1E1B4B] font-medium truncate">{{ $lead->name }}</span>
    </div>

    {{-- Deal header card --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <div class="flex flex-col sm:flex-row sm:items-start gap-4">
            <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-white font-bold text-lg shrink-0"
                 style="background:#2563EB">
                {{ strtoupper(substr($lead->name, 0, 2)) }}
            </div>
            <div class="flex-1 min-w-0">
                <h1 class="text-xl font-bold text-[#1E1B4B]">{{ $lead->name }}</h1>
                <p class="text-sm text-gray-400 mt-0.5">
                    @if(isset($lead->data['province'])){{ $lead->data['province'] }}@endif
                    @if(isset($lead->data['municipality'])) › {{ $lead->data['municipality'] }}@endif
                </p>
                <div class="flex flex-wrap gap-2 mt-3">
                    @php
                        $stageMap = [
                            'introduction'  => ['bg-gray-100 text-gray-600',    'Introduction'],
                            'presentation'  => ['bg-blue-100 text-blue-700',    'Presentation'],
                            'contract_sent' => ['bg-amber-100 text-amber-700',  'Contract Sent'],
                            'signed'        => ['bg-purple-100 text-purple-700','Signed'],
                            'paid'          => ['bg-green-100 text-green-700',  'Paid'],
                        ];
                        [$stageBadge, $stageLabel] = $stageMap[$lead->stage] ?? ['bg-gray-100 text-gray-600', ucfirst(str_replace('_', ' ', $lead->stage))];
                        $statusBadge = $lead->status === 'active'
                            ? 'bg-green-100 text-green-700'
                            : ($lead->status === 'expiring' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700');
                    @endphp
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $stageBadge }}">
                        {{ $stageLabel }}
                    </span>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $statusBadge }}">
                        {{ ucfirst($lead->status) }}
                    </span>
                    @if(isset($lead->days_left) && $lead->days_left !== null)
                    <span class="text-xs {{ $lead->days_left <= 3 ? 'text-red-600 font-bold' : ($lead->days_left <= 7 ? 'text-orange-500 font-semibold' : 'text-gray-500') }} flex items-center">
                        {{ $lead->days_left }}d remaining
                    </span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Your role notice --}}
    <div class="flex items-center gap-3 px-4 py-3 bg-blue-50 border border-blue-100 rounded-2xl">
        <svg class="w-4 h-4 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-xs text-blue-700">You can view this deal because you were added as a Partner. This is a read-only view.</p>
    </div>

    {{-- Deal value (show without commission breakdown) --}}
    @if(isset($lead->deal_value) && $lead->deal_value)
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <h2 class="text-sm font-bold text-[#1E1B4B] mb-3">Deal Information</h2>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <p class="text-xs text-gray-400 mb-1">Deal Value</p>
                <p class="text-lg font-bold text-[#1E1B4B]">₱{{ number_format($lead->deal_value) }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 mb-1">Your Involvement</p>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700">Active Partner</span>
            </div>
        </div>
    </div>
    @endif

    {{-- Referrer (show name only, no contact details) --}}
    @if(isset($lead->reseller_name) && $lead->reseller_name)
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <h2 class="text-sm font-bold text-[#1E1B4B] mb-3">Referrer</h2>
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-full bg-purple-100 flex items-center justify-center text-purple-700 text-xs font-bold shrink-0">
                {{ strtoupper(substr($lead->reseller_name, 0, 2)) }}
            </div>
            <div>
                <p class="text-sm font-semibold text-[#1E1B4B]">{{ $lead->reseller_name }}</p>
                <p class="text-xs text-gray-400">Referrer</p>
            </div>
        </div>
    </div>
    @endif

    {{-- Actions --}}
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('partner.messages') }}?deal_id={{ $lead->id }}"
           class="pt-btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
            </svg>
            Message Referrer
        </a>
        <a href="{{ route('partner.deals') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors">
            ← Back to Deals
        </a>
    </div>

</div>
@endsection
