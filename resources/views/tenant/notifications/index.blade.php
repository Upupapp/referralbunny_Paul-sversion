@extends('layouts.app')
@section('title', 'Notifications')

@section('nav')
    @include('tenant._nav')
@endsection

@section('topbar-actions')
    <button id="markAllReadBtn" class="btn-secondary text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <span class="hidden sm:inline">Mark all read</span>
    </button>
@endsection

@section('content')
<div x-data="notificationCenter()" x-init="init()" class="space-y-4">

    {{-- Header + Filters --}}
    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-[#1E1B4B] font-bold text-lg">Notifications</h2>
                <p class="text-gray-400 text-sm mt-0.5">
                    <span x-text="unreadCount">0</span> unread notifications
                </p>
            </div>
        </div>

        {{-- Filter tabs --}}
        <div class="flex flex-wrap gap-2 mt-4">
            <template x-for="tab in tabs" :key="tab.key">
                <button
                    @click="activeTab = tab.key; page = 1; load()"
                    :class="activeTab === tab.key
                        ? 'bg-[#7B61FF] text-white shadow-sm'
                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                    class="px-3 py-1.5 rounded-full text-xs font-medium transition-all"
                    x-text="tab.label"
                ></button>
            </template>
        </div>

        {{-- Category + Priority filters --}}
        <div class="filter-bar mt-3">
            <label class="filter-pill" :class="filterCategory !== '' ? 'active' : ''">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/></svg>
                <select x-model="filterCategory" @change="page = 1; load()">
                    <option value="">All Categories</option>
                    <option value="deal_pipeline">Deals</option>
                    <option value="commission">Commission</option>
                    <option value="reseller_referrer">Referrers</option>
                    <option value="import_export">Imports</option>
                    <option value="billing_subscription">Billing</option>
                    <option value="auth_security">Security</option>
                    <option value="system">System</option>
                    <option value="dashboard_briefing">Briefing</option>
                    <option value="team">Team</option>
                </select>
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>

            <label class="filter-pill" :class="filterPriority !== '' ? 'active' : ''">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <select x-model="filterPriority" @change="page = 1; load()">
                    <option value="">All Priorities</option>
                    <option value="urgent">Urgent</option>
                    <option value="high">High</option>
                    <option value="normal">Normal</option>
                    <option value="low">Low</option>
                </select>
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>

            <button x-show="filterCategory || filterPriority"
                    @click="filterCategory=''; filterPriority=''; page=1; load()"
                    class="filter-pill !border-red-200 !text-red-500 hover:!bg-red-50">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                Clear
            </button>
        </div>
    </div>

    {{-- Notification list --}}
    <div class="card p-0 overflow-hidden">

        {{-- Loading --}}
        <template x-if="loading">
            <div class="divide-y divide-gray-50">
                <template x-for="i in [1,2,3,4,5]" :key="i">
                    <div class="flex items-start gap-3 px-5 py-4 animate-pulse">
                        <div class="w-2.5 h-2.5 rounded-full bg-gray-200 mt-1.5 shrink-0"></div>
                        <div class="flex-1 space-y-2">
                            <div class="h-3 bg-gray-200 rounded w-2/3"></div>
                            <div class="h-3 bg-gray-100 rounded w-1/2"></div>
                        </div>
                    </div>
                </template>
            </div>
        </template>

        {{-- Empty state --}}
        <template x-if="!loading && items.length === 0">
            <div class="flex flex-col items-center justify-center py-20 text-center px-6">
                <div class="w-16 h-16 rounded-2xl bg-[#EDE9FE] flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-[#7B61FF]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                </div>
                <h3 class="text-[#1E1B4B] font-semibold text-base">No notifications</h3>
                <p class="text-gray-400 text-sm mt-1 max-w-xs">
                    <template x-if="activeTab !== 'all'">
                        <span>No notifications in this filter. <button @click="activeTab='all'; load()" class="text-purple-600 underline">View all</button></span>
                    </template>
                    <template x-if="activeTab === 'all'">
                        <span>Important updates will appear here as they happen.</span>
                    </template>
                </p>
            </div>
        </template>

        {{-- Notification rows --}}
        <template x-if="!loading && items.length > 0">
            <div class="divide-y divide-gray-50">
                <template x-for="n in items" :key="n.id">
                    <div
                        class="flex items-start gap-3.5 px-5 py-4 hover:bg-gray-50 transition-colors group"
                        :class="!n.is_read ? 'bg-purple-50/30' : ''"
                    >
                        {{-- Priority dot --}}
                        <span
                            class="w-2.5 h-2.5 rounded-full mt-1.5 shrink-0"
                            :class="{
                                'bg-red-500':     ['critical','urgent'].includes(n.priority),
                                'bg-orange-400':  n.priority === 'high',
                                'bg-[#7B61FF]':   ['normal','medium'].includes(n.priority),
                                'bg-gray-300':    n.priority === 'low',
                            }"
                        ></span>

                        {{-- Content --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-[#1E1B4B] leading-snug"
                                       :class="!n.is_read ? 'font-bold' : ''"
                                       x-text="n.title || n.message"
                                    ></p>
                                    <p class="text-sm text-gray-500 mt-0.5 leading-relaxed line-clamp-2"
                                       x-show="n.title"
                                       x-text="n.message"
                                    ></p>
                                    <div class="flex items-center gap-3 mt-1.5">
                                        <span class="text-[10px] text-gray-400" x-text="n.created_ago"></span>
                                        <span
                                            class="text-[10px] font-medium px-1.5 py-0.5 rounded-full"
                                            :class="{
                                                'bg-red-100 text-red-600':     ['critical','urgent'].includes(n.priority),
                                                'bg-orange-100 text-orange-600': n.priority === 'high',
                                                'bg-purple-100 text-purple-600': ['normal','medium'].includes(n.priority),
                                                'bg-gray-100 text-gray-500':   n.priority === 'low',
                                            }"
                                            x-show="['urgent','critical','high'].includes(n.priority)"
                                            x-text="n.priority.charAt(0).toUpperCase() + n.priority.slice(1)"
                                        ></span>
                                        <span class="text-[10px] text-gray-400 capitalize" x-text="(n.category ?? '').replace(/_/g,' ')"></span>
                                    </div>
                                </div>

                                {{-- Actions --}}
                                <div class="flex items-center gap-1 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button
                                        x-show="!n.is_read"
                                        @click.stop="markRead(n)"
                                        title="Mark as read"
                                        class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-[#7B61FF] transition-colors"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </button>
                                    <button
                                        @click.stop="archive(n)"
                                        title="Archive"
                                        class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition-colors"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            {{-- CTA button --}}
                            <div class="mt-2.5" x-show="n.action_url && n.action_label">
                                <a
                                    :href="n.action_url"
                                    @click.prevent="storeAndNavigate(n)"
                                    class="inline-flex items-center gap-1.5 text-xs font-medium text-[#7B61FF] hover:text-purple-800 transition-colors"
                                    x-text="n.action_label"
                                ></a>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </template>

        {{-- Pagination --}}
        <template x-if="!loading && totalPages > 1">
            <div class="flex items-center justify-between px-5 py-3 border-t border-gray-100 bg-gray-50">
                <button
                    @click="page--; load()"
                    :disabled="page <= 1"
                    class="text-xs text-gray-500 hover:text-[#7B61FF] disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                >
                    ← Previous
                </button>
                <span class="text-xs text-gray-400">
                    Page <span x-text="page"></span> of <span x-text="totalPages"></span>
                    · <span x-text="total"></span> total
                </span>
                <button
                    @click="page++; load()"
                    :disabled="page >= totalPages"
                    class="text-xs text-gray-500 hover:text-[#7B61FF] disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                >
                    Next →
                </button>
            </div>
        </template>
    </div>

</div>

@push('scripts')
<script>
function notificationCenter() {
    const CSRF    = document.querySelector('meta[name=csrf-token]')?.content ?? '';
    const BASE    = '/api/notifications';
    const WEB_BASE = '{{ url("tenant/" . $tenant->id . "/notifications") }}';
    const PER_PAGE = 20;

    return {
        items:          [],
        total:          0,
        unreadCount:    0,
        page:           1,
        loading:        false,
        activeTab:      'all',
        filterCategory: '',
        filterPriority: '',

        tabs: [
            { key: 'all',      label: 'All' },
            { key: 'unread',   label: 'Unread' },
            { key: 'high',     label: 'Action Required' },
            { key: 'security', label: 'Security' },
        ],

        get totalPages() {
            return Math.max(1, Math.ceil(this.total / PER_PAGE));
        },

        async init() {
            await this.load();
            document.getElementById('markAllReadBtn')?.addEventListener('click', () => this.markAllRead());
        },

        async load() {
            this.loading = true;
            try {
                const params = new URLSearchParams({
                    limit:  PER_PAGE,
                    offset: (this.page - 1) * PER_PAGE,
                });

                if (this.activeTab === 'unread')   params.set('unread',   'true');
                if (this.activeTab === 'high')      params.set('priority', 'high');
                if (this.activeTab === 'security')  params.set('category', 'auth_security');
                if (this.filterCategory) params.set('category', this.filterCategory);
                if (this.filterPriority) params.set('priority', this.filterPriority);

                const r    = await fetch(`${BASE}/mine?${params}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await r.json();
                this.items       = data.items        ?? [];
                this.total       = data.total        ?? 0;
                this.unreadCount = data.unread_count ?? 0;
            } finally {
                this.loading = false;
            }
        },

        async markRead(n) {
            if (n.is_read) return;
            n.is_read = true;
            this.unreadCount = Math.max(0, this.unreadCount - 1);
            window.dispatchEvent(new CustomEvent('notifications:updated', { detail: { unreadCount: this.unreadCount } }));
            try {
                const res = await fetch(`${WEB_BASE}/${n.id}/read`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!res.ok) throw new Error('mark-read failed');
            } catch(e) {
                // Revert optimistic update on failure
                n.is_read = false;
                this.unreadCount = Math.min(this.unreadCount + 1, 999);
                window.dispatchEvent(new CustomEvent('notifications:updated', { detail: { unreadCount: this.unreadCount } }));
            }
        },

        async storeAndNavigate(n) {
            await this.markRead(n);
            try {
                sessionStorage.setItem('__notif_detail', JSON.stringify({
                    id:          n.id,
                    title:       n.title || n.message,
                    message:     n.message,
                    priority:    n.priority,
                    category:    n.category,
                    action_url:  n.action_url,
                    action_label:n.action_label,
                    created_ago: n.created_ago,
                    metadata:    n.metadata || null,
                }));
            } catch(e) {}
            if (n.action_url) window.location.href = n.action_url;
        },

        async archive(n) {
            this.items = this.items.filter(x => x.id !== n.id);
            this.total = Math.max(0, this.total - 1);
            try {
                await fetch(`${WEB_BASE}/${n.id}/archive`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
            } catch(e) {}
        },

        async markAllRead() {
            const prevCount = this.unreadCount;
            this.items.forEach(n => { n.is_read = true; });
            this.unreadCount = 0;
            window.dispatchEvent(new CustomEvent('notifications:updated', { detail: { unreadCount: 0 } }));
            try {
                const res = await fetch(`${WEB_BASE}/mark-all-read`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!res.ok) throw new Error('mark-all-read failed');
            } catch(e) {
                // Revert on failure
                this.items.forEach(n => { n.is_read = false; });
                this.unreadCount = prevCount;
                window.dispatchEvent(new CustomEvent('notifications:updated', { detail: { unreadCount: prevCount } }));
            }
        },
    };
}
</script>
@endpush
@endsection
