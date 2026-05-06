@extends('layouts.app')
@section('title', $tenant->name . ' — Dashboard')

@section('nav')
    @include('tenant._nav')
@endsection

@section('topbar-actions')
    {{-- View toggle --}}
    <div class="flex items-center gap-1 bg-gray-100 rounded-xl p-1" x-data>
        <button @click="$store.dashView.set('basic')"
                :class="$store.dashView.mode==='basic' ? 'bg-white shadow-sm text-[#1E1B4B]' : 'text-gray-500'"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all">Overview</button>
        <button @click="$store.dashView.set('full')"
                :class="$store.dashView.mode==='full' ? 'bg-white shadow-sm text-[#1E1B4B]' : 'text-gray-500'"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all">Full View</button>
    </div>
    <button x-data @click="$dispatch('open-add-deal')" class="btn-primary text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        <span class="hidden sm:inline">New Deal</span>
    </button>
@endsection

<script>
document.addEventListener('alpine:init', () => {
    Alpine.store('dashView', { mode: 'basic', set(m) { this.mode = m; } });
});
</script>

@section('content')
<div x-data="tenantDashboard('{{ $tenant->id }}', {{ json_encode($currentResellerName) }})"
     x-init="init()"
     @open-add-deal.window="showAdd = true"
     class="space-y-5">


    {{-- ── KPI CARDS ────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">

        @php
        $kpis = [
            ['icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/></svg>', 'bg' => '#EDE9FE', 'color' => '#7B61FF', 'label' => 'Total Referrals', 'val' => 'leads.length', 'sub' => 'newThisWeek() + \' new this week\'', 'up' => 'newThisWeek()>0', 'line' => '#7B61FF', 'pct' => 'Math.min((leads.length/50)*100,100)'],
            ['icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>', 'bg' => '#D1FAE5', 'color' => '#10B981', 'label' => 'Pipeline Value', 'val' => 'pipelineValue()', 'sub' => '\'Total deal value\'', 'up' => 'true', 'line' => '#10B981', 'pct' => 'Math.min((leads.reduce((s,l)=>s+(+l.deal_value||0),0)/10000000)*100,100)'],
            ['icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>', 'bg' => '#DBEAFE', 'color' => '#3B82F6', 'label' => 'Active Referrers', 'val' => 'resellers.length', 'sub' => '\'Referrer partners\'', 'up' => 'true', 'line' => '#3B82F6', 'pct' => 'Math.min((resellers.length/20)*100,100)'],
            ['icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>', 'bg' => '#FEF3C7', 'color' => '#F59E0B', 'label' => 'Conversion Rate', 'val' => 'conversionRate()+\'%\'', 'sub' => '\'Referrals to paid\'', 'up' => 'conversionRate()>0', 'line' => '#F59E0B', 'pct' => 'conversionRate()'],
        ];
        @endphp

        @foreach($kpis as $k)
        <div class="kpi-inline-card bg-white rounded-2xl shadow-sm border border-gray-100" style="padding:16px 18px">
            {{-- Row 1: icon + label + menu --}}
            <div class="flex items-center justify-between mb-2 sm:mb-3">
                <div class="flex items-center gap-1.5 sm:gap-2 min-w-0 flex-1">
                    <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg flex items-center justify-center shrink-0"
                         style="background:{{ $k['bg'] }};color:{{ $k['color'] }}">{!! $k['icon'] !!}</div>
                    <span class="kpi-label text-xs sm:text-sm font-medium text-gray-600 truncate">{{ $k['label'] }}</span>
                </div>
                <button class="text-gray-300 hover:text-gray-500 font-bold shrink-0 ml-1" style="font-size:14px;line-height:1">⋮</button>
            </div>
            {{-- Row 2: slider line --}}
            <div class="relative mb-3" style="height:2px;background:#f3f4f6;border-radius:9999px">
                <div class="absolute inset-y-0 left-0 rounded-full"
                     style="background:{{ $k['line'] }};transition:width .4s"
                     :style="`width:${{{ $k['pct'] }}}%`"></div>
                <div class="absolute top-1/2 -translate-y-1/2 rounded-full bg-white shadow-sm border-2"
                     style="width:14px;height:14px;border-color:{{ $k['line'] }};transition:left .4s"
                     :style="`left:calc(${Math.min({{ $k['pct'] }},95)}% - 7px)`"></div>
            </div>
            {{-- Row 3: number --}}
            <p class="kpi-value text-xl sm:text-2xl font-bold text-[#1E1B4B] tabular-nums mb-1 sm:mb-1.5" x-text="{{ $k['val'] }}"></p>
            {{-- Row 4: trend + helper --}}
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-xs font-semibold"
                      :class="{{ $k['up'] }} ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-500'">
                    <svg class="w-3 h-3" viewBox="0 0 12 12" fill="none">
                        <path :d="{{ $k['up'] }} ? 'M2 9L6 4l4 5' : 'M2 3L6 8l4-5'" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="text-xs text-gray-400" x-text="{{ $k['sub'] }}"></span>
            </div>
        </div>
        @endforeach
    </div>

    {{-- ── CRITICAL ACTIONS + QUICK COUNTS ───────────────── --}}
    @php
        $severityConfig = [
            'urgent' => ['bg' => '#FEF2F2', 'border' => '#FECACA', 'dot' => '#EF4444', 'text' => '#DC2626', 'label' => 'Urgent'],
            'high'   => ['bg' => '#FFF7ED', 'border' => '#FED7AA', 'dot' => '#F97316', 'text' => '#EA580C', 'label' => 'High'],
            'medium' => ['bg' => '#FFFBEB', 'border' => '#FDE68A', 'dot' => '#F59E0B', 'text' => '#D97706', 'label' => 'Medium'],
            'low'    => ['bg' => '#EFF6FF', 'border' => '#BFDBFE', 'dot' => '#3B82F6', 'text' => '#2563EB', 'label' => 'Low'],
            'info'   => ['bg' => '#F9FAFB', 'border' => '#E5E7EB', 'dot' => '#9CA3AF', 'text' => '#6B7280', 'label' => 'Info'],
        ];
    @endphp
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Critical Actions widget — spans 2 of 3 columns --}}
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-50">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-[#EDE9FE] flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5 text-[#7B61FF]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3 class="text-sm font-semibold text-[#1E1B4B]">Recent Critical Actions</h3>
                    <span title="Important activity and warnings from Admins, Managers, Referrers, and system events in this workspace."
                          class="w-4 h-4 rounded-full bg-gray-100 text-gray-400 text-[9px] font-bold flex items-center justify-center cursor-help hover:bg-gray-200 transition-colors shrink-0"
                          aria-label="About critical actions">i</span>
                </div>
                <a href="{{ route('tenant.critical-actions', $tenant->id) }}"
                   class="text-xs font-semibold text-[#7B61FF] hover:text-purple-800 transition-colors">
                    View All →
                </a>
            </div>

            @if(count($criticalActions) === 0)
                <div class="flex flex-col items-center justify-center py-10 text-center px-4">
                    <img src="/images/mascots/r-bunny-sleeping.webp" alt="" class="w-10 h-10 object-contain mb-2 opacity-50">
                    <p class="text-xs font-medium text-gray-500">Nothing critical right now</p>
                    <p class="text-[10px] text-gray-400 mt-0.5">R Bunny says your workspace is calm.</p>
                </div>
            @else
                <div class="divide-y divide-gray-50">
                    @foreach($criticalActions as $action)
                        @php $sev = $severityConfig[$action['severity']] ?? $severityConfig['info']; @endphp
                        <div class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50/60 transition-colors">
                            {{-- Severity dot --}}
                            <div class="flex items-center mt-1.5 shrink-0">
                                <span class="w-2 h-2 rounded-full" style="background:{{ $sev['dot'] }}"
                                      title="{{ $sev['label'] }}" aria-label="Severity: {{ $sev['label'] }}"></span>
                            </div>
                            {{-- Content --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-xs font-semibold text-[#1E1B4B] leading-snug">{{ $action['summary'] }}</p>
                                    <span class="text-[9px] text-gray-400 shrink-0 mt-0.5 whitespace-nowrap">{{ $action['occurred_ago'] }}</span>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 mt-0.5">
                                    <span class="text-[10px] text-gray-500">{{ $action['actor_name'] }}</span>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-medium"
                                          style="background:{{ $sev['bg'] }};color:{{ $sev['text'] }};border:1px solid {{ $sev['border'] }}">
                                        {{ $sev['label'] }}
                                    </span>
                                    @if($action['action_needed'])
                                        <span class="text-[9px] font-bold text-amber-600 uppercase tracking-wide">⚠ Action needed</span>
                                    @endif
                                </div>
                            </div>
                            {{-- Open link --}}
                            @if($action['action_url'])
                                <a href="{{ $action['action_url'] }}"
                                   class="text-[10px] font-semibold text-[#7B61FF] hover:text-purple-800 shrink-0 mt-1 transition-colors"
                                   aria-label="Open {{ $action['summary'] }}">
                                    Open
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="px-4 py-2.5 bg-gray-50/50 border-t border-gray-50">
                    <a href="{{ route('tenant.critical-actions', $tenant->id) }}"
                       class="text-[10px] font-semibold text-[#7B61FF] hover:text-purple-800 transition-colors">
                        View all critical actions →
                    </a>
                </div>
            @endif
        </div>

        {{-- Quick counts sidebar --}}
        <div class="space-y-3">
            @php
            $quickCounts = [
                [
                    'label'  => 'Deals Expiring Soon',
                    'value'  => $dashboardCounts['expiring_deals'],
                    'color'  => '#F97316',
                    'bg'     => '#FFF7ED',
                    'url'    => route('tenant.deals', $tenant->id) . '?status=expiring',
                    'tip'    => 'Deals currently in expiring status. Click to review.',
                    'icon'   => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
                ],
                [
                    'label'  => 'Pending Invitations',
                    'value'  => $dashboardCounts['pending_invites'],
                    'color'  => '#7B61FF',
                    'bg'     => '#EDE9FE',
                    'url'    => route('tenant.users', $tenant->id),
                    'tip'    => 'Team invitations waiting to be accepted.',
                    'icon'   => 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z',
                ],
                [
                    'label'  => 'Import Warnings',
                    'value'  => $dashboardCounts['import_warnings'],
                    'color'  => '#EF4444',
                    'bg'     => '#FEF2F2',
                    'url'    => route('tenant.imports', $tenant->id),
                    'tip'    => 'Imports completed with warnings in the last 14 days that may need review.',
                    'icon'   => 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12',
                ],
                [
                    'label'  => 'Missing Referrer',
                    'value'  => $dashboardCounts['missing_referrer'],
                    'color'  => '#9CA3AF',
                    'bg'     => '#F3F4F6',
                    'url'    => route('tenant.deals', $tenant->id),
                    'tip'    => 'Active deals with no Referrer assigned. Commission cannot be finalised until a Referrer is linked.',
                    'icon'   => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
                ],
            ];
            @endphp
            @foreach($quickCounts as $qc)
            <a href="{{ $qc['url'] }}"
               class="flex items-center gap-3 bg-white rounded-xl border border-gray-100 px-4 py-3 shadow-sm hover:shadow-md hover:border-gray-200 transition-all">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                     style="background:{{ $qc['bg'] }};color:{{ $qc['color'] }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $qc['icon'] }}"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[11px] font-medium text-gray-500 truncate">{{ $qc['label'] }}</p>
                    <p class="text-xl font-bold leading-none mt-0.5" style="color:{{ $qc['value'] > 0 ? $qc['color'] : '#1E1B4B' }}">
                        {{ $qc['value'] }}
                    </p>
                </div>
                <span title="{{ $qc['tip'] }}"
                      class="w-4 h-4 rounded-full bg-gray-100 text-gray-400 text-[9px] font-bold flex items-center justify-center cursor-help hover:bg-gray-200 transition-colors shrink-0"
                      aria-label="{{ $qc['tip'] }}">i</span>
            </a>
            @endforeach
        </div>
    </div>

    {{-- ── CHARTS ROW ───────────────────────────────────────── --}}
    <div style="display:grid;grid-template-columns:4fr 3fr 5fr;gap:1rem">

        {{-- Bar Chart: Referral Pipeline --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5" style="display:flex;flex-direction:column">
            <div class="flex items-center justify-between mb-4 shrink-0">
                <h3 class="text-sm font-semibold text-[#1E1B4B]">Referral Pipeline</h3>
                <select class="text-xs text-gray-500 border border-gray-100 rounded-lg px-2.5 py-1.5 bg-gray-50 outline-none">
                    <option>Monthly</option><option>Quarterly</option><option>All Time</option>
                </select>
            </div>

            {{-- Chart body: grows to fill card --}}
            <div style="flex:1;display:flex;flex-direction:column;justify-content:flex-end">
                <div style="display:flex;gap:8px;align-items:flex-end;height:100%">
                    {{-- Y-axis labels --}}
                    <div style="display:flex;flex-direction:column;justify-content:space-between;align-items:flex-end;width:16px;flex-shrink:0;padding-bottom:22px;height:100%">
                        <span class="text-[9px] text-gray-300" x-text="maxCount"></span>
                        <span class="text-[9px] text-gray-300" x-text="Math.ceil(maxCount*.5)"></span>
                        <span class="text-[9px] text-gray-300">0</span>
                    </div>
                    {{-- Bars area --}}
                    <div style="flex:1;position:relative;height:100%">
                        {{-- Dashed grid lines --}}
                        <div style="position:absolute;inset:0;padding-bottom:22px;display:flex;flex-direction:column;justify-content:space-between;pointer-events:none" aria-hidden="true">
                            <div class="border-t border-dashed border-gray-100 w-full"></div>
                            <div class="border-t border-dashed border-gray-100 w-full"></div>
                            <div class="border-t border-dashed border-gray-100 w-full"></div>
                        </div>
                        {{-- Bars row --}}
                        <div style="position:absolute;bottom:0;left:0;right:0;display:flex;align-items:flex-end;justify-content:space-between;gap:6px">
                            <template x-for="stage in funnel" :key="stage.stage">
                                <div x-data="{hov:false}" @mouseenter="hov=true" @mouseleave="hov=false"
                                     style="flex:1;max-width:48px;display:flex;flex-direction:column;align-items:center;gap:4px;cursor:pointer;position:relative">
                                    {{-- Tooltip --}}
                                    <div x-show="hov" x-cloak
                                         class="absolute z-10 bg-[#1E1B4B] text-white rounded-xl px-3 py-2 text-center shadow-lg pointer-events-none"
                                         style="bottom:calc(100% + 6px);white-space:nowrap;left:50%;transform:translateX(-50%);min-width:90px">
                                        <p class="text-[10px] text-white/60" x-text="stage.label"></p>
                                        <p class="text-sm font-bold" x-text="stage.count+' deals'"></p>
                                    </div>
                                    {{-- Bar --}}
                                    <div class="w-full relative overflow-hidden transition-all duration-200"
                                         :style="`height:${Math.max(10,(stage.count/maxCount)*130)}px;border-radius:9999px`">
                                        <div class="absolute inset-0" style="background:rgba(123,97,255,.10);border-radius:9999px"></div>
                                        <div class="absolute inset-0 hatch-bar transition-opacity duration-150" style="border-radius:9999px"
                                             :style="`opacity:${hov?0:1}`"></div>
                                        <div class="absolute inset-0 transition-opacity duration-150"
                                             style="background:#7B61FF;border-radius:9999px"
                                             :style="`opacity:${hov?1:0}`"></div>
                                    </div>
                                    {{-- Label --}}
                                    <span class="text-[9px] text-gray-400 text-center leading-tight w-full"
                                          style="height:22px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical"
                                          x-text="stage.label"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
                <div x-show="funnel.length===0" class="flex items-center justify-center h-24 text-sm text-gray-400">No pipeline data</div>
            </div>
        </div>

        {{-- Area Chart: Referral Trend --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5" style="display:flex;flex-direction:column">
            {{-- Header --}}
            <div class="flex items-center justify-between mb-2 shrink-0">
                <h3 class="text-sm font-semibold text-[#1E1B4B]">Referral Trend</h3>
                <select class="text-xs text-gray-500 border border-gray-100 rounded-lg px-2.5 py-1.5 bg-gray-50 outline-none">
                    <option>Monthly</option><option>Weekly</option>
                </select>
            </div>
            {{-- Headline stat --}}
            <div class="flex items-center gap-2 mb-3 shrink-0">
                <p class="text-2xl font-bold text-[#1E1B4B]" x-text="conversionRate()+'%'"></p>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-600">
                    <svg class="w-3 h-3" viewBox="0 0 12 12" fill="none"><path d="M2 9L6 4l4 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                    conversion
                </span>
                <span class="text-xs text-gray-400">6-month trend</span>
            </div>
            {{-- SVG fills remaining space --}}
            <div style="flex:1;min-height:0;display:flex;flex-direction:column;justify-content:flex-end">
                <svg width="100%" height="100%" viewBox="0 0 300 80" preserveAspectRatio="none" style="display:block;flex:1;min-height:60px">
                    <defs>
                        <linearGradient id="trendGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#7B61FF" stop-opacity="0.22"/>
                            <stop offset="100%" stop-color="#7B61FF" stop-opacity="0"/>
                        </linearGradient>
                    </defs>
                    <template x-if="areaChartData().length>1 && !areaChartData().every(d=>d.count===0)">
                        <g>
                            <path :d="areaPath(areaChartData(),300,75).area" fill="url(#trendGrad)"/>
                            <path :d="areaPath(areaChartData(),300,75).line" fill="none" stroke="#7B61FF" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <template x-for="(pt,i) in areaPath(areaChartData(),300,75).pts" :key="i">
                                <circle :cx="pt.x" :cy="pt.y" r="3.5" fill="white" stroke="#7B61FF" stroke-width="2"/>
                            </template>
                        </g>
                    </template>
                    <template x-if="areaChartData().every(d=>d.count===0)">
                        <line x1="0" y1="65" x2="300" y2="65" stroke="#e5e7eb" stroke-width="1.5" stroke-dasharray="6,4"/>
                    </template>
                </svg>
                {{-- X-axis labels pinned to bottom --}}
                <div class="flex justify-between mt-2 shrink-0">
                    <template x-for="m in areaChartData()" :key="m.label">
                        <span class="text-[9px] text-gray-300" x-text="m.label"></span>
                    </template>
                </div>
            </div>
        </div>

        {{-- Top Resellers panel --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-[#1E1B4B]">Top Resellers</h3>
                <select class="text-xs text-gray-500 border border-gray-100 rounded-lg px-2.5 py-1.5 bg-gray-50 outline-none focus:ring-1 focus:ring-violet-200">
                    <option>Today</option><option>This Week</option><option>This Month</option>
                </select>
            </div>

            <div class="space-y-1 overflow-y-auto" style="max-height:220px" x-show="resellers.length>0">
                <template x-for="(r,i) in resellers.slice(0,8)" :key="r.id">
                    <div class="flex items-center gap-3 px-2 py-2.5 rounded-xl hover:bg-gray-50 transition-colors cursor-pointer">
                        {{-- Avatar --}}
                        <div class="relative shrink-0">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-xs font-bold"
                                 :style="`background:${r.is_anonymous ? '#9CA3AF' : ['#7B61FF','#FF6CAB','#3B82F6','#10B981','#F59E0B','#8B5CF6','#06B6D4','#EF4444'][i%8]}`"
                                 x-text="r.is_anonymous ? '🔒' : r.name.slice(0,2).toUpperCase()"></div>
                            <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-emerald-400 border-2 border-white rounded-full"></span>
                        </div>
                        {{-- Name + sub --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5">
                                <p class="text-sm font-semibold text-[#1E1B4B] truncate" x-text="r.name"></p>
                                {{-- Tenant admin can see the anonymous badge as a reminder --}}
                                <span x-show="r.is_anonymous"
                                      class="text-[9px] px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-400 shrink-0">
                                    anonymous
                                </span>
                            </div>
                            <p class="text-xs text-gray-400 truncate mt-0.5"
                               x-text="(r.assigned_leads||0)+' deals · '+(r.performance_score||0)+'% rate'"></p>
                        </div>
                        {{-- Value + time --}}
                        <div class="text-right shrink-0">
                            <p class="text-xs font-bold text-[#1E1B4B]"
                               x-text="r.closed_value?'₱'+(Number(r.closed_value)/1000).toFixed(0)+'K':'₱0'"></p>
                            <p class="text-[10px] text-gray-400 mt-0.5">active</p>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Empty --}}
            <div x-show="resellers.length===0" class="flex flex-col items-center justify-center py-10 text-center">
                <img src="/images/mascots/r-bunny-sleeping.webp" alt="" aria-hidden="true"
                     class="w-12 h-12 object-contain mb-2 opacity-50">
                <p class="text-xs text-gray-400">No resellers yet</p>
            </div>

            {{-- Scrollbar indicator --}}
            <div x-show="resellers.length>5" class="flex justify-center mt-2 gap-1">
                <div class="w-1 h-4 bg-gray-200 rounded-full"></div>
            </div>
        </div>
    </div>

    {{-- ── BOTTOM ROW: DEALS + ACTIVITY ────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">

        {{-- Recent Deals (2 cols) --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 lg:col-span-2">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-[#1E1B4B]">Recent Referrals</h3>
                <div class="flex items-center gap-2">
                    <div class="relative">
                        <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" x-model="dealSearch" placeholder="Search…"
                               class="pl-8 pr-3 py-1.5 text-xs border border-gray-100 rounded-lg bg-gray-50 outline-none focus:ring-1 focus:ring-violet-200 w-28">
                    </div>
                    <a href="{{ route('tenant.deals', $tenant->id) }}"
                       class="text-xs text-[#7B61FF] hover:text-violet-700 font-medium border border-violet-100 rounded-lg px-3 py-1.5 hover:bg-violet-50 transition-colors">
                        View all
                    </a>
                </div>
            </div>

            {{-- Column headers --}}
            <div x-show="filteredDeals().length>0"
                 class="grid gap-3 px-3 mb-1"
                 style="grid-template-columns:2fr 1fr 1fr 80px">
                <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Referral</span>
                <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Stage</span>
                <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Value</span>
                <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide text-right">Status</span>
            </div>

            {{-- Deal list --}}
            <div class="space-y-1" x-show="filteredDeals().length>0">
                <template x-for="deal in filteredDeals().slice(0,6)" :key="deal.id">
                    <a :href="`/tenant/{{ $tenant->id }}/deals/${deal.id}`"
                       class="grid gap-3 px-3 py-3 rounded-xl hover:bg-gray-50 transition-colors items-center"
                       style="grid-template-columns:2fr 1fr 1fr 80px">
                        {{-- Name + referrer --}}
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-xs font-bold shrink-0"
                                 :style="`background:${stageColor(deal.stage)}`"
                                 x-text="deal.name?.slice(0,2).toUpperCase()"></div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-[#1E1B4B] truncate" x-text="deal.name"></p>
                                <p class="text-[11px] text-gray-400 truncate mt-0.5 flex items-center gap-1">
                                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    <span x-text="deal.reseller_name || '—'"></span>
                                </p>
                            </div>
                        </div>
                        {{-- Stage --}}
                        <span class="text-xs text-gray-500 capitalize" x-text="(deal.stage||'').replace('_',' ')"></span>
                        {{-- Value --}}
                        <span class="text-sm font-bold text-[#1E1B4B] tabular-nums"
                              x-text="deal.deal_value ? '₱'+Number(deal.deal_value).toLocaleString() : '₱0'"></span>
                        {{-- Status badge --}}
                        <div class="flex justify-end">
                            <span class="text-[10px] px-2.5 py-1 rounded-full font-medium"
                                  :class="{'bg-emerald-100 text-emerald-700':deal.status==='active','bg-orange-100 text-orange-700':deal.status==='expiring','bg-red-100 text-red-600':deal.status==='expired','bg-gray-100 text-gray-500':!['active','expiring','expired'].includes(deal.status||'')}"
                                  x-text="deal.status"></span>
                        </div>
                    </a>
                </template>
            </div>

            {{-- Empty state --}}
            <div x-show="filteredDeals().length===0" class="flex flex-col items-center justify-center py-10 text-center">
                <img src="/images/mascots/r-bunny-sleeping.webp" alt="" aria-hidden="true"
                     class="w-16 h-16 object-contain mb-3 opacity-60">
                <p class="text-sm font-medium text-gray-500">No referrals yet</p>
                <p class="text-xs text-gray-400 mt-1">Add your first deal to start tracking referrals.</p>
            </div>
        </div>

        {{-- Activity / Notifications Panel (1 col) --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-[#1E1B4B]">Activity</h3>
                <select class="text-xs text-gray-500 border border-gray-100 rounded-lg px-2 py-1.5 bg-gray-50 outline-none">
                    <option>Today</option><option>This Week</option>
                </select>
            </div>
            <div class="space-y-1" x-show="recentActivity().length>0">
                <template x-for="(item,i) in recentActivity()" :key="i">
                    <div class="flex items-start gap-3 p-2.5 rounded-xl hover:bg-gray-50 transition-colors">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0 mt-0.5"
                             :style="`background:${['#7B61FF','#FF6CAB','#3B82F6','#10B981','#F59E0B'][i%5]}`"
                             x-text="item.initials"></div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-[#1E1B4B] truncate" x-text="item.name"></p>
                            <p class="text-[10px] text-gray-400 mt-0.5 capitalize" x-text="item.action"></p>
                        </div>
                        <span class="text-[9px] text-gray-300 shrink-0 mt-1" x-text="item.time"></span>
                    </div>
                </template>
            </div>
            <div x-show="recentActivity().length===0" class="flex flex-col items-center justify-center py-8 text-center">
                <img src="/images/mascots/r-bunny-sleeping.webp" alt="" aria-hidden="true"
                     class="w-12 h-12 object-contain mb-2 opacity-50">
                <p class="text-xs text-gray-400">No activity yet</p>
            </div>

            {{-- Commission quick stats --}}
            <div class="mt-4 pt-4 border-t border-gray-50 space-y-2.5">
                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Commission</p>
                <template x-for="cs in [{label:'Pending',status:'pending',color:'#7B61FF'},{label:'Locked',status:'locked',color:'#F59E0B'},{label:'Paid',status:'paid',color:'#10B981'}]" :key="cs.label">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full" :style="`background:${cs.color}`"></span>
                            <span class="text-xs text-gray-500" x-text="cs.label"></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold text-[#1E1B4B]" x-text="commissionStat(cs.status).value"></span>
                            <span class="text-[10px] text-gray-400" x-text="'('+commissionStat(cs.status).count+')'"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- ── FULL VIEW (expanded) ─────────────────────────────── --}}
    <div x-show="$store.dashView.mode==='full'" class="space-y-4" x-cloak>

        {{-- Critical Metrics --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-sm font-semibold text-[#1E1B4B]">Critical Metrics</h3>
                <span class="inline-flex items-center gap-1.5 text-[10px] font-semibold text-emerald-600 uppercase tracking-wide">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>Live
                </span>
            </div>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

                {{-- Avg. Deal Size --}}
                <div class="p-4 rounded-2xl bg-violet-50 border border-violet-100/60">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-7 h-7 rounded-lg bg-violet-100 flex items-center justify-center shrink-0">
                            <svg class="w-3.5 h-3.5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        </div>
                        <span class="text-xs text-violet-600 font-semibold">Avg. Deal Size</span>
                    </div>
                    <p class="text-2xl font-bold text-[#1E1B4B] tabular-nums"
                       x-text="leads.length ? '₱'+(leads.reduce((s,l)=>s+(+l.deal_value||0),0)/leads.length/1000000).toFixed(1)+'M' : '—'"></p>
                    <p class="text-[10px] text-gray-400 mt-1.5" x-text="'Across '+leads.length+' referral'+(leads.length!==1?'s':'')"></p>
                </div>

                {{-- Revenue at Risk --}}
                <div class="p-4 rounded-2xl bg-orange-50 border border-orange-100/60">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-7 h-7 rounded-lg bg-orange-100 flex items-center justify-center shrink-0">
                            <svg class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <span class="text-xs text-orange-500 font-semibold">Revenue at Risk</span>
                    </div>
                    <p class="text-2xl font-bold text-[#1E1B4B] tabular-nums" x-text="(()=>{
                        const v = leads.filter(l=>(l.days_left??21)<=7||l.status==='expired').reduce((s,l)=>s+(+l.deal_value||0),0);
                        return v>=1000000 ? '₱'+(v/1000000).toFixed(1)+'M' : v>=1000 ? '₱'+Math.round(v/1000)+'K' : '₱0';
                    })()"></p>
                    <p class="text-[10px] text-gray-400 mt-1.5"
                       x-text="leads.filter(l=>(l.days_left??21)<=7||l.status==='expired').length+' deals expiring or expired'"></p>
                </div>

                {{-- Conversion Rate --}}
                <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-100/60">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-7 h-7 rounded-lg bg-emerald-100 flex items-center justify-center shrink-0">
                            <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <span class="text-xs text-emerald-600 font-semibold">Conversion Rate</span>
                    </div>
                    <p class="text-2xl font-bold text-[#1E1B4B] tabular-nums" x-text="conversionRate()+'%'"></p>
                    <p class="text-[10px] text-gray-400 mt-1.5"
                       x-text="leads.filter(l=>l.stage==='paid').length+' paid of '+leads.length+' total'"></p>
                </div>

                {{-- Referrer Activation --}}
                <div class="p-4 rounded-2xl bg-blue-50 border border-blue-100/60">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center shrink-0">
                            <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <span class="text-xs text-blue-600 font-semibold">Referrer Activation</span>
                    </div>
                    <p class="text-2xl font-bold text-[#1E1B4B] tabular-nums"
                       x-text="resellers.length > 0 ? Math.round((resellers.filter(r=>(r.assigned_leads||0)>0).length/resellers.length)*100)+'%' : '0%'"></p>
                    <p class="text-[10px] text-gray-400 mt-1.5"
                       x-text="resellers.filter(r=>(r.assigned_leads||0)>0).length+' of '+resellers.length+' referrers have deals'"></p>
                </div>

            </div>
        </div>

        {{-- Stage Breakdown + Data Quality --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h3 class="text-sm font-semibold text-[#1E1B4B] mb-4">Stage Breakdown</h3>
                <div class="space-y-2.5">
                    <template x-for="stage in stageSummary()" :key="stage.key">
                        <div class="flex items-center gap-3">
                            <div class="w-2 h-2 rounded-full shrink-0" :style="`background:${stage.color}`"></div>
                            <span class="text-xs text-gray-500 w-28 shrink-0" x-text="stage.label"></span>
                            <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all"
                                     :style="`width:${maxLeadCount>0?(stage.count/maxLeadCount)*100:0}%;background:${stage.color}`"></div>
                            </div>
                            <span class="text-xs font-semibold text-[#1E1B4B] w-6 text-right tabular-nums" x-text="stage.count"></span>
                        </div>
                    </template>
                </div>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h3 class="text-sm font-semibold text-[#1E1B4B] mb-4">Deal Health</h3>
                <div class="space-y-3">
                    @foreach([['Active', "leads.filter(l=>l.status==='active').length", '#10B981'],['Expiring (≤7d)', "leads.filter(l=>(l.days_left??21)<=7&&l.status==='active').length", '#F59E0B'],['Expired', "leads.filter(l=>l.status==='expired').length", '#EF4444'],['No Referrer', "leads.filter(l=>!l.reseller_name).length", '#9CA3AF']] as [$label, $expr, $color])
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-500 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full shrink-0" style="background:{{ $color }}"></span>{{ $label }}
                        </span>
                        <span class="text-xs font-semibold text-[#1E1B4B] tabular-nums" x-text="{{ $expr }}"></span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Setup Checklist (shown only if setup incomplete) --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5"
             x-show="resellers.length===0||leads.length===0">
            <div class="flex items-start gap-4">
                <img src="/images/mascots/r-bunny-helper-question.webp" alt="R Bunny helper"
                     class="w-14 h-14 object-contain shrink-0">
                <div class="flex-1">
                    <h3 class="text-sm font-semibold text-[#1E1B4B] mb-3">Complete your setup</h3>
                    <div class="space-y-2.5">
                        @foreach([['Add your first referral', 'leads.length>0'],['Invite referrers', 'resellers.length>0'],['Configure commission', 'true'],['Complete business profile', 'true']] as [$item, $done])
                        <div class="flex items-center gap-3">
                            <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-colors shrink-0"
                                 :class="{{ $done }} ? 'bg-emerald-500 border-emerald-500' : 'border-gray-300'">
                                <svg x-show="{{ $done }}" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <span class="text-xs" :class="{{ $done }} ? 'text-gray-400 line-through' : 'text-gray-600'">{{ $item }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── DAILY BRIEFING (once per day, server-gated) ────────── --}}
    @if(isset($dailyBriefing))
    @php
        $expiringDeals = $dailyBriefing['expiringDeals'];
        $newDeals      = $dailyBriefing['newDeals'];
        $newInvited    = $dailyBriefing['newInvited'];
        $newActive     = $dailyBriefing['newActive'];
        $totalItems    = $expiringDeals->count() + $newDeals->count() + $newInvited->count() + $newActive->count();
    @endphp
    <div x-data="{ open: true }"
         x-show="open"
         x-transition:enter="transition ease-out duration-250"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background:rgba(15,10,40,0.6);backdrop-filter:blur(4px)"
         @keydown.escape.window="open = false">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md max-h-[88vh] flex flex-col overflow-hidden" @click.stop>

            {{-- Gradient header --}}
            <div class="shrink-0 px-6 pt-6 pb-5" style="background:linear-gradient(135deg,#1E1B4B 0%,#4C3FA0 100%)">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-white/50 text-xs font-semibold uppercase tracking-widest mb-1">{{ now()->format('l, F j') }}</p>
                        <h3 class="text-white text-xl font-bold leading-tight">Good morning 👋</h3>
                        <p class="text-white/60 text-sm mt-1">{{ $totalItems }} item{{ $totalItems > 1 ? 's' : '' }} need your attention today</p>
                    </div>
                    <button @click="open = false"
                            class="w-8 h-8 rounded-full flex items-center justify-center transition-colors shrink-0"
                            style="background:rgba(255,255,255,0.15)" onmouseover="this.style.background='rgba(255,255,255,0.25)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Summary pills --}}
                <div class="flex flex-wrap gap-2 mt-4">
                    @if($expiringDeals->isNotEmpty())
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold" style="background:rgba(245,158,11,0.25);color:#FCD34D">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-300"></span>{{ $expiringDeals->count() }} Expiring
                    </span>
                    @endif
                    @if($newDeals->isNotEmpty())
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold" style="background:rgba(123,97,255,0.25);color:#C4B5FD">
                        <span class="w-1.5 h-1.5 rounded-full bg-violet-300"></span>{{ $newDeals->count() }} New Deals
                    </span>
                    @endif
                    @if($newInvited->isNotEmpty())
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold" style="background:rgba(59,130,246,0.25);color:#93C5FD">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-300"></span>{{ $newInvited->count() }} Invited
                    </span>
                    @endif
                    @if($newActive->isNotEmpty())
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold" style="background:rgba(16,185,129,0.25);color:#6EE7B7">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-300"></span>{{ $newActive->count() }} Joined
                    </span>
                    @endif
                </div>
            </div>

            {{-- Scrollable sections --}}
            <div class="overflow-y-auto flex-1 bg-gray-50/50">

                {{-- Section 1: Expiring Deals --}}
                @if($expiringDeals->isNotEmpty())
                <div class="px-5 pt-4 pb-3">
                    <div class="flex items-center justify-between mb-2.5">
                        <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest">Expiring Deals</p>
                        <a href="{{ route('tenant.deals', $tenant->id) }}?status=expiring"
                           class="text-[11px] font-semibold" style="color:#7B61FF">View all →</a>
                    </div>
                    <div class="space-y-1.5">
                        @foreach($expiringDeals->take(4) as $deal)
                        @php $urgency = $deal->days_left <= 1 ? ['#FEE2E2','#DC2626'] : ($deal->days_left <= 3 ? ['#FEF3C7','#D97706'] : ['#FEF9C3','#CA8A04']); @endphp
                        <a href="{{ route('tenant.deals', $tenant->id) }}?status=expiring"
                           class="flex items-center gap-3 p-3 bg-white rounded-2xl border border-gray-100 hover:border-orange-200 hover:shadow-sm transition-all">
                            <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white text-xs font-bold shrink-0"
                                 style="background:{{ $urgency[1] }}">
                                {{ strtoupper(substr($deal->name, 0, 2)) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-[#1E1B4B] truncate">{{ $deal->name }}</p>
                                <p class="text-xs text-gray-400 truncate mt-0.5">{{ $deal->reseller_name ?? 'No referrer' }}</p>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="inline-block text-xs font-bold px-2 py-0.5 rounded-full"
                                      style="background:{{ $urgency[0] }};color:{{ $urgency[1] }}">{{ $deal->days_left }}d left</span>
                                @if($deal->deal_value)<p class="text-[10px] text-gray-400 mt-0.5">₱{{ number_format($deal->deal_value) }}</p>@endif
                            </div>
                        </a>
                        @endforeach
                        @if($expiringDeals->count() > 4)
                        <p class="text-xs text-gray-400 text-center py-1">+{{ $expiringDeals->count() - 4 }} more expiring deals</p>
                        @endif
                    </div>
                </div>
                @endif

                {{-- Section 2: New Deals --}}
                @if($newDeals->isNotEmpty())
                <div class="px-5 pt-3 pb-3">
                    <div class="flex items-center justify-between mb-2.5">
                        <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest">New Deals</p>
                        <a href="{{ route('tenant.deals', $tenant->id) }}"
                           class="text-[11px] font-semibold" style="color:#7B61FF">View all →</a>
                    </div>
                    <div class="space-y-1.5">
                        @foreach($newDeals->take(4) as $deal)
                        <a href="{{ route('tenant.deals', $tenant->id) }}"
                           class="flex items-center gap-3 p-3 bg-white rounded-2xl border border-gray-100 hover:border-violet-200 hover:shadow-sm transition-all">
                            <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white text-xs font-bold shrink-0"
                                 style="background:#7B61FF">
                                {{ strtoupper(substr($deal->name, 0, 2)) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-[#1E1B4B] truncate">{{ $deal->name }}</p>
                                <p class="text-xs text-gray-400 truncate mt-0.5">{{ $deal->reseller_name ?? 'No referrer' }} · {{ ucfirst(str_replace('_', ' ', $deal->stage)) }}</p>
                            </div>
                            @if($deal->deal_value)
                            <p class="text-sm font-bold text-[#1E1B4B] shrink-0">₱{{ number_format($deal->deal_value) }}</p>
                            @endif
                        </a>
                        @endforeach
                        @if($newDeals->count() > 4)
                        <p class="text-xs text-gray-400 text-center py-1">+{{ $newDeals->count() - 4 }} more new deals</p>
                        @endif
                    </div>
                </div>
                @endif

                {{-- Section 3: Pending Invites --}}
                @if($newInvited->isNotEmpty())
                <div class="px-5 pt-3 pb-3">
                    <div class="flex items-center justify-between mb-2.5">
                        <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest">Pending Referrer Invites</p>
                        <a href="{{ route('tenant.referrers', $tenant->id) }}"
                           class="text-[11px] font-semibold" style="color:#7B61FF">View all →</a>
                    </div>
                    <div class="space-y-1.5">
                        @foreach($newInvited->take(4) as $r)
                        <div class="flex items-center gap-3 p-3 bg-white rounded-2xl border border-gray-100">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0"
                                 style="background:#3B82F6">
                                {{ strtoupper(substr($r->name, 0, 2)) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-[#1E1B4B] truncate">{{ $r->name }}</p>
                                <p class="text-xs text-gray-400 truncate mt-0.5">{{ $r->email }}</p>
                            </div>
                            <span class="text-[10px] font-semibold px-2.5 py-1 rounded-full shrink-0"
                                  style="background:#DBEAFE;color:#1D4ED8">Invited</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Section 4: New Active Resellers --}}
                @if($newActive->isNotEmpty())
                <div class="px-5 pt-3 pb-4">
                    <div class="flex items-center justify-between mb-2.5">
                        <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest">New Referrers Joined</p>
                        <a href="{{ route('tenant.referrers', $tenant->id) }}"
                           class="text-[11px] font-semibold" style="color:#7B61FF">View all →</a>
                    </div>
                    <div class="space-y-1.5">
                        @foreach($newActive->take(4) as $r)
                        <div class="flex items-center gap-3 p-3 bg-white rounded-2xl border border-gray-100">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0"
                                 style="background:#10B981">
                                {{ strtoupper(substr($r->name, 0, 2)) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-[#1E1B4B] truncate">{{ $r->name }}</p>
                                <p class="text-xs text-gray-400 truncate mt-0.5">{{ $r->email }}</p>
                            </div>
                            <span class="text-[10px] font-semibold px-2.5 py-1 rounded-full shrink-0"
                                  style="background:#D1FAE5;color:#065F46">Active</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

            </div>

            {{-- Footer --}}
            <div class="px-5 py-4 border-t border-gray-100 bg-white shrink-0 flex gap-3">
                <button @click="open = false"
                        class="flex-1 py-2.5 rounded-2xl text-sm font-semibold text-gray-500 hover:text-gray-700 hover:bg-gray-50 border border-gray-200 transition-colors">
                    Skip
                </button>
                <a href="{{ route('tenant.deals', $tenant->id) }}?status=expiring"
                   class="flex-1 py-2.5 rounded-2xl text-sm font-semibold text-white text-center transition-colors"
                   style="background:#7B61FF" onmouseover="this.style.background='#6D4FE8'" onmouseout="this.style.background='#7B61FF'">
                    View
                </a>
            </div>
        </div>
    </div>
    @endif

    {{-- ── ADD DEAL MODAL ───────────────────────────────────── --}}
    <div x-show="showAdd" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-[#1E1B4B]">Add Referral</h3>
                <button @click="showAdd=false; clearLgu()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6 space-y-4">

                {{-- Step 1: LGU search --}}
                <div>
                    <label class="form-label">City / Municipality *</label>
                    <div class="relative">
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input type="text"
                                   x-model="lguQuery"
                                   @input.debounce.300ms="searchLgu()"
                                   @keydown.escape="lguDropdown = false"
                                   @keydown.arrow-down.prevent="lguFocus = Math.min(lguFocus+1, lguResults.length-1)"
                                   @keydown.arrow-up.prevent="lguFocus = Math.max(lguFocus-1, 0)"
                                   @keydown.enter.prevent="lguResults[lguFocus] && selectLgu(lguResults[lguFocus])"
                                   class="form-input pl-9"
                                   :class="lguSelected ? 'border-violet-300 bg-violet-50/30' : ''"
                                   placeholder="Type city or municipality name…"
                                   autocomplete="off">
                            <button x-show="lguSelected" @click="clearLgu()"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        {{-- Dropdown --}}
                        <div x-show="lguDropdown && lguResults.length > 0" x-cloak
                             @click.outside="lguDropdown = false"
                             class="absolute z-30 w-full mt-1 bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden max-h-52 overflow-y-auto">
                            <template x-for="(org, idx) in lguResults" :key="org.id">
                                <button type="button"
                                        @click="selectLgu(org)"
                                        :class="idx === lguFocus ? 'bg-violet-50' : 'hover:bg-gray-50'"
                                        class="w-full flex items-center gap-3 px-4 py-2.5 text-left transition-colors">
                                    <div class="w-7 h-7 rounded-lg flex items-center justify-center text-white text-[10px] font-bold shrink-0"
                                         :style="`background:${org._d && org._d.lgu_type==='City' ? '#7B61FF' : '#3B82F6'}`"
                                         x-text="(org.name||'').slice(0,2).toUpperCase()"></div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-[#1E1B4B] truncate" x-text="org.name"></p>
                                        <p class="text-xs text-gray-400 truncate" x-text="org.address"></p>
                                    </div>
                                </button>
                            </template>
                            <div x-show="lguSearching" class="px-4 py-3 text-xs text-gray-400 text-center">Searching…</div>
                        </div>
                    </div>

                    {{-- Selected: standardized name preview --}}
                    <div x-show="lguSelected" x-cloak class="mt-2 px-4 py-3 rounded-xl border border-violet-100" style="background:#F5F3FF">
                        <p class="text-[10px] font-bold text-violet-400 uppercase tracking-widest mb-1">Standardized Name</p>
                        <p class="text-sm font-semibold text-[#1E1B4B]" x-text="form.name"></p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Province: <span class="font-medium text-gray-700" x-text="form.data.province || '—'"></span>
                        </p>
                    </div>
                </div>

                {{-- Stage + Deal Value --}}
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
                    <div>
                        <label class="form-label">Deal Value (₱)</label>
                        <input type="number" x-model="form.deal_value" class="form-input" placeholder="0">
                    </div>
                </div>

                {{-- Referrer Name --}}
                <div>
                    <label class="form-label">
                        Referrer Name *
                        <span x-show="form.reseller_name && autoFilledReferrer"
                              class="ml-1.5 text-[10px] font-semibold px-1.5 py-0.5 rounded-full"
                              style="background:#D1FAE5;color:#065F46">auto-filled</span>
                        <span x-show="resellerIsNew"
                              class="ml-1.5 text-[10px] font-semibold px-1.5 py-0.5 rounded-full"
                              style="background:#FEF3C7;color:#D97706">new referrer</span>
                    </label>
                    <input type="text" x-model="form.reseller_name" class="form-input"
                           @input="form.new_reseller_email = ''"
                           placeholder="Assigned referrer"
                           :class="autoFilledReferrer ? 'border-emerald-300 bg-emerald-50/30' : (resellerIsNew ? 'border-amber-300' : '')">
                </div>

                {{-- New referrer email (shown when name doesn't match any existing reseller) --}}
                <div x-show="resellerIsNew" x-cloak>
                    <div class="flex items-start gap-2.5 px-4 py-3 rounded-xl mb-3" style="background:#FFFBEB;border:1px solid #FDE68A">
                        <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-xs text-amber-700 leading-relaxed">
                            <strong x-text="form.reseller_name"></strong> is not an existing referrer.
                            Enter their email below — a referrer account will be created and an invitation sent automatically.
                        </p>
                    </div>
                    <label class="form-label">Referrer Email *</label>
                    <input type="email" x-model="form.new_reseller_email" class="form-input"
                           placeholder="referrer@email.com"
                           :class="resellerIsNew && !form.new_reseller_email ? 'border-amber-300' : ''">
                </div>

                <p x-show="addError" class="text-xs text-red-600 font-medium" x-text="addError"></p>
                <div class="flex justify-end gap-3">
                    <button @click="showAdd=false; clearLgu()" class="btn-secondary">Cancel</button>
                    <button @click="addLead()" :disabled="saving || !lguSelected" class="btn-primary"
                            x-text="saving ? 'Saving…' : 'Add Referral'"></button>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function tenantDashboard(tenantId, currentResellerName) {
    return {
        leads: [], resellers: [], funnel: [], stats: {}, metric: {},
        maxCount: 1, maxLeadCount: 1, subscription: null,
        showAdd: false, saving: false, dealSearch: '', addError: '',

        // LGU search
        lguQuery: '', lguResults: [], lguDropdown: false, lguSearching: false,
        lguSelected: null, lguFocus: 0,

        // Referrer auto-fill
        autoFilledReferrer: !!currentResellerName,

        get resellerIsNew() {
            if (!this.form.reseller_name || this.form.reseller_name.length < 2) return false;
            const q = this.form.reseller_name.toLowerCase();
            return !this.resellers.some(r => (r.name || '').toLowerCase() === q);
        },

        form: {
            name: '',
            stage: 'introduction',
            deal_value: '',
            reseller_name: currentResellerName || '',
            new_reseller_email: '',
            data: { province: '', municipality: '' },
        },

        async init() {
            const [lRes, rRes, fRes, mRes, sRes] = await Promise.all([
                fetch(`/api/leads?tenant_id=${tenantId}`),
                fetch(`/api/resellers?tenant_id=${tenantId}`),
                fetch(`/api/analytics/funnel?tenant_id=${tenantId}`),
                fetch(`/api/metrics/${tenantId}`),
                fetch(`/api/billing/tenants/${tenantId}/subscription`),
            ]);
            const lData = await lRes.json();
            this.leads = Array.isArray(lData) ? lData : [];
            const rData = await rRes.json();
            this.resellers = Array.isArray(rData) ? rData : (rData.data || []);
            this.funnel = await fRes.json();
            const mData = await mRes.json();
            this.stats  = mData.detail ?? {};
            this.metric = mData.metric ?? {};
            this.subscription = await sRes.json();
            this.maxCount     = Math.max(...this.funnel.map(f=>f.count), 1);
            this.maxLeadCount = Math.max(...this.stageSummary().map(s=>s.count), 1);
        },

        // ── Computed ──────────────────────────────────────────
        pipelineValue() {
            const t = this.leads.reduce((s,l)=>s+(Number(l.deal_value)||0), 0);
            if (t>=1000000) return '₱'+(t/1000000).toFixed(1)+'M';
            if (t>=1000)    return '₱'+Math.round(t/1000)+'K';
            return t>0 ? '₱'+t.toLocaleString() : '₱0';
        },
        conversionRate() {
            if (!this.leads.length) return 0;
            return Math.round((this.leads.filter(l=>l.stage==='paid').length / this.leads.length)*100);
        },
        newThisWeek() {
            const cutoff = new Date(); cutoff.setDate(cutoff.getDate()-7);
            return this.leads.filter(l=>l.created_at&&new Date(l.created_at)>=cutoff).length;
        },
        fmtVal(v) {
            const n = Math.round(Number(v)||0);
            if (n>=1000000) return (n/1000000).toFixed(1)+'M';
            if (n>=1000)    return Math.round(n/1000)+'K';
            return n.toLocaleString('en');
        },
        commissionStat(status) {
            const f = this.leads.filter(l=>l.commission_status===status);
            return { count: f.length, value: '₱'+this.fmtVal(f.reduce((s,l)=>s+(Number(l.deal_value)||0),0)) };
        },
        stageSummary() {
            return [
                {key:'introduction', label:'Introduction',  color:'#9CA3AF'},
                {key:'presentation', label:'Presentation',  color:'#3B82F6'},
                {key:'contract_sent',label:'Contract Sent', color:'#F59E0B'},
                {key:'signed',       label:'Signed',        color:'#8B5CF6'},
                {key:'paid',         label:'Paid',          color:'#10B981'},
            ].map(s=>({...s, count:this.leads.filter(l=>l.stage===s.key).length}));
        },
        stageColor(stage) {
            return {introduction:'#9CA3AF',presentation:'#3B82F6',contract_sent:'#F59E0B',signed:'#8B5CF6',paid:'#10B981'}[stage]||'#7B61FF';
        },
        filteredDeals() {
            const q = (this.dealSearch||'').toLowerCase();
            if (!q) return this.leads;
            return this.leads.filter(l=>(l.name||'').toLowerCase().includes(q)||(l.reseller_name||'').toLowerCase().includes(q));
        },
        trialDaysLeft() {
            if (!this.subscription?.trial_end_date) return 0;
            return Math.max(0, Math.ceil((new Date(this.subscription.trial_end_date)-new Date())/86400000));
        },

        // ── Area chart ────────────────────────────────────────
        areaChartData() {
            const months = [];
            for (let i=5; i>=0; i--) {
                const d = new Date(); d.setDate(1); d.setMonth(d.getMonth()-i);
                const key = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`;
                months.push({
                    label: d.toLocaleDateString('en',{month:'short'}),
                    count: this.leads.filter(l=>l.created_at&&l.created_at.startsWith(key)).length,
                });
            }
            return months;
        },
        areaPath(data, w, h) {
            if (!data||data.length<2) return {area:'',line:'',pts:[]};
            const max = Math.max(...data.map(d=>d.count), 1);
            const pts = data.map((d,i)=>({
                x: (i/(data.length-1))*w,
                y: h-(d.count/max)*(h-14)-7,
            }));
            let line = `M${pts[0].x},${pts[0].y}`;
            for (let i=1; i<pts.length; i++) {
                const cpx = (pts[i].x-pts[i-1].x)*0.4;
                line += ` C${pts[i-1].x+cpx},${pts[i-1].y} ${pts[i].x-cpx},${pts[i].y} ${pts[i].x},${pts[i].y}`;
            }
            return { line, pts, area:`${line} L${pts[pts.length-1].x},${h} L${pts[0].x},${h} Z` };
        },

        // ── Activity feed ─────────────────────────────────────
        recentActivity() {
            return [...this.leads]
                .sort((a,b)=>new Date(b.created_at||0)-new Date(a.created_at||0))
                .slice(0,6)
                .map(l=>({
                    initials: (l.name||'??').slice(0,2).toUpperCase(),
                    name:     l.name || 'Unknown',
                    action:   (l.stage||'').replace('_',' ') + (l.reseller_name ? ' · '+l.reseller_name : ''),
                    time:     this.timeAgo(l.created_at),
                }));
        },
        timeAgo(date) {
            if (!date) return '';
            const mins = Math.floor((new Date()-new Date(date))/60000);
            if (mins<1)   return 'just now';
            if (mins<60)  return mins+'m ago';
            if (mins<1440) return Math.floor(mins/60)+'h ago';
            return Math.floor(mins/1440)+'d ago';
        },

        // ── LGU search ────────────────────────────────────────
        async searchLgu() {
            if (this.lguQuery.length < 2) { this.lguResults = []; this.lguDropdown = false; return; }
            this.lguSearching = true;
            this.lguDropdown  = true;
            this.lguFocus     = 0;
            try {
                const p   = new URLSearchParams({ tenant_id: tenantId, search: this.lguQuery, per_page: 10 });
                const res = await fetch(`/api/organizations?${p}`);
                const json = await res.json();
                this.lguResults = (json.data || []).map(o => {
                    let _d = {};
                    try { _d = typeof o.data === 'string' ? JSON.parse(o.data || '{}') : (o.data || {}); } catch(e) {}
                    return { ...o, _d };
                });
            } catch(e) { this.lguResults = []; }
            this.lguSearching = false;
        },

        selectLgu(org) {
            this.lguSelected      = org;
            this.lguDropdown      = false;
            this.lguQuery         = org.name;
            // Standardized name comes directly from the org record
            this.form.name        = org.name;
            // Province from address field
            this.form.data.province    = org.address || '';
            // Municipality = city/municipality name without the "Municipality of" / "City of" prefix
            this.form.data.municipality = (org.name || '').replace(/^(?:Municipality|City) of\s+/i, '');
        },

        clearLgu() {
            this.lguSelected           = null;
            this.lguQuery              = '';
            this.lguResults            = [];
            this.lguDropdown           = false;
            this.form.name             = '';
            this.form.data.province    = '';
            this.form.data.municipality = '';
        },

        // ── Add deal ──────────────────────────────────────────
        async addLead() {
            this.addError = '';
            if (!this.lguSelected)        { this.addError = 'Please select a city or municipality first.'; return; }
            if (!this.form.reseller_name) { this.addError = 'Referrer name is required.'; return; }
            if (this.resellerIsNew && !this.form.new_reseller_email) {
                this.addError = 'Please enter the referrer\'s email to create their account.'; return;
            }
            this.saving = true;
            try {
                const payload = {
                    tenant_id:    tenantId,
                    name:         this.form.name,
                    stage:        this.form.stage,
                    deal_value:   this.form.deal_value || 0,
                    reseller_name: this.form.reseller_name,
                    data:         this.form.data,
                };
                const res  = await fetch('/api/leads', {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify(payload),
                });
                const lead = await res.json();

                if (!res.ok) {
                    // Show first validation error or generic message
                    const msg = lead.message || (lead.errors ? Object.values(lead.errors)[0][0] : null) || 'Failed to create referral.';
                    this.addError = msg;
                    return;
                }

                if (lead.id) {
                    this.leads.unshift(lead);
                    this.maxCount     = Math.max(...this.funnel.map(f => f.count), 1);
                    this.maxLeadCount = Math.max(...this.stageSummary().map(s => s.count), 1);
                }

                this.showAdd = false;
                this.clearLgu();
                this.form = {
                    name: '',
                    stage: 'introduction',
                    deal_value: '',
                    reseller_name: currentResellerName || '',
                    new_reseller_email: '',
                    data: { province: '', municipality: '' },
                };
                this.autoFilledReferrer = !!currentResellerName;
                let msg = `Referral "${lead.name}" added successfully.`;
                if (lead.reseller_created && lead.invite_sent)  msg += ` Invitation sent to ${this.form.new_reseller_email}.`;
                if (lead.reseller_created && !lead.invite_sent) msg += ` Referrer account created (invite email could not be sent).`;
                this.$dispatch('show-toast', { type: 'success', message: msg });
            } catch(e) {
                this.addError = 'Network error. Please try again.';
            } finally {
                this.saving = false;
            }
        },
    }
}
</script>

@if($accessExtendedNotif)
<style>
@keyframes rb-pop-in  { 0%{opacity:0;transform:scale(.85) translateY(32px)} 65%{transform:scale(1.02) translateY(-4px)} 100%{opacity:1;transform:scale(1) translateY(0)} }
@keyframes rb-fade-up { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
@keyframes rb-float   { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-8px)} }
@keyframes rb-confetti-fall { 0%{transform:translateY(0) rotateZ(var(--r,0deg));opacity:1} 100%{transform:translateY(800px) rotateZ(calc(var(--r,0deg)+540deg));opacity:0} }
.rb-modal  { animation: rb-pop-in .5s cubic-bezier(.34,1.56,.64,1) forwards; }
.rb-float  { animation: rb-float 3s ease-in-out infinite; }
.rb-fade-1 { animation: rb-fade-up .4s ease-out .15s both; }
.rb-fade-2 { animation: rb-fade-up .4s ease-out .30s both; }
.rb-fade-3 { animation: rb-fade-up .4s ease-out .45s both; }
.rb-fade-4 { animation: rb-fade-up .4s ease-out .60s both; }
</style>
<div x-data="rbAccessExtendedPopup('{{ $accessExtendedNotif->id }}')"
     x-init="$nextTick(()=>{ if(show) launchConfetti(); })"
     x-show="show" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6">
    <div class="absolute inset-0 bg-[#0D0B26]/70 backdrop-blur-md" @click="dismiss()"></div>
    <div id="rb-confetti" class="absolute inset-0 overflow-hidden pointer-events-none z-10"></div>
    <div class="relative z-20 w-full max-w-sm rb-modal" @click.stop>
        <div class="bg-white rounded-[24px] shadow-2xl overflow-hidden">
            <div class="relative text-center px-6 pt-8 pb-7 overflow-hidden"
                 style="background:linear-gradient(145deg,#1E1B4B 0%,#3B0764 40%,#7B61FF 75%,#FF6CAB 100%)">
                <div class="absolute -top-8 -right-8 w-40 h-40 rounded-full opacity-10 bg-white pointer-events-none"></div>
                <div class="rb-float inline-block mb-4">
                    <img src="/images/mascots/r-bunny-celebration.webp" alt="R Bunny celebrating" class="w-28 h-28 object-contain mx-auto drop-shadow-lg">
                </div>
                <div class="rb-fade-1">
                    <h2 class="text-[22px] font-bold text-white leading-tight mb-1">R Bunny extended<br>your access!</h2>
                    <p class="text-white/60 text-sm">A gift from the Referral Bunny team</p>
                </div>
            </div>
            <div class="px-6 pb-6 pt-5 space-y-4">
                <div class="rb-fade-2 flex items-center justify-center">
                    <div class="flex items-center gap-3 px-5 py-3 rounded-2xl" style="background:linear-gradient(135deg,#F0EFFA,#FDF2F8);border:1.5px solid #E9D5FF">
                        <svg class="w-5 h-5 text-purple-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div>
                            <p class="text-xs text-purple-400 font-semibold uppercase tracking-wide leading-none mb-0.5">Access Extended</p>
                            <p class="text-xl font-bold text-[#1E1B4B] leading-none">+{{ $accessExtendedNotif->metadata_json['days'] ?? '?' }} {{ ($accessExtendedNotif->metadata_json['days'] ?? 1) === 1 ? 'Day' : 'Days' }}</p>
                        </div>
                    </div>
                </div>
                <div class="rb-fade-3 text-center">
                    <p class="text-sm text-gray-600 leading-relaxed">{{ $accessExtendedNotif->message }}</p>
                </div>
                <div class="rb-fade-4 flex gap-2.5 pt-1">
                    <button @click="dismiss()" class="flex-1 py-2.5 rounded-xl text-sm text-gray-500 hover:bg-gray-50 border border-gray-200 font-medium">Got it</button>
                    <button @click="dismiss()" class="flex-[2] btn-primary text-sm justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        Continue
                    </button>
                </div>
            </div>
        </div>
        <button @click="dismiss()" class="absolute -top-3 -right-3 w-8 h-8 rounded-full bg-white shadow-md flex items-center justify-center text-gray-400 hover:text-gray-700 border border-gray-100">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
</div>
<script>
function rbAccessExtendedPopup(notifId) {
    return {
        show: true,
        async dismiss() {
            this.show = false;
            try { await fetch(`/api/notifications/${notifId}`,{method:'PATCH',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:JSON.stringify({is_read:true,is_dismissed:true})}); } catch(e) {}
        },
        launchConfetti() {
            const c = document.getElementById('rb-confetti'); if (!c) return;
            const colors = ['#7B61FF','#FF6CAB','#10B981','#F59E0B','#3B82F6'];
            for (let i=0;i<60;i++) {
                const el=document.createElement('div'), color=colors[Math.floor(Math.random()*colors.length)], isCircle=Math.random()>.5, w=Math.random()*9+4, h=isCircle?w:w*.4, dur=(Math.random()*1.8+1.5).toFixed(2), delay=(Math.random()*.6).toFixed(2);
                el.style.cssText=`position:absolute;left:${Math.random()*100}%;top:-${h*2}px;width:${w}px;height:${h}px;background:${color};opacity:.85;border-radius:${isCircle?'50%':'2px'};--r:${Math.random()*360}deg;animation:rb-confetti-fall ${dur}s ease-in ${delay}s forwards;`;
                c.appendChild(el); setTimeout(()=>el.remove(),(+dur+ +delay)*1000+200);
            }
        },
    };
}
</script>
@endif

@endsection
