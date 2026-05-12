@extends('layouts.partner')
@section('title', $dealSummary['name'])
@section('nav') @include('partner._nav') @endsection

@section('content')
@php
    $dealId       = $dealSummary['id'];
    $dealName     = $dealSummary['name'];
    $dealStage    = $dealSummary['stage'];
    $dealStatus   = $dealSummary['status'];
    $daysLeft     = $dealSummary['days_left'];
    $dealValue    = $dealSummary['deal_value'];
    $referrerName = $dealSummary['reseller_name'];
    $province     = $dealSummary['data']['province'] ?? null;
    $municipality = $dealSummary['data']['municipality'] ?? null;

    $stageMap = [
        'introduction'  => ['bg-blue-100 text-blue-700',   'Introduction'],
        'presentation'  => ['bg-indigo-100 text-indigo-700','Presentation'],
        'contract_sent' => ['bg-amber-100 text-amber-700', 'Contract Sent'],
        'signed'        => ['bg-purple-100 text-purple-700','Signed'],
        'paid'          => ['bg-green-100 text-green-700',  'Paid'],
    ];
    [$stageBadge, $stageLabel] = $stageMap[$dealStage] ?? ['bg-gray-100 text-gray-600', ucfirst(str_replace('_', ' ', $dealStage))];

    $statusBadge = match($dealStatus) {
        'active'   => 'bg-green-100 text-green-700',
        'expiring' => 'bg-amber-100 text-amber-700',
        default    => 'bg-red-100 text-red-700',
    };

    $pipelineStages = [
        ['key' => 'introduction',  'label' => 'Introduction'],
        ['key' => 'presentation',  'label' => 'Presentation'],
        ['key' => 'contract_sent', 'label' => 'Contract Sent'],
        ['key' => 'signed',        'label' => 'Signed'],
        ['key' => 'paid',          'label' => 'Paid'],
    ];
    $stageOrder = array_column($pipelineStages, 'key');
    $currentIdx = array_search($dealStage, $stageOrder);

    if ($myPartnerSplit) {
        $splitStatusLabel = match($myPartnerSplit->status ?? 'provisional') {
            'active'         => 'Active',
            'pending_invite' => 'Pending',
            'invite_failed'  => 'Invite Failed',
            default          => 'Provisional',
        };
        [$splitStatusBg, $splitStatusClr] = match($myPartnerSplit->status ?? 'provisional') {
            'active'  => ['#dcfce7', '#15803d'],
            default   => ['#ede9fe', '#7B61FF'],
        };
    }
@endphp

{{-- ───────────────────────────────────────── Page wrapper ── --}}
<div class="max-w-6xl mx-auto px-0 sm:px-0">

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-sm text-gray-400 mb-5" aria-label="Breadcrumb">
        <a href="{{ route('partner.deals') }}" class="hover:text-[#2563EB] transition-colors font-medium">My Deals</a>
        <svg class="w-3.5 h-3.5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="text-[#1E1B4B] font-semibold truncate max-w-[220px] sm:max-w-none">{{ $dealName }}</span>
    </nav>

    {{-- ── HERO CARD — full width ──────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-6 mb-5">
        <div class="flex flex-col sm:flex-row sm:items-start gap-4">
            {{-- Avatar --}}
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-white font-black text-xl shrink-0 shadow-sm"
                 style="background:linear-gradient(135deg,#2563EB,#1D4ED8)">
                {{ strtoupper(substr($dealName, 0, 2)) }}
            </div>
            {{-- Deal identity --}}
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h1 class="text-xl sm:text-2xl font-extrabold text-[#1E1B4B] leading-tight">{{ $dealName }}</h1>
                        @if($province || $municipality)
                        <p class="text-sm text-gray-400 mt-0.5 flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 shrink-0 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            {{ implode(' › ', array_filter([$province, $municipality])) }}
                        </p>
                        @endif
                    </div>
                    {{-- Partner view badge --}}
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-blue-50 text-blue-600 border border-blue-100 shrink-0">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Partner View
                    </span>
                </div>
                {{-- Status badges --}}
                <div class="flex flex-wrap gap-2 mt-3">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ $stageBadge }}">{{ $stageLabel }}</span>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ $statusBadge }}">{{ ucfirst($dealStatus) }}</span>
                    @if($daysLeft !== null && $dealStage !== 'paid')
                    @php
                        $dlBadge = $daysLeft <= 3 ? 'bg-red-100 text-red-700' : ($daysLeft <= 7 ? 'bg-amber-100 text-amber-700' : 'bg-blue-50 text-blue-600');
                        $dlLabel = $daysLeft <= 0 ? 'Overdue' : $daysLeft . 'd to move stage';
                    @endphp
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold {{ $dlBadge }}">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ $dlLabel }}
                    </span>
                    @endif
                    {{-- Read-only indicator --}}
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Read-only
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- ── INFO BANNER — full width ────────────────────────────────────── --}}
    <div class="flex items-start gap-3 px-4 py-3 mb-5 rounded-2xl border"
         style="background:linear-gradient(135deg,#eff6ff,#eef2ff);border-color:#c7d2fe">
        <svg class="w-4 h-4 text-indigo-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-xs text-indigo-700 leading-relaxed">
            You are a <strong class="font-bold">Partner</strong> on this deal. Your individual commission split is shown below.
            Other parties' financial details are intentionally not visible to partners.
        </p>
    </div>

    {{-- ── TWO-COLUMN GRID ─────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- ══ LEFT COLUMN (2/3) ══════════════════════════════════════════ --}}
        <div class="lg:col-span-2 space-y-5">

            {{-- ── Your Commission Card ─────────────────────────────────── --}}
            @if($myPartnerSplit)
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 sm:px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-[#1E1B4B]">Your Commission</h2>
                        <p class="text-xs text-gray-400 mt-0.5">Your individual share on this deal</p>
                    </div>
                    <span class="text-[11px] font-bold px-3 py-1 rounded-full"
                          style="background:{{ $splitStatusBg }};color:{{ $splitStatusClr }}">
                        {{ $splitStatusLabel }}
                    </span>
                </div>

                <div class="px-5 sm:px-6 pt-5 pb-5">
                    {{-- Prominent amount display --}}
                    <div class="rounded-2xl text-center py-7 px-4 mb-5 relative overflow-hidden"
                         style="background:linear-gradient(135deg,#1e1b4b,#312e81);box-shadow:0 8px 32px rgba(99,102,241,0.2)">
                        <div class="absolute inset-0 opacity-10" style="background:radial-gradient(circle at 70% 30%,#818cf8,transparent 60%)"></div>
                        <p class="text-[10px] font-bold tracking-[0.15em] text-indigo-300 uppercase mb-2 relative">Your Estimated Share</p>
                        <p class="text-4xl sm:text-5xl font-black text-white tabular-nums mb-2 relative">
                            ₱{{ number_format((int) round($myPartnerSplit->peso_amount ?? 0)) }}
                        </p>
                        <p class="text-sm text-indigo-300 font-medium relative">
                            @if(($myPartnerSplit->split_share_type ?? '') === 'percentage')
                                {{ number_format((float)$myPartnerSplit->split_share_value, 0) }}% of the commission pool allocated to you
                            @else
                                Fixed amount allocation
                            @endif
                        </p>
                    </div>

                    {{-- Details grid --}}
                    <dl class="space-y-0 divide-y divide-gray-50">
                        <div class="flex justify-between items-center py-3">
                            <dt class="text-sm text-gray-500">Deal contract value</dt>
                            <dd class="text-sm font-bold text-[#1E1B4B]">₱{{ number_format($dealValue, 0) }}</dd>
                        </div>
                        <div class="flex justify-between items-center py-3">
                            <dt class="text-sm text-gray-500">Your split type</dt>
                            <dd class="text-sm font-semibold text-[#1E1B4B]">
                                {{ ($myPartnerSplit->split_share_type ?? '') === 'percentage' ? 'Percentage' : 'Fixed Amount' }}
                            </dd>
                        </div>
                        @if(($myPartnerSplit->split_share_type ?? '') === 'percentage')
                        <div class="flex justify-between items-center py-3">
                            <dt class="text-sm text-gray-500">Your split percentage</dt>
                            <dd class="text-sm font-black text-indigo-600">{{ number_format((float)$myPartnerSplit->split_share_value, 0) }}%</dd>
                        </div>
                        @endif
                        <div class="flex justify-between items-center py-3">
                            <dt class="text-sm text-gray-500">Commission status</dt>
                            <dd class="text-sm font-semibold text-[#1E1B4B] capitalize">{{ $myPartnerSplit->status ?? 'provisional' }}</dd>
                        </div>
                    </dl>

                    @if(($myPartnerSplit->status ?? '') === 'pending_invite')
                    <div class="flex items-start gap-2 mt-4 p-3 bg-amber-50 border border-amber-200 rounded-xl">
                        <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <p class="text-xs text-amber-700">Your invitation is pending. Your split will be confirmed once you complete account setup.</p>
                    </div>
                    @endif

                    <p class="flex items-start gap-1.5 text-[11px] text-gray-400 mt-4 leading-relaxed">
                        <svg class="w-3 h-3 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Estimated amounts are subject to applicable taxes, deductions, and final confirmation by the deal administrator.
                    </p>
                </div>
            </div>

            @else
            {{-- No split yet --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
                <div class="w-14 h-14 rounded-2xl bg-gray-50 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <p class="text-sm font-bold text-[#1E1B4B] mb-1">No commission split assigned yet</p>
                <p class="text-xs text-gray-400 max-w-xs mx-auto">The deal administrator has not assigned your commission share. Check back later or message your Referrer.</p>
            </div>
            @endif

            {{-- ── Deal Progress ─────────────────────────────────────────── --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-6">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h2 class="text-sm font-bold text-[#1E1B4B]">Deal Progress</h2>
                        <p class="text-xs text-gray-400 mt-0.5">Stage overview — view only</p>
                    </div>
                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-gray-400 bg-gray-100 px-2.5 py-1 rounded-full uppercase tracking-wide">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Read-only
                    </span>
                </div>

                {{-- Desktop: horizontal steps --}}
                <div class="hidden sm:flex items-stretch gap-0">
                    @foreach($pipelineStages as $idx => $ps)
                    @php
                        $isDone = $currentIdx !== false && $idx < $currentIdx;
                        $isCur  = $dealStage === $ps['key'];
                    @endphp
                    <div class="flex-1 relative flex flex-col items-center">
                        {{-- Connector line --}}
                        @if(!$loop->first)
                        <div class="absolute top-4 left-0 w-1/2 h-0.5 -translate-y-px
                            {{ $isDone || $isCur ? 'bg-blue-300' : 'bg-gray-200' }}"></div>
                        @endif
                        @if(!$loop->last)
                        <div class="absolute top-4 right-0 w-1/2 h-0.5 -translate-y-px
                            {{ $isDone ? 'bg-blue-300' : 'bg-gray-200' }}"></div>
                        @endif
                        {{-- Step circle --}}
                        <div class="relative z-10 w-8 h-8 rounded-full flex items-center justify-center mb-2 transition-all
                            @if($isCur) text-white shadow-lg @elseif($isDone) bg-green-100 text-green-600 @else bg-gray-100 text-gray-400 @endif"
                             style="{{ $isCur ? 'background:linear-gradient(135deg,#2563EB,#1D4ED8);box-shadow:0 4px 12px rgba(37,99,235,0.3)' : '' }}">
                            @if($isDone)
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                            @elseif($isCur)
                            <div class="w-2 h-2 rounded-full bg-white"></div>
                            @else
                            <div class="w-2 h-2 rounded-full bg-gray-300"></div>
                            @endif
                        </div>
                        {{-- Stage label --}}
                        <p class="text-center text-[11px] font-semibold leading-tight
                            @if($isCur) text-blue-700 @elseif($isDone) text-green-600 @else text-gray-400 @endif">
                            {{ $ps['label'] }}
                        </p>
                    </div>
                    @endforeach
                </div>

                {{-- Mobile: vertical timeline --}}
                <div class="flex sm:hidden flex-col gap-0">
                    @foreach($pipelineStages as $idx => $ps)
                    @php
                        $isDone = $currentIdx !== false && $idx < $currentIdx;
                        $isCur  = $dealStage === $ps['key'];
                    @endphp
                    <div class="flex items-start gap-3 py-2">
                        {{-- Icon + line --}}
                        <div class="flex flex-col items-center shrink-0">
                            <div class="w-7 h-7 rounded-full flex items-center justify-center
                                @if($isCur) text-white @elseif($isDone) bg-green-100 text-green-600 @else bg-gray-100 text-gray-400 @endif"
                                 style="{{ $isCur ? 'background:linear-gradient(135deg,#2563EB,#1D4ED8)' : '' }}">
                                @if($isDone)
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                @else
                                <div class="w-2 h-2 rounded-full {{ $isCur ? 'bg-white' : 'bg-current opacity-40' }}"></div>
                                @endif
                            </div>
                            @if(!$loop->last)
                            <div class="w-0.5 h-5 {{ $isDone ? 'bg-blue-200' : 'bg-gray-200' }} mt-0.5"></div>
                            @endif
                        </div>
                        <div class="pt-0.5">
                            <p class="text-sm font-semibold {{ $isCur ? 'text-blue-700' : ($isDone ? 'text-green-600' : 'text-gray-400') }}">
                                {{ $ps['label'] }}
                            </p>
                            @if($isCur)<p class="text-[10px] text-blue-500">Current stage</p>@endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- ── Notes ─────────────────────────────────────────────────── --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden"
                 x-data="partnerNotes('{{ $dealId }}', '{{ route('partner.deals.notes', $dealId) }}', '{{ csrf_token() }}')"
                 x-init="load()">

                <div class="px-5 sm:px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-[#1E1B4B]">Notes</h2>
                        <p class="text-xs text-gray-400 mt-0.5">Shared notes on this deal</p>
                    </div>
                    <button @click="showForm = !showForm"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-white transition-all active:scale-95 focus:outline-none focus:ring-2 focus:ring-blue-400/50"
                            style="background:linear-gradient(135deg,#2563EB,#1D4ED8)"
                            :aria-expanded="showForm ? 'true' : 'false'"
                            aria-controls="partner-note-form">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add Note
                    </button>
                </div>

                {{-- Add note form --}}
                <div x-show="showForm" id="partner-note-form"
                     class="px-5 sm:px-6 py-4 border-b border-gray-100 bg-blue-50/30">
                    <textarea x-model="noteBody" rows="3"
                              placeholder="Write your note… (optional if attaching files)"
                              aria-label="Note text"
                              class="w-full border border-gray-200 bg-white rounded-xl px-3.5 py-2.5 text-sm text-gray-800 outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all resize-none mb-3"></textarea>
                    <label class="flex items-center gap-2.5 px-3 py-2.5 border-2 border-dashed border-gray-200 rounded-xl cursor-pointer hover:border-blue-400 hover:bg-blue-50/30 transition-colors mb-3">
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                        </svg>
                        <span class="text-xs text-gray-500">Attach files</span>
                        <span class="text-[10px] text-gray-400">(PDF, Word, Excel, images · max 10MB each)</span>
                        <input type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.webp,.gif"
                               class="sr-only" aria-label="Attach files"
                               @change="noteFiles = Array.from($event.target.files).slice(0, 5)">
                    </label>
                    <template x-if="noteFiles.length > 0">
                        <ul class="flex flex-wrap gap-1.5 mb-3">
                            <template x-for="(f, i) in noteFiles" :key="i">
                                <li class="inline-flex items-center gap-1.5 text-xs text-gray-600 bg-gray-100 px-2.5 py-1 rounded-full">
                                    <span class="truncate max-w-[100px]" x-text="f.name"></span>
                                    <button type="button" @click="noteFiles = noteFiles.filter((_,j)=>j!==i)" class="text-red-400 hover:text-red-600" aria-label="Remove file">✕</button>
                                </li>
                            </template>
                        </ul>
                    </template>
                    <div x-show="noteError" role="alert" class="text-xs text-red-600 bg-red-50 border border-red-200 px-3 py-2 rounded-lg mb-3" x-text="noteError"></div>
                    <div class="flex gap-2 justify-end">
                        <button @click="showForm = false; noteBody = ''; noteFiles = []; noteError = ''"
                                class="px-3.5 py-2 rounded-xl text-xs font-semibold border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors min-h-[44px]">
                            Cancel
                        </button>
                        <button @click="save()"
                                :disabled="(!noteBody.trim() && noteFiles.length === 0) || saving"
                                class="px-4 py-2 rounded-xl text-xs font-semibold text-white disabled:opacity-50 transition-all active:scale-95 min-h-[44px]"
                                style="background:linear-gradient(135deg,#2563EB,#1D4ED8)"
                                x-text="saving ? 'Saving…' : 'Save Note'">Save Note</button>
                    </div>
                </div>

                {{-- Notes list --}}
                <div>
                    <template x-if="loading">
                        <div class="flex justify-center py-8">
                            <svg class="w-5 h-5 animate-spin text-blue-400" fill="none" viewBox="0 0 24 24" aria-label="Loading notes">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4z"/>
                            </svg>
                        </div>
                    </template>
                    <template x-if="!loading && notes.length === 0">
                        <div class="px-5 py-10 text-center">
                            <div class="w-10 h-10 rounded-xl bg-gray-50 flex items-center justify-center mx-auto mb-3">
                                <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-gray-400">No shared notes yet</p>
                            <p class="text-xs text-gray-300 mt-0.5">Add the first note using the button above.</p>
                        </div>
                    </template>
                    <div class="divide-y divide-gray-50">
                        <template x-for="n in notes" :key="n.id">
                            <div class="px-5 sm:px-6 py-4 flex items-start gap-3">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-[11px] shrink-0 mt-0.5"
                                     :class="n.author_role === 'partner' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500'"
                                     x-text="(n.author_name || '?').charAt(0).toUpperCase()"
                                     :aria-label="'Note by ' + (n.author_name || 'Team')"></div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2 mb-1">
                                        <span class="text-xs font-bold text-[#1E1B4B]" x-text="n.author_name || 'Team'"></span>
                                        <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full capitalize"
                                              :class="n.author_role === 'partner' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500'"
                                              x-text="n.author_role === 'partner' ? 'You' : n.author_role_label"></span>
                                        <span class="text-[10px] text-gray-400" x-text="n.created_ago"></span>
                                    </div>
                                    <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap" x-text="n.body"></p>
                                    <template x-if="n.attachments && n.attachments.length > 0">
                                        <div class="flex flex-wrap gap-1.5 mt-2">
                                            <template x-for="att in n.attachments" :key="att.id">
                                                <a :href="att.download_url" target="_blank" rel="noopener noreferrer"
                                                   class="inline-flex items-center gap-1 text-[10px] text-blue-600 bg-blue-50 px-2 py-1 rounded-full hover:bg-blue-100 transition-colors"
                                                   :aria-label="'Download ' + att.original_filename">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                    <span x-text="att.original_filename"></span>
                                                </a>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

        </div>{{-- / left column --}}

        {{-- ══ RIGHT SIDEBAR (1/3) ═════════════════════════════════════════ --}}
        <div class="space-y-5">

            {{-- ── Your Referrer card ─────────────────────────────────────── --}}
            @if($referrerName)
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h2 class="text-sm font-bold text-[#1E1B4B] mb-4">Your Referrer</h2>
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-11 h-11 rounded-full flex items-center justify-center font-black text-sm shrink-0 text-white"
                         style="background:linear-gradient(135deg,#2563EB,#1D4ED8)"
                         aria-hidden="true">
                        {{ strtoupper(substr($referrerName, 0, 2)) }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-[#1E1B4B] truncate">{{ $referrerName }}</p>
                        <p class="text-xs text-gray-400">Referrer</p>
                    </div>
                </div>
                <a href="{{ route('partner.messages') }}?deal_id={{ $dealId }}"
                   class="flex items-center justify-center gap-2 w-full px-4 py-2.5 rounded-xl text-sm font-semibold text-white transition-all active:scale-95 focus:outline-none focus:ring-2 focus:ring-blue-400/50 min-h-[44px]"
                   style="background:linear-gradient(135deg,#2563EB,#1D4ED8)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                    </svg>
                    Message Referrer
                </a>
            </div>
            @endif

            {{-- ── Quick Actions card ─────────────────────────────────────── --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h2 class="text-sm font-bold text-[#1E1B4B] mb-4">Actions</h2>
                <div class="space-y-2.5">
                    @if(!$referrerName)
                    <a href="{{ route('partner.messages') }}?deal_id={{ $dealId }}"
                       class="flex items-center gap-2.5 w-full px-4 py-2.5 rounded-xl text-sm font-semibold text-white transition-all active:scale-95 min-h-[44px]"
                       style="background:linear-gradient(135deg,#2563EB,#1D4ED8)">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                        </svg>
                        Message Referrer
                    </a>
                    @endif
                    <a href="{{ route('partner.deals') }}"
                       class="flex items-center gap-2.5 w-full px-4 py-2.5 rounded-xl text-sm font-semibold text-gray-600 border border-gray-200 hover:bg-gray-50 transition-all active:scale-95 focus:outline-none focus:ring-2 focus:ring-gray-300/50 min-h-[44px]">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Back to My Deals
                    </a>
                </div>
            </div>

            {{-- ── Deal snapshot mini-card ───────────────────────────────── --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h2 class="text-sm font-bold text-[#1E1B4B] mb-3">Deal Snapshot</h2>
                <dl class="space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <dt class="text-gray-500">Contract Value</dt>
                        <dd class="font-bold text-[#1E1B4B]">₱{{ number_format($dealValue, 0) }}</dd>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <dt class="text-gray-500">Stage</dt>
                        <dd>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $stageBadge }}">{{ $stageLabel }}</span>
                        </dd>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <dt class="text-gray-500">Status</dt>
                        <dd>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $statusBadge }}">{{ ucfirst($dealStatus) }}</span>
                        </dd>
                    </div>
                    @if($myPartnerSplit)
                    <div class="flex items-center justify-between text-xs pt-1 border-t border-gray-50">
                        <dt class="text-gray-500">Your Estimated Share</dt>
                        <dd class="font-black text-indigo-700">₱{{ number_format((int) round($myPartnerSplit->peso_amount ?? 0)) }}</dd>
                    </div>
                    @endif
                </dl>
            </div>

        </div>{{-- / right sidebar --}}

    </div>{{-- / grid --}}

    {{-- Mobile-only action strip (below grid) --}}
    <div class="mt-5 flex flex-col sm:flex-row gap-3 lg:hidden">
        <a href="{{ route('partner.messages') }}?deal_id={{ $dealId }}"
           class="flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-sm font-semibold text-white transition-all active:scale-95 min-h-[44px]"
           style="background:linear-gradient(135deg,#2563EB,#1D4ED8)">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
            </svg>
            Message Referrer
        </a>
        <a href="{{ route('partner.deals') }}"
           class="flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-sm font-semibold text-gray-600 border border-gray-200 hover:bg-gray-50 transition-all min-h-[44px]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to My Deals
        </a>
    </div>

</div>

@push('scripts')
<script>
function partnerNotes(dealId, postUrl, csrf) {
    return {
        notes:    [],
        loading:  true,
        showForm: false,
        noteBody: '',
        noteFiles:[],
        noteError:'',
        saving:   false,

        async load() {
            try {
                const r = await fetch('/partner/deals/' + dealId + '/notes', {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (r.ok) this.notes = (await r.json()).notes ?? [];
            } catch(e) {}
            this.loading = false;
        },

        async save() {
            if ((!this.noteBody.trim() && this.noteFiles.length === 0) || this.saving) return;
            this.saving = true; this.noteError = '';
            try {
                const fd = new FormData();
                fd.append('_token', csrf);
                if (this.noteBody.trim()) fd.append('body', this.noteBody);
                this.noteFiles.forEach(f => fd.append('files[]', f));

                const r = await fetch(postUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: fd,
                });
                const d = await r.json();
                if (!r.ok) { this.noteError = d.error || 'Could not save note.'; return; }
                this.notes.unshift({
                    id:               d.note.id,
                    body:             d.note.body,
                    author_name:      d.note.author,
                    author_role:      'partner',
                    author_role_label:'Partner',
                    created_ago:      'just now',
                    attachments:      [],
                });
                this.showForm = false;
                this.noteBody  = '';
                this.noteFiles = [];
            } catch(e) {
                this.noteError = 'Network error. Please try again.';
            } finally {
                this.saving = false;
            }
        },
    };
}
</script>
@endpush
@endsection
