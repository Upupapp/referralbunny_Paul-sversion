@extends('layouts.app')
@section('title', 'Organizations')
@section('nav') @include('tenant._nav') @endsection

@section('topbar-actions')
    <button x-data @click="$dispatch('open-add-org')" class="btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        <span class="hidden sm:inline">Add Organization</span>
    </button>
@endsection

@section('content')
<div class="space-y-5"
     x-data="orgsModule('{{ $tenant->id }}')"
     x-init="init()"
     @open-add-org.window="openAdd()">

    {{-- KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Total Organizations</span>
                <p class="text-2xl font-bold text-[#1E1B4B] mt-1.5" x-text="orgs.length"></p>
            </div>
            <div class="kpi-icon bg-purple-100 ml-3 shrink-0">
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
        </div>
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Total Contacts</span>
                <p class="text-2xl font-bold text-[#1E1B4B] mt-1.5" x-text="orgs.reduce((s,o) => s + Number(o.contact_count||0), 0)"></p>
            </div>
            <div class="kpi-icon bg-blue-100 ml-3 shrink-0">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </div>
        </div>
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">With Deals</span>
                <p class="text-2xl font-bold text-[#1E1B4B] mt-1.5" x-text="orgs.filter(o => o.deal_count > 0).length"></p>
            </div>
            <div class="kpi-icon bg-emerald-100 ml-3 shrink-0">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
            </div>
        </div>
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Total Deal Value</span>
                <p class="text-2xl font-bold text-[#1E1B4B] mt-1.5" x-text="fmtValue(orgs.reduce((s,o) => s + Number(o.deal_value||0), 0))"></p>
            </div>
            <div class="kpi-icon bg-orange-100 ml-3 shrink-0">
                <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
    </div>

    {{-- Filter bar --}}
    <div class="card">
        <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
            <div class="search-group flex-1">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" x-model="search" @input.debounce.250ms="applyFilters()" placeholder="Search organizations…">
            </div>
            <select x-model="filterIndustry" @change="applyFilters()" class="form-input sm:w-48">
                <option value="">All Industries</option>
                <template x-for="ind in industries" :key="ind">
                    <option :value="ind" x-text="ind"></option>
                </template>
            </select>
        </div>
    </div>

    {{-- Table --}}
    <div class="card p-0 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
            <p class="text-sm font-semibold text-[#1E1B4B]">
                <span x-text="filtered.length"></span> organizations
                <span x-show="filterIndustry || search" class="text-gray-400 font-normal text-xs ml-1">— filtered</span>
            </p>
        </div>

        <div x-show="loading" class="flex items-center justify-center py-10 gap-3 text-gray-400">
            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            <span class="text-sm">Loading organizations…</span>
        </div>

        <div x-show="!loading" class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="table-head">
                        <th>Organization</th>
                        <th>Industry</th>
                        <th>City</th>
                        <th>Contacts</th>
                        <th>Deals</th>
                        <th>Deal Value</th>
                        <th>Added</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="filtered.length === 0 && !loading">
                        <tr>
                            <td colspan="8" class="py-16 text-center">
                                <div class="flex justify-center text-gray-300 mb-3">
                                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                </div>
                                <p class="text-gray-400 text-sm" x-text="orgs.length === 0 ? 'No organizations yet. Add your first organization.' : 'No organizations match the filters.'"></p>
                                <button x-show="orgs.length === 0" @click="openAdd()" class="btn-primary mt-3 text-sm">Add First Organization</button>
                            </td>
                        </tr>
                    </template>
                    <template x-for="o in filtered" :key="o.id">
                        <tr class="table-row">
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-[#EDE9FE] flex items-center justify-center text-[#7B61FF] text-xs font-bold shrink-0"
                                         x-text="(o.name||'?').slice(0,2).toUpperCase()"></div>
                                    <div class="min-w-0">
                                        <p class="font-medium text-[#1E1B4B] truncate" x-text="o.name"></p>
                                        <a x-show="o.website" :href="o.website" target="_blank"
                                           class="text-xs text-purple-500 hover:text-purple-700 truncate block" x-text="o.website"></a>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span x-show="o.industry" class="badge badge-gray text-xs" x-text="o.industry"></span>
                                <span x-show="!o.industry" class="text-gray-300 text-sm">—</span>
                            </td>
                            <td class="text-gray-500 text-sm" x-text="o.city || '—'"></td>
                            <td>
                                <span x-show="o.contact_count > 0"
                                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-purple-50 text-purple-700 text-xs font-semibold">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    <span x-text="o.contact_count"></span>
                                </span>
                                <span x-show="!o.contact_count" class="text-gray-300 text-sm">—</span>
                            </td>
                            <td class="text-sm font-semibold text-[#1E1B4B] tabular-nums"
                                x-text="o.deal_count > 0 ? o.deal_count : '—'"></td>
                            <td class="text-sm font-semibold text-[#1E1B4B] tabular-nums"
                                x-text="o.deal_value > 0 ? fmtValue(o.deal_value) : '—'"></td>
                            <td class="text-gray-400 text-sm tabular-nums"
                                x-text="o.created_at ? new Date(o.created_at).toLocaleDateString('en',{month:'short',day:'numeric',year:'numeric'}) : '—'"></td>
                            <td>
                                <div class="flex items-center gap-1.5 justify-end">
                                    <button @click="openEdit(o)"
                                            class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-700 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button @click="deleteOrg(o.id)"
                                            class="p-1.5 rounded-lg hover:bg-red-50 text-gray-400 hover:text-red-500 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Add / Edit Modal --}}
    <div x-show="showModal" x-cloak
         class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4"
         @keydown.escape.window="showModal = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-[#1E1B4B]" x-text="editId ? 'Edit Organization' : 'Add Organization'"></h3>
                <button @click="showModal = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="form-label">Organization Name *</label>
                    <input type="text" x-model="form.name" class="form-input" placeholder="e.g. Municipality of Tagaytay">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Industry</label>
                        <input type="text" x-model="form.industry" class="form-input" placeholder="e.g. Government, Healthcare">
                    </div>
                    <div>
                        <label class="form-label">City / Municipality</label>
                        <input type="text" x-model="form.city" class="form-input" placeholder="City name">
                    </div>
                    <div>
                        <label class="form-label">Website</label>
                        <input type="text" x-model="form.website" class="form-input" placeholder="https://…">
                    </div>
                    <div>
                        <label class="form-label">Country</label>
                        <input type="text" x-model="form.country" class="form-input" placeholder="Philippines">
                    </div>
                </div>
                <div>
                    <label class="form-label">Address</label>
                    <textarea x-model="form.address" class="form-input" rows="2" placeholder="Full address"></textarea>
                </div>
                <div>
                    <label class="form-label">Notes</label>
                    <textarea x-model="form.notes" class="form-input" rows="2" placeholder="Any notes about this organization…"></textarea>
                </div>
                <p x-show="formError" class="text-xs text-red-600 font-medium" x-text="formError"></p>
                <div class="flex justify-end gap-3 pt-1">
                    <button @click="showModal = false" class="btn-secondary">Cancel</button>
                    <button @click="saveOrg()" :disabled="saving" class="btn-primary"
                            x-text="saving ? 'Saving…' : (editId ? 'Save Changes' : 'Add Organization')"></button>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function orgsModule(tenantId) {
    return {
        orgs: [], filtered: [],
        loading: true,
        search: '', filterIndustry: '',
        showModal: false, saving: false, formError: '',
        editId: null,
        form: { name: '', industry: '', website: '', address: '', city: '', country: 'Philippines', notes: '' },

        get industries() {
            const set = new Set(this.orgs.map(o => o.industry).filter(Boolean));
            return [...set].sort();
        },

        async init() {
            try {
                const res = await fetch(`/api/organizations?tenant_id=${tenantId}`);
                this.orgs = await res.json();
                if (!Array.isArray(this.orgs)) this.orgs = [];
            } catch(e) { this.orgs = []; }
            this.applyFilters();
            this.loading = false;
        },

        applyFilters() {
            const q = this.search.toLowerCase();
            this.filtered = this.orgs.filter(o => {
                const matchQ = !q || (o.name||'').toLowerCase().includes(q) || (o.city||'').toLowerCase().includes(q) || (o.industry||'').toLowerCase().includes(q);
                const matchI = !this.filterIndustry || o.industry === this.filterIndustry;
                return matchQ && matchI;
            });
        },

        fmtValue(v) {
            const n = Math.round(Number(v) || 0);
            if (n >= 1000000) return '&#8369;' + (n/1000000).toFixed(1) + 'M';
            if (n >= 1000)    return '&#8369;' + Math.round(n/1000) + 'K';
            return n > 0 ? '&#8369;' + n.toLocaleString('en') : '—';
        },

        openAdd() {
            this.editId = null;
            this.form = { name: '', industry: '', website: '', address: '', city: '', country: 'Philippines', notes: '' };
            this.formError = '';
            this.showModal = true;
        },

        openEdit(o) {
            this.editId = o.id;
            this.form = {
                name:     o.name || '',
                industry: o.industry || '',
                website:  o.website || '',
                address:  o.address || '',
                city:     o.city || '',
                country:  o.country || 'Philippines',
                notes:    o.notes || '',
            };
            this.formError = '';
            this.showModal = true;
        },

        async saveOrg() {
            if (!this.form.name.trim()) { this.formError = 'Organization name is required.'; return; }
            this.saving = true; this.formError = '';
            try {
                const url    = this.editId ? `/api/organizations/${this.editId}` : '/api/organizations';
                const method = this.editId ? 'PUT' : 'POST';
                const body   = this.editId ? { ...this.form } : { ...this.form, tenant_id: tenantId };
                const res    = await fetch(url, {
                    method,
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify(body),
                });
                const data = await res.json();
                if (data.id) {
                    if (this.editId) {
                        const i = this.orgs.findIndex(o => o.id === this.editId);
                        if (i !== -1) this.orgs.splice(i, 1, data);
                    } else {
                        this.orgs.unshift(data);
                    }
                    this.applyFilters();
                    this.showModal = false;
                    this.$dispatch('show-toast', { type: 'success', message: this.editId ? 'Organization updated.' : 'Organization added.' });
                } else {
                    this.formError = data.message || 'Failed to save organization.';
                }
            } catch(e) { this.formError = 'Network error. Please try again.'; }
            finally { this.saving = false; }
        },

        async deleteOrg(id) {
            if (!confirm('Delete this organization? Contacts linked to it will become unaffiliated.')) return;
            try {
                await fetch(`/api/organizations/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                });
                this.orgs = this.orgs.filter(o => o.id !== id);
                this.applyFilters();
                this.$dispatch('show-toast', { type: 'success', message: 'Organization deleted.' });
            } catch(e) {
                this.$dispatch('show-toast', { type: 'error', message: 'Failed to delete organization.' });
            }
        },
    };
}
</script>
@endsection
