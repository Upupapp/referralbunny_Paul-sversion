@extends('layouts.partner')
@section('title', 'Notifications')
@section('nav') @include('partner._nav') @endsection

@section('content')
<div x-data="partnerNotifications()" x-init="init()" class="max-w-2xl mx-auto space-y-4">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-[#1E1B4B]">Notifications</h1>
            <p class="text-gray-400 text-sm mt-0.5">
                <span x-text="unreadCount">0</span> unread
            </p>
        </div>
        <button @click="markAllRead()"
                x-show="unreadCount > 0"
                class="text-xs font-medium text-blue-600 hover:text-blue-700 transition-colors">
            Mark all as read
        </button>
    </div>

    {{-- Filter tabs --}}
    <div class="flex gap-2 flex-wrap">
        <template x-for="tab in tabs" :key="tab.key">
            <button @click="activeTab = tab.key; page = 1; load()"
                    :class="activeTab === tab.key
                        ? 'bg-blue-500 text-white shadow-sm'
                        : 'bg-white text-gray-500 border border-gray-200 hover:border-blue-300'"
                    class="px-3 py-1.5 rounded-full text-xs font-medium transition-all"
                    x-text="tab.label">
            </button>
        </template>
    </div>

    {{-- List --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

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

        <template x-if="!loading && error">
            <div class="flex flex-col items-center justify-center py-16 text-center px-6">
                <svg class="w-10 h-10 text-red-200 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <p class="text-sm font-medium text-gray-500">Could not load notifications</p>
                <button @click="load()" class="mt-2 text-xs text-blue-600 hover:underline">Try again</button>
            </div>
        </template>

        <template x-if="!loading && !error && items.length === 0">
            <div class="flex flex-col items-center justify-center py-16 text-center px-6">
                <svg class="w-10 h-10 text-gray-200 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <p class="text-sm font-medium text-gray-500">No notifications</p>
                <p class="text-xs text-gray-400 mt-1">You're all caught up.</p>
            </div>
        </template>

        <template x-if="!loading && !error && items.length > 0">
            <div class="divide-y divide-gray-50">
                <template x-for="n in items" :key="n.id">
                    <div class="flex items-start gap-3 px-5 py-4 transition-colors"
                         :class="!n.is_read ? 'bg-blue-50/40' : 'hover:bg-gray-50/60'">
                        <div class="w-2 h-2 rounded-full mt-2 shrink-0 transition-colors"
                             :class="!n.is_read ? 'bg-blue-500' : 'bg-gray-200'"></div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-[#1E1B4B] leading-snug" x-text="n.title"></p>
                            <p class="text-xs text-gray-500 mt-0.5 leading-relaxed" x-text="n.message"></p>
                            <div class="flex items-center gap-3 mt-1.5">
                                <span class="text-[11px] text-gray-400" x-text="n.created_ago"></span>
                                <template x-if="n.action_url">
                                    <a :href="n.action_url"
                                       class="text-[11px] font-semibold text-blue-600 hover:underline"
                                       x-text="n.action_label || 'View'"></a>
                                </template>
                                <template x-if="!n.is_read">
                                    <button @click="markRead(n)"
                                            class="text-[11px] text-gray-400 hover:text-blue-600 transition-colors"
                                            aria-label="Mark as read">
                                        Mark read
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>

    {{-- Pagination --}}
    <div class="flex items-center justify-between" x-show="total > perPage">
        <button @click="prev()" :disabled="page === 1"
                class="px-3 py-2 text-xs font-medium rounded-xl border border-gray-200 disabled:opacity-40 hover:bg-gray-50 transition-colors">
            Previous
        </button>
        <span class="text-xs text-gray-400" x-text="`Page ${page} of ${Math.ceil(total / perPage)}`"></span>
        <button @click="next()" :disabled="page >= Math.ceil(total / perPage)"
                class="px-3 py-2 text-xs font-medium rounded-xl border border-gray-200 disabled:opacity-40 hover:bg-gray-50 transition-colors">
            Next
        </button>
    </div>

</div>
@endsection

@push('scripts')
<script>
function partnerNotifications() {
    const CSRF = document.querySelector('meta[name=csrf-token]')?.content ?? '';

    return {
        items: [], total: 0, unreadCount: 0,
        loading: false, error: false, page: 1, perPage: 20,
        activeTab: 'all',
        tabs: [
            { key: 'all',           label: 'All' },
            { key: 'deal_pipeline', label: 'Deals' },
            { key: 'commission',    label: 'Commissions' },
            { key: 'messaging',     label: 'Messages' },
        ],

        async init() { await this.load(); },

        async load() {
            this.loading = true;
            this.error = false;
            try {
                const params = new URLSearchParams({
                    limit:  this.perPage,
                    offset: (this.page - 1) * this.perPage,
                    ...(this.activeTab !== 'all' ? { category: this.activeTab } : {}),
                });
                const r = await fetch(`/api/notifications/mine?${params}`, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!r.ok) throw new Error(r.status);
                const d = await r.json();
                this.items       = d.items ?? [];
                this.total       = d.total ?? 0;
                this.unreadCount = d.unread_count ?? 0;
            } catch (e) {
                this.items = [];
                this.error = true;
            } finally {
                this.loading = false;
            }
        },

        async markRead(n) {
            n.is_read = true;
            this.unreadCount = Math.max(0, this.unreadCount - 1);
            await fetch(`/partner/notifications/${n.id}/read`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
            });
        },

        async markAllRead() {
            await fetch('{{ route('partner.notifications.mark-all-read') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
            });
            this.items.forEach(n => n.is_read = true);
            this.unreadCount = 0;
            window.dispatchEvent(new CustomEvent('notifications:updated', { detail: { unreadCount: 0 } }));
        },

        prev() { if (this.page > 1) { this.page--; this.load(); } },
        next() { if (this.page < Math.ceil(this.total / this.perPage)) { this.page++; this.load(); } },
    };
}
</script>
@endpush
