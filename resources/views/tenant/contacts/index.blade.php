@extends('layouts.app')
@section('title', 'Contacts')
@section('nav') @include('tenant._nav') @endsection

@section('topbar-actions')
    <button x-data @click="$dispatch('open-add-contact')" class="btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        <span class="hidden sm:inline">Add Contact</span>
    </button>
@endsection

@section('content')
<div class="space-y-5"
     x-data="contactsModule('{{ $tenant->id }}')"
     x-init="init()"
     @open-add-contact.window="openAdd()">

    {{-- KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Total Contacts</span>
                <p class="text-2xl font-bold text-[#1E1B4B] mt-1.5" x-text="contacts.length"></p>
            </div>
            <div class="kpi-icon bg-purple-100 ml-3 shrink-0">
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </div>
        </div>
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Active</span>
                <p class="text-2xl font-bold text-[#1E1B4B] mt-1.5" x-text="contacts.filter(c => c.status === 'active').length"></p>
            </div>
            <div class="kpi-icon bg-emerald-100 ml-3 shrink-0">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Linked to Deals</span>
                <p class="text-2xl font-bold text-[#1E1B4B] mt-1.5" x-text="contacts.filter(c => c.deal_count > 0).length"></p>
            </div>
            <div class="kpi-icon bg-blue-100 ml-3 shrink-0">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
            </div>
        </div>
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Organizations</span>
                <p class="text-2xl font-bold text-[#1E1B4B] mt-1.5" x-text="orgs.length"></p>
            </div>
            <div class="kpi-icon bg-orange-100 ml-3 shrink-0">
                <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
        </div>
    </div>

    {{-- Filter bar --}}
    <div class="card">
        <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
            <div class="search-group flex-1">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" x-model="search" @input.debounce.250ms="applyFilters()" placeholder="Search by name, email, or organization…">
            </div>
            <select x-model="filterStatus" @change="applyFilters()" class="form-input sm:w-40">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="prospect">Prospect</option>
                <option value="inactive">Inactive</option>
            </select>
            <select x-model="filterOrg" @change="applyFilters()" class="form-input sm:w-48">
                <option value="">All Organizations</option>
                <template x-for="o in orgs" :key="o.id">
                    <option :value="o.id" x-text="o.name"></option>
                </template>
            </select>
        </div>
    </div>

    {{-- Table --}}
    <div class="card p-0 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
            <p class="text-sm font-semibold text-[#1E1B4B]">
                <span x-text="filtered.length"></span> contacts
                <span x-show="filterStatus || filterOrg || search" class="text-gray-400 font-normal text-xs ml-1">— filtered</span>
            </p>
        </div>

        <div x-show="loading" class="flex items-center justify-center py-10 gap-3 text-gray-400">
            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            <span class="text-sm">Loading contacts…</span>
        </div>

        <div x-show="!loading" class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="table-head">
                        <th>Contact</th>
                        <th>Job Title</th>
                        <th>Organization</th>
                        <th>Deals</th>
                        <th>Status</th>
                        <th>Added</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="filtered.length === 0 && !loading">
                        <tr>
                            <td colspan="7" class="py-16 text-center">
                                <div class="flex justify-center text-gray-300 mb-3">
                                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                </div>
                                <p class="text-gray-400 text-sm" x-text="contacts.length === 0 ? 'No contacts yet. Add your first contact.' : 'No contacts match the filters.'"></p>
                                <button x-show="contacts.length === 0" @click="openAdd()" class="btn-primary mt-3 text-sm">Add First Contact</button>
                            </td>
                        </tr>
                    </template>
                    <template x-for="c in filtered" :key="c.id">
                        <tr class="table-row">
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-purple-700 text-xs font-bold shrink-0"
                                         x-text="initials(c)"></div>
                                    <div class="min-w-0">
                                        <p class="font-medium text-[#1E1B4B] truncate" x-text="fullName(c)"></p>
                                        <p class="text-xs text-gray-400 truncate" x-text="c.email || '—'"></p>
                                    </div>
                                </div>
                            </td>
                            <td class="text-gray-500 text-sm" x-text="c.job_title || '—'"></td>
                            <td>
                                <span x-show="c.org_name" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-gray-50 text-gray-700 text-xs font-medium border border-gray-100">
                                    <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    <span x-text="c.org_name"></span>
                                </span>
                                <span x-show="!c.org_name" class="text-gray-300 text-sm">—</span>
                            </td>
                            <td>
                                <span x-show="c.deal_count > 0"
                                      class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 text-xs font-semibold">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                                    <span x-text="c.deal_count"></span>
                                </span>
                                <span x-show="!c.deal_count" class="text-gray-300 text-sm">—</span>
                            </td>
                            <td>
                                <span :class="{
                                    'badge badge-green':  c.status === 'active',
                                    'badge badge-gray':   c.status === 'inactive',
                                    'badge badge-blue':   c.status === 'prospect',
                                }" x-text="c.status ? c.status.charAt(0).toUpperCase() + c.status.slice(1) : '—'"></span>
                            </td>
                            <td class="text-gray-400 text-sm tabular-nums"
                                x-text="c.created_at ? new Date(c.created_at).toLocaleDateString('en',{month:'short',day:'numeric',year:'numeric'}) : '—'"></td>
                            <td>
                                <div class="flex items-center gap-1.5 justify-end">
                                    <button @click="openEdit(c)"
                                            class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-700 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button @click="deleteContact(c.id)"
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
                <h3 class="font-semibold text-[#1E1B4B]" x-text="editId ? 'Edit Contact' : 'Add Contact'"></h3>
                <button @click="showModal = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">First Name *</label>
                        <input type="text" x-model="form.first_name" class="form-input" placeholder="Juan">
                    </div>
                    <div>
                        <label class="form-label">Last Name</label>
                        <input type="text" x-model="form.last_name" class="form-input" placeholder="dela Cruz">
                    </div>
                    <div>
                        <label class="form-label">Email</label>
                        <input type="email" x-model="form.email" class="form-input" placeholder="juan@email.com">
                    </div>
                    <div>
                        <label class="form-label">Phone</label>
                        <input type="text" x-model="form.phone" class="form-input" placeholder="+63 9XX XXX XXXX">
                    </div>
                    <div>
                        <label class="form-label">Job Title</label>
                        <input type="text" x-model="form.job_title" class="form-input" placeholder="e.g. IT Director, Mayor">
                    </div>
                    <div>
                        <label class="form-label">Status</label>
                        <select x-model="form.status" class="form-input">
                            <option value="active">Active</option>
                            <option value="prospect">Prospect</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="form-label">Organization</label>
                    <select x-model="form.organization_id" class="form-input">
                        <option value="">No organization</option>
                        <template x-for="o in orgs" :key="o.id">
                            <option :value="o.id" x-text="o.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="form-label">Notes</label>
                    <textarea x-model="form.notes" class="form-input" rows="2" placeholder="Any relevant notes about this contact…"></textarea>
                </div>
                <p x-show="formError" class="text-xs text-red-600 font-medium" x-text="formError"></p>
                <div class="flex justify-end gap-3 pt-1">
                    <button @click="showModal = false" class="btn-secondary">Cancel</button>
                    <button @click="saveContact()" :disabled="saving" class="btn-primary"
                            x-text="saving ? 'Saving…' : (editId ? 'Save Changes' : 'Add Contact')"></button>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function contactsModule(tenantId) {
    return {
        contacts: [], orgs: [], filtered: [],
        loading: true,
        search: '', filterStatus: '', filterOrg: '',
        showModal: false, saving: false, formError: '',
        editId: null,
        form: { first_name: '', last_name: '', email: '', phone: '', job_title: '', organization_id: '', status: 'active', notes: '' },

        async init() {
            try {
                const [cr, or] = await Promise.all([
                    fetch(`/api/contacts?tenant_id=${tenantId}`).then(r => r.json()),
                    fetch(`/api/organizations?tenant_id=${tenantId}`).then(r => r.json()),
                ]);
                this.contacts = Array.isArray(cr) ? cr : [];
                this.orgs     = Array.isArray(or) ? or : [];
            } catch(e) { this.contacts = []; this.orgs = []; }
            this.applyFilters();
            this.loading = false;
        },

        applyFilters() {
            const q = this.search.toLowerCase();
            this.filtered = this.contacts.filter(c => {
                const name  = (this.fullName(c)).toLowerCase();
                const matchQ = !q || name.includes(q) || (c.email||'').toLowerCase().includes(q) || (c.org_name||'').toLowerCase().includes(q) || (c.job_title||'').toLowerCase().includes(q);
                const matchS = !this.filterStatus || c.status === this.filterStatus;
                const matchO = !this.filterOrg || c.organization_id === this.filterOrg;
                return matchQ && matchS && matchO;
            });
        },

        fullName(c) { return [c.first_name, c.last_name].filter(Boolean).join(' ') || '—'; },
        initials(c) {
            const parts = [c.first_name, c.last_name].filter(Boolean);
            return parts.length > 0 ? parts.map(n => n[0]).join('').toUpperCase() : '?';
        },

        openAdd() {
            this.editId = null;
            this.form = { first_name: '', last_name: '', email: '', phone: '', job_title: '', organization_id: '', status: 'active', notes: '' };
            this.formError = '';
            this.showModal = true;
        },

        openEdit(c) {
            this.editId = c.id;
            this.form = {
                first_name:      c.first_name || '',
                last_name:       c.last_name || '',
                email:           c.email || '',
                phone:           c.phone || '',
                job_title:       c.job_title || '',
                organization_id: c.organization_id || '',
                status:          c.status || 'active',
                notes:           c.notes || '',
            };
            this.formError = '';
            this.showModal = true;
        },

        async saveContact() {
            if (!this.form.first_name.trim()) { this.formError = 'First name is required.'; return; }
            this.saving = true; this.formError = '';
            try {
                const url    = this.editId ? `/api/contacts/${this.editId}` : '/api/contacts';
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
                        const i = this.contacts.findIndex(c => c.id === this.editId);
                        if (i !== -1) this.contacts.splice(i, 1, data);
                    } else {
                        this.contacts.unshift(data);
                    }
                    this.applyFilters();
                    this.showModal = false;
                    this.$dispatch('show-toast', { type: 'success', message: this.editId ? 'Contact updated.' : 'Contact added.' });
                } else {
                    this.formError = data.message || 'Failed to save contact.';
                }
            } catch(e) { this.formError = 'Network error. Please try again.'; }
            finally { this.saving = false; }
        },

        async deleteContact(id) {
            if (!confirm('Delete this contact? They will also be unlinked from all deals.')) return;
            try {
                await fetch(`/api/contacts/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                });
                this.contacts = this.contacts.filter(c => c.id !== id);
                this.applyFilters();
                this.$dispatch('show-toast', { type: 'success', message: 'Contact deleted.' });
            } catch(e) {
                this.$dispatch('show-toast', { type: 'error', message: 'Failed to delete contact.' });
            }
        },
    };
}
</script>
@endsection
