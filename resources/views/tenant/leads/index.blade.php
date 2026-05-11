@extends('layouts.app')
@section('title', 'Leads')
@section('nav')
    @include('tenant._nav')
@endsection

@section('topbar-actions')
    <button x-data @click="$dispatch('open-add-lead')" class="btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        <span class="hidden sm:inline">Add Lead</span>
    </button>
@endsection

@section('content')
<div class="space-y-5" x-data="leadsPage('{{ $tenant->id }}', {{ ($showLocation ?? false) ? 'true' : 'false' }})" x-init="init()" @open-add-lead.window="showAdd = true">

    {{-- Filters --}}
    <div class="card">
        <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
            <div class="search-group flex-1">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" x-model="search" @input.debounce="filter()" placeholder="Search leads…">
            </div>
            <select x-effect="$el.value = filterStage" @change="filterStage = $event.target.value; filter()" class="form-input sm:w-40">
                <option value="">All Stages</option>
                <option value="introduction">Introduction</option>
                <option value="presentation">Presentation</option>
                <option value="contract_sent">Contract Sent</option>
                <option value="signed">Signed</option>
                <option value="paid">Paid</option>
            </select>
            <select x-effect="$el.value = filterStatus" @change="filterStatus = $event.target.value; filter()" class="form-input sm:w-36">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="expiring">Expiring</option>
                <option value="expired">Expired</option>
            </select>
        </div>
    </div>

    {{-- Table --}}
    <div class="card p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead><tr class="table-head"><th>Lead</th><th>Stage</th><th>Referrer</th><th>Value</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    <template x-if="loading"><tr><td colspan="6" class="py-10 text-center text-gray-400">Loading...</td></tr></template>
                    <template x-if="!loading && filtered.length === 0"><tr><td colspan="6" class="py-10 text-center text-gray-400">No leads found</td></tr></template>
                    <template x-for="lead in filtered" :key="lead.id">
                        <tr class="table-row cursor-pointer" @click="window.location=`/tenant/{{ $tenant->id }}/leads/${lead.id}`">
                            <td>
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-xl flex items-center justify-center text-[#7B61FF] font-bold text-xs shrink-0" style="background:#EDE9FE" x-text="(lead.name||'?').slice(0,2).toUpperCase()"></div>
                                    <div class="min-w-0">
                                        <p class="font-medium text-[#1E1B4B] truncate" x-text="lead.name"></p>
                                        <p class="text-xs text-gray-400" x-text="[lead.data?.province, lead.data?.municipality].filter(Boolean).join(' › ') || ''"></p>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge badge-purple capitalize text-xs" x-text="lead.stage?.replace('_',' ') || '—'"></span></td>
                            <td class="text-gray-500 text-sm" x-text="lead.reseller_name || '—'"></td>
                            <td class="font-semibold text-sm" x-text="lead.deal_value > 0 ? '₱' + Math.round(lead.deal_value).toLocaleString('en') : '—'"></td>
                            <td><span :class="{'badge':true,'badge-green':lead.status==='active','badge-orange':lead.status==='expiring','badge-red':lead.status==='expired','badge-gray':true}" x-text="lead.status"></span></td>
                            <td class="text-right"><a :href="`/tenant/{{ $tenant->id }}/leads/${lead.id}`" class="text-purple-600 hover:text-purple-700 text-xs font-medium" @click.stop>View →</a></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Add Lead Modal --}}
    <div x-show="showAdd" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 sticky top-0 bg-white">
                <h3 class="font-semibold text-[#1E1B4B]">Add Lead</h3>
                <button @click="showAdd = false" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <div class="p-6 space-y-4">
                @if($showLocation)
                {{-- Province + Municipality — shown only for tenants whose config includes these fields --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Province *</label>
                        <select x-model="form.province" @change="deriveName()" class="form-input">
                            <option value="">Select province…</option>
                            @foreach(['Abra','Agusan del Norte','Agusan del Sur','Aklan','Albay','Antique','Apayao','Aurora','Basilan','Bataan','Batanes','Batangas','Benguet','Biliran','Bohol','Bukidnon','Bulacan','Cagayan','Camarines Norte','Camarines Sur','Camiguin','Capiz','Catanduanes','Cavite','Cebu','Cotabato','Davao de Oro','Davao del Norte','Davao del Sur','Davao Occidental','Davao Oriental','Dinagat Islands','Eastern Samar','Guimaras','Ifugao','Ilocos Norte','Ilocos Sur','Iloilo','Isabela','Kalinga','La Union','Laguna','Lanao del Norte','Lanao del Sur','Leyte','Maguindanao del Norte','Maguindanao del Sur','Marinduque','Masbate','Metro Manila','Misamis Occidental','Misamis Oriental','Mountain Province','Negros Occidental','Negros Oriental','Northern Samar','Nueva Ecija','Nueva Vizcaya','Occidental Mindoro','Oriental Mindoro','Palawan','Pampanga','Pangasinan','Quezon','Quirino','Rizal','Romblon','Samar','Sarangani','Siquijor','Sorsogon','South Cotabato','Southern Leyte','Sultan Kudarat','Sulu','Surigao del Norte','Surigao del Sur','Tarlac','Tawi-Tawi','Zambales','Zamboanga del Norte','Zamboanga del Sur','Zamboanga Sibugay'] as $prov)
                            <option value="{{ $prov }}">{{ $prov }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Municipality / City *</label>
                        <input type="text" x-model="form.municipality" @input="deriveName()" class="form-input" placeholder="e.g. Mandaue City">
                    </div>
                </div>
                @endif
                <div>
                    <label class="form-label">{{ $leadLabel ?? 'Lead' }} Name *
                        @if($showLocation)
                        <span x-show="nameAutoFilled" class="ml-1 text-xs text-purple-500 font-normal">(auto-filled)</span>
                        @endif
                    </label>
                    <input type="text" x-model="form.name" @input="nameAutoFilled = false" class="form-input"
                           placeholder="{{ $showLocation ? 'Auto-fills from Province + Municipality' : 'Enter name' }}">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Stage *</label>
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
                <p x-show="formError" class="text-xs text-red-600 font-medium" x-text="formError"></p>
                <div class="flex justify-end gap-3">
                    <button @click="showAdd = false; formError = ''" class="btn-secondary">Cancel</button>
                    <button @click="addLead()" :disabled="saving" class="btn-primary" x-text="saving ? 'Saving...' : 'Add Lead'"></button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function leadsPage(tenantId, showLocation) {
    return {
        leads: [], filtered: [], loading: true, showAdd: false, saving: false,
        search: '', filterStage: '', filterStatus: '', formError: '',
        nameAutoFilled: false,
        form: { name:'', stage:'introduction', deal_value:'', reseller_name:'', province:'', municipality:'' },

        async init() {
            try {
                const res = await fetch(`/api/leads?tenant_id=${tenantId}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const d = await res.json(); this.leads = Array.isArray(d) ? d : (d.data || []);
            } catch(e) { this.leads = []; }
            this.filtered = this.leads;
            this.loading = false;
        },

        filter() {
            this.filtered = this.leads.filter(l => {
                const matchSearch = !this.search || l.name.toLowerCase().includes(this.search.toLowerCase());
                const matchStage  = !this.filterStage  || l.stage  === this.filterStage;
                const matchStatus = !this.filterStatus || l.status === this.filterStatus;
                return matchSearch && matchStage && matchStatus;
            });
        },

        deriveName() {
            if (this.nameAutoFilled || !this.form.name) {
                const parts = [this.form.municipality, this.form.province].filter(Boolean);
                if (parts.length > 0) { this.form.name = parts.join(', '); this.nameAutoFilled = true; }
            }
        },

        async addLead() {
            this.formError = '';
            if (showLocation && !this.form.province)     { this.formError = 'Province is required.'; return; }
            if (showLocation && !this.form.municipality) { this.formError = 'Municipality / City is required.'; return; }
            if (!this.form.name)         { this.formError = 'Lead name is required.'; return; }
            if (!this.form.reseller_name){ this.formError = 'Reseller name is required.'; return; }
            this.saving = true;
            try {
                const csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
                const { province, municipality, ...rest } = this.form;
                const res = await fetch('/api/leads', {
                    method:      'POST',
                    credentials: 'same-origin',
                    headers:     { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body:        JSON.stringify({ ...rest, tenant_id: tenantId, data: { province, municipality } }),
                });
                const data = await res.json();
                if (res.ok) {
                    this.showAdd = false;
                    this.form = { name:'', stage:'introduction', deal_value:'', reseller_name:'', province:'', municipality:'' };
                    this.nameAutoFilled = false;
                    this.formError = '';
                    await this.init();
                } else {
                    this.formError = data.message || 'Failed to save lead. Please try again.';
                }
            } catch(e) {
                this.formError = 'Network error. Please try again.';
            } finally { this.saving = false; }
        },
    }
}
</script>
@endsection
