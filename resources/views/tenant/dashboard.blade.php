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
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">

        {{-- KPI 1: Total Deals --}}
        <div class="card" style="padding:16px 20px">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background:#EDE9FE">
                        <svg class="w-4 h-4 text-[#7B61FF]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/></svg>
                    </div>
                    <span class="text-sm font-medium text-gray-700">Total Deals</span>
                </div>
                <button class="text-gray-300 hover:text-gray-500 text-lg leading-none">⋯</button>
            </div>
            <div class="relative mb-4" style="height:2px;background:#f3f4f6;border-radius:9999px">
                <div class="absolute inset-y-0 left-0 rounded-full" style="background:#7B61FF;transition:width .4s"
                     :style="`width:${Math.min((leads.length/50)*100,100)}%`"></div>
                <div class="absolute top-1/2 -translate-y-1/2 w-3.5 h-3.5 rounded-full bg-white border-2 border-[#7B61FF] shadow-sm"
                     :style="`left:calc(${Math.min((leads.length/50)*100,96)}% - 7px)`"></div>
            </div>
            <p class="text-3xl font-bold text-[#1E1B4B]" x-text="leads.length"></p>
            <div class="flex items-center gap-2 mt-1.5">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold"
                      :class="newThisWeek() > 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-500'">
                    <svg class="w-3 h-3" viewBox="0 0 12 12" fill="none"><path :d="newThisWeek()>0?'M2 9L6 4l4 5':'M2 3L6 8l4-5'" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                    <span x-text="newThisWeek()"></span>
                </span>
                <span class="text-xs text-gray-400">Since last week</span>
            </div>
        </div>

        {{-- KPI 2: Pipeline Value --}}
        <div class="card" style="padding:16px 20px">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background:#D1FAE5">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <span class="text-sm font-medium text-gray-700">Pipeline Value</span>
                </div>
                <button class="text-gray-300 hover:text-gray-500 text-lg leading-none">⋯</button>
            </div>
            <div class="relative mb-4" style="height:2px;background:#f3f4f6;border-radius:9999px">
                <div class="absolute inset-y-0 left-0 rounded-full" style="background:#10B981;transition:width .4s"
                     :style="`width:${Math.min((leads.reduce((s,l)=>s+(+l.deal_value||0),0)/10000000)*100,100)}%`"></div>
                <div class="absolute top-1/2 -translate-y-1/2 w-3.5 h-3.5 rounded-full bg-white border-2 border-emerald-500 shadow-sm"
                     :style="`left:calc(${Math.min((leads.reduce((s,l)=>s+(+l.deal_value||0),0)/10000000)*100,96)}% - 7px)`"></div>
            </div>
            <p class="text-3xl font-bold text-[#1E1B4B]" x-text="pipelineValue()"></p>
            <div class="flex items-center gap-2 mt-1.5">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-600">
                    <svg class="w-3 h-3" viewBox="0 0 12 12" fill="none"><path d="M2 9L6 4l4 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                    1.8%
                </span>
                <span class="text-xs text-gray-400">Since last week</span>
            </div>
        </div>

        {{-- KPI 3: Resellers --}}
        <div class="card" style="padding:16px 20px">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background:#DBEAFE">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <span class="text-sm font-medium text-gray-700">Total Resellers</span>
                </div>
                <button class="text-gray-300 hover:text-gray-500 text-lg leading-none">⋯</button>
            </div>
            <div class="relative mb-4" style="height:2px;background:#f3f4f6;border-radius:9999px">
                <div class="absolute inset-y-0 left-0 rounded-full" style="background:#3B82F6;transition:width .4s"
                     :style="`width:${Math.min((resellers.length/20)*100,100)}%`"></div>
                <div class="absolute top-1/2 -translate-y-1/2 w-3.5 h-3.5 rounded-full bg-white border-2 border-blue-500 shadow-sm"
                     :style="`left:calc(${Math.min((resellers.length/20)*100,96)}% - 7px)`"></div>
            </div>
            <p class="text-3xl font-bold text-[#1E1B4B]" x-text="resellers.length"></p>
            <div class="flex items-center gap-2 mt-1.5">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-600">
                    Active
                </span>
                <span class="text-xs text-gray-400">Since last week</span>
            </div>
        </div>

        {{-- KPI 4: Commission Paid --}}
        <div class="card" style="padding:16px 20px">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background:#FEF3C7">
                        <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                    </div>
                    <span class="text-sm font-medium text-gray-700">Commission Paid</span>
                </div>
                <button class="text-gray-300 hover:text-gray-500 text-lg leading-none">⋯</button>
            </div>
            <div class="relative mb-4" style="height:2px;background:#f3f4f6;border-radius:9999px">
                <div class="absolute inset-y-0 left-0 rounded-full" style="background:#F59E0B;transition:width .4s"
                     :style="`width:${leads.length ? Math.min((leads.filter(l=>l.commission_status==='paid').length/leads.length)*100,100) : 0}%`"></div>
                <div class="absolute top-1/2 -translate-y-1/2 w-3.5 h-3.5 rounded-full bg-white border-2 border-amber-400 shadow-sm"
                     :style="`left:calc(${leads.length ? Math.min((leads.filter(l=>l.commission_status==='paid').length/leads.length)*100,96) : 0}% - 7px)`"></div>
            </div>
            <p class="text-3xl font-bold text-[#1E1B4B]" x-text="commissionStat('paid').value"></p>
            <div class="flex items-center gap-2 mt-1.5">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-600"
                      x-text="commissionStat('paid').count + ' deals'"></span>
                <span class="text-xs text-gray-400">Settled</span>
            </div>
        </div>
    </div>

    {{-- ══ CHARTS ROW: Bar + Area + Concentric ══ --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">

        {{-- Deal Stages Bar Chart (5 cols) --}}
        <div class="card lg:col-span-5" style="padding:20px">
            <div class="flex items-center justify-between mb-1">
                <h3 class="font-semibold text-[#1E1B4B]">Deal Stages</h3>
                <select class="text-xs font-medium text-gray-500 border border-gray-100 rounded-lg px-2.5 py-1.5 bg-gray-50 outline-none">
                    <option>Monthly</option><option>Quarterly</option><option>All Time</option>
                </select>
            </div>
            {{-- Y-axis labels + bars --}}
            <div class="flex gap-3 mt-4">
                <div class="flex flex-col justify-between text-right pb-6" style="width:24px">
                    <span class="text-[10px] text-gray-300" x-text="maxCount"></span>
                    <span class="text-[10px] text-gray-300" x-text="Math.round(maxCount*0.75)"></span>
                    <span class="text-[10px] text-gray-300" x-text="Math.round(maxCount*0.5)"></span>
                    <span class="text-[10px] text-gray-300" x-text="Math.round(maxCount*0.25)"></span>
                    <span class="text-[10px] text-gray-300">0</span>
                </div>
                <div class="flex-1 relative">
                    {{-- Grid lines --}}
                    <div class="absolute inset-0 pb-6 flex flex-col justify-between pointer-events-none">
                        <template x-for="i in [0,1,2,3,4]" :key="i">
                            <div class="w-full border-t border-dashed border-gray-100"></div>
                        </template>
                    </div>
                    {{-- Bars --}}
                    <div class="relative flex items-end justify-around pb-6 gap-2" style="height:180px">
                        <template x-for="(stage, idx) in funnel" :key="stage.stage">
                            <div x-data="{ hovered: false }"
                                 @mouseenter="hovered=true" @mouseleave="hovered=false"
                                 class="flex flex-col items-center gap-1.5 cursor-pointer" style="width:28px;flex-shrink:0">
                                {{-- Tooltip --}}
                                <div x-show="hovered" class="absolute z-10 bg-white rounded-xl shadow-lg border border-gray-100 px-3 py-2 text-center pointer-events-none"
                                     style="bottom:calc(100% + 8px);white-space:nowrap;transform:translateX(-50%);left:50%;min-width:80px">
                                    <p class="text-[10px] font-semibold text-gray-500" x-text="stage.label"></p>
                                    <p class="text-sm font-bold text-[#1E1B4B]" x-text="stage.count + ' deals'"></p>
                                </div>
                                <div class="w-full relative overflow-hidden transition-all duration-200"
                                     :style="`height:${Math.max(10,(stage.count/maxCount)*140)}px;border-radius:9999px`">
                                    <div class="absolute inset-0" style="background:rgba(123,97,255,.12);border-radius:9999px"></div>
                                    <div class="absolute inset-0 hatch-bar" style="border-radius:9999px"
                                         :style="`opacity:${hovered?0:1};transition:opacity .15s`"></div>
                                    <div class="absolute inset-0" style="background:#7B61FF;border-radius:9999px"
                                         :style="`opacity:${hovered?1:0};transition:opacity .15s`"></div>
                                </div>
                                <span class="text-[9px] text-gray-400 text-center leading-tight" x-text="stage.label.split(' ')[0]"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
            {{-- Legend --}}
            <div class="flex items-center gap-3 mt-2 flex-wrap">
                <div class="flex items-center gap-1.5">
                    <div class="w-3 h-3 rounded-sm hatch-bar" style="background:rgba(123,97,255,.12)"></div>
                    <span class="text-[10px] text-gray-400">Inactive stage</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <div class="w-3 h-3 rounded-sm" style="background:#7B61FF"></div>
                    <span class="text-[10px] text-gray-400">Active (hover)</span>
                </div>
            </div>
        </div>

        {{-- Pipeline Progress Area Chart (4 cols) --}}
        <div class="card lg:col-span-4" style="padding:20px">
            <div class="flex items-center justify-between mb-1">
                <h3 class="font-semibold text-[#1E1B4B]">Pipeline Progress</h3>
                <select class="text-xs font-medium text-gray-500 border border-gray-100 rounded-lg px-2.5 py-1.5 bg-gray-50 outline-none">
                    <option>Monthly</option><option>Weekly</option>
                </select>
            </div>
            <div class="flex items-center gap-3 mt-2 mb-3">
                <p class="text-2xl font-bold text-[#1E1B4B]"
                   x-text="Math.round((leads.filter(l=>l.stage==='paid').length / Math.max(leads.length,1))*100) + '%'"></p>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-600">
                    <svg class="w-3 h-3" viewBox="0 0 12 12" fill="none"><path d="M2 9L6 4l4 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                    1.8%
                </span>
                <span class="text-xs text-gray-400">Since last week</span>
            </div>
            {{-- SVG area chart --}}
            <div style="height:120px;position:relative">
                <svg width="100%" height="120" :viewBox="`0 0 300 100`" preserveAspectRatio="none" style="overflow:visible">
                    <defs>
                        <linearGradient id="areaGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#7B61FF" stop-opacity="0.25"/>
                            <stop offset="100%" stop-color="#7B61FF" stop-opacity="0"/>
                        </linearGradient>
                    </defs>
                    <template x-if="areaChartData().length > 1">
                        <g>
                            <path :d="areaPath(areaChartData(), 300, 90).area" fill="url(#areaGrad)"/>
                            <path :d="areaPath(areaChartData(), 300, 90).line" fill="none" stroke="#7B61FF" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <template x-for="(pt, i) in areaPath(areaChartData(), 300, 90).pts" :key="i">
                                <circle :cx="pt.x" :cy="pt.y" r="3.5" fill="white" stroke="#7B61FF" stroke-width="2"/>
                            </template>
                        </g>
                    </template>
                </svg>
            </div>
            {{-- X axis labels --}}
            <div class="flex justify-between mt-1">
                <template x-for="m in areaChartData()" :key="m.label">
                    <span class="text-[10px] text-gray-300" x-text="m.label"></span>
                </template>
            </div>
        </div>

        {{-- Status Analysis Concentric (3 cols) --}}
        <div class="card lg:col-span-3" style="padding:20px">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-[#1E1B4B]">Status Analysis</h3>
                <select class="text-xs font-medium text-gray-500 border border-gray-100 rounded-lg px-2 py-1.5 bg-gray-50 outline-none">
                    <option>Monthly</option><option>All Time</option>
                </select>
            </div>
            {{-- Concentric circles --}}
            <div class="flex justify-center my-2">
                <div class="relative" style="width:160px;height:160px">
                    {{-- Outermost: Paid --}}
                    <div class="absolute inset-0 rounded-full flex items-start justify-center pt-2"
                         style="background:#EDE9FE">
                        <div class="text-center">
                            <p class="text-[9px] font-semibold text-violet-400">Paid</p>
                            <p class="text-xs font-bold text-violet-600" x-text="leads.filter(l=>l.stage==='paid').length"></p>
                        </div>
                    </div>
                    {{-- Active --}}
                    <div class="absolute rounded-full flex items-start justify-center pt-2"
                         style="inset:20px;background:#C4B5FD">
                        <div class="text-center">
                            <p class="text-[9px] font-semibold text-violet-600">Active</p>
                            <p class="text-xs font-bold text-violet-800" x-text="leads.filter(l=>l.status==='active').length"></p>
                        </div>
                    </div>
                    {{-- Expiring --}}
                    <div class="absolute rounded-full flex items-start justify-center pt-1.5"
                         style="inset:40px;background:#7B61FF">
                        <div class="text-center">
                            <p class="text-[9px] font-semibold text-violet-200">Expiring</p>
                            <p class="text-xs font-bold text-white" x-text="leads.filter(l=>l.status==='expiring').length"></p>
                        </div>
                    </div>
                    {{-- Innermost: Expired --}}
                    <div class="absolute rounded-full flex flex-col items-center justify-center"
                         style="inset:60px;background:#4C1D95">
                        <p class="text-[8px] font-semibold text-purple-200 leading-none">Expired</p>
                        <p class="text-sm font-bold text-white leading-none mt-0.5"
                           x-text="leads.filter(l=>l.status==='expired').length"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ RECENT DEALS GRID + TOP RESELLERS ══ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Recent Deals Cards (2 cols) --}}
        <div class="card lg:col-span-2" style="padding:20px">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-[#1E1B4B]">Recent Deals</h3>
                <div class="flex items-center gap-2">
                    <div class="relative">
                        <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" placeholder="Search" class="pl-8 pr-3 py-1.5 text-xs border border-gray-200 rounded-lg bg-gray-50 outline-none focus:ring-1 focus:ring-violet-300 w-28">
                    </div>
                    <a href="{{ route('tenant.deals', $tenant->id) }}" class="text-xs text-violet-600 hover:text-violet-700 font-medium border border-violet-200 rounded-lg px-2.5 py-1.5 hover:bg-violet-50">View all</a>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" x-show="leads.length > 0">
                <template x-for="lead in leads.slice(0,4)" :key="lead.id">
                    <a :href="`/tenant/{{ $tenant->id }}/deals/${lead.id}`"
                       class="group rounded-2xl border border-gray-100 overflow-hidden hover:shadow-md hover:border-violet-200 transition-all">
                        {{-- Card header gradient --}}
                        <div class="relative h-20 flex items-center justify-center"
                             :style="`background:linear-gradient(135deg,${stageColor(lead.stage)}22,${stageColor(lead.stage)}44)`">
                            <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-white font-bold text-lg shadow-sm"
                                 :style="`background:${stageColor(lead.stage)}`"
                                 x-text="lead.name.slice(0,2).toUpperCase()"></div>
                        </div>
                        {{-- Card body --}}
                        <div class="p-3">
                            <p class="text-sm font-semibold text-[#1E1B4B] truncate" x-text="lead.name"></p>
                            <p class="text-xs text-gray-400 mt-0.5 capitalize" x-text="lead.stage.replace('_',' ')"></p>
                            <div class="flex items-center justify-between mt-2.5">
                                <span class="text-sm font-bold text-[#1E1B4B]"
                                      x-text="lead.deal_value ? '₱' + Number(lead.deal_value).toLocaleString() : '₱0'"></span>
                                <span :class="{'badge badge-green':lead.status==='active','badge badge-orange':lead.status==='expiring','badge badge-red':lead.status==='expired','badge badge-gray':true}"
                                      x-text="lead.status" style="font-size:10px"></span>
                            </div>
                            <div class="flex items-center gap-1 mt-1.5 text-gray-400">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                <span class="text-[10px] truncate" x-text="lead.reseller_name || '—'"></span>
                            </div>
                        </div>
                    </a>
                </template>
            </div>
            <p class="text-gray-400 text-sm text-center py-8" x-show="leads.length === 0">No deals yet</p>
        </div>

        {{-- Top Resellers Panel (1 col) --}}
        <div class="card" style="padding:20px">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-[#1E1B4B]">Top Resellers</h3>
                <a href="{{ route('tenant.resellers', $tenant->id) }}" class="text-xs text-violet-600 hover:text-violet-700 font-medium">
                    <select class="text-xs font-medium text-gray-500 border border-gray-100 rounded-lg px-2 py-1.5 bg-gray-50 outline-none">
                        <option>Today</option><option>This Week</option><option>This Month</option>
                    </select>
                </a>
            </div>
            <div class="space-y-3" x-show="resellers.length > 0">
                <template x-for="(r, i) in resellers.slice(0,5)" :key="r.id">
                    <div class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-gray-50 transition-colors cursor-pointer">
                        <div class="relative shrink-0">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-xs font-bold"
                                 :style="`background:${['#7B61FF','#FF6CAB','#3B82F6','#10B981','#F59E0B'][i%5]}`"
                                 x-text="r.name.slice(0,2).toUpperCase()"></div>
                            <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-emerald-400 border border-white rounded-full"></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-[#1E1B4B] truncate" x-text="r.name"></p>
                            <p class="text-[10px] text-gray-400 truncate"
                               x-text="(r.assigned_leads||0) + ' deals · ' + (r.performance_score||0) + '%'"></p>
                        </div>
                        <p class="text-xs font-bold text-[#1E1B4B] shrink-0"
                           x-text="r.closed_value ? '₱' + (Number(r.closed_value)/1000).toFixed(0)+'K' : '₱0'"></p>
                    </div>
                </template>
            </div>
            <p class="text-gray-400 text-sm text-center py-8" x-show="resellers.length === 0">No resellers yet</p>
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

        stageColor(stage) {
            const map = { introduction:'#9CA3AF', presentation:'#3B82F6', contract_sent:'#F59E0B', signed:'#8B5CF6', paid:'#10B981' };
            return map[stage] || '#7B61FF';
        },

        areaChartData() {
            const months = [];
            for (let i = 5; i >= 0; i--) {
                const d = new Date();
                d.setDate(1); d.setMonth(d.getMonth() - i);
                const key = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`;
                months.push({
                    label: d.toLocaleDateString('en', {month:'short'}),
                    count: this.leads.filter(l => l.created_at && l.created_at.startsWith(key)).length
                });
            }
            return months;
        },

        areaPath(data, w, h) {
            if (!data || data.length < 2) return { area:'', line:'', pts:[] };
            const max = Math.max(...data.map(d=>d.count), 1);
            const pts = data.map((d,i) => ({
                x: (i / (data.length-1)) * w,
                y: h - (d.count / max) * (h - 12) - 6
            }));
            let line = `M ${pts[0].x},${pts[0].y}`;
            for (let i = 1; i < pts.length; i++) {
                const cp1x = pts[i-1].x + (pts[i].x - pts[i-1].x) * 0.4;
                const cp2x = pts[i].x   - (pts[i].x - pts[i-1].x) * 0.4;
                line += ` C ${cp1x},${pts[i-1].y} ${cp2x},${pts[i].y} ${pts[i].x},${pts[i].y}`;
            }
            const area = `${line} L ${pts[pts.length-1].x},${h} L ${pts[0].x},${h} Z`;
            return { area, line, pts };
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



