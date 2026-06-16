@extends('layouts.app')
@section('title', 'Referrers')
@section('nav')
    @include('tenant._nav')
@endsection

@section('topbar-actions')
    <button type="button" x-data @click="$dispatch('open-add-reseller')" class="btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        <span class="hidden sm:inline">Invite Referrer</span>
    </button>
@endsection

@section('content')
<div class="space-y-5" x-data="resellersPage('{{ $tenant->id }}')" x-init="init()" @open-add-reseller.window="showAdd = true">

    {{-- Search + filter --}}
    <div class="card space-y-3">
        <div class="search-group">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" x-model="search" @input.debounce="filter()" placeholder="Search referrers…" class="w-full bg-transparent outline-none text-sm text-gray-700 placeholder-gray-400">
            <button type="button" x-show="search" @click="search=''; filter()" class="text-gray-400 hover:text-gray-600 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="filter-bar">
            <button type="button" @click="filterStatus=''; filter()" :class="filterStatus === '' ? 'filter-pill active' : 'filter-pill'">All</button>
            <button type="button" @click="filterStatus='invited'; filter()" :class="filterStatus === 'invited' ? 'filter-pill active' : 'filter-pill'">
                <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span> Invited
            </button>
            <button type="button" @click="filterStatus='active'; filter()" :class="filterStatus === 'active' ? 'filter-pill active' : 'filter-pill'">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
            </button>
            <button type="button" @click="filterStatus='nda_signed'; filter()" :class="filterStatus === 'nda_signed' ? 'filter-pill active' : 'filter-pill'">
                <span class="w-1.5 h-1.5 rounded-full bg-violet-500"></span> NDA Signed
            </button>
        </div>
    </div>

    <div class="card p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead><tr class="table-head"><th>Referrer</th><th>Territory</th><th>Leads</th><th>Closed Value</th><th>Score</th><th>Status</th></tr></thead>
                <tbody>
                    <template x-if="loading"><tr><td colspan="6" class="py-10 text-center text-gray-400">Loading...</td></tr></template>
                    <template x-if="!loading && filtered.length === 0"><tr><td colspan="6" class="py-10 text-center text-gray-400">No referrers found</td></tr></template>
                    <template x-for="r in filtered" :key="r.id">
                        <tr class="table-row">
                            <td>
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-xl bg-purple-100 flex items-center justify-center text-purple-700 font-bold text-xs shrink-0" x-text="r.name.slice(0,2).toUpperCase()"></div>
                                    <div><p class="font-medium text-[#1E1B4B] text-sm" x-text="r.name"></p><p class="text-xs text-gray-400" x-text="r.email"></p></div>
                                </div>
                            </td>
                            <td class="text-gray-500 text-sm" x-text="r.territory || '—'"></td>
                            <td class="font-medium" x-text="r.assigned_leads || 0"></td>
                            <td class="font-semibold text-sm" x-text="r.closed_value ? '₱' + Number(r.closed_value).toLocaleString() : '—'"></td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden max-w-[60px]">
                                        <div class="h-full rounded-full bg-gradient-to-r from-purple-500 to-pink-500" :style="'width:' + (r.performance_score || 0) + '%'"></div>
                                    </div>
                                    <span class="text-xs text-gray-500" x-text="(r.performance_score || 0) + '%'"></span>
                                </div>
                            </td>
                            <td><span :class="{'badge':true,'badge-green':r.status==='active'||r.status==='nda_signed','badge-blue':r.status==='invited','badge-gray':true}" x-text="r.status?.replace('_',' ')"></span></td>
                            <td>
                                <template x-if="r.status === 'invited'">
                                    <button type="button" @click="sendInvite(r)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold text-white transition-colors"
                                            style="background:#7B61FF"
                                            onmouseover="this.style.background='#5B45DF'" onmouseout="this.style.background='#7B61FF'"
                                            :disabled="r._inviting"
                                            :class="r._inviting ? 'opacity-60 cursor-not-allowed' : ''">
                                        <svg x-show="r._inviting" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                                        <svg x-show="!r._inviting" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                        <span x-text="r._inviting ? 'Sending…' : 'Send Invite'"></span>
                                    </button>
                                </template>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Add Reseller Modal --}}
    <div x-show="showAdd" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-[#1E1B4B]">Invite Referrer</h3>
                <button type="button" @click="showAdd = false" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <div class="p-6 space-y-4">
                <div><label class="form-label">Full Name *</label><input type="text" x-model="form.name" class="form-input" placeholder="Referrer full name"></div>
                <div><label class="form-label">Email *</label><input type="email" x-model="form.email" class="form-input" placeholder="referrer@email.com"></div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="form-label">Phone</label><input type="text" x-model="form.phone" class="form-input" placeholder="+63 9XX XXX XXXX"></div>
                    <div><label class="form-label">Territory</label><input type="text" x-model="form.territory" class="form-input" placeholder="e.g. Metro Manila"></div>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" @click="showAdd = false" class="btn-secondary">Cancel</button>
                    <button type="button" @click="addReseller()" :disabled="saving" class="btn-primary" x-text="saving ? 'Saving...' : 'Send Invite'"></button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function resellersPage(tenantId) {
    return {
        resellers: [], filtered: [], loading: true, showAdd: false, saving: false,
        search: '', filterStatus: '',
        form: { name:'', email:'', phone:'', territory:'' },

        async init() {
            const res = await fetch(`/api/resellers?tenant_id=${tenantId}`);
            const d = await res.json(); this.resellers = Array.isArray(d) ? d : (d.data || []);
            this.filtered  = this.resellers;
            this.loading   = false;
        },

        filter() {
            this.filtered = this.resellers.filter(r => {
                const matchSearch = !this.search || r.name.toLowerCase().includes(this.search.toLowerCase()) || r.email.toLowerCase().includes(this.search.toLowerCase());
                const matchStatus = !this.filterStatus || r.status === this.filterStatus;
                return matchSearch && matchStatus;
            });
        },

        async addReseller() {
            if (!this.form.name || !this.form.email) return;
            this.saving = true;
            try {
                await fetch('/api/resellers', {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ ...this.form, tenant_id: tenantId, status: 'invited' }),
                });
                this.showAdd = false;
                this.form = { name:'', email:'', phone:'', territory:'' };
                await this.init();
            } finally { this.saving = false; }
        },

        async sendInvite(reseller) {
            reseller._inviting = true;
            this.$nextTick(() => {});
            try {
                const csrf = document.querySelector('meta[name=csrf-token]').content;
                const res  = await fetch(`/api/resellers/${reseller.id}/send-invite`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ tenant_id: tenantId }),
                });
                const data = await res.json();
                if (res.ok) {
                    this.$dispatch('show-toast', { type: 'success', message: `Invitation sent to ${reseller.email}` });
                } else {
                    this.$dispatch('show-toast', { type: 'error', message: data.message || 'Failed to send invite.' });
                }
            } catch (e) {
                this.$dispatch('show-toast', { type: 'error', message: 'Network error. Try again.' });
            } finally {
                reseller._inviting = false;
            }
        },
    }
}
</script>
@endsection
