@extends('layouts.partner')
@section('title', $dealSummary['name'])
@section('nav') @include('partner._nav') @endsection

@section('content')
@php
    // Only fields from $dealSummary are available here — never the full Lead model.
    $dealId    = $dealSummary['id'];
    $dealName  = $dealSummary['name'];
    $dealStage = $dealSummary['stage'];
    $dealStatus= $dealSummary['status'];
    $daysLeft  = $dealSummary['days_left'];
    $dealValue = $dealSummary['deal_value'];      // total contract value, visible to partner
    $referrerName = $dealSummary['reseller_name'];

    $stageMap = [
        'introduction'  => ['bg-gray-100 text-gray-600',    'Introduction'],
        'presentation'  => ['bg-blue-100 text-blue-700',    'Presentation'],
        'contract_sent' => ['bg-amber-100 text-amber-700',  'Contract Sent'],
        'signed'        => ['bg-purple-100 text-purple-700','Signed'],
        'paid'          => ['bg-green-100 text-green-700',  'Paid'],
    ];
    [$stageBadge, $stageLabel] = $stageMap[$dealStage] ?? ['bg-gray-100 text-gray-600', ucfirst(str_replace('_', ' ', $dealStage))];
    $statusBadge = $dealStatus === 'active'
        ? 'bg-green-100 text-green-700'
        : ($dealStatus === 'expiring' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700');
@endphp

<div class="space-y-5 max-w-3xl">

    {{-- Breadcrumb --}}
    <div class="flex items-center gap-2 text-sm text-gray-400">
        <a href="{{ route('partner.deals') }}" class="hover:text-gray-600 transition-colors">My Deals</a>
        <span>›</span>
        <span class="text-[#1E1B4B] font-medium truncate">{{ $dealName }}</span>
    </div>

    {{-- Deal header --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <div class="flex flex-col sm:flex-row sm:items-start gap-4">
            <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-white font-bold text-lg shrink-0"
                 style="background:#2563EB">
                {{ strtoupper(substr($dealName, 0, 2)) }}
            </div>
            <div class="flex-1 min-w-0">
                <h1 class="text-xl font-bold text-[#1E1B4B]">{{ $dealName }}</h1>
                @if($dealSummary['data']['province'] || $dealSummary['data']['municipality'])
                <p class="text-sm text-gray-400 mt-0.5">
                    {{ $dealSummary['data']['province'] ?? '' }}
                    @if($dealSummary['data']['province'] && $dealSummary['data']['municipality']) › @endif
                    {{ $dealSummary['data']['municipality'] ?? '' }}
                </p>
                @endif
                <div class="flex flex-wrap gap-2 mt-3">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $stageBadge }}">
                        {{ $stageLabel }}
                    </span>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $statusBadge }}">
                        {{ ucfirst($dealStatus) }}
                    </span>
                    @if($daysLeft !== null && $dealStage !== 'paid')
                    @php
                        $dlBadge = ($daysLeft ?? 99) <= 3 ? 'bg-red-100 text-red-700'
                            : (($daysLeft ?? 99) <= 7 ? 'bg-amber-100 text-amber-700' : 'bg-blue-50 text-blue-700');
                        $dlLabel = ($daysLeft ?? 0) <= 0 ? 'Overdue' : $daysLeft . 'd to move stage';
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

    {{-- Partner role notice --}}
    <div class="flex items-center gap-3 px-4 py-3 bg-blue-50 border border-blue-100 rounded-2xl">
        <svg class="w-4 h-4 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-xs text-blue-700">
            You are a <strong>Partner</strong> on this deal. Your individual commission split is shown below.
            Other parties' financial details are not visible to partners.
        </p>
    </div>

    {{-- ── Your Commission Card ─────────────────────────────────────────── --}}
    @if($myPartnerSplit)
    @php
        $splitStatusLabel = match($myPartnerSplit->status ?? 'provisional') {
            'active'           => 'Active',
            'pending_invite'   => 'Pending',
            'invite_failed'    => 'Invite Failed',
            default            => 'Provisional',
        };
        $splitStatusStyle = match($myPartnerSplit->status ?? 'provisional') {
            'active'  => 'background:#dcfce7;color:#15803d',
            default   => 'background:#ede9fe;color:#7B61FF',
        };
    @endphp
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold text-[#1E1B4B]">Your Commission</h3>
                <p class="text-xs text-gray-400 mt-0.5">Your individual share on this deal</p>
            </div>
            <span style="font-size:11px;font-weight:700;padding:3px 12px;border-radius:9999px;{{ $splitStatusStyle }}">
                {{ $splitStatusLabel }}
            </span>
        </div>

        {{-- Commission amount -- large and prominent --}}
        <div class="px-5 pt-5 pb-4">
            <div class="rounded-2xl text-center py-6 px-4 mb-5"
                 style="background:linear-gradient(135deg,#f5f3ff,#ede9fe);border:1.5px solid #c4b5fd">
                <p class="text-[11px] font-bold tracking-widest text-purple-600 uppercase mb-2">Your Estimated Share</p>
                <p class="text-4xl font-extrabold text-[#1E1B4B] tabular-nums mb-1">
                    ₱{{ number_format((int) round($myPartnerSplit->peso_amount ?? 0)) }}
                </p>
                <p class="text-sm text-purple-600 font-medium">
                    @if(($myPartnerSplit->split_share_type ?? '') === 'percentage')
                        {{ number_format((float)$myPartnerSplit->split_share_value, 0) }}% of the commission pool allocated to you
                    @else
                        Fixed amount allocation
                    @endif
                </p>
            </div>

            {{-- Detail rows — only partner-scoped info --}}
            <div class="space-y-3">
                <div class="flex justify-between items-center text-sm py-2 border-b border-gray-50">
                    <span class="text-gray-500">Deal contract value</span>
                    <span class="font-semibold text-[#1E1B4B]">₱{{ number_format($dealValue, 0) }}</span>
                </div>
                <div class="flex justify-between items-center text-sm py-2 border-b border-gray-50">
                    <span class="text-gray-500">Your split type</span>
                    <span class="font-semibold text-[#1E1B4B]">
                        {{ ($myPartnerSplit->split_share_type ?? '') === 'percentage' ? 'Percentage' : 'Fixed Amount' }}
                    </span>
                </div>
                @if(($myPartnerSplit->split_share_type ?? '') === 'percentage')
                <div class="flex justify-between items-center text-sm py-2 border-b border-gray-50">
                    <span class="text-gray-500">Your split percentage</span>
                    <span class="font-bold text-purple-700">{{ number_format((float)$myPartnerSplit->split_share_value, 0) }}%</span>
                </div>
                @endif
                <div class="flex justify-between items-center text-sm py-2">
                    <span class="text-gray-500">Commission status</span>
                    <span class="font-semibold text-[#1E1B4B]">{{ ucfirst($myPartnerSplit->status ?? 'provisional') }}</span>
                </div>
            </div>

            {{-- Status notices --}}
            @php $commStatusOnDeal = $myPartnerSplit->commission_status ?? null; @endphp
            @if(($myPartnerSplit->status ?? '') === 'pending_invite')
            <div class="flex items-start gap-2 mt-4 p-3 bg-amber-50 border border-amber-200 rounded-xl">
                <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <p class="text-xs text-amber-700">Your invitation is pending. Your split will be confirmed once you complete account setup.</p>
            </div>
            @endif

            <p class="text-[10px] text-gray-400 mt-4 leading-relaxed">
                * Estimated amounts are subject to applicable taxes, deductions, and final confirmation by the deal administrator.
            </p>
        </div>
    </div>

    @else
    {{-- No split assigned --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 text-center">
        <div class="w-12 h-12 rounded-2xl bg-gray-50 flex items-center justify-center mx-auto mb-3">
            <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <p class="text-sm font-semibold text-[#1E1B4B] mb-1">No commission split assigned yet</p>
        <p class="text-xs text-gray-400 max-w-xs mx-auto">The deal administrator has not assigned your commission share yet. Check back later or message your Referrer.</p>
    </div>
    @endif

    {{-- ── Deal Stage Progress — read-only ────────────────────────────── --}}
    @php
        $pipelineStages = [
            ['key' => 'introduction',  'label' => 'Introduction'],
            ['key' => 'presentation',  'label' => 'Presentation'],
            ['key' => 'contract_sent', 'label' => 'Contract Sent'],
            ['key' => 'signed',        'label' => 'Signed'],
            ['key' => 'paid',          'label' => 'Paid'],
        ];
        $stageOrder  = array_column($pipelineStages, 'key');
        $currentIdx  = array_search($dealStage, $stageOrder);
    @endphp
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-bold text-[#1E1B4B]">Deal Progress</h3>
                <p class="text-xs text-gray-400 mt-0.5">Stage overview — view only</p>
            </div>
            <span class="text-[10px] font-semibold text-gray-400 bg-gray-100 px-2.5 py-1 rounded-full uppercase tracking-wide">Read-only</span>
        </div>

        <div class="flex items-center gap-1 overflow-x-auto pb-1">
            @foreach($pipelineStages as $idx => $ps)
            @php
                $isDone  = $currentIdx !== false && $idx < $currentIdx;
                $isCur   = $dealStage === $ps['key'];
            @endphp
            <div class="flex items-center flex-1 min-w-0">
                <div class="flex-1 min-w-[60px] text-center py-2 px-1 rounded-xl text-[10px] font-semibold transition-all
                    {{ $isCur ? 'text-white shadow-md' : ($isDone ? 'text-green-700 bg-green-50' : 'text-gray-400 bg-gray-50') }}"
                    style="{{ $isCur ? 'background:linear-gradient(135deg,#2563EB,#1D4ED8)' : '' }}">
                    @if($isDone)
                    <svg class="w-3.5 h-3.5 mx-auto mb-0.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    @else
                    <div class="w-1.5 h-1.5 rounded-full mx-auto mb-0.5 {{ $isCur ? 'bg-white' : 'bg-current' }}"></div>
                    @endif
                    {{ $ps['label'] }}
                </div>
                @if(!$loop->last)
                <div class="w-3 shrink-0 flex items-center justify-center">
                    <svg class="w-2.5 h-2.5 {{ $isDone ? 'text-green-300' : 'text-gray-200' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>

    {{-- ── Referrer (name only, no contact or financial details) ─────── --}}
    @if($referrerName)
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <h3 class="text-sm font-bold text-[#1E1B4B] mb-3">Your Referrer</h3>
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 text-xs font-bold shrink-0">
                {{ strtoupper(substr($referrerName, 0, 2)) }}
            </div>
            <div>
                <p class="text-sm font-semibold text-[#1E1B4B]">{{ $referrerName }}</p>
                <p class="text-xs text-gray-400">Referrer</p>
            </div>
        </div>
    </div>
    @endif

    {{-- ── Actions ───────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('partner.messages') }}?deal_id={{ $dealId }}"
           class="pt-btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
            </svg>
            Message Referrer
        </a>
        <a href="{{ route('partner.deals') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors">
            ← Back to My Deals
        </a>
    </div>

</div>
@endsection
