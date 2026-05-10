@extends('layouts.app')
@section('title', 'Reports')
@section('nav')
    @include('tenant._nav')
@endsection

@section('content')
<div class="space-y-6" x-data="reportsPage('{{ $tenant->id }}')" x-init="init()">

    {{-- Financial Summary --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide flex items-center gap-1">Total Contract Value @if($showLocation ?? false)<x-tax-tip />@endif</span>
                <p class="text-2xl font-bold text-[#1E1B4B] mt-1.5" x-text="fmtM(totalContractValue())"></p>
                <p class="text-xs text-gray-400 mt-0.5">base cost + margin</p>
            </div>
            <div class="kpi-icon bg-purple-100 ml-3 shrink-0"><svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg></div>
        </div>
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Company Revenue</span>
                <p class="text-2xl font-bold text-blue-700 mt-1.5" x-text="fmtM(totalCompanyShare())"></p>
                <p class="text-xs text-gray-400 mt-0.5">30% of all margins</p>
            </div>
            <div class="kpi-icon bg-blue-100 ml-3 shrink-0"><svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/></svg></div>
        </div>
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Commission Pool</span>
                <p class="text-2xl font-bold text-emerald-700 mt-1.5" x-text="fmtM(totalCommPool())"></p>
                <p class="text-xs text-gray-400 mt-0.5">70% for referrers</p>
            </div>
            <div class="kpi-icon bg-emerald-100 ml-3 shrink-0"><svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/></svg></div>
        </div>
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Paid Out</span>
                <p class="text-2xl font-bold text-green-700 mt-1.5" x-text="fmtM(paidCommPool())"></p>
                <p class="text-xs text-gray-400 mt-0.5">commission marked paid</p>
            </div>
            <div class="kpi-icon bg-green-100 ml-3 shrink-0"><svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
        </div>
    </div>

    {{-- Commission status breakdown --}}
    <div class="card">
        <div class="flex items-center gap-1.5 mb-4"><h3 class="font-semibold text-[#1E1B4B]">Commission Status Breakdown</h3>@if($showLocation ?? false)<x-tax-tip />@endif</div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <template x-for="cs in commissionBreakdown()" :key="cs.status">
                <div class="p-4 rounded-2xl border border-gray-100 space-y-2">
                    <div class="flex items-center justify-between">
                        <span :class="{
                            'badge badge-gray':   cs.status === 'pending',
                            'badge badge-orange': cs.status === 'locked',
                            'badge badge-green':  cs.status === 'paid',
                        }" x-text="cs.status.charAt(0).toUpperCase()+cs.status.slice(1)"></span>
                        <span class="text-xs text-gray-400" x-text="cs.count + ' deals'"></span>
                    </div>
                    <p class="text-xl font-bold text-[#1E1B4B] tabular-nums" x-text="fmtM(cs.pool)"></p>
                    <div class="text-xs text-gray-400 space-y-0.5">
                        <div class="flex justify-between"><span>Contract:</span><span class="font-medium" x-text="fmtM(cs.contract)"></span></div>
                        <div class="flex justify-between"><span>Company:</span><span class="font-medium text-blue-600" x-text="fmtM(cs.company)"></span></div>
                        <div class="flex justify-between"><span>Pool:</span><span class="font-medium text-emerald-600" x-text="fmtM(cs.pool)"></span></div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5 mb-1.5">
                    <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Total Deals</span>
                    <x-info-tip text="All deals in this tenant's pipeline across every stage and status." />
                </div>
                <p class="text-2xl font-bold text-[#1E1B4B]" x-text="stats.total_leads ?? '—'"></p>
            </div>
            <div class="kpi-icon bg-purple-100 ml-3 shrink-0"><svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/></svg></div>
        </div>
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5 mb-1.5">
                    <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Pipeline Value</span>
                    <x-info-tip text="Total deal value of all leads currently in the pipeline, in Philippine Peso." />
                </div>
                <p class="text-2xl font-bold text-[#1E1B4B]" x-text="stats.pipeline_value ? '₱' + (stats.pipeline_value/1000000).toFixed(1) + 'M' : '—'"></p>
            </div>
            <div class="kpi-icon bg-emerald-100 ml-3 shrink-0"><svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
        </div>
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5 mb-1.5">
                    <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Active Deals</span>
                    <x-info-tip text="Deals with active status — not expired, declined, or reassigned." />
                </div>
                <p class="text-2xl font-bold text-[#1E1B4B]" x-text="stats.leads_active ?? '—'"></p>
            </div>
            <div class="kpi-icon bg-blue-100 ml-3 shrink-0"><svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg></div>
        </div>
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5 mb-1.5">
                    <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Referrers</span>
                    <x-info-tip text="Referrers registered for this tenant. They introduce leads and earn commission on closed deals." position="left" />
                </div>
                <p class="text-2xl font-bold text-[#1E1B4B]" x-text="resellers.length"></p>
            </div>
            <div class="kpi-icon bg-orange-100 ml-3 shrink-0"><svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg></div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Pipeline Funnel --}}
        <div class="card">
            <div class="flex items-center gap-1 mb-4">
                <h3 class="font-semibold text-[#1E1B4B]">Pipeline Funnel</h3>
                <x-info-tip text="Lead count at each pipeline stage. Bar height is relative to the busiest stage. Taller bars = more leads at that point in the sales process." />
            </div>
            <div class="flex items-end justify-around gap-2 h-40 overflow-x-auto pb-2" x-show="funnel.length > 0">
                <template x-for="stage in funnel" :key="stage.stage">
                    <div class="rb-bar-wrap flex flex-col items-center gap-1.5 cursor-pointer" style="width:48px;flex-shrink:0">
                        <span class="text-xs font-semibold text-[#1E1B4B]" x-text="stage.count"></span>
                        <div class="w-full transition-all duration-200 relative overflow-hidden"
                             :style="`height:${Math.max(12,(stage.count/maxCount)*130)}px; border-radius:9999px;`"
                             x-show="maxCount > 0">
                            <div class="absolute inset-0" style="background:rgba(123,97,255,.13); border-radius:9999px"></div>
                            <div class="absolute inset-0 hatch-bar" style="border-radius:9999px"></div>
                            <div class="absolute inset-0 rb-bar-solid transition-opacity duration-150"
                                 style="background:#7B61FF; border-radius:9999px; opacity:0"></div>
                        </div>
                        <span class="text-xs text-gray-400 text-center capitalize" x-text="stage.label"></span>
                    </div>
                </template>
            </div>
            <p class="text-gray-400 text-sm text-center py-6" x-show="funnel.length === 0">No pipeline data</p>
        </div>

        {{-- Top Referrers --}}
        <div class="card">
            <div class="flex items-center gap-1 mb-4">
                <h3 class="font-semibold text-[#1E1B4B]">Top Referrers</h3>
                <x-info-tip text="Referrers ranked by closed deal value. Shows their assigned lead count and total revenue generated." />
            </div>
            <div class="space-y-3">
                <template x-for="(r, i) in resellers.slice(0,5)" :key="r.id">
                    <div class="flex items-center gap-3">
                        <div class="w-6 h-6 rounded-full bg-purple-100 flex items-center justify-center text-purple-700 text-xs font-bold shrink-0" x-text="i+1"></div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-[#1E1B4B] truncate" x-text="r.name"></p>
                            <p class="text-xs text-gray-400" x-text="(r.assigned_leads || 0) + ' deals'"></p>
                        </div>
                        <span class="text-sm font-semibold text-gray-700" x-text="r.closed_value ? '₱' + Number(r.closed_value).toLocaleString() : '₱0'"></span>
                    </div>
                </template>
                <template x-if="resellers.length === 0">
                    <p class="text-gray-400 text-sm text-center py-4">No resellers yet</p>
                </template>
            </div>
        </div>
    </div>

    {{-- Export --}}
    <div class="card">
        <h3 class="font-semibold text-[#1E1B4B] mb-3">Export Data</h3>
        <div class="flex flex-wrap gap-3">
            <a href="/api/export/leads?tenant_id={{ $tenant->id }}" class="btn-secondary text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Export Deals CSV
            </a>
        </div>
    </div>
</div>

<script>
function reportsPage(tenantId) {
    return {
        stats: {}, funnel: [], resellers: [], leads: [], maxCount: 1,

        async init() {
            const [metricsRes, funnelRes, resellersRes, leadsRes] = await Promise.all([
                fetch(`/api/metrics/${tenantId}`),
                fetch(`/api/analytics/funnel?tenant_id=${tenantId}`),
                fetch(`/api/resellers?tenant_id=${tenantId}`),
                fetch(`/api/leads?tenant_id=${tenantId}`),
            ]);
            const metricsData = await metricsRes.json();
            this.stats     = metricsData.detail ?? {};
            this.funnel    = await funnelRes.json();
            const res      = await resellersRes.json();
            this.resellers = Array.isArray(res) ? res : (res.data || []);
            const ldata    = await leadsRes.json();
            this.leads     = Array.isArray(ldata) ? ldata : (ldata.data || []);
            this.maxCount  = Math.max(...this.funnel.map(f => f.count), 1);
        },

        // Financial computations using the base_cost / added_amount model
        _addedAmount(l) {
            // Use added_amount if set; fall back to deal_value for legacy records
            const aa = Number(l.added_amount || 0);
            return aa > 0 ? aa : Number(l.deal_value || 0);
        },
        _contract(l) { return Number(l.base_cost||0) + this._addedAmount(l) || Number(l.deal_value||0); },
        _company(l)  { return this._addedAmount(l) * 0.30; },
        _pool(l)     { return this._addedAmount(l) * 0.70; },

        totalContractValue() { return this.leads.reduce((s,l) => s + this._contract(l), 0); },
        totalCompanyShare()  { return this.leads.reduce((s,l) => s + this._company(l), 0); },
        totalCommPool()      { return this.leads.reduce((s,l) => s + this._pool(l), 0); },
        paidCommPool()       { return this.leads.filter(l=>l.commission_status==='paid').reduce((s,l)=>s+this._pool(l),0); },

        commissionBreakdown() {
            return ['pending','locked','paid'].map(status => {
                const group = this.leads.filter(l => (l.commission_status||'pending') === status);
                return {
                    status,
                    count:    group.length,
                    contract: group.reduce((s,l)=>s+this._contract(l),0),
                    company:  group.reduce((s,l)=>s+this._company(l),0),
                    pool:     group.reduce((s,l)=>s+this._pool(l),0),
                };
            });
        },

        fmtM(v) {
            const n = Math.round(Number(v) || 0);
            if (n >= 1000000) return '₱' + (n/1000000).toFixed(1) + 'M';
            if (n >= 1000)    return '₱' + Math.round(n/1000) + 'K';
            return '₱' + n.toLocaleString('en');
        },
    }
}
</script>
@endsection


