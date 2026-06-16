@extends('layouts.reseller')
@section('title', 'Notifications')
@section('nav') @include('reseller._nav') @endsection

@section('content')
<div x-data="resellerNotifications()" x-init="init()" class="max-w-2xl mx-auto space-y-4">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-[#1E1B4B]">Notifications</h1>
            <p class="text-gray-400 text-sm mt-0.5">
                <span x-text="unreadCount">0</span> unread
            </p>
        </div>
        <button type="button" @click="markAllRead()"
                x-show="unreadCount > 0"
                class="text-xs font-medium text-teal-600 hover:text-teal-700 transition-colors">
            Mark all as read
        </button>
    </div>

    {{-- Filter tabs --}}
    <div class="flex gap-2 flex-wrap">
        <template x-for="tab in tabs" :key="tab.key">
            <button type="button" @click="activeTab = tab.key; page = 1; load()"
                    :class="activeTab === tab.key
                        ? 'bg-teal-500 text-white shadow-sm'
                        : 'bg-white text-gray-500 border border-gray-200 hover:border-teal-300'"
                    class="px-3 py-1.5 rounded-full text-xs font-medium transition-all"
                    x-text="tab.label">
            </button>
        </template>
    </div>

    {{-- List --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

        {{-- Loading --}}
        <template x-if="loading">
            <div class="divide-y divide-gray-50">
                <template x-for="i in [1,2,3,4]" :key="i">
                    <div class="flex items-start gap-3 px-5 py-4 animate-pulse">
                        <div class="w-2 h-2 rounded-full bg-gray-200 mt-2 shrink-0"></div>
                        <div class="flex-1 space-y-2">
                            <div class="h-3 bg-gray-200 rounded w-2/3"></div>
                            <div class="h-3 bg-gray-100 rounded w-1/2"></div>
                        </div>
                    </div>
                </template>
            </div>
        </template>

        {{-- Empty --}}
        <template x-if="!loading && items.length === 0">
            <div class="flex flex-col items-center justify-center py-16 text-center px-6">
                <div class="w-14 h-14 rounded-2xl bg-teal-50 flex items-center justify-center mb-3">
                    <svg class="w-7 h-7 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                </div>
                <p class="text-sm font-semibold text-[#1E1B4B]">No notifications</p>
                <p class="text-xs text-gray-400 mt-1 max-w-xs">
                    <template x-if="activeTab !== 'all'">
                        <span>No notifications in this filter. <button type="button" @click="activeTab='all'; load()" class="text-teal-600 underline">View all</button></span>
                    </template>
                    <template x-if="activeTab === 'all'">
                        <span>Updates about your deals, commissions, and messages will appear here.</span>
                    </template>
                </p>
            </div>
        </template>

        {{-- Notification rows --}}
        <template x-if="!loading && items.length > 0">
            <div class="divide-y divide-gray-50">
                <template x-for="n in items" :key="n.id">
                    <div class="flex items-start gap-3.5 px-5 py-4 hover:bg-gray-50 transition-colors group"
                         :class="!n.is_read ? 'bg-teal-50/30' : ''">

                        {{-- Priority dot --}}
                        <span class="w-2 h-2 rounded-full mt-2 shrink-0"
                              :class="{
                                  'bg-red-500':    ['critical','urgent'].includes(n.priority),
                                  'bg-orange-400': n.priority === 'high',
                                  'bg-teal-500':   ['normal','medium'].includes(n.priority),
                                  'bg-gray-300':   n.priority === 'low',
                              }"></span>

                        {{-- Content --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-[#1E1B4B] leading-snug"
                                       :class="!n.is_read ? 'font-bold' : ''"
                                       x-text="n.title || n.message"></p>
                                    <p class="text-sm text-gray-500 mt-0.5 leading-relaxed line-clamp-2"
                                       x-show="n.title" x-text="n.message"></p>
                                    <div class="flex items-center gap-3 mt-1.5">
                                        <span class="text-[10px] text-gray-400" x-text="n.created_ago"></span>
                                        <span class="text-[10px] text-gray-400 capitalize"
                                              x-text="(n.category ?? '').replace(/_/g,' ')"></span>
                                    </div>
                                </div>

                                {{-- Actions --}}
                                <div class="flex items-center gap-1 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button type="button" x-show="!n.is_read" @click.stop="markRead(n)"
                                            title="Mark as read"
                                            class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-teal-500 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            {{-- CTA --}}
                            <div class="mt-2" x-show="n.action_url && n.action_label">
                                <a :href="n.action_url"
                                   @click.prevent="storeAndNavigate(n)"
                                   class="inline-flex items-center gap-1.5 text-xs font-medium text-teal-600 hover:text-teal-800 transition-colors"
                                   x-text="n.action_label"></a>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </template>

        {{-- Pagination --}}
        <template x-if="!loading && totalPages > 1">
            <div class="flex items-center justify-between px-5 py-3 border-t border-gray-100 bg-gray-50">
                <button type="button" @click="page--; load()" :disabled="page <= 1"
                        class="text-xs text-gray-500 hover:text-teal-600 disabled:opacity-40 disabled:cursor-not-allowed">
                    ← Previous
                </button>
                <span class="text-xs text-gray-400">
                    Page <span x-text="page"></span> of <span x-text="totalPages"></span>
                </span>
                <button type="button" @click="page++; load()" :disabled="page >= totalPages"
                        class="text-xs text-gray-500 hover:text-teal-600 disabled:opacity-40 disabled:cursor-not-allowed">
                    Next →
                </button>
            </div>
        </template>
    </div>

</div>

@push('scripts')
<script>
function resellerNotifications() {
    const CSRF    = document.querySelector('meta[name=csrf-token]')?.content ?? '';
    const BASE    = '/api/notifications';
    const WEB_BASE = '{{ url("reseller/" . $tenant->id . "/notifications") }}';
    const PER_PAGE = 20;

    return {
        items:       [],
        total:       0,
        unreadCount: 0,
        page:        1,
        loading:     false,
        activeTab:   'all',

        tabs: [
            { key: 'all',        label: 'All' },
            { key: 'unread',     label: 'Unread' },
            { key: 'deals',      label: 'Deals' },
            { key: 'commission', label: 'Commission' },
        ],

        get totalPages() {
            return Math.max(1, Math.ceil(this.total / PER_PAGE));
        },

        async init() {
            await this.load();
        },

        async load() {
            this.loading = true;
            try {
                const params = new URLSearchParams({
                    limit:  PER_PAGE,
                    offset: (this.page - 1) * PER_PAGE,
                });

                if (this.activeTab === 'unread')     params.set('unread',   'true');
                if (this.activeTab === 'deals')       params.set('category', 'deal_pipeline');
                if (this.activeTab === 'commission')  params.set('category', 'commission');

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
                n.is_read = false;
                this.unreadCount = Math.min(this.unreadCount + 1, 999);
                window.dispatchEvent(new CustomEvent('notifications:updated', { detail: { unreadCount: this.unreadCount } }));
            }
        },

        // Store notification context then navigate so the detail modal shows on the destination page
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

        async markAllRead() {
            const prevCount = this.unreadCount;
            this.items.forEach(n => { n.is_read = true; });
            this.unreadCount = 0;
            window.dispatchEvent(new CustomEvent('notifications:updated', { detail: { unreadCount: 0 } }));
            try {
                const res = await fetch('/api/notifications/mine/mark-all-read', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!res.ok) throw new Error('mark-all-read failed');
            } catch(e) {
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
