@extends('layouts.app')
@section('title', 'Search')
@section('nav') @include('platform._nav') @endsection

@section('content')
<div class="space-y-5" x-data="searchPage()" x-init="init()">

    {{-- Search bar --}}
    <div class="card">
        <div class="flex flex-col sm:flex-row gap-3 items-center">
            <div class="search-group flex-1">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" x-model="query" @input.debounce.300ms="runSearch()"
                       @keydown.enter="runSearch()" autofocus
                       placeholder="Search tenants, invoices, promos… or type a command">
            </div>
            <button type="button" @click="saveCurrentSearch()" x-show="query && results.length" style="display:none" class="btn-secondary text-sm shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                Save Search
            </button>
        </div>

        {{-- Entity type pills --}}
        <div class="mt-4 pt-3 border-t border-gray-100">
            <div class="flex items-center gap-1.5 flex-wrap">
                @php $typeLabels = [''=> 'All','tenant'=>'Tenants','invoice'=>'Invoices','payment'=>'Payments','promo_code'=>'Promo Codes','promotion'=>'Promotions','approval_request'=>'Approvals','lead'=>'Leads','reseller'=>'Referrers']; @endphp
                @foreach($typeLabels as $type => $label)
                <button @click="filters.type = '{{ $type }}'; offset = 0; runSearch()"
                        :class="filters.type === '{{ $type }}' ? 'tab-active' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                        class="px-3 py-1.5 rounded-xl text-xs font-medium transition-colors whitespace-nowrap">
                    {{ $label }}
                </button>
                @endforeach
            </div>
        </div>

        {{-- Secondary filters --}}
        <div class="mt-3 flex flex-col sm:flex-row gap-3 items-center">
            <select x-model="filters.status" @change="runSearch()" class="form-input sm:w-44">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="trial">Trial</option>
                <option value="inactive">Inactive</option>
                <option value="open">Open / Unpaid</option>
                <option value="paid">Paid</option>
                <option value="past_due">Past Due</option>
                <option value="pending">Pending</option>
                <option value="failed">Failed</option>
                <option value="suspended">Suspended</option>
            </select>
            <input type="date" x-model="filters.from" @change="runSearch()"
                   class="form-input sm:w-40" aria-label="From date">
            <input type="date" x-model="filters.to" @change="runSearch()"
                   class="form-input sm:w-40" aria-label="To date">
            <button type="button" @click="clearFilters()"
                    x-show="filters.status || filters.from || filters.to || filters.type"
                    class="btn-secondary shrink-0">
                Clear filters
            </button>
        </div>
    </div>

    {{-- Command result --}}
    <template x-if="command">
        <div class="card flex items-center gap-4 bg-purple-50 border border-purple-200">
            <div class="w-10 h-10 rounded-xl bg-purple-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <div class="flex-1">
                <p class="font-semibold text-[#1E1B4B] text-sm" x-text="'Command: ' + query"></p>
                <p class="text-xs text-purple-600 mt-0.5" x-text="command.action?.replace(/_/g,' ').toUpperCase()"></p>
            </div>
            <div class="flex gap-2 shrink-0">
                <template x-if="command.action === 'navigate'">
                    <a :href="command.url" class="btn-primary text-xs">Go →</a>
                </template>
                <template x-if="command.sensitive">
                    <button type="button" @click="confirmAction(command)" class="btn-primary text-xs">Execute</button>
                </template>
                <template x-if="command.filter">
                    <button type="button" @click="applyCommandFilter(command)" class="btn-primary text-xs">Apply Filter</button>
                </template>
            </div>
        </div>
    </template>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-5">

        {{-- Sidebar: saved searches + favorites --}}
        <div class="space-y-4">

            <div class="card">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-[#1E1B4B] text-sm">Saved Searches</h3>
                </div>
                <div class="space-y-1.5">
                    <template x-for="s in savedSearches" :key="s.id">
                        <button type="button" @click="loadSaved(s)"
                                class="w-full text-left px-3 py-2 rounded-xl hover:bg-[#F0EFFA] transition-colors">
                            <div class="flex items-center gap-2">
                                <svg x-show="s.is_pinned" class="w-3 h-3 text-purple-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                <span class="text-sm text-gray-700 truncate" x-text="s.name"></span>
                            </div>
                            <p class="text-xs text-gray-400 truncate pl-5" x-text="s.query"></p>
                        </button>
                    </template>
                    <p x-show="savedSearches.length === 0" class="text-xs text-gray-400 text-center py-2">No saved searches</p>
                </div>
            </div>

            <div class="card">
                <h3 class="font-semibold text-[#1E1B4B] text-sm mb-3">Recent Searches</h3>
                <div class="space-y-1">
                    <template x-for="r in recentSearches" :key="r.query + r.created_at">
                        <button type="button" @click="query = r.query; runSearch()"
                                class="w-full text-left px-3 py-1.5 rounded-xl hover:bg-[#F0EFFA] transition-colors flex items-center justify-between">
                            <span class="text-sm text-gray-600 truncate" x-text="r.query"></span>
                            <span class="text-xs text-gray-400 ml-2 shrink-0" x-text="r.result_count + ' results'"></span>
                        </button>
                    </template>
                    <p x-show="recentSearches.length === 0" class="text-xs text-gray-400 text-center py-2">No recent searches</p>
                </div>
            </div>
        </div>

        {{-- Main results --}}
        <div class="lg:col-span-3 space-y-3">

            {{-- Results header --}}
            <div class="flex items-center justify-between" x-show="query">
                <p class="text-sm text-gray-500">
                    <span x-text="total"></span> results
                    <span x-show="query"> for "<span class="font-medium text-[#1E1B4B]" x-text="query"></span>"</span>
                </p>
                <div class="flex items-center gap-2">
                    <label class="flex items-center gap-1.5 text-xs text-gray-500 cursor-pointer">
                        <input type="checkbox" x-model="bulkMode" class="rounded accent-purple-600">
                        Bulk select
                    </label>
                    <template x-if="bulkMode && selected.length > 0">
                        <div class="flex gap-2">
                            <button type="button" @click="bulkApprove()" class="btn-primary text-xs py-1">Approve (<span x-text="selected.length"></span>)</button>
                            <button type="button" @click="bulkSuspend()" class="btn-danger text-xs py-1">Suspend (<span x-text="selected.length"></span>)</button>
                        </div>
                    </template>
                </div>
            </div>

            {{-- No index warning --}}
            <div x-show="!hasIndex && query" style="display:none" class="p-4 bg-orange-50 border border-orange-200 rounded-2xl text-orange-800 text-sm flex items-center gap-3">
                <svg class="w-5 h-5 shrink-0 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                Search index is empty. Run <code class="bg-orange-100 px-1 rounded">php artisan search:reindex</code> to build the index, then search again.
                @if(Auth::guard('web')->check())
                <button type="button" @click="triggerReindex()" :disabled="reindexing" class="btn-primary text-xs ml-auto" x-text="reindexing ? 'Indexing...' : 'Reindex Now'"></button>
                @endif
            </div>

            {{-- Loading --}}
            <div x-show="loading" class="card text-center py-10 text-gray-400">
                <svg class="w-6 h-6 mx-auto animate-spin text-purple-400 mb-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                Searching…
            </div>

            {{-- Empty state --}}
            <div x-show="!loading && query && results.length === 0 && hasIndex" class="card text-center py-12">
                <svg class="w-10 h-10 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <p class="font-medium text-gray-600">No results for "<span x-text="query"></span>"</p>
                <p class="text-sm text-gray-400 mt-1">Try different keywords or check the spelling.</p>
            </div>

            {{-- Results list --}}
            <template x-for="result in results" :key="result.entity_type + result.id">
                <div class="card hover:shadow-md transition-shadow group"
                     :class="selected.includes(result.id) ? 'ring-2 ring-purple-400' : ''">
                    <div class="flex items-start gap-3">

                        {{-- Checkbox (bulk mode) --}}
                        <template x-if="bulkMode">
                            <input type="checkbox" :value="result.id"
                                   @change="toggleSelect(result.id)"
                                   :checked="selected.includes(result.id)"
                                   class="mt-1 rounded accent-purple-600 shrink-0">
                        </template>

                        {{-- Entity icon --}}
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center text-base shrink-0"
                             :class="{
                                'bg-purple-100': result.entity_type === 'tenant',
                                'bg-blue-100':   result.entity_type === 'invoice' || result.entity_type === 'payment',
                                'bg-emerald-100':result.entity_type === 'promo_code' || result.entity_type === 'promotion',
                                'bg-orange-100': result.entity_type === 'approval_request',
                                'bg-gray-100':   !['tenant','invoice','payment','promo_code','promotion','approval_request'].includes(result.entity_type),
                             }"
                             x-text="entityEmoji(result.entity_type)">
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <a :href="result.url" class="font-semibold text-[#1E1B4B] hover:text-purple-700 transition-colors"
                                   x-html="result.title_highlight"></a>
                                <span class="badge badge-gray text-xs" x-text="result.entity_label"></span>
                                <span :class="{
                                    'badge badge-green':  ['active','paid','healthy','approved'].includes(result.status),
                                    'badge badge-blue':   result.status === 'trial',
                                    'badge badge-orange': ['open','pending','expiring','past_due'].includes(result.status),
                                    'badge badge-red':    ['failed','suspended','canceled','at_risk'].includes(result.status),
                                    'badge badge-gray':   true,
                                }" x-text="result.status"></span>
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5" x-text="result.description"></p>
                            <p class="text-xs text-gray-300 mt-0.5" x-text="result.last_activity ? new Date(result.last_activity).toLocaleDateString() : ''"></p>
                        </div>

                        {{-- Quick actions --}}
                        <div class="flex gap-1.5 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                            <template x-for="action in result.quick_actions" :key="action.action">
                                <button type="button" @click="action.sensitive ? confirmAction({...action, entity_type: result.entity_type, entity_id: result.id, entity_title: result.title}) : navigateTo(result.url)"
                                        :class="action.sensitive ? 'btn-secondary' : 'btn-primary'"
                                        class="text-xs py-1 px-2.5"
                                        x-text="action.label">
                                </button>
                            </template>
                            <button type="button" @click="toggleFavorite(result)"
                                    class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-purple-600 transition-colors"
                                    title="Add to favorites">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            {{-- Pagination --}}
            <div class="flex items-center justify-between" x-show="total > limit">
                <button type="button" @click="prevPage()" :disabled="offset === 0" class="btn-secondary text-sm">← Previous</button>
                <span class="text-sm text-gray-500" x-text="'Showing ' + (offset+1) + '–' + Math.min(offset+limit, total) + ' of ' + total"></span>
                <button type="button" @click="nextPage()" :disabled="offset + limit >= total" class="btn-secondary text-sm">Next →</button>
            </div>
        </div>
    </div>

    {{-- Confirmation modal --}}
    <div x-show="confirmModal.open" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
         role="dialog" aria-modal="true"
         @keydown.escape.window="confirmModal.open = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm" @click.stop>
            <div class="p-6 space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-orange-100 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-[#1E1B4B]" x-text="confirmModal.title"></p>
                        <p class="text-sm text-gray-500" x-text="confirmModal.description"></p>
                    </div>
                </div>
                <div>
                    <label class="form-label">Reason (optional)</label>
                    <input type="text" x-model="confirmModal.reason" class="form-input" placeholder="Reason for this action">
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" @click="confirmModal.open = false" class="btn-secondary">Cancel</button>
                    <button type="button" @click="executeConfirmed()" :disabled="actionLoading" class="btn-primary"
                            x-text="actionLoading ? 'Executing...' : 'Confirm'"></button>
                </div>
            </div>
        </div>
    </div>

    {{-- Save search modal --}}
    <div x-show="saveModal" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
         role="dialog" aria-modal="true"
         @keydown.escape.window="saveModal = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm" @click.stop>
            <div class="p-6 space-y-4">
                <h3 class="font-semibold text-[#1E1B4B]">Save Search</h3>
                <div><label class="form-label">Name *</label><input type="text" x-model="saveName" class="form-input" placeholder="e.g. Unpaid invoices"></div>
                <div class="flex items-center gap-2"><input type="checkbox" x-model="savePin" class="rounded accent-purple-600"><label class="text-sm text-gray-600">Pin to sidebar</label></div>
                <div class="flex justify-end gap-3">
                    <button type="button" @click="saveModal = false" class="btn-secondary">Cancel</button>
                    <button type="button" @click="doSaveSearch()" class="btn-primary">Save</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Bulk confirm modal --}}
    <div x-show="bulkModal.open" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
         role="dialog" aria-modal="true"
         @keydown.escape.window="bulkModal.open = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm" @click.stop>
            <div class="p-6 space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-orange-100 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-[#1E1B4B]" x-text="bulkModal.label + ' ' + bulkModal.count + ' item' + (bulkModal.count !== 1 ? 's' : '') + '?'"></p>
                        <p class="text-sm text-gray-500">This action will be applied to all selected items.</p>
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" @click="bulkModal.open = false" class="btn-secondary">Cancel</button>
                    <button type="button" @click="executeBulk()" class="btn-primary" x-text="'Confirm ' + bulkModal.label"></button>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function searchPage() {
    return {
        query: '{{ request('q', '') }}',
        results: [], total: 0, loading: false, hasIndex: true,
        filters: { type: '', status: '', from: '', to: '' },
        recentSearches: [], savedSearches: [],
        command: null, selected: [], bulkMode: false,
        offset: 0, limit: 20,
        reindexing: false,
        confirmModal: { open: false, title: '', description: '', action: null, entity_type: '', entity_id: '', reason: '' },
        actionLoading: false,
        saveModal: false, saveName: '', savePin: false,
        bulkModal: { open: false, action: '', label: '', count: 0 },

        async init() {
            try {
                const [recentRes, savedRes] = await Promise.all([
                    fetch('/api/search/recent'),
                    fetch('/api/search/saved'),
                ]);
                this.recentSearches = recentRes.ok  ? await recentRes.json()  : [];
                this.savedSearches  = savedRes.ok   ? await savedRes.json()   : [];
            } catch (e) {
                this.recentSearches = [];
                this.savedSearches  = [];
            }
            if (this.query) this.runSearch();
        },

        async runSearch() {
            if (!this.query.trim()) { this.results = []; this.total = 0; this.command = null; return; }
            this.loading = true;
            try {
                const params = new URLSearchParams({ q: this.query, limit: this.limit, offset: this.offset });
                if (this.filters.type)   params.set('type',   this.filters.type);
                if (this.filters.status) params.set('status', this.filters.status);
                if (this.filters.from)   params.set('from',   this.filters.from);
                if (this.filters.to)     params.set('to',     this.filters.to);
                const res  = await fetch('/api/search?' + params);
                const data = await res.json();
                this.results  = data.results  || [];
                this.total    = data.total    || 0;
                this.command  = data.command  || null;
                this.hasIndex = data.has_index !== false;
            } catch (e) {
                this.results = [];
                this.$dispatch('show-toast', { type: 'error', message: 'Search failed. Please try again.' });
            } finally {
                this.loading = false;
            }
        },

        prevPage() { this.offset = Math.max(0, this.offset - this.limit); this.runSearch(); },
        nextPage() { this.offset += this.limit; this.runSearch(); },

        clearFilters() { this.filters = { type: '', status: '', from: '', to: '' }; this.runSearch(); },

        loadSaved(s) { this.query = s.query; this.filters = s.filters_json || {}; this.offset = 0; this.runSearch(); },

        applyCommandFilter(cmd) { if (cmd.filter) { Object.assign(this.filters, cmd.filter); this.runSearch(); } },

        confirmAction(action) {
            this.confirmModal = {
                open: true,
                title: action.label + (action.entity_title ? ': ' + action.entity_title : ''),
                description: 'This action requires confirmation.',
                action: action.action,
                entity_type: action.entity_type || '',
                entity_id: action.entity_id || '',
                reason: '',
            };
        },

        async executeConfirmed() {
            this.actionLoading = true;
            try {
                const res = await fetch('/api/search/actions', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({
                        action: this.confirmModal.action,
                        entity_type: this.confirmModal.entity_type,
                        entity_id: this.confirmModal.entity_id,
                        reason: this.confirmModal.reason,
                    }),
                });
                const data = await res.json();
                this.confirmModal.open = false;
                if (data.success) {
                    this.$dispatch('show-toast', { type: 'success', message: data.message || 'Action completed.' });
                    await this.runSearch();
                } else {
                    this.$dispatch('show-toast', { type: 'error', message: data.message || 'Action failed.' });
                }
            } catch (e) {
                this.$dispatch('show-toast', { type: 'error', message: 'Network error. Please try again.' });
            } finally { this.actionLoading = false; }
        },

        async triggerReindex() {
            this.reindexing = true;
            try {
                const res  = await fetch('/api/search/reindex', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } });
                const data = await res.json();
                this.$dispatch('show-toast', { type: res.ok ? 'success' : 'error', message: data.message || (res.ok ? 'Reindex complete.' : 'Reindex failed.') });
                if (res.ok) await this.runSearch();
            } catch (e) {
                this.$dispatch('show-toast', { type: 'error', message: 'Reindex failed. Please try again.' });
            } finally {
                this.reindexing = false;
            }
        },

        toggleSelect(id) {
            const i = this.selected.indexOf(id);
            if (i === -1) this.selected.push(id); else this.selected.splice(i, 1);
        },

        bulkApprove() {
            this.bulkModal = { open: true, action: 'approve', label: 'Approve', count: this.selected.length };
        },

        bulkSuspend() {
            this.bulkModal = { open: true, action: 'suspend_tenant', label: 'Suspend', count: this.selected.length };
        },

        async executeBulk() {
            const { action } = this.bulkModal;
            const entityType = action === 'approve' ? 'approval_request' : 'tenant';
            this.bulkModal.open = false;
            const ids = [...this.selected];
            let succeeded = 0, failed = 0;
            for (const id of ids) {
                try {
                    const res = await fetch('/api/search/actions', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: JSON.stringify({ action, entity_type: entityType, entity_id: id }),
                    });
                    res.ok ? succeeded++ : failed++;
                } catch (e) { failed++; }
            }
            this.selected = [];
            if (failed === 0) {
                this.$dispatch('show-toast', { type: 'success', message: `${succeeded} item${succeeded !== 1 ? 's' : ''} processed successfully.` });
            } else {
                this.$dispatch('show-toast', { type: 'error', message: `${succeeded} succeeded, ${failed} failed. Please check the results.` });
            }
            await this.runSearch();
        },

        async toggleFavorite(result) {
            try {
                const res = await fetch('/api/search/favorites', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ entity_type: result.entity_type, entity_id: result.id, label: result.title, url: result.url }),
                });
                if (!res.ok) throw new Error();
                this.$dispatch('show-toast', { type: 'success', message: 'Added to favorites.' });
            } catch (e) {
                this.$dispatch('show-toast', { type: 'error', message: 'Could not update favorites. Please try again.' });
            }
        },

        saveCurrentSearch() { this.saveModal = true; this.saveName = ''; },

        async doSaveSearch() {
            if (!this.saveName) return;
            try {
                const res = await fetch('/api/search/saved', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ name: this.saveName, query: this.query, filters: this.filters, is_pinned: this.savePin }),
                });
                if (!res.ok) {
                    const d = await res.json().catch(() => ({}));
                    this.$dispatch('show-toast', { type: 'error', message: d.message || 'Could not save search. Please try again.' });
                    return;
                }
                this.saveModal = false;
                const savedRes = await fetch('/api/search/saved');
                this.savedSearches = savedRes.ok ? await savedRes.json() : this.savedSearches;
                this.$dispatch('show-toast', { type: 'success', message: 'Search saved.' });
            } catch (e) {
                this.$dispatch('show-toast', { type: 'error', message: 'Network error. Could not save search.' });
            }
        },

        navigateTo(url) { window.location.href = url; },

        entityEmoji(type) {
            const map = { tenant: '🏢', invoice: '📄', payment: '💳', promo_code: '🏷️', promotion: '✨', approval_request: '⏳', subscription: '🔄', lead: '👤', reseller: '👥', notification: '🔔' };
            return map[type] || '📌';
        },
    }
}
</script>
@endsection
