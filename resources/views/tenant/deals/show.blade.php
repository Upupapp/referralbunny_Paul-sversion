@extends('layouts.app')
@section('title', 'Deal Detail')
@section('nav') @include('tenant._nav') @endsection

@section('topbar-actions')
    <button x-data @click="$dispatch('open-move-stage-deal')" class="btn-secondary text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        Move Stage
    </button>
    <button x-data @click="$dispatch('open-reassign-deal')" class="btn-secondary text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
        Reassign
    </button>
@endsection

@section('content')
<div class="space-y-5"
     x-data="dealDetail('{{ $dealId }}', '{{ $tenant->id }}')"
     x-init="init()"
     @open-move-stage-deal.window="showMoveStage = true"
     @open-reassign-deal.window="showReassign = true">

    <a href="{{ route('tenant.deals', $tenant->id) }}"
       class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Back to Deals
    </a>

    {{-- Loading state --}}
    <div x-show="loading" class="card flex items-center justify-center py-16 gap-3 text-gray-400">
        <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
        <span class="text-sm">Loading deal…</span>
    </div>

    <div x-show="!loading && lead" class="space-y-5">

        {{-- â”€â”€ Header â”€â”€ --}}
        <div class=”card border-l-4 transition-colors” :class=”headerStatusBorderClass()”>
            <div class=”flex flex-col sm:flex-row sm:items-start gap-4”>
                <div class=”w-14 h-14 rounded-2xl flex items-center justify-center text-[#7B61FF] font-bold text-xl shrink-0”
                     style=”background:#EDE9FE” x-text=”(lead?.name||'?').slice(0,2).toUpperCase()”></div>
                <div class=”flex-1 min-w-0”>
                    <div class=”flex flex-wrap items-center gap-2 mb-1.5”>
                        <h2 class=”text-xl font-bold text-[#1E1B4B]” x-text=”lead?.name”></h2>
                        <span :class=”{
                            'badge badge-green':  lead?.status === 'active',
                            'badge badge-orange': lead?.status === 'expiring',
                            'badge badge-red':    lead?.status === 'expired',
                            'badge badge-gray':   !['active','expiring','expired'].includes(lead?.status||''),
                        }” x-text=”lead?.status ? lead.status.charAt(0).toUpperCase()+lead.status.slice(1) : ''”></span>
                        <span :class=”stageBadge(lead?.stage)” x-text=”stageLabel(lead?.stage)”></span>
                    </div>
                    <div class=”flex flex-wrap items-center gap-x-4 gap-y-1.5 mt-1”>
                        <p class=”text-sm text-gray-500 flex items-center gap-1.5”>
                            <svg class=”w-3.5 h-3.5 text-gray-400 shrink-0” fill=”none” stroke=”currentColor” viewBox=”0 0 24 24”><path stroke-linecap=”round” stroke-linejoin=”round” stroke-width=”2” d=”M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z”/></svg>
                            <span class=”font-medium text-gray-700” x-text=”lead?.reseller_name || 'Unassigned'”></span>
                        </p>
                        <span x-show=”lead?.data?.province” class=”inline-flex items-center gap-1 text-xs text-blue-600”>
                            <svg class=”w-3 h-3 shrink-0” fill=”none” stroke=”currentColor” viewBox=”0 0 24 24”><path stroke-linecap=”round” stroke-linejoin=”round” stroke-width=”2” d=”M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z”/><path stroke-linecap=”round” stroke-linejoin=”round” stroke-width=”2” d=”M15 11a3 3 0 11-6 0 3 3 0 016 0z”/></svg>
                            <span x-text=”[lead?.data?.municipality, lead?.data?.province].filter(Boolean).join(', ')”></span>
                        </span>
                    </div>
                    <p class=”text-xs text-gray-400 mt-1.5” x-text=”lead?.created_at ? 'Created ' + new Date(lead.created_at).toLocaleDateString('en',{month:'long',day:'numeric',year:'numeric'}) : ''”></p>
                </div>
                <div class=”flex flex-col items-end gap-2 shrink-0”>
                    <div class=”text-right”>
                        <p class=”text-2xl font-bold text-[#1E1B4B]” x-text=”fmt(contractValue())”></p>
                        <p class=”text-xs text-gray-400”>Contract Value</p>
                    </div>
                    {{-- Days left urgency badge --}}
                    <div x-show=”lead?.days_left != null”
                         class=”inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold”
                         :class=”daysLeftBadgeClass()”>
                        <svg class=”w-3 h-3 shrink-0” fill=”none” stroke=”currentColor” viewBox=”0 0 24 24”><path stroke-linecap=”round” stroke-linejoin=”round” stroke-width=”2” d=”M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z”/></svg>
                        <span x-text=”lead?.days_left + ' days left'”></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- â”€â”€ Stage Progress â”€â”€ --}}
        <div class="card">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-[#1E1B4B] text-sm">Pipeline Progress</h3>
                <button @click="showMoveStage = true" class="text-xs text-purple-600 hover:text-purple-700 font-medium">Move Stage →</button>
            </div>
            <div class="flex items-center">
                <template x-for="(s, i) in allStages" :key="s.key">
                    <div class="flex items-center flex-1 min-w-0">
                        <div class="flex flex-col items-center flex-none w-16">
                            <div :class="stageCircleClass(s.key)"
                                 class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all shrink-0">
                                <template x-if="isStageDone(s.key)">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                </template>
                                <template x-if="!isStageDone(s.key)">
                                    <span x-text="i + 1"></span>
                                </template>
                            </div>
                            <span class="text-[10px] text-gray-500 text-center mt-1.5 leading-tight" x-text="s.label"></span>
                        </div>
                        <div x-show="i < allStages.length - 1" class="flex-1 h-0.5 mx-1 transition-colors"
                             :class="isStageDone(allStages[i+1]?.key) ? 'bg-[#7B61FF]' : 'bg-gray-200'"></div>
                    </div>
                </template>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════
             FINANCIAL BREAKDOWN  â† the key feature
             ══════════════════════════════════════════════ --}}
        <div class="card">

            {{-- Header --}}
            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-emerald-100 flex items-center justify-center">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h3 class="font-semibold text-[#1E1B4B]">Financial Breakdown</h3>
                    @if($showLocation ?? false)<x-tax-tip />@endif
                </div>
                <button x-show="!editFinance" @click="startEditFinance()"
                        class="flex items-center gap-1.5 text-xs text-purple-600 hover:text-purple-700 font-medium">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Edit
                </button>
            </div>

            {{-- â”€â”€ View mode â”€â”€ --}}
            <div x-show="!editFinance" class="space-y-5">

                {{-- Calculation table --}}
                <div class="space-y-0">
                    <div class="flex items-center justify-between py-3 border-b border-gray-100">
                        <div class="flex items-center gap-3">
                            <span class="w-6 h-6 rounded-lg bg-gray-100 flex items-center justify-center text-xs font-bold text-gray-500 shrink-0">₱</span>
                            <div>
                                <p class="text-sm font-medium text-[#1E1B4B]">Base Cost</p>
                                <p class="text-xs text-gray-400">Actual cost to deliver</p>
                            </div>
                        </div>
                        <span class="text-base font-bold text-gray-800 tabular-nums" x-text="fmt(lead?.base_cost || 0)"></span>
                    </div>
                    <div class="flex items-center justify-between py-3 border-b border-dashed border-gray-200">
                        <div class="flex items-center gap-3">
                            <span class="w-6 h-6 rounded-lg bg-blue-50 flex items-center justify-center text-xs font-bold text-blue-500 shrink-0">+</span>
                            <div>
                                <p class="text-sm font-medium text-[#1E1B4B]">Added Amount</p>
                                <p class="text-xs text-gray-400">Markup / margin — this gets split</p>
                            </div>
                        </div>
                        <span class="text-base font-semibold text-blue-600 tabular-nums" x-text="fmt(lead?.added_amount || 0)"></span>
                    </div>
                    <div class="flex items-center justify-between py-3 bg-[#F0EFFA] rounded-xl px-4 mt-1">
                        <div class="flex items-center gap-3">
                            <span class="w-6 h-6 rounded-lg bg-purple-200 flex items-center justify-center text-xs font-bold text-purple-700 shrink-0">=</span>
                            <div>
                                <p class="text-sm font-bold text-[#1E1B4B]">Contract Value</p>
                                <p class="text-xs text-purple-500">Base Cost + Added Amount</p>
                            </div>
                        </div>
                        <span class="text-xl font-bold text-[#1E1B4B] tabular-nums" x-text="fmt(contractValue())"></span>
                    </div>
                </div>

                {{-- Split boxes --}}
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-2xl p-4 space-y-1.5" style="background:linear-gradient(135deg,#EFF6FF,#DBEAFE)">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-lg bg-blue-200 flex items-center justify-center">
                                <svg class="w-3.5 h-3.5 text-blue-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/></svg>
                            </div>
                            <div class="flex items-center gap-1"><p class="text-xs font-semibold text-blue-700 uppercase tracking-wide">Company Share</p>@if($showLocation ?? false)<x-tax-tip />@endif</div>
                        </div>
                        <p class="text-2xl font-bold text-blue-800 tabular-nums" x-text="fmt(companyShare())"></p>
                        <p class="text-xs text-blue-600">30% of Added Amount</p>
                        <div class="h-1 bg-blue-200 rounded-full overflow-hidden mt-2">
                            <div class="h-full bg-blue-500 rounded-full" style="width:30%"></div>
                        </div>
                    </div>
                    <div class="rounded-2xl p-4 space-y-1.5" style="background:linear-gradient(135deg,#F0FDF4,#DCFCE7)">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-lg bg-emerald-200 flex items-center justify-center">
                                <svg class="w-3.5 h-3.5 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/></svg>
                            </div>
                            <div class="flex items-center gap-1"><p class="text-xs font-semibold text-emerald-700 uppercase tracking-wide">Commission Pool</p>@if($showLocation ?? false)<x-tax-tip />@endif</div>
                        </div>
                        <p class="text-2xl font-bold text-emerald-800 tabular-nums" x-text="fmt(commPool())"></p>
                        <p class="text-xs text-emerald-600">70% of Added Amount</p>
                        <div class="h-1 bg-emerald-200 rounded-full overflow-hidden mt-2">
                            <div class="h-full bg-emerald-500 rounded-full" style="width:70%"></div>
                        </div>
                    </div>
                </div>

                {{-- Commission distribution --}}
                <div x-show="(lead?.commission_splits||[]).length > 0">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Commission Distribution</p>
                        <span :class="{
                            'badge badge-gray':   lead?.commission_status === 'pending',
                            'badge badge-orange': lead?.commission_status === 'locked',
                            'badge badge-green':  lead?.commission_status === 'paid',
                        }" x-text="lead?.commission_status ? lead.commission_status.charAt(0).toUpperCase()+lead.commission_status.slice(1) : 'Pending'"></span>
                    </div>
                    <div class="space-y-2">
                        <template x-for="split in (lead?.commission_splits||[])" :key="split.id">
                            <div class="flex items-center gap-3 p-3 rounded-xl bg-gray-50">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                                     :class="split.role==='primary' ? 'bg-emerald-100 text-emerald-700' : split.role==='secondary' ? 'bg-blue-100 text-blue-700' : 'bg-gray-200 text-gray-600'"
                                     x-text="split.reseller_name.slice(0,2).toUpperCase()"></div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-[#1E1B4B] truncate" x-text="split.reseller_name"></p>
                                    <p class="text-xs text-gray-400 capitalize" x-text="split.role + ' · ' + split.percentage + '% of pool'"></p>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-sm font-bold text-[#1E1B4B] tabular-nums" x-text="fmt(commPool() * split.percentage / 100)"></p>
                                    <p class="text-xs text-gray-400">commission</p>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100 text-sm">
                        <span class="text-gray-500">Total pool distributed</span>
                        <span class="font-bold text-[#1E1B4B]" x-text="fmt(commPool())"></span>
                    </div>
                </div>

                {{-- No financial data notice --}}
                <div x-show="!lead?.added_amount || Number(lead?.added_amount) === 0" class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800">
                    No financial data set yet. Click <strong>Edit</strong> to enter Base Cost and Added Amount.
                </div>

            </div>

            {{-- â”€â”€ Edit mode â”€â”€ --}}
            <div x-show="editFinance" class="space-y-5">
                <p class="text-xs text-gray-500">Enter the deal financials. Contract Value, Company Share, and Commission Pool are calculated automatically.</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Base Cost (₱) <span class="text-gray-400 font-normal">— actual delivery cost</span></label>
                        <input type="number" x-model.number="financeForm.base_cost" @input="recalc()"
                               class="form-input" placeholder="0" min="0" step="100">
                    </div>
                    <div>
                        <label class="form-label">Added Amount (₱) <span class="text-gray-400 font-normal">— your margin</span></label>
                        <input type="number" x-model.number="financeForm.added_amount" @input="recalc()"
                               class="form-input" placeholder="0" min="0" step="100">
                    </div>
                </div>

                {{-- Live preview --}}
                <div class="p-4 rounded-2xl space-y-3" style="background:linear-gradient(135deg,#F5F3FF,#F0FDF4)">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Live Preview</p>

                    <div class="grid grid-cols-3 gap-3">
                        <div class="bg-white rounded-xl p-3 text-center shadow-sm">
                            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Contract Value</p>
                            <p class="text-base font-bold text-[#1E1B4B] tabular-nums" x-text="fmt(previewContract())"></p>
                            <p class="text-xs text-gray-400 mt-0.5">base + margin</p>
                        </div>
                        <div class="bg-white rounded-xl p-3 text-center shadow-sm">
                            <p class="text-xs text-blue-600 uppercase tracking-wide mb-1">Company Share</p>
                            <p class="text-base font-bold text-blue-700 tabular-nums" x-text="fmt(previewCompanyShare())"></p>
                            <p class="text-xs text-blue-400 mt-0.5">30% of margin</p>
                        </div>
                        <div class="bg-white rounded-xl p-3 text-center shadow-sm">
                            <p class="text-xs text-emerald-600 uppercase tracking-wide mb-1">Commission Pool</p>
                            <p class="text-base font-bold text-emerald-700 tabular-nums" x-text="fmt(previewCommPool())"></p>
                            <p class="text-xs text-emerald-400 mt-0.5">70% of margin</p>
                        </div>
                    </div>

                    {{-- Per-reseller preview --}}
                    <div x-show="(lead?.commission_splits||[]).length > 0" class="space-y-1.5 pt-1">
                        <p class="text-xs text-gray-400 uppercase tracking-wider">Referrer Earnings</p>
                        <template x-for="split in (lead?.commission_splits||[])" :key="split.id">
                            <div class="flex items-center justify-between bg-white rounded-lg px-3 py-2 text-xs">
                                <span class="text-gray-700 font-medium" x-text="split.reseller_name"></span>
                                <span class="text-gray-500 mx-2" x-text="split.percentage + '%'"></span>
                                <span class="font-bold text-emerald-700 tabular-nums" x-text="fmt(previewCommPool() * split.percentage / 100)"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <button @click="cancelEditFinance()" class="btn-secondary">Cancel</button>
                    <button @click="saveFinance()" :disabled="saving" class="btn-primary" x-text="saving ? 'Saving…' : 'Save Financial Data'"></button>
                </div>
            </div>
        </div>

        {{-- â”€â”€ Bottom layout: Details + Notes/History â”€â”€ --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

            {{-- Left: Details + Commission splits quick view --}}
            <div class="space-y-4">
                <div class="card">
                    <h3 class="font-semibold text-[#1E1B4B] text-sm mb-3">Deal Details</h3>
                    <div class="divide-y divide-gray-50">
                        <div class="flex items-center justify-between py-2.5">
                            <div class="flex items-center gap-2 text-gray-400">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                <span class="text-sm">Referrer</span>
                            </div>
                            <span class="text-sm font-medium text-gray-700 text-right max-w-[55%] truncate" x-text="lead?.reseller_name || '—'"></span>
                        </div>
                        @if($showLocation ?? false)
                        <div class="flex items-center justify-between py-2.5">
                            <div class="flex items-center gap-2 text-gray-400">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/></svg>
                                <span class="text-sm">Province</span>
                            </div>
                            <span class="text-sm font-medium text-gray-700" x-text="lead?.data?.province || '—'"></span>
                        </div>
                        <div class="flex items-center justify-between py-2.5">
                            <div class="flex items-center gap-2 text-gray-400">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span class="text-sm">Municipality</span>
                            </div>
                            <span class="text-sm font-medium text-gray-700" x-text="lead?.data?.municipality || '—'"></span>
                        </div>
                        @endif
                        <div class="flex items-center justify-between py-2.5">
                            <div class="flex items-center gap-2 text-gray-400">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                <span class="text-sm">Stage</span>
                            </div>
                            <span :class="stageBadge(lead?.stage)" x-text="stageLabel(lead?.stage)"></span>
                        </div>
                        <div class="flex items-center justify-between py-2.5">
                            <div class="flex items-center gap-2 text-gray-400">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span class="text-sm">Days Left</span>
                            </div>
                            <span class="text-sm font-semibold tabular-nums"
                                  :class="daysLeftTextClass()"
                                  x-text="lead?.days_left != null ? lead.days_left + ' days' : '—'"></span>
                        </div>
                        <div class="flex items-center justify-between py-2.5">
                            <div class="flex items-center gap-2 text-gray-400">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span class="text-sm">Commission</span>
                            </div>
                            <span :class="{
                                'badge badge-gray':   lead?.commission_status === 'pending',
                                'badge badge-orange': lead?.commission_status === 'locked',
                                'badge badge-green':  lead?.commission_status === 'paid',
                            }" x-text="lead?.commission_status ? lead.commission_status.charAt(0).toUpperCase()+lead.commission_status.slice(1) : 'Pending'"></span>
                        </div>
                    </div>
                </div>

                {{-- Commission Splits --}}
                <div class="card space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-[#1E1B4B] text-sm">Commission Splits</h3>
                        <button x-show="!editSplits" @click="openEditSplits()" class="text-xs text-purple-600 hover:text-purple-700 font-medium">Edit</button>
                        <button x-show="editSplits"  @click="editSplits = false" class="text-xs text-gray-500 hover:text-gray-700 font-medium">Cancel</button>
                    </div>

                    {{-- View mode --}}
                    <div x-show="!editSplits" class="space-y-2">
                        <template x-if="(lead?.commission_splits||[]).length === 0">
                            <p class="text-xs text-gray-400 py-1">Primary referrer gets 100% of pool. <button @click="openEditSplits()" class="text-purple-600 hover:underline">Add splits</button></p>
                        </template>
                        <template x-for="split in (lead?.commission_splits||[])" :key="split.id">
                            <div class="flex items-center gap-2.5 py-1.5 border-b border-gray-50 last:border-0">
                                <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                                     :class="split.role==='primary' ? 'bg-purple-100 text-purple-700' : split.role==='secondary' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500'"
                                     x-text="(split.reseller_name||'?').slice(0,2).toUpperCase()"></div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-[#1E1B4B] truncate" x-text="split.reseller_name"></p>
                                    <p class="text-xs text-gray-400 capitalize" x-text="split.role"></p>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-sm font-bold text-[#1E1B4B] tabular-nums" x-text="split.percentage + '%'"></p>
                                    <p class="text-xs text-emerald-600 tabular-nums" x-show="commPool() > 0" x-text="fmt(commPool() * split.percentage / 100)"></p>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Edit mode --}}
                    <div x-show="editSplits" class="space-y-2.5">
                        <template x-for="(split, i) in splitsForm" :key="i">
                            <div class="flex items-center gap-2">
                                <input type="text" x-model="split.reseller_name" class="form-input text-xs flex-1 min-w-0" placeholder="Referrer name">
                                <select x-model="split.role" class="form-input text-xs w-24 shrink-0">
                                    <option value="primary">Primary</option>
                                    <option value="secondary">Secondary</option>
                                    <option value="tertiary">Tertiary</option>
                                </select>
                                <div class="flex items-center gap-1 shrink-0">
                                    <input type="number" x-model.number="split.percentage" class="form-input text-xs w-16" placeholder="%" min="0" max="100">
                                    <span class="text-xs text-gray-400">%</span>
                                </div>
                                <button @click="splitsForm.splice(i,1)" class="text-red-400 hover:text-red-600 shrink-0" type="button">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </template>
                        <button @click="splitsForm.push({reseller_name:'',role:'secondary',percentage:0})"
                                type="button" class="text-xs text-purple-600 hover:text-purple-700 font-medium">+ Add split</button>
                        <div class="p-2 bg-gray-50 rounded-lg text-xs text-gray-500">
                            Total: <span :class="totalSplitPct() === 100 ? 'text-emerald-600 font-bold' : 'text-orange-600 font-bold'" x-text="totalSplitPct() + '%'"></span>
                            <span x-show="totalSplitPct() !== 100" class="ml-1 text-orange-600">(should equal 100%)</span>
                        </div>
                        <div class="flex gap-2">
                            <button @click="editSplits = false" class="btn-secondary text-xs flex-1">Cancel</button>
                            <button @click="saveSplits()" :disabled="saving" class="btn-primary text-xs flex-1" x-text="saving ? 'Saving…' : 'Save Splits'"></button>
                        </div>
                    </div>
                </div>
            {{-- Partners & Split Share --}}
            <div class="card space-y-3"
                 x-data="partnerSplitSection('{{ $dealId }}', '{{ $tenant->id }}')"
                 x-init="load()">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-[#1E1B4B] text-sm">Partners &amp; Split Share</h3>
                    <button @click="showAdd = !showAdd; if(!showAdd){ clearContact(); formError = '' }" class="text-xs text-purple-600 hover:text-purple-700 font-medium flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add Partner
                    </button>
                </div>
                <p class="text-[11px] text-gray-400">Split Share must be assigned to a Partner. You can add a Partner even if they have not accepted the invitation yet.</p>

                {{-- Loading --}}
                <div x-show="loading" class="flex items-center gap-2 text-gray-400 text-xs py-2">
                    <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Loading…
                </div>

                {{-- Split rows --}}
                <div x-show="!loading" class="space-y-2">
                    <template x-if="splits.length === 0 && !showAdd">
                        <p class="text-xs text-gray-400 py-1">No Partner Split Shares added yet.</p>
                    </template>
                    <template x-for="s in splits" :key="s.id">
                        <div class="flex items-start gap-2.5 py-2 border-b border-gray-50 last:border-0">
                            <div class="w-7 h-7 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 text-xs font-bold shrink-0"
                                 x-text="(s.partner_name||'?').slice(0,2).toUpperCase()"></div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-[#1E1B4B] truncate" x-text="s.partner_name"></p>
                                <p class="text-xs text-gray-400 truncate" x-text="s.partner_email"></p>
                                <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full"
                                      :class="{
                                          'bg-green-100 text-green-700': s.status === 'active',
                                          'bg-yellow-100 text-yellow-700': s.status === 'pending_invite',
                                          'bg-gray-100 text-gray-500': s.status === 'provisional' || s.status === 'invite_failed',
                                      }"
                                      x-text="s.status_label"></span>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-bold text-[#1E1B4B]" x-text="s.display_share"></p>
                                <button @click="removeSplit(s.id)" class="text-[10px] text-red-400 hover:text-red-600 mt-0.5">Remove</button>
                            </div>
                        </div>
                    </template>

                    {{-- Total --}}
                    <div x-show="splits.filter(s => s.split_share_type === 'percentage').length > 0"
                         class="flex justify-between text-xs py-1 border-t border-gray-100 mt-1">
                        <span class="text-gray-400">Total Partner %</span>
                        <span :class="totalPct > 100 ? 'text-red-600 font-bold' : 'text-gray-700 font-medium'" x-text="totalPct + '%'"></span>
                    </div>
                </div>

                {{-- Add form --}}
                <div x-show="showAdd" class="space-y-2.5 pt-2 border-t border-gray-100">
                    <p class="text-xs font-medium text-[#1E1B4B]">Add Partner Split</p>

                    {{-- Contact combobox --}}
                    <div x-show="!contactSelected" class="relative" @click.outside="contactOpen = false">
                        <div class="relative">
                            <input type="text"
                                   x-model="contactQuery"
                                   @input.debounce.300ms="contactOpen = true; searchContacts()"
                                   @keydown.escape="contactOpen = false"
                                   @keydown.arrow-down.prevent="contactFocusIdx = Math.min(contactFocusIdx + 1, contactOptions.length - 1)"
                                   @keydown.arrow-up.prevent="contactFocusIdx = Math.max(contactFocusIdx - 1, -1)"
                                   @keydown.enter.prevent="if(contactFocusIdx >= 0 && contactOptions[contactFocusIdx]) selectContact(contactOptions[contactFocusIdx])"
                                   placeholder="Search contacts by name or email…"
                                   autocomplete="off"
                                   class="form-input text-xs pr-7">
                            <div class="absolute right-2.5 top-1/2 -translate-y-1/2 cursor-pointer"
                                 @click="contactOpen = true; searchContacts()" title="Search contacts">
                                <svg x-show="!loadingContacts" class="w-3.5 h-3.5 text-gray-400 hover:text-purple-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                <svg x-show="loadingContacts" class="w-3.5 h-3.5 text-gray-400 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            </div>
                        </div>
                        <div x-show="contactOpen"
                             class="absolute z-50 w-full mt-1 bg-white rounded-xl shadow-xl border border-gray-100 max-h-44 overflow-y-auto"
                             style="display:none">
                            <div x-show="loadingContacts" class="flex items-center gap-2 px-3 py-2.5 text-xs text-gray-400">
                                <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                Loading contacts…
                            </div>
                            <div x-show="!loadingContacts && contactLoadError" class="px-3 py-2.5">
                                <p class="text-xs text-red-500" x-text="contactLoadError"></p>
                                <button type="button" @click="searchContacts()" class="text-xs text-purple-600 hover:underline mt-0.5">Try again</button>
                            </div>
                            <div x-show="!loadingContacts && !contactLoadError">
                                <template x-for="(c, idx) in contactOptions" :key="c.id">
                                    <button type="button"
                                            @click="selectContact(c)"
                                            :class="contactFocusIdx === idx ? 'bg-[#F0EFFA]' : 'hover:bg-gray-50'"
                                            class="w-full flex items-center gap-2.5 px-3 py-2 text-left transition-colors">
                                        <div class="w-7 h-7 rounded-full bg-purple-100 flex items-center justify-center text-purple-700 text-xs font-bold shrink-0"
                                             x-text="([c.first_name, c.last_name].filter(Boolean).join(' ') || c.name || '?').slice(0,2).toUpperCase()"></div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-medium text-[#1E1B4B] truncate" x-text="[c.first_name, c.last_name].filter(Boolean).join(' ') || c.name || '—'"></p>
                                            <p class="text-[10px] text-gray-400 truncate" x-text="c.email || c.job_title || ''"></p>
                                        </div>
                                    </button>
                                </template>
                                <div x-show="contactOptions.length === 0 && !loadingContacts && !contactLoadError"
                                     class="px-3 py-4 text-center text-xs text-gray-400"
                                     x-text="contactQuery ? 'No matching contacts.' : 'Click 🔍 or type to search contacts.'"></div>
                            </div>
                        </div>
                    </div>

                    {{-- Selected contact pill --}}
                    <div x-show="contactSelected" class="flex items-center gap-2 p-2 border border-purple-200 rounded-xl bg-purple-50">
                        <div class="w-6 h-6 rounded-full bg-purple-200 flex items-center justify-center text-purple-700 text-[10px] font-bold shrink-0"
                             x-text="([contactSelected?.first_name, contactSelected?.last_name].filter(Boolean).join(' ') || contactSelected?.name || '?').slice(0,2).toUpperCase()"></div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-[#1E1B4B] truncate" x-text="[contactSelected?.first_name, contactSelected?.last_name].filter(Boolean).join(' ') || contactSelected?.name || '—'"></p>
                            <p class="text-[10px] text-gray-400 truncate" x-text="contactSelected?.email || ''"></p>
                        </div>
                        <button type="button" @click="clearContact()" class="text-gray-400 hover:text-gray-600 shrink-0">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    {{-- Share amount row --}}
                    <div class="space-y-1.5">
                        <div class="flex gap-2">
                            <div class="relative flex-1">
                                <input type="number"
                                       x-model.number="form.split_share_value"
                                       class="form-input text-sm pr-8"
                                       placeholder="0"
                                       min="0"
                                       :max="form.split_share_type === 'percentage' ? 100 : null">
                                <span class="absolute inset-y-0 right-3 flex items-center text-xs font-semibold text-gray-400 pointer-events-none"
                                      x-text="form.split_share_type === 'percentage' ? '%' : '₱'"></span>
                            </div>
                            <select x-model="form.split_share_type" class="form-input text-xs w-32 shrink-0">
                                <option value="percentage">Percentage</option>
                                <option value="fixed_amount">Fixed Amount</option>
                            </select>
                        </div>
                        {{-- Exact peso amount hint --}}
                        <p x-show="form.split_share_type === 'percentage' && form.split_share_value > 0 && dealValue > 0"
                           class="text-xs text-purple-600 font-medium flex items-center gap-1">
                            <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            <span x-text="'= ₱' + (dealValue * form.split_share_value / 100).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                        </p>
                        <p x-show="form.split_share_type === 'fixed_amount' && form.split_share_value > 0 && dealValue > 0"
                           class="text-xs text-gray-400 flex items-center gap-1">
                            <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span x-text="'≈ ' + (form.split_share_value / dealValue * 100).toFixed(1) + '% of deal value'"></span>
                        </p>
                    </div>
                    <p x-show="formError" class="text-xs text-red-600" x-text="formError"></p>
                    <div class="flex gap-2">
                        <button @click="showAdd = false; clearContact(); formError = ''" class="btn-secondary text-xs flex-1">Cancel</button>
                        <button @click="addSplit()" :disabled="saving" class="btn-primary text-xs flex-1" x-text="saving ? 'Saving…' : 'Add Split'"></button>
                    </div>
                </div>
            </div>

            {{-- Extension Request (LGU IDS) --}}
            <div class="card space-y-3"
                 x-data="extensionRequestSection('{{ $dealId }}', '{{ $tenant->id }}')"
                 x-init="load()">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-[#1E1B4B] text-sm">Extension Request</h3>
                    <button x-show="!showForm && canRequest" @click="showForm = true"
                            class="text-xs text-purple-600 hover:text-purple-700 font-medium">
                        Request Extension
                    </button>
                </div>

                <div x-show="loading" class="flex items-center gap-2 text-gray-400 text-xs py-1">
                    <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Loading…
                </div>

                <div x-show="!loading" class="space-y-2">
                    {{-- Existing requests list --}}
                    <template x-if="requests.length === 0 && !showForm">
                        <p class="text-xs text-gray-400">No extension requests for this deal.</p>
                    </template>
                    <template x-for="r in requests" :key="r.id">
                        <div class="rounded-xl p-3 text-xs space-y-1"
                             :class="{
                                 'bg-yellow-50 border border-yellow-200': r.status === 'pending_review',
                                 'bg-green-50 border border-green-200':  r.status === 'approved',
                                 'bg-red-50 border border-red-200':      r.status === 'rejected',
                                 'bg-gray-50 border border-gray-200':    !['pending_review','approved','rejected'].includes(r.status),
                             }">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold" x-text="r.status_label ?? r.status"></span>
                                <span class="text-gray-400" x-text="r.requested_days + ' days requested'"></span>
                            </div>
                            <p class="text-gray-600" x-text="r.reason"></p>
                            <p x-show="r.admin_note" class="text-gray-500 italic" x-text="'Admin note: ' + r.admin_note"></p>
                            <p x-show="r.approved_days" class="text-green-700 font-medium" x-text="r.approved_days + ' days approved'"></p>
                        </div>
                    </template>

                    {{-- Request form --}}
                    <div x-show="showForm" class="space-y-2.5 pt-2 border-t border-gray-100">
                        <p class="text-xs font-medium text-[#1E1B4B]">Request Extension of Assignment</p>
                        <select x-model.number="form.requested_days" class="form-input text-xs">
                            <option value="7">7 days</option>
                            <option value="14">14 days</option>
                            <option value="21">21 days</option>
                            <option value="30">30 days</option>
                        </select>
                        <textarea x-model="form.reason" class="form-input text-xs" rows="3"
                                  placeholder="Reason for extension request (required, min 10 characters)…"></textarea>
                        <label class="flex items-start gap-2 text-xs text-gray-500 cursor-pointer">
                            <input type="checkbox" x-model="form.acknowledged" class="mt-0.5">
                            <span>I understand this request requires Tenant Admin approval.</span>
                        </label>
                        <p x-show="formError" class="text-xs text-red-600" x-text="formError"></p>
                        <div class="flex gap-2">
                            <button @click="showForm = false; formError = ''" class="btn-secondary text-xs flex-1">Cancel</button>
                            <button @click="submitRequest()" :disabled="saving || !form.acknowledged"
                                    class="btn-primary text-xs flex-1" x-text="saving ? 'Submitting…' : 'Submit Request'"></button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Contacts on this Deal --}}
            <div class="card space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-[#1E1B4B] text-sm">Contacts</h3>
                    <button @click="openLinkContact()" class="text-xs text-purple-600 hover:text-purple-700 font-medium flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Link
                    </button>
                </div>
                <div x-show="loadingContacts" class="flex items-center gap-2 text-gray-400 text-xs py-2">
                    <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Loading…
                </div>
                <div x-show="!loadingContacts">
                    <template x-if="dealContacts.length === 0">
                        <div class="text-center py-5 rounded-xl border-2 border-dashed border-gray-100">
                            <p class="text-gray-400 text-xs">No contacts linked yet.</p>
                            <button @click="openLinkContact()" class="text-xs text-purple-600 hover:text-purple-700 font-medium mt-1">Link a contact</button>
                        </div>
                    </template>
                    <div x-show="dealContacts.length > 0" class="space-y-1">
                        <template x-for="c in dealContacts" :key="c.id">
                            <div class="flex items-center gap-2.5 py-2 border-b border-gray-50 last:border-0 group">
                                <div class="w-7 h-7 rounded-full bg-purple-100 flex items-center justify-center text-purple-700 text-xs font-bold shrink-0"
                                     x-text="contactInitials(c)"></div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-[#1E1B4B] truncate" x-text="contactFullName(c)"></p>
                                    <p class="text-xs text-gray-400 truncate">
                                        <span x-show="c.deal_role" x-text="c.deal_role + ' · '"></span>
                                        <span x-text="c.org_name || c.job_title || c.email || ''"></span>
                                    </p>
                                </div>
                                <button @click="unlinkContact(c.id)"
                                        class="p-1 rounded text-gray-300 hover:text-red-400 hover:bg-red-50 transition-colors opacity-0 group-hover:opacity-100"
                                        title="Unlink">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
            </div>

            {{-- Right: Notes + Activity --}}
            <div class="lg:col-span-2 space-y-4">

                {{-- Notes --}}
                <div class="card space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-[#1E1B4B] text-sm">Notes</h3>
                        <button @click="showNoteForm = !showNoteForm" class="text-xs text-purple-600 hover:text-purple-700 font-medium">+ Add Note</button>
                    </div>
                    <div x-show="showNoteForm" class="space-y-2 p-3 bg-[#F0EFFA] rounded-xl">
                        <textarea x-model="noteText" rows="3" class="form-input text-sm" placeholder="Add a note…" autofocus></textarea>
                        <div class="flex justify-end gap-2">
                            <button @click="showNoteForm = false; noteText=''; noteAuthor=''" class="btn-secondary text-xs">Cancel</button>
                            <button @click="addNote()" :disabled="saving || !noteText" class="btn-primary text-xs" x-text="saving ? 'Saving…' : 'Save Note'"></button>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <template x-if="(lead?.notes||[]).length === 0">
                            <div class="text-center py-6">
                                <svg class="w-8 h-8 text-gray-200 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <p class="text-gray-400 text-sm">No notes yet.</p>
                                <button @click="showNoteForm = true" class="text-xs text-purple-600 hover:underline mt-1">Add the first note</button>
                            </div>
                        </template>
                        <template x-for="note in (lead?.notes||[])" :key="note.id">
                            <div class="p-3 bg-gray-50 rounded-xl">
                                <p class="text-sm text-gray-700" x-text="note.text"></p>
                                <p class="text-xs text-gray-400 mt-1.5">
                                    <span x-text="note.author || 'Unknown'"></span> ·
                                    <span x-text="note.created_at ? new Date(note.created_at).toLocaleDateString('en',{month:'short',day:'numeric'}) : ''"></span>
                                </p>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Activity History --}}
                <div class="card space-y-3">
                    <h3 class="font-semibold text-[#1E1B4B] text-sm">Activity History</h3>
                    <div class="space-y-3">
                        <template x-if="(lead?.history||[]).length === 0">
                            <div class="text-center py-6">
                                <svg class="w-8 h-8 text-gray-200 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <p class="text-gray-400 text-sm">No activity yet.</p>
                            </div>
                        </template>
                        <template x-for="(event, ei) in [...(lead?.history||[])].reverse()" :key="event.id">
                            <div class="flex gap-3">
                                <div class="flex flex-col items-center shrink-0">
                                    <div :class="{
                                        'bg-purple-100 text-purple-600':  event.type === 'stage',
                                        'bg-blue-100 text-blue-600':      event.type === 'assignment',
                                        'bg-emerald-100 text-emerald-600':event.type === 'commission',
                                        'bg-amber-100 text-amber-600':    event.type === 'financial',
                                        'bg-gray-100 text-gray-500':      !event.type || event.type === 'notes',
                                    }" class="w-7 h-7 rounded-full flex items-center justify-center shrink-0">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <template x-if="event.type === 'stage'"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></template>
                                            <template x-if="event.type === 'assignment'"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></template>
                                            <template x-if="event.type === 'commission'"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2"/></template>
                                            <template x-if="event.type === 'financial'"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></template>
                                            <template x-if="!event.type || event.type === 'notes'"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></template>
                                        </svg>
                                    </div>
                                    <div x-show="ei < (lead?.history||[]).length - 1" class="w-px flex-1 bg-gray-100 mt-1"></div>
                                </div>
                                <div class="pb-3 flex-1 min-w-0">
                                    <p class="text-sm text-gray-700" x-text="event.action"></p>
                                    <p class="text-xs text-gray-400 mt-0.5">
                                        <span x-show="event.reseller" x-text="event.reseller + ' · '"></span>
                                        <span x-text="event.date ? new Date(event.date).toLocaleDateString('en',{month:'short',day:'numeric',year:'numeric'}) : ''"></span>
                                    </p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

            </div>
        </div>

    </div>

    {{-- Link Contact Modal --}}
    <div x-show=”showLinkContact” x-cloak
         class=”fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4”
         @keydown.escape.window=”showLinkContact = false”>
        <div class=”bg-white rounded-2xl shadow-xl w-full max-w-md” @click.stop>
            <div class=”flex items-center justify-between px-6 py-4 border-b border-gray-100”>
                <h3 class=”font-semibold text-[#1E1B4B]”>Link Contact to Deal</h3>
                <button @click=”showLinkContact = false; linkSearch = ''” class=”text-gray-400 hover:text-gray-600 transition-colors”>
                    <svg class=”w-5 h-5” fill=”none” stroke=”currentColor” viewBox=”0 0 24 24”><path stroke-linecap=”round” stroke-linejoin=”round” stroke-width=”2” d=”M6 18L18 6M6 6l12 12”/></svg>
                </button>
            </div>
            <div class=”p-5 space-y-3”>
                <div class=”search-group”>
                    <svg @click=”if(!allTenantContacts.length) fetchAllContacts()”
                         class=”cursor-pointer hover:text-purple-600 transition-colors”
                         fill=”none” stroke=”currentColor” viewBox=”0 0 24 24”>
                        <path stroke-linecap=”round” stroke-linejoin=”round” stroke-width=”2” d=”M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z”/>
                    </svg>
                    <input type=”text” x-model=”linkSearch”
                           @input.debounce.200ms=”if(!allTenantContacts.length) fetchAllContacts()”
                           placeholder=”Click 🔍 or type to search contacts…” autofocus>
                </div>
                <div class=”max-h-72 overflow-y-auto space-y-1”>
                    <template x-if=”allTenantContacts.length === 0”>
                        <p class=”text-center text-gray-400 text-sm py-6”>Click the search icon or start typing to load contacts.</p>
                    </template>
                    <template x-if=”allTenantContacts.length > 0 && linkableContacts().length === 0”>
                        <p class=”text-center text-gray-400 text-sm py-6”>
                            <span x-text=”'No matching contacts.'”></span>
                        </p>
                    </template>
                    <template x-for=”c in linkableContacts()” :key=”c.id”>
                        <button @click=”linkContact(c)”
                                :disabled=”linkSaving”
                                class=”flex items-center gap-3 w-full px-3 py-2.5 rounded-xl hover:bg-[#F0EFFA] transition-colors text-left group”>
                            <div class=”w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-purple-700 text-xs font-bold shrink-0”
                                 x-text=”contactInitials(c)”></div>
                            <div class=”flex-1 min-w-0”>
                                <p class=”text-sm font-medium text-[#1E1B4B] truncate” x-text=”contactFullName(c)”></p>
                                <p class=”text-xs text-gray-400 truncate” x-text=”[c.job_title, c.org_name].filter(Boolean).join(' · ') || c.email || ''”></p>
                            </div>
                            <svg class=”w-4 h-4 text-purple-400 opacity-0 group-hover:opacity-100 shrink-0” fill=”none” stroke=”currentColor” viewBox=”0 0 24 24”><path stroke-linecap=”round” stroke-linejoin=”round” stroke-width=”2” d=”M12 4v16m8-8H4”/></svg>
                        </button>
                    </template>
                </div>
                <p class=”text-xs text-gray-400 pt-1”>
                    Can't find the contact?
                    <a href=”{{ route('tenant.contacts', $tenant->id) }}” class=”text-purple-600 hover:underline”>Add them first</a>
                    in the Contacts module.
                </p>
            </div>
        </div>
    </div>

    {{-- ── Move Stage Modal ── --}}
    <div x-show=”showMoveStage” x-cloak
         class=”fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4”
         @keydown.escape.window=”showMoveStage = false; moveStageNote = ''”>
        <div class=”bg-white rounded-2xl shadow-xl w-full max-w-sm” @click.stop>
            <div class=”flex items-center justify-between px-6 py-4 border-b border-gray-100”>
                <div>
                    <h3 class=”font-semibold text-[#1E1B4B]”>Move Stage</h3>
                    <p class=”text-xs text-gray-400 mt-0.5”>
                        <span x-text=”lead?.name”></span>
                    </p>
                </div>
                <button @click=”showMoveStage = false; moveStageNote = ''” class=”text-gray-400 hover:text-gray-600”>
                    <svg class=”w-5 h-5” fill=”none” stroke=”currentColor” viewBox=”0 0 24 24”><path stroke-linecap=”round” stroke-linejoin=”round” stroke-width=”2” d=”M6 18L18 6M6 6l12 12”/></svg>
                </button>
            </div>
            <div class=”p-6 space-y-4”>
                <p class=”text-sm text-gray-500”>
                    Current stage: <span class=”font-semibold text-[#1E1B4B]” x-text=”stageLabel(lead?.stage)”></span>
                </p>
                <div class=”space-y-2”>
                    <template x-for=”s in allStages” :key=”s.key”>
                        <button @click=”moveToStage(s.key)”
                                :disabled=”s.key === lead?.stage || saving”
                                :class=”s.key === lead?.stage
                                    ? 'opacity-50 cursor-not-allowed bg-gray-50 border-gray-100'
                                    : 'hover:bg-[#F0EFFA] hover:border-purple-200 cursor-pointer'”
                                class=”w-full flex items-center gap-3 px-4 py-3 rounded-xl border border-gray-100 transition-all text-left”>
                            <span :class=”stageBadge(s.key)” class=”shrink-0 text-xs” x-text=”s.label”></span>
                            <span x-show=”s.key === 'signed'” class=”text-xs text-orange-600”>→ locks commission at <span x-text=”fmt(commPool())”></span></span>
                            <span x-show=”s.key === 'paid'”   class=”text-xs text-emerald-600”>→ marks <span x-text=”fmt(commPool())”></span> paid</span>
                            <span x-show=”s.key === lead?.stage” class=”ml-auto text-xs text-gray-400”>current</span>
                            <svg x-show=”saving && s.key !== lead?.stage” class=”w-3.5 h-3.5 animate-spin text-purple-400 ml-auto shrink-0” fill=”none” viewBox=”0 0 24 24”><circle class=”opacity-25” cx=”12” cy=”12” r=”10” stroke=”currentColor” stroke-width=”4”/><path class=”opacity-75” fill=”currentColor” d=”M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z”/></svg>
                        </button>
                    </template>
                </div>
                <div>
                    <label class=”form-label”>Note <span class=”text-gray-400 font-normal”>(optional)</span></label>
                    <textarea x-model=”moveStageNote” rows=”2” class=”form-input text-sm resize-none”
                              placeholder=”Reason for stage movement, e.g. 'Proposal sent to procurement office'…”></textarea>
                </div>
                <p class=”text-xs text-gray-400”>The note will be saved to the activity history.</p>
            </div>
        </div>
    </div>

    {{-- â”€â”€ Reassign Modal â”€â”€ --}}
    <div x-show="showReassign" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 space-y-4" @click.stop>
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-[#1E1B4B]">Reassign Record</h3>
                <button @click="showReassign = false; reassignName = ''" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800">
                This resets the stage to Introduction, restarts the pipeline timer, and transfers 100% of the commission pool to the new referrer.
            </div>
            <div>
                <label class="form-label">New Referrer Name *</label>
                <input type="text" x-model="reassignName" class="form-input" placeholder="Referrer full name">
            </div>
            <div class="flex justify-end gap-3">
                <button @click="showReassign = false; reassignName = ''" class="btn-secondary">Cancel</button>
                <button @click="reassign()" :disabled="!reassignName || saving" class="btn-primary" x-text="saving ? 'Reassigning…' : 'Confirm Reassign'"></button>
            </div>
        </div>
    </div>

    {{-- ── Deal Comments ────────────────────────────────────────────────── --}}
    <div x-show="!loading && lead"
         x-data="dealComments('{{ $dealId }}', '{{ $tenant->id }}')"
         x-init="loadComments()"
         class="card space-y-4">

        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
            <h3 class="font-semibold text-[#1E1B4B] text-sm flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                Comments
                <span class="text-gray-400 font-normal" x-text="'(' + comments.length + ')'"></span>
            </h3>
            <div class="flex items-center gap-2">
                <label class="filter-pill text-xs" x-show="canPostInternal">
                    <select x-model="newVisibility" class="text-xs">
                        <option value="shared">Shared with participants</option>
                        <option value="internal_admin">Internal admin note</option>
                    </select>
                </label>
            </div>
        </div>

        {{-- Composer --}}
        <div class="flex gap-3">
            <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-purple-700 text-xs font-bold shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </div>
            <div class="flex-1 space-y-2">
                <textarea x-model="newBody" rows="2"
                          class="form-input text-sm resize-none"
                          :placeholder="newVisibility === 'internal_admin' ? 'Internal note — only visible to Tenant Admins and permitted Managers…' : 'Write a comment about this deal…'"></textarea>
                <div class="flex justify-between items-center">
                    <p x-show="commentError" class="text-xs text-red-500" x-text="commentError"></p>
                    <div class="ml-auto">
                        <button @click="postComment()" :disabled="!newBody.trim() || posting"
                                class="btn-primary text-xs py-1.5 px-3"
                                x-text="posting ? 'Posting…' : 'Post Comment'"></button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Comments list --}}
        <div x-show="loadingComments" class="flex items-center gap-2 text-gray-400 text-sm py-4 justify-center">
            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            Loading comments…
        </div>

        <div x-show="!loadingComments && comments.length === 0"
             class="text-center text-gray-400 text-sm py-6">
            No comments yet. Start the discussion.
        </div>

        <div x-show="!loadingComments && comments.length > 0" class="space-y-4">
            <template x-for="c in comments" :key="c.id">
                <div class="flex gap-3 group/comment">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0 mt-0.5"
                         :class="c.author_role === 'referrer' ? 'bg-blue-100 text-blue-700' : c.author_role === 'partner' ? 'bg-orange-100 text-orange-700' : 'bg-purple-100 text-purple-700'"
                         x-text="(c.author_name||'?').slice(0,2).toUpperCase()"></div>
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <span class="text-sm font-semibold text-[#1E1B4B]" x-text="c.author_name"></span>
                            <span class="text-[10px] text-gray-400 capitalize" x-text="c.author_role.replace('_',' ')"></span>
                            <template x-if="c.is_internal">
                                <span class="px-1.5 py-0 rounded text-[10px] font-bold bg-gray-100 text-gray-500">Internal</span>
                            </template>
                            <span class="text-[10px] text-gray-300" x-text="c.created_at ? new Date(c.created_at).toLocaleString('en',{month:'short',day:'numeric',hour:'numeric',minute:'2-digit'}) : ''"></span>
                            <span x-show="c.edited_at" class="text-[10px] text-gray-300 italic">edited</span>
                        </div>
                        <template x-if="c.is_deleted">
                            <p class="text-sm text-gray-300 italic">This comment was deleted.</p>
                        </template>
                        <template x-if="!c.is_deleted">
                            <div>
                                <p class="text-sm text-gray-700 whitespace-pre-wrap" x-text="c.body"></p>
                                {{-- Edit/Delete actions --}}
                                <div class="mt-1 flex items-center gap-2 opacity-0 group-hover/comment:opacity-100 transition-opacity">
                                    <button @click="startEdit(c)"
                                            class="text-[11px] text-gray-400 hover:text-[#7B61FF] transition-colors">Edit</button>
                                    <button @click="deleteComment(c)"
                                            class="text-[11px] text-gray-400 hover:text-red-500 transition-colors">Delete</button>
                                </div>
                                {{-- Inline edit --}}
                                <div x-show="editingId === c.id" class="mt-2 space-y-2">
                                    <textarea x-model="editBody" rows="2" class="form-input text-sm resize-none"></textarea>
                                    <div class="flex gap-2">
                                        <button @click="saveEdit(c)" :disabled="posting" class="btn-primary text-xs py-1 px-2.5">Save</button>
                                        <button @click="editingId=null" class="btn-secondary text-xs py-1 px-2.5">Cancel</button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>

</div>

<script>
function dealComments(dealId, tenantId) {
    return {
        comments: [], loadingComments: true, posting: false,
        newBody: '', newVisibility: 'shared', commentError: '',
        editingId: null, editBody: '',
        canPostInternal: true, // Tenant admin default; API enforces actual permission

        async loadComments() {
            this.loadingComments = true;
            try {
                const res = await fetch(`/api/deals/${dealId}/comments`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                this.comments = Array.isArray(data) ? data : [];
            } catch(e) { this.comments = []; }
            this.loadingComments = false;
        },

        async postComment() {
            if (!this.newBody.trim()) return;
            this.posting = true; this.commentError = '';
            try {
                const csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
                const res  = await fetch(`/api/deals/${dealId}/comments`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ body: this.newBody, visibility: this.newVisibility }),
                });
                const data = await res.json();
                if (data.id) {
                    this.comments.unshift(data);
                    this.newBody = '';
                } else {
                    this.commentError = data.error || 'Failed to post comment.';
                }
            } catch(e) { this.commentError = 'Network error.'; }
            this.posting = false;
        },

        startEdit(c) {
            this.editingId = c.id;
            this.editBody  = c.body;
        },

        async saveEdit(c) {
            if (!this.editBody.trim()) return;
            this.posting = true;
            try {
                const csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
                const res  = await fetch(`/api/deals/${dealId}/comments/${c.id}`, {
                    method: 'PATCH',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ body: this.editBody }),
                });
                const data = await res.json();
                if (data.id) {
                    const idx = this.comments.findIndex(x => x.id === c.id);
                    if (idx !== -1) this.comments.splice(idx, 1, data);
                    this.editingId = null;
                }
            } catch(e) {}
            this.posting = false;
        },

        async deleteComment(c) {
            if (!confirm('Delete this comment?')) return;
            try {
                const csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
                await fetch(`/api/deals/${dealId}/comments/${c.id}`, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                });
                const idx = this.comments.findIndex(x => x.id === c.id);
                if (idx !== -1) this.comments[idx].is_deleted = true;
            } catch(e) {}
        },
    };
}

function dealDetail(leadId, tenantId) {
    return {
        lead: null, loading: true,
        showNoteForm: false, showMoveStage: false, showReassign: false,
        noteText: '', noteAuthor: '', saving: false, reassignName: '',
        moveStageNote: '',
        editFinance: false,
        financeForm: { base_cost: 0, added_amount: 0 },
        editSplits: false,
        splitsForm: [],

        // Contacts
        dealContacts: [], loadingContacts: true,
        allTenantContacts: [],
        showLinkContact: false, linkSearch: '', linkSaving: false,

        allStages: [
            { key: 'introduction',  label: 'Introduction'  },
            { key: 'presentation',  label: 'Presentation'  },
            { key: 'contract_sent', label: 'Contract Sent' },
            { key: 'signed',        label: 'Signed'        },
            { key: 'paid',          label: 'Paid'          },
        ],

        async init() {
            const res = await fetch(`/api/leads/${leadId}`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            this.lead = await res.json();
            this.loading = false;
            this.fetchContacts();
        },

        // â”€â”€ Financial helpers â”€â”€
        contractValue() {
            const bc = Number(this.lead?.base_cost    || 0);
            const aa = Number(this.lead?.added_amount || 0);
            return (bc + aa) || Number(this.lead?.deal_value || 0);
        },
        companyShare() { return Number(this.lead?.added_amount || 0) * 0.30; },
        commPool()     { return Number(this.lead?.added_amount || 0) * 0.70; },

        previewContract()     { return (Number(this.financeForm.base_cost)||0) + (Number(this.financeForm.added_amount)||0); },
        previewCompanyShare() { return (Number(this.financeForm.added_amount)||0) * 0.30; },
        previewCommPool()     { return (Number(this.financeForm.added_amount)||0) * 0.70; },
        recalc() { /* reactivity happens automatically via x-model.number */ },

        fmt(v) {
            const n = Math.round(Number(v) || 0);
            if (n === 0) return '—';
            return '₱' + n.toLocaleString('en');
        },

        startEditFinance() {
            this.financeForm = {
                base_cost:    Number(this.lead?.base_cost    || 0),
                added_amount: Number(this.lead?.added_amount || 0),
            };
            this.editFinance = true;
        },

        cancelEditFinance() { this.editFinance = false; },

        async saveFinance() {
            this.saving = true;
            try {
                const bc   = Number(this.financeForm.base_cost)    || 0;
                const aa   = Number(this.financeForm.added_amount) || 0;
                const csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
                const res  = await fetch(`/api/leads/${this.lead.id}`, {
                    method: 'PATCH',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ base_cost: bc, added_amount: aa, deal_value: bc + aa }),
                });
                const updated = await res.json();
                if (updated.id) {
                    this.lead = { ...this.lead, ...updated };
                    this.editFinance = false;
                    this.$dispatch('show-toast', { type: 'success', message: 'Financial data saved.' });
                } else {
                    this.$dispatch('show-toast', { type: 'error', message: updated.message || 'Failed to save financial data.' });
                }
            } catch(e) {
                this.$dispatch('show-toast', { type: 'error', message: 'Network error. Please try again.' });
            } finally { this.saving = false; }
        },

        // ── Days-left helpers (avoid < > in :class attributes — breaks HTML parsing) ──
        headerStatusBorderClass() {
            const s = this.lead?.status;
            if (s === 'active')   return 'border-emerald-400';
            if (s === 'expiring') return 'border-amber-400';
            if (s === 'expired')  return 'border-red-500';
            return 'border-gray-200';
        },
        daysLeftBadgeClass() {
            const d = this.lead?.days_left;
            if (d == null)  return 'bg-gray-100 text-gray-600';
            if (d <= 3)     return 'bg-red-100 text-red-700';
            if (d <= 7)     return 'bg-amber-100 text-amber-700';
            return 'bg-gray-100 text-gray-600';
        },
        daysLeftTextClass() {
            const d = this.lead?.days_left;
            if (d == null) return 'text-gray-700';
            if (d <= 3)    return 'text-red-600';
            if (d <= 7)    return 'text-amber-600';
            return 'text-gray-700';
        },

        // â”€â”€ Stage helpers â”€â”€
        stageIdx(key) { return this.allStages.findIndex(s => s.key === key); },
        isStageDone(key) { return this.stageIdx(key) < this.stageIdx(this.lead?.stage); },
        stageCircleClass(key) {
            const cur  = key === this.lead?.stage;
            const done = this.isStageDone(key);
            if (done) return 'bg-[#7B61FF] text-white';
            if (cur)  return 'bg-[#7B61FF] text-white ring-4 ring-purple-200';
            return 'bg-gray-100 text-gray-400';
        },
        stageLabel(s) {
            const m = { introduction:'Introduction', presentation:'Presentation', contract_sent:'Contract Sent', signed:'Signed', paid:'Paid' };
            return m[s] || (s || '—');
        },
        stageBadge(s) {
            const m = { introduction:'badge badge-gray', presentation:'badge badge-blue', contract_sent:'badge badge-orange', signed:'badge badge-purple', paid:'badge badge-green' };
            return m[s] || 'badge badge-gray';
        },

        async moveToStage(stage) {
            if (stage === this.lead?.stage) return;
            this.saving = true;
            try {
                const csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
                const res = await fetch(`/api/leads/${this.lead.id}/stage`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ stage, note: this.moveStageNote }),
                });
                const updated = await res.json();
                if (updated.id) {
                    this.lead = { ...this.lead, ...updated, history: updated.history, commission_splits: updated.commission_splits };
                    this.showMoveStage = false;
                    this.moveStageNote = '';
                    this.$dispatch('show-toast', { type: 'success', message: 'Stage updated.' });
                } else {
                    this.$dispatch('show-toast', { type: 'error', message: updated.message || 'Failed to move stage.' });
                }
            } catch(e) {
                this.$dispatch('show-toast', { type: 'error', message: 'Network error. Please try again.' });
            } finally { this.saving = false; }
        },

        async addNote() {
            if (!this.noteText) return;
            this.saving = true;
            try {
                const csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
                const res  = await fetch(`/api/leads/${this.lead.id}/notes`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ text: this.noteText, author: this.noteAuthor || 'Admin' }),
                });
                const note = await res.json();
                if (note.id) {
                    if (!this.lead.notes) this.lead.notes = [];
                    this.lead.notes.push(note);
                    this.noteText = ''; this.noteAuthor = ''; this.showNoteForm = false;
                    this.$dispatch('show-toast', { type: 'success', message: 'Note saved.' });
                } else {
                    this.$dispatch('show-toast', { type: 'error', message: note.message || 'Failed to save note.' });
                }
            } catch(e) {
                this.$dispatch('show-toast', { type: 'error', message: 'Network error. Please try again.' });
            } finally { this.saving = false; }
        },

        async reassign() {
            if (!this.reassignName) return;
            this.saving = true;
            try {
                const csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
                const res  = await fetch(`/api/leads/${this.lead.id}/reassign`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ reseller_name: this.reassignName }),
                });
                const updated = await res.json();
                if (updated.id) {
                    this.lead = updated;
                    this.reassignName = ''; this.showReassign = false;
                    this.$dispatch('show-toast', { type: 'success', message: 'Deal reassigned.' });
                } else {
                    this.$dispatch('show-toast', { type: 'error', message: updated.message || 'Failed to reassign.' });
                }
            } catch(e) {
                this.$dispatch('show-toast', { type: 'error', message: 'Network error. Please try again.' });
            } finally { this.saving = false; }
        },

        // ── Contact helpers ──────────────────────────────────────────────
        contactFullName(c) { return [c.first_name, c.last_name].filter(Boolean).join(' ') || '—'; },
        contactInitials(c) {
            const p = [c.first_name, c.last_name].filter(Boolean);
            return p.length ? p.map(n => n[0]).join('').toUpperCase() : '?';
        },

        async fetchContacts() {
            this.loadingContacts = true;
            try {
                const res = await fetch(`/api/deals/${leadId}/contacts`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                this.dealContacts = Array.isArray(data) ? data : [];
            } catch(e) { this.dealContacts = []; }
            this.loadingContacts = false;
        },

        async fetchAllContacts() {
            try {
                const res = await fetch(`/api/contacts?tenant_id=${tenantId}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                this.allTenantContacts = Array.isArray(data) ? data : [];
            } catch(e) { this.allTenantContacts = []; }
        },

        openLinkContact() {
            this.linkSearch = '';
            this.showLinkContact = true;
        },

        linkableContacts() {
            const linked = new Set(this.dealContacts.map(c => c.id));
            const q = this.linkSearch.toLowerCase();
            return this.allTenantContacts.filter(c => {
                if (linked.has(c.id)) return false;
                const name = this.contactFullName(c).toLowerCase();
                return !q || name.includes(q) || (c.email||'').toLowerCase().includes(q) || (c.org_name||'').toLowerCase().includes(q);
            });
        },

        async linkContact(contact) {
            this.linkSaving = true;
            try {
                const csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
                const res = await fetch(`/api/deals/${leadId}/contacts`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ contact_id: contact.id, tenant_id: tenantId }),
                });
                const data = await res.json();
                if (data.id) {
                    this.dealContacts.push(data);
                    this.showLinkContact = false;
                    this.linkSearch = '';
                    this.$dispatch('show-toast', { type: 'success', message: `${this.contactFullName(contact)} linked to deal.` });
                } else {
                    this.$dispatch('show-toast', { type: 'error', message: data.error || 'Failed to link contact.' });
                }
            } catch(e) { this.$dispatch('show-toast', { type: 'error', message: 'Network error.' }); }
            finally { this.linkSaving = false; }
        },

        async unlinkContact(contactId) {
            try {
                const csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
                await fetch(`/api/deals/${leadId}/contacts/${contactId}`, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                });
                this.dealContacts = this.dealContacts.filter(c => c.id !== contactId);
                this.$dispatch('show-toast', { type: 'success', message: 'Contact unlinked.' });
            } catch(e) { this.$dispatch('show-toast', { type: 'error', message: 'Failed to unlink contact.' }); }
        },

        openEditSplits() {
            this.splitsForm = (this.lead?.commission_splits || []).map(s => ({
                reseller_name: s.reseller_name || '',
                role: s.role || 'primary',
                percentage: Number(s.percentage) || 0,
            }));
            if (this.splitsForm.length === 0 && this.lead?.reseller_name) {
                this.splitsForm.push({ reseller_name: this.lead.reseller_name, role: 'primary', percentage: 100 });
            }
            this.editSplits = true;
        },

        totalSplitPct() {
            return this.splitsForm.reduce((s, sp) => s + (Number(sp.percentage) || 0), 0);
        },

        async saveSplits() {
            this.saving = true;
            try {
                const csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
                const res  = await fetch(`/api/leads/${this.lead.id}/commission-splits`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ splits: this.splitsForm }),
                });
                const updated = await res.json();
                if (updated.commission_splits) {
                    this.lead = { ...this.lead, commission_splits: updated.commission_splits };
                    this.editSplits = false;
                    this.$dispatch('show-toast', { type: 'success', message: 'Commission splits saved.' });
                } else {
                    this.$dispatch('show-toast', { type: 'error', message: updated.message || 'Failed to save splits.' });
                }
            } catch(e) {
                this.$dispatch('show-toast', { type: 'error', message: 'Network error. Please try again.' });
            } finally { this.saving = false; }
        },
    }
}

// ── Partner Split Section ─────────────────────────────────────────────────
function partnerSplitSection(dealId, tenantId) {
    return {
        splits: [], loading: true, showAdd: false, saving: false, formError: '',
        totalPct: 0,
        dealValue: 0,
        form: { partner_name: '', partner_email: '', split_share_value: 0, split_share_type: 'percentage' },
        // Contact combobox
        contactQuery: '', contactOpen: false, contactSelected: null,
        contactOptions: [], loadingContacts: false, contactLoadError: '', contactFocusIdx: -1,

        async searchContacts() {
            this.loadingContacts = true;
            this.contactLoadError = '';
            try {
                const q = encodeURIComponent(this.contactQuery || '');
                const res = await fetch(`/api/contacts?tenant_id=${tenantId}&search=${q}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) throw new Error('Server error');
                const data = await res.json();
                this.contactOptions = Array.isArray(data) ? data : (data.data || []);
                this.contactFocusIdx = -1;
            } catch(e) {
                this.contactLoadError = 'Unable to load contacts. Try again.';
                this.contactOptions = [];
            }
            this.loadingContacts = false;
        },

        selectContact(c) {
            this.contactSelected = c;
            const fullName = [c.first_name, c.last_name].filter(Boolean).join(' ') || c.name || '';
            this.form.partner_name  = fullName;
            this.form.partner_email = c.email || '';
            this.contactQuery = fullName;
            this.contactOpen  = false;
        },

        clearContact() {
            this.contactSelected   = null;
            this.form.partner_name  = '';
            this.form.partner_email = '';
            this.contactQuery  = '';
            this.contactOpen   = false;
            this.contactOptions = [];
        },

        async load() {
            this.loading = true;
            try {
                const [splitsRes, leadRes] = await Promise.all([
                    fetch(`/api/leads/${dealId}/partner-splits?tenant_id=${tenantId}`, {
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    }),
                    fetch(`/api/leads/${dealId}?tenant_id=${tenantId}`, {
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    }),
                ]);
                const data = await splitsRes.json();
                this.splits   = data.splits   || [];
                this.totalPct = data.total_percentage || 0;
                if (leadRes.ok) {
                    const lead = await leadRes.json();
                    const bc   = Number(lead.base_cost    || 0);
                    const aa   = Number(lead.added_amount || 0);
                    this.dealValue = (bc + aa) || Number(lead.deal_value || 0);
                }
            } catch(e) { this.splits = []; }
            this.loading = false;
        },

        async addSplit() {
            this.formError = '';
            if (!this.form.partner_name.trim()) { this.formError = 'Partner name is required.'; return; }
            if (!this.form.partner_email.trim()) { this.formError = 'Partner email is required.'; return; }
            if (this.form.split_share_value < 0)  { this.formError = 'Split share must be 0 or more.'; return; }
            this.saving = true;
            try {
                const csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
                const res  = await fetch(`/api/leads/${dealId}/partner-splits`, {
                    method:      'POST',
                    credentials: 'same-origin',
                    headers:     { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body:        JSON.stringify({ ...this.form, tenant_id: tenantId }),
                });
                const data = await res.json();
                if (res.ok) {
                    this.showAdd  = false;
                    this.form     = { partner_name: '', partner_email: '', split_share_value: 0, split_share_type: 'percentage' };
                    this.formError= '';
                    await this.load();
                    this.$dispatch('show-toast', { type: 'success', message: 'Partner split added.' });
                } else {
                    this.formError = data.error || 'Failed to add partner split.';
                }
            } catch(e) { this.formError = 'Network error. Please try again.'; }
            finally { this.saving = false; }
        },

        async removeSplit(splitId) {
            if (!confirm('Remove this partner split?')) return;
            try {
                const csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
                await fetch(`/api/leads/${dealId}/partner-splits/${splitId}`, {
                    method:      'DELETE',
                    credentials: 'same-origin',
                    headers:     { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                });
                await this.load();
                this.$dispatch('show-toast', { type: 'success', message: 'Partner split removed.' });
            } catch(e) {}
        },
    };
}

// ── Extension Request Section ─────────────────────────────────────────────
function extensionRequestSection(dealId, tenantId) {
    return {
        requests: [], loading: true, showForm: false, saving: false, formError: '',
        canRequest: false,
        form: { requested_days: 14, reason: '', acknowledged: false },

        async load() {
            this.loading = true;
            try {
                const res  = await fetch(`/api/leads/${dealId}/extension-requests?tenant_id=${tenantId}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (res.ok) {
                    this.requests = await res.json();
                }
                // Can request if no pending request exists
                this.canRequest = !this.requests.some(r => r.status === 'pending_review');
            } catch(e) { this.requests = []; }
            this.loading = false;
        },

        async submitRequest() {
            this.formError = '';
            if (!this.form.reason || this.form.reason.trim().length < 10) {
                this.formError = 'Please provide a reason (minimum 10 characters).';
                return;
            }
            if (!this.form.acknowledged) {
                this.formError = 'Please acknowledge that this request requires admin approval.';
                return;
            }
            this.saving = true;
            try {
                const csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
                const res  = await fetch(`/api/leads/${dealId}/extension-requests`, {
                    method:      'POST',
                    credentials: 'same-origin',
                    headers:     { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body:        JSON.stringify({ ...this.form, tenant_id: tenantId }),
                });
                const data = await res.json();
                if (res.ok) {
                    this.showForm  = false;
                    this.formError = '';
                    this.form      = { requested_days: 14, reason: '', acknowledged: false };
                    await this.load();
                    this.$dispatch('show-toast', { type: 'success', message: data.message || 'Extension request submitted.' });
                } else {
                    this.formError = data.error || 'Failed to submit request.';
                }
            } catch(e) { this.formError = 'Network error. Please try again.'; }
            finally { this.saving = false; }
        },
    };
}
</script>
@endsection



