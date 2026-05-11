@extends('layouts.reseller')
@section('title', 'Dashboard')
@section('nav') @include('reseller._nav') @endsection

@section('topbar-actions')
    <a href="{{ route('reseller.deals', $tenant->id) }}" class="rs-btn-primary text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        <span class="hidden sm:inline">Create a Deal</span>
    </a>
@endsection

@section('content')
@php
    $stageColors = ['introduction'=>'#9CA3AF','presentation'=>'#3B82F6','contract_sent'=>'#F59E0B','signed'=>'#8B5CF6','paid'=>'#10B981'];
    $actionItems = collect($recentActivity ?? [])->filter(fn($a) => ($a['action_needed'] ?? false))->take(5)->values();
    $hasActions  = $actionItems->isNotEmpty();
@endphp

{{-- ── Reusable info popup macro ──────────────────────────────────────────
     Usage:
       @include('reseller._info-popup', ['label' => '...', 'title' => '...', 'body' => '...', 'align' => 'left|right'])
     Rendered inline with Alpine.js. ──────────────────────────────────── --}}

<div x-data class="space-y-5">

    {{-- ── 1. GREETING ─────────────────────────────────────────────────── --}}
    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold" style="color:#1E1B4B">
                Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }},
                {{ explode(' ', $reseller->name ?? 'Referrer')[0] }}!
            </h2>
            <p class="text-sm text-gray-400 mt-0.5">{{ now()->format('l, F j, Y') }} · {{ $tenant->name }}</p>
        </div>
        @if(($unreadCount ?? 0) > 0)
        <a href="{{ route('reseller.messages', $tenant->id) }}"
           class="flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-semibold text-white shrink-0 transition-opacity hover:opacity-90"
           style="background:#0D9488">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
            </svg>
            {{ $unreadCount }} unread
        </a>
        @endif
    </div>

    {{-- ── 2. PRIMARY KPI CARDS (Commission · Deals · Pipeline · Partners) ─ --}}
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 sm:gap-4">

        {{-- CARD 1: My Commission ──────────────────────────── --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-5 col-span-2 sm:col-span-1 xl:col-span-1">
            <div class="flex items-start justify-between mb-3">
                <div class="flex items-center gap-1.5">
                    <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0" style="background:#EDE9FE">
                        <svg class="w-4 h-4" style="color:#7B61FF" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <span class="text-xs font-semibold text-gray-600">My Commission</span>
                    {{-- Info popup --}}
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" @keydown.escape.window="open = false"
                                aria-label="Explain My Commission" :aria-expanded="open.toString()"
                                class="w-4 h-4 rounded-full bg-gray-100 text-gray-400 text-[8px] font-bold flex items-center justify-center hover:bg-purple-100 hover:text-purple-600 transition-colors shrink-0">
                            i
                        </button>
                        <div x-show="open" @click.outside="open = false" x-cloak
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             class="absolute left-0 top-full mt-2 z-50 w-72 bg-white rounded-xl shadow-xl border border-gray-100 p-4"
                             role="tooltip">
                            <button @click="open = false" class="absolute top-3 right-3 text-gray-300 hover:text-gray-500 text-sm leading-none">✕</button>
                            <p class="text-xs font-bold text-[#1E1B4B] mb-1.5 pr-5">My Commission</p>
                            <p class="text-xs text-gray-500 leading-relaxed">Your estimated earnings from assigned deals. <strong>Pending</strong> may change if deal amounts, splits, or stages change. <strong>Locked</strong> means the deal reached the required stage for commission confirmation. <strong>Paid</strong> has been released.</p>
                            <p class="text-[10px] text-gray-400 mt-2">Amounts shown are gross estimates subject to applicable taxes, deductions, and final approval.</p>
                        </div>
                    </div>
                </div>
            </div>
            <p class="text-2xl font-bold mb-3" style="color:#1E1B4B">₱{{ number_format($totalCommission ?? 0) }}</p>
            <div class="space-y-1.5">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-gray-400">Pending</span>
                    <span class="font-semibold text-amber-500">₱{{ number_format($commissionStats['pending'] ?? 0) }}</span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <span class="text-gray-400">Locked</span>
                    <span class="font-semibold" style="color:#7B61FF">₱{{ number_format($commissionStats['locked'] ?? 0) }}</span>
                </div>
                <div class="flex items-center justify-between text-xs border-t border-gray-50 pt-1.5">
                    <span class="text-gray-400">Paid</span>
                    <span class="font-semibold text-emerald-600">₱{{ number_format($commissionStats['paid'] ?? 0) }}</span>
                </div>
            </div>
            <p class="text-[10px] text-gray-400 mt-2">* Subject to applicable taxes and deductions.</p>
            <a href="{{ route('reseller.commission', $tenant->id) }}"
               class="mt-2 inline-flex items-center gap-1 text-[10px] font-semibold" style="color:#0D9488">
                View Commission →
            </a>
        </div>

        {{-- CARD 2: My Deals ────────────────────────────────── --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-5">
            <div class="flex items-start justify-between mb-3">
                <div class="flex items-center gap-1.5">
                    <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0" style="background:#CCFBF1">
                        <svg class="w-4 h-4" style="color:#0D9488" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </div>
                    <span class="text-xs font-semibold text-gray-600">My Deals</span>
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" @keydown.escape.window="open = false"
                                aria-label="Explain My Deals" :aria-expanded="open.toString()"
                                class="w-4 h-4 rounded-full bg-gray-100 text-gray-400 text-[8px] font-bold flex items-center justify-center hover:bg-teal-100 hover:text-teal-600 transition-colors shrink-0">
                            i
                        </button>
                        <div x-show="open" @click.outside="open = false" x-cloak
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             class="absolute left-0 top-full mt-2 z-50 w-72 bg-white rounded-xl shadow-xl border border-gray-100 p-4">
                            <button @click="open = false" class="absolute top-3 right-3 text-gray-300 hover:text-gray-500 text-sm leading-none">✕</button>
                            <p class="text-xs font-bold text-[#1E1B4B] mb-1.5 pr-5">My Deals</p>
                            <p class="text-xs text-gray-500 leading-relaxed">The total number of deals currently assigned to you as a Referrer. These are deals you can view, update, add notes to, upload documents for, and move forward based on your permissions.</p>
                        </div>
                    </div>
                </div>
            </div>
            <p class="text-2xl font-bold mb-1" style="color:#1E1B4B">{{ $stats['total'] }}</p>
            <p class="text-xs text-gray-400 mb-3">{{ $stats['active'] }} active · {{ $stats['expiring'] }} expiring</p>
            <a href="{{ route('reseller.deals', $tenant->id) }}"
               class="inline-flex items-center gap-1 text-[10px] font-semibold" style="color:#0D9488">
                View Deals →
            </a>
        </div>

        {{-- CARD 3: My Deal Pipeline Amount ──────────────────── --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-5">
            <div class="flex items-start justify-between mb-3">
                <div class="flex items-center gap-1.5">
                    <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0" style="background:#FEF3C7">
                        <svg class="w-4 h-4" style="color:#D97706" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                        </svg>
                    </div>
                    <span class="text-xs font-semibold text-gray-600">My Pipeline</span>
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" @keydown.escape.window="open = false"
                                aria-label="Explain My Deal Pipeline Amount" :aria-expanded="open.toString()"
                                class="w-4 h-4 rounded-full bg-gray-100 text-gray-400 text-[8px] font-bold flex items-center justify-center hover:bg-amber-100 hover:text-amber-600 transition-colors shrink-0">
                            i
                        </button>
                        <div x-show="open" @click.outside="open = false" x-cloak
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             class="absolute left-0 top-full mt-2 z-50 w-72 bg-white rounded-xl shadow-xl border border-gray-100 p-4">
                            <button @click="open = false" class="absolute top-3 right-3 text-gray-300 hover:text-gray-500 text-sm leading-none">✕</button>
                            <p class="text-xs font-bold text-[#1E1B4B] mb-1.5 pr-5">My Deal Pipeline Amount</p>
                            <p class="text-xs text-gray-500 leading-relaxed">The total contract value of your assigned active deals. This is <strong>not</strong> the same as your commission. Your commission depends on the deal amount, pricing breakdown, commission pool, and your split share.</p>
                        </div>
                    </div>
                </div>
            </div>
            <p class="text-2xl font-bold mb-1 truncate" style="color:#1E1B4B">₱{{ number_format($stats['pipeline'] ?? 0) }}</p>
            <p class="text-xs text-gray-400 mb-3">Total value of assigned deals</p>
            <a href="{{ route('reseller.deals', $tenant->id) }}"
               class="inline-flex items-center gap-1 text-[10px] font-semibold" style="color:#0D9488">
                Review Pipeline →
            </a>
        </div>

        {{-- CARD 4: My Partners ────────────────────────────── --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-5">
            <div class="flex items-start justify-between mb-3">
                <div class="flex items-center gap-1.5">
                    <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0" style="background:#DBEAFE">
                        <svg class="w-4 h-4" style="color:#3B82F6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <span class="text-xs font-semibold text-gray-600">My Partners</span>
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" @keydown.escape.window="open = false"
                                aria-label="Explain My Partners" :aria-expanded="open.toString()"
                                class="w-4 h-4 rounded-full bg-gray-100 text-gray-400 text-[8px] font-bold flex items-center justify-center hover:bg-blue-100 hover:text-blue-600 transition-colors shrink-0">
                            i
                        </button>
                        <div x-show="open" @click.outside="open = false" x-cloak
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             class="absolute right-0 top-full mt-2 z-50 w-72 bg-white rounded-xl shadow-xl border border-gray-100 p-4">
                            <button @click="open = false" class="absolute top-3 right-3 text-gray-300 hover:text-gray-500 text-sm leading-none">✕</button>
                            <p class="text-xs font-bold text-[#1E1B4B] mb-1.5 pr-5">My Partners</p>
                            <p class="text-xs text-gray-500 leading-relaxed">Partners connected to your assigned deals. Partners may help move a deal forward and may have their own split share if approved. Pending Partners are invited but not yet activated.</p>
                        </div>
                    </div>
                </div>
            </div>
            <p class="text-2xl font-bold mb-1" style="color:#1E1B4B">{{ $partnerCount ?? 0 }}</p>
            <p class="text-xs text-gray-400 mb-3">Connected to your deals</p>
            @if(($partnerCount ?? 0) === 0)
            <p class="text-[10px] text-gray-400">Add a Partner via a deal's detail page.</p>
            @else
            <a href="{{ route('reseller.deals', $tenant->id) }}"
               class="inline-flex items-center gap-1 text-[10px] font-semibold" style="color:#0D9488">
                View Deals →
            </a>
            @endif
        </div>
    </div>

    {{-- ── 3. EXPIRING ALERT ─────────────────────────────────────────────── --}}
    @if(($stats['expiring'] ?? 0) > 0)
    <div class="flex items-center gap-3 px-4 py-3 rounded-2xl border" style="background:#FFFBEB;border-color:#FDE68A">
        <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <p class="text-sm text-amber-700 font-medium flex-1">
            {{ $stats['expiring'] }} deal{{ $stats['expiring'] > 1 ? 's are' : ' is' }} expiring soon — take action before they expire.
        </p>
        <a href="{{ route('reseller.deals', $tenant->id) }}?status=expiring"
           class="text-xs font-bold text-amber-700 hover:text-amber-900 whitespace-nowrap shrink-0">View →</a>
    </div>
    @endif

    {{-- ── 4. CRITICAL ACTIONS / NEXT BEST ACTIONS ──────────────────────── --}}
    @if($hasActions)
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
            <div class="flex items-center gap-2">
                <div class="w-2 h-2 rounded-full bg-orange-400 animate-pulse"></div>
                <p class="text-sm font-bold" style="color:#1E1B4B">Actions Needed</p>
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" @keydown.escape.window="open = false"
                            aria-label="Explain Actions Needed"
                            class="w-4 h-4 rounded-full bg-gray-100 text-gray-400 text-[8px] font-bold flex items-center justify-center hover:bg-orange-100 hover:text-orange-600 transition-colors">
                        i
                    </button>
                    <div x-show="open" @click.outside="open = false" x-cloak
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         class="absolute left-0 top-full mt-2 z-50 w-72 bg-white rounded-xl shadow-xl border border-gray-100 p-4">
                        <button @click="open = false" class="absolute top-3 right-3 text-gray-300 hover:text-gray-500 text-sm leading-none">✕</button>
                        <p class="text-xs font-bold text-[#1E1B4B] mb-1.5 pr-5">Actions Needed</p>
                        <p class="text-xs text-gray-500 leading-relaxed">Important items that need your attention to keep your deals moving. Completing them helps keep deals updated, documented, and progressing through the pipeline.</p>
                    </div>
                </div>
            </div>
            <span class="text-[10px] font-bold text-orange-500 bg-orange-50 px-2 py-0.5 rounded-full">{{ $actionItems->count() }} item{{ $actionItems->count() > 1 ? 's' : '' }}</span>
        </div>
        <div class="divide-y divide-gray-50">
            @foreach($actionItems as $act)
            <div class="flex items-start gap-3 px-4 py-3">
                <div class="w-1.5 h-1.5 rounded-full mt-2 shrink-0"
                     style="background:{{ ['urgent'=>'#EF4444','high'=>'#F97316','medium'=>'#F59E0B','low'=>'#3B82F6'][$act['severity']] ?? '#9CA3AF' }}"></div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold" style="color:#1E1B4B">{{ $act['summary'] }}</p>
                    <p class="text-[10px] text-gray-400 mt-0.5">{{ $act['occurred_ago'] }}</p>
                </div>
                @if($act['action_url'] ?? null)
                <a href="{{ $act['action_url'] }}"
                   class="text-[10px] font-bold shrink-0 mt-0.5 whitespace-nowrap" style="color:#0D9488">
                    {{ $act['action_label'] ?? 'Act →' }}
                </a>
                @else
                <span class="text-[9px] font-bold text-orange-500 uppercase shrink-0 mt-1">Action needed</span>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ── 5. RECENT DEALS ──────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
            <div class="flex items-center gap-1.5">
                <p class="text-sm font-bold" style="color:#1E1B4B">Recent Deals</p>
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" @keydown.escape.window="open = false"
                            aria-label="Explain Recent Deals"
                            class="w-4 h-4 rounded-full bg-gray-100 text-gray-400 text-[8px] font-bold flex items-center justify-center hover:bg-gray-200 transition-colors">
                        i
                    </button>
                    <div x-show="open" @click.outside="open = false" x-cloak
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         class="absolute left-0 top-full mt-2 z-50 w-72 bg-white rounded-xl shadow-xl border border-gray-100 p-4">
                        <button @click="open = false" class="absolute top-3 right-3 text-gray-300 hover:text-gray-500 text-sm leading-none">✕</button>
                        <p class="text-xs font-bold text-[#1E1B4B] mb-1.5 pr-5">Recent Deals</p>
                        <p class="text-xs text-gray-500 leading-relaxed">Your most recently created or updated assigned deals. Open a deal to add notes, upload documents, add Partners, or request a stage move.</p>
                    </div>
                </div>
            </div>
            <a href="{{ route('reseller.deals', $tenant->id) }}"
               class="text-xs font-semibold" style="color:#0D9488">View all →</a>
        </div>

        @if($recentLeads->isEmpty())
        <div class="flex flex-col items-center justify-center py-12 text-center px-4">
            <img src="/images/mascots/r-bunny-rocket.webp" alt="" aria-hidden="true" class="w-14 h-14 object-contain mb-3 opacity-60">
            <p class="text-sm font-semibold text-gray-500">No deals assigned yet</p>
            <p class="text-xs text-gray-400 mt-1 max-w-xs">Once an Admin or Manager assigns a deal to you, it will appear here with pipeline, commission, partners, and activity.</p>
            <a href="{{ route('reseller.deals', $tenant->id) }}" class="rs-btn-primary mt-4 text-xs">Go to My Deals</a>
        </div>
        @else
        <div class="divide-y divide-gray-50">
            @foreach($recentLeads as $lead)
            @php
                $sc  = $stageColors[$lead->stage ?? ''] ?? '#9CA3AF';
                $sc2 = match($lead->status ?? '') {
                    'active'   => ['bg'=>'#D1FAE5','text'=>'#065F46'],
                    'expiring' => ['bg'=>'#FEF3C7','text'=>'#D97706'],
                    'expired'  => ['bg'=>'#FEE2E2','text'=>'#DC2626'],
                    default    => ['bg'=>'#F3F4F6','text'=>'#6B7280'],
                };
            @endphp
            <a href="{{ route('reseller.deals.show', [$tenant->id, $lead->id]) }}"
               class="flex items-center gap-3 px-5 py-3.5 hover:bg-gray-50 transition-colors group">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white text-xs font-bold shrink-0"
                     style="background:{{ $sc }}">
                    {{ strtoupper(substr($lead->name ?? '??', 0, 2)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold truncate group-hover:underline" style="color:#1E1B4B">
                        {{ $lead->name ?? 'Unnamed Deal' }}
                    </p>
                    <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                        <span class="text-xs text-gray-400">{{ ucfirst(str_replace('_', ' ', $lead->stage ?? 'unknown')) }}</span>
                        <span class="text-gray-200 text-xs">·</span>
                        <span class="text-[10px] px-2 py-0.5 rounded-full font-medium"
                              style="background:{{ $sc2['bg'] }};color:{{ $sc2['text'] }}">
                            {{ $lead->status ?? 'unknown' }}
                        </span>
                    </div>
                </div>
                <div class="text-right shrink-0">
                    <p class="text-xs text-gray-400 mb-0.5">Deal Value</p>
                    <p class="text-sm font-bold" style="color:#1E1B4B">₱{{ number_format($lead->deal_value ?? 0) }}</p>
                    @if(($lead->my_commission ?? 0) > 0)
                    <p class="text-xs font-semibold mt-1" style="color:#7B61FF">
                        My Commission: ₱{{ number_format($lead->my_commission) }}
                    </p>
                    @else
                    <p class="text-[10px] text-gray-300 mt-1">Commission TBD</p>
                    @endif
                </div>
            </a>
            @endforeach
        </div>
        @endif
    </div>

    {{-- ── 7. MESSAGES + RECENT ACTIVITY (side-by-side on md+) ─────────── --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        {{-- Messages --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-1.5">
                    <p class="text-sm font-bold" style="color:#1E1B4B">Messages</p>
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" @keydown.escape.window="open = false"
                                aria-label="Explain Messages"
                                class="w-4 h-4 rounded-full bg-gray-100 text-gray-400 text-[8px] font-bold flex items-center justify-center hover:bg-gray-200 transition-colors">
                            i
                        </button>
                        <div x-show="open" @click.outside="open = false" x-cloak
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             class="absolute left-0 top-full mt-2 z-50 w-64 bg-white rounded-xl shadow-xl border border-gray-100 p-4">
                            <button @click="open = false" class="absolute top-3 right-3 text-gray-300 hover:text-gray-500 text-sm leading-none">✕</button>
                            <p class="text-xs font-bold text-[#1E1B4B] mb-1.5 pr-5">Messages</p>
                            <p class="text-xs text-gray-500 leading-relaxed">Recent messages connected to your deals and team conversations, including updates from Admins, Managers, and Partners.</p>
                        </div>
                    </div>
                </div>
                <a href="{{ route('reseller.messages', $tenant->id) }}"
                   class="text-xs font-semibold" style="color:#0D9488">Open →</a>
            </div>
            @if(($unreadCount ?? 0) > 0)
            <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl" style="background:#F0FDFA">
                <svg class="w-5 h-5 shrink-0" style="color:#0D9488" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                </svg>
                <div>
                    <p class="text-sm font-semibold" style="color:#0D9488">{{ $unreadCount }} unread {{ Str::plural('message', $unreadCount) }}</p>
                    <p class="text-[10px] text-gray-400 mt-0.5">Reply to stay on top of your deals.</p>
                </div>
            </div>
            @else
            <div class="flex flex-col items-center py-6 text-center">
                <svg class="w-8 h-8 text-gray-200 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                </svg>
                <p class="text-xs text-gray-400">No unread messages.</p>
            </div>
            @endif
        </div>

        {{-- Recent Deal Activity --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-1.5">
                    <p class="text-sm font-bold" style="color:#1E1B4B">Recent Activity</p>
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" @keydown.escape.window="open = false"
                                aria-label="Explain Recent Activity"
                                class="w-4 h-4 rounded-full bg-gray-100 text-gray-400 text-[8px] font-bold flex items-center justify-center hover:bg-gray-200 transition-colors">
                            i
                        </button>
                        <div x-show="open" @click.outside="open = false" x-cloak
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             class="absolute right-0 top-full mt-2 z-50 w-64 bg-white rounded-xl shadow-xl border border-gray-100 p-4">
                            <button @click="open = false" class="absolute top-3 right-3 text-gray-300 hover:text-gray-500 text-sm leading-none">✕</button>
                            <p class="text-xs font-bold text-[#1E1B4B] mb-1.5 pr-5">Recent Deal Activity</p>
                            <p class="text-xs text-gray-500 leading-relaxed">Recent actions from your assigned deals — stage moves, document uploads, note additions, partner changes, and commission updates.</p>
                        </div>
                    </div>
                </div>
                <a href="{{ route('reseller.activity', $tenant->id) }}"
                   class="text-xs font-semibold" style="color:#0D9488">View all →</a>
            </div>
            @if(empty($recentActivity))
            <div class="flex flex-col items-center py-6 text-center">
                <svg class="w-8 h-8 text-gray-200 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <p class="text-xs text-gray-400">Recent deal updates will appear here.</p>
            </div>
            @else
            @php $actSeverityColor = ['urgent'=>'#EF4444','high'=>'#F97316','medium'=>'#F59E0B','low'=>'#3B82F6','info'=>'#9CA3AF']; @endphp
            <div class="space-y-2.5">
                @foreach(array_slice($recentActivity, 0, 5) as $act)
                <div class="flex items-start gap-2.5">
                    <span class="w-1.5 h-1.5 rounded-full mt-1.5 shrink-0"
                          style="background:{{ $actSeverityColor[$act['severity']] ?? '#9CA3AF' }}"></span>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-medium leading-snug" style="color:#1E1B4B">{{ $act['summary'] }}</p>
                        <p class="text-[10px] text-gray-400 mt-0.5">{{ $act['occurred_ago'] }}</p>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>

</div>
@endsection
