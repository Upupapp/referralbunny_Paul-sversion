@extends('layouts.reseller')
@section('title', 'Partner — ' . $first->partner_name)
@section('nav') @include('reseller._nav') @endsection

@section('content')
<div
    x-data="partnerDetail()"
    x-init="init()"
    class="space-y-5 max-w-4xl mx-auto"
>

    {{-- ── Back + Header ──────────────────────────────────────── --}}
    <div>
        <a href="{{ route('reseller.partners', $tenantId) }}"
           class="inline-flex items-center gap-1.5 text-xs text-gray-400 hover:text-teal-600 transition-colors mb-3">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            My Partners
        </a>

        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-lg font-bold text-teal-700 shrink-0" style="background:#CCFBF1">
                    {{ strtoupper(substr($first->partner_name, 0, 1)) }}
                </div>
                <div>
                    <h1 class="text-xl font-bold text-[#1E1B4B]">{{ $first->partner_name }}</h1>
                    <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                        <p class="text-sm text-gray-400">{{ $email }}</p>
                        <span @class([
                            'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold',
                            'bg-green-100 text-green-700'  => $status === 'active',
                            'bg-amber-100 text-amber-700'  => $status === 'pending_invite',
                            'bg-gray-100 text-gray-500'    => $status === 'provisional',
                        ])>
                            @if($status === 'active') Active
                            @elseif($status === 'pending_invite') Pending Invite
                            @else Not Invited
                            @endif
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex gap-2 sm:shrink-0">
                <button type="button" @click="openAddModal()"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-sm font-semibold text-white transition-all hover:shadow-md"
                        style="background:linear-gradient(135deg,#0D9488,#14B8A6)">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add to Deal
                </button>
            </div>
        </div>
    </div>

    {{-- ── Tab navigation ──────────────────────────────────────── --}}
    <div class="flex gap-1 bg-gray-100 rounded-xl p-1 w-full sm:w-auto sm:inline-flex">
        <button type="button" @click="tab='overview'"
                :class="tab==='overview' ? 'bg-white shadow-sm text-[#1E1B4B]' : 'text-gray-400 hover:text-gray-600'"
                class="flex-1 sm:flex-none px-4 py-2 rounded-lg text-sm font-semibold transition-all">Overview</button>
        <button type="button" @click="tab='deals'"
                :class="tab==='deals' ? 'bg-white shadow-sm text-[#1E1B4B]' : 'text-gray-400 hover:text-gray-600'"
                class="flex-1 sm:flex-none px-4 py-2 rounded-lg text-sm font-semibold transition-all">
            Shared Deals
            <span class="ml-1 text-xs text-gray-400">({{ $sharedDeals->count() }})</span>
        </button>
        <button type="button" @click="tab='commission'"
                :class="tab==='commission' ? 'bg-white shadow-sm text-[#1E1B4B]' : 'text-gray-400 hover:text-gray-600'"
                class="flex-1 sm:flex-none px-4 py-2 rounded-lg text-sm font-semibold transition-all">Commission</button>
        <button type="button" @click="tab='activity'"
                :class="tab==='activity' ? 'bg-white shadow-sm text-[#1E1B4B]' : 'text-gray-400 hover:text-gray-600'"
                class="flex-1 sm:flex-none px-4 py-2 rounded-lg text-sm font-semibold transition-all">Activity</button>
    </div>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- TAB: OVERVIEW ─────────────────────────────────────────── --}}
    <div x-show="tab==='overview'" class="space-y-4">

        {{-- Summary Card --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <h2 class="text-sm font-bold text-[#1E1B4B] mb-4">Partner Summary</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <p class="text-xs text-gray-400 mb-0.5">Full Name</p>
                    <p class="text-sm font-semibold text-[#1E1B4B]">{{ $first->partner_name }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 mb-0.5">Email</p>
                    <p class="text-sm text-[#1E1B4B]">{{ $email }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 mb-0.5">Status</p>
                    <span @class([
                        'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold',
                        'bg-green-100 text-green-700'  => $status === 'active',
                        'bg-amber-100 text-amber-700'  => $status === 'pending_invite',
                        'bg-gray-100 text-gray-500'    => $status === 'provisional',
                    ])>
                        @if($status === 'active') Active
                        @elseif($status === 'pending_invite') Pending Invite
                        @else Not Invited
                        @endif
                    </span>
                </div>
                <div>
                    <p class="text-xs text-gray-400 mb-0.5">Connected Deals</p>
                    <p class="text-sm font-semibold text-[#1E1B4B]">{{ $sharedDeals->count() }} deal{{ $sharedDeals->count() !== 1 ? 's' : '' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 mb-0.5">First Added</p>
                    <p class="text-sm text-[#1E1B4B]">
                        {{ $splits->min('created_at')?->format('M j, Y') ?? '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 mb-0.5">Total Commission</p>
                    <p class="text-sm font-bold text-[#0D9488]">₱{{ number_format($totalCommission, 0) }}</p>
                </div>
            </div>
        </div>

        {{-- Commission snapshot --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center gap-2 mb-4">
                <h2 class="text-sm font-bold text-[#1E1B4B]">Commission Snapshot</h2>
                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = !open" @click.outside="open = false" @keydown.escape.window="open = false"
                            class="w-5 h-5 rounded-full flex items-center justify-center text-gray-300 hover:text-gray-500 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </button>
                    <div x-show="open" x-cloak x-transition class="absolute left-0 top-6 z-30 w-72 bg-white border border-gray-100 rounded-xl shadow-lg p-3 text-xs text-gray-500 leading-relaxed">
                        <strong class="text-gray-700 block mb-1">Partner Commission</strong>
                        This shows the estimated commission allocated to this Partner from deals you share with them. Amounts may change if the deal amount, stage, or split allocation changes. Amounts may be subject to taxes, withholding, deductions, and final approval.
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="bg-gray-50 rounded-xl p-3">
                    <p class="text-xs text-gray-400 mb-1">Total Est.</p>
                    <p class="text-base font-bold text-[#0D9488]">₱{{ number_format($totalCommission, 0) }}</p>
                </div>
                @foreach([
                    ['Pending', number_format($pendingCommission, 0), 'text-amber-600', 'Estimates from active deals. May change as deal amounts, stages, or splits are updated.'],
                    ['Locked',  number_format($lockedCommission,  0), 'text-blue-600',  'Confirmed when a deal reached Signed. Awaiting final payout. Will not change unless the deal is revised.'],
                    ['Paid',    number_format($paidCommission,    0), 'text-green-600', 'Approved and released. These amounts are final.'],
                ] as [$cLabel, $cAmount, $cColor, $cInfo])
                <div class="bg-gray-50 rounded-xl p-3">
                    <div class="flex items-center gap-1 mb-1">
                        <p class="text-xs text-gray-400">{{ $cLabel }}</p>
                        <div x-data="{ open: false }" class="relative">
                            <button type="button" @click="open = !open" @click.outside="open = false" @keydown.escape.window="open = false"
                                    class="w-3.5 h-3.5 flex items-center justify-center text-gray-300 hover:text-gray-500 transition-colors" aria-label="About {{ $cLabel }} commission">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </button>
                            <div x-show="open" x-cloak x-transition class="absolute left-0 top-5 z-50 w-56 bg-white border border-gray-100 rounded-xl shadow-xl p-3 text-xs text-gray-500 leading-relaxed">
                                <strong class="block mb-1 text-gray-700">{{ $cLabel }}</strong>{{ $cInfo }}
                            </div>
                        </div>
                    </div>
                    <p class="text-base font-bold {{ $cColor }}">₱{{ $cAmount }}</p>
                </div>
                @endforeach
            </div>
            <p class="text-[10px] text-gray-400 mt-3">* All amounts are estimates and subject to appropriate taxes and deductions.</p>
        </div>

    </div>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- TAB: SHARED DEALS ─────────────────────────────────────── --}}
    <div x-show="tab==='deals'" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        @if($sharedDeals->isEmpty())
        <div class="flex flex-col items-center justify-center py-12 text-center px-6">
            <p class="text-sm text-gray-400">No shared deals found.</p>
        </div>
        @else

        {{-- Desktop --}}
        <div class="hidden lg:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100">
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Deal</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Stage</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Deal Value</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Split</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Partner Comm.</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Status</th>
                        <th class="text-right px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($sharedDeals as $item)
                    @php $split = $item['split']; $lead = $item['lead']; @endphp
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-5 py-4">
                            <p class="font-semibold text-[#1E1B4B] truncate max-w-[180px]">{{ $lead?->name ?? '—' }}</p>
                        </td>
                        <td class="px-4 py-4">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-purple-100 text-purple-700">
                                {{ ucfirst(str_replace('_', ' ', $lead?->stage ?? '')) }}
                            </span>
                        </td>
                        <td class="px-4 py-4 text-right text-gray-600">
                            ₱{{ number_format((float)($lead?->deal_value ?? 0), 0) }}
                        </td>
                        <td class="px-4 py-4">
                            <span class="font-semibold text-[#1E1B4B]">{{ $split->display_share }}</span>
                        </td>
                        <td class="px-4 py-4 text-right font-semibold text-[#0D9488]">
                            ₱{{ number_format($item['partner_commission'], 0) }}
                        </td>
                        <td class="px-4 py-4">
                            @php $cs = $lead?->commission_status ?? 'pending'; @endphp
                            <span @class([
                                'inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold',
                                'bg-green-100 text-green-700' => $cs === 'paid',
                                'bg-blue-100 text-blue-700'   => $cs === 'locked',
                                'bg-amber-100 text-amber-700' => $cs === 'pending',
                            ])>{{ ucfirst($cs) }}</span>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('reseller.deals.show', [$tenantId, $lead?->id]) }}"
                                   class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-teal-700 bg-teal-50 hover:bg-teal-100 transition-colors">
                                    View Deal
                                </a>
                                @if(!in_array($lead?->commission_status ?? '', ['locked','paid']))
                                <button type="button" @click="confirmRemove('{{ $split->id }}', {{ json_encode($lead?->name ?? '', JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) }})"
                                        class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-red-600 bg-red-50 hover:bg-red-100 transition-colors">
                                    Remove
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Mobile cards --}}
        <div class="lg:hidden divide-y divide-gray-50">
            @foreach($sharedDeals as $item)
            @php $split = $item['split']; $lead = $item['lead']; @endphp
            <div class="px-4 py-4 space-y-2">
                <div class="flex items-center justify-between">
                    <p class="font-semibold text-[#1E1B4B] text-sm">{{ $lead?->name ?? '—' }}</p>
                    @php $cs = $lead?->commission_status ?? 'pending'; @endphp
                    <span @class([
                        'inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold',
                        'bg-green-100 text-green-700' => $cs === 'paid',
                        'bg-blue-100 text-blue-700'   => $cs === 'locked',
                        'bg-amber-100 text-amber-700' => $cs === 'pending',
                    ])>{{ ucfirst($cs) }}</span>
                </div>
                <div class="flex items-center gap-3 text-xs text-gray-500 flex-wrap">
                    <span>{{ ucfirst(str_replace('_', ' ', $lead?->stage ?? '')) }}</span>
                    <span>·</span>
                    <span>Split: <strong class="text-[#1E1B4B]">{{ $split->display_share }}</strong></span>
                    <span>·</span>
                    <span>Partner Commission: <strong class="text-[#0D9488]">₱{{ number_format($item['partner_commission'], 0) }}</strong></span>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('reseller.deals.show', [$tenantId, $lead?->id]) }}"
                       class="px-3 py-1.5 rounded-lg text-xs font-semibold text-teal-700 bg-teal-50 hover:bg-teal-100 transition-colors">
                        View Deal
                    </a>
                    @if(!in_array($lead?->commission_status ?? '', ['locked','paid']))
                    <button type="button" @click="confirmRemove('{{ $split->id }}', {{ json_encode($lead?->name ?? '', JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) }})"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold text-red-600 bg-red-50 hover:bg-red-100 transition-colors">
                        Remove
                    </button>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- TAB: COMMISSION ────────────────────────────────────────── --}}
    <div x-show="tab==='commission'" class="space-y-4">

        {{-- Commission summary card --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center gap-2 mb-5">
                <h2 class="text-sm font-bold text-[#1E1B4B]">Partner Commission Summary</h2>
                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = !open" @click.outside="open = false" @keydown.escape.window="open = false"
                            class="w-5 h-5 rounded-full flex items-center justify-center text-gray-300 hover:text-gray-500 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </button>
                    <div x-show="open" x-cloak x-transition class="absolute left-0 top-6 z-30 w-72 bg-white border border-gray-100 rounded-xl shadow-lg p-3 text-xs text-gray-500 leading-relaxed">
                        <strong class="text-gray-700 block mb-1">Partner Commission</strong>
                        This shows the estimated commission allocated to this Partner from deals you share with them. Amounts may change if the deal amount, stage, or split allocation changes. Amounts may be subject to taxes, withholding, deductions, and final approval.
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                <div class="text-center p-4 bg-gray-50 rounded-xl">
                    <p class="text-xs text-gray-400 mb-1">Total Estimated</p>
                    <p class="text-xl font-bold text-[#0D9488]">₱{{ number_format($totalCommission, 0) }}</p>
                </div>
                @foreach([
                    ['Pending', number_format($pendingCommission, 0), 'text-amber-600', 'bg-amber-50', 'Estimates from active deals. May change as deal amounts, stages, or splits are updated.'],
                    ['Locked',  number_format($lockedCommission,  0), 'text-blue-600',  'bg-blue-50',  'Confirmed when a deal reached Signed. Awaiting final payout. Will not change unless the deal is revised.'],
                    ['Paid',    number_format($paidCommission,    0), 'text-green-600', 'bg-green-50', 'Approved and released. These amounts are final.'],
                ] as [$cLabel, $cAmt, $cColor, $cBg, $cInfo])
                <div class="text-center p-4 {{ $cBg }} rounded-xl">
                    <div class="flex items-center justify-center gap-1 mb-1">
                        <p class="text-xs text-gray-400">{{ $cLabel }}</p>
                        <div x-data="{ open: false }" class="relative">
                            <button type="button" @click="open = !open" @click.outside="open = false" @keydown.escape.window="open = false"
                                    class="w-3.5 h-3.5 flex items-center justify-center text-gray-300 hover:text-gray-500 transition-colors" aria-label="About {{ $cLabel }} commission">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </button>
                            <div x-show="open" x-cloak x-transition class="absolute left-1/2 -translate-x-1/2 top-5 z-50 w-56 bg-white border border-gray-100 rounded-xl shadow-xl p-3 text-left text-xs text-gray-500 leading-relaxed">
                                <strong class="block mb-1 text-gray-700">{{ $cLabel }}</strong>{{ $cInfo }}
                            </div>
                        </div>
                    </div>
                    <p class="text-xl font-bold {{ $cColor }}">₱{{ $cAmt }}</p>
                </div>
                @endforeach
            </div>

            {{-- Per-deal breakdown --}}
            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Per Deal Breakdown</h3>
            <div class="space-y-2">
                @foreach($sharedDeals as $item)
                @php $split = $item['split']; $lead = $item['lead']; @endphp
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl gap-2 flex-wrap">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-[#1E1B4B] truncate">{{ $lead?->name ?? '—' }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            Split: {{ $split->display_share }}
                            · Pool: ₱{{ number_format($item['commission_pool'], 0) }}
                        </p>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-sm font-bold text-[#0D9488]">₱{{ number_format($item['partner_commission'], 0) }}</p>
                        <p class="text-[10px] text-gray-400">Estimated</p>
                    </div>
                </div>
                @endforeach
            </div>
            <p class="text-[10px] text-gray-400 mt-4">* All amounts are estimates and subject to appropriate taxes and deductions.</p>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- TAB: ACTIVITY ─────────────────────────────────────────── --}}
    <div x-show="tab==='activity'" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        @if(empty($activity))
        <div class="flex flex-col items-center justify-center py-12 text-center px-6">
            <p class="text-sm text-gray-400">No Partner activity recorded yet.</p>
        </div>
        @else
        <div class="divide-y divide-gray-50">
            @foreach($activity as $item)
            <div class="px-5 py-4 flex items-start gap-3">
                <div class="w-7 h-7 rounded-full bg-teal-50 flex items-center justify-center shrink-0 mt-0.5">
                    <svg class="w-3.5 h-3.5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-[#1E1B4B]">{{ $item->action ?? $item->description ?? '' }}</p>
                    @if(!empty($item->actor_name))
                    <p class="text-xs text-gray-400 mt-0.5">by {{ $item->actor_name }}</p>
                    @endif
                </div>
                <span class="text-[10px] text-gray-400 shrink-0">
                    @php
                        try { echo \Carbon\Carbon::parse($item->created_at)->diffForHumans(); } catch(\Throwable) { echo $item->created_at ?? ''; }
                    @endphp
                </span>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- ── Add to Another Deal Modal ───────────────────────────── --}}
    <div x-show="modal" x-cloak
         class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4"
         @keydown.escape.window="modal = false">
        <div @click="modal = false" class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg" @click.stop
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">

            <div class="flex items-center justify-between px-6 pt-5 pb-4 border-b border-gray-100">
                <div>
                    <h3 class="text-[#1E1B4B] font-bold text-base">Add Partner to Another Deal</h3>
                    <p class="text-gray-400 text-xs mt-0.5">Associate <strong>{{ $first->partner_name }}</strong> with another one of your deals.</p>
                </div>
                <button type="button" @click="modal = false" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form @submit.prevent="submitAdd()" class="px-6 py-5 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Deal <span class="text-red-400">*</span></label>
                    <select x-model="form.deal_id" class="w-full text-sm bg-white border border-gray-200 rounded-xl px-3 py-2.5 outline-none focus:ring-2 focus:ring-teal-400/20 focus:border-teal-400 transition-all" required>
                        <option value="">Select a deal…</option>
                        @foreach($myDeals as $deal)
                        <option value="{{ $deal->id }}">{{ $deal->name }}
                            @if($deal->commission_status === 'paid') (Paid)
                            @elseif($deal->commission_status === 'locked') (Locked)
                            @endif
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Split Value <span class="text-red-400">*</span></label>
                        <input x-model="form.split_share_value" type="number" step="0.01" min="0.01"
                               :placeholder="form.split_share_type === 'percentage' ? 'e.g. 10' : 'e.g. 50000'"
                               class="w-full text-sm bg-white border border-gray-200 rounded-xl px-3 py-2.5 outline-none focus:ring-2 focus:ring-teal-400/20 focus:border-teal-400 transition-all" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Type</label>
                        <select x-model="form.split_share_type" class="w-full text-sm bg-white border border-gray-200 rounded-xl px-3 py-2.5 outline-none focus:ring-2 focus:ring-teal-400/20 focus:border-teal-400 transition-all">
                            <option value="percentage">Percentage (%)</option>
                            <option value="fixed_amount">Fixed Amount (₱)</option>
                        </select>
                    </div>
                </div>

                <div x-show="formError" class="text-xs text-red-600 bg-red-50 px-3 py-2.5 rounded-xl border border-red-200" x-text="formError"></div>

                <div class="flex gap-3 justify-end pt-1">
                    <button type="button" @click="modal = false"
                            class="px-4 py-2 rounded-xl text-sm font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200 transition-colors">Cancel</button>
                    <button type="submit"
                            :disabled="submitting || !form.deal_id || !form.split_share_value"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                            style="background:linear-gradient(135deg,#0D9488,#14B8A6)">
                        <svg x-show="submitting" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4z"/>
                        </svg>
                        <span x-text="submitting ? 'Adding…' : 'Add to Deal'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Remove Confirm Modal ────────────────────────────────── --}}
    <div x-show="removeModal" x-cloak
         class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4"
         @keydown.escape.window="removeModal = false">
        <div @click="removeModal = false" class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6" @click.stop
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-bold text-[#1E1B4B]">Remove Partner</h3>
                    <p class="text-xs text-gray-400 mt-0.5">This will remove the Partner from <span class="font-semibold" x-text="removeTarget.dealName"></span>.</p>
                </div>
            </div>
            <p class="text-sm text-gray-500 mb-4">Are you sure? This action will be logged and the admin will be notified.</p>
            <div x-show="removeError" class="text-xs text-red-600 bg-red-50 px-3 py-2 rounded-xl mb-3" x-text="removeError"></div>
            <div class="flex gap-3 justify-end">
                <button type="button" @click="removeModal = false" class="px-4 py-2 rounded-xl text-sm font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200 transition-colors">Cancel</button>
                <button type="button" @click="doRemove()"
                        :disabled="removing"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white bg-red-500 hover:bg-red-600 transition-colors disabled:opacity-50">
                    <svg x-show="removing" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4z"/>
                    </svg>
                    <span x-text="removing ? 'Removing…' : 'Remove Partner'"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- ── Toast ───────────────────────────────────────────────── --}}
    <div x-show="toast" x-cloak x-transition
         class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 flex items-center gap-3 bg-[#1E1B4B] text-white text-sm font-medium px-4 py-3 rounded-2xl shadow-xl">
        <svg class="w-4 h-4 text-teal-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <span x-text="toast"></span>
    </div>

</div>
@endsection

@push('scripts')
<script>
function partnerDetail() {
    const CSRF       = document.querySelector('meta[name=csrf-token]')?.content ?? '';
    const STORE      = '{{ route('reseller.partners.store', $tenantId) }}';
    const REMOVE_BASE= '{{ url('reseller/' . $tenantId . '/partners/splits') }}';
    const PARTNER_NAME = {!! json_encode($first->partner_name, JSON_HEX_TAG|JSON_HEX_AMP) !!};
    const PARTNER_EMAIL= {!! json_encode($email, JSON_HEX_TAG|JSON_HEX_AMP) !!};

    return {
        tab:         'overview',
        modal:       false,
        removeModal: false,
        submitting:  false,
        removing:    false,
        toast:       null,
        formError:   null,
        removeError: null,
        removeTarget:{ splitId: '', dealName: '' },
        form: {
            deal_id:           '',
            partner_name:      PARTNER_NAME,
            partner_email:     PARTNER_EMAIL,
            split_share_value: '',
            split_share_type:  'percentage',
        },

        init() {},

        openAddModal() {
            this.form = {
                deal_id: '',
                partner_name: PARTNER_NAME,
                partner_email: PARTNER_EMAIL,
                split_share_value: '',
                split_share_type: 'percentage',
            };
            this.formError = null;
            this.modal     = true;
        },

        async submitAdd() {
            if (this.submitting) return;
            this.submitting = true;
            this.formError  = null;
            try {
                const r = await fetch(STORE, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify(this.form),
                });
                const d = await r.json();
                if (!r.ok) { this.formError = d.error || d.message || 'Could not add Partner.'; return; }
                this.modal = false;
                this.showToast(d.message || 'Partner added to deal. Admins notified.');
                setTimeout(() => { window.location.reload(); }, 1200);
            } catch(e) {
                this.formError = 'Network error. Please try again.';
            } finally {
                this.submitting = false;
            }
        },

        confirmRemove(splitId, dealName) {
            this.removeTarget  = { splitId, dealName };
            this.removeError   = null;
            this.removeModal   = true;
        },

        async doRemove() {
            if (this.removing) return;
            this.removing    = true;
            this.removeError = null;
            try {
                const r = await fetch(`${REMOVE_BASE}/${this.removeTarget.splitId}`, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({}),
                });
                const d = await r.json();
                if (!r.ok) { this.removeError = d.error || d.message || 'Could not remove Partner.'; return; }
                this.removeModal = false;
                this.showToast(d.message || 'Partner removed from deal.');
                setTimeout(() => { window.location.reload(); }, 1200);
            } catch(e) {
                this.removeError = 'Network error. Please try again.';
            } finally {
                this.removing = false;
            }
        },

        showToast(msg) {
            this.toast = msg;
            setTimeout(() => { this.toast = null; }, 4000);
        },
    };
}
</script>
@endpush
