@extends('layouts.reseller')
@section('title', $lead->name ?? 'Deal Detail')
@section('nav') @include('reseller._nav') @endsection

@section('topbar-actions')
    <a href="{{ route('reseller.deals', $tenantId) }}" class="rs-btn-primary text-sm py-1.5 px-3">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        <span class="hidden sm:inline">My Deals</span>
    </a>
@endsection

@section('content')
@php
    $BASE    = url("reseller/{$tenantId}/deals/{$lead->id}");
    $CSRF    = csrf_token();

    $stageLabels = ['introduction' => 'Introduction','presentation' => 'Presentation','contract_sent' => 'Contract Sent','signed' => 'Signed','paid' => 'Paid'];
    $stageOrder  = ['introduction','presentation','contract_sent','signed','paid'];
    $currentIdx  = array_search($lead->stage, $stageOrder);

    $pendingStageMoveRequest  = collect($pendingApprovals)->where('type', 'deal_stage_move')->first();
    $pendingArchiveRequest    = collect($pendingApprovals)->where('type', 'deal_archive')->first();

    $stageColors = ['introduction'=>'#9CA3AF','presentation'=>'#3B82F6','contract_sent'=>'#F59E0B','signed'=>'#8B5CF6','paid'=>'#10B981'];
    $stageColor  = $stageColors[$lead->stage] ?? '#9CA3AF';
@endphp

<script>
// Blade values injected once — safe for the JS function below
window.__rsDeal = {
    base:         '{{ $BASE }}',
    csrf:         '{{ $CSRF }}',
    currentStage: '{{ $lead->stage }}',
    initAmount:   '{{ number_format((float)($lead->deal_value ?? 0), 2, ".", "") }}',
    stageReqs:    @json($stageRequirements),
};
</script>

<div x-data="rsDealData()" class="space-y-5 max-w-3xl mx-auto">

    {{-- ── LGU IDS Default Amount Confirmation Prompt (Referrer) ── --}}
    @if(($lead->data['amount_defaulted'] ?? false) && ($lead->data['amount_confirmation_status'] ?? '') === 'pending')
    <div x-data="{ visible: true, busy: false, err: null }" x-show="visible" x-cloak x-transition
         class="flex flex-col sm:flex-row sm:items-start gap-4 px-5 py-4 rounded-2xl bg-amber-50 border border-amber-200">
        <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        </div>
        <div class="flex-1">
            <p class="text-sm font-bold text-amber-800">Confirm Deal Amount</p>
            <p class="text-xs text-amber-700 mt-1 leading-relaxed">
                No Deal Amount was provided when this deal was created, so the LGU IDS default of <strong>₱4,000,000</strong> was applied.
                Please confirm this is correct or update it to the actual contract value.
            </p>
            <div class="flex flex-wrap items-center gap-2 mt-3">
                <button @click="async () => {
                            busy = true; err = null;
                            const r = await fetch('/api/leads/{{ $lead->id }}/confirm-default-amount', {
                                method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'},
                                body:'{}'});
                            const d = await r.json().catch(()=>({}));
                            busy = false;
                            if (!r.ok) { err = d.message || 'Could not confirm.'; return; }
                            visible = false;
                        }"
                        :disabled="busy"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold text-white transition-all disabled:opacity-50"
                        style="background:#D97706">
                    <svg x-show="busy" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="busy ? 'Confirming…' : 'Confirm ₱4,000,000'"></span>
                </button>
                <button @click="showUpdateAmount = true"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold text-amber-700 bg-white border border-amber-200 hover:bg-amber-50 transition-all">
                    Change Amount
                </button>
                <button @click="visible = false" class="text-xs text-amber-600 hover:text-amber-800 underline">
                    Remind me later
                </button>
            </div>
            <p x-show="err" class="text-xs text-red-600 mt-2" x-text="err"></p>
        </div>
    </div>
    @endif

    {{-- Pending approval banners ──────────────────────────────── --}}
    @if($pendingStageMoveRequest)
    <div class="flex items-start gap-3 px-4 py-3.5 rounded-2xl bg-amber-50 border border-amber-200">
        <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <div>
            <p class="text-sm font-semibold text-amber-700">Stage change requested — Pending Admin approval</p>
            <p class="text-xs text-amber-600 mt-0.5">
                You requested to move this deal to <strong>{{ ucfirst(str_replace('_', ' ', $pendingStageMoveRequest['request_payload']['target_stage'] ?? '')) }}</strong>.
                An Admin or Manager will review your request.
            </p>
        </div>
    </div>
    @endif

    @if($pendingArchiveRequest)
    <div class="flex items-start gap-3 px-4 py-3.5 rounded-2xl bg-red-50 border border-red-200">
        <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1 12a2 2 0 002 2h8a2 2 0 002-2L19 8"/>
        </svg>
        <div>
            <p class="text-sm font-semibold text-red-700">Archive requested — Pending Admin approval</p>
            <p class="text-xs text-red-600 mt-0.5">Reason: {{ $pendingArchiveRequest['reason'] ?? '—' }}</p>
        </div>
    </div>
    @endif

    {{-- Deal Summary Card ─────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-6">

        {{-- ── Row 1: Identity (left) + Deal Value (right) ────── --}}
        <div class="flex items-start justify-between gap-4">

            {{-- Deal Identity --}}
            <div class="flex items-start gap-4 flex-1 min-w-0">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-white text-lg font-bold shrink-0 shadow-sm"
                     style="background:{{ $stageColor }}">
                    {{ strtoupper(substr($lead->name ?? '??', 0, 2)) }}
                </div>
                <div class="flex-1 min-w-0 pt-0.5">
                    <h1 class="text-xl sm:text-2xl font-bold text-[#1E1B4B] leading-tight break-words">{{ $lead->name }}</h1>
                    <div class="flex flex-wrap items-center gap-2 mt-2">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold text-white"
                              style="background:{{ $stageColor }}">
                            {{ $stageLabels[$lead->stage] ?? ucfirst($lead->stage) }}
                        </span>
                        @if($lead->status === 'expiring')
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">Expiring</span>
                        @endif
                        @if($lead->status === 'expired')
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-600">Expired</span>
                        @endif
                    </div>
                    @if($lead->data['province'] ?? null)
                    <p class="text-sm text-gray-400 mt-2 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        {{ $lead->data['province'] }}{{ ($lead->data['municipality'] ?? null) ? ' · ' . $lead->data['municipality'] : '' }}
                    </p>
                    @endif
                </div>
            </div>

            {{-- Deal Value (top right) --}}
            <div class="text-right shrink-0">
                <div class="flex items-center justify-end gap-1.5 mb-0.5">
                    <p class="text-xs text-gray-400 font-medium">Deal Value</p>
                    <div x-data="{ open: false }" class="relative">
                        <button @click.stop="open = !open" @click.outside="open = false" @keydown.escape.window="open = false"
                                class="w-4 h-4 flex items-center justify-center text-gray-300 hover:text-gray-500 transition-colors" aria-label="Info about Deal Value">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </button>
                        <div x-show="open" x-cloak x-transition
                             class="absolute right-0 top-6 z-40 w-64 bg-white border border-gray-100 rounded-xl shadow-xl p-3 text-left text-xs text-gray-500 leading-relaxed">
                            <strong class="text-gray-700 block mb-1">Deal Value</strong>
                            The current deal or contract amount used for pipeline, financial breakdown, and commission calculations.
                        </div>
                    </div>
                </div>
                <p class="text-2xl font-bold text-[#1E1B4B] tabular-nums">₱{{ number_format((float)($lead->deal_value ?? 0)) }}</p>
                @if($lead->days_left !== null && $lead->stage !== 'paid')
                <div class="flex justify-end mt-2">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium
                        {{ $lead->days_left <= 3 ? 'bg-red-100 text-red-600' : ($lead->days_left <= 7 ? 'bg-amber-100 text-amber-600' : 'bg-gray-100 text-gray-500') }}">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ $lead->days_left > 0 ? $lead->days_left . 'd left' : 'Overdue' }}
                    </span>
                </div>
                @endif
            </div>
        </div>

        {{-- ── Row 2: Commission Breakdown ── --}}
        <div class="mt-4 pt-4 border-t border-gray-100">
            @php
                $bdv  = (float) ($breakdown['deal_value']   ?? $lead->deal_value ?? 0);
                $bbc  = (float) ($breakdown['base_cost']    ?? 0);
                $baa  = (float) ($breakdown['added_amount'] ?? 0);
                $basePct = $bdv > 0 ? round($bbc / $bdv * 100) : 0;
            @endphp

            {{-- Header --}}
            <div class="flex items-center justify-between mb-3">
                <p class="text-xs font-semibold text-[#1E1B4B]">Commission Breakdown</p>
                <div class="flex items-center gap-1">
                    @if($lead->commission_status === 'locked' || $lead->commission_status === 'paid')
                    <svg class="w-3 h-3 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                    @endif
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold
                        @if($lead->commission_status === 'paid') bg-green-100 text-green-700
                        @elseif($lead->commission_status === 'locked') bg-blue-100 text-blue-700
                        @else bg-teal-50 text-teal-600
                        @endif">
                        @if($lead->commission_status === 'paid') Paid
                        @elseif($lead->commission_status === 'locked') Locked
                        @else Estimated
                        @endif
                    </span>
                </div>
            </div>

            {{-- Financial breakdown: Deal Value → Base Cost → Added Amount --}}
            <div class="rounded-xl overflow-hidden border border-gray-100 mb-3">
                <div class="grid grid-cols-3 divide-x divide-gray-100">
                    <div class="px-3 py-2.5 bg-gray-50">
                        <p class="text-[10px] text-gray-400 font-medium">Contract Value</p>
                        <p class="text-xs font-bold text-[#1E1B4B] tabular-nums mt-0.5">₱{{ number_format($bdv, 0) }}</p>
                    </div>
                    <div class="px-3 py-2.5 bg-gray-50">
                        <p class="text-[10px] text-gray-400 font-medium">Base Cost <span class="text-gray-300">({{ $basePct }}%)</span></p>
                        <p class="text-xs font-bold text-gray-500 tabular-nums mt-0.5">₱{{ number_format($bbc, 0) }}</p>
                    </div>
                    <div class="px-3 py-2.5 bg-blue-50">
                        <p class="text-[10px] text-blue-400 font-medium">Added Amount</p>
                        <p class="text-xs font-bold text-blue-700 tabular-nums mt-0.5">₱{{ number_format($baa, 0) }}</p>
                    </div>
                </div>
            </div>

            {{-- Pool bar (partner vs referrer within the pool) --}}
            @php
                $poolPct   = $commissionPool > 0 ? min(100, round(($partnersCommission / $commissionPool) * 100)) : 0;
                $myCommPct = $commissionPool > 0 ? min(100 - $poolPct, round(($myCommission / $commissionPool) * 100)) : 0;
            @endphp
            <div class="w-full h-1.5 rounded-full bg-gray-100 overflow-hidden flex mb-3">
                <div class="h-full rounded-l-full" style="width:{{ $poolPct }}%;background:#7B61FF"></div>
                <div class="h-full" style="width:{{ $myCommPct }}%;background:#0D9488"></div>
            </div>

            {{-- Commission pool split --}}
            <div class="grid grid-cols-3 gap-2">
                <div class="bg-gray-50 rounded-xl px-3 py-2.5">
                    <p class="text-[10px] text-gray-400 font-medium mb-0.5">Pool <span class="text-gray-300">(70%)</span></p>
                    <p class="text-sm font-bold text-[#1E1B4B] tabular-nums">₱{{ number_format($commissionPool, 0) }}</p>
                </div>
                <div class="rounded-xl px-3 py-2.5 {{ $partnersCommission > 0 ? 'bg-purple-50' : 'bg-gray-50' }}">
                    <p class="text-[10px] font-medium mb-0.5 {{ $partnersCommission > 0 ? 'text-purple-400' : 'text-gray-400' }}">
                        Partners <span class="font-normal">({{ count($partnerSplits) }})</span>
                    </p>
                    <p class="text-sm font-bold tabular-nums {{ $partnersCommission > 0 ? 'text-[#7B61FF]' : 'text-gray-300' }}">
                        @if($partnersCommission > 0) −₱{{ number_format($partnersCommission, 0) }}
                        @else ₱0
                        @endif
                    </p>
                </div>
                <div class="bg-teal-50 rounded-xl px-3 py-2.5">
                    <p class="text-[10px] text-teal-500 font-medium mb-0.5">My Share</p>
                    <p class="text-sm font-bold text-[#0D9488] tabular-nums">₱{{ number_format($myCommission, 0) }}</p>
                </div>
            </div>

        </div>

        {{-- Tax disclaimer --}}
        <p class="text-[10px] text-gray-400 mt-3">* All amounts shown are estimates and subject to appropriate taxes and deductions.</p>

        {{-- ── Bottom zone: Action Bar ──────────────────────────── --}}
        <div class="mt-5 pt-4 border-t border-gray-100">

            {{-- Desktop: single row, primary left / archive right --}}
            <div class="hidden sm:flex items-center justify-between gap-3 flex-wrap">
                {{-- Primary actions --}}
                <div class="flex items-center gap-2 flex-wrap">
                    <button @click="showAddNote = true"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold text-white transition-all hover:shadow-sm active:scale-95"
                            style="background:#0D9488">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Add Note
                    </button>
                    <button @click="showUpdateAmount = true"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold text-[#7B61FF] bg-purple-50 hover:bg-purple-100 transition-all active:scale-95">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Update Amount
                    </button>
                    @if(!$pendingStageMoveRequest && $lead->stage !== 'paid')
                    <button @click="showMoveStage = true"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 transition-all active:scale-95">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
                        Move Stage
                    </button>
                    @endif
                    <button @click="showAddPartner = true"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200 transition-all active:scale-95">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                        Add Partner
                    </button>
                    <button @click="showAddReferrer = true"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200 transition-all active:scale-95">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Add Co-Referrer
                    </button>
                </div>
                {{-- Destructive action --}}
                @if(!$pendingArchiveRequest)
                <button @click="showArchive = true"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold text-red-600 bg-red-50 hover:bg-red-100 border border-red-100 transition-all active:scale-95 shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1 12a2 2 0 002 2h8a2 2 0 002-2L19 8"/></svg>
                    Request Archive
                </button>
                @endif
            </div>

            {{-- Mobile: 2-col grid + full-width archive --}}
            <div class="sm:hidden space-y-2">
                <div class="grid grid-cols-2 gap-2">
                    <button @click="showAddNote = true"
                            class="inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl text-xs font-semibold text-white transition-colors"
                            style="background:#0D9488">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Add Note
                    </button>
                    <button @click="showUpdateAmount = true"
                            class="inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl text-xs font-semibold text-[#7B61FF] bg-purple-50 hover:bg-purple-100 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Update Amount
                    </button>
                    @if(!$pendingStageMoveRequest && $lead->stage !== 'paid')
                    <button @click="showMoveStage = true"
                            class="inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl text-xs font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
                        Move Stage
                    </button>
                    @endif
                    <button @click="showAddPartner = true"
                            class="inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl text-xs font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                        Add Partner
                    </button>
                    <button @click="showAddReferrer = true"
                            class="inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl text-xs font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Add Co-Referrer
                    </button>
                </div>
                @if(!$pendingArchiveRequest)
                <button @click="showArchive = true"
                        class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl text-xs font-semibold text-red-600 bg-red-50 hover:bg-red-100 border border-red-100 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1 12a2 2 0 002 2h8a2 2 0 002-2L19 8"/></svg>
                    Request Archive
                </button>
                @endif
            </div>

        </div>
    </div>

    {{-- Stage Tracker ─────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <h2 class="text-sm font-bold text-[#1E1B4B] mb-4">Stage Progress</h2>
        <div class="flex items-center gap-0">
            @foreach($stageOrder as $i => $stage)
            @php
                $isDone    = $i < $currentIdx;
                $isCurrent = $i === $currentIdx;
                $color     = $stageColors[$stage] ?? '#9CA3AF';
            @endphp
            <div class="flex-1 flex flex-col items-center">
                <div class="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0 z-10"
                     style="background:{{ $isDone || $isCurrent ? $color : '#E5E7EB' }}">
                    @if($isDone)
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    @else
                        {{ $i + 1 }}
                    @endif
                </div>
                <p class="text-[9px] font-medium mt-1 text-center leading-tight {{ $isCurrent ? 'text-[#1E1B4B]' : 'text-gray-400' }}">
                    {{ $stageLabels[$stage] ?? $stage }}
                </p>
            </div>
            @if(!$loop->last)
            <div class="flex-1 h-0.5 -mt-4" style="background:{{ $i < $currentIdx ? $color : '#E5E7EB' }}"></div>
            @endif
            @endforeach
        </div>
    </div>

    {{-- Referrers & Partners ─────────────────────────────────── --}}
    @php
        $canRemovePartner = !in_array($lead->commission_status ?? '', ['locked', 'paid']);
        $primarySplits    = collect($splits)->where('role', 'primary')->values();
        $secondarySplits  = collect($splits)->where('role', 'secondary')->values();
        $hasAnyone        = $primarySplits->count() || $secondarySplits->count() || count($partnerSplits);
    @endphp
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <div class="flex items-center gap-2">
                <h2 class="text-sm font-bold text-[#1E1B4B]">Referrers & Partners</h2>
                <span class="text-[10px] font-semibold text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">
                    {{ $primarySplits->count() + $secondarySplits->count() + count($partnerSplits) }} listed
                </span>
            </div>
            <div class="flex items-center gap-2">
                <button @click="showAddReferrer = true"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    Co-Referrer
                </button>
                <button @click="showAddPartner = true"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold text-teal-700 bg-teal-50 hover:bg-teal-100 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    Partner
                </button>
            </div>
        </div>

        {{-- Column headers --}}
        <div class="hidden sm:grid grid-cols-4 gap-0 bg-gray-50 border-b border-gray-100 px-5 py-2">
            <div class="col-span-2 text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Person / Role</div>
            <div class="text-right text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Split</div>
            <div class="text-right text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Est. Commission</div>
        </div>

        {{-- ── Primary Referrer(s) ── --}}
        @if($primarySplits->count())
        <div class="divide-y divide-gray-50">
            @foreach($primarySplits as $split)
            @php $rAmt = round($commissionPool * (float)($split->percentage ?? 0) / 100, 0); @endphp
            <div class="px-5 py-3.5 flex items-center justify-between gap-4">
                <div class="flex items-center gap-2.5 min-w-0 flex-1">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0 text-green-700" style="background:#dcfce7">
                        {{ strtoupper(substr($split->reseller_name ?? '?', 0, 2)) }}
                    </div>
                    <div class="min-w-0">
                        <p class="font-semibold text-[#1E1B4B] text-sm truncate">{{ $split->reseller_name ?? '—' }}</p>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-green-100 text-green-700">Primary Referrer</span>
                            @if(strtolower($split->reseller_name ?? '') === strtolower($reseller->name ?? ''))
                            <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-purple-100 text-purple-700">You</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="text-right shrink-0">
                    <p class="text-sm font-bold text-[#1E1B4B]">{{ number_format((float)($split->percentage ?? 0), 1) }}%</p>
                    <p class="text-xs text-gray-400 hidden sm:block">of pool</p>
                </div>
                <div class="text-right shrink-0 min-w-[90px]">
                    <p class="text-sm font-bold text-green-700">₱{{ number_format($rAmt, 0) }}</p>
                    <p class="text-[10px] text-gray-400 hidden sm:block">estimated</p>
                </div>
            </div>
            @endforeach
        </div>
        @endif

        {{-- ── Co-Referrers ── --}}
        @if($secondarySplits->count())
        @if($primarySplits->count())
        <div class="px-5 py-1.5 bg-gray-50/60 border-t border-gray-100">
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Co-Referrers</p>
        </div>
        @endif
        <div class="divide-y divide-gray-50">
            @foreach($secondarySplits as $split)
            @php $rAmt = round($commissionPool * (float)($split->percentage ?? 0) / 100, 0); @endphp
            <div class="px-5 py-3.5 flex items-center justify-between gap-4">
                <div class="flex items-center gap-2.5 min-w-0 flex-1">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0 text-blue-700" style="background:#dbeafe">
                        {{ strtoupper(substr($split->reseller_name ?? '?', 0, 2)) }}
                    </div>
                    <div class="min-w-0">
                        <p class="font-semibold text-[#1E1B4B] text-sm truncate">{{ $split->reseller_name ?? '—' }}</p>
                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-blue-100 text-blue-700">Co-Referrer</span>
                    </div>
                </div>
                <div class="text-right shrink-0">
                    <p class="text-sm font-bold text-[#1E1B4B]">{{ number_format((float)($split->percentage ?? 0), 1) }}%</p>
                    <p class="text-xs text-gray-400 hidden sm:block">of pool</p>
                </div>
                <div class="text-right shrink-0 min-w-[90px]">
                    <p class="text-sm font-bold text-blue-700">₱{{ number_format($rAmt, 0) }}</p>
                    <p class="text-[10px] text-gray-400 hidden sm:block">estimated</p>
                </div>
            </div>
            @endforeach
        </div>
        @endif

        {{-- ── Partners ── --}}
        @if(count($partnerSplits))
        @if($primarySplits->count() || $secondarySplits->count())
        <div class="px-5 py-1.5 bg-gray-50/60 border-t border-gray-100">
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Partners</p>
        </div>
        @endif
        <div class="divide-y divide-gray-50">
            @foreach($partnerSplits as $ps)
            @php $pStatus = $ps['status'] ?? 'provisional'; @endphp
            <div class="px-5 py-3.5 flex items-center justify-between gap-4">
                <div class="flex items-center gap-2.5 min-w-0 flex-1">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold text-teal-700 shrink-0" style="background:#CCFBF1">
                        {{ strtoupper(substr($ps['partner_name'] ?? '?', 0, 2)) }}
                    </div>
                    <div class="min-w-0">
                        <p class="font-semibold text-[#1E1B4B] text-sm truncate">{{ $ps['partner_name'] ?? '—' }}</p>
                        <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                            <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-teal-100 text-teal-700">Partner</span>
                            <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded-full
                                {{ $pStatus === 'active' ? 'bg-green-100 text-green-700' : ($pStatus === 'pending_invite' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-500') }}">
                                @if($pStatus === 'active') Active
                                @elseif($pStatus === 'pending_invite') Pending Invite
                                @elseif($pStatus === 'invite_failed') Invite Failed
                                @else Not Invited @endif
                            </span>
                            @if($ps['partner_email'] ?? '')
                            <span class="text-[9px] text-gray-400 truncate max-w-[120px]">{{ $ps['partner_email'] }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="text-right shrink-0">
                    <p class="text-sm font-bold text-[#1E1B4B]">{{ $ps['display_share'] ?? '—' }}</p>
                    <p class="text-xs text-gray-400 hidden sm:block">of pool</p>
                </div>
                <div class="text-right shrink-0 min-w-[90px] flex items-center justify-end gap-2">
                    <div>
                        <p class="text-sm font-bold text-[#0D9488]">₱{{ number_format($ps['estimated_commission'] ?? 0, 0) }}</p>
                        <p class="text-[10px] text-gray-400 hidden sm:block">estimated</p>
                    </div>
                    @if($canRemovePartner)
                    <button onclick="rbRemovePartner('{{ $ps['id'] }}', '{{ addslashes($ps['partner_name'] ?? 'this partner') }}')"
                            title="Remove partner"
                            class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-gray-300 hover:text-red-500 hover:bg-red-50 transition-colors shrink-0"
                            aria-label="Remove {{ $ps['partner_name'] ?? 'partner' }}">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Empty state --}}
        @if(!$hasAnyone)
        <div class="flex flex-col items-center justify-center py-10 px-5 text-center">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-3" style="background:#CCFBF1">
                <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <p class="text-sm font-semibold text-[#1E1B4B]">No one listed yet</p>
            <p class="text-xs text-gray-400 mt-1 max-w-xs leading-relaxed">Referrer splits and partners will appear here once assigned.</p>
        </div>
        @endif

        {{-- Summary footer --}}
        @if($hasAnyone)
        <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
            <p class="text-xs text-gray-400">
                {{ $primarySplits->count() + $secondarySplits->count() }} Referrer{{ ($primarySplits->count() + $secondarySplits->count()) !== 1 ? 's' : '' }}
                · {{ count($partnerSplits) }} Partner{{ count($partnerSplits) !== 1 ? 's' : '' }}
            </p>
            <p class="text-xs font-bold text-[#0D9488]">Pool: ₱{{ number_format($commissionPool, 0) }}</p>
        </div>
        <div class="px-5 py-2 border-t border-gray-50">
            <p class="text-[10px] text-gray-400">* All amounts are estimates and subject to appropriate taxes and deductions.</p>
        </div>
        @endif
    </div>

    {{-- Notes ────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
            <h2 class="text-sm font-bold text-[#1E1B4B]">Notes</h2>
            <button @click="showAddNote = true" class="text-xs text-teal-600 font-semibold hover:underline">+ Add Note</button>
        </div>
        @if(count($notes) === 0)
        <div class="px-5 py-8 text-center text-xs text-gray-400">No notes yet. Add your first note above.</div>
        @else
        <div class="divide-y divide-gray-50">
            @foreach($notes as $note)
            <div class="px-5 py-3">
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-xs font-semibold text-gray-700">{{ $note['author'] ?? 'Unknown' }}</span>
                    <span class="text-[10px] text-gray-400">{{ \Carbon\Carbon::parse($note['created_at'])->diffForHumans() }}</span>
                </div>
                <p class="text-sm text-gray-600 leading-relaxed break-words">{!! preg_replace('~(https?://[^\s<>"\']+)~i','<a href="$1" target="_blank" rel="noopener noreferrer" class="text-teal-600 underline hover:text-teal-800 break-all">$1</a>',e($note['text'] ?? '')) !!}</p>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Activity Timeline ─────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
        <div class="px-5 py-3.5 border-b border-gray-100">
            <h2 class="text-sm font-bold text-[#1E1B4B]">Activity</h2>
        </div>
        @if(count($history) === 0)
        <div class="px-5 py-8 text-center text-xs text-gray-400">No activity recorded yet.</div>
        @else
        <div class="divide-y divide-gray-50">
            @foreach($history as $h)
            <div class="px-5 py-3 flex items-start gap-3">
                <span class="w-1.5 h-1.5 rounded-full bg-gray-300 mt-2 shrink-0"></span>
                <div class="flex-1 min-w-0">
                    <p class="text-xs text-gray-700">{{ $h->action ?? 'Activity recorded' }}</p>
                    @if($h->actor_name ?? null)
                    <p class="text-[10px] text-gray-400 mt-0.5">{{ $h->actor_name }} · {{ \Carbon\Carbon::parse($h->created_at)->diffForHumans() }}</p>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- ── MODALS ────────────────────────────────────────────────── --}}

    {{-- Add Note modal --}}
    <div x-show="showAddNote" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md" @click.stop>
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <h3 class="font-bold text-[#1E1B4B]">Add Note</h3>
                <button @click="showAddNote = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="px-5 py-4 space-y-4">
                <textarea x-model="noteBody" rows="4" placeholder="Write your note…"
                          class="form-input w-full text-sm resize-none" required></textarea>
                <div x-show="noteError" class="text-xs text-red-600 bg-red-50 px-3 py-2 rounded-lg" x-text="noteError"></div>
            </div>
            <div class="flex gap-3 justify-end px-5 py-4 border-t border-gray-100">
                <button @click="showAddNote = false" class="btn-secondary text-sm">Cancel</button>
                <button @click="saveNote()" :disabled="!noteBody.trim() || noteSaving"
                        class="rs-btn-primary text-sm" x-text="noteSaving ? 'Saving…' : 'Add Note'"></button>
            </div>
        </div>
    </div>

    {{-- Update Amount modal --}}
    <div x-show="showUpdateAmount" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md" @click.stop>
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <h3 class="font-bold text-[#1E1B4B]">Update Deal Amount</h3>
                <button @click="showUpdateAmount = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="px-5 py-4 space-y-3">
                <div class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                    Changing the deal amount updates the financial breakdown and may affect commission calculations. Admins and Managers will be notified.
                </div>
                <div>
                    <label class="form-label">New Deal Amount (₱)</label>
                    <input x-model="newAmount" type="number" min="1" step="0.01" placeholder="0.00" class="form-input w-full">
                </div>
                <div>
                    <label class="form-label">Reason for change <span class="text-red-400">*</span></label>
                    <textarea x-model="amountReason" rows="2" placeholder="Explain why the amount is changing…" class="form-input w-full text-sm resize-none"></textarea>
                </div>
                <div x-show="amountError" class="text-xs text-red-600 bg-red-50 px-3 py-2 rounded-lg" x-text="amountError"></div>
            </div>
            <div class="flex gap-3 justify-end px-5 py-4 border-t border-gray-100">
                <button @click="showUpdateAmount = false" class="btn-secondary text-sm">Cancel</button>
                <button @click="saveAmount()" :disabled="!newAmount || !amountReason.trim() || amountSaving"
                        class="rs-btn-primary text-sm" x-text="amountSaving ? 'Updating…' : 'Update Amount'"></button>
            </div>
        </div>
    </div>

    {{-- Move Stage modal --}}
    <div x-show="showMoveStage" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg" @click.stop>
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div>
                    <h3 class="font-bold text-[#1E1B4B]">Move Deal Stage</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Check all requirements before moving the stage.</p>
                </div>
                <button @click="showMoveStage = false; resetStageModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="px-5 py-4 space-y-4">
                {{-- Stage selector --}}
                <div>
                    <label class="form-label">Move to Stage</label>
                    <select x-model="targetStage" @change="reqChecks = {}; stageError = ''" class="form-input w-full">
                        <option value="">Select target stage…</option>
                        @php $stageOrder = ['introduction','presentation','contract_sent','signed','paid']; $currentIdx = array_search($lead->stage, $stageOrder); @endphp
                        @foreach($stageOrder as $idx => $s)
                            @if($s !== $lead->stage && $idx > $currentIdx)
                            <option value="{{ $s }}">{{ $stageLabels[$s] }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>

                {{-- Requirements checklist (shows when target selected and requirements exist) --}}
                <div x-show="targetStage && currentReqs().length" x-cloak class="space-y-1">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Requirements for this transition</p>
                    <template x-for="req in currentReqs()" :key="req.id">
                        <label class="flex items-start gap-3 px-3 py-2.5 rounded-xl cursor-pointer transition-colors"
                               :class="reqChecks[req.id] ? 'bg-green-50 border border-green-200' : 'bg-gray-50 border border-gray-200 hover:border-gray-300'">
                            <input type="checkbox" :id="'req_' + req.id"
                                   :checked="reqChecks[req.id]"
                                   @change="reqChecks = {...reqChecks, [req.id]: $event.target.checked}"
                                   class="mt-0.5 w-4 h-4 rounded accent-green-600 shrink-0">
                            <div class="flex-1 min-w-0">
                                <span class="text-sm" :class="reqChecks[req.id] ? 'text-green-700 line-through' : 'text-gray-700'" x-text="req.label"></span>
                                <span x-show="req.required && !reqChecks[req.id]" class="ml-1.5 text-[10px] font-bold text-red-500 uppercase tracking-wide">Required</span>
                                <span x-show="!req.required" class="ml-1.5 text-[10px] text-gray-400 uppercase tracking-wide">Optional</span>
                            </div>
                        </label>
                    </template>

                    {{-- Needs-approval notice --}}
                    <div x-show="needsApproval()" class="flex items-start gap-2 mt-2 px-3 py-2.5 rounded-xl bg-amber-50 border border-amber-200">
                        <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <div>
                            <p class="text-xs font-semibold text-amber-700">Admin approval required</p>
                            <p class="text-xs text-amber-600 mt-0.5">Some required items are not yet completed. Your request will be sent to an Admin or Manager for review.</p>
                        </div>
                    </div>
                    <div x-show="!needsApproval() && targetStage && currentReqs().length" class="flex items-center gap-2 mt-2 px-3 py-2.5 rounded-xl bg-green-50 border border-green-200">
                        <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs font-semibold text-green-700">All required items confirmed — you can move the stage directly.</p>
                    </div>
                </div>

                {{-- Note/reason field --}}
                <div>
                    <label class="form-label">
                        <span x-text="needsApproval() ? 'Reason for approval request' : 'Note (optional)'"></span>
                        <span x-show="needsApproval()" class="text-red-400 ml-1">*</span>
                    </label>
                    <textarea x-model="stageReason" rows="2"
                              :placeholder="needsApproval() ? 'Explain what you need help with or when requirements will be met…' : 'Optional note about this stage change…'"
                              class="form-input w-full text-sm resize-none"></textarea>
                </div>

                <div x-show="stageError" class="text-xs text-red-600 bg-red-50 px-3 py-2 rounded-lg" x-text="stageError"></div>
            </div>
            <div class="flex gap-3 justify-end px-5 py-4 border-t border-gray-100">
                <button @click="showMoveStage = false; resetStageModal()" class="btn-secondary text-sm">Cancel</button>
                <button @click="moveStage()"
                        :disabled="!targetStage || stageSaving"
                        :class="needsApproval() ? 'bg-amber-500 hover:bg-amber-600' : ''"
                        class="rs-btn-primary text-sm"
                        x-text="stageSaving ? 'Processing…' : (needsApproval() ? 'Request Approval →' : 'Move Stage →')"></button>
            </div>
        </div>
    </div>

    {{-- Archive Request modal --}}
    <div x-show="showArchive" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md" @click.stop>
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div>
                    <h3 class="font-bold text-[#1E1B4B]">Request Deal Archive</h3>
                    <p class="text-[10px] text-gray-400 mt-0.5">Requires Admin or Manager approval before taking effect.</p>
                </div>
                <button @click="showArchive = false; archiveError = ''" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="px-5 py-4 space-y-3">
                {{-- TEST — block paid deals --}}
                @if($lead->stage === 'paid')
                <div class="flex items-start gap-2 px-3 py-2.5 rounded-xl bg-amber-50 border border-amber-200">
                    <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                    <p class="text-xs text-amber-700 font-medium">Paid deals cannot be archived. Contact an admin if this is a mistake.</p>
                </div>
                @else
                <div class="flex items-start gap-2 px-3 py-2.5 rounded-xl bg-red-50 border border-red-100">
                    <svg class="w-4 h-4 text-red-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1 12a2 2 0 002 2h8a2 2 0 002-2L19 8"/></svg>
                    <p class="text-xs text-red-700">The deal stays active until an Admin or Manager approves this request. You'll receive a notification with their decision.</p>
                </div>
                <div>
                    <label class="form-label">Reason for archiving <span class="text-red-400">*</span></label>
                    <select x-model="archiveReason" class="form-input w-full">
                        <option value="">Select a reason…</option>
                        <option>LGU no longer interested</option>
                        <option>Duplicate deal</option>
                        <option>Wrong LGU / contact</option>
                        <option>Deal inactive for too long</option>
                        <option>Replaced by another deal</option>
                        <option>Other</option>
                    </select>
                </div>
                {{-- NOTIFY — allow free-text detail when "Other" or always --}}
                <div>
                    <label class="form-label">Additional details <span class="text-gray-300 font-normal">(optional)</span></label>
                    <textarea x-model="archiveDetail" rows="2" class="form-input w-full text-sm resize-none"
                              placeholder="Any extra context for the admin reviewing this request…"></textarea>
                </div>
                @endif
                <div x-show="archiveError" class="text-xs text-red-600 bg-red-50 px-3 py-2 rounded-lg" x-text="archiveError"></div>
            </div>
            <div class="flex gap-3 justify-end px-5 py-4 border-t border-gray-100">
                <button @click="showArchive = false; archiveError = ''" class="btn-secondary text-sm">Cancel</button>
                @if($lead->stage !== 'paid')
                <button @click="submitArchive()"
                        :disabled="!archiveReason || archiveSaving"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white bg-red-500 hover:bg-red-600 transition-colors disabled:opacity-50">
                    <svg x-show="archiveSaving" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                    <span x-text="archiveSaving ? 'Submitting…' : 'Submit Archive Request'"></span>
                </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Add Partner modal --}}
    @php
        $poolLocked       = in_array($lead->commission_status, ['locked', 'paid']);
        $alreadyAllocated = $partnersCommission;
        $poolRemaining    = max(0.0, $commissionPool - $alreadyAllocated);
    @endphp
    <div x-show="showAddPartner" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4"
         x-data="{
             partnerName: '', partnerEmail: '', partnerSplit: '', partnerType: 'percentage',
             partnerSaving: false, partnerError: '',
             pool: {{ $commissionPool }},
             alreadyAllocated: {{ $alreadyAllocated }},
             get remaining() { return Math.max(0, this.pool - this.alreadyAllocated); },
             get previewAmount() {
                 const v = parseFloat(this.partnerSplit) || 0;
                 if (this.partnerType === 'fixed_amount') return Math.min(v, this.remaining);
                 return Math.round(this.pool * Math.min(v, 100) / 100 * 100) / 100;
             },
             get partnerGets() { return Math.min(this.previewAmount, this.remaining); },
             get youKeep() { return Math.max(0, this.remaining - this.partnerGets); },
             get overCap() {
                 return this.partnerGets > this.remaining + 0.05;
             },
             get maxInput() {
                 if (this.partnerType === 'fixed_amount') return this.remaining;
                 return this.remaining > 0 && this.pool > 0 ? Math.floor(this.remaining / this.pool * 10000) / 100 : 0;
             },
             async savePartner() {
                 if (!this.partnerName || !this.partnerSplit || this.partnerSaving) return;
                 if (this.overCap) { this.partnerError = 'Split exceeds remaining capacity of ₱' + Math.round(this.remaining).toLocaleString(); return; }
                 this.partnerSaving = true; this.partnerError = '';
                 const r = await fetch('{{ $BASE }}/partners', {
                     method: 'POST', credentials: 'same-origin',
                     headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ $CSRF }}', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                     body: JSON.stringify({ partner_name: this.partnerName, partner_email: this.partnerEmail || null, split_share_value: parseFloat(this.partnerSplit), split_share_type: this.partnerType }),
                 });
                 const d = await r.json().catch(() => ({}));
                 this.partnerSaving = false;
                 if (!r.ok) { this.partnerError = d.error || 'Could not add partner.'; return; }
                 this.showAddPartner = false;
                 window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'success', message: d.message || 'Partner added.' } }));
                 setTimeout(() => window.location.reload(), 800);
             }
         }">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md" @click.stop>
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div>
                    <h3 class="font-bold text-[#1E1B4B]">Add Partner</h3>
                    <p class="text-[10px] text-gray-400 mt-0.5">Partner share comes out of the commission pool.</p>
                </div>
                <button @click="showAddPartner = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            @if($poolLocked)
            {{-- Locked notice --}}
            <div class="mx-5 mt-4 flex items-start gap-2 px-3 py-2.5 rounded-xl bg-blue-50 border border-blue-100">
                <svg class="w-4 h-4 text-blue-400 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                <p class="text-xs text-blue-700 font-medium">Commission is {{ $lead->commission_status }}. Partner splits are locked. Contact an admin to make changes.</p>
            </div>
            @endif

            <div class="px-5 py-4 space-y-3">
                {{-- Pool context bar --}}
                <div class="rounded-xl px-3 py-2.5 space-y-1.5" style="background:#F0FDFA;border:1px solid #99F6E4">
                    <div class="flex items-center justify-between text-[10px]">
                        <span class="text-teal-600 font-semibold">Commission Pool</span>
                        <span class="text-teal-700 font-bold tabular-nums">₱{{ number_format($commissionPool, 0) }}</span>
                    </div>
                    @if($alreadyAllocated > 0)
                    <div class="flex items-center justify-between text-[10px]">
                        <span class="text-purple-500">Already allocated ({{ count($partnerSplits) }} partner{{ count($partnerSplits) !== 1 ? 's' : '' }})</span>
                        <span class="text-purple-600 font-semibold tabular-nums">−₱{{ number_format($alreadyAllocated, 0) }}</span>
                    </div>
                    @endif
                    <div class="flex items-center justify-between text-[10px] border-t border-teal-100 pt-1">
                        <span class="text-teal-700 font-semibold">Available capacity</span>
                        <span class="font-bold tabular-nums" :class="remaining <= 0 ? 'text-red-500' : 'text-teal-700'">
                            ₱{{ number_format($poolRemaining, 0) }}
                        </span>
                    </div>
                </div>

                @if($poolRemaining <= 0 && !$poolLocked)
                <div class="flex items-start gap-2 px-3 py-2.5 rounded-xl bg-red-50 border border-red-100">
                    <p class="text-xs text-red-700 font-medium">The commission pool is fully allocated. Remove or reduce an existing partner split before adding another.</p>
                </div>
                @endif

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">Partner Name <span class="text-red-400">*</span></label>
                        <input x-model="partnerName" type="text" class="form-input w-full text-sm" placeholder="Full name" {{ $poolLocked ? 'disabled' : '' }}>
                    </div>
                    <div>
                        <div class="flex items-center gap-1.5 mb-1">
                            <label class="form-label mb-0">Partner Email</label>
                            <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-400">Optional</span>
                        </div>
                        <input x-model="partnerEmail" type="email" class="form-input w-full text-sm" placeholder="email@example.com" {{ $poolLocked ? 'disabled' : '' }}>
                        {{-- Live invite hint --}}
                        <p x-show="partnerEmail && partnerEmail.includes('@')" x-cloak
                           class="text-[10px] mt-1 font-medium" style="color:#0F766E">
                            ✉ Invite email will be sent when saved.
                        </p>
                        <p x-show="!partnerEmail" class="text-[10px] mt-1 text-gray-400">
                            Leave blank to add without a platform invite.
                        </p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">Split Amount <span class="text-red-400">*</span>
                            <span class="text-[10px] text-gray-400 font-normal ml-1" x-show="partnerType === 'percentage'">max <span x-text="maxInput + '%'"></span></span>
                            <span class="text-[10px] text-gray-400 font-normal ml-1" x-show="partnerType === 'fixed_amount'">max ₱<span x-text="Math.round(maxInput).toLocaleString()"></span></span>
                        </label>
                        <input x-model="partnerSplit" type="number" min="0.01" step="0.01"
                               :max="maxInput"
                               :class="overCap ? 'border-red-400 focus:ring-red-400' : ''"
                               class="form-input w-full text-sm" {{ $poolLocked ? 'disabled' : '' }}>
                    </div>
                    <div>
                        <label class="form-label">Split Type</label>
                        <select x-model="partnerType" class="form-input w-full text-sm" {{ $poolLocked ? 'disabled' : '' }}>
                            <option value="percentage">Percentage (%)</option>
                            <option value="fixed_amount">Fixed Amount (₱)</option>
                        </select>
                    </div>
                </div>

                {{-- Live preview --}}
                <template x-if="partnerSplit && parseFloat(partnerSplit) > 0">
                    <div class="rounded-xl px-3 py-2.5 space-y-1" :class="overCap ? 'bg-red-50 border border-red-100' : 'bg-gray-50'">
                        <p class="text-[10px] font-semibold text-gray-500 mb-1">Preview</p>
                        <div class="flex items-center justify-between text-[10px]">
                            <span class="text-purple-500">Partner gets</span>
                            <span class="font-bold text-[#7B61FF] tabular-nums" x-text="'₱' + Math.round(partnerGets).toLocaleString()"></span>
                        </div>
                        <div class="flex items-center justify-between text-[10px]">
                            <span class="text-teal-600">Your remaining share</span>
                            <span class="font-bold text-[#0D9488] tabular-nums" x-text="'₱' + Math.round(youKeep).toLocaleString()"></span>
                        </div>
                        <p x-show="overCap" class="text-[10px] text-red-600 font-semibold mt-1">Exceeds capacity — reduce the split amount.</p>
                    </div>
                </template>

                <div x-show="partnerError" class="text-xs text-red-600 bg-red-50 px-3 py-2 rounded-lg" x-text="partnerError"></div>
            </div>
            <div class="flex gap-3 justify-end px-5 py-4 border-t border-gray-100">
                <button @click="showAddPartner = false" class="btn-secondary text-sm">Cancel</button>
                <button @click="savePartner()"
                        :disabled="!partnerName || !partnerSplit || partnerSaving || overCap {{ $poolLocked ? '|| true' : '' }}"
                        class="rs-btn-primary text-sm"
                        x-text="partnerSaving ? 'Saving…' : (partnerEmail && partnerEmail.includes('@') ? 'Add & Send Invite' : 'Add Partner')"></button>
            </div>
        </div>
    </div>

    {{-- Add Co-Referrer modal --}}
    <div x-show="showAddReferrer" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md" @click.stop>
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div>
                    <h3 class="font-bold text-[#1E1B4B]">Add Co-Referrer</h3>
                    <p class="text-xs text-gray-400 mt-0.5">An invite or in-app notification will be sent.</p>
                </div>
                <button @click="showAddReferrer = false; refName=''; refSplit=''; refError=''" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="px-5 py-4 space-y-3">
                <div class="text-xs text-blue-700 bg-blue-50 border border-blue-100 rounded-lg px-3 py-2 leading-relaxed">
                    Enter the email address of the person you want to add as a co-referrer. If they already have a Referrer account, they'll receive an in-app notification. If not, they'll receive an invitation email.
                </div>
                <div>
                    <label class="form-label">Email Address <span class="text-red-400">*</span></label>
                    <input x-model="refName" type="email" class="form-input w-full text-sm" placeholder="referrer@example.com" autocomplete="email">
                </div>
                <div>
                    <label class="form-label">Commission Share (%) <span class="text-red-400">*</span></label>
                    <input x-model="refSplit" type="number" min="0" max="100" step="0.01" class="form-input w-full text-sm" placeholder="e.g. 10">
                    <p class="text-xs text-gray-400 mt-1">Your total commission across all co-referrers cannot exceed 100%.</p>
                </div>
                <div x-show="refError" class="text-xs text-red-600 bg-red-50 px-3 py-2 rounded-lg" x-text="refError"></div>
            </div>
            <div class="flex gap-3 justify-end px-5 py-4 border-t border-gray-100">
                <button @click="showAddReferrer = false; refName=''; refSplit=''; refError=''" class="btn-secondary text-sm">Cancel</button>
                <button @click="saveReferrer()" :disabled="!refName || !refSplit || refSaving"
                        class="rs-btn-primary text-sm" x-text="refSaving ? 'Adding…' : 'Add Co-Referrer'"></button>
            </div>
        </div>
    </div>

</div>

{{-- ── Remove Partner Confirmation Modal ──────────────────────────────────── --}}
<div id="rb-remove-partner-modal"
     style="display:none;position:fixed;inset:0;background:rgba(15,15,35,.55);z-index:9999;align-items:center;justify-content:center;padding:16px"
     role="dialog" aria-modal="true">
    <div style="background:white;border-radius:20px;max-width:400px;width:100%;padding:28px;box-shadow:0 24px 64px rgba(0,0,0,.2);text-align:center">
        <div style="width:48px;height:48px;border-radius:14px;background:#fee2e2;display:flex;align-items:center;justify-content:center;margin:0 auto 14px">
            <svg style="width:22px;height:22px;color:#dc2626" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        </div>
        <p style="font-size:11px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#dc2626;margin:0 0 6px">Remove Partner</p>
        <h3 id="rb-remove-partner-name" style="font-size:16px;font-weight:700;color:#1E1B4B;margin:0 0 8px"></h3>
        <p style="font-size:13px;color:#9ca3af;margin:0 0 22px;line-height:1.6">
            This partner will be removed from this deal. Their commission split will no longer be counted. This can be undone by adding them again.
        </p>
        <div style="display:flex;gap:10px">
            <button id="rb-remove-cancel"
                    onclick="document.getElementById('rb-remove-partner-modal').style.display='none'"
                    style="flex:1;padding:10px;border-radius:12px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:13px;font-weight:600;cursor:pointer">
                Cancel
            </button>
            <button id="rb-remove-confirm"
                    style="flex:1;padding:10px;border-radius:12px;background:#dc2626;color:white;border:none;font-size:13px;font-weight:600;cursor:pointer">
                Remove Partner
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
function rsDealData() {
    const { base, csrf, currentStage, initAmount, stageReqs } = window.__rsDeal;

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body),
        }).then(r => r.json().catch(() => ({})).then(d => ({ ok: r.ok, data: d })));
    }

    function patch(url, body) {
        return fetch(url, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body),
        }).then(r => r.json().catch(() => ({})).then(d => ({ ok: r.ok, data: d })));
    }

    return {
        showAddNote:      false,
        showUpdateAmount: false,
        showMoveStage:    false,
        showArchive:      false,
        showAddPartner:   false,
        showAddReferrer:  false,

        noteBody:    '',
        noteSaving:  false,
        noteError:   '',

        newAmount:    initAmount,
        amountReason: '',
        amountSaving: false,
        amountError:  '',

        targetStage:  '',
        stageReason:  '',
        stageSaving:  false,
        stageError:   '',
        stageReqs:    stageReqs,
        reqChecks:    {},

        // Computed helpers (methods, not getters, to avoid > in HTML attrs)
        transitionKey()   { return currentStage + '_to_' + this.targetStage; },
        currentReqs()     { return this.stageReqs[this.transitionKey()] || []; },
        missingRequired() { return this.currentReqs().filter(r => r.required && !this.reqChecks[r.id]); },
        needsApproval()   { return this.targetStage !== '' && this.missingRequired().length !== 0; },

        resetStageModal() {
            this.targetStage = ''; this.stageReason = ''; this.reqChecks = {}; this.stageError = '';
        },

        archiveReason:  '',
        archiveDetail:  '',
        archiveSaving:  false,
        archiveError:   '',

        refName:    '',
        refSplit:   '',
        refSaving:  false,
        refError:   '',

        toast: '',

        showToast(msg) {
            this.toast = msg;
            setTimeout(() => { this.toast = ''; }, 4000);
            window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'success', message: msg } }));
        },

        async saveNote() {
            if (!this.noteBody.trim() || this.noteSaving) return;
            this.noteSaving = true; this.noteError = '';
            const { ok, data } = await post(base + '/notes', { body: this.noteBody });
            this.noteSaving = false;
            if (!ok) { this.noteError = data.error || 'Could not save note.'; return; }
            this.showAddNote = false; this.noteBody = '';
            this.showToast('Note added.');
            setTimeout(() => window.location.reload(), 800);
        },

        async saveAmount() {
            if (!this.newAmount || this.amountSaving) return;
            if (!this.amountReason.trim()) {
                this.amountError = 'Please explain why the amount is changing.';
                return;
            }
            this.amountSaving = true; this.amountError = '';
            try {
                const dealValue = parseFloat(String(this.newAmount).replace(/,/g, ''));
                const { ok, data } = await patch(base + '/amount', { deal_value: dealValue, reason: this.amountReason });
                this.amountSaving = false;
                if (!ok) {
                    // Extract from Laravel validation error format or plain error field
                    const errMsg = data.error || data.message
                        || (data.errors ? Object.values(data.errors).flat().join(' ') : null)
                        || 'Could not update amount. Please try again.';
                    this.amountError = errMsg;
                    return;
                }
                this.showUpdateAmount = false;
                this.amountReason = '';
                this.showToast('Deal amount updated. Admins have been notified.');
                // Force a hard reload (bypasses cache) so updated amounts are reflected
                setTimeout(() => { window.location.href = window.location.href; }, 700);
            } catch (e) {
                this.amountSaving = false;
                this.amountError = 'Network error. Please check your connection and try again.';
            }
        },

        async moveStage() {
            if (!this.targetStage || this.stageSaving) return;
            if (this.needsApproval() && !this.stageReason.trim()) {
                this.stageError = 'Please explain what you still need to complete before this stage can move.';
                return;
            }
            this.stageSaving = true; this.stageError = '';
            let ok, data;
            if (this.needsApproval()) {
                const missing = this.missingRequired().map(r => r.label);
                ({ ok, data } = await post(base + '/stage-approval', {
                    target_stage:         this.targetStage,
                    reason:               this.stageReason,
                    missing_requirements: missing,
                }));
            } else {
                ({ ok, data } = await post(base + '/move-stage', {
                    stage:  this.targetStage,
                    reason: this.stageReason,
                }));
            }
            this.stageSaving = false;
            if (!ok) { this.stageError = data.error || 'Could not process stage action.'; return; }
            this.showMoveStage = false;
            this.showToast(this.needsApproval() ? 'Approval request submitted. Admin will review shortly.' : 'Stage moved successfully.');
            setTimeout(() => window.location.reload(), 800);
        },

        async submitArchive() {
            if (!this.archiveReason.trim() || this.archiveSaving) return;
            this.archiveSaving = true; this.archiveError = '';
            const fullReason = this.archiveDetail.trim()
                ? this.archiveReason + ' — ' + this.archiveDetail.trim()
                : this.archiveReason;
            const { ok, data } = await post(base + '/archive-request', { reason: fullReason });
            this.archiveSaving = false;
            if (!ok) { this.archiveError = data.error || 'Could not submit request.'; return; }
            this.showArchive = false; this.archiveReason = ''; this.archiveDetail = '';
            this.showToast('Archive request submitted. Admins have been notified and will review shortly.');
            setTimeout(() => window.location.reload(), 1000);
        },

        async saveReferrer() {
            if (!this.refName || !this.refSplit || this.refSaving) return;
            // Basic email validation client-side
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.refName.trim())) {
                this.refError = 'Please enter a valid email address.';
                return;
            }
            this.refSaving = true; this.refError = '';
            try {
                const { ok, data } = await post(base + '/referrers', {
                    referrer_email: this.refName.trim().toLowerCase(),
                    percentage:     parseFloat(this.refSplit),
                });
                this.refSaving = false;
                if (!ok) {
                    this.refError = data.error || data.message || 'Could not add co-referrer. Please try again.';
                    return;
                }
                this.showAddReferrer = false; this.refName = ''; this.refSplit = '';
                this.showToast(data.message || 'Co-referrer added.');
                setTimeout(() => window.location.reload(), 900);
            } catch (e) {
                this.refSaving = false;
                this.refError = 'Network error. Please try again.';
                return;
            }
        },
    };
}

let _rbRemoveSplitId   = null;
let _rbRemoveSplitUrl  = null;

function rbRemovePartner(splitId, name) {
    _rbRemoveSplitId  = splitId;
    _rbRemoveSplitUrl = '/reseller/{{ $tenant->id }}/partners/splits/' + splitId;

    document.getElementById('rb-remove-partner-name').textContent = name;
    document.getElementById('rb-remove-partner-modal').style.display = 'flex';

    document.getElementById('rb-remove-confirm').onclick = async function() {
        this.textContent = 'Removing…';
        this.disabled    = true;

        try {
            const r = await fetch(_rbRemoveSplitUrl, {
                method:      'DELETE',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN':     document.querySelector('meta[name=csrf-token]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept':           'application/json',
                },
            });
            const d = await r.json().catch(() => ({}));
            document.getElementById('rb-remove-partner-modal').style.display = 'none';

            if (r.ok) {
                window.dispatchEvent(new CustomEvent('show-toast', {
                    detail: { type: 'success', message: d.message || 'Partner removed from deal.' }
                }));
                setTimeout(() => window.location.reload(), 700);
            } else {
                window.dispatchEvent(new CustomEvent('show-toast', {
                    detail: { type: 'error', message: d.error || 'Could not remove partner. Please try again.' }
                }));
                this.textContent = 'Remove Partner';
                this.disabled    = false;
            }
        } catch (err) {
            document.getElementById('rb-remove-partner-modal').style.display = 'none';
            alert('Network error. Please try again.');
        }
    };
}

// Close modal on Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') document.getElementById('rb-remove-partner-modal').style.display = 'none';
});
</script>
@endpush
@endsection
