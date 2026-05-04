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
        <div class="card">
            <div class="flex flex-col sm:flex-row sm:items-start gap-4">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-[#7B61FF] font-bold text-lg shrink-0"
                     style="background:#EDE9FE" x-text="(lead?.name||'?').slice(0,2).toUpperCase()"></div>
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <h2 class="text-xl font-bold text-[#1E1B4B]" x-text="lead?.name"></h2>
                        <span :class="{
                            'badge badge-green':  lead?.status === 'active',
                            'badge badge-orange': lead?.status === 'expiring',
                            'badge badge-red':    lead?.status === 'expired',
                            'badge badge-gray':   !['active','expiring','expired'].includes(lead?.status||''),
                        }" x-text="lead?.status ? lead.status.charAt(0).toUpperCase()+lead.status.slice(1) : ''"></span>
                        <span :class="stageBadge(lead?.stage)" x-text="stageLabel(lead?.stage)"></span>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 mt-1">
                        <p class="text-sm text-gray-500">Referrer: <span class="font-medium text-gray-700" x-text="lead?.reseller_name || 'Unassigned'"></span></p>
                        <span x-show="lead?.data?.province" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-blue-50 text-blue-700 text-xs font-medium">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span x-text="[lead?.data?.municipality, lead?.data?.province].filter(Boolean).join(', ')"></span>
                        </span>
                    </div>
                    <p class="text-xs text-gray-400 mt-0.5" x-text="lead?.created_at ? 'Created ' + new Date(lead.created_at).toLocaleDateString('en',{month:'long',day:'numeric',year:'numeric'}) : ''"></p>
                </div>
                <div class="text-right shrink-0">
                    <p class="text-2xl font-bold text-[#1E1B4B]" x-text="fmt(contractValue())"></p>
                    <p class="text-xs text-gray-400">Contract Value</p>
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
                            <span class="text-xs text-gray-500 text-center mt-1.5 leading-tight hidden sm:block" x-text="s.label"></span>
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
                        <span class="text-base font-semibold text-[#1E1B4B] tabular-nums" x-text="fmt(lead?.base_cost || 0)"></span>
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
                <div class="card space-y-3">
                    <h3 class="font-semibold text-[#1E1B4B] text-sm">Deal Details</h3>
                    <div class="flex justify-between text-sm py-1.5 border-b border-gray-50">
                        <span class="text-gray-400">Referrer</span>
                        <span class="font-medium text-gray-700" x-text="lead?.reseller_name || '—'"></span>
                    </div>
                    @if($showLocation ?? false)
                    <div class="flex justify-between text-sm py-1.5 border-b border-gray-50">
                        <span class="text-gray-400">Province</span>
                        <span class="font-medium text-gray-700" x-text="lead?.data?.province || '—'"></span>
                    </div>
                    <div class="flex justify-between text-sm py-1.5 border-b border-gray-50">
                        <span class="text-gray-400">Municipality</span>
                        <span class="font-medium text-gray-700" x-text="lead?.data?.municipality || '—'"></span>
                    </div>
                    @endif
                    <div class="flex justify-between text-sm py-1.5 border-b border-gray-50">
                        <span class="text-gray-400">Stage</span>
                        <span class="font-medium text-gray-700" x-text="stageLabel(lead?.stage)"></span>
                    </div>
                    <div class="flex justify-between text-sm py-1.5 border-b border-gray-50 last:border-0">
                        <span class="text-gray-400">Days Left</span>
                        <span class="font-medium text-gray-700" x-text="(lead?.days_left ?? 21) + ' days'"></span>
                    </div>
                    <div class="flex justify-between text-sm py-1.5">
                        <span class="text-gray-400">Commission</span>
                        <span :class="{
                            'badge badge-gray':   lead?.commission_status === 'pending',
                            'badge badge-orange': lead?.commission_status === 'locked',
                            'badge badge-green':  lead?.commission_status === 'paid',
                        }" x-text="lead?.commission_status ? lead.commission_status.charAt(0).toUpperCase()+lead.commission_status.slice(1) : 'Pending'"></span>
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
                        <textarea x-model="noteText" rows="3" class="form-input text-sm" placeholder="Add a note…"></textarea>
                        <input type="text" x-model="noteAuthor" class="form-input text-sm" placeholder="Your name">
                        <div class="flex justify-end gap-2">
                            <button @click="showNoteForm = false; noteText=''; noteAuthor=''" class="btn-secondary text-xs">Cancel</button>
                            <button @click="addNote()" :disabled="saving || !noteText" class="btn-primary text-xs" x-text="saving ? 'Saving…' : 'Save Note'"></button>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <template x-if="(lead?.notes||[]).length === 0">
                            <p class="text-gray-400 text-sm text-center py-4">No notes yet.</p>
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
                            <p class="text-gray-400 text-sm text-center py-4">No activity yet.</p>
                        </template>
                        <template x-for="(event, ei) in [...(lead?.history||[])].reverse()" :key="event.id">
                            <div class="flex gap-3">
                                <div class="flex flex-col items-center shrink-0">
                                    <div :class="{
                                        'bg-purple-100 text-purple-600': event.type === 'stage',
                                        'bg-blue-100 text-blue-600':    event.type === 'assignment',
                                        'bg-emerald-100 text-emerald-600': event.type === 'commission',
                                        'bg-gray-100 text-gray-500':    !event.type || event.type === 'notes',
                                    }" class="w-7 h-7 rounded-full flex items-center justify-center shrink-0">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <template x-if="event.type === 'stage'"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></template>
                                            <template x-if="event.type === 'assignment'"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></template>
                                            <template x-if="event.type === 'commission'"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2"/></template>
                                            <template x-if="!event.type || event.type === 'notes'"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></template>
                                        </svg>
                                    </div>
                                    <div class="w-px flex-1 bg-gray-100 mt-1"></div>
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
                    <svg fill=”none” stroke=”currentColor” viewBox=”0 0 24 24”><path stroke-linecap=”round” stroke-linejoin=”round” stroke-width=”2” d=”M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z”/></svg>
                    <input type=”text” x-model=”linkSearch” placeholder=”Search contacts by name or email…” autofocus>
                </div>
                <div class=”max-h-72 overflow-y-auto space-y-1”>
                    <template x-if=”linkableContacts().length === 0”>
                        <p class=”text-center text-gray-400 text-sm py-6”>
                            <span x-text=”allTenantContacts.length === 0 ? 'No contacts in this tenant yet.' : 'No matching contacts.'”></span>
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

    {{-- â”€â”€ Move Stage Modal â”€â”€ --}}
    <div x-show="showMoveStage" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 space-y-4" @click.stop>
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-[#1E1B4B]">Move to Stage</h3>
                <button @click="showMoveStage = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <p class="text-sm text-gray-500">Currently: <span class="font-semibold" x-text="stageLabel(lead?.stage)"></span></p>
            <div class="space-y-2">
                <template x-for="s in allStages" :key="s.key">
                    <button @click="moveToStage(s.key)"
                            :disabled="s.key === lead?.stage || saving"
                            :class="s.key === lead?.stage ? 'opacity-50 cursor-not-allowed bg-gray-50' : 'hover:bg-[#F0EFFA] hover:border-purple-200 cursor-pointer'"
                            class="w-full flex items-center gap-3 px-4 py-3 rounded-xl border border-gray-100 transition-all text-left">
                        <span :class="stageBadge(s.key)" class="shrink-0 text-xs" x-text="s.label"></span>
                        <span x-show="s.key === 'signed'" class="text-xs text-orange-600">→ locks commission at <span x-text="fmt(commPool())"></span></span>
                        <span x-show="s.key === 'paid'"   class="text-xs text-emerald-600">→ marks <span x-text="fmt(commPool())"></span> paid</span>
                        <span x-show="s.key === lead?.stage" class="ml-auto text-xs text-gray-400">current</span>
                    </button>
                </template>
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
                This resets the stage to Introduction, restarts the 21-day timer, and transfers 100% of the commission pool to the new referrer.
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

</div>

<script>
function dealDetail(leadId, tenantId) {
    return {
        lead: null, loading: true,
        showNoteForm: false, showMoveStage: false, showReassign: false,
        noteText: '', noteAuthor: '', saving: false, reassignName: '',
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
            const [leadRes] = await Promise.all([
                fetch(`/api/leads/${leadId}`),
            ]);
            this.lead = await leadRes.json();
            this.loading = false;
            this.fetchContacts();
            this.fetchAllContacts();
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
            if (n >= 1000000) return '₱' + (n/1000000).toFixed(1) + 'M';
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
                const bc = Number(this.financeForm.base_cost)    || 0;
                const aa = Number(this.financeForm.added_amount) || 0;
                const res = await fetch(`/api/leads/${this.lead.id}`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
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
                const res = await fetch(`/api/leads/${this.lead.id}/stage`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ stage }),
                });
                const updated = await res.json();
                if (updated.id) {
                    this.lead = { ...this.lead, ...updated, history: updated.history, commission_splits: updated.commission_splits };
                    this.showMoveStage = false;
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
                const res = await fetch(`/api/leads/${this.lead.id}/notes`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
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
                const res = await fetch(`/api/leads/${this.lead.id}/reassign`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
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
                const res = await fetch(`/api/deals/${leadId}/contacts`);
                const data = await res.json();
                this.dealContacts = Array.isArray(data) ? data : [];
            } catch(e) { this.dealContacts = []; }
            this.loadingContacts = false;
        },

        async fetchAllContacts() {
            try {
                const res = await fetch(`/api/contacts?tenant_id=${tenantId}`);
                const data = await res.json();
                this.allTenantContacts = Array.isArray(data) ? data : [];
            } catch(e) { this.allTenantContacts = []; }
        },

        openLinkContact() {
            this.linkSearch = '';
            this.showLinkContact = true;
            if (this.allTenantContacts.length === 0) this.fetchAllContacts();
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
                const res = await fetch(`/api/deals/${leadId}/contacts`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
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
                await fetch(`/api/deals/${leadId}/contacts/${contactId}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
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
                const res = await fetch(`/api/leads/${this.lead.id}/commission-splits`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
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
</script>
@endsection



