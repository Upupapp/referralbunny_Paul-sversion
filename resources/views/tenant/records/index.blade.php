@extends('layouts.app')
@section('title', 'Records')
@section('nav') @include('tenant._nav') @endsection

@section('topbar-actions')
    <button x-data @click="$dispatch('open-add-record')" class="btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        <span class="hidden sm:inline">New Record</span>
    </button>
@endsection

@section('content')
<div class="space-y-5"
     x-data="recordsModule('{{ $tenant->id }}')"
     x-init="init()"
     @open-add-record.window="showAdd = true">

    {{-- Filter bar --}}
    <div class="card">
        <div class="flex flex-col sm:flex-row gap-3 flex-wrap items-start sm:items-center">
            <div class="search-group flex-1 min-w-48">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" x-model="search" @input.debounce.250ms="applyFilters()" placeholder="Search records, referrers…">
            </div>
            <select x-model="filterStage" @change="applyFilters()" class="form-input sm:w-44">
                <option value="">All Stages</option>
                <option value="introduction">Introduction</option>
                <option value="presentation">Presentation</option>
                <option value="contract_sent">Contract Sent</option>
                <option value="signed">Signed</option>
                <option value="paid">Paid</option>
            </select>
            <select x-model="filterStatus" @change="applyFilters()" class="form-input sm:w-36">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="expiring">Expiring</option>
                <option value="expired">Expired</option>
            </select>
            <select x-model="filterCommission" @change="applyFilters()" class="form-input sm:w-40">
                <option value="">All Commission</option>
                <option value="pending">Pending</option>
                <option value="locked">Locked</option>
                <option value="paid">Paid</option>
            </select>
            <div class="flex items-center gap-1.5 ml-auto">
                <button @click="viewMode = 'table'" :class="viewMode==='table' ? 'bg-[#7B61FF] text-white border-[#7B61FF]' : 'text-gray-500 border-gray-200 hover:bg-gray-50'"
                        class="p-2 rounded-xl border transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 6h18M3 14h18M3 18h18"/></svg>
                </button>
                <button @click="viewMode = 'kanban'" :class="viewMode==='kanban' ? 'bg-[#7B61FF] text-white border-[#7B61FF]' : 'text-gray-500 border-gray-200 hover:bg-gray-50'"
                        class="p-2 rounded-xl border transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/></svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Loading --}}
    <div x-show="loading" class="card flex items-center justify-center py-16 gap-3 text-gray-400">
        <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
        <span class="text-sm">Loading records…</span>
    </div>

    {{-- Table view --}}
    <div x-show="!loading && viewMode === 'table'" class="card p-0 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
            <p class="text-sm font-semibold text-[#1E1B4B]">
                <span x-text="filtered.length"></span><span x-show="leads.length < total" x-text="' of ' + total"></span> records
                <span x-show="filterStage || filterStatus || filterCommission || search" class="text-gray-400 font-normal text-xs ml-1">— filtered</span>
            </p>
            <div class="flex items-center gap-3 text-xs text-gray-400">
                <span x-text="'₱' + totalValue()"></span>
                <span>pipeline value</span>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="table-head">
                        <th>Record</th>
                        <th>Stage</th>
                        <th>Referrer</th>
                        <th>Value</th>
                        <th>Commission</th>
                        <th>Days Left</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="filtered.length === 0 && !loading">
                        <tr>
                            <td colspan="8" class="py-16 text-center">
                                <div class="text-gray-300 mb-3 flex justify-center">
                                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                </div>
                                <p class="text-gray-400 text-sm" x-text="leads.length === 0 ? 'No records yet. Add your first record to get started.' : 'No records match the current filters.'"></p>
                                <button x-show="leads.length === 0" @click="showAdd = true" class="btn-primary mt-3 text-sm">Add First Record</button>
                            </td>
                        </tr>
                    </template>
                    <template x-for="lead in filtered" :key="lead.id">
                        <tr class="table-row cursor-pointer" @click="viewRecord(lead.id)">
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-xl flex items-center justify-center text-[#7B61FF] font-bold text-xs shrink-0"
                                         style="background:#EDE9FE" x-text="(lead.name||'?').slice(0,2).toUpperCase()"></div>
                                    <div class="min-w-0">
                                        <p class="font-medium text-[#1E1B4B] truncate max-w-48" x-text="lead.name"></p>
                                        <p class="text-xs text-gray-400" x-text="lead.data?.municipality || ''"></p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span :class="stageBadge(lead.stage)" x-text="stageLabel(lead.stage)"></span>
                            </td>
                            <td class="text-gray-600 text-sm" x-text="lead.reseller_name || '—'"></td>
                            <td class="font-semibold text-[#1E1B4B]" x-text="formatValue(lead.deal_value)"></td>
                            <td>
                                <span :class="commissionBadge(lead.commission_status)" x-text="(lead.commission_status || 'pending').charAt(0).toUpperCase() + (lead.commission_status || 'pending').slice(1)"></span>
                            </td>
                            <td>
                                <span :class="daysClass(lead.days_left)" x-text="(lead.days_left ?? 21) + 'd'"></span>
                            </td>
                            <td>
                                <span :class="{
                                    'badge badge-green':  lead.status === 'active',
                                    'badge badge-orange': lead.status === 'expiring',
                                    'badge badge-red':    lead.status === 'expired',
                                    'badge badge-gray':   lead.status === 'reassigned' || lead.status === 'declined',
                                }" x-text="lead.status ? lead.status.charAt(0).toUpperCase() + lead.status.slice(1) : 'Active'"></span>
                            </td>
                            <td @click.stop>
                                <a :href="'/tenant/{{ $tenant->id }}/records/' + lead.id"
                                   class="px-2.5 py-1.5 rounded-lg text-xs font-medium bg-purple-50 text-purple-700 hover:bg-purple-100 transition-colors">
                                    View →
                                </a>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Kanban view --}}
    <div x-show="!loading && viewMode === 'kanban'" class="overflow-x-auto pb-4">
        <div class="flex gap-4 min-w-max">
            <template x-for="stage in stages" :key="stage.key">
                <div class="w-64 flex-none">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <div class="w-2 h-2 rounded-full" :style="'background:' + stage.color"></div>
                            <span class="text-xs font-semibold text-gray-700 uppercase tracking-wide" x-text="stage.label"></span>
                        </div>
                        <span class="text-xs text-gray-400" x-text="leadsInStage(stage.key).length"></span>
                    </div>
                    <div class="space-y-2">
                        <template x-for="lead in leadsInStage(stage.key)" :key="lead.id">
                            <a :href="'/tenant/{{ $tenant->id }}/records/' + lead.id"
                               class="block card p-3 hover:shadow-md transition-shadow cursor-pointer">
                                <p class="font-medium text-[#1E1B4B] text-sm truncate" x-text="lead.name"></p>
                                <p class="text-xs text-gray-400 mt-0.5" x-text="lead.reseller_name || 'No referrer'"></p>
                                <div class="flex items-center justify-between mt-2">
                                    <span class="text-xs font-semibold text-gray-700" x-text="formatValue(lead.deal_value)"></span>
                                    <span :class="commissionBadge(lead.commission_status) + ' text-xs'" x-text="lead.commission_status || 'pending'"></span>
                                </div>
                            </a>
                        </template>
                        <div x-show="leadsInStage(stage.key).length === 0" class="text-center py-6 text-gray-300 text-xs">Empty</div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- Add Record Modal --}}
    <div x-show="showAdd" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 sticky top-0 bg-white">
                <h3 class="font-semibold text-[#1E1B4B]">New Record</h3>
                <button @click="showAdd = false; resetForm()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="form-label">Record Name *</label>
                    <input type="text" x-model="form.name" class="form-input" placeholder="Full name or company">
                </div>

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
                    <label class="form-label">Referrer Name *</label>
                    <input type="text" x-model="form.reseller_name" class="form-input" placeholder="Assigned referrer">
                </div>

                {{-- Financial inputs --}}
                <div class="space-y-3 pt-1">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Financial Breakdown</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Base Cost (₱)</label>
                            <input type="number" x-model.number="form.base_cost" class="form-input" placeholder="0" min="0" step="100">
                            <p class="text-xs text-gray-400 mt-1">Delivery cost</p>
                        </div>
                        <div>
                            <label class="form-label">Added Amount (₱)</label>
                            <input type="number" x-model.number="form.added_amount" class="form-input" placeholder="0" min="0" step="100">
                            <p class="text-xs text-gray-400 mt-1">Markup / margin</p>
                        </div>
                    </div>

                    {{-- Live preview --}}
                    <div x-show="(form.base_cost > 0) || (form.added_amount > 0)"
                         class="grid grid-cols-3 gap-2 p-3 rounded-xl bg-gray-50 border border-gray-100">
                        <div class="text-center">
                            <p class="text-[10px] text-gray-400 uppercase tracking-wide">Contract Value</p>
                            <p class="text-sm font-bold text-[#1E1B4B] tabular-nums mt-0.5" x-text="'₱' + ((form.base_cost||0)+(form.added_amount||0)).toLocaleString()"></p>
                        </div>
                        <div class="text-center border-x border-gray-200">
                            <p class="text-[10px] text-blue-500 uppercase tracking-wide">Company Share</p>
                            <p class="text-sm font-bold text-blue-700 tabular-nums mt-0.5" x-text="'₱' + ((form.added_amount||0)*0.30).toLocaleString()"></p>
                            <p class="text-[10px] text-blue-400">30% margin</p>
                        </div>
                        <div class="text-center">
                            <p class="text-[10px] text-emerald-500 uppercase tracking-wide">Commission Pool</p>
                            <p class="text-sm font-bold text-emerald-700 tabular-nums mt-0.5" x-text="'₱' + ((form.added_amount||0)*0.70).toLocaleString()"></p>
                            <p class="text-[10px] text-emerald-400">70% margin</p>
                        </div>
                    </div>
                </div>

                <p x-show="formError" class="text-xs text-red-600" x-text="formError"></p>
                <div class="flex justify-end gap-3 pt-1">
                    <button @click="showAdd = false; resetForm()" class="btn-secondary">Cancel</button>
                    <button @click="addRecord()" :disabled="saving" class="btn-primary" x-text="saving ? 'Saving…' : 'Add Record'"></button>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function recordsModule(tenantId) {
    return {
        leads: [], filtered: [], total: 0, loading: true,
        viewMode: 'table',
        search: '', filterStage: '', filterStatus: '', filterCommission: '',
        showAdd: false, saving: false, formError: '',
        form: { name: '', stage: 'introduction', base_cost: 0, added_amount: 0, reseller_name: '' },

        stages: [
            { key: 'introduction',  label: 'Introduction',  color: '#9CA3AF' },
            { key: 'presentation',  label: 'Presentation',  color: '#3B82F6' },
            { key: 'contract_sent', label: 'Contract Sent', color: '#F59E0B' },
            { key: 'signed',        label: 'Signed',        color: '#8B5CF6' },
            { key: 'paid',          label: 'Paid',          color: '#10B981' },
        ],

        async init() {
            try {
                const res = await fetch(`/api/leads?tenant_id=${tenantId}&per_page=500`);
                const d = await res.json(); this.leads = Array.isArray(d) ? d : (d.data || []);
                this.total = d.total ?? this.leads.length;
            } catch(e) { this.leads = []; }
            this.applyFilters();
            this.loading = false;
        },

        applyFilters() {
            const q = this.search.toLowerCase();
            this.filtered = this.leads.filter(l => {
                const matchQ  = !q || (l.name||'').toLowerCase().includes(q) || (l.reseller_name||'').toLowerCase().includes(q);
                const matchSt = !this.filterStage      || l.stage              === this.filterStage;
                const matchSx = !this.filterStatus     || l.status             === this.filterStatus;
                const matchCo = !this.filterCommission || l.commission_status   === this.filterCommission;
                return matchQ && matchSt && matchSx && matchCo;
            });
        },

        leadsInStage(stage) { return this.leads.filter(l => l.stage === stage); },

        totalValue() {
            const t = this.filtered.reduce((s, l) => s + (Number(l.deal_value) || 0), 0);
            if (t >= 1000000) return (t/1000000).toFixed(1) + 'M';
            if (t >= 1000)    return Math.round(t/1000) + 'K';
            return t.toLocaleString();
        },

        stageLabel(s) {
            const m = { introduction:'Intro', presentation:'Presentation', contract_sent:'Contract', signed:'Signed', paid:'Paid' };
            return m[s] || s;
        },

        stageBadge(s) {
            const m = { introduction:'badge badge-gray', presentation:'badge badge-blue', contract_sent:'badge badge-orange', signed:'badge badge-purple', paid:'badge badge-green' };
            return m[s] || 'badge badge-gray';
        },

        commissionBadge(s) {
            const m = { pending:'badge badge-gray', locked:'badge badge-orange', paid:'badge badge-green' };
            return m[s] || 'badge badge-gray';
        },

        daysClass(d) {
            const n = d ?? 21;
            if (n <= 0)  return 'text-red-600 font-bold text-xs tabular-nums';
            if (n <= 7)  return 'text-orange-500 font-semibold text-xs tabular-nums';
            return 'text-gray-500 text-xs tabular-nums';
        },

        formatValue(v) {
            const n = Number(v) || 0;
            if (n >= 1000000) return '₱' + (n/1000000).toFixed(1) + 'M';
            if (n >= 1000)    return '₱' + Math.round(n/1000) + 'K';
            return n > 0 ? '₱' + n.toLocaleString() : '₱0';
        },

        viewRecord(id) { window.location.href = `/tenant/${tenantId}/records/${id}`; },

        resetForm() { this.form = { name:'', stage:'introduction', base_cost:0, added_amount:0, reseller_name:'' }; this.formError = ''; },

        async addRecord() {
            if (!this.form.name)          { this.formError = 'Record name is required.'; return; }
            if (!this.form.reseller_name) { this.formError = 'Referrer name is required.'; return; }
            this.saving = true; this.formError = '';
            try {
                const bc = Number(this.form.base_cost)    || 0;
                const aa = Number(this.form.added_amount) || 0;
                const res = await fetch('/api/leads', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ ...this.form, base_cost: bc, added_amount: aa, deal_value: bc + aa, tenant_id: tenantId }),
                });
                const lead = await res.json();
                if (lead.id) {
                    this.leads.unshift(lead);
                    this.applyFilters();
                    this.showAdd = false;
                    this.resetForm();
                } else {
                    this.formError = lead.message || 'Failed to create record.';
                }
            } catch(e) { this.formError = 'Network error. Please try again.'; }
            finally { this.saving = false; }
        },
    }
}
</script>
@endsection
