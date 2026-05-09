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
/* Financial breakdown layout — guaranteed, no Tailwind compile dependency */
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
                            <span class="text-[10px] text-gray-500 text-center mt-1.5 leading-tight" x-text="s.label"></span>
                        </div>
                        <div x-show="i + 1 !== allStages.length" class="flex-1 h-0.5 mx-1 transition-colors"
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

            {{-- â”€â”€ View mode (PHP-rendered — no Alpine x-text dependency) â”€â”€ --}}
            @php
                $bc = (float)($ssrLead['base_cost']    ?? 0);
                $aa = (float)($ssrLead['added_amount'] ?? 0);
                $dv = (float)($ssrLead['deal_value']   ?? 0);
                $cv = ($bc + $aa) ?: $dv;
                $co = $aa * 0.30;
                $cp = $aa * 0.70;
            @endphp
            <div :class=”editFinance ? 'hidden' : ''” class=”space-y-4”>

                {{-- Formula rows --}}
                <div>
                    <div class=”fin-row py-2.5 border-b border-gray-100”>
                        <p class=”text-sm text-gray-500”>₱ Base Cost</p>
                        <p id=”fin-bc” class=”text-sm font-semibold text-gray-700 tabular-nums”>₱{{ number_format((int)$bc) }}</p>
                    </div>
                    <div class=”fin-row py-2.5 border-b border-dashed border-gray-200”>
                        <p class=”text-sm text-gray-500”>+ Added Amount <span class=”text-xs text-gray-400”>(margin)</span></p>
                        <p id=”fin-aa” class=”text-sm font-semibold text-blue-600 tabular-nums”>₱{{ number_format((int)$aa) }}</p>
                    </div>
                    <div class=”fin-row py-2.5 rounded-xl px-3 mt-1” style=”background:#F0EFFA”>
                        <p class=”text-sm font-semibold” style=”color:#1E1B4B”>= Contract Value</p>
                        <p id=”fin-cv” class=”text-sm font-bold tabular-nums” style=”color:#1E1B4B”>₱{{ number_format((int)$cv) }}</p>
                    </div>
                </div>

                {{-- 3-column summary --}}
                <div class=”fin-grid-3 p-3 rounded-xl bg-gray-50 border border-gray-100”>
                    <div class=”text-center”>
                        <p class=”text-xs text-gray-400 uppercase tracking-wide”>Contract Value</p>
                        <p id=”fin-cv2” class=”text-sm font-bold tabular-nums mt-0.5” style=”color:#1E1B4B”>₱{{ number_format((int)$cv) }}</p>
                        <p class=”text-xs text-gray-400”>base + margin</p>
                    </div>
                    <div class=”text-center” style=”border-left:1px solid #e5e7eb;border-right:1px solid #e5e7eb”>
                        <p class=”text-xs text-blue-500 uppercase tracking-wide”>Company Share</p>
                        <p id=”fin-co” class=”text-sm font-bold text-blue-700 tabular-nums mt-0.5”>₱{{ number_format((int)$co) }}</p>
                        <p class=”text-xs text-blue-400”>30% margin</p>
                    </div>
                    <div class=”text-center”>
                        <p class=”text-xs text-emerald-500 uppercase tracking-wide”>Commission Pool</p>
                        <p id=”fin-cp” class=”text-sm font-bold text-emerald-700 tabular-nums mt-0.5”>₱{{ number_format((int)$cp) }}</p>
                        <p class=”text-xs text-emerald-400”>70% margin</p>
                    </div>
                </div>

                {{-- Commission distribution (Alpine-driven — only shows when splits exist) --}}
                @if(!empty($ssrLead['commission_splits']))
                <div>
                    <div class=”flex items-center justify-between mb-2”>
                        <p class=”text-xs font-semibold text-gray-500 uppercase tracking-wider”>Commission Distribution</p>
                        <span class=”badge badge-gray”
                              x-text=”lead?.commission_status ? lead.commission_status.charAt(0).toUpperCase()+lead.commission_status.slice(1) : 'Pending'”>
                            {{ ucfirst($ssrLead['commission_status'] ?? 'pending') }}</span>
                    </div>
                    <div class=”space-y-1.5”>
                        <template x-for=”split in (lead?.commission_splits||[])” :key=”split.id”>
                            <div class=”flex items-center justify-between bg-gray-50 rounded-xl px-3 py-2.5”>
                                <div class=”flex items-center gap-2.5 min-w-0”>
                                    <div class=”w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0”
                                         :class=”split.role==='primary' ? 'bg-emerald-100 text-emerald-700' : split.role==='secondary' ? 'bg-blue-100 text-blue-700' : 'bg-gray-200 text-gray-600'”
                                         x-text=”(split.reseller_name||'?').slice(0,2).toUpperCase()”></div>
                                    <div class=”min-w-0”>
                                        <p class=”text-sm font-medium text-[#1E1B4B] truncate” x-text=”split.reseller_name”></p>
                                        <p class=”text-xs text-gray-400 capitalize” x-text=”split.role”></p>
                                    </div>
                                </div>
                                <div class=”text-right shrink-0 ml-3”>
                                    <p class=”text-sm font-bold text-emerald-700 tabular-nums” x-text=”fmt(commPool() * split.percentage / 100)”></p>
                                    <p class=”text-xs text-gray-400” x-text=”split.percentage + '% of pool'”></p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
                @endif

                {{-- No financial data notice --}}
                @if(!$aa)
                <div class=”p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800”>
                    No financial data set yet. Click <strong>Edit</strong> to enter Base Cost and Added Amount.
                </div>
                @endif

            </div>

            {{-- â”€â”€ Edit mode â”€â”€ --}}
            <div x-show="editFinance" style="display:none" class="space-y-5">
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
                    <div x-show="showNoteForm" style="display:none" class="space-y-2 p-3 bg-[#F0EFFA] rounded-xl">
                        <textarea x-model="noteText" rows="3" class="form-input text-sm" placeholder="Add a note…"></textarea>
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
    <template x-teleport=”body”>
    <div x-show=”showLinkContact” style=”display:none”
         class=”fixed inset-0 bg-black/50 z-[9999] flex items-end sm:items-center justify-center p-4”
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
                           placeholder=”Click 🔍 or type to search contacts…”>
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
    </template>

    {{-- ── Move Stage Modal ── --}}
    <template x-teleport=”body”>
    <div x-show=”showMoveStage” style=”display:none”
         class=”fixed inset-0 bg-black/50 z-[9999] flex items-end sm:items-center justify-center p-4”
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
    </template>

    {{-- â”€â”€ Reassign Modal â”€â”€ --}}
    <template x-teleport=”body”>
    <div x-show=”showReassign” style=”display:none” class=”fixed inset-0 bg-black/50 z-[9999] flex items-center justify-center p-4”>
        <div class=”bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 space-y-4” @click.stop>
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

        <div x-show="!loadingComments && comments.length" class="space-y-4">
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
                                <p class="text-sm text-gray-700 whitespace-pre-wrap break-words" x-text="c.body"></p>
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

function dealDetail(leadId, tenantId, ssrLead) {
    return {
        lead: ssrLead || null, loading: !ssrLead,
        showNoteForm: false, showMoveStage: false, showReassign: false,
        noteText: '', noteAuthor: '', saving: false, reassignName: '',
        moveStageNote: '',
        editFinance: false,
        financeForm: { base_cost: 0, added_amount: 0 },

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
                // SSR data already present — page is instantly visible.
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

// ── Partner Split Section ─────────────────────────────────────────────────
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

// ── Extension Request Section ─────────────────────────────────────────────
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



