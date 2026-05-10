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
<script>var __dealSsrLead = @json($ssrLead ?? null);</script>
<style>
/* Financial breakdown layout â€” guaranteed, no Tailwind compile dependency */
.fin-row{display:flex!important;justify-content:space-between;align-items:center}
.fin-grid-3{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr));gap:.5rem}
</style>
<div class="space-y-5"
     x-data="dealDetail('{{ $dealId }}', '{{ $tenant->id }}', __dealSsrLead)"
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

        {{-- Ã¢"â‚¬Ã¢"â‚¬ Header Ã¢"â‚¬Ã¢"â‚¬ --}}
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
                        {{-- Days-to-move counter --}}
                        <span x-show="lead?.days_left !== null && lead?.days_left !== undefined && lead?.stage !== 'paid'"
                              class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold"
                              :class="{
                                  'bg-red-100 text-red-700':    (lead?.days_left ?? 99) <= 3,
                                  'bg-amber-100 text-amber-700': (lead?.days_left ?? 99) > 3 && (lead?.days_left ?? 99) <= 7,
                                  'bg-blue-50 text-blue-700':   (lead?.days_left ?? 99) > 7,
                              }">
                            <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span x-text="(lead?.days_left ?? 0) <= 0 ? 'Overdue' : (lead.days_left + 'd to move stage')"></span>
                        </span>
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

        {{-- Ã¢"â‚¬Ã¢"â‚¬ Stage Progress Ã¢"â‚¬Ã¢"â‚¬ --}}
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
                        <div x-show="i + 1 !== allStages.length" class="flex-1 h-0.5 mx-1 transition-colors"
                             :class="isStageDone(allStages[i+1]?.key) ? 'bg-[#7B61FF]' : 'bg-gray-200'"></div>
                    </div>
                </template>
            </div>
        </div>

        {{-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
             FINANCIAL BREAKDOWN  Ã¢â€ Â the key feature
             â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• --}}
        <div class="card">

            {{-- Header --}}
            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-emerald-100 flex items-center justify-center">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h3 class="font-semibold text-[#1E1B4B]">Financial Breakdown</h3>

                    {{-- â“˜ Formula explainer (orange, always visible) --}}
                    <div class="relative" x-data="{ open: false }">
                        <button type="button"
                                @click="open = !open"
                                @keydown.escape.window="open = false"
                                aria-label="Explain financial breakdown"
                                title="How this financial breakdown works"
                                class="ml-0.5 flex-shrink-0 focus:outline-none rounded-full transition-colors"
                                :class="open ? 'text-orange-500' : 'text-orange-400 hover:text-orange-600'">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                            </svg>
                        </button>

                        {{-- Popover --}}
                        <div x-show="open"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             @click.outside="open = false"
                             style="display:none"
                             class="absolute left-0 top-7 z-50 w-80 sm:w-96 bg-white border border-gray-100 rounded-2xl shadow-xl p-5 text-left">

                            <div class="flex items-start justify-between mb-3">
                                <h4 class="font-semibold text-[#1E1B4B] text-sm leading-snug">How this financial breakdown works</h4>
                                <button @click="open = false" class="text-gray-300 hover:text-gray-500 ml-3 shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>

                            <p class="text-xs text-gray-600 leading-relaxed mb-3">
                                ReferralBunny.ai separates the total contract value into the actual base cost and the added amount (your margin). The <span class="font-semibold text-blue-600">company share</span> is 30% of the added amount, while the <span class="font-semibold text-emerald-600">referrer commission pool</span> is 70% of the added amount. This lets everyone see exactly how the contract value, company share, and commission pool are calculated â€” before commissions are locked or paid.
                            </p>

                            {{-- Formula --}}
                            <div class="space-y-1.5 bg-[#F0EFFA] rounded-xl p-3 mb-3">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-gray-500">₱ Base Cost  +  Added Amount</span>
                                    <span class="font-semibold text-[#1E1B4B]">= Contract Value</span>
                                </div>
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-blue-500">Company Share</span>
                                    <span class="font-semibold text-blue-700">= 30% of Added Amount</span>
                                </div>
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-emerald-500">Commission Pool</span>
                                    <span class="font-semibold text-emerald-700">= 70% of Added Amount</span>
                                </div>
                            </div>

                            {{-- Live example using current deal --}}
                            <div class="bg-gray-50 rounded-xl p-3 mb-3">
                                <p class="text-[10px] text-gray-400 uppercase tracking-wide font-semibold mb-1.5">This deal</p>
                                <div class="space-y-1">
                                    <div class="flex justify-between text-xs">
                                        <span class="text-gray-500">Base Cost</span>
                                        <span class="font-medium tabular-nums" x-text="fmt(lead?.base_cost || 0)"></span>
                                    </div>
                                    <div class="flex justify-between text-xs">
                                        <span class="text-gray-500">Added Amount</span>
                                        <span class="font-medium text-blue-600 tabular-nums" x-text="fmt(lead?.added_amount || 0)"></span>
                                    </div>
                                    <div class="flex justify-between text-xs border-t border-gray-200 pt-1 mt-1">
                                        <span class="font-semibold text-[#1E1B4B]">Contract Value</span>
                                        <span class="font-bold text-[#1E1B4B] tabular-nums" x-text="fmt(contractValue())"></span>
                                    </div>
                                    <div class="flex justify-between text-xs">
                                        <span class="text-blue-500">Company (30%)</span>
                                        <span class="font-medium text-blue-700 tabular-nums" x-text="fmt(companyShare())"></span>
                                    </div>
                                    <div class="flex justify-between text-xs">
                                        <span class="text-emerald-500">Commission Pool (70%)</span>
                                        <span class="font-medium text-emerald-700 tabular-nums" x-text="fmt(commPool())"></span>
                                    </div>
                                </div>
                            </div>

                            <p class="text-[10px] text-gray-400 leading-relaxed">
                                Partner split shares are tracked separately and do not automatically reduce the referrer commission pool unless the deal rules explicitly say so.
                            </p>
                        </div>
                    </div>
                </div>
                <button x-show="!editFinance" @click="startEditFinance()"
                        class="flex items-center gap-1.5 text-xs text-purple-600 hover:text-purple-700 font-medium">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Edit
                </button>
            </div>

            {{-- Ã¢"â‚¬Ã¢"â‚¬ View mode (PHP-rendered â€” no Alpine x-text dependency) Ã¢"â‚¬Ã¢"â‚¬ --}}
            @php
                $bc = (float)($ssrLead['base_cost']    ?? 0);
                $aa = (float)($ssrLead['added_amount'] ?? 0);
                $dv = (float)($ssrLead['deal_value']   ?? 0);
                $cv = ($bc + $aa) ?: $dv;
                $co = $aa * 0.30;
                $cp = $aa * 0.70;
            @endphp
            <div x-show="!editFinance" class="space-y-4">

                {{-- Formula rows --}}
                <div>
                    <div class="fin-row py-2.5 border-b border-gray-100">
                        <p class="text-sm text-gray-500">₱ Base Cost</p>
                        <p id="fin-bc" class="text-sm font-semibold text-gray-700 tabular-nums">₱{{ number_format((int)$bc) }}</p>
                    </div>
                    <div class="fin-row py-2.5 border-b border-dashed border-gray-200">
                        <p class="text-sm text-gray-500">+ Added Amount <span class="text-xs text-gray-400">(margin)</span></p>
                        <p id="fin-aa" class="text-sm font-semibold text-blue-600 tabular-nums">₱{{ number_format((int)$aa) }}</p>
                    </div>
                    <div class="fin-row py-2.5 rounded-xl px-3 mt-1" style="background:#F0EFFA">
                        <p class="text-sm font-semibold" style="color:#1E1B4B">= Contract Value</p>
                        <p id="fin-cv" class="text-sm font-bold tabular-nums" style="color:#1E1B4B">₱{{ number_format((int)$cv) }}</p>
                    </div>
                </div>

                {{-- 3-column summary --}}
                <div class="fin-grid-3 p-3 rounded-xl bg-gray-50 border border-gray-100">
                    <div class="text-center">
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Contract Value</p>
                        <p id="fin-cv2" class="text-sm font-bold tabular-nums mt-0.5" style="color:#1E1B4B">₱{{ number_format((int)$cv) }}</p>
                        <p class="text-xs text-gray-400">base + margin</p>
                    </div>
                    <div class="text-center" style="border-left:1px solid #e5e7eb;border-right:1px solid #e5e7eb">
                        <p class="text-xs text-blue-500 uppercase tracking-wide">Company Share</p>
                        <p id="fin-co" class="text-sm font-bold text-blue-700 tabular-nums mt-0.5">₱{{ number_format((int)$co) }}</p>
                        <p class="text-xs text-blue-400">30% margin</p>
                    </div>
                    <div class="text-center">
                        <p class="text-xs text-emerald-500 uppercase tracking-wide">Commission Pool</p>
                        <p id="fin-cp" class="text-sm font-bold text-emerald-700 tabular-nums mt-0.5">₱{{ number_format((int)$cp) }}</p>
                        <p class="text-xs text-emerald-400">70% margin</p>
                    </div>
                </div>

                {{-- Commission distribution (Alpine-driven â€” only shows when splits exist) --}}
                @if(!empty($ssrLead['commission_splits']))
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Commission Distribution</p>
                        <span class="badge badge-gray"
                              x-text="lead?.commission_status ? lead.commission_status.charAt(0).toUpperCase()+lead.commission_status.slice(1) : 'Pending'">
                            {{ ucfirst($ssrLead['commission_status'] ?? 'pending') }}</span>
                    </div>
                    <div class="space-y-1.5">
                        <template x-for="split in (lead?.commission_splits||[])" :key="split.id">
                            <div class="flex items-center justify-between bg-gray-50 rounded-xl px-3 py-2.5">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                                         :class="split.role==='primary' ? 'bg-emerald-100 text-emerald-700' : split.role==='secondary' ? 'bg-blue-100 text-blue-700' : 'bg-gray-200 text-gray-600'"
                                         x-text="(split.reseller_name||'?').slice(0,2).toUpperCase()"></div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-[#1E1B4B] truncate" x-text="split.reseller_name"></p>
                                        <p class="text-xs text-gray-400 capitalize" x-text="split.role"></p>
                                    </div>
                                </div>
                                <div class="text-right shrink-0 ml-3">
                                    <p class="text-sm font-bold text-emerald-700 tabular-nums" x-text="fmt(commPool() * split.percentage / 100)"></p>
                                    <p class="text-xs text-gray-400" x-text="split.percentage + '% of pool'"></p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
                @endif

                {{-- No financial data notice --}}
                @if(!$aa)
                <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800">
                    No financial data set yet. Click <strong>Edit</strong> to enter Base Cost and Added Amount.
                </div>
                @endif

            </div>

            {{-- Edit mode --}}
            <div x-show=”editFinance” x-cloak class=”space-y-5”>

                @if($showLocation ?? false)
                {{-- LGU IDS: deal-value-first with auto-locked base cost --}}
                <div class=”flex items-start gap-2 p-3 bg-purple-50 border border-purple-100 rounded-xl”>
                    <svg class=”w-3.5 h-3.5 text-purple-400 shrink-0 mt-0.5” fill=”currentColor” viewBox=”0 0 20 20”><path fill-rule=”evenodd” d=”M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z” clip-rule=”evenodd”/></svg>
                    <p class=”text-xs text-purple-700 leading-relaxed”>Enter the total contract value first. Base cost is auto-locked by the LGU IDS pricing tier. Adjust the added amount (margin) if needed — the deal value will update to stay consistent.</p>
                </div>

                {{-- Deal Value (primary input) --}}
                <div>
                    <label class=”form-label text-[#1E1B4B] font-semibold”>Deal Value (₱) <span class=”text-gray-400 font-normal text-xs”>— total contract amount</span></label>
                    <input type=”number” x-model.number=”financeForm.deal_value”
                           @input=”onDealValueChange()”
                           class=”form-input text-base font-semibold” placeholder=”4000000” min=”0” step=”100”>
                    <p class=”text-[10px] text-purple-500 mt-1”>LGU IDS default: ₱4,000,000</p>
                </div>

                {{-- Base Cost (locked) + Added Amount (editable) --}}
                <div class=”grid grid-cols-1 sm:grid-cols-2 gap-4”>
                    <div>
                        <label class=”form-label”>Base Cost (₱) <span class=”text-gray-400 font-normal”>— LGU IDS tier</span></label>
                        <div class=”form-input bg-gray-50 flex items-center justify-between cursor-not-allowed select-none”
                             style=”padding-top:0.6rem;padding-bottom:0.6rem”>
                            <span class=”text-gray-700 font-semibold tabular-nums” x-text=”fmt(financeForm.base_cost)”></span>
                            <span class=”text-[10px] font-bold text-purple-500 bg-purple-100 rounded-full px-2 py-0.5 ml-2 shrink-0”
                                  x-text=”tierLabel(financeForm.deal_value)”></span>
                        </div>
                        <p class=”text-[10px] text-gray-400 mt-0.5”>Auto-locked · not editable</p>
                    </div>
                    <div>
                        <label class=”form-label”>Added Amount (₱) <span class=”text-gray-400 font-normal”>— margin</span></label>
                        <input type=”number” x-model.number=”financeForm.added_amount”
                               @input=”onAddedAmountChange()”
                               class=”form-input” placeholder=”0” min=”0” step=”100”>
                        <p class=”text-[10px] text-gray-400 mt-0.5”>Edit to adjust — deal value updates</p>
                    </div>
                </div>

                @else
                {{-- Generic tenant: manual base cost + added amount --}}
                <p class=”text-xs text-gray-500”>Enter the deal financials. Contract Value, Company Share, and Commission Pool are calculated automatically.</p>

                <div class=”grid grid-cols-1 sm:grid-cols-2 gap-4”>
                    <div>
                        <label class=”form-label”>Base Cost (₱) <span class=”text-gray-400 font-normal”>— actual delivery cost</span></label>
                        <input type=”number” x-model.number=”financeForm.base_cost” @input=”recalc()”
                               class=”form-input” placeholder=”0” min=”0” step=”100”>
                    </div>
                    <div>
                        <label class=”form-label”>Added Amount (₱) <span class=”text-gray-400 font-normal”>— your margin</span></label>
                        <input type=”number” x-model.number=”financeForm.added_amount” @input=”recalc()”
                               class=”form-input” placeholder=”0” min=”0” step=”100”>
                    </div>
                </div>
                @endif

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
                    <div x-show="(lead?.commission_splits||[]).length" class="space-y-1.5 pt-1">
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
        <script>
        // Apply financial layout â€” JS setProperty bypasses ALL CSS blocking
        (function applyFin(){
            ['.fin-row','.fin-grid-3'].forEach(function(sel){
                document.querySelectorAll(sel).forEach(function(el){
                    if(sel==='.fin-row'){
                        el.style.setProperty('display','flex','important');
                        el.style.setProperty('justify-content','space-between','important');
                        el.style.setProperty('align-items','center','important');
                    } else {
                        el.style.setProperty('display','grid','important');
                        el.style.setProperty('grid-template-columns','repeat(3,minmax(0,1fr))','important');
                        el.style.setProperty('gap','.5rem','important');
                    }
                });
            });
        })();
        document.addEventListener('DOMContentLoaded',function(){
            document.querySelectorAll('.fin-row').forEach(function(el){
                el.style.setProperty('display','flex','important');
                el.style.setProperty('justify-content','space-between','important');
                el.style.setProperty('align-items','center','important');
            });
            document.querySelectorAll('.fin-grid-3').forEach(function(el){
                el.style.setProperty('display','grid','important');
                el.style.setProperty('grid-template-columns','repeat(3,minmax(0,1fr))','important');
                el.style.setProperty('gap','.5rem','important');
            });
        });
        </script>

        {{-- Ã¢"â‚¬Ã¢"â‚¬ Bottom layout: Details + Notes/History Ã¢"â‚¬Ã¢"â‚¬ --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

            {{-- Left: Details + Commission splits quick view --}}
            <div class="space-y-4">
                <div class="card space-y-3">
                    <h3 class="font-semibold text-[#1E1B4B] text-sm">Deal Details</h3>
                    <div class="flex justify-between text-sm py-1.5 border-b border-gray-50">
                        <span class="text-gray-400">Referrer</span>
                        <span class="font-medium text-gray-700" x-text="lead?.reseller_name || 'â€”'"></span>
                    </div>
                    @if($showLocation ?? false)
                    <div class="flex justify-between text-sm py-1.5 border-b border-gray-50">
                        <span class="text-gray-400">Province</span>
                        <span class="font-medium text-gray-700" x-text="lead?.data?.province || 'â€”'"></span>
                    </div>
                    <div class="flex justify-between text-sm py-1.5 border-b border-gray-50">
                        <span class="text-gray-400">Municipality</span>
                        <span class="font-medium text-gray-700" x-text="lead?.data?.municipality || 'â€”'"></span>
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

            {{-- Partners & Split Share --}}
            <div class="card space-y-3"
                 x-data="partnerSplitSection('{{ $dealId }}', '{{ $tenant->id }}')"
                 x-init="load()">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-[#1E1B4B] text-sm">Partners &amp; Split Share</h3>
                    <button @click="showAdd = !showAdd" class="text-xs text-purple-600 hover:text-purple-700 font-medium flex items-center gap-1">
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
                    <div x-show="hasPctSplits()"
                         class="flex justify-between text-xs py-1 border-t border-gray-100 mt-1">
                        <span class="text-gray-400">Total Partner %</span>
                        <span :class="totalPctClass()" x-text="totalPct + '%'"></span>
                    </div>
                </div>

                {{-- Add form --}}
                <div x-show="showAdd" style="display:none" class="space-y-2.5 pt-2 border-t border-gray-100">
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
                                            <p class="text-xs font-medium text-[#1E1B4B] truncate" x-text="[c.first_name, c.last_name].filter(Boolean).join(' ') || c.name || 'â€”'"></p>
                                            <p class="text-[10px] text-gray-400 truncate" x-text="c.email || c.job_title || ''"></p>
                                        </div>
                                    </button>
                                </template>
                                <div x-show="contactOptions.length === 0 && !loadingContacts && !contactLoadError"
                                     class="px-3 py-4 text-center text-xs text-gray-400"
                                     x-text="contactQuery ? 'No matching contacts.' : 'Click ðŸ” or type to search contacts.'"></div>
                            </div>
                        </div>
                    </div>

                    {{-- Selected contact pill --}}
                    <div x-show="contactSelected" class="flex items-center gap-2 p-2 border border-purple-200 rounded-xl bg-purple-50">
                        <div class="w-6 h-6 rounded-full bg-purple-200 flex items-center justify-center text-purple-700 text-[10px] font-bold shrink-0"
                             x-text="([contactSelected?.first_name, contactSelected?.last_name].filter(Boolean).join(' ') || contactSelected?.name || '?').slice(0,2).toUpperCase()"></div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-[#1E1B4B] truncate" x-text="[contactSelected?.first_name, contactSelected?.last_name].filter(Boolean).join(' ') || contactSelected?.name || 'â€”'"></p>
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
                        <p x-show="form.split_share_type === 'percentage' && form.split_share_value && dealValue"
                           class="text-xs text-purple-600 font-medium flex items-center gap-1">
                            <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            <span x-text="'= ₱' + (dealValue * form.split_share_value / 100).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                        </p>
                        <p x-show="form.split_share_type === 'fixed_amount' && form.split_share_value && dealValue"
                           class="text-xs text-gray-400 flex items-center gap-1">
                            <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span x-text="'â‰ˆ ' + (form.split_share_value / dealValue * 100).toFixed(1) + '% of deal value'"></span>
                        </p>
                    </div>
                    <p x-show="formError" class="text-xs text-red-600" x-text="formError"></p>
                    <div class="flex gap-2">
                        <button @click="showAdd = false; clearContact(); formError = ''" class="btn-secondary text-xs flex-1">Cancel</button>
                        <button @click="addSplit()" :disabled="saving" class="btn-primary text-xs flex-1" x-text="saving ? 'Saving…' : 'Add Split'"></button>
                    </div>
                </div>
            </div>

            {{-- Extend Assignment (admin/manager review view) --}}
            <div class="card space-y-3"
                 x-data="extensionRequestSection('{{ $dealId }}', '{{ $tenant->id }}')"
                 x-init="load()">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-[#1E1B4B] text-sm">Extend Assignment</h3>
                    <div class="flex items-center gap-2">
                        <span x-show="hasPendingRequests()"
                              class="text-xs font-semibold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">
                            Needs Review
                        </span>
                        <button x-show="!showExtendForm" @click="showExtendForm = true"
                                class="text-xs text-purple-600 hover:text-purple-700 font-medium">
                            + Extend
                        </button>
                    </div>
                </div>

                {{-- Admin direct extend form --}}
                <div x-show="showExtendForm" style="display:none" class="space-y-2.5 p-3 bg-[#F0EFFA] rounded-xl">
                    <p class="text-xs font-medium text-[#1E1B4B]">Extend Assignment</p>
                    <select x-model.number="extendForm.days" class="form-input text-xs">
                        <option value="7">+7 days</option>
                        <option value="14">+14 days</option>
                        <option value="21">+21 days</option>
                        <option value="30">+30 days</option>
                    </select>
                    <textarea x-model="extendForm.reason" class="form-input text-xs" rows="2"
                              placeholder="Reason for extension (optional)…"></textarea>
                    <p x-show="extendError" class="text-xs text-red-600" x-text="extendError"></p>
                    <div class="flex gap-2">
                        <button @click="showExtendForm = false; extendError = ''" class="btn-secondary text-xs flex-1">Cancel</button>
                        <button @click="adminExtend()" :disabled="saving"
                                class="btn-primary text-xs flex-1"
                                x-text="saving ? 'Extending…' : 'Confirm Extension'"></button>
                    </div>
                </div>

                <div x-show="loading" class="flex items-center gap-2 text-gray-400 text-xs py-1">
                    <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Loading…
                </div>

                <div x-show="!loading" class="space-y-2">
                    <template x-if="requests.length === 0">
                        <p class="text-xs text-gray-400">No extension requests for this deal.</p>
                    </template>
                    <template x-for="r in requests" :key="r.id">
                        <div class="rounded-xl p-3 text-xs space-y-2"
                             :class="{
                                 'bg-amber-50 border border-amber-200': r.status === 'pending_review',
                                 'bg-green-50 border border-green-200':  r.status === 'approved',
                                 'bg-red-50 border border-red-200':      r.status === 'rejected',
                                 'bg-gray-50 border border-gray-200':    !['pending_review','approved','rejected'].includes(r.status),
                             }">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold capitalize" x-text="r.status_label ?? r.status.replace('_',' ')"></span>
                                <span class="text-gray-500 font-medium" x-text="r.requested_days + ' days requested'"></span>
                            </div>
                            <p class="text-gray-600 leading-relaxed" x-text="r.reason"></p>
                            <p x-show="r.admin_note" class="text-gray-500 italic" x-text="'Note: ' + r.admin_note"></p>
                            <p x-show="r.approved_days" class="text-green-700 font-semibold" x-text="r.approved_days + ' days approved'"></p>

                            {{-- Approve / Deny buttons for pending requests --}}
                            <div x-show="r.status === 'pending_review'" class="flex gap-2 pt-1">
                                <button @click="approveRequest(r.id, r.requested_days)"
                                        :disabled="saving"
                                        class="btn-primary text-xs flex-1">
                                    <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    Approve
                                </button>
                                <button @click="rejectRequest(r.id)"
                                        :disabled="saving"
                                        class="btn-secondary text-xs flex-1 !text-red-600 !border-red-200 hover:!bg-red-50">
                                    <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    Deny
                                </button>
                            </div>
                            <p x-show="actionError === r.id" class="text-xs text-red-600" x-text="'Failed to update request.'"></p>
                        </div>
                    </template>
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
                    <div x-show="dealContacts.length" class="space-y-1">
                        <template x-for="c in dealContacts" :key="c.id">
                            <div class="flex items-center gap-2.5 py-2 border-b border-gray-50 last:border-0 group">
                                <div class="w-7 h-7 rounded-full bg-purple-100 flex items-center justify-center text-purple-700 text-xs font-bold shrink-0"
                                     x-text="contactInitials(c)"></div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-[#1E1B4B] truncate" x-text="contactFullName(c)"></p>
                                    <p class="text-xs text-gray-400 truncate">
                                        <span x-show="c.deal_role" x-text="c.deal_role + ' Â· '"></span>
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

                {{-- Notes (rich: @mentions, file attachments, visibility) --}}
                <div x-data="dealComments('{{ $dealId }}', '{{ $tenant->id }}')"
                     x-init="loadComments()"
                     class="card space-y-4">

                    <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                        <div>
                            <h3 class="font-semibold text-[#1E1B4B] text-sm flex items-center gap-2">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                Notes
                                <span class="text-gray-400 font-normal text-xs" x-text="comments.length ? '(' + comments.length + ')' : ''"></span>
                            </h3>
                            <p class="text-[11px] text-gray-400 mt-0.5">Capture updates, tag people, and attach supporting files for this deal.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="filter-pill text-xs" x-show="canPostInternal">
                                <select x-model="newVisibility" class="text-xs">
                                    <option value="shared">Shared with participants</option>
                                    <option value="internal_admin">Internal admin only</option>
                                </select>
                            </label>
                        </div>
                    </div>

                    {{-- Composer --}}
                    <div class="flex gap-3">
                        <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-purple-700 text-xs font-bold shrink-0 mt-0.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                        <div class="flex-1 space-y-2">
                            {{-- Textarea with @mention --}}
                            <div class="relative">
                                <textarea x-ref="noteTextarea"
                                          x-model="newBody"
                                          @input="handleBodyInput($event)"
                                          @keydown.escape="mentionOpen = false"
                                          @keydown.arrow-down.prevent="mentionFocusIdx = Math.min(mentionFocusIdx + 1, mentionResults.length - 1)"
                                          @keydown.arrow-up.prevent="mentionFocusIdx = Math.max(mentionFocusIdx - 1, 0)"
                                          @keydown.enter.prevent="if(mentionOpen && mentionResults[mentionFocusIdx]) selectMention(mentionResults[mentionFocusIdx])"
                                          rows="3"
                                          class="form-input text-sm resize-none"
                                          :placeholder="newVisibility === 'internal_admin' ? 'Internal note â€” only visible to Tenant Admins and Managers. Type @ to tag someone…' : 'Write a note about this deal. Type @ to tag a teammate, Referrer, Partner, or Contact…'"></textarea>

                                {{-- @Mention dropdown --}}
                                <div x-show="mentionOpen" style="display:none"
                                     class="absolute left-0 top-full mt-1 z-50 w-72 bg-white rounded-xl shadow-xl border border-gray-100 max-h-48 overflow-y-auto">
                                    <div x-show="mentionLoading" class="flex items-center gap-2 px-3 py-2.5 text-xs text-gray-400">
                                        <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        Searching…
                                    </div>
                                    <template x-for="(m, idx) in mentionResults" :key="m.type + ':' + m.id">
                                        <button type="button" @click="selectMention(m)"
                                                :class="mentionFocusIdx === idx ? 'bg-[#F0EFFA]' : 'hover:bg-gray-50'"
                                                class="w-full flex items-center gap-2.5 px-3 py-2 text-left transition-colors">
                                            <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                                                 :class="{'bg-purple-100 text-purple-700':m.type==='tenant_admin','bg-blue-100 text-blue-700':m.type==='referrer','bg-orange-100 text-orange-700':m.type==='partner','bg-gray-100 text-gray-600':m.type==='contact'}"
                                                 x-text="(m.name||'?').slice(0,2).toUpperCase()"></div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-medium text-[#1E1B4B] truncate" x-text="m.name"></p>
                                                <p class="text-[10px] text-gray-400 truncate" x-text="m.email || ''"></p>
                                            </div>
                                            <span class="text-[10px] px-1.5 py-0.5 rounded-full font-semibold shrink-0"
                                                  :class="{'bg-purple-100 text-purple-700':m.type==='tenant_admin','bg-blue-100 text-blue-700':m.type==='referrer','bg-orange-100 text-orange-700':m.type==='partner','bg-gray-100 text-gray-600':m.type==='contact'}"
                                                  x-text="m.badge"></span>
                                        </button>
                                    </template>
                                    <div x-show="!mentionLoading && mentionResults.length === 0" class="px-3 py-3 text-xs text-gray-400 text-center">No results.</div>
                                </div>
                            </div>

                            {{-- Selected mention pills --}}
                            <div x-show="mentions.length > 0" class="flex flex-wrap gap-1.5">
                                <template x-for="(m, i) in mentions" :key="m.type + ':' + m.id">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium"
                                          :class="{'bg-purple-100 text-purple-700':m.type==='tenant_admin','bg-blue-100 text-blue-700':m.type==='referrer','bg-orange-100 text-orange-700':m.type==='partner','bg-gray-100 text-gray-600':m.type==='contact'}">
                                        @<span x-text="m.name"></span>
                                        <button @click="mentions.splice(i,1)" class="ml-0.5 opacity-60 hover:opacity-100">Ã—</button>
                                    </span>
                                </template>
                            </div>

                            {{-- File previews --}}
                            <div x-show="selectedFiles.length > 0" class="space-y-1">
                                <template x-for="(f, i) in selectedFiles" :key="i">
                                    <div class="flex items-center gap-2 px-2.5 py-1.5 bg-gray-50 border border-gray-100 rounded-lg">
                                        <svg class="w-3.5 h-3.5 shrink-0" :class="f.type.startsWith('image/') ? 'text-blue-400' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                        <span class="text-xs text-gray-600 truncate flex-1" x-text="f.name"></span>
                                        <span class="text-[10px] text-gray-400 shrink-0" x-text="formatFileSize(f.size)"></span>
                                        <button type="button" @click="removeFile(i)" aria-label="Remove file" class="text-gray-300 hover:text-red-400 shrink-0">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </template>
                            </div>

                            {{-- Success banner --}}
                            <div x-show="noteSaved" x-transition style="display:none"
                                 class="flex items-center gap-2 px-3 py-2 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-700 font-medium">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                Note saved successfully.
                            </div>

                            {{-- Error banner --}}
                            <div x-show="commentError" style="display:none"
                                 class="flex items-start gap-2 px-3 py-2 bg-red-50 border border-red-200 rounded-xl text-xs text-red-700">
                                <svg class="w-3.5 h-3.5 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span x-text="commentError"></span>
                            </div>

                            {{-- Toolbar --}}
                            <div class="flex items-center justify-between gap-2">
                                <label class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 bg-gray-50 hover:bg-purple-50 hover:border-purple-200 hover:text-purple-700 text-xs text-gray-500 font-medium transition-colors"
                                       title="Attach PDFs, documents, spreadsheets, or images (max 10 MB, 5 files)">
                                    <input type="file" multiple class="sr-only" x-ref="fileInput" @change="handleFiles($event)"
                                           accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                    Attach files
                                    <span x-show="selectedFiles.length > 0" class="px-1.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-700" x-text="selectedFiles.length"></span>
                                </label>
                                <button @click="postComment()"
                                        :disabled="(!newBody.trim() && selectedFiles.length === 0) || posting"
                                        class="btn-primary text-xs py-1.5 px-4 flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <svg x-show="posting" class="w-3 h-3 animate-spin shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    <span x-text="posting ? 'Saving…' : 'Save Note'"></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Notes list --}}
                    <div x-show="loadingComments" class="flex items-center gap-2 text-gray-400 text-sm py-4 justify-center">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Loading notes…
                    </div>
                    <div x-show="!loadingComments && comments.length === 0" class="flex flex-col items-center text-center py-8 text-gray-300">
                        <svg class="w-8 h-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <p class="text-sm">No notes yet. Add the first update for this deal.</p>
                    </div>
                    <div x-show="!loadingComments && comments.length" class="space-y-4">
                        <template x-for="c in comments" :key="c.id">
                            <div class="flex gap-3 group/note">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0 mt-0.5"
                                     :class="{'bg-blue-100 text-blue-700':c.author_role==='referrer','bg-orange-100 text-orange-700':c.author_role==='partner','bg-purple-100 text-purple-700':!['referrer','partner'].includes(c.author_role)}"
                                     x-text="(c.author_name||'?').slice(0,2).toUpperCase()"></div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2 mb-1">
                                        <span class="text-sm font-semibold text-[#1E1B4B]" x-text="c.author_name"></span>
                                        <span class="text-[10px] text-gray-400 capitalize" x-text="c.author_role.replace(/_/g,' ')"></span>
                                        <template x-if="c.is_internal"><span class="px-1.5 rounded text-[10px] font-bold bg-amber-100 text-amber-700">Internal</span></template>
                                        <span class="text-[10px] text-gray-300" x-text="c.created_at ? new Date(c.created_at).toLocaleString('en',{month:'short',day:'numeric',hour:'numeric',minute:'2-digit'}) : ''"></span>
                                        <span x-show="c.edited_at" class="text-[10px] text-gray-300 italic">edited</span>
                                    </div>
                                    <template x-if="c.is_deleted"><p class="text-sm text-gray-300 italic">This note was deleted.</p></template>
                                    <template x-if="!c.is_deleted">
                                        <div class="space-y-2">
                                            <p class="text-sm text-gray-700 whitespace-pre-wrap break-words" x-text="c.body"></p>
                                            <div x-show="(c.mentions||[]).length > 0" class="flex flex-wrap gap-1">
                                                <template x-for="m in (c.mentions||[])" :key="m.id">
                                                    <span class="text-xs px-1.5 rounded-full font-medium"
                                                          :class="{'bg-purple-100 text-purple-700':m.type==='tenant_admin','bg-blue-100 text-blue-700':m.type==='referrer','bg-orange-100 text-orange-700':m.type==='partner','bg-gray-100 text-gray-600':m.type==='contact'}"
                                                          x-text="'@'+m.name"></span>
                                                </template>
                                            </div>
                                            <div x-show="(c.attachments||[]).length > 0" class="space-y-1">
                                                <template x-for="a in (c.attachments||[])" :key="a.id">
                                                    <a :href="a.download_url" target="_blank"
                                                       class="flex items-center gap-2 px-2.5 py-1.5 bg-gray-50 border border-gray-100 rounded-lg hover:bg-purple-50 hover:border-purple-100 transition-colors group/att">
                                                        <svg class="w-3.5 h-3.5 shrink-0" :class="a.file_type_group==='image'?'text-blue-400':'text-gray-400 group-hover/att:text-purple-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                                        <span class="text-xs text-gray-600 truncate flex-1 group-hover/att:text-purple-700" x-text="a.original_filename"></span>
                                                        <span class="text-[10px] text-gray-400 shrink-0" x-text="formatFileSize(a.file_size)"></span>
                                                        <svg class="w-3 h-3 text-gray-300 group-hover/att:text-purple-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                    </a>
                                                </template>
                                            </div>
                                            <div class="flex items-center gap-2 opacity-0 group-hover/note:opacity-100 transition-opacity mt-0.5">
                                                <button @click="startEdit(c)" class="text-[11px] text-gray-400 hover:text-[#7B61FF]">Edit</button>
                                                <button @click="deleteComment(c)" class="text-[11px] text-gray-400 hover:text-red-500">Delete</button>
                                            </div>
                                            <div x-show="editingId === c.id" class="mt-2 space-y-2">
                                                <textarea x-model="editBody" rows="2" class="form-input text-sm resize-none"></textarea>
                                                <div class="flex gap-2">
                                                    <button @click="saveEdit(c)" :disabled="posting" class="btn-primary text-xs py-1 px-2.5" x-text="posting ? 'Saving…' : 'Save'"></button>
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
                                    <div x-show="ei + 1 !== (lead?.history||[]).length" class="w-px flex-1 bg-gray-100 mt-1"></div>
                                </div>
                                <div class="pb-3 flex-1 min-w-0">
                                    <p class="text-sm text-gray-700" x-text="event.action"></p>
                                    <p class="text-xs text-gray-400 mt-0.5">
                                        <span x-show="event.reseller" x-text="event.reseller + ' Â· '"></span>
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
    <template x-teleport="body">
    <div x-show="showLinkContact" style="display:none"
         class="fixed inset-0 bg-black/50 z-[9999] flex items-end sm:items-center justify-center p-4"
         @keydown.escape.window="showLinkContact = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-[#1E1B4B]">Link Contact to Deal</h3>
                <button @click="showLinkContact = false; linkSearch = ''" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-5 space-y-3">
                <div class="search-group">
                    <svg @click="if(!allTenantContacts.length) fetchAllContacts()"
                         class="cursor-pointer hover:text-purple-600 transition-colors"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" x-model="linkSearch"
                           @input.debounce.200ms="if(!allTenantContacts.length) fetchAllContacts()"
                           placeholder="Click ðŸ” or type to search contacts…">
                </div>
                <div class="max-h-72 overflow-y-auto space-y-1">
                    <template x-if="allTenantContacts.length === 0">
                        <p class="text-center text-gray-400 text-sm py-6">Click the search icon or start typing to load contacts.</p>
                    </template>
                    <template x-if="allTenantContacts.length > 0 && linkableContacts().length === 0">
                        <p class="text-center text-gray-400 text-sm py-6">
                            <span x-text="'No matching contacts.'"></span>
                        </p>
                    </template>
                    <template x-for="c in linkableContacts()" :key="c.id">
                        <button @click="linkContact(c)"
                                :disabled="linkSaving"
                                class="flex items-center gap-3 w-full px-3 py-2.5 rounded-xl hover:bg-[#F0EFFA] transition-colors text-left group">
                            <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-purple-700 text-xs font-bold shrink-0"
                                 x-text="contactInitials(c)"></div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-[#1E1B4B] truncate" x-text="contactFullName(c)"></p>
                                <p class="text-xs text-gray-400 truncate" x-text="[c.job_title, c.org_name].filter(Boolean).join(' Â· ') || c.email || ''"></p>
                            </div>
                            <svg class="w-4 h-4 text-purple-400 opacity-0 group-hover:opacity-100 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </button>
                    </template>
                </div>
                <p class="text-xs text-gray-400 pt-1">
                    Can't find the contact?
                    <a href="{{ route('tenant.contacts', $tenant->id) }}" class="text-purple-600 hover:underline">Add them first</a>
                    in the Contacts module.
                </p>
            </div>
        </div>
    </div>
    </template>

    {{-- â”€â”€ Move Stage Modal â”€â”€ --}}
    <template x-teleport="body">
    <div x-show="showMoveStage" style="display:none"
         class="fixed inset-0 bg-black/50 z-[9999] flex items-end sm:items-center justify-center p-4"
         @keydown.escape.window="showMoveStage = false; moveStageNote = ''">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div>
                    <h3 class="font-semibold text-[#1E1B4B]">Move Stage</h3>
                    <p class="text-xs text-gray-400 mt-0.5">
                        <span x-text="lead?.name"></span>
                    </p>
                </div>
                <button @click="showMoveStage = false; moveStageNote = ''" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <p class="text-sm text-gray-500">
                    Current stage: <span class="font-semibold text-[#1E1B4B]" x-text="stageLabel(lead?.stage)"></span>
                </p>
                <div class="space-y-2">
                    <template x-for="s in allStages" :key="s.key">
                        <button @click="moveToStage(s.key)"
                                :disabled="s.key === lead?.stage || saving"
                                :class="s.key === lead?.stage
                                    ? 'opacity-50 cursor-not-allowed bg-gray-50 border-gray-100'
                                    : 'hover:bg-[#F0EFFA] hover:border-purple-200 cursor-pointer'"
                                class="w-full flex items-center gap-3 px-4 py-3 rounded-xl border border-gray-100 transition-all text-left">
                            <span :class="stageBadge(s.key)" class="shrink-0 text-xs" x-text="s.label"></span>
                            <span x-show="s.key === 'signed'" class="text-xs text-orange-600">→ locks commission at <span x-text="fmt(commPool())"></span></span>
                            <span x-show="s.key === 'paid'"   class="text-xs text-emerald-600">→ marks <span x-text="fmt(commPool())"></span> paid</span>
                            <span x-show="s.key === lead?.stage" class="ml-auto text-xs text-gray-400">current</span>
                            <svg x-show="saving && s.key !== lead?.stage" class="w-3.5 h-3.5 animate-spin text-purple-400 ml-auto shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        </button>
                    </template>
                </div>
                <div>
                    <label class="form-label">Note <span class="text-gray-400 font-normal">(optional)</span></label>
                    <textarea x-model="moveStageNote" rows="2" class="form-input text-sm resize-none"
                              placeholder="Reason for stage movement, e.g. 'Proposal sent to procurement office'…"></textarea>
                </div>
                <p class="text-xs text-gray-400">The note will be saved to the activity history.</p>
            </div>
        </div>
    </div>
    </template>

    {{-- Ã¢"â‚¬Ã¢"â‚¬ Reassign Modal Ã¢"â‚¬Ã¢"â‚¬ --}}
    <template x-teleport="body">
    <div x-show="showReassign" style="display:none" class="fixed inset-0 bg-black/50 z-[9999] flex items-center justify-center p-4">
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
    </template>


</div>

<script>
function dealComments(dealId, tenantId) {
    return {
        // â”€â”€ State â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        comments: [], loadingComments: true, posting: false,
        newBody: '', newVisibility: 'shared', commentError: '',
        noteSaved: false,          // inline success banner
        editingId: null, editBody: '',
        canPostInternal: true,     // tenant admin default; API enforces actual permission

        // Idempotency â€” generated once per component, rotated after each save
        clientRequestId: crypto.randomUUID ? crypto.randomUUID() : (Date.now().toString(36) + Math.random().toString(36)),

        // @Mention state
        mentions: [],
        mentionQuery: '',
        mentionResults: [],
        mentionOpen: false,
        mentionLoading: false,
        mentionFocusIdx: -1,
        mentionCursorStart: -1,
        mentionDebounceTimer: null,

        // File attachment state
        selectedFiles: [],

        // â”€â”€ Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        csrf() {
            return (document.querySelector('meta[name=csrf-token]') || {}).content || '';
        },

        // â”€â”€ Load notes â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        async loadComments() {
            this.loadingComments = true;
            try {
                const res = await fetch(`/api/deals/${dealId}/comments`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) throw new Error('load failed');
                const data = await res.json();
                this.comments = Array.isArray(data) ? data : [];
            } catch(e) { this.comments = []; }
            this.loadingComments = false;
        },

        // â”€â”€ Post note â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        async postComment() {
            // â‘  Hard duplicate guard â€” must be the very first check
            if (this.posting) return;
            if (!this.newBody.trim() && this.selectedFiles.length === 0) return;

            this.posting     = true;
            this.commentError = '';
            this.noteSaved   = false;

            try {
                const fd = new FormData();
                fd.append('body',              this.newBody);
                fd.append('visibility',        this.newVisibility);
                fd.append('mentions',          JSON.stringify(this.mentions));
                fd.append('client_request_id', this.clientRequestId);
                this.selectedFiles.forEach((f, i) => fd.append(`files[${i}]`, f));

                const res = await fetch(`/api/deals/${dealId}/comments`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': this.csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd,
                });

                let data = null;
                try { data = await res.json(); } catch(e) { data = null; }

                if (res.status === 419) {
                    this.commentError = 'Your session expired. Please refresh the page and try again.';
                } else if (res.status === 403) {
                    this.commentError = 'You do not have permission to add notes to this deal.';
                } else if (res.status === 422) {
                    this.commentError = (data?.error) || (data?.message) || 'Please review and correct the note.';
                } else if (!res.ok) {
                    this.commentError = data?.error || 'Unable to save note. Please try again.';
                } else if (data?.id) {
                    // â‘¡ Prevent duplicate in list â€” only add if not already present
                    if (!this.comments.find(c => c.id === data.id)) {
                        this.comments.unshift(data);
                    }
                    // â‘¢ Clear form
                    this.newBody       = '';
                    this.mentions      = [];
                    this.selectedFiles = [];
                    this.mentionOpen   = false;
                    if (this.$refs.fileInput) this.$refs.fileInput.value = '';
                    // â‘£ Rotate idempotency key for next note
                    this.clientRequestId = crypto.randomUUID ? crypto.randomUUID()
                        : (Date.now().toString(36) + Math.random().toString(36));
                    // â‘¤ Show both inline banner + toast
                    this.noteSaved = true;
                    setTimeout(() => { this.noteSaved = false; }, 4000);
                    this.$dispatch('show-toast', { type: 'success', message: 'Note saved.' });
                } else {
                    this.commentError = data?.error || 'Unable to save note. Please try again.';
                }
            } catch(e) {
                this.commentError = 'Network error. Please check your connection and try again.';
            } finally {
                this.posting = false;
            }
        },

        // â”€â”€ Edit â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        startEdit(c) { this.editingId = c.id; this.editBody = c.body; },

        async saveEdit(c) {
            if (!this.editBody.trim() || this.posting) return;
            this.posting = true;
            try {
                const res = await fetch(`/api/deals/${dealId}/comments/${c.id}`, {
                    method: 'PATCH',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ body: this.editBody }),
                });
                const data = await res.json();
                if (data?.id) {
                    const idx = this.comments.findIndex(x => x.id === c.id);
                    if (idx !== -1) this.comments.splice(idx, 1, data);
                    this.editingId = null;
                    this.$dispatch('show-toast', { type: 'success', message: 'Note updated.' });
                }
            } catch(e) {}
            this.posting = false;
        },

        // â”€â”€ Delete â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        async deleteComment(c) {
            if (!confirm('Delete this note?')) return;
            try {
                await fetch(`/api/deals/${dealId}/comments/${c.id}`, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': this.csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                });
                const idx = this.comments.findIndex(x => x.id === c.id);
                if (idx !== -1) this.comments[idx].is_deleted = true;
                this.$dispatch('show-toast', { type: 'success', message: 'Note deleted.' });
            } catch(e) {}
        },

        // â”€â”€ @Mention picker â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        handleBodyInput(e) {
            const ta     = e.target;
            const before = ta.value.substring(0, ta.selectionStart);
            const atIdx  = before.lastIndexOf('@');

            if (atIdx !== -1) {
                const q = before.substring(atIdx + 1);
                if (!q.includes(' ') && q.length <= 40) {
                    this.mentionCursorStart = atIdx;
                    this.mentionQuery       = q;
                    this.mentionFocusIdx    = 0;
                    clearTimeout(this.mentionDebounceTimer);
                    this.mentionDebounceTimer = setTimeout(() => {
                        this.mentionOpen = true;
                        this.fetchMentions(q);
                    }, 250);
                    return;
                }
            }
            this.mentionOpen = false;
        },

        async fetchMentions(q) {
            this.mentionLoading = true;
            try {
                const res = await fetch(`/api/deals/${dealId}/mentions/search?q=${encodeURIComponent(q)}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) throw new Error();
                const data = await res.json();
                this.mentionResults = Array.isArray(data) ? data : [];
            } catch(e) { this.mentionResults = []; }
            this.mentionLoading = false;
        },

        selectMention(m) {
            const ta     = this.$refs.noteTextarea;
            const value  = ta.value;
            const before = value.substring(0, this.mentionCursorStart);
            const after  = value.substring(ta.selectionStart);
            this.newBody = before + '@' + m.name + ' ' + after;

            if (!this.mentions.find(x => x.id === m.id && x.type === m.type)) {
                this.mentions.push(m);
            }
            this.mentionOpen     = false;
            this.mentionResults  = [];
            this.mentionFocusIdx = -1;
            this.$nextTick(() => { if (ta) { ta.focus(); const end = this.newBody.length; ta.setSelectionRange(end, end); } });
        },

        // â”€â”€ File attachments â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        handleFiles(e) {
            const files   = Array.from(e.target.files || []);
            const maxSize = 10 * 1024 * 1024;
            const allowed = ['jpg','jpeg','png','webp','pdf','doc','docx','xls','xlsx','csv','txt'];

            for (const f of files) {
                if (this.selectedFiles.length >= 5) {
                    this.$dispatch('show-toast', { type: 'error', message: 'Maximum 5 files per note.' });
                    break;
                }
                const ext = f.name.split('.').pop().toLowerCase();
                if (!allowed.includes(ext)) {
                    this.$dispatch('show-toast', { type: 'error', message: `${f.name}: file type not allowed.` });
                    continue;
                }
                if (f.size > maxSize) {
                    this.$dispatch('show-toast', { type: 'error', message: `${f.name}: exceeds the 10 MB limit.` });
                    continue;
                }
                // Prevent duplicate selection
                if (!this.selectedFiles.find(x => x.name === f.name && x.size === f.size)) {
                    this.selectedFiles.push(f);
                }
            }
            // Reset input so the same file can be re-selected after removal
            e.target.value = '';
        },

        removeFile(i) { this.selectedFiles.splice(i, 1); },

        formatFileSize(bytes) {
            if (!bytes) return '';
            if (bytes < 1024)      return bytes + ' B';
            if (bytes < 1048576)   return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        },
    };
}

function dealDetail(leadId, tenantId, ssrLead) {
    return {
        lead: ssrLead || null, loading: !ssrLead,
        showNoteForm: false, showMoveStage: false, showReassign: false,
        noteText: '', noteAuthor: '', saving: false, reassignName: '',
        moveStageNote: '',
        editFinance: false,
        financeForm: { deal_value: 0, base_cost: 0, added_amount: 0 },

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
            this.fetchContacts();
            if (this.lead) {
                // SSR data already present â€” page is instantly visible.
                // Refresh silently in background so any stale fields update.
                fetch(`/api/leads/${leadId}`, { credentials: 'same-origin' })
                    .then(r => r.ok ? r.json() : null)
                    .then(d => { if (d) this.lead = d; })
                    .catch(() => {});
            } else {
                try {
                    const res = await fetch(`/api/leads/${leadId}`, { credentials: 'same-origin' });
                    if (res.ok) this.lead = await res.json();
                } catch(e) { /* silent */ }
                this.loading = false;
            }
        },

        // Ã¢"â‚¬Ã¢"â‚¬ Financial helpers Ã¢"â‚¬Ã¢"â‚¬
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

        // ── LGU IDS pricing tier helpers ──
        lguBaseCost(dv) {
            dv = Math.round(Number(dv) || 0);
            if (dv <= 6_000_000)  return Math.round(dv * 0.60);
            if (dv <= 12_000_000) return Math.round(dv * 0.58);
            if (dv <= 15_000_000) return Math.round(dv * 0.48);
            return Math.round(dv * 0.41);
        },
        tierLabel(dv) {
            dv = Number(dv) || 0;
            if (dv <= 6_000_000)  return '60% base';
            if (dv <= 12_000_000) return '58% base';
            if (dv <= 15_000_000) return '48% base';
            return '41% base';
        },

        // Called when Deal Value input changes — recalculate base_cost and added_amount
        onDealValueChange() {
            const dv = Math.round(Number(this.financeForm.deal_value) || 0);
            const bc = this.lguBaseCost(dv);
            this.financeForm.base_cost    = bc;
            this.financeForm.added_amount = Math.max(0, dv - bc);
        },

        // Called when Added Amount input changes — update deal_value and recalculate base_cost
        onAddedAmountChange() {
            const aa    = Math.round(Number(this.financeForm.added_amount) || 0);
            const bc    = this.financeForm.base_cost;
            const newDv = bc + aa;
            const newBc = this.lguBaseCost(newDv);
            this.financeForm.deal_value = newDv;
            this.financeForm.base_cost  = newBc;
            // If the new deal_value crossed a tier boundary, adjust added_amount to remain consistent
            if (newBc !== bc) {
                this.financeForm.added_amount = Math.max(0, newDv - newBc);
            }
        },

        fmt(v) {
            // null/undefined/empty = not set → show dash; explicit 0 → show ₱0
            if (v === null || v === undefined || v === '') return '—';
            const n = Math.round(Number(v) || 0);
            return '₱' + n.toLocaleString('en');
        },

        startEditFinance() {
            const bc = Number(this.lead?.base_cost    || 0);
            const aa = Number(this.lead?.added_amount || 0);
            const dv = (bc + aa) || Number(this.lead?.deal_value || 0);
            this.financeForm = { deal_value: dv, base_cost: bc, added_amount: aa };
            this.editFinance = true;
        },

        cancelEditFinance() { this.editFinance = false; },

        async saveFinance() {
            this.saving = true;
            try {
                const bc   = Math.round(Number(this.financeForm.base_cost)    || 0);
                const aa   = Math.round(Number(this.financeForm.added_amount) || 0);
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
                    // Update the PHP-rendered financial display elements directly
                    const _bc = Math.round(Number(updated.base_cost    || 0));
                    const _aa = Math.round(Number(updated.added_amount || 0));
                    const _cv = (_bc + _aa) || Math.round(Number(updated.deal_value || 0));
                    const _p  = n => '₱' + n.toLocaleString('en');
                    [['fin-bc',_bc],['fin-aa',_aa],['fin-cv',_cv],['fin-cv2',_cv],
                     ['fin-co',Math.round(_aa*.3)],['fin-cp',Math.round(_aa*.7)]].forEach(([id,v]) => {
                        const el = document.getElementById(id);
                        if (el) el.textContent = _p(v);
                    });
                    this.editFinance = false;
                    this.$dispatch('show-toast', { type: 'success', message: 'Financial data saved.' });
                } else {
                    this.$dispatch('show-toast', { type: 'error', message: updated.message || 'Failed to save financial data.' });
                }
            } catch(e) {
                this.$dispatch('show-toast', { type: 'error', message: 'Network error. Please try again.' });
            } finally { this.saving = false; }
        },

        // Ã¢"â‚¬Ã¢"â‚¬ Stage helpers Ã¢"â‚¬Ã¢"â‚¬
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
            return m[s] || (s || 'â€”');
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

        // â”€â”€ Contact helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        contactFullName(c) { return [c.first_name, c.last_name].filter(Boolean).join(' ') || 'â€”'; },
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
            } catch(e) { this.dealContacts = []; this.$dispatch('show-toast', { type: 'error', message: 'Failed to load contacts.' }); }
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

    }
}

// â”€â”€ Partner Split Section â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function partnerSplitSection(dealId, tenantId) {
    return {
        splits: [], loading: true, showAdd: false, saving: false, formError: '',
        totalPct: 0,
        dealValue: 0,
        totalPctClass() { return this.totalPct > 100 ? 'text-red-600 font-bold' : 'text-gray-700 font-medium'; },
        hasPctSplits() { return this.splits.some(function(s) { return s.split_share_type === 'percentage'; }); },
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
            } catch(e) { this.splits = []; this.$dispatch('show-toast', { type: 'error', message: 'Failed to load partner splits.' }); }
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

// â”€â”€ Extension Request Section â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function extensionRequestSection(dealId, tenantId) {
    return {
        requests: [], loading: true, saving: false, actionError: null,
        showExtendForm: false, extendError: '',
        extendForm: { days: 14, reason: '' },
        hasPendingRequests() { return this.requests.some(function(r) { return r.status === 'pending_review'; }); },

        async load() {
            this.loading = true;
            try {
                const res = await fetch(`/api/leads/${dealId}/extension-requests?tenant_id=${tenantId}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (res.ok) this.requests = await res.json();
            } catch(e) { this.requests = []; }
            this.loading = false;
        },

        async adminExtend() {
            this.extendError = '';
            this.saving = true;
            try {
                const csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
                // Create the request
                const createRes = await fetch(`/api/leads/${dealId}/extension-requests`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({
                        requested_days: this.extendForm.days,
                        reason: this.extendForm.reason || 'Extended by admin.',
                        acknowledged: true,
                        tenant_id: tenantId,
                    }),
                });
                const created = await createRes.json();
                if (!createRes.ok) {
                    this.extendError = created.error || 'Failed to create extension.';
                    return;
                }
                // Auto-approve it immediately
                const approveRes = await fetch(`/api/extension-requests/${created.id}/approve`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ approved_days: this.extendForm.days, tenant_id: tenantId }),
                });
                if (approveRes.ok) {
                    const extendedDays = this.extendForm.days || created.requested_days;
                    this.showExtendForm = false;
                    this.extendForm = { days: 14, reason: '' };
                    await this.load();
                    this.$dispatch('show-toast', { type: 'success', message: `Assignment extended by ${extendedDays} days.` });
                } else {
                    this.extendError = 'Extension created but auto-approval failed. Approve it manually below.';
                    await this.load();
                }
            } catch(e) {
                this.extendError = 'Network error. Please try again.';
            } finally { this.saving = false; }
        },

        async approveRequest(id, approvedDays) {
            this.saving = true; this.actionError = null;
            try {
                const csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
                const res  = await fetch(`/api/extension-requests/${id}/approve`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ approved_days: approvedDays, tenant_id: tenantId }),
                });
                if (res.ok) {
                    await this.load();
                    this.$dispatch('show-toast', { type: 'success', message: 'Extension approved.' });
                } else {
                    this.actionError = id;
                    this.$dispatch('show-toast', { type: 'error', message: 'Failed to approve.' });
                }
            } catch(e) {
                this.actionError = id;
                this.$dispatch('show-toast', { type: 'error', message: 'Network error.' });
            } finally { this.saving = false; }
        },

        async rejectRequest(id) {
            if (!confirm('Deny this extension request?')) return;
            this.saving = true; this.actionError = null;
            try {
                const csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
                const res  = await fetch(`/api/extension-requests/${id}/reject`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ tenant_id: tenantId }),
                });
                if (res.ok) {
                    await this.load();
                    this.$dispatch('show-toast', { type: 'success', message: 'Extension denied.' });
                } else {
                    this.actionError = id;
                    this.$dispatch('show-toast', { type: 'error', message: 'Failed to deny.' });
                }
            } catch(e) {
                this.actionError = id;
                this.$dispatch('show-toast', { type: 'error', message: 'Network error.' });
            } finally { this.saving = false; }
        },
    };
}
</script>
@endsection



