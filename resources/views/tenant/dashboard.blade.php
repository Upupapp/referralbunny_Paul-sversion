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

    {{-- Stage funnel + Recent Deals --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- Stage funnel --}}
        <div class="card">
            <div class="flex items-center gap-1 mb-4">
                <h3 class="font-semibold text-[#1E1B4B]">Deal Stages</h3>
                <x-info-tip text="Lead count at each stage of the sales pipeline: Introduction → Presentation → Contract Sent → Signed → Paid." />
            </div>
            <div class="flex items-end gap-2 h-32 overflow-x-auto pb-2" x-show="funnel.length > 0">
                <template x-for="stage in funnel" :key="stage.stage">
                    <div class="flex-1 min-w-[56px] flex flex-col items-center gap-1">
                        <span class="text-xs font-semibold text-[#1E1B4B]" x-text="stage.count"></span>
                        <div class="w-full rounded-t-lg transition-all"
                             :style="'height:' + Math.max(8, (stage.count / maxCount) * 100) + 'px; background: linear-gradient(180deg, #7B61FF, #FF6CAB)'"
                             x-show="maxCount > 0"></div>
                        <span class="text-xs text-gray-400 text-center capitalize" x-text="stage.label"></span>
                    </div>
                </template>
            </div>
            <p class="text-gray-400 text-sm text-center py-4" x-show="funnel.length === 0">No deal data yet</p>
        </div>

        {{-- Recent Deals --}}
        <div class="card">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-[#1E1B4B]">Recent Deals</h3>
                <a href="{{ route('tenant.deals', $tenant->id) }}" class="text-sm text-purple-600 hover:text-purple-700">View all</a>
            </div>
            <div class="space-y-1" x-show="leads.length > 0">
                <template x-for="lead in leads.slice(0,5)" :key="lead.id">
                    <a :href="`/tenant/{{ $tenant->id }}/deals/${lead.id}`"
                       class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-[#F0EFFA] transition-colors">
                        <div class="w-8 h-8 rounded-xl flex items-center justify-center text-[#7B61FF] font-bold text-xs shrink-0"
                             style="background:#EDE9FE" x-text="lead.name.slice(0,2).toUpperCase()"></div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-[#1E1B4B] truncate" x-text="lead.name"></p>
                            <p class="text-xs text-gray-400 capitalize" x-text="lead.stage.replace('_',' ')"></p>
                        </div>
                        <span class="text-xs font-semibold text-gray-600" x-text="'₱' + (lead.deal_value/1000000).toFixed(1) + 'M'"></span>
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

    {{-- ═══ COMMISSION SNAPSHOT (always visible) ═══ --}}
    @if($showLocation ?? false)
    <div class="flex items-center gap-2">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Commission Overview</p>
        <x-tax-tip />
    </div>
    @endif
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="card">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-400 mb-1.5">Pending Commission</p>
            <p class="text-2xl font-bold text-[#1E1B4B]" x-text="commissionStat('pending').count + ' deals'"></p>
            <p class="text-xs text-gray-500 mt-0.5" x-text="'₱' + commissionStat('pending').value + ' in pipeline'"></p>
        </div>
        <div class="card">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-400 mb-1.5">Locked Commission</p>
            <p class="text-2xl font-bold text-orange-600" x-text="commissionStat('locked').count + ' deals'"></p>
            <p class="text-xs text-gray-500 mt-0.5" x-text="'₱' + commissionStat('locked').value + ' awaiting payment'"></p>
        </div>
        <div class="card">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-400 mb-1.5">Paid Commission</p>
            <p class="text-2xl font-bold text-emerald-600" x-text="commissionStat('paid').count + ' deals'"></p>
            <p class="text-xs text-gray-500 mt-0.5" x-text="'₱' + commissionStat('paid').value + ' closed'"></p>
        </div>
        <div class="card">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-400 mb-1.5">Expiring Soon</p>
            <p class="text-2xl font-bold text-red-600" x-text="leads.filter(l => l.days_left <= 7 && l.status === 'active').length"></p>
            <p class="text-xs text-gray-500 mt-0.5">deals with ≤ 7 days left</p>
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
@endsection



