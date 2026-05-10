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
                    @if(isset($lead->days_left) && $lead->days_left !== null && $lead->stage !== 'paid')
                    @php
                        $dl = $lead->days_left;
                        $dlBadge = $dl <= 3
                            ? 'bg-red-100 text-red-700'
                            : ($dl <= 7 ? 'bg-amber-100 text-amber-700' : 'bg-blue-50 text-blue-700');
                        $dlLabel = $dl <= 0 ? 'Overdue' : $dl . 'd to move stage';
                    @endphp
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $dlBadge }}">
                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ $dlLabel }}
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
        <p class="text-xs text-blue-700">You can view this deal because you were added as a Partner. This is a view-only stage progress.</p>
    </div>

    {{-- Deal Progress — read-only stage workflow for Partner --}}
    @php
        $pipelineStages = [
            ['key' => 'introduction',  'label' => 'Introduction',  'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
            ['key' => 'presentation',  'label' => 'Presentation',  'icon' => 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
            ['key' => 'contract_sent', 'label' => 'Contract Sent', 'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
            ['key' => 'signed',        'label' => 'Signed',        'icon' => 'M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z'],
            ['key' => 'paid',          'label' => 'Paid',          'icon' => 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z'],
        ];
        $stageOrder  = array_column($pipelineStages, 'key');
        $currentIdx  = array_search($lead->stage, $stageOrder);
    @endphp
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-bold text-[#1E1B4B]" style="font-size:14px">Deal Progress</h3>
                <p class="text-xs text-gray-400 mt-0.5">View-only stage progress.</p>
            </div>
            <span style="font-size:10px;font-weight:600;color:#6b7280;background:#f3f4f6;padding:4px 10px;border-radius:9999px;letter-spacing:.04em;text-transform:uppercase">Read-only</span>
        </div>

        {{-- Desktop horizontal --}}
        <div class="hidden sm:flex items-stretch overflow-x-auto pb-1" style="gap:0;min-width:480px">
            @foreach($pipelineStages as $idx => $ps)
            @php
                $isDone = $currentIdx !== false && $idx < $currentIdx;
                $isCur  = $lead->stage === $ps['key'];
                $isNext = $currentIdx !== false && $idx === $currentIdx + 1;
                if ($isCur) {
                    $cardStyle = 'background:linear-gradient(135deg,#7B61FF,#5b4cdb);box-shadow:0 8px 25px rgba(123,97,255,0.3);transform:translateY(-2px)';
                    $iconStyle = 'background:rgba(255,255,255,0.2);color:white';
                    $labelColor = 'color:white';
                    $statusLabel = 'Current'; $statusColor = 'color:rgba(255,255,255,0.85)';
                } elseif ($isDone) {
                    $cardStyle = 'background:#f0fdf4;border:1.5px solid #86efac';
                    $iconStyle = 'background:#dcfce7;color:#16a34a';
                    $labelColor = 'color:#15803d';
                    $statusLabel = 'Done'; $statusColor = 'color:#16a34a';
                } elseif ($isNext) {
                    $cardStyle = 'background:#f5f3ff;border:1.5px solid #c4b5fd';
                    $iconStyle = 'background:#ede9fe;color:#7B61FF';
                    $labelColor = 'color:#6d28d9';
                    $statusLabel = 'Next'; $statusColor = 'color:#7B61FF';
                } else {
                    $cardStyle = 'background:#f9fafb;border:1.5px dashed #e5e7eb';
                    $iconStyle = 'background:#f3f4f6;color:#9ca3af';
                    $labelColor = 'color:#9ca3af';
                    $statusLabel = ''; $statusColor = '';
                }
            @endphp
            <div style="display:flex;align-items:center;flex:1;min-width:0">
                <div style="{{ $cardStyle }};display:flex;flex-direction:column;align-items:center;justify-content:space-between;gap:8px;padding:14px 8px;border-radius:16px;flex:1;min-width:0;transition:all .2s"
                     @if($isCur) aria-current="step" @endif>
                    <div style="height:14px;display:flex;align-items:center;justify-content:center">
                        @if($statusLabel)
                        <span style="{{ $statusColor }};font-size:9px;font-weight:700;letter-spacing:.08em;text-transform:uppercase">{{ $statusLabel }}</span>
                        @endif
                    </div>
                    <div style="{{ $iconStyle }};width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        @if($isDone)
                        <svg style="width:16px;height:16px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        @else
                        <svg style="width:14px;height:14px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="{{ $ps['icon'] }}"/>
                        </svg>
                        @endif
                    </div>
                    <span style="{{ $labelColor }};font-size:11px;font-weight:600;text-align:center;line-height:1.3;width:100%;padding:0 4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $ps['label'] }}</span>
                </div>
                @if(!$loop->last)
                <div style="width:18px;flex-shrink:0;display:flex;align-items:center;justify-content:center">
                    <svg style="width:12px;height:12px;{{ ($isDone || ($currentIdx !== false && array_search($pipelineStages[$idx+1]['key'], $stageOrder) <= $currentIdx)) ? 'color:#7B61FF;opacity:0.6' : 'color:#d1d5db' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                    </svg>
                </div>
                @endif
            </div>
            @endforeach
        </div>

        {{-- Mobile vertical --}}
        <div class="flex flex-col sm:hidden" style="gap:0">
            @foreach($pipelineStages as $idx => $ps)
            @php
                $isDone = $currentIdx !== false && $idx < $currentIdx;
                $isCur  = $lead->stage === $ps['key'];
                $isNext = $currentIdx !== false && $idx === $currentIdx + 1;
                $isLast = $loop->last;
                if ($isCur) {
                    $dotStyle = 'background:linear-gradient(135deg,#7B61FF,#5b4cdb);color:white;box-shadow:0 4px 12px rgba(123,97,255,0.4)';
                    $nameColor = 'color:#7B61FF'; $badge = 'background:#ede9fe;color:#7B61FF'; $badgeLabel = 'Current';
                } elseif ($isDone) {
                    $dotStyle = 'background:#dcfce7;color:#16a34a';
                    $nameColor = 'color:#15803d'; $badge = 'background:#dcfce7;color:#16a34a'; $badgeLabel = 'Done';
                } elseif ($isNext) {
                    $dotStyle = 'background:#ede9fe;color:#7B61FF';
                    $nameColor = 'color:#6d28d9'; $badge = 'background:#f5f3ff;color:#7B61FF'; $badgeLabel = 'Next';
                } else {
                    $dotStyle = 'background:#f3f4f6;color:#d1d5db';
                    $nameColor = 'color:#9ca3af'; $badge = ''; $badgeLabel = '';
                }
                $lineStyle = ($currentIdx !== false && $idx < $currentIdx - 1) || ($isCur && !$isLast)
                    ? 'background:rgba(123,97,255,0.3)'
                    : 'background:#e5e7eb';
            @endphp
            <div style="display:flex;align-items:flex-start;gap:12px">
                <div style="width:32px;flex-shrink:0;display:flex;flex-direction:column;align-items:center">
                    <div style="{{ $dotStyle }};width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        @if($isDone)
                        <svg style="width:14px;height:14px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        @else
                        <svg style="width:13px;height:13px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="{{ $ps['icon'] }}"/>
                        </svg>
                        @endif
                    </div>
                    @if(!$isLast)
                    <div style="{{ $lineStyle }};width:2px;border-radius:9999px;margin-top:4px;min-height:18px;flex:1"></div>
                    @endif
                </div>
                <div style="flex:1;min-width:0;{{ $isLast ? 'padding-bottom:4px' : 'padding-bottom:12px' }};margin-top:6px">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                        <span style="{{ $nameColor }};font-size:13px;font-weight:600">{{ $ps['label'] }}</span>
                        @if($badgeLabel)
                        <span style="{{ $badge }};font-size:10px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;padding:2px 8px;border-radius:9999px">{{ $badgeLabel }}</span>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
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
