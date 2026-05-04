@extends('layouts.app')
@section('title', $tenant->name . ' — Dashboard')

@section('nav')
    @include('tenant._nav')
@endsection

@section('topbar-actions')
    <div class="flex items-center gap-2 bg-gray-100 rounded-xl p-1" x-data>
        <button @click="$store.dashView.set('basic')"
                :class="$store.dashView.mode === 'basic' ? 'bg-white shadow-sm text-[#1E1B4B]' : 'text-gray-500 hover:text-gray-700'"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all">Basic</button>
        <button @click="$store.dashView.set('full')"
                :class="$store.dashView.mode === 'full' ? 'bg-white shadow-sm text-[#1E1B4B]' : 'text-gray-500 hover:text-gray-700'"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all">Full View</button>
    </div>
    <button x-data @click="$dispatch('open-add-deal')" class="btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        <span class="hidden sm:inline">New Deal</span>
    </button>
@endsection

<script>
document.addEventListener('alpine:init', () => {
    Alpine.store('dashView', {
        mode: 'basic',
        set(m) { this.mode = m; }
    });
});
</script>

@section('content')
<div class="space-y-5"
     x-data="tenantDashboard('{{ $tenant->id }}')"
     x-init="init()"
     @open-add-deal.window="showAdd = true">

    {{-- ══ PIPELINE OVERVIEW BANNER ══ --}}
    <div class="gradient-banner rounded-2xl p-5 sm:p-6 text-white relative overflow-hidden">
        <div class="relative z-10">

            {{-- Top row: program info + subscription status --}}
            <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                <div>
                    <p class="text-white/60 text-xs font-medium uppercase tracking-wider">{{ $tenant->name }}</p>
                    <h2 class="text-xl sm:text-2xl font-bold mt-0.5 leading-tight">{{ $tenant->program_name }}</h2>
                </div>
                {{-- Subscription status pill (compact, right-aligned) --}}
                <div class="flex items-center gap-2 flex-wrap justify-end" x-show="subscription">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold"
                          style="background:rgba(255,255,255,0.15);backdrop-filter:blur(4px);border:1px solid rgba(255,255,255,0.2)">
                        <span class="w-1.5 h-1.5 rounded-full" :class="{
                            'bg-emerald-400': subscription?.status === 'active',
                            'bg-blue-300':    subscription?.status === 'trial',
                            'bg-orange-400':  subscription?.status === 'past_due',
                            'bg-red-400':     ['suspended','canceled'].includes(subscription?.status||''),
                        }"></span>
                        <span x-text="subscription?.plan?.name || 'No plan'"></span>
                        <span class="text-white/60">·</span>
                        <span class="text-white/80 capitalize" x-text="subscription?.status || '—'"></span>
                    </span>
                    <span x-show="subscription?.status === 'trial' && trialDaysLeft() > 0"
                          class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium"
                          style="background:rgba(96,165,250,0.25);border:1px solid rgba(147,197,253,0.3);color:#bfdbfe">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span x-text="trialDaysLeft() + ' days left'"></span>
                    </span>
                </div>
            </div>

            {{-- Metrics row --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-5">
                <div>
                    <p class="text-white/80 text-xs mb-0.5">Total Deals</p>
                    <p class="text-2xl font-bold tabular-nums" x-text="leads.length || '0'"></p>
                </div>
                <div>
                    <p class="text-white/80 text-xs mb-0.5">Pipeline Value</p>
                    <p class="text-2xl font-bold tabular-nums" x-text="stats.pipeline_value ? '₱' + (stats.pipeline_value/1000000).toFixed(1)+'M' : pipelineValue()"></p>
                </div>
                <div>
                    <p class="text-white/80 text-xs mb-0.5">Active Deals</p>
                    <p class="text-2xl font-bold tabular-nums" x-text="leads.filter(l=>l.status==='active').length"></p>
                </div>
                <div>
                    <p class="text-white/80 text-xs mb-0.5">New This Week</p>
                    <p class="text-2xl font-bold tabular-nums" x-text="newThisWeek()"></p>
                </div>
                <div>
                    <p class="text-white/80 text-xs mb-0.5">Closed / Paid</p>
                    <p class="text-2xl font-bold tabular-nums" x-text="leads.filter(l=>l.stage==='paid').length"></p>
                </div>
            </div>
        </div>

        {{-- Decorative orbs --}}
        <div class="absolute -right-10 -top-10 w-48 h-48 bg-white/10 rounded-full pointer-events-none"></div>
        <div class="absolute -right-4 top-10 w-24 h-24 bg-white/10 rounded-full pointer-events-none"></div>
        <div class="absolute left-1/2 -bottom-8 w-32 h-32 bg-white/5 rounded-full pointer-events-none"></div>
    </div>

    {{-- ══ KPI CARDS ══ --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

        {{-- Total Deals --}}
        <div class="card">
            <div class="flex items-center justify-between mb-3">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background:#EDE9FE">
                    <svg class="w-4 h-4 text-[#7B61FF]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
                <span class="text-xs font-medium text-gray-400">Total</span>
            </div>
            <div class="relative h-1 bg-gray-100 rounded-full mb-3">
                <div class="absolute inset-y-0 left-0 rounded-full" style="background:linear-gradient(90deg,#7B61FF,#A78BFA)"
                     :style="`width:${Math.min((leads.length/50)*100,100)}%`"></div>
                <div class="absolute top-1/2 -translate-y-1/2 w-3 h-3 bg-white border-2 border-[#7B61FF] rounded-full shadow"
                     :style="`left:${Math.min((leads.length/50)*100,97)}%`"></div>
            </div>
            <p class="text-2xl font-bold text-[#1E1B4B]" x-text="leads.length"></p>
            <div class="flex items-center gap-1.5 mt-1">
                <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-xs font-semibold bg-violet-100 text-violet-700">
                    <svg class="w-3 h-3" viewBox="0 0 12 12" fill="none"><path d="M2 9L6 4l4 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Active
                </span>
                <span class="text-xs text-gray-400">Since last month</span>
            </div>
        </div>

        {{-- Pipeline Value --}}
        <div class="card">
            <div class="flex items-center justify-between mb-3">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background:#D1FAE5">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <span class="text-xs font-medium text-gray-400">Pipeline</span>
            </div>
            <div class="relative h-1 bg-gray-100 rounded-full mb-3">
                <div class="absolute inset-y-0 left-0 rounded-full" style="background:linear-gradient(90deg,#10B981,#34D399)"
                     :style="`width:${Math.min((leads.reduce((s,l)=>s+(+l.deal_value||0),0)/10000000)*100,100)}%`"></div>
                <div class="absolute top-1/2 -translate-y-1/2 w-3 h-3 bg-white border-2 border-emerald-500 rounded-full shadow"
                     :style="`left:${Math.min((leads.reduce((s,l)=>s+(+l.deal_value||0),0)/10000000)*100,97)}%`"></div>
            </div>
            <p class="text-2xl font-bold text-[#1E1B4B]" x-text="pipelineValue()"></p>
            <div class="flex items-center gap-1.5 mt-1">
                <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">
                    <svg class="w-3 h-3" viewBox="0 0 12 12" fill="none"><path d="M2 9L6 4l4 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Growing
                </span>
                <span class="text-xs text-gray-400">Total value</span>
            </div>
        </div>

        {{-- Pending Commission --}}
        <div class="card">
            <div class="flex items-center justify-between mb-3">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background:#FEF3C7">
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                </div>
                <span class="text-xs font-medium text-gray-400">Commission</span>
            </div>
            <div class="relative h-1 bg-gray-100 rounded-full mb-3">
                <div class="absolute inset-y-0 left-0 rounded-full" style="background:linear-gradient(90deg,#F59E0B,#FCD34D)"
                     :style="`width:${leads.length ? Math.min((leads.filter(l=>l.commission_status==='pending').length/leads.length)*100,100) : 0}%`"></div>
                <div class="absolute top-1/2 -translate-y-1/2 w-3 h-3 bg-white border-2 border-amber-400 rounded-full shadow"
                     :style="`left:${leads.length ? Math.min((leads.filter(l=>l.commission_status==='pending').length/leads.length)*100,97) : 0}%`"></div>
            </div>
            <p class="text-2xl font-bold text-[#1E1B4B]" x-text="commissionStat('pending').value"></p>
            <div class="flex items-center gap-1.5 mt-1">
                <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700"
                      x-text="commissionStat('pending').count + ' deals'"></span>
                <span class="text-xs text-gray-400">Pending</span>
            </div>
        </div>

        {{-- Active Resellers --}}
        <div class="card">
            <div class="flex items-center justify-between mb-3">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background:#DBEAFE">
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <span class="text-xs font-medium text-gray-400">Resellers</span>
            </div>
            <div class="relative h-1 bg-gray-100 rounded-full mb-3">
                <div class="absolute inset-y-0 left-0 rounded-full" style="background:linear-gradient(90deg,#3B82F6,#60A5FA)"
                     :style="`width:${Math.min((resellers.length/20)*100,100)}%`"></div>
                <div class="absolute top-1/2 -translate-y-1/2 w-3 h-3 bg-white border-2 border-blue-500 rounded-full shadow"
                     :style="`left:${Math.min((resellers.length/20)*100,97)}%`"></div>
            </div>
            <p class="text-2xl font-bold text-[#1E1B4B]" x-text="resellers.length"></p>
            <div class="flex items-center gap-1.5 mt-1">
                <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">
                    Active
                </span>
                <span class="text-xs text-gray-400">Since last week</span>
            </div>
        </div>
    </div>

    {{-- ══ CHARTS ROW ══ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Deal Stages Bar Chart --}}
        <div class="card lg:col-span-2">
            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-1.5">
                    <h3 class="font-semibold text-[#1E1B4B]">Deal Stages</h3>
                    <x-info-tip text="Lead count at each stage: Introduction → Presentation → Contract Sent → Signed → Paid." />
                </div>
                <select class="text-xs font-medium text-gray-500 border border-gray-200 rounded-lg px-2.5 py-1.5 bg-white outline-none focus:ring-2 focus:ring-violet-200">
                    <option>Monthly</option><option>Quarterly</option><option>All Time</option>
                </select>
            </div>
            <div class="flex items-end justify-around gap-3 pb-2" style="height:160px" x-show="funnel.length > 0">
                <template x-for="stage in funnel" :key="stage.stage">
                    <div class="rb-bar-wrap flex flex-col items-center gap-1.5 cursor-pointer" style="width:32px;flex-shrink:0">
                        <span class="text-xs font-semibold text-[#1E1B4B]" x-text="stage.count"></span>
                        <div class="w-full transition-all duration-200 relative overflow-hidden"
                             :style="`height:${Math.max(16,(stage.count/maxCount)*120)}px; border-radius:9999px;`">
                            <div class="absolute inset-0" style="background:rgba(123,97,255,.13);border-radius:9999px"></div>
                            <div class="absolute inset-0 hatch-bar" style="border-radius:9999px"></div>
                            <div class="absolute inset-0 rb-bar-solid transition-opacity duration-150"
                                 style="background:#7B61FF;border-radius:9999px;opacity:0"></div>
                        </div>
                        <span class="text-[10px] text-gray-400 text-center leading-tight" style="max-width:48px" x-text="stage.label"></span>
                    </div>
                </template>
            </div>
            <p class="text-gray-400 text-sm text-center py-8" x-show="funnel.length === 0">No deal data yet</p>
        </div>

        {{-- Deal Status Analysis --}}
        <div class="card">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-[#1E1B4B]">Status Analysis</h3>
                <select class="text-xs font-medium text-gray-500 border border-gray-200 rounded-lg px-2.5 py-1.5 bg-white outline-none focus:ring-2 focus:ring-violet-200">
                    <option>Monthly</option><option>All Time</option>
                </select>
            </div>

            {{-- Donut chart --}}
            <div class="flex justify-center my-2">
                <div class="relative" style="width:130px;height:130px">
                    <div class="w-full h-full rounded-full transition-all"
                         :style="`background: conic-gradient(
                            #7B61FF 0% ${donutPct('active')}%,
                            #F59E0B ${donutPct('active')}% ${donutPct('active')+donutPct('expiring')}%,
                            #EF4444 ${donutPct('active')+donutPct('expiring')}% ${donutPct('active')+donutPct('expiring')+donutPct('expired')}%,
                            #10B981 ${donutPct('active')+donutPct('expiring')+donutPct('expired')}% 100%
                         )`"></div>
                    <div class="absolute rounded-full bg-white flex flex-col items-center justify-center" style="inset:26px">
                        <p class="text-xl font-bold text-[#1E1B4B]" x-text="leads.length"></p>
                        <p class="text-[9px] text-gray-400 uppercase tracking-wide">Total</p>
                    </div>
                </div>
            </div>

            {{-- Legend --}}
            <div class="space-y-2 mt-3">
                <template x-for="item in statusLegend()" :key="item.label">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="`background:${item.color}`"></span>
                            <span class="text-xs text-gray-600" x-text="item.label"></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold text-[#1E1B4B]" x-text="item.count"></span>
                            <span class="text-[10px] text-gray-400" x-text="'(' + item.pct + '%)'"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- ══ COMMISSION + RECENT DEALS ══ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Commission Breakdown --}}
        <div class="card lg:col-span-2">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-[#1E1B4B]">Commission Overview</h3>
                <a href="{{ route('tenant.reports', $tenant->id) }}" class="text-xs text-violet-600 hover:text-violet-700 font-medium">View reports →</a>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div class="text-center p-3 rounded-xl" style="background:#F5F3FF">
                    <p class="text-xs font-semibold text-violet-600 uppercase tracking-wide mb-1">Pending</p>
                    <p class="text-xl font-bold text-[#1E1B4B]" x-text="commissionStat('pending').value"></p>
                    <p class="text-xs text-gray-400 mt-0.5" x-text="commissionStat('pending').count + ' deals'"></p>
                    <div class="mt-2 h-1 bg-violet-100 rounded-full overflow-hidden">
                        <div class="h-full bg-violet-500 rounded-full transition-all"
                             :style="`width:${leads.length ? (leads.filter(l=>l.commission_status==='pending').length/leads.length)*100 : 0}%`"></div>
                    </div>
                </div>
                <div class="text-center p-3 rounded-xl" style="background:#FFF7ED">
                    <p class="text-xs font-semibold text-orange-600 uppercase tracking-wide mb-1">Locked</p>
                    <p class="text-xl font-bold text-[#1E1B4B]" x-text="commissionStat('locked').value"></p>
                    <p class="text-xs text-gray-400 mt-0.5" x-text="commissionStat('locked').count + ' deals'"></p>
                    <div class="mt-2 h-1 bg-orange-100 rounded-full overflow-hidden">
                        <div class="h-full bg-orange-400 rounded-full transition-all"
                             :style="`width:${leads.length ? (leads.filter(l=>l.commission_status==='locked').length/leads.length)*100 : 0}%`"></div>
                    </div>
                </div>
                <div class="text-center p-3 rounded-xl" style="background:#ECFDF5">
                    <p class="text-xs font-semibold text-emerald-600 uppercase tracking-wide mb-1">Paid</p>
                    <p class="text-xl font-bold text-[#1E1B4B]" x-text="commissionStat('paid').value"></p>
                    <p class="text-xs text-gray-400 mt-0.5" x-text="commissionStat('paid').count + ' deals'"></p>
                    <div class="mt-2 h-1 bg-emerald-100 rounded-full overflow-hidden">
                        <div class="h-full bg-emerald-500 rounded-full transition-all"
                             :style="`width:${leads.length ? (leads.filter(l=>l.commission_status==='paid').length/leads.length)*100 : 0}%`"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent Deals --}}
        <div class="card">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-[#1E1B4B]">Recent Deals</h3>
                <a href="{{ route('tenant.deals', $tenant->id) }}" class="text-xs text-violet-600 hover:text-violet-700 font-medium">View all</a>
            </div>
            <div class="space-y-1" x-show="leads.length > 0">
                <template x-for="lead in leads.slice(0,4)" :key="lead.id">
                    <a :href="`/tenant/{{ $tenant->id }}/deals/${lead.id}`"
                       class="flex items-center gap-2.5 p-2 rounded-xl hover:bg-[#F0EFFA] transition-colors">
                        <div class="w-7 h-7 rounded-lg flex items-center justify-center text-[#7B61FF] font-bold text-xs shrink-0"
                             style="background:#EDE9FE" x-text="lead.name.slice(0,2).toUpperCase()"></div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-[#1E1B4B] truncate" x-text="lead.name"></p>
                            <p class="text-[10px] text-gray-400 capitalize" x-text="lead.stage.replace('_',' ')"></p>
                        </div>
                        <span :class="{'badge badge-green':lead.status==='active','badge badge-orange':lead.status==='expiring','badge badge-red':lead.status==='expired','badge badge-gray':true}"
                              x-text="lead.status"></span>
                    </a>
                </template>
            </div>
            <p class="text-gray-400 text-sm text-center py-6" x-show="leads.length === 0">No deals yet</p>
        </div>
    </div>

    {{-- Add Deal Modal --}}
    <div x-show="showAdd" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-[#1E1B4B]">Add Deal</h3>
                <button @click="showAdd = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div><label class="form-label">Lead Name *</label><input type="text" x-model="form.name" class="form-input" placeholder="Full name or company"></div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Stage</label>
                        <select x-model="form.stage" class="form-input">
                            <option value="introduction">Introduction</option>
                            <option value="presentation">Presentation</option>
                            <option value="contract_sent">Contract Sent</option>
                            <option value="signed">Signed</option>
                            <option value="paid">Paid</option>
                        </select>
                    </div>
                    <div><label class="form-label">Deal Value (₱)</label><input type="number" x-model="form.deal_value" class="form-input" placeholder="0" min="0"></div>
                </div>
                <div><label class="form-label">Reseller Name *</label><input type="text" x-model="form.reseller_name" class="form-input" placeholder="Assigned reseller"></div>
                <div class="flex justify-end gap-3">
                    <button @click="showAdd = false" class="btn-secondary">Cancel</button>
                    <button @click="addLead()" :disabled="saving" class="btn-primary" x-text="saving ? 'Saving...' : 'Add Deal'"></button>
                </div>
            </div>
        </div>
    </div>


    {{-- ═══ FULL VIEW SECTIONS ═══ --}}
    <div x-show="$store.dashView.mode === 'full'" class="space-y-5">

        {{-- Top Referrers by Performance --}}
        <div class="card">
            <h3 class="font-semibold text-[#1E1B4B] mb-4">Referrer Performance</h3>
            <div class="space-y-3" x-show="resellers.length > 0">
                <template x-for="(r, i) in resellers.slice(0, 8)" :key="r.id">
                    <div class="flex items-center gap-3">
                        <div class="w-6 h-6 rounded-full bg-purple-100 flex items-center justify-center text-purple-700 text-xs font-bold shrink-0" x-text="i + 1"></div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between mb-1">
                                <p class="text-sm font-medium text-[#1E1B4B] truncate" x-text="r.name"></p>
                                <span class="text-xs text-gray-500 tabular-nums shrink-0 ml-2" x-text="(r.performance_score || 0) + '%'"></span>
                            </div>
                            <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all"
                                     :class="(r.performance_score||0) >= 70 ? 'bg-emerald-500' : (r.performance_score||0) >= 40 ? 'bg-orange-400' : 'bg-gray-300'"
                                     :style="'width:' + Math.min(r.performance_score||0, 100) + '%'"></div>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-sm font-semibold text-[#1E1B4B]" x-text="r.closed_value ? '₱' + Number(r.closed_value).toLocaleString() : '₱0'"></p>
                            <p class="text-xs text-gray-400" x-text="(r.assigned_leads || 0) + ' deals'"></p>
                        </div>
                    </div>
                </template>
            </div>
            <p x-show="resellers.length === 0" class="text-gray-400 text-sm text-center py-6">No referrers yet</p>
        </div>

        {{-- Stage Breakdown & Data Quality --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

            <div class="card">
                <h3 class="font-semibold text-[#1E1B4B] mb-4">Stage Breakdown</h3>
                <div class="space-y-2.5">
                    <template x-for="stage in stageSummary()" :key="stage.key">
                        <div class="flex items-center gap-3">
                            <div class="w-2 h-2 rounded-full shrink-0" :style="'background:' + stage.color"></div>
                            <span class="text-sm text-gray-600 w-28 shrink-0" x-text="stage.label"></span>
                            <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all"
                                     :style="'width:' + (maxLeadCount > 0 ? (stage.count / maxLeadCount) * 100 : 0) + '%; background:' + stage.color"></div>
                            </div>
                            <span class="text-sm font-semibold text-[#1E1B4B] w-8 text-right tabular-nums" x-text="stage.count"></span>
                        </div>
                    </template>
                </div>
            </div>

            <div class="card">
                <h3 class="font-semibold text-[#1E1B4B] mb-4">Data Quality</h3>
                <div class="space-y-3">
                    @foreach([
                        ['Active records',   "leads.filter(l => l.status === 'active').length",     '#10B981'],
                        ['Expiring (≤7 days)', "leads.filter(l => (l.days_left??21) <= 7 && l.status === 'active').length", '#F59E0B'],
                        ['Expired',          "leads.filter(l => l.status === 'expired').length",    '#EF4444'],
                        ['No referrer',      "leads.filter(l => !l.reseller_name).length",          '#9CA3AF'],
                    ] as [$label, $expr, $color])
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full shrink-0" style="background:{{ $color }}"></span>
                            {{ $label }}
                        </span>
                        <span class="font-semibold text-[#1E1B4B] tabular-nums" x-text="{{ $expr }}"></span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

    </div>

</div>

<script>
function tenantDashboard(tenantId) {
    return {
        leads: [], resellers: [], funnel: [], stats: {}, metric: {}, maxCount: 0, maxLeadCount: 1,
        subscription: null,
        showAdd: false, saving: false,
        form: { name: '', stage: 'introduction', deal_value: '', reseller_name: '' },

        async init() {
            const [leadsRes, resellersRes, funnelRes, metricsRes, subRes] = await Promise.all([
                fetch(`/api/leads?tenant_id=${tenantId}`),
                fetch(`/api/resellers?tenant_id=${tenantId}`),
                fetch(`/api/analytics/funnel?tenant_id=${tenantId}`),
                fetch(`/api/metrics/${tenantId}`),
                fetch(`/api/billing/tenants/${tenantId}/subscription`),
            ]);

            const leadsData = await leadsRes.json();
            this.leads      = Array.isArray(leadsData) ? leadsData : [];

            const resData   = await resellersRes.json();
            this.resellers  = Array.isArray(resData) ? resData : (resData.data || []);

            this.funnel       = await funnelRes.json();
            const metricsData = await metricsRes.json();
            this.stats        = metricsData.detail ?? {};
            this.metric       = metricsData.metric ?? {};
            this.subscription = await subRes.json();
            this.maxCount     = Math.max(...this.funnel.map(f => f.count), 1);
            this.maxLeadCount = Math.max(...this.stageSummary().map(s => s.count), 1);
        },

        pipelineValue() {
            const t = this.leads.reduce((s,l) => s + (Number(l.deal_value)||0), 0);
            if (t >= 1000000) return '₱' + (t/1000000).toFixed(1) + 'M';
            if (t >= 1000)    return '₱' + Math.round(t/1000) + 'K';
            return t > 0 ? '₱' + t.toLocaleString() : '₱0';
        },

        newThisWeek() {
            const cutoff = new Date();
            cutoff.setDate(cutoff.getDate() - 7);
            return this.leads.filter(l => l.created_at && new Date(l.created_at) >= cutoff).length;
        },

        fmtDash(v) {
            const n = Math.round(Number(v) || 0);
            if (n >= 1000000) return (n/1000000).toFixed(1) + 'M';
            if (n >= 1000)    return Math.round(n/1000) + 'K';
            return n.toLocaleString('en');
        },

        commissionStat(status) {
            const filtered = this.leads.filter(l => l.commission_status === status);
            const total    = filtered.reduce((s, l) => s + (Number(l.deal_value) || 0), 0);
            return { count: filtered.length, value: this.fmtDash(total) };
        },

        stageSummary() {
            const stages = [
                { key:'introduction',  label:'Introduction',  color:'#9CA3AF' },
                { key:'presentation',  label:'Presentation',  color:'#3B82F6' },
                { key:'contract_sent', label:'Contract Sent', color:'#F59E0B' },
                { key:'signed',        label:'Signed',        color:'#8B5CF6' },
                { key:'paid',          label:'Paid',          color:'#10B981' },
            ];
            return stages.map(s => ({ ...s, count: this.leads.filter(l => l.stage === s.key).length }));
        },

        donutPct(status) {
            if (!this.leads.length) return 25;
            const map = { active: this.leads.filter(l=>l.status==='active').length, expiring: this.leads.filter(l=>l.status==='expiring').length, expired: this.leads.filter(l=>l.status==='expired').length, paid: this.leads.filter(l=>l.stage==='paid').length };
            return Math.round((map[status]||0) / this.leads.length * 100);
        },

        statusLegend() {
            const t = this.leads.length || 1;
            return [
                { label:'Active',   color:'#7B61FF', count: this.leads.filter(l=>l.status==='active').length,   pct: Math.round(this.leads.filter(l=>l.status==='active').length/t*100)   },
                { label:'Expiring', color:'#F59E0B', count: this.leads.filter(l=>l.status==='expiring').length, pct: Math.round(this.leads.filter(l=>l.status==='expiring').length/t*100) },
                { label:'Expired',  color:'#EF4444', count: this.leads.filter(l=>l.status==='expired').length,  pct: Math.round(this.leads.filter(l=>l.status==='expired').length/t*100)  },
                { label:'Paid',     color:'#10B981', count: this.leads.filter(l=>l.stage==='paid').length,      pct: Math.round(this.leads.filter(l=>l.stage==='paid').length/t*100)      },
            ];
        },

        trialDaysLeft() {
            if (!this.subscription?.trial_end_date) return 0;
            const diff = Math.ceil((new Date(this.subscription.trial_end_date) - new Date()) / 86400000);
            return Math.max(0, diff);
        },

        ucFirst(str) { return str ? str.charAt(0).toUpperCase() + str.slice(1) : ''; },

        async addLead() {
            if (!this.form.name || !this.form.reseller_name) return;
            this.saving = true;
            try {
                const res = await fetch('/api/leads', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ ...this.form, tenant_id: tenantId }),
                });
                const lead = await res.json();
                if (lead.id) {
                    this.leads.unshift(lead);
                    this.maxLeadCount = Math.max(...this.stageSummary().map(s => s.count), 1);
                }
                this.showAdd = false;
                this.form = { name: '', stage: 'introduction', deal_value: '', reseller_name: '' };
            } finally { this.saving = false; }
        },
    }
}
</script>

@if($accessExtendedNotif)
{{-- ═══════════════════════════════════════════════════
     R BUNNY ACCESS EXTENDED POPUP
     Shown once when super admin extends tenant access.
     Dismissed via API → marks notification as read.
     ═══════════════════════════════════════════════════ --}}
<style>
@keyframes rb-pop-in {
    0%   { opacity:0; transform:scale(0.85) translateY(32px); }
    65%  { transform:scale(1.02) translateY(-4px); }
    100% { opacity:1; transform:scale(1) translateY(0); }
}
@keyframes rb-fade-up {
    from { opacity:0; transform:translateY(14px); }
    to   { opacity:1; transform:translateY(0); }
}
@keyframes rb-float {
    0%,100% { transform:translateY(0); }
    50%      { transform:translateY(-8px); }
}
@keyframes rb-confetti-fall {
    0%   { transform:translateY(0) rotateZ(var(--r,0deg)); opacity:1; }
    100% { transform:translateY(800px) rotateZ(calc(var(--r,0deg)+540deg)); opacity:0; }
}
.rb-modal   { animation: rb-pop-in .5s cubic-bezier(.34,1.56,.64,1) forwards; }
.rb-float   { animation: rb-float 3s ease-in-out infinite; }
.rb-fade-1  { animation: rb-fade-up .4s ease-out .15s both; }
.rb-fade-2  { animation: rb-fade-up .4s ease-out .30s both; }
.rb-fade-3  { animation: rb-fade-up .4s ease-out .45s both; }
.rb-fade-4  { animation: rb-fade-up .4s ease-out .60s both; }
</style>

<div x-data="rbAccessExtendedPopup('{{ $accessExtendedNotif->id }}')"
     x-init="$nextTick(() => { if(show) launchConfetti(); })"
     x-show="show" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-[#0D0B26]/70 backdrop-blur-md" @click="dismiss()"></div>

    {{-- Confetti --}}
    <div id="rb-confetti" class="absolute inset-0 overflow-hidden pointer-events-none z-10"></div>

    {{-- Modal --}}
    <div class="relative z-20 w-full max-w-sm rb-modal" @click.stop>
        <div class="bg-white rounded-[24px] shadow-[0_24px_64px_rgba(123,97,255,0.22)] overflow-hidden">

            {{-- Gradient header --}}
            <div class="relative text-center px-6 pt-8 pb-7 overflow-hidden"
                 style="background:linear-gradient(145deg,#1E1B4B 0%,#3B0764 40%,#7B61FF 75%,#FF6CAB 100%)">

                {{-- Background orbs --}}
                <div class="absolute -top-8 -right-8 w-40 h-40 rounded-full opacity-10 bg-white pointer-events-none"></div>
                <div class="absolute bottom-0 -left-6 w-28 h-28 rounded-full opacity-10 bg-white pointer-events-none"></div>

                {{-- R Bunny mascot --}}
                <div class="rb-float inline-block mb-4">
                    <img src="/images/mascots/r-bunny-celebration.webp"
                         alt="R Bunny celebrating"
                         class="w-28 h-28 object-contain mx-auto drop-shadow-lg">
                </div>

                <div class="rb-fade-1">
                    <h2 class="text-[22px] font-bold text-white leading-tight mb-1">
                        R Bunny extended<br>your access!
                    </h2>
                    <p class="text-white/60 text-sm">A gift from the Referral Bunny team</p>
                </div>
            </div>

            {{-- Body --}}
            <div class="px-6 pb-6 pt-5 space-y-4">

                {{-- Days pill --}}
                <div class="rb-fade-2 flex items-center justify-center">
                    <div class="flex items-center gap-3 px-5 py-3 rounded-2xl"
                         style="background:linear-gradient(135deg,#F0EFFA,#FDF2F8);border:1.5px solid #E9D5FF">
                        <svg class="w-5 h-5 text-purple-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <p class="text-xs text-purple-400 font-semibold uppercase tracking-wide leading-none mb-0.5">Access Extended</p>
                            <p class="text-xl font-bold text-[#1E1B4B] leading-none">
                                +{{ $accessExtendedNotif->metadata_json['days'] ?? '?' }}
                                {{ ($accessExtendedNotif->metadata_json['days'] ?? 1) === 1 ? 'Day' : 'Days' }}
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Message --}}
                <div class="rb-fade-3 text-center">
                    <p class="text-sm text-gray-600 leading-relaxed">
                        {{ $accessExtendedNotif->message }}
                    </p>
                    @if(!empty($accessExtendedNotif->metadata_json['reactivated']))
                    <div class="mt-2 inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-xs font-semibold text-emerald-700">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        Account Reactivated
                    </div>
                    @endif
                </div>

                {{-- CTA --}}
                <div class="rb-fade-4 flex gap-2.5 pt-1">
                    <button @click="dismiss()"
                            class="flex-1 py-2.5 rounded-xl text-sm text-gray-500 hover:text-[#1E1B4B] hover:bg-gray-50 transition-all font-medium border border-gray-200">
                        Got it
                    </button>
                    <button @click="dismiss()"
                            class="flex-[2] btn-primary text-sm justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                        Continue to Dashboard
                    </button>
                </div>

                <p class="rb-fade-4 text-center text-[11px] text-gray-400">
                    {{ $tenant->name }} · {{ $tenant->program_name }}
                </p>
            </div>
        </div>

        {{-- Close button --}}
        <button @click="dismiss()"
                class="absolute -top-3 -right-3 w-8 h-8 rounded-full bg-white shadow-md flex items-center justify-center text-gray-400 hover:text-gray-700 transition-colors border border-gray-100">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
</div>

<script>
function rbAccessExtendedPopup(notifId) {
    return {
        show: true,

        async dismiss() {
            this.show = false;
            try {
                await fetch(`/api/notifications/${notifId}`, {
                    method:  'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ is_read: true, is_dismissed: true }),
                });
            } catch(e) { /* silent — popup already closed */ }
        },

        launchConfetti() {
            const container = document.getElementById('rb-confetti');
            if (!container) return;
            const colors = ['#7B61FF','#FF6CAB','#10B981','#F59E0B','#3B82F6','#EC4899'];
            for (let i = 0; i < 60; i++) {
                const el       = document.createElement('div');
                const color    = colors[Math.floor(Math.random() * colors.length)];
                const isCircle = Math.random() > 0.5;
                const w        = Math.random() * 9 + 4;
                const h        = isCircle ? w : w * 0.4;
                const dur      = (Math.random() * 1.8 + 1.5).toFixed(2);
                const delay    = (Math.random() * 0.6).toFixed(2);
                el.style.cssText = `
                    position:absolute;left:${Math.random()*100}%;top:-${h*2}px;
                    width:${w}px;height:${h}px;background:${color};opacity:.85;
                    border-radius:${isCircle?'50%':'2px'};
                    --r:${Math.random()*360}deg;
                    animation:rb-confetti-fall ${dur}s ease-in ${delay}s forwards;
                `;
                container.appendChild(el);
                setTimeout(() => el.remove(), (+dur + +delay) * 1000 + 200);
            }
        },
    };
}
</script>
@endif

@endsection



