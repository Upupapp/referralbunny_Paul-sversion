@extends('layouts.reseller')
@section('title', 'My Deals')
@section('nav') @include('reseller._nav') @endsection

@section('topbar-actions')
    <button x-data @click="$dispatch('open-add-deal')" class="rs-btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        <span class="hidden sm:inline">New Deal</span>
    </button>
@endsection

@section('content')
<div class="space-y-5"
     x-data="resellerDeals('{{ $tenant->id }}', '{{ addslashes($reseller->name) }}')"
     x-init="init()"
     @open-add-deal.window="showAdd = true">

    {{-- Search + filters --}}
    <div class="card space-y-3">
        <div class="search-group">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" x-model="search" @input.debounce.300ms="applyFilters()" placeholder="Search my deals…">
            <button x-show="search" @click="search=''; applyFilters()" class="text-gray-400 hover:text-gray-600 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="filter-bar">
            <label class="filter-pill" :class="filterStatus ? 'active' : ''">
                <select x-model="filterStatus" @change="applyFilters()">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="expiring">Expiring</option>
                    <option value="expired">Expired</option>
                </select>
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>
            <label class="filter-pill" :class="filterStage ? 'active' : ''">
                <select x-model="filterStage" @change="applyFilters()">
                    <option value="">All Stages</option>
                    <option value="introduction">Introduction</option>
                    <option value="presentation">Presentation</option>
                    <option value="contract_sent">Contract Sent</option>
                    <option value="signed">Signed</option>
                    <option value="paid">Paid</option>
                </select>
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>
            <button x-show="filterStatus || filterStage || search"
                    @click="filterStatus=''; filterStage=''; search=''; applyFilters()"
                    class="filter-pill !border-red-200 !text-red-500 hover:!bg-red-50">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                Clear
            </button>
        </div>
    </div>

    {{-- Table --}}
    <div class="card p-0 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
            <p class="text-sm font-semibold" style="color:#1E1B4B">
                <span x-text="filtered.length"></span> deal<span x-show="filtered.length !== 1">s</span>
            </p>
        </div>

        <div x-show="loading" class="flex items-center justify-center py-10 gap-3 text-gray-400">
            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            <span class="text-sm">Loading your deals…</span>
        </div>

        <div x-show="!loading" class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="table-head">
                        <th>Deal</th>
                        <th>Stage</th>
                        <th>Value</th>
                        <th>Commission</th>
                        <th>Status</th>
                        <th>Days Left</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="filtered.length === 0 && !loading">
                        <tr><td colspan="6" class="py-14 text-center">
                            <img src="/images/mascots/r-bunny-sleeping.webp" alt="" class="w-12 h-12 object-contain mx-auto mb-3 opacity-50">
                            <p class="text-gray-400 text-sm">No deals match your filters.</p>
                        </td></tr>
                    </template>
                    <template x-for="d in filtered" :key="d.id">
                        <tr class="table-row">
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-xs font-bold shrink-0"
                                         :style="`background:${stageColor(d.stage)}`"
                                         x-text="(d.name||'?').slice(0,2).toUpperCase()"></div>
                                    <div class="min-w-0">
                                        <p class="font-medium text-sm truncate" style="color:#1E1B4B" x-text="d.name"></p>
                                        <p class="text-xs text-gray-400 truncate" x-text="(d.data?.province || d.data?.municipality) ? (d.data?.province || '') : ''"></p>
                                    </div>
                                </div>
                            </td>
                            <td class="text-sm text-gray-600 capitalize" x-text="(d.stage||'').replace('_',' ')"></td>
                            <td class="text-sm font-bold tabular-nums" style="color:#1E1B4B" x-text="d.deal_value ? '₱'+Number(d.deal_value).toLocaleString() : '₱0'"></td>
                            <td>
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium capitalize"
                                      :class="{'bg-violet-100 text-violet-700': d.commission_status==='pending','bg-amber-100 text-amber-700': d.commission_status==='locked','bg-emerald-100 text-emerald-700': d.commission_status==='paid'}"
                                      x-text="d.commission_status || 'pending'"></span>
                            </td>
                            <td>
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium capitalize"
                                      :class="{'bg-emerald-100 text-emerald-700':d.status==='active','bg-amber-100 text-amber-700':d.status==='expiring','bg-red-100 text-red-600':d.status==='expired','bg-gray-100 text-gray-500':!['active','expiring','expired'].includes(d.status||'')}"
                                      x-text="d.status || 'active'"></span>
                            </td>
                            <td class="text-sm tabular-nums" :class="(d.days_left||21) <= 3 ? 'text-red-500 font-bold' : (d.days_left||21) <= 7 ? 'text-amber-500 font-semibold' : 'text-gray-500'"
                                x-text="(d.days_left ?? 21) + 'd'"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Add Deal Modal (same LGU autocomplete as tenant admin) --}}
    <div x-show="showAdd" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="font-semibold" style="color:#1E1B4B">Add New Deal</h3>
                <button @click="showAdd=false; clearLgu()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6 space-y-4">
                {{-- LGU Search --}}
                <div>
                    <label class="form-label">City / Municipality *</label>
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" x-model="lguQuery" @input.debounce.300ms="searchLgu()" @keydown.escape="lguDropdown=false"
                               class="form-input pl-9" :class="lguSelected ? 'border-teal-300 bg-teal-50/30' : ''"
                               placeholder="Type city or municipality…" autocomplete="off">
                        <button x-show="lguSelected" @click="clearLgu()" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                        <div x-show="lguDropdown && lguResults.length > 0" x-cloak @click.outside="lguDropdown=false"
                             class="absolute z-30 w-full mt-1 bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden max-h-48 overflow-y-auto">
                            <template x-for="org in lguResults" :key="org.id">
                                <button type="button" @click="selectLgu(org)"
                                        class="w-full flex items-center gap-3 px-4 py-2.5 text-left hover:bg-gray-50">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium truncate" style="color:#1E1B4B" x-text="org.name"></p>
                                        <p class="text-xs text-gray-400 truncate" x-text="org.address"></p>
                                    </div>
                                </button>
                            </template>
                        </div>
                    </div>
                    <div x-show="lguSelected" x-cloak class="mt-2 px-4 py-3 rounded-xl border border-teal-100" style="background:#F0FDFA">
                        <p class="text-[10px] font-bold text-teal-500 uppercase tracking-widest mb-1">Standardized Name</p>
                        <p class="text-sm font-semibold" style="color:#1E1B4B" x-text="form.name"></p>
                        <p class="text-xs text-gray-500 mt-0.5">Province: <span class="font-medium text-gray-700" x-text="form.data.province || '—'"></span></p>
                    </div>
                </div>

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

                <p x-show="addError" class="text-xs text-red-600 font-medium" x-text="addError"></p>
                <div class="flex justify-end gap-3">
                    <button @click="showAdd=false; clearLgu()" class="btn-secondary">Cancel</button>
                    <button @click="addDeal()" :disabled="saving || !lguSelected"
                            class="rs-btn-primary"
                            x-text="saving ? 'Saving…' : 'Add Deal'"></button>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function resellerDeals(tenantId, resellerName) {
    return {
        leads: [], filtered: [], loading: true,
        search: '', filterStatus: '', filterStage: '',
        showAdd: false, saving: false, addError: '',
        lguQuery: '', lguResults: [], lguDropdown: false, lguSelected: null,
        form: { name: '', stage: 'introduction', deal_value: '', data: { province: '', municipality: '' } },

        stageColors: { introduction:'#9CA3AF', presentation:'#3B82F6', contract_sent:'#F59E0B', signed:'#8B5CF6', paid:'#10B981' },
        stageColor(s) { return this.stageColors[s] || '#9CA3AF'; },

        async init() {
            const urlParams = new URLSearchParams(window.location.search);
            const preStatus = urlParams.get('status');
            if (['expiring','expired','active'].includes(preStatus)) this.filterStatus = preStatus;
            try {
                const res  = await fetch(`/api/leads?tenant_id=${tenantId}&reseller_name=${encodeURIComponent(resellerName)}`);
                const data = await res.json();
                this.leads = Array.isArray(data) ? data : [];
            } catch(e) { this.leads = []; }
            this.applyFilters();
            this.loading = false;
        },

        applyFilters() {
            const q = this.search.toLowerCase();
            this.filtered = this.leads.filter(d => {
                const matchQ  = !q || (d.name||'').toLowerCase().includes(q);
                const matchSt = !this.filterStatus || d.status === this.filterStatus;
                const matchSg = !this.filterStage  || d.stage  === this.filterStage;
                return matchQ && matchSt && matchSg;
            });
        },

        async searchLgu() {
            if (this.lguQuery.length < 2) { this.lguResults = []; this.lguDropdown = false; return; }
            this.lguDropdown = true;
            try {
                const res  = await fetch(`/api/organizations?tenant_id=${tenantId}&search=${encodeURIComponent(this.lguQuery)}&per_page=10`);
                const json = await res.json();
                this.lguResults = json.data || [];
            } catch(e) { this.lguResults = []; }
        },

        selectLgu(org) {
            this.lguSelected = org;
            this.lguDropdown = false;
            this.lguQuery    = org.name;
            this.form.name   = org.name;
            this.form.data.province    = org.address || '';
            this.form.data.municipality = (org.name||'').replace(/^(?:Municipality|City) of\s+/i, '');
        },

        clearLgu() {
            this.lguSelected = null; this.lguQuery = ''; this.lguResults = []; this.lguDropdown = false;
            this.form.name = ''; this.form.data.province = ''; this.form.data.municipality = '';
        },

        async addDeal() {
            this.addError = '';
            if (!this.lguSelected) { this.addError = 'Please select a city or municipality.'; return; }
            this.saving = true;
            try {
                const res  = await fetch('/api/leads', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ tenant_id: tenantId, reseller_name: resellerName, ...this.form }),
                });
                const lead = await res.json();
                if (!res.ok) { this.addError = lead.message || Object.values(lead.errors||{})[0]?.[0] || 'Failed to create deal.'; return; }
                if (lead.id) { this.leads.unshift(lead); this.applyFilters(); }
                this.showAdd = false;
                this.clearLgu();
                this.form = { name: '', stage: 'introduction', deal_value: '', data: { province: '', municipality: '' } };
                this.$dispatch('show-toast', { type: 'success', message: `Deal "${lead.name}" added successfully.` });
            } catch(e) { this.addError = 'Network error. Please try again.'; }
            finally { this.saving = false; }
        },
    };
}
</script>
@endsection
