@extends('layouts.app')
@section('title', 'Deal Detail')
@section('nav') @include('tenant._nav') @endsection

@section('topbar-actions')
    <a href="{{ route('tenant.tasks', [$tenant->id]) }}?create=1&source_type=deal&source_id={{ $dealId }}"
       class="btn-secondary text-sm" style="text-decoration:none;display:flex;align-items:center;gap:6px">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        <span class="hidden sm:inline">New Task</span>
    </a>
    <button onclick="window.dispatchEvent(new CustomEvent('open-move-stage-deal'))" class="btn-secondary text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        Move Stage
    </button>
    <button onclick="window.dispatchEvent(new CustomEvent('open-reassign-deal'))" class="btn-secondary text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
        Reassign
    </button>
@endsection

@section('content')
<script>var __dealSsrLead = @json($ssrLead ?? null);</script>
<style>
/* Financial breakdown layout â€" guaranteed, no Tailwind compile dependency */
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

        {{-- Deal Progress — redesigned 3D workflow --}}
        <div class="card">

            {{-- Header --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
                <div>
                    <h3 class="font-bold text-[#1E1B4B]" style="font-size:15px">Deal Progress</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Track this deal from introduction to payment.</p>
                </div>
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                    {{-- Move Stage button — always rendered; disabled only when paid or no lead --}}
                    <button @click="window.dispatchEvent(new CustomEvent('open-move-stage-deal'))"
                            :disabled="lead?.stage === 'paid' || !lead"
                            style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:12px;font-size:12px;font-weight:600;color:white;cursor:pointer;border:none;transition:opacity .15s,transform .1s;background:linear-gradient(135deg,#7B61FF,#5b4cdb);box-shadow:0 4px 14px rgba(123,97,255,0.3)"
                            :style="lead?.stage === 'paid' || !lead ? 'opacity:0.4;cursor:not-allowed' : 'opacity:1;cursor:pointer'"
                            aria-label="Move this deal to the next stage">
                        <svg style="width:13px;height:13px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        Move Stage
                    </button>
                </div>
            </div>

            {{-- ── Desktop / Tablet: horizontal stage cards ── --}}
            <div class="hidden sm:block overflow-x-auto pb-1">
                <div class="flex items-stretch" style="min-width:520px;gap:0">
                    <template x-for="(s, i) in allStages" :key="s.key">
                        <div class="flex items-center flex-1 min-w-0">

                            {{-- Stage card — clickable for future/next stages --}}
                            <div class="relative flex flex-col items-center justify-between gap-2 py-4 px-2 rounded-2xl flex-1 min-w-0 transition-all duration-200"
                                 :style="stageCardStyle(s.key) + (!isStageDone(s.key) && s.key !== lead?.stage ? ';cursor:pointer' : ';cursor:default')"
                                 :aria-current="s.key === lead?.stage ? 'step' : null"
                                 :title="!isStageDone(s.key) && s.key !== lead?.stage ? 'Click to advance to ' + s.label : null"
                                 @click="if (!isStageDone(s.key) && s.key !== lead?.stage) window.dispatchEvent(new CustomEvent('open-move-stage-deal'))">

                                {{-- Status label (top) --}}
                                <div style="height:14px;display:flex;align-items:center;justify-content:center">
                                    <template x-if="isStageDone(s.key)">
                                        <span style="font-size:9px;font-weight:700;letter-spacing:0.08em;color:#16a34a;text-transform:uppercase">Done</span>
                                    </template>
                                    <template x-if="s.key === lead?.stage">
                                        <span style="font-size:9px;font-weight:700;letter-spacing:0.08em;color:rgba(255,255,255,0.9);text-transform:uppercase">Current</span>
                                    </template>
                                    <template x-if="isStageNext(s.key)">
                                        <span style="font-size:9px;font-weight:700;letter-spacing:0.08em;color:#7B61FF;text-transform:uppercase">Next</span>
                                    </template>
                                </div>

                                {{-- Icon circle --}}
                                <div style="width:38px;height:38px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .2s"
                                     :style="stageIconBgStyle(s.key)">
                                    <template x-if="isStageDone(s.key)">
                                        <svg style="width:17px;height:17px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </template>
                                    <template x-if="!isStageDone(s.key)">
                                        <svg style="width:15px;height:15px" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                             x-html="stageIconHtml(s)"></svg>
                                    </template>
                                </div>

                                {{-- Stage label --}}
                                <span style="font-size:11px;font-weight:600;text-align:center;line-height:1.3;width:100%;padding:0 4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                                      :style="stageLabelStyle(s.key)"
                                      x-text="s.label"></span>
                            </div>

                            {{-- Arrow connector --}}
                            <template x-if="i + 1 < allStages.length">
                                <div style="width:20px;flex-shrink:0;display:flex;align-items:center;justify-content:center" aria-hidden="true">
                                    <svg style="width:13px;height:13px;flex-shrink:0;transition:color .2s"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                         :style="isStageDone(allStages[i+1]?.key) || allStages[i+1]?.key === lead?.stage
                                             ? 'color:#7B61FF;opacity:0.6' : 'color:#d1d5db'">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </div>
                            </template>

                        </div>
                    </template>
                </div>
            </div>

            {{-- ── Mobile: vertical timeline ── --}}
            <div class="flex flex-col sm:hidden" role="list" aria-label="Deal stage progress">
                <template x-for="(s, i) in allStages" :key="s.key + '-mob'">
                    <div class="flex items-start gap-3" role="listitem">

                        {{-- Timeline: dot + connector line --}}
                        <div style="width:34px;flex-shrink:0;display:flex;flex-direction:column;align-items:center">
                            <div style="width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .2s"
                                 :style="stageMobileCircleStyle(s.key) + (!isStageDone(s.key) && s.key !== lead?.stage ? ';cursor:pointer' : ';cursor:default')"
                                 @click="if (!isStageDone(s.key) && s.key !== lead?.stage) window.dispatchEvent(new CustomEvent('open-move-stage-deal'))">
                                <template x-if="isStageDone(s.key)">
                                    <svg style="width:15px;height:15px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </template>
                                <template x-if="!isStageDone(s.key)">
                                    <svg style="width:14px;height:14px" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                         x-html="stageIconHtml(s)"></svg>
                                </template>
                            </div>
                            <div x-show="i + 1 < allStages.length"
                                 style="width:2px;border-radius:9999px;margin-top:4px;flex:1;min-height:20px"
                                 :style="isStageDone(allStages[i+1]?.key) || allStages[i+1]?.key === lead?.stage
                                     ? 'background:rgba(123,97,255,0.3)' : 'background:#e5e7eb'"></div>
                        </div>

                        {{-- Stage info --}}
                        <div class="flex-1 min-w-0" :style="i + 1 < allStages.length ? 'padding-bottom:14px' : 'padding-bottom:4px'">
                            <div style="margin-top:6px;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                                <span style="font-size:13px;font-weight:600"
                                      :style="s.key === lead?.stage ? 'color:#7B61FF'
                                          : isStageDone(s.key) ? 'color:#15803d' : 'color:#9ca3af'"
                                      x-text="s.label"></span>
                                <template x-if="s.key === lead?.stage">
                                    <span style="font-size:10px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;padding:2px 8px;border-radius:9999px;background:#ede9fe;color:#7B61FF">Current</span>
                                </template>
                                <template x-if="isStageDone(s.key)">
                                    <span style="font-size:10px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;padding:2px 8px;border-radius:9999px;background:#dcfce7;color:#16a34a">Done</span>
                                </template>
                                <template x-if="isStageNext(s.key)">
                                    <span style="font-size:10px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;padding:2px 8px;border-radius:9999px;background:#f5f3ff;color:#7B61FF">Next</span>
                                </template>
                            </div>
                            <p x-show="s.key === lead?.stage && (lead?.days_left ?? null) !== null"
                               style="font-size:11px;color:#9ca3af;margin-top:2px"
                               x-text="(lead?.days_left ?? 0) + ' day(s) remaining'"></p>
                        </div>
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

                    {{-- â"˜ Formula explainer (orange, always visible) --}}
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
                                ReferralBunny.ai separates the total contract value into the actual base cost and the added amount (your margin). The <span class="font-semibold text-blue-600">company share</span> is 30% of the added amount, while the <span class="font-semibold text-emerald-600">referrer commission pool</span> is 70% of the added amount. This lets everyone see exactly how the contract value, company share, and commission pool are calculated â€" before commissions are locked or paid.
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

            {{-- View mode (PHP-rendered for instant SSR, no Alpine x-text flash) --}}
            @php
                $bc = (float)($ssrLead['base_cost']    ?? 0);
                $aa = (float)($ssrLead['added_amount'] ?? 0);
                $dv = (float)($ssrLead['deal_value']   ?? 0);
                $cv = ($bc + $aa) ?: $dv;
                // Formula: Company Share = 30%, Commission Pool = 70% of Added Amount
                $co = round($aa * 0.30, 2);
                $cp = round($aa * 0.70, 2);
                $commStatus = $ssrLead['commission_status'] ?? 'pending';
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

                {{-- Commission Distribution — Referrer splits (Alpine-driven) --}}
                <div x-show="(lead?.commission_splits||[]).length > 0 || lead?.added_amount > 0">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                        <p style="font-size:11px;font-weight:700;color:#9ca3af;letter-spacing:.05em;text-transform:uppercase">Referrer Commission Distribution</p>
                        {{-- Commission status badge --}}
                        <span style="display:inline-flex;align-items:center;gap:4px;padding:2px 10px;border-radius:9999px;font-size:11px;font-weight:600"
                              :style="lead?.commission_status === 'paid'   ? 'background:#dcfce7;color:#15803d'
                                    : lead?.commission_status === 'locked' ? 'background:#fef3c7;color:#d97706'
                                    : 'background:#ede9fe;color:#7B61FF'"
                              x-text="lead?.commission_status === 'paid'   ? 'Paid'
                                    : lead?.commission_status === 'locked' ? 'Locked'
                                    : 'Pending'">{{ ucfirst($commStatus) }}</span>
                    </div>

                    {{-- Referrer split rows --}}
                    <div class="space-y-1.5">
                        <template x-for="split in (lead?.commission_splits||[])" :key="split.id">
                            <div style="display:flex;align-items:center;justify-content:space-between;background:#f9fafb;border-radius:12px;padding:10px 12px">
                                <div style="display:flex;align-items:center;gap:10px;min-width:0">
                                    <div style="width:28px;height:28px;border-radius:9999px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0"
                                         :style="split.role==='primary' ? 'background:#dcfce7;color:#16a34a'
                                               : split.role==='secondary' ? 'background:#dbeafe;color:#2563eb'
                                               : 'background:#f3f4f6;color:#6b7280'"
                                         x-text="(split.reseller_name||'?').slice(0,2).toUpperCase()"></div>
                                    <div style="min-width:0">
                                        <p style="font-size:13px;font-weight:600;color:#1E1B4B;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="split.reseller_name"></p>
                                        <p style="font-size:11px;color:#9ca3af;text-transform:capitalize" x-text="split.role + ' referrer'"></p>
                                    </div>
                                </div>
                                <div style="text-align:right;flex-shrink:0;margin-left:12px">
                                    <p style="font-size:13px;font-weight:700;color:#16a34a" x-text="fmt(commPool() * split.percentage / 100)"></p>
                                    <p style="font-size:11px;color:#9ca3af" x-text="split.percentage + '% of pool'"></p>
                                </div>
                            </div>
                        </template>

                        {{-- No splits message --}}
                        <template x-if="(lead?.commission_splits||[]).length === 0 && (lead?.added_amount||0) > 0">
                            <div style="padding:10px 12px;background:#fffbeb;border:1.5px dashed #fcd34d;border-radius:12px;font-size:12px;color:#d97706">
                                Commission pool unallocated — no referrer split assigned.
                            </div>
                        </template>

                        {{-- Unallocated amount warning (uses dealDetail scope directly) --}}
                        <template x-if="(lead?.commission_splits||[]).length > 0">
                            <div>
                                <div>
                                    {{-- totalAllocatedPct computed inline in Alpine using dealDetail data --}}
                                    <div x-show="(lead?.commission_splits||[]).reduce((s,r) => s + parseFloat(r.percentage||0), 0) < 99.9"
                                         style="display:flex;justify-content:space-between;align-items:center;font-size:11px;color:#9ca3af;padding:6px 0 0">
                                        <span>Unallocated pool</span>
                                        <span style="color:#d97706;font-weight:600"
                                              x-text="fmt(commPool() * (100 - (lead?.commission_splits||[]).reduce((s,r) => s + parseFloat(r.percentage||0), 0)) / 100)"></span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Locked/Paid notice --}}
                    <template x-if="lead?.commission_status === 'locked'">
                        <div style="display:flex;align-items:center;gap:6px;margin-top:8px;padding:8px 10px;background:#fffbeb;border:1px solid #fde68a;border-radius:10px">
                            <svg style="width:13px;height:13px;color:#d97706;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            <p style="font-size:11px;color:#d97706;font-weight:600">Commission locked. Contact admin to make changes.</p>
                        </div>
                    </template>
                    <template x-if="lead?.commission_status === 'paid'">
                        <div style="display:flex;align-items:center;gap:6px;margin-top:8px;padding:8px 10px;background:#f0fdf4;border:1px solid #86efac;border-radius:10px">
                            <svg style="width:13px;height:13px;color:#16a34a;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                            </svg>
                            <p style="font-size:11px;color:#16a34a;font-weight:600">Commission paid.</p>
                        </div>
                    </template>
                </div>

                {{-- No financial data notice --}}
                @if(!$aa)
                <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800">
                    No financial data set yet. Click <strong>Edit</strong> to enter Base Cost and Added Amount.
                </div>
                @endif

            </div>

            {{-- Edit mode --}}
            <div x-show="editFinance" style="display:none" class="space-y-5">

                @if($showLocation ?? false)
                {{-- LGU IDS: deal-value-first with auto-locked base cost --}}
                <div class="flex items-start gap-2 p-3 bg-purple-50 border border-purple-100 rounded-xl">
                    <svg class="w-3.5 h-3.5 text-purple-400 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                    <p class="text-xs text-purple-700 leading-relaxed">Enter the total contract value first. Base cost is auto-locked by the LGU IDS pricing tier. Adjust the added amount (margin) if needed — the deal value will update to stay consistent.</p>
                </div>

                {{-- Deal Value (primary input) --}}
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;color:#1E1B4B;margin-bottom:4px">
                        Deal Value (₱) <span style="font-weight:400;color:#9ca3af;font-size:11px">— total contract amount</span>
                    </label>
                    <input type="number" x-model.number="financeForm.deal_value"
                           x-on:input="onDealValueChange()"
                           style="display:block;width:100%;padding:10px 14px;border:2px solid #7B61FF;border-radius:12px;font-size:16px;font-weight:600;color:#1E1B4B;background:white;outline:none;box-sizing:border-box"
                           placeholder="4000000" min="0" step="100">
                    <p style="font-size:10px;color:#7B61FF;margin-top:4px">LGU IDS default: ₱4,000,000</p>
                </div>

                {{-- Base Cost (locked) + Added Amount (editable) --}}
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                    <div>
                        <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:4px">
                            Base Cost (₱) <span style="font-weight:400;color:#9ca3af">— LGU IDS tier</span>
                        </label>
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border:1px solid #e5e7eb;border-radius:12px;background:#f9fafb;cursor:not-allowed;user-select:none">
                            <span style="font-size:14px;font-weight:600;color:#374151;font-variant-numeric:tabular-nums" x-text="fmt(financeForm.base_cost)"></span>
                            <span style="font-size:10px;font-weight:700;color:#7B61FF;background:#ede9fe;border-radius:20px;padding:2px 8px;white-space:nowrap;margin-left:8px"
                                  x-text="tierLabel(financeForm.deal_value)"></span>
                        </div>
                        <p style="font-size:10px;color:#9ca3af;margin-top:4px">Auto-locked · not editable</p>
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:4px">
                            Added Amount (₱) <span style="font-weight:400;color:#9ca3af">— margin</span>
                        </label>
                        <input type="number" x-model.number="financeForm.added_amount"
                               x-on:input="onAddedAmountChange()"
                               style="display:block;width:100%;padding:10px 14px;border:1px solid #e5e7eb;border-radius:12px;font-size:14px;color:#1E1B4B;background:white;outline:none;box-sizing:border-box"
                               placeholder="0" min="0" step="100">
                        <p style="font-size:10px;color:#9ca3af;margin-top:4px">Edit to adjust — deal value updates</p>
                    </div>
                </div>

                @else
                {{-- Generic tenant: manual base cost + added amount --}}
                <p style="font-size:12px;color:#6b7280">Enter the deal financials. Contract Value, Company Share, and Commission Pool are calculated automatically.</p>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                    <div>
                        <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:4px">
                            Base Cost (₱) <span style="font-weight:400;color:#9ca3af">— actual delivery cost</span>
                        </label>
                        <input type="number" x-model.number="financeForm.base_cost" x-on:input="recalc()"
                               style="display:block;width:100%;padding:10px 14px;border:1px solid #e5e7eb;border-radius:12px;font-size:14px;color:#1E1B4B;background:white;outline:none;box-sizing:border-box"
                               placeholder="0" min="0" step="100">
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:4px">
                            Added Amount (₱) <span style="font-weight:400;color:#9ca3af">— your margin</span>
                        </label>
                        <input type="number" x-model.number="financeForm.added_amount" x-on:input="recalc()"
                               style="display:block;width:100%;padding:10px 14px;border:1px solid #e5e7eb;border-radius:12px;font-size:14px;color:#1E1B4B;background:white;outline:none;box-sizing:border-box"
                               placeholder="0" min="0" step="100">
                    </div>
                </div>
                @endif

                {{-- Live preview --}}
                <div class="p-4 rounded-2xl space-y-3" style="background:linear-gradient(135deg,#F5F3FF,#F0FDF4)">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Live Preview</p>

                    <div class="grid grid-cols-3 gap-3">
                        <div class="bg-white rounded-xl p-3 text-center shadow-sm">
                            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Contract Value</p>
                            <p class="text-base font-bold tabular-nums" style="color:#1E1B4B" x-text="fmt(previewContract())"></p>
                            <p class="text-xs text-gray-400 mt-0.5">base + margin</p>
                        </div>
                        <div class="bg-white rounded-xl p-3 text-center shadow-sm">
                            <p class="text-xs uppercase tracking-wide mb-1" style="color:#1d4ed8">Company Share</p>
                            <p class="text-base font-bold tabular-nums" style="color:#1d4ed8" x-text="fmt(previewCompanyShare())"></p>
                            <p class="text-xs mt-0.5" style="color:#60a5fa">30% of margin</p>
                        </div>
                        <div class="bg-white rounded-xl p-3 text-center shadow-sm">
                            <p class="text-xs uppercase tracking-wide mb-1" style="color:#059669">Commission Pool</p>
                            <p class="text-base font-bold tabular-nums" style="color:#059669" x-text="fmt(previewCommPool())"></p>
                            <p class="text-xs mt-0.5" style="color:#34d399">70% of margin</p>
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

                <div style="display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap">
                    <button @click="cancelEditFinance()"
                            style="display:inline-flex;align-items:center;padding:9px 20px;border-radius:12px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:13px;font-weight:600;cursor:pointer;transition:background .15s"
                            @mouseenter="$event.currentTarget.style.background='#f9fafb'"
                            @mouseleave="$event.currentTarget.style.background='white'">Cancel</button>
                    <button @click="saveFinance()" :disabled="saving"
                            style="display:inline-flex;align-items:center;padding:9px 20px;border-radius:12px;border:none;background:#7B61FF;color:white;font-size:13px;font-weight:600;cursor:pointer;transition:opacity .15s"
                            :style="saving ? 'opacity:0.6;cursor:not-allowed' : 'opacity:1;cursor:pointer'"
                            x-text="saving ? 'Saving…' : 'Save Financial Data'"></button>
                </div>
            </div>
        </div>
        <script>
        // Apply financial layout â€" JS setProperty bypasses ALL CSS blocking
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
                        <span class="font-medium text-gray-700" x-text="lead?.reseller_name || 'â€"'"></span>
                    </div>
                    @if($showLocation ?? false)
                    <div class="flex justify-between text-sm py-1.5 border-b border-gray-50">
                        <span class="text-gray-400">Province</span>
                        <span class="font-medium text-gray-700" x-text="lead?.data?.province || 'â€"'"></span>
                    </div>
                    <div class="flex justify-between text-sm py-1.5 border-b border-gray-50">
                        <span class="text-gray-400">Municipality</span>
                        <span class="font-medium text-gray-700" x-text="lead?.data?.municipality || 'â€"'"></span>
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

            {{-- Commission Split Share --}}
            <div class="card space-y-3"
                 x-data="partnerSplitSection('{{ $dealId }}', '{{ $tenant->id }}')"
                 x-init="load()"
                 @finance-updated.window="load()">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-[#1E1B4B] text-sm">Commission Split Share</h3>
                    <button @click="showAdd = !showAdd" class="text-xs text-purple-600 hover:text-purple-700 font-medium flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add Partner
                    </button>
                </div>
                <p class="text-[11px] text-gray-400">The referrer holds the full commission pool. Partners receive a share from the referrer's pool.</p>

                {{-- Loading --}}
                <div x-show="loading" class="flex items-center gap-2 text-gray-400 text-xs py-2">
                    <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Loading…
                </div>

                <div x-show="!loading" class="space-y-1.5">

                    {{-- Referrer row (top — default holder of full commission pool) --}}
                    <div x-show="commPool > 0 || referrerName"
                         style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:linear-gradient(135deg,#f5f3ff,#ede9fe);border-radius:14px;border:1.5px solid #c4b5fd">
                        <div style="width:32px;height:32px;border-radius:9999px;background:#7B61FF;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:white;flex-shrink:0"
                             x-text="(referrerName||'R').slice(0,2).toUpperCase()"></div>
                        <div style="flex:1;min-width:0">
                            <div style="display:flex;align-items:center;gap:6px">
                                <p style="font-size:13px;font-weight:700;color:#1E1B4B;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"
                                   x-text="referrerName || 'Referrer'"></p>
                                <span style="font-size:9px;font-weight:700;color:#7B61FF;background:#ede9fe;padding:1px 7px;border-radius:9999px;letter-spacing:.04em;text-transform:uppercase;flex-shrink:0">Referrer</span>
                            </div>
                            <p style="font-size:11px;color:#7B61FF;margin-top:1px">Full commission pool — net after partner allocations</p>
                        </div>
                        <div style="text-align:right;flex-shrink:0">
                            {{-- Gross (full pool) --}}
                            <p style="font-size:12px;color:#9ca3af;text-decoration:line-through;line-height:1"
                               x-show="splits.length > 0"
                               x-text="'₱' + Math.round(commPool).toLocaleString('en-PH')"></p>
                            {{-- Net (after partner deductions) --}}
                            <p style="font-size:14px;font-weight:700;color:#7B61FF;line-height:1.3"
                               x-text="'₱' + Math.round(remainingPool()).toLocaleString('en-PH')"></p>
                            <p style="font-size:10px;color:#9ca3af;margin-top:2px"
                               x-text="splits.length > 0 ? 'net share' : 'full pool'"></p>
                        </div>
                    </div>

                    {{-- Partner split rows --}}
                    <template x-if="splits.length > 0">
                        <div>
                            <p style="font-size:10px;font-weight:700;color:#9ca3af;letter-spacing:.06em;text-transform:uppercase;padding:8px 4px 4px">
                                Partner Allocations
                            </p>
                            <template x-for="s in splits" :key="s.id">
                                <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:#f8f7ff;border-radius:12px;border:1.5px solid #e9e5ff;margin-bottom:6px">
                                    {{-- Avatar initials --}}
                                    <div style="width:32px;height:32px;border-radius:9999px;background:#ede9fe;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#7B61FF;flex-shrink:0"
                                         x-text="(s.partner_name||'P').slice(0,2).toUpperCase()"></div>
                                    <div style="flex:1;min-width:0">
                                        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                                            <p style="font-size:13px;font-weight:600;color:#1E1B4B;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"
                                               x-text="s.partner_name"></p>
                                            <span style="font-size:9px;font-weight:700;padding:1px 7px;border-radius:9999px;text-transform:uppercase;letter-spacing:.04em;flex-shrink:0"
                                                  :style="s.status === 'active'
                                                      ? 'background:#dcfce7;color:#16a34a'
                                                      : 'background:#f3f4f6;color:#9ca3af'"
                                                  x-text="s.status === 'active' ? 'Active' : s.status_label || 'Provisional'"></span>
                                        </div>
                                        <p style="font-size:11px;color:#9ca3af;margin-top:1px" x-text="s.partner_email"></p>
                                    </div>
                                    <div style="text-align:right;flex-shrink:0">
                                        <p style="font-size:14px;font-weight:700;color:#7B61FF"
                                           x-text="'₱' + splitPesoAmount(s).toLocaleString('en-PH')"></p>
                                        <p style="font-size:10px;color:#9ca3af;margin-top:1px"
                                           x-text="s.split_share_type === 'percentage'
                                               ? parseFloat(s.split_share_value) + '% of pool'
                                               : 'fixed'"></p>
                                        <button @click="removeSplit(s.id)"
                                                style="font-size:10px;color:#9ca3af;cursor:pointer;background:none;border:none;margin-top:3px;display:block;margin-left:auto;padding:0">Remove</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- No partners yet --}}
                    <template x-if="splits.length === 0 && !showAdd && commPool > 0">
                        <p style="font-size:12px;color:#9ca3af;padding:4px 2px">No partner splits — referrer keeps the full pool.</p>
                    </template>
                    <template x-if="splits.length === 0 && !showAdd && !commPool">
                        <p style="font-size:12px;color:#9ca3af;padding:4px 2px">Set deal financials to see commission split.</p>
                    </template>
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
                                            <p class="text-xs font-medium text-[#1E1B4B] truncate" x-text="[c.first_name, c.last_name].filter(Boolean).join(' ') || c.name || 'â€"'"></p>
                                            <p class="text-[10px] text-gray-400 truncate" x-text="c.email || c.job_title || ''"></p>
                                        </div>
                                    </button>
                                </template>
                                <div x-show="contactOptions.length === 0 && !loadingContacts && !contactLoadError"
                                     class="px-3 py-4 text-center text-xs text-gray-400"
                                     x-text="contactQuery ? 'No matching contacts.' : 'Click ðŸ" or type to search contacts.'"></div>
                            </div>
                        </div>
                    </div>

                    {{-- Selected contact pill --}}
                    <div x-show="contactSelected" class="flex items-center gap-2 p-2 border border-purple-200 rounded-xl bg-purple-50">
                        <div class="w-6 h-6 rounded-full bg-purple-200 flex items-center justify-center text-purple-700 text-[10px] font-bold shrink-0"
                             x-text="([contactSelected?.first_name, contactSelected?.last_name].filter(Boolean).join(' ') || contactSelected?.name || '?').slice(0,2).toUpperCase()"></div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-[#1E1B4B] truncate" x-text="[contactSelected?.first_name, contactSelected?.last_name].filter(Boolean).join(' ') || contactSelected?.name || 'â€"'"></p>
                            <p class="text-[10px] text-gray-400 truncate" x-text="contactSelected?.email || ''"></p>
                        </div>
                        <button type="button" @click="clearContact()" class="text-gray-400 hover:text-gray-600 shrink-0">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    {{-- Duplicate partner warning --}}
                    <div x-show="isDuplicate()" style="display:flex;align-items:flex-start;gap:8px;padding:10px 12px;background:#fef2f2;border:1.5px solid #fecaca;border-radius:12px">
                        <svg style="width:14px;height:14px;color:#dc2626;flex-shrink:0;margin-top:1px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <div>
                            <p style="font-size:12px;font-weight:700;color:#dc2626">Partner already added</p>
                            <p style="font-size:11px;color:#6b7280;margin-top:2px">This email already has a split on this deal. Remove the existing entry first, or edit it to adjust the amount.</p>
                        </div>
                    </div>

                    {{-- Share amount row --}}
                    <div class="space-y-1.5">
                        {{-- Remaining pool info --}}
                        <div x-show="commPool > 0" style="display:flex;justify-content:space-between;align-items:center;font-size:10px;margin-bottom:2px">
                            <span style="color:#9ca3af">Available commission pool</span>
                            <span :style="remainingPool() <= 0 ? 'color:#dc2626;font-weight:700' : 'color:#7B61FF;font-weight:600'"
                                  x-text="'₱' + Math.max(0, Math.round(remainingPool())).toLocaleString('en-PH')"></span>
                        </div>
                        <div style="display:flex;gap:8px;align-items:stretch">
                            <div style="position:relative;flex:1">
                                <input type="number"
                                       x-model.number="form.split_share_value"
                                       x-on:input="enforceMax()"
                                       style="display:block;width:100%;padding:9px 36px 9px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;color:#1E1B4B;background:white;outline:none;box-sizing:border-box;-moz-appearance:textfield"
                                       placeholder="0"
                                       min="0"
                                       :max="form.split_share_type === 'percentage' ? maxPct() : commPool">
                                <span style="position:absolute;inset-y:0;right:10px;display:flex;align-items:center;font-size:13px;font-weight:700;color:#7B61FF;pointer-events:none"
                                      x-text="form.split_share_type === 'percentage' ? '%' : '₱'"></span>
                            </div>
                            <select x-model="form.split_share_type"
                                    x-on:change="form.split_share_value = 0"
                                    style="padding:9px 10px;border:1px solid #d1d5db;border-radius:10px;font-size:12px;color:#1E1B4B;background:white;width:130px;flex-shrink:0;cursor:pointer">
                                <option value="percentage">Percentage</option>
                                <option value="fixed_amount">Fixed Amount</option>
                            </select>
                        </div>
                        {{-- Hints --}}
                        <div x-show="form.split_share_value > 0 && dealValue > 0" style="font-size:11px;color:#7B61FF;display:flex;align-items:center;gap:4px">
                            <template x-if="form.split_share_type === 'percentage'">
                                <span x-text="'= ₱' + Math.round(dealValue * form.split_share_value / 100).toLocaleString('en-PH') + ' of contract value'"></span>
                            </template>
                            <template x-if="form.split_share_type === 'fixed_amount'">
                                <span x-text="'~ ' + (dealValue > 0 ? (form.split_share_value / dealValue * 100).toFixed(1) : '0') + '% of contract value'"></span>
                            </template>
                        </div>
                        {{-- Over-cap warning --}}
                        <p x-show="isOverCap()" style="font-size:11px;color:#dc2626;font-weight:600">
                            Exceeds the referrer commission pool. Max allowed: <span x-text="form.split_share_type === 'percentage' ? maxPct() + '%' : '₱' + commPool.toLocaleString('en-PH')"></span>
                        </p>
                    </div>
                    <p x-show="formError" class="text-xs text-red-600" x-text="formError"></p>
                    <div style="display:flex;gap:8px">
                        <button @click="showAdd = false; clearContact(); formError = ''"
                                style="flex:1;padding:9px;border-radius:12px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:12px;font-weight:600;cursor:pointer">Cancel</button>
                        <button @click="addSplit()" :disabled="saving || isOverCap() || isDuplicate() || remainingPool() <= 0"
                                style="flex:1;padding:9px;border-radius:12px;border:none;background:#7B61FF;color:white;font-size:12px;font-weight:600;cursor:pointer;transition:opacity .15s"
                                :style="(saving || isOverCap() || isDuplicate() || remainingPool() <= 0) ? 'opacity:0.4;cursor:not-allowed' : 'opacity:1'"
                                x-text="saving ? 'Saving...' : 'Add Split'"></button>
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
                                          :placeholder="newVisibility === 'internal_admin' ? 'Internal note â€" only visible to Tenant Admins and Managers. Type @ to tag someone…' : 'Write a note about this deal. Type @ to tag a teammate, Referrer, Partner, or Contact…'"></textarea>

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

                {{-- Activity History — self-contained component, does not rely on dealDetail scope --}}
                <div class="card" x-data="dealActivityHistory(@json($ssrLead['history'] ?? []))">

                    {{-- Header + filters --}}
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px">
                        <h3 class="font-semibold text-[#1E1B4B] text-sm">Activity History</h3>
                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                            <template x-for="f in ahFilters" :key="f.key">
                                <button @click="ahFilter = f.key"
                                        :style="ahFilter === f.key
                                            ? 'background:#7B61FF;color:white;border-color:#7B61FF'
                                            : 'background:white;color:#6b7280;border-color:#e5e7eb'"
                                        style="padding:3px 12px;border-radius:9999px;border:1.5px solid;font-size:11px;font-weight:600;cursor:pointer;transition:all .15s"
                                        x-text="f.label">
                                </button>
                            </template>
                        </div>
                    </div>

                    {{-- Event list --}}
                    <div class="space-y-1">
                        {{-- Empty state --}}
                        <template x-if="ahFiltered().length === 0">
                            <div style="text-align:center;padding:28px 12px;color:#9ca3af;font-size:13px">
                                <svg style="width:32px;height:32px;margin:0 auto 10px;opacity:0.35" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                                <p style="font-weight:600;color:#6b7280;margin-bottom:4px" x-text="ahFilter === 'all' ? 'No activity recorded yet.' : 'No ' + ahFilter + ' activity yet.'"></p>
                                <p style="font-size:11px">Activity will appear here when stage, financial, partner, or commission changes are made.</p>
                            </div>
                        </template>

                        <template x-for="(event, ei) in ahVisible()" :key="event.id || ei">
                            <div style="display:flex;gap:10px;padding-bottom:0">

                                {{-- Icon column --}}
                                <div style="display:flex;flex-direction:column;align-items:center;flex-shrink:0">
                                    <div style="width:30px;height:30px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0"
                                         :style="ahIconStyle(event)">
                                        <svg style="width:14px;height:14px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <template x-if="event.type === 'stage' || event.category === 'stage'">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </template>
                                            <template x-if="event.type === 'partner' || event.category === 'partner'">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </template>
                                            <template x-if="event.type === 'financial' || event.category === 'financial'">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </template>
                                            <template x-if="event.type === 'commission' || event.category === 'commission'">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                                            </template>
                                            <template x-if="event.type === 'assignment' || event.category === 'assignment'">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                                            </template>
                                            <template x-if="event.type === 'import' || event.category === 'import'">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                            </template>
                                            <template x-if="!event.type && !event.category || event.type === 'deal'">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </template>
                                        </svg>
                                    </div>
                                    <div x-show="ei + 1 < ahVisible().length"
                                         style="width:1px;flex:1;background:#f3f4f6;margin-top:4px;min-height:12px"></div>
                                </div>

                                {{-- Content --}}
                                <div style="flex:1;min-width:0;padding-bottom:16px">

                                    {{-- Action text + actor --}}
                                    <p style="font-size:13px;color:#374151;line-height:1.5;margin-bottom:2px" x-text="event.action"></p>

                                    {{-- Actor + timestamp row --}}
                                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:0">
                                        {{-- Actor name badge (new field) --}}
                                        <template x-if="event.actor_name">
                                            <span style="font-size:10px;font-weight:600;padding:1px 8px;border-radius:9999px;background:#f5f3ff;color:#7B61FF"
                                                  x-text="event.actor_name + (event.actor_role ? ' · ' + event.actor_role : '')"></span>
                                        </template>
                                        {{-- Fallback: reseller field from old records --}}
                                        <template x-if="!event.actor_name && event.reseller">
                                            <span style="font-size:10px;font-weight:600;padding:1px 8px;border-radius:9999px;background:#f5f3ff;color:#7B61FF"
                                                  x-text="event.reseller + ' · Referrer'"></span>
                                        </template>
                                        {{-- Timestamp --}}
                                        <span style="font-size:10px;color:#9ca3af"
                                              x-text="ahDate(event)"></span>
                                    </div>

                                    {{-- Old/New value change card --}}
                                    <template x-if="event.old_values || event.new_values">
                                        <div x-data="{ showChanges: false }">
                                            <button @click="showChanges = !showChanges"
                                                    style="font-size:10px;color:#7B61FF;cursor:pointer;background:none;border:none;padding:3px 0;font-weight:600;display:flex;align-items:center;gap:3px;margin-top:4px">
                                                <svg style="width:10px;height:10px;transition:transform .15s" :style="showChanges ? 'transform:rotate(90deg)' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                                                </svg>
                                                <span x-text="showChanges ? 'Hide changes' : 'View changes'"></span>
                                            </button>
                                            <div x-show="showChanges" style="display:none">
                                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:4px">
                                                    <template x-if="event.old_values">
                                                        <div style="padding:8px 10px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px">
                                                            <p style="font-size:9px;font-weight:700;color:#9ca3af;letter-spacing:.06em;text-transform:uppercase;margin-bottom:4px">Before</p>
                                                            <template x-for="[k, v] in Object.entries(event.old_values || {})" :key="k">
                                                                <div style="font-size:11px;color:#374151;margin-bottom:2px">
                                                                    <span style="color:#9ca3af;text-transform:capitalize" x-text="k.replace(/_/g,' ') + ': '"></span>
                                                                    <span style="font-weight:600" x-text="typeof v === 'number' ? v.toLocaleString('en-PH') : (v || '—')"></span>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </template>
                                                    <template x-if="event.new_values">
                                                        <div style="padding:8px 10px;background:#f0fdf4;border:1px solid #86efac;border-radius:8px">
                                                            <p style="font-size:9px;font-weight:700;color:#9ca3af;letter-spacing:.06em;text-transform:uppercase;margin-bottom:4px">After</p>
                                                            <template x-for="[k, v] in Object.entries(event.new_values || {})" :key="k">
                                                                <div style="font-size:11px;color:#374151;margin-bottom:2px">
                                                                    <span style="color:#9ca3af;text-transform:capitalize" x-text="k.replace(/_/g,' ') + ': '"></span>
                                                                    <span style="font-weight:600;color:#16a34a" x-text="typeof v === 'number' ? v.toLocaleString('en-PH') : (v || '—')"></span>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- View more / View less --}}
                    <template x-if="ahFiltered().length > ahPageSize">
                        <div style="text-align:center;padding-top:8px;border-top:1px solid #f3f4f6;margin-top:4px">
                            <button @click="ahShowAll = !ahShowAll"
                                    style="font-size:12px;font-weight:600;color:#7B61FF;background:none;border:none;cursor:pointer"
                                    x-text="ahShowAll ? 'Show less' : 'View all ' + ahFiltered().length + ' events'">
                            </button>
                        </div>
                    </template>
                    {{-- @window-event: refresh history when stage/financial changes happen --}}
                    <span x-on:finance-updated.window="refreshHistory()" style="display:none"></span>
                    <span x-on:stage-updated.window="refreshHistory()" style="display:none"></span>

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
                <div style="display:flex;align-items:center;gap:8px;padding:10px 14px;border:1px solid #e5e7eb;border-radius:12px;background:white">
                    <svg @click="fetchAllContacts()"
                         style="width:16px;height:16px;color:#9ca3af;cursor:pointer;flex-shrink:0"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" x-model="linkSearch"
                           @input.debounce.200ms="fetchAllContacts()"
                           style="flex:1;border:none;outline:none;font-size:14px;color:#1E1B4B;background:transparent"
                           placeholder="Search contacts...">
                </div>
                <div class="max-h-72 overflow-y-auto space-y-1">
                    {{-- Loading state --}}
                    <template x-if="loadingAllContacts">
                        <div style="display:flex;align-items:center;justify-content:center;gap:8px;padding:24px;color:#9ca3af;font-size:13px">
                            <svg class="animate-spin" style="width:16px;height:16px;flex-shrink:0" fill="none" viewBox="0 0 24 24">
                                <circle style="opacity:0.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path style="opacity:0.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            Loading contacts...
                        </div>
                    </template>
                    {{-- Empty — not loading --}}
                    <template x-if="!loadingAllContacts && allTenantContacts.length === 0">
                        <div style="text-align:center;padding:24px 12px">
                            <p style="color:#9ca3af;font-size:13px;margin-bottom:10px">No contacts found.</p>
                            <button @click="fetchAllContacts()"
                                    style="font-size:12px;color:#7B61FF;background:#ede9fe;border:none;padding:6px 16px;border-radius:8px;cursor:pointer;font-weight:600">
                                Retry
                            </button>
                            <a href="{{ route('tenant.contacts', $tenant->id) }}"
                               style="display:block;margin-top:8px;color:#9ca3af;font-size:11px;text-decoration:underline">
                                Add contacts in the Contacts module
                            </a>
                        </div>
                    </template>
                    {{-- No match for search --}}
                    <template x-if="!loadingAllContacts && allTenantContacts.length > 0 && linkableContacts().length === 0">
                        <p style="text-align:center;color:#9ca3af;font-size:13px;padding:24px 12px">No contacts match your search.</p>
                    </template>
                    {{-- Contact list --}}
                    <template x-for="c in linkableContacts()" :key="c.id">
                        <button @click="linkContact(c)"
                                :disabled="linkSaving"
                                style="display:flex;align-items:center;gap:12px;width:100%;padding:10px 12px;border-radius:12px;text-align:left;background:white;border:none;cursor:pointer;transition:background .15s"
                                @mouseenter="$event.currentTarget.style.background='#F0EFFA'"
                                @mouseleave="$event.currentTarget.style.background='white'">
                            <div style="width:34px;height:34px;border-radius:9999px;background:#ede9fe;display:flex;align-items:center;justify-content:center;color:#7B61FF;font-size:12px;font-weight:700;flex-shrink:0"
                                 x-text="contactInitials(c)"></div>
                            <div style="flex:1;min-width:0">
                                <p style="font-size:13px;font-weight:600;color:#1E1B4B;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="contactFullName(c)"></p>
                                <p style="font-size:11px;color:#9ca3af;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="[c.job_title, c.org_name].filter(Boolean).join(' · ') || c.email || ''"></p>
                            </div>
                            <svg style="width:14px;height:14px;color:#7B61FF;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
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

    {{-- Move Stage Modal --}}
    <template x-teleport="body">
    <div x-show="showMoveStage" style="display:none;background:rgba(0,0,0,0.5)"
         class="fixed inset-0 z-[9999] flex items-end sm:items-center justify-center p-4"
         @keydown.escape.window="showMoveStage = false; moveStageNote = ''"
         @click.self="showMoveStage = false; moveStageNote = ''"
         role="dialog" aria-modal="true" aria-label="Move Stage">
        <div class="bg-white rounded-2xl w-full max-w-sm" style="box-shadow:0 25px 60px rgba(0,0,0,0.18)" @click.stop>

            {{-- Modal header --}}
            <div style="padding:20px 24px 16px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between">
                <div style="display:flex;align-items:center;gap:12px">
                    <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#7B61FF,#5b4cdb);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <svg style="width:16px;height:16px;color:white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </div>
                    <div>
                        <h3 style="font-weight:700;color:#1E1B4B;font-size:15px">Move Stage</h3>
                        <p style="font-size:11px;color:#9ca3af;margin-top:1px" x-text="lead?.name"></p>
                    </div>
                </div>
                <button @click="showMoveStage = false; moveStageNote = ''"
                        style="width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#9ca3af;transition:all .15s"
                        class="hover:bg-gray-100 hover:text-gray-600">
                    <svg style="width:16px;height:16px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Current stage indicator --}}
            <div style="padding:12px 24px 0;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <span style="font-size:12px;color:#9ca3af">Current:</span>
                <span style="font-size:12px;font-weight:700;color:#7B61FF;background:#ede9fe;padding:2px 10px;border-radius:9999px"
                      x-text="stageLabel(lead?.stage)"></span>
                {{-- Commission locked banner --}}
                <template x-if="lead?.commission_status === 'locked'">
                    <span style="font-size:11px;font-weight:600;background:#fef3c7;color:#d97706;padding:2px 10px;border-radius:9999px;display:flex;align-items:center;gap:4px">
                        <svg style="width:11px;height:11px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Commission locked — only Paid move allowed
                    </span>
                </template>
                <template x-if="lead?.stage === 'paid'">
                    <span style="font-size:11px;font-weight:600;background:#dcfce7;color:#15803d;padding:2px 10px;border-radius:9999px">
                        Final stage — no further moves
                    </span>
                </template>
            </div>

            {{-- Stage selector list --}}
            <div style="padding:12px 24px;display:flex;flex-direction:column;gap:6px">
                <template x-for="s in allStages" :key="s.key">
                    <button @click="moveToStage(s.key)"
                            :disabled="s.key === lead?.stage || saving || isStageDone(s.key) || lead?.stage === 'paid' || (lead?.commission_status === 'locked' && s.key !== 'paid')"
                            class="w-full text-left transition-all"
                            style="display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:14px;border:1.5px solid #f3f4f6;cursor:pointer;background:white"
                            :style="s.key === lead?.stage
                                ? 'opacity:0.6;cursor:not-allowed;background:#f9fafb'
                                : isStageDone(s.key)
                                    ? 'border-color:#86efac;background:#f0fdf4;opacity:0.7;cursor:not-allowed'
                                    : (lead?.commission_status === 'locked' && s.key !== 'paid')
                                        ? 'opacity:0.4;cursor:not-allowed;border-color:#f3f4f6;background:#f9fafb'
                                        : isStageNext(s.key)
                                            ? 'border-color:#c4b5fd;background:#f5f3ff'
                                            : 'border-color:#f3f4f6;background:white'"
                            @mouseenter="if(s.key !== lead?.stage && !saving) $event.currentTarget.style.borderColor='#c4b5fd'"
                            @mouseleave="if(s.key !== lead?.stage) $event.currentTarget.style.borderColor = isStageDone(s.key) ? '#86efac' : isStageNext(s.key) ? '#c4b5fd' : '#f3f4f6'">

                        {{-- Stage icon dot --}}
                        <div style="width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0"
                             :style="s.key === lead?.stage ? 'background:#f3f4f6;color:#9ca3af'
                                 : isStageDone(s.key) ? 'background:#dcfce7;color:#16a34a'
                                 : isStageNext(s.key) ? 'background:#ede9fe;color:#7B61FF'
                                 : 'background:#f3f4f6;color:#9ca3af'">
                            <template x-if="isStageDone(s.key)">
                                <svg style="width:13px;height:13px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                            </template>
                            <template x-if="!isStageDone(s.key)">
                                <svg style="width:12px;height:12px" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                     x-html="stageIconHtml(s)"></svg>
                            </template>
                        </div>

                        {{-- Label + hint --}}
                        <div class="flex-1 min-w-0">
                            <span style="font-size:13px;font-weight:600;color:#1E1B4B;display:block" x-text="s.label"></span>
                            <span x-show="s.key === 'signed'" style="font-size:11px;color:#d97706;display:block">
                                Locks commission at <span x-text="fmt(commPool())"></span>
                            </span>
                            <span x-show="s.key === 'paid'" style="font-size:11px;color:#16a34a;display:block">
                                Marks <span x-text="fmt(commPool())"></span> as paid
                            </span>
                        </div>

                        {{-- Current / spinner --}}
                        <template x-if="s.key === lead?.stage">
                            <span style="font-size:10px;font-weight:600;color:#9ca3af;flex-shrink:0">Current</span>
                        </template>
                        <template x-if="isStageNext(s.key) && !saving">
                            <svg style="width:14px;height:14px;color:#7B61FF;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </template>
                        <svg x-show="saving && s.key !== lead?.stage"
                             style="width:14px;height:14px;flex-shrink:0;color:#7B61FF"
                             class="animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                    </button>
                </template>
            </div>

            {{-- Optional note + footer --}}
            <div style="padding:0 24px 20px;display:flex;flex-direction:column;gap:10px">
                <textarea x-model="moveStageNote" rows="2"
                          style="width:100%;padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:12px;font-size:13px;color:#1E1B4B;background:white;outline:none;resize:none;box-sizing:border-box;font-family:inherit"
                          placeholder="Optional note — reason for stage movement..."></textarea>
                <p style="font-size:11px;color:#9ca3af">Note is saved to the activity history.</p>
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
                <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:4px">New Referrer Name *</label>
                <input type="text" x-model="reassignName"
                       style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:12px;font-size:14px;color:#1E1B4B;background:white;outline:none;box-sizing:border-box"
                       placeholder="Referrer full name">
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button @click="showReassign = false; reassignName = ''"
                        style="display:inline-flex;align-items:center;padding:9px 20px;border-radius:12px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:13px;font-weight:600;cursor:pointer">Cancel</button>
                <button @click="reassign()" :disabled="!reassignName || saving"
                        style="display:inline-flex;align-items:center;padding:9px 20px;border-radius:12px;border:none;background:#FF5733;color:white;font-size:13px;font-weight:600;cursor:pointer;transition:opacity .15s"
                        :style="(!reassignName || saving) ? 'opacity:0.5;cursor:not-allowed' : 'opacity:1;cursor:pointer'"
                        x-text="saving ? 'Reassigning…' : 'Confirm Reassign'"></button>
            </div>
        </div>
    </div>
    </template>


</div>

<script>
// ── Self-contained Activity History component ─────────────────────────────
// Decoupled from dealDetail scope to avoid Alpine scope-chain lookup failures.
function dealActivityHistory(initialHistory) {
    return {
        history:    Array.isArray(initialHistory) ? initialHistory : [],
        ahFilter:   'all',
        ahPageSize: 8,
        ahShowAll:  false,
        ahFilters: [
            { key: 'all',        label: 'All'        },
            { key: 'stage',      label: 'Stage'      },
            { key: 'financial',  label: 'Financial'  },
            { key: 'partner',    label: 'Partner'    },
            { key: 'commission', label: 'Commission' },
            { key: 'assignment', label: 'Referrer'   },
            { key: 'import',     label: 'Import'     },
        ],

        ahFiltered() {
            const hist = [...this.history].reverse();
            if (this.ahFilter === 'all') return hist;
            return hist.filter(e => (e.category || e.type || '') === this.ahFilter);
        },
        ahVisible() {
            const f = this.ahFiltered();
            return this.ahShowAll ? f : f.slice(0, this.ahPageSize);
        },
        ahIconStyle(event) {
            const t = event.category || event.type || '';
            const m = {
                stage:      'background:#ede9fe;color:#7B61FF',
                partner:    'background:#dbeafe;color:#2563eb',
                financial:  'background:#fef3c7;color:#d97706',
                commission: 'background:#dcfce7;color:#16a34a',
                assignment: 'background:#e0f2fe;color:#0284c7',
                import:     'background:#f3f4f6;color:#6b7280',
                deal:       'background:#f3f4f6;color:#6b7280',
            };
            return m[t] || 'background:#f3f4f6;color:#6b7280';
        },
        ahDate(event) {
            const ts = event.created_at || event.date;
            if (!ts) return '';
            try {
                const d = new Date(ts);
                return d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
                    + ' ' + d.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
            } catch { return ts; }
        },
        async refreshHistory() {
            // Called via window events (finance-updated, stage-updated)
            // Re-fetches just to get the latest history array
            try {
                const dealId = document.querySelector('[x-data*="dealDetail"]')
                    ?.__x?.$data?.lead?.id;
                if (!dealId) return;
                const res = await fetch(`/api/leads/${dealId}`, { credentials: 'same-origin' });
                if (res.ok) {
                    const data = await res.json();
                    this.history = Array.isArray(data.history) ? data.history : [];
                }
            } catch {}
        },
    };
}

function dealComments(dealId, tenantId) {
    return {
        // â"€â"€ State â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€
        comments: [], loadingComments: true, posting: false,
        newBody: '', newVisibility: 'shared', commentError: '',
        noteSaved: false,          // inline success banner
        editingId: null, editBody: '',
        canPostInternal: true,     // tenant admin default; API enforces actual permission

        // Idempotency â€" generated once per component, rotated after each save
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

        // â"€â"€ Helpers â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€
        csrf() {
            return (document.querySelector('meta[name=csrf-token]') || {}).content || '';
        },

        // â"€â"€ Load notes â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€
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

        // â"€â"€ Post note â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€
        async postComment() {
            // â‘  Hard duplicate guard â€" must be the very first check
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
                    // â‘¡ Prevent duplicate in list â€" only add if not already present
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

        // â"€â"€ Edit â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€
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

        // â"€â"€ Delete â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€
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

        // â"€â"€ @Mention picker â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€
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

        // â"€â"€ File attachments â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€
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
        showLinkContact: false, linkSearch: '', linkSaving: false, loadingAllContacts: false,

        allStages: [
            { key: 'introduction',  label: 'Introduction',  icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
            { key: 'presentation',  label: 'Presentation',  icon: 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z' },
            { key: 'contract_sent', label: 'Contract Sent', icon: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z' },
            { key: 'signed',        label: 'Signed',        icon: 'M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z' },
            { key: 'paid',          label: 'Paid',          icon: 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z' },
        ],

        async init() {
            this.fetchContacts();
            if (this.lead) {
                // SSR data already present â€" page is instantly visible.
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
            if (dv <= 6000000)  return Math.round(dv * 0.60);
            if (dv <= 12000000) return Math.round(dv * 0.58);
            if (dv <= 15000000) return Math.round(dv * 0.48);
            return Math.round(dv * 0.41);
        },
        tierLabel(dv) {
            dv = Number(dv) || 0;
            if (dv <= 6000000)  return '60% base';
            if (dv <= 12000000) return '58% base';
            if (dv <= 15000000) return '48% base';
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
                    this.$dispatch('finance-updated');
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

        // ── New workflow card helpers ──
        isStageNext(key) {
            const curIdx  = this.stageIdx(this.lead?.stage);
            const thisIdx = this.stageIdx(key);
            return thisIdx === curIdx + 1;
        },
        stageCardStyle(key) {
            const done = this.isStageDone(key);
            const cur  = key === this.lead?.stage;
            const next = this.isStageNext(key);
            if (cur)  return 'background:linear-gradient(135deg,#7B61FF,#5b4cdb);box-shadow:0 8px 25px rgba(123,97,255,0.35);transform:translateY(-2px)';
            if (done) return 'background:#f0fdf4;border:1.5px solid #86efac';
            if (next) return 'background:#f5f3ff;border:1.5px solid #c4b5fd';
            return 'background:#f9fafb;border:1.5px dashed #e5e7eb';
        },
        stageIconBgStyle(key) {
            const done = this.isStageDone(key);
            const cur  = key === this.lead?.stage;
            const next = this.isStageNext(key);
            if (cur)  return 'background:rgba(255,255,255,0.2);color:white';
            if (done) return 'background:#dcfce7;color:#16a34a';
            if (next) return 'background:#ede9fe;color:#7B61FF';
            return 'background:#f3f4f6;color:#9ca3af';
        },
        stageLabelStyle(key) {
            const done = this.isStageDone(key);
            const cur  = key === this.lead?.stage;
            const next = this.isStageNext(key);
            if (cur)  return 'color:white';
            if (done) return 'color:#15803d';
            if (next) return 'color:#6d28d9';
            return 'color:#9ca3af';
        },
        stageMobileCircleStyle(key) {
            const done = this.isStageDone(key);
            const cur  = key === this.lead?.stage;
            const next = this.isStageNext(key);
            if (cur)  return 'background:linear-gradient(135deg,#7B61FF,#5b4cdb);color:white;box-shadow:0 4px 12px rgba(123,97,255,0.4)';
            if (done) return 'background:#dcfce7;color:#16a34a';
            if (next) return 'background:#ede9fe;color:#7B61FF';
            return 'background:#f3f4f6;color:#d1d5db';
        },
        stageIconHtml(s) {
            return '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="' + s.icon + '"/>';
        },
        stageLabel(s) {
            const m = { introduction:'Introduction', presentation:'Presentation', contract_sent:'Contract Sent', signed:'Signed', paid:'Paid' };
            return m[s] || (s || 'â€"');
        },
        stageBadge(s) {
            const m = { introduction:'badge badge-gray', presentation:'badge badge-blue', contract_sent:'badge badge-orange', signed:'badge badge-purple', paid:'badge badge-green' };
            return m[s] || 'badge badge-gray';
        },

        async moveToStage(stage) {
            if (!stage || stage === this.lead?.stage) return;
            // Client-side guards (mirrors API guards for instant feedback)
            if (this.lead?.stage === 'paid') {
                this.$dispatch('show-toast', { type: 'error', message: 'This deal is at the final stage and cannot be advanced.' });
                return;
            }
            if (this.lead?.commission_status === 'locked' && stage !== 'paid') {
                this.$dispatch('show-toast', { type: 'error', message: 'Commission is locked. Only the Paid stage move is allowed.' });
                return;
            }

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
                const data = await res.json();
                if (res.ok && data.id) {
                    this.lead = { ...this.lead, ...data, history: data.history, commission_splits: data.commission_splits };
                    this.showMoveStage = false;
                    this.moveStageNote = '';
                    const stageName = stage.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                    this.$dispatch('show-toast', { type: 'success', message: `Deal moved to ${stageName}.` });
                    // Trigger activity history refresh
                    this.$dispatch('stage-updated');
                    window.dispatchEvent(new CustomEvent('finance-updated'));
                } else {
                    this.$dispatch('show-toast', { type: 'error', message: data.error || data.message || 'Failed to move stage.' });
                }
            } catch {
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

        // â"€â"€ Contact helpers â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€
        contactFullName(c) { return [c.first_name, c.last_name].filter(Boolean).join(' ') || 'â€"'; },
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
            if (this.loadingAllContacts) return;
            this.loadingAllContacts = true;
            try {
                const res = await fetch(`/api/contacts?per_page=200`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) throw new Error('contacts-api-' + res.status);
                const data = await res.json();
                // Handle both direct array and paginated {data:[...]} format
                this.allTenantContacts = Array.isArray(data) ? data : (data.data || []);
            } catch(e) {
                this.allTenantContacts = [];
                this.$dispatch('show-toast', { type: 'error', message: 'Could not load contacts. Please try again.' });
            }
            this.loadingAllContacts = false;
        },

        openLinkContact() {
            this.linkSearch = '';
            this.showLinkContact = true;
            if (!this.allTenantContacts.length) this.fetchAllContacts();
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

// -- Partner Split Section ------------------------------------------------------
function partnerSplitSection(dealId, tenantId) {
    return {
        splits: [], loading: true, showAdd: false, saving: false, formError: '',
        totalPct: 0,
        dealValue: 0,
        commPool: 0,
        referrerName: '',
        totalPctClass() { return this.totalPct > 100 ? 'text-red-600 font-bold' : 'text-gray-700 font-medium'; },
        hasPctSplits() { return this.splits.some(function(s) { return s.split_share_type === 'percentage'; }); },

        // Convert any split row to its peso equivalent
        splitPesoAmount(s) {
            const v = parseFloat(s.split_share_value) || 0;
            if (s.split_share_type === 'percentage') return Math.round(this.dealValue * v / 100);
            return Math.round(v);
        },

        // Total peso already allocated to existing splits
        allocatedPool() {
            return this.splits.reduce((sum, s) => sum + this.splitPesoAmount(s), 0);
        },

        // How much commission pool remains for new splits
        remainingPool() {
            return Math.max(0, this.commPool - this.allocatedPool());
        },

        // Max % a new split can take given remaining pool
        maxPct() {
            if (!this.dealValue || !this.commPool) return 100;
            const remaining = this.remainingPool();
            return Math.floor(remaining / this.dealValue * 100 * 100) / 100;
        },

        // True if new split would exceed remaining pool
        isOverCap() {
            const v = Number(this.form.split_share_value) || 0;
            if (this.form.split_share_type === 'percentage') return v > this.maxPct();
            return v > this.remainingPool();
        },

        // Auto-clamp value to max allowed
        enforceMax() {
            const v = Number(this.form.split_share_value) || 0;
            if (this.form.split_share_type === 'percentage') {
                const maxP = this.maxPct();
                if (v > maxP) this.form.split_share_value = maxP;
            } else {
                const rem = this.remainingPool();
                if (rem >= 0 && v > rem) this.form.split_share_value = Math.round(rem);
            }
        },

        // True if the selected partner email already has a split on this deal
        isDuplicate() {
            if (!this.form.partner_email.trim()) return false;
            const email = this.form.partner_email.toLowerCase().trim();
            return this.splits.some(function(s) { return (s.partner_email || '').toLowerCase() === email; });
        },
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
                    this.dealValue    = (bc + aa) || Number(lead.deal_value || 0);
                    this.commPool     = Math.round(aa * 0.70);
                    this.referrerName = lead.reseller_name || '';
                }
            } catch(e) { this.splits = []; this.$dispatch('show-toast', { type: 'error', message: 'Failed to load partner splits.' }); }
            this.loading = false;
        },

        async addSplit() {
            this.formError = '';
            if (!this.form.partner_name.trim()) { this.formError = 'Partner name is required.'; return; }
            if (!this.form.partner_email.trim()) { this.formError = 'Partner email is required.'; return; }
            if (this.form.split_share_value <= 0) { this.formError = 'Split share must be greater than 0.'; return; }
            if (this.isDuplicate()) {
                this.formError = 'This partner already has a split on this deal. Remove their existing entry first if you want to change it.';
                return;
            }
            if (this.remainingPool() <= 0) {
                this.formError = 'The referrer commission pool is fully allocated. Remove an existing split to free up space.';
                return;
            }
            if (this.isOverCap()) {
                const rem = Math.round(this.remainingPool());
                const limit = this.form.split_share_type === 'percentage'
                    ? this.maxPct() + '% (= ₱' + Math.round(this.dealValue * this.maxPct() / 100).toLocaleString('en-PH') + ')'
                    : '₱' + rem.toLocaleString('en-PH');
                this.formError = 'Exceeds the remaining commission pool. Max for this partner: ' + limit + '.';
                return;
            }
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

// â"€â"€ Extension Request Section â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€
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



