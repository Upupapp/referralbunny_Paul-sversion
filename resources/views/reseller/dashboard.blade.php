@extends('layouts.reseller')
@section('title', 'Dashboard')
@section('nav') @include('reseller._nav') @endsection

@section('topbar-actions')
    <a href="{{ route('reseller.deals', $tenant->id) }}" class="rs-btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        <span class="hidden sm:inline">New Deal</span>
    </a>
@endsection

@section('content')
<div class="space-y-5">

    {{-- Welcome --}}
    <div>
        <h2 class="text-xl font-bold" style="color:#1E1B4B">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ explode(' ', $reseller->name ?? 'Referrer')[0] }}!</h2>
        <p class="text-sm text-gray-400 mt-0.5">{{ now()->format('l, F j, Y') }} · {{ $tenant->name }}</p>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        @php
        $kpis = [
            ['label'=>'My Deals',       'value'=>$stats['total'],                  'icon'=>'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2',             'bg'=>'#CCFBF1','color'=>'#0D9488','sub'=>'Total submitted'],
            ['label'=>'Active Deals',   'value'=>$stats['active'],                 'icon'=>'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',                                                                                    'bg'=>'#EDE9FE','color'=>'#7B61FF','sub'=>'In pipeline'],
            ['label'=>'Conversion',     'value'=>$stats['conversion'].'%',         'icon'=>'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',                                                                    'bg'=>'#D1FAE5','color'=>'#10B981','sub'=>'Deals paid'],
            ['label'=>'Pipeline Value', 'value'=>'₱'.number_format($stats['pipeline']), 'icon'=>'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1','bg'=>'#FEF3C7','color'=>'#D97706','sub'=>'Total deal value'],
        ];
        @endphp
        @foreach($kpis as $k)
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-3 sm:p-4">
            <div class="flex items-center justify-between mb-2 sm:mb-3">
                <span class="text-[11px] sm:text-xs font-medium text-gray-500 truncate pr-1">{{ $k['label'] }}</span>
                <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg flex items-center justify-center shrink-0"
                     style="background:{{ $k['bg'] }};color:{{ $k['color'] }}">
                    <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $k['icon'] }}"/></svg>
                </div>
            </div>
            <p class="text-xl sm:text-2xl font-bold truncate" style="color:#1E1B4B">{{ $k['value'] }}</p>
            <p class="text-[10px] sm:text-xs text-gray-400 mt-0.5 sm:mt-1">{{ $k['sub'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Expiring alert --}}
    @if($stats['expiring'] > 0)
    <div class="flex items-center gap-3 px-4 py-3 rounded-2xl border" style="background:#FFFBEB;border-color:#FDE68A">
        <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <p class="text-sm text-amber-700 font-medium">
            {{ $stats['expiring'] }} deal{{ $stats['expiring'] > 1 ? 's are' : ' is' }} expiring soon — take action before they expire.
        </p>
        <a href="{{ route('reseller.deals', $tenant->id) }}?status=expiring"
           class="ml-auto text-xs font-semibold text-amber-700 hover:text-amber-900 whitespace-nowrap">View →</a>
    </div>
    @endif

    {{-- Recent Deals --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
            <p class="text-sm font-semibold" style="color:#1E1B4B">Recent Deals</p>
            <a href="{{ route('reseller.deals', $tenant->id) }}"
               class="text-xs font-semibold" style="color:#0D9488">View all →</a>
        </div>

        @if($recentLeads->isEmpty())
        <div class="flex flex-col items-center justify-center py-12 text-center">
            <img src="/images/mascots/r-bunny-sleeping.webp" alt="" aria-hidden="true" class="w-14 h-14 object-contain mb-3 opacity-50">
            <p class="text-sm font-medium text-gray-500">No deals yet</p>
            <p class="text-xs text-gray-400 mt-1">Add your first deal to start tracking referrals.</p>
            <a href="{{ route('reseller.deals', $tenant->id) }}" class="rs-btn-primary mt-4 text-xs">Add First Deal</a>
        </div>
        @else
        <div class="divide-y divide-gray-50">
            @foreach($recentLeads as $lead)
            @php
                $stageColors = ['introduction'=>'#9CA3AF','presentation'=>'#3B82F6','contract_sent'=>'#F59E0B','signed'=>'#8B5CF6','paid'=>'#10B981'];
                $sc = $stageColors[$lead->stage] ?? '#9CA3AF';
            @endphp
            <div class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 transition-colors">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white text-xs font-bold shrink-0"
                     style="background:{{ $sc }}">
                    {{ strtoupper(substr($lead->name, 0, 2)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold truncate" style="color:#1E1B4B">{{ $lead->name }}</p>
                    <p class="text-xs text-gray-400 truncate mt-0.5">{{ ucfirst(str_replace('_', ' ', $lead->stage)) }}</p>
                </div>
                <div class="text-right shrink-0">
                    <p class="text-sm font-bold" style="color:#1E1B4B">₱{{ number_format($lead->deal_value ?? 0) }}</p>
                    <span class="text-[10px] px-2 py-0.5 rounded-full font-medium"
                          style="background:{{ $lead->status === 'active' ? '#D1FAE5' : ($lead->status === 'expiring' ? '#FEF3C7' : '#FEE2E2') }};color:{{ $lead->status === 'active' ? '#065F46' : ($lead->status === 'expiring' ? '#D97706' : '#DC2626') }}">
                        {{ $lead->status }}
                    </span>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

</div>
@endsection
