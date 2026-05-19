@extends('layouts.app')
@section('title', 'Deal Detail')
@section('nav') @include('tenant._nav') @endsection

@section('topbar-actions')
    <a href="{{ route('tenant.tasks', [$tenant->id]) }}?create=1&source_type=deal&source_id={{ $dealId }}"
       class="btn-secondary text-sm" style="text-decoration:none;display:flex;align-items:center;gap:6px">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        <span class="hidden sm:inline">New Task</span>
    </a>
    {{-- Update Amount: opens the finance edit section directly --}}
    <button onclick="window.dispatchEvent(new CustomEvent('open-update-amount-deal'))"
            class="btn-secondary text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span class="hidden sm:inline">Update Amount</span>
    </button>
    <button onclick="rbOpenMoveStage()" class="btn-secondary text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        Move Stage
    </button>
    <button onclick="rbOpenReassign()" class="btn-secondary text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
        Reassign
    </button>
    <button onclick="window.dispatchEvent(new CustomEvent('open-delete-deal'))"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-red-600 bg-red-50 hover:bg-red-100 border border-red-100 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        <span class="hidden sm:inline">Delete Deal</span>
    </button>
@endsection

@section('content')
<script>var __dealSsrLead = @json($ssrLead ?? null); var __tenantId = '{{ $tenant->id }}'; var rbReferrers = @json($referrers ?? []);</script>
<style>
/* Financial breakdown layout â€" guaranteed, no Tailwind compile dependency */
.fin-row{display:flex!important;justify-content:space-between;align-items:center}
.fin-grid-3{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr));gap:.5rem}
</style>
<div class="space-y-5"
     x-data="dealDetail('{{ $dealId }}', '{{ $tenant->id }}', __dealSsrLead)"
     x-init="init()"
     @open-move-stage-deal.window="showMoveStage = true"
     @open-delete-deal.window="showDeleteConfirm = true"
     @open-update-amount-deal.window="startEditFinance(); $nextTick(() => { document.getElementById('rb-finance-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' }) })"
     @open-add-co-ref.window="showAddCoRef = true; coRefEmail = ''; coRefPct = '0'; coRefErr = ''"
     @coref-pick.window="coRefEmail = $event.detail.email">

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

    {{-- ── Pending Approval Requests Panel (Admin/Manager only) ───────── --}}
    @if(!empty($pendingApprovals))
    <div id="rb-pending-approvals" class="space-y-3" data-tenant-id="{{ $tenant->id }}" x-data="rbApprovalPanel()">
        @foreach($pendingApprovals as $approval)
        @php
            $isStageMove = ($approval['type'] ?? '') === 'deal_stage_move';
            $isArchive   = ($approval['type'] ?? '') === 'deal_archive';
            $payload     = $approval['request_payload'] ?? [];
            $missing     = $approval['missing_requirements'] ?? [];
            $tenantId    = $tenant->id;
            $approvalId  = $approval['id'];
        @endphp
        <div class="rounded-2xl border p-5 {{ $isStageMove ? 'bg-amber-50 border-amber-200' : 'bg-red-50 border-red-200' }}">
            <div class="flex flex-col sm:flex-row sm:items-start gap-4">
                <div class="flex items-center gap-2 shrink-0">
                    @if($isStageMove)
                    <div class="w-9 h-9 rounded-xl bg-amber-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </div>
                    @else
                    <div class="w-9 h-9 rounded-xl bg-red-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1 12a2 2 0 002 2h8a2 2 0 002-2L19 8"/></svg>
                    </div>
                    @endif
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <span class="text-xs font-bold {{ $isStageMove ? 'text-amber-700' : 'text-red-700' }} uppercase tracking-wide">
                            {{ $isStageMove ? 'Stage Move Request' : 'Archive Request' }}
                        </span>
                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-amber-200 text-amber-800 font-semibold">Pending Review</span>
                    </div>
                    @if($isStageMove)
                    <p class="text-sm font-medium text-gray-800">
                        <strong>{{ $payload['referrer_name'] ?? 'Referrer' }}</strong> wants to move this deal from
                        <strong class="text-gray-900">{{ ucfirst(str_replace('_', ' ', $payload['current_stage'] ?? '')) }}</strong> →
                        <strong class="text-[#7B61FF]">{{ ucfirst(str_replace('_', ' ', $payload['target_stage'] ?? '')) }}</strong>
                    </p>
                    @else
                    <p class="text-sm font-medium text-gray-800">
                        <strong>{{ $payload['referrer_name'] ?? 'Referrer' }}</strong> requested to archive this deal at stage
                        <strong>{{ ucfirst(str_replace('_', ' ', $payload['deal_stage'] ?? '')) }}</strong>.
                    </p>
                    @endif
                    @if($approval['reason'] ?? '')
                    <p class="text-xs text-gray-600 mt-1">Reason: {{ $approval['reason'] }}</p>
                    @endif
                    @if(!empty($missing))
                    <div class="mt-2">
                        <p class="text-xs font-semibold text-red-600 mb-1">Missing requirements (referrer has not confirmed):</p>
                        <ul class="space-y-0.5">
                            @foreach($missing as $item)
                            <li class="flex items-center gap-1.5 text-xs text-red-700">
                                <svg class="w-3 h-3 shrink-0 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                {{ $item }}
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                </div>
                {{-- Action buttons --}}
                <div class="flex flex-col gap-2 shrink-0 w-full sm:w-auto" x-data="{ note_{{ str_replace('-', '_', $approvalId) }}: '' }">
                    <input type="text"
                           x-model="note_{{ str_replace('-', '_', $approvalId) }}"
                           placeholder="Optional review note…"
                           class="w-full sm:w-48 text-xs border border-gray-200 rounded-xl px-3 py-2 outline-none focus:ring-2 focus:ring-blue-400/20 focus:border-blue-400 bg-white">
                    <div class="flex gap-2">
                        <button @click="$parent.approveApproval('{{ $approvalId }}', note_{{ str_replace('-', '_', $approvalId) }})"
                                :disabled="$parent.apBusy"
                                class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold text-white transition-all disabled:opacity-50"
                                style="background:#10B981">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Approve
                        </button>
                        <button @click="$parent.rejectApproval('{{ $approvalId }}', note_{{ str_replace('-', '_', $approvalId) }})"
                                :disabled="$parent.apBusy"
                                class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold text-red-700 bg-white border border-red-200 hover:bg-red-50 transition-all disabled:opacity-50">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            Decline
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    <div x-show="!loading && lead" class="space-y-5">

        {{-- ── LGU IDS Default Amount Confirmation Prompt ──────────────── --}}
        @if(($ssrLead['data']['amount_defaulted'] ?? false) && ($ssrLead['data']['amount_confirmation_status'] ?? '') === 'pending')
        <div x-data="defaultAmountPrompt('{{ $dealId }}', '{{ $tenant->id }}')"
             x-show="visible" x-cloak x-transition
             class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
            <div class="flex flex-col sm:flex-row sm:items-start gap-4">
                <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-bold text-amber-800">Confirm Deal Amount</p>
                    <p class="text-xs text-amber-700 mt-1 leading-relaxed max-w-2xl">
                        This deal did not have a Deal Amount when it was created or imported, so the LGU IDS default amount of <strong>₱4,000,000</strong> was applied.
                        Please confirm if this amount is correct or update it based on the actual contract value.
                    </p>
                    <p class="text-[10px] text-amber-600 mt-1">Deal amount affects financial breakdown, commission pool, pipeline value, and reports.</p>
                    <div class="flex flex-wrap items-center gap-2 mt-4">
                        <button @click="confirm()"
                                :disabled="busy"
                                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold text-white transition-all disabled:opacity-50"
                                style="background:#D97706">
                            <svg x-show="busy" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <svg x-show="!busy" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span x-text="busy ? 'Confirming…' : 'Confirm ₱4,000,000'"></span>
                        </button>
                        <button @click="window.dispatchEvent(new CustomEvent('open-edit-finance'))"
                                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold text-amber-700 bg-white border border-amber-200 hover:bg-amber-50 transition-all">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            Change Amount
                        </button>
                        <button @click="snooze()"
                                class="text-xs text-amber-600 hover:text-amber-800 underline transition-colors">
                            Remind me later
                        </button>
                    </div>
                    <p x-show="error" class="text-xs text-red-600 mt-2" x-text="error"></p>
                </div>
            </div>
        </div>
        @endif

        {{-- ── Header ── --}}
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
                    <button onclick="rbOpenMoveStage()"
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
                                 @click="if (!isStageDone(s.key) && s.key !== lead?.stage) rbOpenMoveStage()">

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
                                 @click="if (!isStageDone(s.key) && s.key !== lead?.stage) rbOpenMoveStage()">
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
        <div class="card" id="rb-finance-section">

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
                // If only deal_value is set (no base_cost/added_amount split yet),
                // derive added_amount for commission purposes to avoid showing ₱0 pool.
                if ($aa <= 0 && $bc <= 0 && $dv > 0) {
                    // Generic fallback — use full deal value as added_amount (conservative)
                    $aa = $dv;
                }
                $cv = ($bc + $aa) ?: $dv;
                // Formula: Company Share = 30%, Commission Pool = 70% of Added Amount
                $co = round($aa * 0.30, 2);
                $cp = round($aa * 0.70, 2);
                $commStatus = $ssrLead['commission_status'] ?? 'pending';
            @endphp
            <div x-show="!editFinance" class="space-y-4">

                {{-- Two-panel layout: Left = equation, Right = distribution --}}
                <div style="display:grid;grid-template-columns:1fr auto 1fr;gap:0;align-items:stretch">

                    {{-- LEFT: Base Cost + Added Amount = Contract Value --}}
                    <div style="padding-right:20px;display:flex;flex-direction:column;gap:0">
                        {{-- Base Cost --}}
                        <div style="display:flex;justify-content:space-between;align-items:flex-end;padding:10px 0;border-bottom:1px solid #f3f4f6">
                            <div>
                                <p style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin:0">Base Cost</p>
                            </div>
                            <p id="fin-bc" style="font-size:16px;font-weight:700;color:#374151;margin:0;font-variant-numeric:tabular-nums">₱{{ number_format((int)$bc) }}</p>
                        </div>
                        {{-- Added Amount --}}
                        <div style="display:flex;justify-content:space-between;align-items:flex-end;padding:10px 0;border-bottom:1px dashed #e5e7eb">
                            <div>
                                <p style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin:0">+ Added Amount</p>
                                <p style="font-size:10px;color:#9ca3af;margin:1px 0 0">margin</p>
                            </div>
                            <p id="fin-aa" style="font-size:16px;font-weight:700;color:#2563eb;margin:0;font-variant-numeric:tabular-nums">₱{{ number_format((int)$aa) }}</p>
                        </div>
                        {{-- Contract Value --}}
                        <div style="display:flex;justify-content:space-between;align-items:flex-end;padding:12px 14px;border-radius:12px;background:#f5f3ff;margin-top:8px">
                            <div>
                                <p style="font-size:11px;font-weight:700;color:#7B61FF;text-transform:uppercase;letter-spacing:.05em;margin:0">= Contract Value</p>
                            </div>
                            <p id="fin-cv" style="font-size:18px;font-weight:800;color:#1E1B4B;margin:0;font-variant-numeric:tabular-nums">₱{{ number_format((int)$cv) }}</p>
                        </div>
                    </div>

                    {{-- Divider --}}
                    <div style="display:flex;align-items:stretch;justify-content:center;padding:0 16px">
                        <div style="width:1px;background:#e5e7eb"></div>
                    </div>

                    {{-- RIGHT: Contract Value → Company Share + Commission Pool --}}
                    <div style="padding-left:20px;display:flex;flex-direction:column;gap:0">
                        {{-- Contract Value (mirrored) --}}
                        <div style="display:flex;justify-content:space-between;align-items:flex-end;padding:10px 0;border-bottom:1px solid #f3f4f6">
                            <div>
                                <p style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin:0">Contract Value</p>
                                <p style="font-size:10px;color:#9ca3af;margin:1px 0 0">base + margin</p>
                            </div>
                            <p id="fin-cv2" style="font-size:16px;font-weight:700;color:#1E1B4B;margin:0;font-variant-numeric:tabular-nums">₱{{ number_format((int)$cv) }}</p>
                        </div>
                        {{-- Company Share --}}
                        <div style="display:flex;justify-content:space-between;align-items:flex-end;padding:10px 0;border-bottom:1px dashed #e5e7eb">
                            <div>
                                <p style="font-size:11px;font-weight:600;color:#1d4ed8;text-transform:uppercase;letter-spacing:.05em;margin:0">Company Share</p>
                                <p style="font-size:10px;color:#93c5fd;margin:1px 0 0">30% of margin</p>
                            </div>
                            <p id="fin-co" style="font-size:16px;font-weight:700;color:#1d4ed8;margin:0;font-variant-numeric:tabular-nums">₱{{ number_format((int)$co) }}</p>
                        </div>
                        {{-- Commission Pool --}}
                        <div style="display:flex;justify-content:space-between;align-items:flex-end;padding:12px 14px;border-radius:12px;background:#f0fdf4;margin-top:8px">
                            <div>
                                <p style="font-size:11px;font-weight:700;color:#16a34a;text-transform:uppercase;letter-spacing:.05em;margin:0">Commission Pool</p>
                                <p style="font-size:10px;color:#86efac;margin:1px 0 0">70% of margin</p>
                            </div>
                            <p id="fin-cp" style="font-size:18px;font-weight:800;color:#15803d;margin:0;font-variant-numeric:tabular-nums">₱{{ number_format((int)$cp) }}</p>
                        </div>
                    </div>

                </div>

                {{-- Commission Distribution — Referrer splits (Alpine-driven) --}}
                <div x-show="(lead?.commission_splits||[]).length > 0 || lead?.added_amount > 0">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                        <div style="display:flex;align-items:center;gap:8px">
                            <p style="font-size:11px;font-weight:700;color:#9ca3af;letter-spacing:.05em;text-transform:uppercase">Referrer Commission Distribution</p>
                            <template x-if="lead?.commission_status !== 'locked' && lead?.commission_status !== 'paid'">
                                <button @click="showAddCoRef = true; coRefEmail = ''; coRefPct = '0'; coRefErr = ''"
                                        style="padding:2px 8px;border-radius:6px;border:1px solid #bfdbfe;background:white;font-size:10px;font-weight:600;color:#2563eb;cursor:pointer;white-space:nowrap">
                                    + Co-Referrer
                                </button>
                            </template>
                        </div>
                        {{-- Commission status badge --}}
                        <span style="display:inline-flex;align-items:center;gap:4px;padding:2px 10px;border-radius:9999px;font-size:11px;font-weight:600"
                              :style="lead?.commission_status === 'paid'   ? 'background:#dcfce7;color:#15803d'
                                    : lead?.commission_status === 'locked' ? 'background:#fef3c7;color:#d97706'
                                    : 'background:#ede9fe;color:#7B61FF'"
                              x-text="lead?.commission_status === 'paid'   ? 'Paid'
                                    : lead?.commission_status === 'locked' ? 'Locked'
                                    : 'Pending'">{{ ucfirst($commStatus) }}</span>
                    </div>

                    {{-- Referrer split rows — editSplitId/editPct/editSaving/editErr are in dealDetail() scope --}}
                    <div class="space-y-1.5">
                        <template x-for="split in (lead?.commission_splits||[])" :key="split.id">
                            <div style="background:#f9fafb;border-radius:12px;padding:10px 12px">
                                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
                                    <div style="display:flex;align-items:center;gap:10px;min-width:0;flex:1">
                                        <div style="width:28px;height:28px;border-radius:9999px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0"
                                             :style="split.role==='primary' ? 'background:#dcfce7;color:#16a34a'
                                                   : split.role==='secondary' ? 'background:#dbeafe;color:#2563eb'
                                                   : 'background:#f3f4f6;color:#6b7280'"
                                             x-text="(split.reseller_name||'?').slice(0,2).toUpperCase()"></div>
                                        <div style="min-width:0;flex:1">
                                            <p style="font-size:13px;font-weight:600;color:#1E1B4B;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="split.reseller_name"></p>
                                            <p style="font-size:11px;color:#9ca3af;text-transform:capitalize" x-text="split.role + ' referrer'"></p>
                                        </div>
                                    </div>
                                    <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
                                        <div style="text-align:right">
                                            <p style="font-size:13px;font-weight:700;color:#16a34a" x-text="fmt(commPool() * split.percentage / 100)"></p>
                                            <p style="font-size:11px;color:#9ca3af" x-text="split.percentage + '% of pool'"></p>
                                        </div>
                                        {{-- Edit % + Remove buttons — only for co-referrers (secondary role) --}}
                                        <template x-if="split.role === 'secondary' && lead?.commission_status !== 'locked' && lead?.commission_status !== 'paid'">
                                            <div style="display:flex;flex-direction:column;gap:4px">
                                                <button @click="editSplitId = split.id; editPct = String(split.percentage); editErr = ''"
                                                        style="padding:3px 8px;border-radius:6px;border:1px solid #bfdbfe;background:white;font-size:10px;font-weight:600;color:#2563eb;cursor:pointer;white-space:nowrap">
                                                    Edit %
                                                </button>
                                                <button @click="adminRemoveCoRef(split.id, split.reseller_name)"
                                                        style="padding:3px 8px;border-radius:6px;border:1px solid #fecaca;background:white;font-size:10px;font-weight:600;color:#dc2626;cursor:pointer;white-space:nowrap">
                                                    Remove
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                                {{-- Inline edit panel for this split --}}
                                <template x-if="editSplitId === split.id">
                                    <div style="margin-top:10px;padding:10px;background:white;border-radius:8px;border:1.5px solid #bfdbfe">
                                        <p style="font-size:11px;font-weight:600;color:#1e40af;margin-bottom:8px">Adjust share for <span x-text="split.reseller_name"></span></p>
                                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                                            <input x-model="editPct" type="number" min="0.01" max="100" step="0.01"
                                                   style="width:80px;padding:5px 8px;border:1.5px solid #bfdbfe;border-radius:7px;font-size:12px;text-align:center;outline:none">
                                            <span style="font-size:11px;color:#6b7280">%</span>
                                            <button @click="adminSaveSplit(split, '{{ url('tenant/'.$tenant->id.'/deals') }}/' + lead.id + '/splits/' + split.id, '{{ csrf_token() }}')"
                                                    :disabled="editSaving || !editPct"
                                                    style="padding:5px 12px;border-radius:7px;background:linear-gradient(135deg,#2563EB,#1D4ED8);color:white;border:none;font-size:11px;font-weight:600;cursor:pointer;opacity:1"
                                                    x-text="editSaving ? 'Saving…' : 'Save'">Save</button>
                                            <button @click="editSplitId = null; editPct = ''; editErr = ''"
                                                    style="padding:5px 10px;border-radius:7px;border:1px solid #e5e7eb;background:white;font-size:11px;font-weight:600;color:#374151;cursor:pointer">
                                                Cancel
                                            </button>
                                        </div>
                                        <p x-show="editErr" style="font-size:11px;color:#dc2626;margin-top:6px" x-text="editErr"></p>
                                    </div>
                                </template>
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

                {{-- Add Co-Referrer modal — outer div only controls visibility; centering is on inner wrapper
                     so Alpine's x-show toggling display:none/block never breaks the flex layout --}}
                <div x-show="showAddCoRef" x-cloak
                     style="position:fixed;inset:0;z-index:9999;"
                     @keydown.escape.window="showAddCoRef = false">
                    {{-- Backdrop + centering wrapper — always display:flex --}}
                    <div style="display:flex;align-items:center;justify-content:center;width:100%;height:100%;padding:16px;background:rgba(0,0,0,.5)"
                         @click.self="showAddCoRef = false">

                        <div x-data="{ crSearch: '', crOpen: false, coRefType: 'pct', coRefFixed: '',
                                        crFiltered() {
                                            var q = this.crSearch.toLowerCase();
                                            return (window.rbReferrers || []).filter(function(r) {
                                                return !q || (r.name && r.name.toLowerCase().includes(q))
                                                          || (r.email && r.email.toLowerCase().includes(q));
                                            }).slice(0, 8);
                                        }
                                      }"
                             style="background:white;border-radius:24px;max-width:460px;width:100%;box-shadow:0 32px 72px rgba(30,27,75,.18);overflow:hidden"
                             @click.stop>

                            {{-- Header --}}
                            <div style="background:linear-gradient(135deg,#EDE9FE,#F5F3FF);padding:20px 24px 16px;border-bottom:1px solid #e5e7eb">
                                <div style="display:flex;align-items:flex-start;justify-content:space-between">
                                    <div style="display:flex;align-items:center;gap:10px">
                                        <div style="width:36px;height:36px;border-radius:10px;background:#7B61FF;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                            <svg style="width:18px;height:18px;color:white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        </div>
                                        <div>
                                            <p style="font-size:15px;font-weight:700;color:#1E1B4B;margin:0">Add Co-Referrer</p>
                                            <p style="font-size:11px;color:#7B61FF;margin:2px 0 0;font-weight:500">Commission pool share assignment</p>
                                        </div>
                                    </div>
                                    <button @click="showAddCoRef = false" style="width:28px;height:28px;border-radius:8px;background:rgba(123,97,255,.1);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#7B61FF;flex-shrink:0">
                                        <svg style="width:14px;height:14px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </div>

                            {{-- Body --}}
                            <div style="padding:20px 24px;display:flex;flex-direction:column;gap:16px">

                                {{-- Referrer search --}}
                                <div style="position:relative">
                                    <label style="font-size:11px;font-weight:700;color:#374151;display:block;margin-bottom:6px;text-transform:uppercase;letter-spacing:.04em">
                                        Select Referrer <span style="color:#ef4444">*</span>
                                    </label>
                                    <div style="position:relative">
                                        <svg style="position:absolute;left:11px;top:50%;transform:translateY(-50%);width:14px;height:14px;color:#9ca3af;pointer-events:none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                        <input x-model="crSearch" type="text"
                                               placeholder="Search by name or type email address…"
                                               autocomplete="off"
                                               style="width:100%;padding:10px 12px 10px 33px;border:1.5px solid #e5e7eb;border-radius:12px;font-size:13px;outline:none;box-sizing:border-box;transition:border-color .15s"
                                               @focus="crOpen = true; $event.target.style.borderColor='#7B61FF'; $event.target.style.boxShadow='0 0 0 3px rgba(123,97,255,.1)'"
                                               @blur="setTimeout(() => crOpen = false, 160); $event.target.style.borderColor='#e5e7eb'; $event.target.style.boxShadow='none'"
                                               @input="coRefEmail = crSearch.includes('@') ? crSearch.trim() : ''; crOpen = true">
                                    </div>

                                    {{-- Dropdown --}}
                                    <div x-show="crOpen && crSearch.length > 0"
                                         style="position:absolute;left:0;right:0;top:calc(100% + 4px);background:white;border:1.5px solid #e5e7eb;border-radius:14px;box-shadow:0 12px 32px rgba(30,27,75,.12);z-index:20;max-height:210px;overflow-y:auto">
                                        <template x-if="crFiltered().length === 0">
                                            <div style="padding:16px;text-align:center;color:#9ca3af;font-size:12px">
                                                <svg style="width:20px;height:20px;margin:0 auto 6px;opacity:.4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                                No match — entering a new email will send an invitation.
                                            </div>
                                        </template>
                                        <template x-for="r in crFiltered()" :key="r.id || r.email || r.name">
                                            <div @mousedown.prevent="$dispatch('coref-pick', { email: r.email }); crSearch = r.name + (r.email ? ' — ' + r.email : ''); crOpen = false"
                                                 style="display:flex;align-items:center;gap:10px;padding:10px 14px;cursor:pointer;border-bottom:1px solid #f9fafb;transition:background .1s"
                                                 @mouseenter="$event.currentTarget.style.background='#F5F3FF'"
                                                 @mouseleave="$event.currentTarget.style.background='white'">
                                                <div style="width:32px;height:32px;border-radius:50%;background:#EDE9FE;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#7B61FF;flex-shrink:0;pointer-events:none"
                                                     x-text="r.name ? r.name.slice(0,2).toUpperCase() : '?'"></div>
                                                <div style="pointer-events:none;min-width:0;flex:1">
                                                    <p style="font-size:13px;font-weight:600;color:#1E1B4B;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="r.name"></p>
                                                    <p style="font-size:11px;color:#9ca3af;margin:1px 0 0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="r.email"></p>
                                                </div>
                                                <span :style="r.status === 'invited' ? 'background:#FEF3C7;color:#D97706' : 'background:#D1FAE5;color:#065F46'"
                                                      style="font-size:10px;padding:2px 8px;border-radius:20px;font-weight:600;flex-shrink:0;pointer-events:none"
                                                      x-text="r.status === 'invited' ? 'Invited' : 'Active'"></span>
                                            </div>
                                        </template>
                                    </div>

                                    {{-- Selected confirmation chip --}}
                                    <div x-show="coRefEmail" style="display:inline-flex;align-items:center;gap:6px;margin-top:6px;background:#F5F3FF;border:1px solid #DDD6FE;border-radius:8px;padding:4px 10px">
                                        <svg style="width:11px;height:11px;color:#7B61FF;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                        <span style="font-size:11px;color:#7B61FF;font-weight:600" x-text="coRefEmail"></span>
                                    </div>
                                </div>

                                {{-- Commission share type toggle + input --}}
                                <div>
                                    <label style="font-size:11px;font-weight:700;color:#374151;display:block;margin-bottom:8px;text-transform:uppercase;letter-spacing:.04em">
                                        Commission Share
                                    </label>
                                    {{-- Segmented control --}}
                                    <div style="display:inline-flex;background:#F3F4F6;border-radius:10px;padding:3px;margin-bottom:10px;gap:2px">
                                        <button type="button" @click="coRefType='pct'"
                                                style="padding:5px 16px;border-radius:8px;border:none;font-size:12px;font-weight:600;cursor:pointer;transition:all .18s;outline:none"
                                                :style="coRefType==='pct'
                                                    ? 'background:white;color:#7B61FF;box-shadow:0 1px 4px rgba(0,0,0,.12)'
                                                    : 'background:transparent;color:#9ca3af'">
                                            % Percentage
                                        </button>
                                        <button type="button" @click="coRefType='fixed'"
                                                style="padding:5px 16px;border-radius:8px;border:none;font-size:12px;font-weight:600;cursor:pointer;transition:all .18s;outline:none"
                                                :style="coRefType==='fixed'
                                                    ? 'background:white;color:#7B61FF;box-shadow:0 1px 4px rgba(0,0,0,.12)'
                                                    : 'background:transparent;color:#9ca3af'">
                                            ₱ Fixed Amount
                                        </button>
                                    </div>

                                    <template x-if="coRefType === 'pct'">
                                        <div>
                                            <div style="position:relative">
                                                <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:14px;font-weight:600;color:#7B61FF;pointer-events:none">%</span>
                                                <input x-model="coRefPct" type="number" min="0" max="100" step="0.01" placeholder="0"
                                                       style="width:100%;padding:10px 12px 10px 28px;border:1.5px solid #e5e7eb;border-radius:12px;font-size:14px;font-weight:600;color:#1E1B4B;outline:none;box-sizing:border-box;transition:border-color .15s"
                                                       @focus="$event.target.style.borderColor='#7B61FF'; $event.target.style.boxShadow='0 0 0 3px rgba(123,97,255,.1)'"
                                                       @blur="$event.target.style.borderColor='#e5e7eb'; $event.target.style.boxShadow='none'">
                                            </div>
                                            <p style="font-size:11px;color:#9ca3af;margin-top:5px">0% registers the co-referrer without a share allocation. Max 100%.</p>
                                        </div>
                                    </template>

                                    <template x-if="coRefType === 'fixed'">
                                        <div>
                                            <div style="position:relative">
                                                <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:14px;font-weight:600;color:#7B61FF;pointer-events:none">₱</span>
                                                <input x-model="coRefFixed" type="number" min="0" step="1" placeholder="0"
                                                       style="width:100%;padding:10px 12px 10px 28px;border:1.5px solid #e5e7eb;border-radius:12px;font-size:14px;font-weight:600;color:#1E1B4B;outline:none;box-sizing:border-box;transition:border-color .15s"
                                                       @focus="$event.target.style.borderColor='#7B61FF'; $event.target.style.boxShadow='0 0 0 3px rgba(123,97,255,.1)'"
                                                       @blur="$event.target.style.borderColor='#e5e7eb'; $event.target.style.boxShadow='none'">
                                            </div>
                                            <template x-if="(window.rbLead && window.rbLead.added_amount) > 0 && coRefFixed > 0">
                                                <p style="font-size:11px;color:#7B61FF;margin-top:5px;font-weight:600"
                                                   x-text="'≈ ' + Math.min(100, Math.round(parseFloat(coRefFixed) / (parseFloat(window.rbLead.added_amount) * 0.70) * 10000) / 100).toFixed(2) + '% of the commission pool (₱' + Math.round(parseFloat(window.rbLead.added_amount) * 0.70).toLocaleString() + ')'">
                                                </p>
                                            </template>
                                            <template x-if="!(window.rbLead && window.rbLead.added_amount > 0)">
                                                <p style="font-size:11px;color:#d97706;margin-top:5px">⚠ Commission pool not set — enter a % instead or set Added Amount on the deal first.</p>
                                            </template>
                                            <p style="font-size:11px;color:#9ca3af;margin-top:3px">Fixed peso amount from the commission pool. Converted to % on save.</p>
                                        </div>
                                    </template>
                                </div>

                                <div x-show="coRefErr" style="font-size:12px;color:#dc2626;background:#fef2f2;border:1px solid #fecaca;padding:10px 12px;border-radius:10px" x-text="coRefErr"></div>
                            </div>

                            {{-- Footer --}}
                            <div style="display:flex;gap:10px;padding:16px 24px 20px;border-top:1px solid #f3f4f6;background:#fafafa">
                                <button @click="showAddCoRef = false"
                                        style="flex:1;padding:10px;border-radius:12px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:13px;font-weight:600;cursor:pointer;transition:background .15s"
                                        @mouseenter="$event.target.style.background='#f9fafb'"
                                        @mouseleave="$event.target.style.background='white'">
                                    Cancel
                                </button>
                                <button @click="if (coRefType === 'fixed') {
                                            var pool = (window.rbLead && window.rbLead.added_amount) ? parseFloat(window.rbLead.added_amount) * 0.70 : 0;
                                            coRefPct = pool > 0 ? String(Math.min(100, Math.round(parseFloat(coRefFixed || 0) / pool * 10000) / 100)) : '0';
                                        }; adminSaveCoRef('{{ $tenant->id }}', lead.id, '{{ csrf_token() }}')"
                                        :disabled="!coRefEmail || coRefSaving || (coRefType==='fixed' && !coRefFixed)"
                                        style="flex:1;padding:10px;border-radius:12px;border:none;background:linear-gradient(135deg,#7B61FF,#5b4cdb);color:white;font-size:13px;font-weight:600;cursor:pointer;box-shadow:0 4px 14px rgba(123,97,255,.3);transition:opacity .15s"
                                        :style="(!coRefEmail || coRefSaving || (coRefType==='fixed' && !coRefFixed)) ? 'opacity:.45;cursor:not-allowed;box-shadow:none' : 'opacity:1'"
                                        x-text="coRefSaving ? 'Adding…' : 'Add Co-Referrer'">Add Co-Referrer</button>
                            </div>

                        </div>
                    </div>
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
                        <span class="font-medium text-gray-700"
                              x-text="lead?.reseller_name || '—'">{{ $ssrLead['reseller_name'] ?? '—' }}</span>
                    </div>
                    @if($showLocation ?? false)
                    <div class="flex justify-between text-sm py-1.5 border-b border-gray-50">
                        <span class="text-gray-400">Province</span>
                        <span class="font-medium text-gray-700"
                              x-text="(lead?.data?.province) || (lead?.province) || '—'">{{ $ssrLead['data']['province'] ?? ($ssrLead['province'] ?? '—') }}</span>
                    </div>
                    <div class="flex justify-between text-sm py-1.5 border-b border-gray-50">
                        <span class="text-gray-400">Municipality</span>
                        <span class="font-medium text-gray-700"
                              x-text="(lead?.data?.municipality) || (lead?.municipality) || '—'">{{ $ssrLead['data']['municipality'] ?? ($ssrLead['municipality'] ?? '—') }}</span>
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
                    <div class="flex items-center gap-2">
                        <button @click="window.dispatchEvent(new CustomEvent('open-add-co-ref'))"
                                class="text-xs text-blue-600 hover:text-blue-700 font-medium flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            + Co-Referrer
                        </button>
                        <button @click="showAdd = !showAdd" class="text-xs text-purple-600 hover:text-purple-700 font-medium flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Add Partner
                        </button>
                    </div>
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

                    {{-- Co-Referrer rows --}}
                    <template x-if="coRefs.length > 0">
                        <div>
                            <p style="font-size:10px;font-weight:700;color:#9ca3af;letter-spacing:.06em;text-transform:uppercase;padding:8px 4px 4px">
                                Co-Referrers
                            </p>
                            <template x-for="r in coRefs" :key="r.id">
                                <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:#f0f9ff;border-radius:12px;border:1.5px solid #bae6fd;margin-bottom:6px">
                                    <div style="width:32px;height:32px;border-radius:9999px;background:#dbeafe;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#2563eb;flex-shrink:0"
                                         x-text="(r.reseller_name||'?').slice(0,2).toUpperCase()"></div>
                                    <div style="flex:1;min-width:0">
                                        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                                            <p style="font-size:13px;font-weight:600;color:#1E1B4B;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="r.reseller_name"></p>
                                            <span style="font-size:9px;font-weight:700;color:#2563eb;background:#dbeafe;padding:1px 7px;border-radius:9999px;letter-spacing:.04em;text-transform:uppercase;flex-shrink:0">Co-Referrer</span>
                                        </div>
                                    </div>
                                    <div style="text-align:right;flex-shrink:0">
                                        <p style="font-size:14px;font-weight:700;color:#2563eb"
                                           x-text="'₱' + Math.round(commPool * parseFloat(r.percentage||0) / 100).toLocaleString('en-PH')"></p>
                                        <p style="font-size:10px;color:#9ca3af;margin-top:1px" x-text="parseFloat(r.percentage||0) + '% of pool'"></p>
                                        <button @click="removeCoRef(r.id, r.reseller_name)"
                                                style="font-size:10px;color:#9ca3af;cursor:pointer;background:none;border:none;margin-top:3px;display:block;margin-left:auto;padding:2px 6px;border-radius:6px;transition:all .15s"
                                                onmouseover="this.style.color='#dc2626';this.style.background='#fef2f2'"
                                                onmouseout="this.style.color='#9ca3af';this.style.background='none'">Remove</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

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
                                        <button @click="removeSplit(s.id, s.partner_name)"
                                                style="font-size:10px;color:#9ca3af;cursor:pointer;background:none;border:none;margin-top:3px;display:block;margin-left:auto;padding:2px 6px;border-radius:6px;transition:all .15s"
                                                onmouseover="this.style.color='#dc2626';this.style.background='#fef2f2'"
                                                onmouseout="this.style.color='#9ca3af';this.style.background='none'">Remove</button>
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

                {{-- Add Partner modal — outer div controls visibility only; centering lives on the inner wrapper --}}
                <div x-show="showAdd"
                     style="position:fixed;inset:0;z-index:9998;"
                     @keydown.escape.window="showAdd = false; clearContact(); formError = ''">
                    <div style="display:flex;align-items:center;justify-content:center;width:100%;height:100%;padding:16px;background:rgba(0,0,0,.5)"
                         @click.self="showAdd = false; clearContact(); formError = ''">
                    <div style="background:white;border-radius:20px;width:100%;max-width:480px;max-height:90vh;overflow-y:auto;box-shadow:0 24px 64px rgba(0,0,0,.18)" @click.stop>
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 20px 14px;border-bottom:1px solid #f3f4f6;position:sticky;top:0;background:white;z-index:1">
                            <div>
                                <p style="font-size:15px;font-weight:700;color:#1E1B4B">Add Partner Split</p>
                                <p style="font-size:11px;color:#9ca3af;margin-top:2px">Partner receives a share of the referrer's commission pool.</p>
                            </div>
                            <button @click="showAdd = false; clearContact(); formError = ''" style="color:#9ca3af;cursor:pointer;background:none;border:none;padding:2px">
                                <svg style="width:18px;height:18px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <div class="space-y-2.5 p-5">

                    {{-- Contact combobox --}}
                    <div x-show="!contactSelected" class="relative" @click.outside="contactOpen = false">
                        <div class="relative">
                            <input type="text"
                                   x-model="contactQuery"
                                   @input.debounce.300ms="contactOpen = true; searchContacts(); form.partner_name = contactQuery.trim()"
                                   @blur="if (!contactSelected && contactQuery.trim()) form.partner_name = contactQuery.trim()"
                                   @keydown.escape="contactOpen = false"
                                   @keydown.arrow-down.prevent="contactFocusIdx = Math.min(contactFocusIdx + 1, contactOptions.length - 1)"
                                   @keydown.arrow-up.prevent="contactFocusIdx = Math.max(contactFocusIdx - 1, -1)"
                                   @keydown.enter.prevent="if(contactFocusIdx >= 0 && contactOptions[contactFocusIdx]) selectContact(contactOptions[contactFocusIdx])"
                                   placeholder="Partner name or search contacts…"
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

                    {{-- Optional email (shown when no contact selected from dropdown) --}}
                    <div x-show="!contactSelected" class="space-y-1">
                        <label class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Email <span class="font-normal normal-case">(optional — triggers invite)</span></label>
                        <input type="email" x-model="form.partner_email"
                               placeholder="partner@example.com"
                               class="form-input text-xs"
                               autocomplete="off">
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
                                <span x-text="'~ ' + (commPool > 0 ? (form.split_share_value / commPool * 100).toFixed(1) : '0') + '% of commission pool'"></span>
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

                        </div>{{-- /modal body --}}
                    </div>{{-- /modal card --}}
                    </div>{{-- /centering wrapper --}}
                </div>{{-- /modal overlay --}}
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
                    <div x-show="!loadingComments && comments.length === 0" class="flex flex-col items-center text-center py-8 px-4">
                        <img src="/images/mascots/r-bunny-rocket.webp" alt="" aria-hidden="true"
                             class="w-16 h-16 object-contain mb-3 opacity-80">
                        <p class="text-sm font-semibold text-[#1E1B4B] mb-1">No notes yet!</p>
                        <p class="text-xs text-gray-400 max-w-xs leading-relaxed">R Bunny is waiting for the first update on this deal. Drop a note — progress, blockers, wins — anything that keeps the team in the loop.</p>
                        <p class="text-[10px] text-[#7B61FF] font-semibold mt-2 cursor-pointer hover:underline"
                           @click="$el.closest('.space-y-4')?.querySelector('textarea')?.focus()">
                            + Write the first note →
                        </p>
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
                                            <p class="text-sm text-gray-700 whitespace-pre-wrap break-words" x-html="linkify(c.body)"></p>
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
                <div class="card" id="rb-activity-history"
                     x-data="dealActivityHistory(@json($ssrLead['history'] ?? []))"
                     x-init="$nextTick(() => refreshHistory())">

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
                                <p style="font-size:11px">Activity will appear here when notes are added or stage, financial, partner, or commission changes are made.</p>
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
                                            <template x-if="event.type === 'note' || event.category === 'note'">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
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

    {{-- Move Stage Modal — 100% plain JS, zero Alpine dependency --}}
    <div id="rb-move-stage-modal"
         style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:16px"
         onclick="if(event.target===this) rbCloseMoveStage()">
        <div style="background:white;border-radius:20px;width:100%;max-width:420px;box-shadow:0 25px 60px rgba(0,0,0,0.18);max-height:90vh;overflow-y:auto"
             onclick="event.stopPropagation()">

            {{-- Header --}}
            <div style="padding:20px 24px 16px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between">
                <div style="display:flex;align-items:center;gap:12px">
                    <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#7B61FF,#5b4cdb);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <svg style="width:16px;height:16px;color:white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </div>
                    <div>
                        <p style="font-weight:700;color:#1E1B4B;font-size:15px;margin:0">Move Stage</p>
                        <p id="rb-modal-deal-name" style="font-size:11px;color:#9ca3af;margin:2px 0 0"></p>
                    </div>
                </div>
                <button onclick="rbCloseMoveStage()" style="width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#9ca3af;cursor:pointer;border:none;background:none">
                    <svg style="width:16px;height:16px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Status bar: current → target --}}
            <div id="rb-stage-status" style="padding:14px 24px 0;display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:12px;color:#9ca3af"></div>

            {{-- Stage list — rendered by rbRenderStageList() --}}
            <div id="rb-stage-list" style="padding:12px 24px;display:flex;flex-direction:column;gap:6px"></div>

            {{-- Note + footer --}}
            <div style="padding:0 24px 20px;display:flex;flex-direction:column;gap:8px">
                <textarea id="rb-stage-note" rows="2"
                          style="width:100%;padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:12px;font-size:13px;color:#1E1B4B;background:white;outline:none;resize:none;box-sizing:border-box;font-family:inherit"
                          placeholder="Optional note — reason for stage movement..."></textarea>
                <p style="font-size:11px;color:#9ca3af;margin:0">Note is saved to the activity history.</p>
                <button id="rb-move-confirm-btn"
                        onclick="rbConfirmMove()"
                        style="display:none;padding:10px 20px;border-radius:12px;background:linear-gradient(135deg,#7B61FF,#5b4cdb);color:white;border:none;font-size:13px;font-weight:600;cursor:pointer;box-shadow:0 4px 14px rgba(123,97,255,0.3)">
                    Confirm Move
                </button>
            </div>
        </div>
    </div>

    {{-- Reassign Modal — searchable referrer picker, pure JS --}}
    <div id="rb-reassign-modal"
         style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:16px"
         onclick="if(event.target===this)rbCloseReassign()">
        <div style="background:white;border-radius:20px;width:100%;max-width:420px;padding:24px;box-shadow:0 25px 60px rgba(0,0,0,0.2)" onclick="event.stopPropagation()">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
                <h3 style="margin:0;font-size:15px;font-weight:600;color:#1E1B4B">Reassign Deal</h3>
                <button onclick="rbCloseReassign()" style="background:none;border:none;cursor:pointer;color:#9ca3af;padding:4px;line-height:0">
                    <svg style="width:20px;height:20px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div style="padding:10px 12px;background:#fffbeb;border:1px solid #fde68a;border-radius:12px;font-size:12px;color:#92400e;margin-bottom:16px">
                This resets the stage to Introduction, restarts the pipeline timer, reassigns 100% of the commission split to the new referrer, and resets commission status to Pending.
            </div>
            <div style="margin-bottom:8px">
                <label style="display:block;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px">Select New Referrer</label>
                <input id="rb-reassign-search" type="text" oninput="rbReassignFilter()" autocomplete="off"
                       style="width:100%;padding:9px 12px 9px 36px;border:1.5px solid #e5e7eb;border-radius:12px;font-size:13px;color:#1E1B4B;background:white;outline:none;box-sizing:border-box;font-family:inherit;background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' stroke='%239ca3af' stroke-width='2' viewBox='0 0 24 24'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='M21 21l-4.35-4.35'/%3E%3C/svg%3E\");background-repeat:no-repeat;background-position:10px center;background-size:16px"
                       placeholder="Search referrers…">
            </div>
            <div id="rb-reassign-list"
                 style="max-height:220px;overflow-y:auto;border:1.5px solid #e5e7eb;border-radius:12px;margin-bottom:16px">
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button onclick="rbCloseReassign()"
                        style="display:inline-flex;align-items:center;padding:9px 20px;border-radius:12px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit">Cancel</button>
                <button id="rb-reassign-btn" onclick="rbConfirmReassign()" disabled
                        style="display:inline-flex;align-items:center;padding:9px 20px;border-radius:12px;border:none;background:#FF5733;color:white;font-size:13px;font-weight:600;cursor:not-allowed;opacity:0.5;transition:opacity .15s;font-family:inherit">
                    Confirm Reassign
                </button>
            </div>
        </div>
    </div>

    {{-- ── Delete Deal Confirmation Modal ──────────────────────────────── --}}
    <div x-show="showDeleteConfirm" style="display:none"
         class="fixed inset-0 bg-black/50 z-[9999] flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 text-center" @click.stop>
            <div class="w-12 h-12 rounded-2xl bg-red-100 flex items-center justify-center mx-auto mb-4">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-[#1E1B4B] mb-1">Archive this deal?</h3>
            <p class="text-sm text-gray-500 mb-1" x-text="lead?.name || 'This deal'"></p>
            <p class="text-xs text-gray-400 mb-6">This deal will be moved to the <strong>Deal Archive</strong> tab and permanently deleted after 10 days. You can restore it before then.</p>
            <div class="flex gap-3">
                <button @click="showDeleteConfirm = false"
                        :disabled="deleting"
                        class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button @click="deleteDeal()"
                        :disabled="deleting"
                        class="flex-1 py-2.5 rounded-xl bg-red-600 text-white text-sm font-semibold hover:bg-red-700 transition-colors inline-flex items-center justify-center gap-1.5">
                    <svg x-show="deleting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    <span x-text="deleting ? 'Archiving…' : 'Yes, Archive Deal'"></span>
                </button>
            </div>
        </div>
    </div>

</div>

<script>
// ── Move Stage: 100% plain JS, zero Alpine dependency ──────────────────────
var RB_STAGES = [
    { key: 'introduction',  label: 'Introduction'  },
    { key: 'presentation',  label: 'Presentation'  },
    { key: 'contract_sent', label: 'Contract Sent' },
    { key: 'signed',        label: 'Signed'        },
    { key: 'paid',          label: 'Paid'          },
];
var rbSelectedStage = null;

function rbGetLead() {
    return window.rbLead || null;
}

function rbOpenMoveStage() {
    var m = document.getElementById('rb-move-stage-modal');
    if (!m) return;
    rbSelectedStage = null;
    rbRenderStageList();
    m.style.display = 'flex';
}

function rbCloseMoveStage() {
    var m = document.getElementById('rb-move-stage-modal');
    if (m) m.style.display = 'none';
    rbSelectedStage = null;
    var note = document.getElementById('rb-stage-note');
    if (note) note.value = '';
    var btn = document.getElementById('rb-move-confirm-btn');
    if (btn) btn.style.display = 'none';
}

function rbRenderStageList() {
    var lead    = rbGetLead();
    var current = lead ? lead.stage : '';
    var curIdx  = RB_STAGES.findIndex(function(s) { return s.key === current; });
    var locked  = lead && lead.commission_status === 'locked';

    // Deal name
    var nameEl = document.getElementById('rb-modal-deal-name');
    if (nameEl && lead) nameEl.textContent = lead.name || '';

    // Status bar: show current stage
    var statusEl = document.getElementById('rb-stage-status');
    if (statusEl) {
        var curLabel = curIdx >= 0 ? RB_STAGES[curIdx].label : current;
        statusEl.innerHTML = '<span>Current stage:</span>'
            + '<span style="font-weight:700;color:#7B61FF;background:#ede9fe;padding:2px 10px;border-radius:9999px">' + curLabel + '</span>'
            + (locked ? '<span style="font-weight:600;color:#d97706;background:#fef3c7;padding:2px 10px;border-radius:9999px">⚠ Commission locked</span>' : '')
            + (current === 'paid' ? '<span style="font-weight:600;color:#15803d;background:#dcfce7;padding:2px 10px;border-radius:9999px">Final stage reached</span>' : '');
    }

    // Stage list
    var list = document.getElementById('rb-stage-list');
    if (!list) return;
    list.innerHTML = '';

    RB_STAGES.forEach(function(s, idx) {
        var isDone    = idx < curIdx;
        var isCurrent = s.key === current;
        var isNext    = idx === curIdx + 1;
        var isBlocked = locked && s.key !== 'paid';
        var isDisabled = isDone || isCurrent || isBlocked || current === 'paid';

        var btn = document.createElement('button');
        btn.type = 'button';

        // Left icon box
        var icon = document.createElement('div');
        icon.style.cssText = 'width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:14px';

        // Label area
        var labelWrap = document.createElement('div');
        labelWrap.style.cssText = 'flex:1;min-width:0;text-align:left';
        var labelEl = document.createElement('span');
        labelEl.style.cssText = 'font-size:13px;font-weight:600;display:block';
        labelEl.textContent = s.label;

        var hint = document.createElement('span');
        hint.style.cssText = 'font-size:11px;display:block;margin-top:1px';

        // Right badge
        var badge = document.createElement('span');
        badge.style.cssText = 'font-size:10px;font-weight:700;flex-shrink:0';

        if (isDone) {
            btn.style.cssText   = 'display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:12px;border:1.5px solid #86efac;background:#f0fdf4;width:100%;cursor:not-allowed;opacity:0.7';
            icon.style.background = '#dcfce7'; icon.style.color = '#16a34a'; icon.textContent = '✓';
            labelEl.style.color = '#15803d';
            badge.textContent = 'Done'; badge.style.color = '#16a34a';
        } else if (isCurrent) {
            btn.style.cssText   = 'display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:12px;border:1.5px solid #c4b5fd;background:#f5f3ff;width:100%;cursor:default;opacity:0.8';
            icon.style.background = '#ede9fe'; icon.style.color = '#7B61FF'; icon.textContent = '▸';
            labelEl.style.color = '#7B61FF';
            badge.textContent = 'Current'; badge.style.color = '#9ca3af';
        } else if (isDisabled) {
            btn.style.cssText   = 'display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:12px;border:1.5px solid #f3f4f6;background:#f9fafb;width:100%;cursor:not-allowed;opacity:0.4';
            icon.style.background = '#f3f4f6'; icon.style.color = '#9ca3af'; icon.textContent = '○';
            labelEl.style.color = '#9ca3af';
        } else {
            // Clickable
            var borderCol = isNext ? '#c4b5fd' : '#e5e7eb';
            var bgCol     = isNext ? '#f5f3ff' : 'white';
            btn.style.cssText = 'display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:12px;border:1.5px solid ' + borderCol + ';background:' + bgCol + ';width:100%;cursor:pointer;transition:all .12s';
            btn.onmouseover = function() { this.style.borderColor='#7B61FF'; this.style.background='#f5f3ff'; };
            btn.onmouseout  = function() { this.style.borderColor=borderCol; this.style.background=bgCol; };

            icon.style.background = isNext ? '#ede9fe' : '#f9fafb';
            icon.style.color      = isNext ? '#7B61FF' : '#9ca3af';
            icon.textContent      = isNext ? '→' : '○';
            labelEl.style.color   = isNext ? '#7B61FF' : '#374151';

            if (s.key === 'signed') { hint.textContent = 'Locks commission pool'; hint.style.color = '#d97706'; }
            if (s.key === 'paid')   { hint.textContent = 'Marks commission as paid'; hint.style.color = '#16a34a'; }
            if (isNext) { badge.textContent = 'Next →'; badge.style.color = '#7B61FF'; }

            (function(key) {
                btn.onclick = function() { rbSelectStage(key); };
            })(s.key);
        }

        btn.disabled = isDisabled;
        labelWrap.appendChild(labelEl);
        if (hint.textContent) labelWrap.appendChild(hint);
        btn.appendChild(icon);
        btn.appendChild(labelWrap);
        btn.appendChild(badge);
        btn.id = 'rb-stage-btn-' + s.key;
        list.appendChild(btn);
    });
}

function rbSelectStage(key) {
    rbSelectedStage = key;
    // Highlight selected, de-highlight others
    RB_STAGES.forEach(function(s) {
        var btn = document.getElementById('rb-stage-btn-' + s.key);
        if (!btn || btn.disabled) return;
        if (s.key === key) {
            btn.style.borderColor = '#7B61FF';
            btn.style.background  = '#ede9fe';
        }
    });
    // Show status: from → to
    var lead   = rbGetLead();
    var curIdx = RB_STAGES.findIndex(function(s) { return s.key === (lead ? lead.stage : ''); });
    var tgtIdx = RB_STAGES.findIndex(function(s) { return s.key === key; });
    var statusEl = document.getElementById('rb-stage-status');
    if (statusEl) {
        statusEl.innerHTML = '<span>Moving:</span>'
            + '<span style="font-weight:700;color:#7B61FF;background:#ede9fe;padding:2px 10px;border-radius:9999px">'
            + (curIdx >= 0 ? RB_STAGES[curIdx].label : '') + '</span>'
            + '<span style="color:#374151">→</span>'
            + '<span style="font-weight:700;color:#16a34a;background:#dcfce7;padding:2px 10px;border-radius:9999px">'
            + RB_STAGES[tgtIdx].label + '</span>';
    }
    var btn = document.getElementById('rb-move-confirm-btn');
    if (btn) { btn.style.display = 'block'; btn.textContent = 'Move to ' + RB_STAGES[tgtIdx].label; }
}

function rbConfirmMove() {
    if (!rbSelectedStage || !window.rbDealRef) return;
    var note = (document.getElementById('rb-stage-note') || {}).value || '';
    var btn  = document.getElementById('rb-move-confirm-btn');
    if (btn) { btn.disabled = true; btn.textContent = 'Moving…'; }
    window.rbDealRef.moveToStage(rbSelectedStage, note);
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { rbCloseMoveStage(); rbCloseReassign(); }
});

// ── Reassign modal — searchable referrer picker, pure JS ─────────────────

var rbSelectedReseller = null;

function rbOpenReassign() {
    var m   = document.getElementById('rb-reassign-modal');
    var s   = document.getElementById('rb-reassign-search');
    var btn = document.getElementById('rb-reassign-btn');
    if (!m) return;
    rbSelectedReseller = null;
    if (s) s.value = '';
    if (btn) { btn.disabled = true; btn.style.opacity = '0.5'; btn.style.cursor = 'not-allowed'; btn.textContent = 'Confirm Reassign'; }
    rbReassignRenderList('');
    m.style.display = 'flex';
    if (s) setTimeout(function() { s.focus(); }, 50);
}

function rbCloseReassign() {
    var m = document.getElementById('rb-reassign-modal');
    if (m) m.style.display = 'none';
    rbSelectedReseller = null;
}

function rbReassignFilter() {
    var s = document.getElementById('rb-reassign-search');
    rbReassignRenderList(s ? s.value.trim().toLowerCase() : '');
}

function rbReassignRenderList(query) {
    var list = document.getElementById('rb-reassign-list');
    if (!list) return;
    var currentName = (window.rbLead && window.rbLead.reseller_name)
        ? window.rbLead.reseller_name.trim().toLowerCase() : '';
    var all = window.rbReferrers || [];
    var rows = query ? all.filter(function(r) { return r.name && r.name.toLowerCase().includes(query); }) : all;
    if (rows.length === 0) {
        list.innerHTML = '<div style="padding:24px;text-align:center;color:#9ca3af;font-size:13px">'
            + (all.length === 0 ? 'No referrers in this workspace yet.' : 'No referrers match your search.') + '</div>';
        list.onclick = null;
        return;
    }
    list.innerHTML = rows.map(function(r, idx) {
        var isCurrent  = r.name && r.name.trim().toLowerCase() === currentName;
        var isSelected = rbSelectedReseller && rbSelectedReseller.name === r.name;
        var isLast     = idx === rows.length - 1;
        var border     = isLast ? 'none' : '1px solid #f3f4f6';
        var badge = r.status === 'invited'
            ? '<span style="font-size:10px;padding:2px 7px;border-radius:20px;background:#FEF3C7;color:#D97706;font-weight:600;flex-shrink:0">Invited</span>'
            : '<span style="font-size:10px;padding:2px 7px;border-radius:20px;background:#D1FAE5;color:#065F46;font-weight:600;flex-shrink:0">Active</span>';
        var check = isSelected
            ? '<svg style="width:15px;height:15px;flex-shrink:0;margin-left:6px" fill="none" stroke="#4F46E5" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>' : '';
        if (isCurrent) {
            return '<div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-bottom:' + border + ';background:#f9fafb;cursor:not-allowed" data-current="1">'
                + '<div style="min-width:0;flex:1">'
                + '<div style="font-size:13px;font-weight:600;color:#9ca3af;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + rbEscHtml(r.name) + '</div>'
                + '<div style="font-size:11px;color:#d1d5db;margin-top:1px">Currently assigned</div>'
                + '</div>'
                + badge
                + '</div>';
        }
        return '<div data-name="' + rbEscHtml(r.name) + '" '
            + 'data-sel="' + (isSelected ? '1' : '0') + '" '
            + 'style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-bottom:' + border + ';cursor:pointer;background:' + (isSelected ? '#EEF2FF' : 'white') + ';transition:background .1s">'
            + '<div style="min-width:0;flex:1;pointer-events:none">'
            + '<div style="font-size:13px;font-weight:600;color:#1E1B4B;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + rbEscHtml(r.name) + '</div>'
            + (r.email ? '<div style="font-size:11px;color:#9ca3af;margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + rbEscHtml(r.email) + '</div>' : '')
            + '</div>'
            + '<div style="display:flex;align-items:center;gap:4px;flex-shrink:0;margin-left:8px;pointer-events:none">' + badge + check + '</div>'
            + '</div>';
    }).join('');

    // Event delegation — one listener on the container, no inline onclick needed.
    // pointer-events:none on children ensures e.target is always the row div.
    list.onclick = function(e) {
        var row = e.target.closest('[data-name]');
        if (row && row.dataset.name) rbReassignSelect(row.dataset.name);
    };
    list.onmouseover = function(e) {
        var row = e.target.closest('[data-name]');
        if (row && row.dataset.sel !== '1') row.style.background = '#f9fafb';
    };
    list.onmouseout = function(e) {
        var row = e.target.closest('[data-name]');
        if (row) row.style.background = row.dataset.sel === '1' ? '#EEF2FF' : 'white';
    };
}

function rbReassignSelect(name) {
    var found = (window.rbReferrers || []).find(function(r) {
        return r.name && r.name.trim().toLowerCase() === name.trim().toLowerCase();
    });
    if (!found) return; // name not in the list — refuse selection
    rbSelectedReseller = found;
    var btn = document.getElementById('rb-reassign-btn');
    if (btn) { btn.disabled = false; btn.style.opacity = '1'; btn.style.cursor = 'pointer'; }
    var s = document.getElementById('rb-reassign-search');
    rbReassignRenderList(s ? s.value.trim().toLowerCase() : '');
}

function rbEscHtml(str) {
    return str ? String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : '';
}

async function rbConfirmReassign() {
    if (!rbSelectedReseller) return;
    var name   = rbSelectedReseller.name;
    var btn    = document.getElementById('rb-reassign-btn');
    var leadId = window.rbLead && window.rbLead.id;
    if (!leadId) return;
    if (btn) { btn.disabled = true; btn.textContent = 'Reassigning…'; btn.style.opacity = '0.7'; }
    var csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
    try {
        var res = await fetch('/api/leads/' + leadId + '/reassign', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ reseller_name: name }),
        });
        var updated = await res.json();
        if (updated.id) {
            if (window.rbDealRef) window.rbDealRef.lead = updated;
            window.rbLead = updated;
            rbCloseReassign();
            window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'success', message: 'Deal reassigned to ' + name + '.' } }));
        } else {
            window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'error', message: updated.message || updated.error || 'Failed to reassign.' } }));
            if (btn) { btn.disabled = false; btn.textContent = 'Confirm Reassign'; btn.style.opacity = '1'; btn.style.cursor = 'pointer'; }
        }
    } catch(e) {
        window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'error', message: 'Network error. Please try again.' } }));
        if (btn) { btn.disabled = false; btn.textContent = 'Confirm Reassign'; btn.style.opacity = '1'; btn.style.cursor = 'pointer'; }
    }
}

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
            { key: 'note',       label: 'Notes'      },
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
                note:       'background:#f0fdf4;color:#16a34a',
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
            // Uses window.rbLead set by dealDetail.init() — no Alpine internals needed
            try {
                const lead = window.rbLead;
                if (!lead || !lead.id) return;
                const res = await fetch(`/api/leads/${lead.id}`, { credentials: 'same-origin' });
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

        // Escape HTML then make URLs clickable — safe because we escape first
        linkify(text) {
            if (!text) return '';
            const e = text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            return e.replace(
                /(https?:\/\/[^\s<>&"'()\[\]{}]+)/gi,
                '<a href="$1" target="_blank" rel="noopener noreferrer" style="color:#7B61FF;text-decoration:underline;word-break:break-all">$1</a>'
            );
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

        // ── Delete Deal ───────────────────────────────────────────────────────
        showDeleteConfirm: false,
        deleting: false,

        async deleteDeal() {
            this.deleting = true;
            try {
                const res = await fetch(`/api/leads/${this.lead.id}`, {
                    method:      'DELETE',
                    credentials: 'same-origin',
                    headers: {
                        'Accept':           'application/json',
                        'X-CSRF-TOKEN':     this.csrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.success) {
                    window.location.href = '/tenant/{{ $tenant->id }}/deals';
                } else {
                    this.$dispatch('show-toast', { type: 'error', message: data.error || 'Could not delete deal.' });
                    this.showDeleteConfirm = false;
                }
            } catch (e) {
                this.$dispatch('show-toast', { type: 'error', message: 'Network error. Please try again.' });
                this.showDeleteConfirm = false;
            } finally {
                this.deleting = false;
            }
        },
    };
}

function defaultAmountPrompt(dealId, tenantId) {
    const CSRF = document.querySelector('meta[name=csrf-token]')?.content ?? '';
    return {
        visible: true,
        busy: false,
        error: null,
        async confirm() {
            if (this.busy) return;
            this.busy = true; this.error = null;
            try {
                const r = await fetch(`/api/leads/${dealId}/confirm-default-amount`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({}),
                });
                const d = await r.json();
                if (!r.ok) { this.error = d.message || 'Could not confirm. Please try again.'; return; }
                this.visible = false;
                window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'success', message: 'Default amount confirmed.' } }));
            } catch(e) {
                this.error = 'Network error. Please try again.';
            } finally { this.busy = false; }
        },
        snooze() { this.visible = false; },
    };
}

function dealDetail(leadId, tenantId, ssrLead) {
    return {
        lead: ssrLead || null, loading: !ssrLead,
        showNoteForm: false, showMoveStage: false,
        noteText: '', noteAuthor: '', saving: false,
        moveStageNote: '',
        editFinance: false,
        financeForm: { deal_value: 0, base_cost: 0, added_amount: 0 },
        editSplitId: null, editPct: '', editSaving: false, editErr: '',
        showAddCoRef: false, coRefEmail: '', coRefPct: '0', coRefSaving: false, coRefErr: '',

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
            window.rbLead    = this.lead;   // plain JS reference for Move Stage modal
            window.rbDealRef = this;         // allow rbConfirmMove() to call moveToStage()
            this.fetchContacts();
            if (this.lead) {
                // SSR data already present â€" page is instantly visible.
                // Refresh silently in background so any stale fields update.
                fetch(`/api/leads/${leadId}`, { credentials: 'same-origin' })
                    .then(r => r.ok ? r.json() : null)
                    .then(d => { if (d) { this.lead = d; window.rbLead = d; } })
                    .catch(() => {});
            } else {
                try {
                    const res = await fetch(`/api/leads/${leadId}`, { credentials: 'same-origin' });
                    if (res.ok) this.lead = await res.json();
                } catch(e) { /* silent */ }
                this.loading = false;
            }
        },

        // ── Financial helpers ──
        // When only deal_value is set (no base/added split), use deal_value as aa
        // so commission pool is never shown as ₱0.
        _effectiveAa() {
            const bc = Number(this.lead?.base_cost    || 0);
            const aa = Number(this.lead?.added_amount || 0);
            const dv = Number(this.lead?.deal_value   || 0);
            if (aa <= 0 && bc <= 0 && dv > 0) return dv; // generic fallback
            return aa;
        },
        contractValue() {
            const bc = Number(this.lead?.base_cost || 0);
            const aa = this._effectiveAa();
            return (bc + aa) || Number(this.lead?.deal_value || 0);
        },
        companyShare() { return this._effectiveAa() * 0.30; },
        commPool()     { return this._effectiveAa() * 0.70; },

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

        async moveToStage(stage, externalNote) {
            if (!stage || stage === this.lead?.stage) return;
            if (this.lead?.stage === 'paid') {
                this.$dispatch('show-toast', { type: 'error', message: 'This deal is at the final stage.' });
                rbCloseMoveStage(); return;
            }
            if (this.lead?.commission_status === 'locked' && stage !== 'paid') {
                this.$dispatch('show-toast', { type: 'error', message: 'Commission locked — only Paid move allowed.' });
                rbCloseMoveStage(); return;
            }

            this.saving = true;
            const note = externalNote !== undefined ? externalNote : this.moveStageNote;
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
                    body: JSON.stringify({ stage, note }),
                });
                const data = await res.json();
                if (res.ok && data.id) {
                    this.lead = { ...this.lead, ...data, history: data.history, commission_splits: data.commission_splits };
                    window.rbLead = this.lead; // keep plain JS ref in sync
                    rbCloseMoveStage();
                    this.moveStageNote = '';
                    const stageName = stage.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                    this.$dispatch('show-toast', { type: 'success', message: `Deal moved to ${stageName}.` });
                    this.$dispatch('stage-updated');
                    window.dispatchEvent(new CustomEvent('finance-updated'));
                } else {
                    this.$dispatch('show-toast', { type: 'error', message: data.error || data.message || 'Failed to move stage.' });
                    var btn = document.getElementById('rb-move-confirm-btn');
                    if (btn) { btn.disabled = false; btn.textContent = 'Confirm Move'; }
                }
            } catch {
                this.$dispatch('show-toast', { type: 'error', message: 'Network error. Please try again.' });
            } finally { this.saving = false; }
        },

        async adminSaveCoRef(tenantId, dealId, csrf) {
            if (!this.coRefEmail || this.coRefSaving) return;
            this.coRefSaving = true; this.coRefErr = '';
            try {
                const r = await fetch(`/tenant/${tenantId}/deals/${dealId}/referrers`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ referrer_email: this.coRefEmail.trim(), percentage: parseFloat(this.coRefPct) || 0 }),
                });
                const d = await r.json();
                if (!r.ok) { this.coRefErr = d.error || 'Could not add co-referrer.'; return; }
                this.showAddCoRef = false;
                window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'success', message: `${d.display_name} added as co-referrer (${d.percentage}%).` } }));
                setTimeout(() => window.location.reload(), 800);
            } catch(e) { this.coRefErr = 'Network error. Please try again.'; }
            finally { this.coRefSaving = false; }
        },

        async adminRemoveCoRef(splitId, name) {
            if (!confirm('Remove ' + (name || 'this co-referrer') + ' as a co-referrer? Their commission share will be released.')) return;
            const csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
            try {
                const r = await fetch(`/tenant/{{ $tenant->id }}/deals/${this.lead.id}/splits/${splitId}`, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const d = await r.json().catch(() => ({}));
                if (!r.ok) {
                    window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'error', message: d.error || 'Could not remove co-referrer.' } }));
                    return;
                }
                // Remove from local state
                if (this.lead && this.lead.commission_splits) {
                    this.lead.commission_splits = this.lead.commission_splits.filter(s => s.id !== splitId);
                }
                window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'success', message: (name || 'Co-referrer') + ' removed.' } }));
                // Reload partnerSplitSection to reflect change
                window.dispatchEvent(new CustomEvent('finance-updated'));
            } catch(e) {
                window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'error', message: 'Network error. Please try again.' } }));
            }
        },

        async adminSaveSplit(split, url, csrf) {
            this.editSaving = true; this.editErr = '';
            try {
                const r = await fetch(url, {
                    method: 'PATCH',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ percentage: parseFloat(this.editPct) }),
                });
                const d = await r.json();
                if (!r.ok) { this.editErr = d.error || 'Could not update share.'; return; }
                // Update split percentage in the local lead data
                const idx = (this.lead.commission_splits || []).findIndex(s => s.id === split.id);
                if (idx >= 0) this.lead.commission_splits[idx].percentage = d.new_percentage;
                this.editSplitId = null; this.editPct = '';
                window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'success', message: 'Co-referrer share updated.' } }));
            } catch(e) { this.editErr = 'Network error. Please try again.'; }
            finally { this.editSaving = false; }
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

        // â"€â"€ Contact helpers â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€
        contactFullName(c) { return [c.first_name, c.last_name].filter(Boolean).join(' ') || 'â€"'; },
        contactInitials(c) {
            const p = [c.first_name, c.last_name].filter(Boolean);
            return p.length ? p.map(n => n[0]).join('').toUpperCase() : '?';
        },

        async fetchContacts() {
            this.loadingContacts = true;
            try {
                const res = await fetch(`/api/deals/${leadId}/contacts?tenant_id=${tenantId}`, {
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
                const res = await fetch(`/api/contacts?per_page=200&tenant_id=${tenantId}`, {
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
        splits: [], coRefs: [], loading: true, showAdd: false, saving: false, formError: '',
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
            // Only check email duplicates when an email is actually provided
            const email = (this.form.partner_email || '').toLowerCase().trim();
            if (!email) return false;
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
                    this.coRefs       = (lead.commission_splits || []).filter(s => s.role === 'secondary');
                }
            } catch(e) { this.splits = []; this.$dispatch('show-toast', { type: 'error', message: 'Failed to load partner splits.' }); }
            this.loading = false;
        },

        async addSplit() {
            this.formError = '';
            if (!this.form.partner_name.trim()) { this.formError = 'Partner name is required.'; return; }
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
                    body:        JSON.stringify({ ...this.form, partner_email: this.form.partner_email.trim() || null, tenant_id: tenantId }),
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

        async removeCoRef(splitId, name) {
            if (!confirm('Remove ' + (name || 'this co-referrer') + ' as a co-referrer? Their commission share will be released.')) return;
            try {
                const csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
                const r = await fetch(`/tenant/${tenantId}/deals/${dealId}/splits/${splitId}`, {
                    method:      'DELETE',
                    credentials: 'same-origin',
                    headers:     { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                });
                const d = await r.json().catch(() => ({}));
                if (r.ok) {
                    await this.load();
                    this.$dispatch('show-toast', { type: 'success', message: (name || 'Co-referrer') + ' removed.' });
                } else {
                    this.$dispatch('show-toast', { type: 'error', message: d.error || 'Could not remove co-referrer.' });
                }
            } catch(e) {
                this.$dispatch('show-toast', { type: 'error', message: 'Network error. Please try again.' });
            }
        },

        async removeSplit(splitId, partnerName) {
            const name = partnerName || 'this partner';
            if (!confirm('Remove ' + name + ' from this deal? This will free up their commission split.')) return;
            try {
                const csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
                const r = await fetch(`/api/leads/${dealId}/partner-splits/${splitId}`, {
                    method:      'DELETE',
                    credentials: 'same-origin',
                    headers:     { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                });
                const d = await r.json().catch(() => ({}));
                if (r.ok) {
                    await this.load();
                    this.$dispatch('show-toast', { type: 'success', message: d.message || 'Partner removed.' });
                } else {
                    this.$dispatch('show-toast', { type: 'error', message: d.error || 'Could not remove partner.' });
                }
            } catch(e) {
                this.$dispatch('show-toast', { type: 'error', message: 'Network error. Please try again.' });
            }
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

function rbApprovalPanel() {
    const CSRF  = document.querySelector('meta[name=csrf-token]')?.content ?? '';
    const hdr   = () => ({ 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' });
    return {
        apBusy: false,

        async approveApproval(approvalId, note) {
            if (!confirm('Approve this request? The action will be executed immediately.')) return;
            this.apBusy = true;
            try {
                const tenantId = this.$el.dataset.tenantId ?? window.__tenantId;
                const res = await fetch(`/tenant/${tenantId}/approvals/${approvalId}/approve`, {
                    method: 'POST', credentials: 'same-origin', headers: hdr(),
                    body: JSON.stringify({ reviewer_note: note || null }),
                });
                const d = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(d.error || 'Approval failed.');
                this.$dispatch('show-toast', { type: 'success', message: 'Request approved and executed.' });
                setTimeout(() => window.location.reload(), 900);
            } catch(e) {
                this.$dispatch('show-toast', { type: 'error', message: e.message || 'Could not approve. Try again.' });
            } finally { this.apBusy = false; }
        },

        async rejectApproval(approvalId, note) {
            if (!note || !note.trim()) {
                this.$dispatch('show-toast', { type: 'error', message: 'A review note is required when declining.' });
                return;
            }
            if (!confirm('Decline this request?')) return;
            this.apBusy = true;
            try {
                const tenantId = this.$el.dataset.tenantId ?? window.__tenantId;
                const res = await fetch(`/tenant/${tenantId}/approvals/${approvalId}/reject`, {
                    method: 'POST', credentials: 'same-origin', headers: hdr(),
                    body: JSON.stringify({ reviewer_note: note }),
                });
                const d = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(d.error || 'Rejection failed.');
                this.$dispatch('show-toast', { type: 'success', message: 'Request declined. Referrer has been notified.' });
                setTimeout(() => window.location.reload(), 900);
            } catch(e) {
                this.$dispatch('show-toast', { type: 'error', message: e.message || 'Could not decline. Try again.' });
            } finally { this.apBusy = false; }
        },
    };
}

// ── Extension Request Review Modal ────────────────────────────────────────
// Auto-opens when URL contains ?extension_request_id=xxx (from notification click)
function extensionReviewModal() {
    const CSRF = () => document.querySelector('meta[name=csrf-token]')?.content ?? '';
    const hdrs = () => ({ 'Content-Type':'application/json','X-CSRF-TOKEN':CSRF(),'Accept':'application/json','X-Requested-With':'XMLHttpRequest' });

    return {
        open:       false,
        loading:    false,
        saving:     false,
        request:    null,
        error:      '',
        mode:       'view',        // 'view' | 'approve' | 'reject'
        approvedDays: '',
        adminNote:  '',
        rejectReason: '',

        init() {
            const params = new URLSearchParams(window.location.search);
            const id     = params.get('extension_request_id');
            if (id) this.fetchRequest(id);
        },

        async fetchRequest(id) {
            this.loading = true; this.open = true; this.error = '';
            try {
                const res = await fetch(`/api/extension-requests/${id}`, {
                    credentials: 'same-origin',
                    headers: { 'Accept':'application/json','X-Requested-With':'XMLHttpRequest' },
                });
                if (!res.ok) throw new Error('Request not found.');
                this.request = await res.json();
                this.approvedDays = this.request.requested_days ?? '';
            } catch(e) {
                this.error = e.message || 'Could not load extension request.';
            } finally { this.loading = false; }
        },

        close() {
            this.open = false; this.request = null; this.mode = 'view';
            this.error = ''; this.approvedDays = ''; this.adminNote = ''; this.rejectReason = '';
            // Remove param from URL without reloading
            const url = new URL(window.location.href);
            url.searchParams.delete('extension_request_id');
            window.history.replaceState({}, '', url.toString());
        },

        async approve() {
            if (!this.approvedDays || parseInt(this.approvedDays) < 1) {
                this.error = 'Enter a valid number of days to approve.'; return;
            }
            this.saving = true; this.error = '';
            try {
                const res = await fetch(`/api/extension-requests/${this.request.id}/approve`, {
                    method: 'POST', credentials: 'same-origin', headers: hdrs(),
                    body: JSON.stringify({ approved_days: parseInt(this.approvedDays), admin_note: this.adminNote || null }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.error || 'Approval failed.');
                this.$dispatch('show-toast', { type:'success', message:'Extension approved — referrer notified.' });
                setTimeout(() => { this.close(); window.location.reload(); }, 800);
            } catch(e) {
                this.error = e.message || 'Could not approve. Please try again.';
            } finally { this.saving = false; }
        },

        async reject() {
            if (!this.rejectReason.trim()) { this.error = 'Please provide a reason for rejection.'; return; }
            this.saving = true; this.error = '';
            try {
                const res = await fetch(`/api/extension-requests/${this.request.id}/reject`, {
                    method: 'POST', credentials: 'same-origin', headers: hdrs(),
                    body: JSON.stringify({ reason: this.rejectReason.trim() }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.error || 'Rejection failed.');
                this.$dispatch('show-toast', { type:'success', message:'Extension request denied — referrer notified.' });
                setTimeout(() => { this.close(); window.location.reload(); }, 800);
            } catch(e) {
                this.error = e.message || 'Could not reject. Please try again.';
            } finally { this.saving = false; }
        },
    };
}
</script>

{{-- ── Extension Request Review Modal (global, auto-opens from notification) ─ --}}
<div x-data="extensionReviewModal()" x-init="init()"
     x-show="open" x-cloak
     class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
     style="background:rgba(0,0,0,.55);backdrop-filter:blur(4px)"
     @keydown.escape.window="close()">

    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto"
         @click.stop>

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <div>
                <h2 class="text-base font-bold text-[#1E1B4B]">Extension Request</h2>
                <p class="text-xs text-gray-400 mt-0.5" x-show="request"
                   x-text="request?.deal_name ?? request?.lead?.name ?? ''"></p>
            </div>
            <button @click="close()"
                    class="w-8 h-8 rounded-xl bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="p-5 space-y-4">

            {{-- Loading --}}
            <div x-show="loading" class="flex items-center justify-center py-10">
                <svg class="w-6 h-6 animate-spin text-[#7B61FF]" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
            </div>

            {{-- Error --}}
            <div x-show="error && !loading"
                 class="flex items-center gap-2 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span x-text="error"></span>
            </div>

            {{-- Request details --}}
            <template x-if="request && !loading">
                <div class="space-y-4">

                    {{-- Status badge --}}
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold px-2.5 py-1 rounded-full capitalize"
                              :class="{
                                  'bg-amber-100 text-amber-700': request.status === 'pending_review',
                                  'bg-green-100 text-green-700':  request.status === 'approved',
                                  'bg-red-100 text-red-700':      request.status === 'rejected',
                                  'bg-gray-100 text-gray-500':    !['pending_review','approved','rejected'].includes(request.status),
                              }"
                              x-text="(request.status ?? '').replace(/_/g,' ')"></span>
                        <span class="text-xs text-gray-400"
                              x-text="request.created_at ? new Date(request.created_at).toLocaleDateString('default',{month:'short',day:'numeric',year:'numeric'}) : ''"></span>
                    </div>

                    {{-- Details grid --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-gray-50 rounded-xl px-3 py-2.5">
                            <p class="text-[10px] text-gray-400 font-medium mb-0.5">Referrer</p>
                            <p class="text-sm font-semibold text-[#1E1B4B] truncate"
                               x-text="request.requested_by_name ?? request.reseller_name ?? '—'"></p>
                        </div>
                        <div class="bg-amber-50 rounded-xl px-3 py-2.5">
                            <p class="text-[10px] text-amber-500 font-medium mb-0.5">Days Requested</p>
                            <p class="text-sm font-bold text-amber-700" x-text="(request.requested_days ?? 0) + ' days'"></p>
                        </div>
                    </div>

                    <div x-show="request.reason" class="bg-gray-50 rounded-xl px-3 py-2.5">
                        <p class="text-[10px] text-gray-400 font-medium mb-1">Reason from Referrer</p>
                        <p class="text-sm text-gray-700 leading-relaxed" x-text="request.reason"></p>
                    </div>

                    <div x-show="request.admin_note" class="bg-blue-50 rounded-xl px-3 py-2.5">
                        <p class="text-[10px] text-blue-400 font-medium mb-1">Admin Note</p>
                        <p class="text-sm text-blue-700 leading-relaxed" x-text="request.admin_note"></p>
                    </div>

                    {{-- Already decided --}}
                    <template x-if="request.status !== 'pending_review'">
                        <p class="text-xs text-center text-gray-400 py-2">
                            This request has already been <span class="font-semibold" x-text="request.status.replace(/_/g,' ')"></span>.
                        </p>
                    </template>

                    {{-- Approve form --}}
                    <template x-if="request.status === 'pending_review' && mode === 'approve'">
                        <div class="space-y-3 p-4 bg-green-50 border border-green-200 rounded-xl">
                            <p class="text-sm font-semibold text-green-800">Approve Extension</p>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Days to approve *</label>
                                <input type="number" x-model.number="approvedDays" min="1" max="90"
                                       class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm bg-white outline-none focus:border-green-400 focus:ring-2 focus:ring-green-100">
                                <p class="text-[10px] text-gray-400 mt-1">Requested: <span x-text="request.requested_days"></span> days</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Admin note <span class="font-normal text-gray-400">(optional)</span></label>
                                <textarea x-model="adminNote" rows="2" maxlength="500"
                                          class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm bg-white outline-none focus:border-green-400 focus:ring-2 focus:ring-green-100 resize-none"
                                          placeholder="Internal note for this decision…"></textarea>
                            </div>
                            <div class="flex gap-2">
                                <button @click="mode = 'view'"
                                        class="flex-1 px-4 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
                                    Back
                                </button>
                                <button @click="approve()" :disabled="saving"
                                        class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white transition-all disabled:opacity-60"
                                        style="background:linear-gradient(135deg,#16a34a,#15803d)">
                                    <svg x-show="saving" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    <svg x-show="!saving" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    <span x-text="saving ? 'Approving…' : 'Confirm Approval'"></span>
                                </button>
                            </div>
                        </div>
                    </template>

                    {{-- Reject form --}}
                    <template x-if="request.status === 'pending_review' && mode === 'reject'">
                        <div class="space-y-3 p-4 bg-red-50 border border-red-200 rounded-xl">
                            <p class="text-sm font-semibold text-red-800">Deny Extension</p>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Reason for denial *</label>
                                <textarea x-model="rejectReason" rows="3" maxlength="500"
                                          class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm bg-white outline-none focus:border-red-400 focus:ring-2 focus:ring-red-100 resize-none"
                                          placeholder="Explain why the extension is being denied…"></textarea>
                            </div>
                            <div class="flex gap-2">
                                <button @click="mode = 'view'"
                                        class="flex-1 px-4 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
                                    Back
                                </button>
                                <button @click="reject()" :disabled="saving"
                                        class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white transition-all disabled:opacity-60"
                                        style="background:linear-gradient(135deg,#dc2626,#b91c1c)">
                                    <svg x-show="saving" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    <span x-text="saving ? 'Denying…' : 'Confirm Denial'"></span>
                                </button>
                            </div>
                        </div>
                    </template>

                    {{-- Action buttons (main view, pending only) --}}
                    <template x-if="request.status === 'pending_review' && mode === 'view'">
                        <div class="flex gap-2.5 pt-1">
                            <button @click="mode = 'approve'"
                                    class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white transition-all"
                                    style="background:linear-gradient(135deg,#16a34a,#15803d)">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                Approve
                            </button>
                            <button @click="mode = 'reject'"
                                    class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white transition-all"
                                    style="background:linear-gradient(135deg,#dc2626,#b91c1c)">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                Deny
                            </button>
                        </div>
                    </template>

                </div>
            </template>
        </div>
    </div>
</div>

@endsection



