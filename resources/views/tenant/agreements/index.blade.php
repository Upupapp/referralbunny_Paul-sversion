@extends('layouts.app')
@section('title', 'Agreements & Documents')

@section('nav')
    @include('tenant._nav')
@endsection

@section('topbar-actions')
    <button x-data @click="$dispatch('open-new-agreement')"
            class="btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        <span class="hidden sm:inline">New Agreement</span>
    </button>
@endsection

@section('content')
<div x-data="agreements()" x-init="init()" @open-new-agreement.window="openCreate()" class="space-y-5">

    {{-- Header card --}}
    <div class="card">
        <div class="flex items-start gap-4">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0" style="background:#EDE9FE">
                <svg class="w-5 h-5" style="color:#7B61FF" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <h1 class="text-base font-bold text-[#1E1B4B]">Agreements & Documents</h1>
                <p class="text-sm text-gray-500 mt-0.5">Require referrers to read and accept agreements before accessing their portal. Agreements can be NDAs, non-competes, confidentiality terms, or custom documents.</p>
            </div>
        </div>
    </div>

    {{-- R Bunny tip --}}
    <div class="flex gap-3 p-4 rounded-xl bg-[#F0EFFA] border border-purple-100">
        <x-r-bunny variant="helper" size="sm" :decorative="true" class="shrink-0 mt-0.5" />
        <div>
            <p class="text-sm font-semibold text-[#1E1B4B] mb-0.5">How agreements work</p>
            <p class="text-xs text-gray-500 leading-relaxed">When a referrer completes account setup or logs in for the first time after an agreement is created, they must read the full document and accept it before accessing their dashboard. Required agreements block portal access until signed. Optional agreements can be skipped.</p>
        </div>
    </div>

    {{-- Stats row --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
        <div class="card py-4">
            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Total</p>
            <p class="text-2xl font-bold text-[#1E1B4B] mt-1" x-text="agreements.length">—</p>
            <p class="text-xs text-gray-400 mt-0.5">agreements</p>
        </div>
        <div class="card py-4">
            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Required</p>
            <p class="text-2xl font-bold text-[#7B61FF] mt-1" x-text="agreements.filter(a => a.is_required && a.is_active).length">—</p>
            <p class="text-xs text-gray-400 mt-0.5">must accept</p>
        </div>
        <div class="card py-4 col-span-2 sm:col-span-1">
            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Active</p>
            <p class="text-2xl font-bold text-emerald-600 mt-1" x-text="agreements.filter(a => a.is_active).length">—</p>
            <p class="text-xs text-gray-400 mt-0.5">currently enforced</p>
        </div>
    </div>

    {{-- Loading state --}}
    <div x-show="loading" class="flex items-center justify-center py-16">
        <svg class="w-6 h-6 animate-spin text-[#7B61FF]" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
    </div>

    {{-- Error state --}}
    <div x-show="loadError && !loading" class="card text-center py-10">
        <p class="text-sm text-red-500 font-medium">Could not load agreements.</p>
        <button @click="load()" class="mt-3 text-sm text-[#7B61FF] underline">Retry</button>
    </div>

    {{-- Empty state --}}
    <div x-show="!loading && !loadError && agreements.length === 0"
         class="card flex flex-col items-center justify-center py-16 text-center">
        <div class="w-14 h-14 rounded-2xl flex items-center justify-center mb-4" style="background:#EDE9FE">
            <svg class="w-7 h-7" style="color:#7B61FF" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
        </div>
        <p class="text-sm font-semibold text-[#1E1B4B]">No agreements yet</p>
        <p class="text-xs text-gray-400 mt-1 max-w-xs">Create your first agreement to require referrers to read and accept your terms before accessing their portal.</p>
        <button @click="openCreate()" class="mt-4 btn-primary text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Create First Agreement
        </button>
    </div>

    {{-- Agreement list --}}
    <div x-show="!loading && !loadError && agreements.length > 0" class="space-y-3">
        <template x-for="agreement in agreements" :key="agreement.id">
            <div class="card hover:shadow-md transition-shadow"
                 :class="!agreement.is_active ? 'opacity-60' : ''">
                <div class="flex items-start gap-4">

                    {{-- Type badge / icon --}}
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                         :style="`background: ${typeColor(agreement.type)}`">
                        <svg class="w-5 h-5" :style="`color: ${typeIconColor(agreement.type)}`" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap mb-1">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold"
                                  :class="typeClass(agreement.type)" x-text="typeLabel(agreement.type)"></span>
                            <span x-show="agreement.version" class="text-xs text-gray-400" x-text="`v${agreement.version}`"></span>
                            <span class="text-xs font-medium"
                                  :class="agreement.is_required ? 'text-red-500' : 'text-gray-400'"
                                  x-text="agreement.is_required ? '• Required' : '• Optional'"></span>
                            <span x-show="!agreement.is_active"
                                  class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-500 font-medium">Inactive</span>
                        </div>
                        <h3 class="font-semibold text-[#1E1B4B] text-sm" x-text="agreement.title"></h3>
                        <p x-show="agreement.effective_date" class="text-xs text-gray-400 mt-0.5"
                           x-text="`Effective ${formatDate(agreement.effective_date)}`"></p>

                        {{-- Acceptance count --}}
                        <div class="flex items-center gap-3 mt-2">
                            <span class="inline-flex items-center gap-1 text-xs text-gray-500">
                                <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span x-text="`${agreement.acceptances_count ?? 0} referrer${(agreement.acceptances_count ?? 0) === 1 ? '' : 's'} accepted`"></span>
                            </span>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center gap-2 shrink-0">
                        <button @click="toggleActive(agreement)"
                                class="text-xs px-2.5 py-1 rounded-lg border transition-colors"
                                :class="agreement.is_active ? 'border-gray-200 text-gray-500 hover:border-amber-300 hover:text-amber-600' : 'border-emerald-200 text-emerald-600 hover:bg-emerald-50'"
                                :title="agreement.is_active ? 'Deactivate' : 'Activate'"
                                x-text="agreement.is_active ? 'Deactivate' : 'Activate'">
                        </button>
                        <button @click="openEdit(agreement)"
                                class="p-1.5 rounded-lg text-gray-400 hover:text-[#7B61FF] hover:bg-purple-50 transition-colors"
                                title="Edit">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </button>
                        <button @click="confirmDelete(agreement)"
                                class="p-1.5 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors"
                                title="Delete">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

{{-- ── Create / Edit Modal ──────────────────────────────────────────── --}}
<div x-show="showForm" x-cloak
     class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4"
     @keydown.escape.window="closeForm()">
    <div @click="closeForm()" class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col" @click.stop
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100">

        {{-- Modal header --}}
        <div class="flex items-center justify-between px-6 pt-5 pb-4 border-b border-gray-100 shrink-0">
            <h3 class="font-bold text-[#1E1B4B] text-base" x-text="editingId ? 'Edit Agreement' : 'New Agreement'"></h3>
            <button @click="closeForm()" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Modal body --}}
        <div class="overflow-y-auto flex-1 px-6 py-5 space-y-4">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Agreement Type</label>
                    <select x-model="form.type" class="form-input w-full text-sm">
                        <option value="custom">Custom Document</option>
                        <option value="nda">NDA (Non-Disclosure)</option>
                        <option value="non_compete">Non-Compete Agreement</option>
                        <option value="confidentiality">Confidentiality Agreement</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Version</label>
                    <input x-model="form.version" type="text" placeholder="e.g. 1.0, 2024-A"
                           class="form-input w-full text-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Title <span class="text-red-400">*</span></label>
                <input x-model="form.title" type="text" placeholder="e.g. Referral Partner Non-Disclosure Agreement"
                       class="form-input w-full text-sm" required>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Effective Date</label>
                <input x-model="form.effective_date" type="date" class="form-input w-full text-sm">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                    Agreement Text <span class="text-red-400">*</span>
                </label>
                <textarea x-model="form.content" rows="10"
                          placeholder="Paste or type the full legal agreement text here. Referrers will need to scroll through and read the entire document before they can accept it."
                          class="form-input w-full text-sm font-mono resize-y" required
                          style="min-height: 180px;"></textarea>
                <p class="text-xs text-gray-400 mt-1">Referrers must scroll to the bottom before they can accept. Plain text is recommended.</p>
            </div>

            <div class="flex flex-wrap gap-5 pt-1">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input type="checkbox" x-model="form.is_required" class="w-4 h-4 rounded accent-[#7B61FF]">
                    <div>
                        <span class="text-sm font-medium text-gray-700">Required</span>
                        <p class="text-xs text-gray-400">Referrers must accept before accessing the portal.</p>
                    </div>
                </label>
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input type="checkbox" x-model="form.is_active" class="w-4 h-4 rounded accent-[#7B61FF]">
                    <div>
                        <span class="text-sm font-medium text-gray-700">Active</span>
                        <p class="text-xs text-gray-400">Inactive agreements are not shown to referrers.</p>
                    </div>
                </label>
            </div>

            <div x-show="formError" class="text-xs text-red-600 bg-red-50 px-3 py-2 rounded-lg" x-text="formError"></div>

        </div>

        {{-- Modal footer --}}
        <div class="flex gap-3 justify-end px-6 py-4 border-t border-gray-100 shrink-0">
            <button type="button" @click="closeForm()" class="btn-secondary text-sm">Cancel</button>
            <button @click="saveAgreement()"
                    :disabled="saving || !form.title.trim() || !form.content.trim()"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white transition-all disabled:opacity-50"
                    style="background:linear-gradient(135deg,#7B61FF,#9B8BFF)">
                <svg x-show="saving" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <span x-text="saving ? 'Saving…' : (editingId ? 'Save Changes' : 'Create Agreement')"></span>
            </button>
        </div>
    </div>
</div>

{{-- ── Delete Confirm Modal ────────────────────────────────────────── --}}
<div x-show="showDelete" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     @keydown.escape.window="showDelete = false">
    <div @click="showDelete = false" class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6" @click.stop>
        <div class="w-12 h-12 rounded-2xl bg-red-50 flex items-center justify-center mb-4">
            <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
        </div>
        <h3 class="font-bold text-[#1E1B4B] mb-1">Delete Agreement?</h3>
        <p class="text-sm text-gray-500 mb-5">
            <strong x-text="deleteTarget?.title"></strong> will be permanently removed, including all acceptance records. This cannot be undone.
        </p>
        <div class="flex gap-3 justify-end">
            <button @click="showDelete = false" class="btn-secondary text-sm">Cancel</button>
            <button @click="deleteAgreement()"
                    :disabled="deleting"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white bg-red-500 hover:bg-red-600 transition-colors disabled:opacity-50">
                <svg x-show="deleting" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <span x-text="deleting ? 'Deleting…' : 'Yes, Delete'"></span>
            </button>
        </div>
    </div>
</div>{{-- end delete modal --}}

</div>{{-- end x-data="agreements()" --}}

@push('scripts')
<script>
function agreements() {
    const TENANT_ID = '{{ $tenant->id }}';
    const API       = `/api/legal-agreements?tenant_id=${TENANT_ID}`;
    const CSRF      = document.querySelector('meta[name=csrf-token]')?.content ?? '';
    const hdrs      = () => ({ 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' });

    return {
        agreements:   [],
        loading:      true,
        loadError:    false,

        // Form
        showForm:   false,
        editingId:  null,
        saving:     false,
        formError:  null,
        form: {
            type: 'custom', title: '', content: '',
            version: '', effective_date: '',
            is_required: true, is_active: true,
        },

        // Delete
        showDelete:   false,
        deleteTarget: null,
        deleting:     false,

        async init() {
            await this.load();
        },

        async load() {
            this.loading   = true;
            this.loadError = false;
            try {
                const r = await fetch(API, { headers: hdrs() });
                if (!r.ok) throw new Error();
                this.agreements = await r.json();
            } catch {
                this.loadError = true;
            } finally {
                this.loading = false;
            }
        },

        openCreate() {
            this.editingId = null;
            this.formError = null;
            this.form = { type: 'custom', title: '', content: '', version: '', effective_date: '', is_required: true, is_active: true };
            this.showForm = true;
        },

        openEdit(a) {
            this.editingId = a.id;
            this.formError = null;
            this.form = {
                type:           a.type ?? 'custom',
                title:          a.title ?? '',
                content:        a.content ?? '',
                version:        a.version ?? '',
                effective_date: a.effective_date ?? '',
                is_required:    !!a.is_required,
                is_active:      !!a.is_active,
            };
            this.showForm = true;
        },

        closeForm() {
            this.showForm  = false;
            this.editingId = null;
            this.formError = null;
        },

        async saveAgreement() {
            if (!this.form.title.trim() || !this.form.content.trim() || this.saving) return;
            this.saving    = true;
            this.formError = null;
            try {
                const url     = this.editingId ? `/api/legal-agreements/${this.editingId}` : '/api/legal-agreements';
                const method  = this.editingId ? 'PUT' : 'POST';
                const payload = { ...this.form, tenant_id: TENANT_ID };
                const r       = await fetch(url, { method, headers: hdrs(), body: JSON.stringify(payload) });
                const d       = await r.json().catch(() => ({}));
                if (!r.ok) {
                    this.formError = d.message || (d.errors ? Object.values(d.errors).flat().join(' ') : 'Could not save agreement.');
                    return;
                }
                if (this.editingId) {
                    const idx = this.agreements.findIndex(a => a.id === this.editingId);
                    if (idx >= 0) this.agreements[idx] = d;
                } else {
                    this.agreements.unshift(d);
                }
                this.closeForm();
                window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'success', message: this.editingId ? 'Agreement updated.' : 'Agreement created.' } }));
            } catch {
                this.formError = 'Network error. Please try again.';
            } finally {
                this.saving = false;
            }
        },

        async toggleActive(agreement) {
            const prev = agreement.is_active;
            agreement.is_active = !prev;
            try {
                const r = await fetch(`/api/legal-agreements/${agreement.id}`, {
                    method: 'PUT', headers: hdrs(),
                    body: JSON.stringify({ is_active: agreement.is_active }),
                });
                if (!r.ok) { agreement.is_active = prev; return; }
                const d = await r.json().catch(() => ({}));
                const idx = this.agreements.findIndex(a => a.id === agreement.id);
                if (idx >= 0) this.agreements[idx] = { ...this.agreements[idx], ...d };
                window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'success', message: agreement.is_active ? 'Agreement activated.' : 'Agreement deactivated.' } }));
            } catch {
                agreement.is_active = prev;
            }
        },

        confirmDelete(agreement) {
            this.deleteTarget = agreement;
            this.showDelete   = true;
        },

        async deleteAgreement() {
            if (!this.deleteTarget || this.deleting) return;
            this.deleting = true;
            try {
                const r = await fetch(`/api/legal-agreements/${this.deleteTarget.id}`, { method: 'DELETE', headers: hdrs() });
                if (!r.ok) return;
                this.agreements = this.agreements.filter(a => a.id !== this.deleteTarget.id);
                this.showDelete = false;
                window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'success', message: 'Agreement deleted.' } }));
            } catch {
                // silent
            } finally {
                this.deleting = false;
            }
        },

        // ── Helpers ──────────────────────────────────────────────
        typeLabel(type) {
            return { nda: 'NDA', non_compete: 'Non-Compete', confidentiality: 'Confidentiality', custom: 'Custom' }[type] ?? 'Agreement';
        },
        typeClass(type) {
            return { nda: 'bg-purple-100 text-purple-700', non_compete: 'bg-red-100 text-red-700', confidentiality: 'bg-blue-100 text-blue-700', custom: 'bg-gray-100 text-gray-600' }[type] ?? 'bg-gray-100 text-gray-600';
        },
        typeColor(type) {
            return { nda: '#EDE9FE', non_compete: '#FEE2E2', confidentiality: '#DBEAFE', custom: '#F3F4F6' }[type] ?? '#F3F4F6';
        },
        typeIconColor(type) {
            return { nda: '#7B61FF', non_compete: '#DC2626', confidentiality: '#3B82F6', custom: '#6B7280' }[type] ?? '#6B7280';
        },
        formatDate(str) {
            if (!str) return '';
            try { return new Date(str).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }); }
            catch { return str; }
        },
    };
}
</script>
@endpush

@endsection
