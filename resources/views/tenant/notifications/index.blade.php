@extends('layouts.app')
@section('title', 'Notifications')

@section('nav')
    @include('tenant._nav')
@endsection

@section('topbar-actions')
    <button type="button" id="markAllReadBtn"
            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-sm font-semibold border border-gray-200 text-gray-600 hover:bg-gray-50 hover:border-gray-300 transition-all focus:outline-none focus:ring-2 focus:ring-gray-300/50"
            aria-label="Mark all notifications as read">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <span class="hidden sm:inline">Mark all read</span>
    </button>
@endsection

@section('content')
<div x-data="notificationCenter()" x-init="init()" class="space-y-4 max-w-4xl">

    {{-- ── Header + Tabs + Filters ─────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

        {{-- Header section --}}
        <div class="px-5 sm:px-6 pt-5 pb-4 border-b border-gray-100"
             style="background:linear-gradient(135deg,#faf8ff 0%,#f0effe 100%)">
            <div class="flex items-start gap-4">
                {{-- Icon --}}
                <div class="w-11 h-11 rounded-2xl flex items-center justify-center shrink-0 shadow-sm"
                     style="background:linear-gradient(135deg,#7B61FF,#6d28d9)">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                </div>
                {{-- Title + meta --}}
                <div class="flex-1 min-w-0">
                    <h1 class="text-xl font-extrabold text-[#1E1B4B]">Notifications</h1>
                    <p class="text-xs text-gray-500 mt-0.5">Review updates, action items, and account alerts.</p>
                    <div class="flex items-center gap-2 mt-2">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold"
                              :class="unreadCount > 0 ? 'bg-[#7B61FF]/10 text-[#7B61FF]' : 'bg-gray-100 text-gray-500'">
                            <span class="w-1.5 h-1.5 rounded-full"
                                  :class="unreadCount > 0 ? 'bg-[#7B61FF]' : 'bg-gray-400'"></span>
                            <span x-text="unreadCount > 0 ? unreadCount + ' unread' : 'All caught up'"></span>
                        </span>
                        <span x-show="filterCategory || filterPriority"
                              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-100 text-amber-700">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
                            </svg>
                            Filtered
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tab row --}}
        <div class="px-5 sm:px-6 pt-4 pb-3 border-b border-gray-100">
            <div class="flex items-center gap-1.5 overflow-x-auto scrollbar-none pb-0.5" role="tablist" aria-label="Notification filters">
                <template x-for="tab in tabs" :key="tab.key">
                    <button type="button"
                        role="tab"
                        :aria-selected="activeTab === tab.key ? 'true' : 'false'"
                        @click="activeTab = tab.key; page = 1; load()"
                        :class="activeTab === tab.key
                            ? 'text-white shadow-md'
                            : 'bg-gray-100 text-gray-500 hover:bg-gray-200 hover:text-gray-700'"
                        :style="activeTab === tab.key ? 'background:linear-gradient(135deg,#7B61FF,#6d28d9)' : ''"
                        class="px-3.5 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-all focus:outline-none focus:ring-2 focus:ring-[#7B61FF]/40 shrink-0 min-h-[36px]"
                        x-text="tab.label"
                    ></button>
                </template>
            </div>
        </div>

        {{-- Filter toolbar --}}
        <div class="px-5 sm:px-6 py-3 flex flex-wrap items-center gap-2">
            {{-- Category dropdown --}}
            <label class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border text-xs font-medium text-gray-600 cursor-pointer transition-all min-h-[36px]"
                   :class="filterCategory ? 'border-[#7B61FF]/40 bg-purple-50 text-[#7B61FF]' : 'border-gray-200 bg-gray-50 hover:border-gray-300'"
                   aria-label="Filter by category">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
                </svg>
                <select x-model="filterCategory" @change="page = 1; load()"
                        class="bg-transparent border-0 outline-none text-xs font-medium cursor-pointer appearance-none pr-4"
                        aria-label="All Categories">
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
                <svg class="w-3 h-3 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </label>

            {{-- Priority dropdown --}}
            <label class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border text-xs font-medium text-gray-600 cursor-pointer transition-all min-h-[36px]"
                   :class="filterPriority ? 'border-[#7B61FF]/40 bg-purple-50 text-[#7B61FF]' : 'border-gray-200 bg-gray-50 hover:border-gray-300'"
                   aria-label="Filter by priority">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <select x-model="filterPriority" @change="page = 1; load()"
                        class="bg-transparent border-0 outline-none text-xs font-medium cursor-pointer appearance-none pr-4"
                        aria-label="All Priorities">
                    <option value="">All Priorities</option>
                    <option value="urgent">Urgent</option>
                    <option value="high">High</option>
                    <option value="normal">Normal</option>
                    <option value="low">Low</option>
                </select>
                <svg class="w-3 h-3 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </label>

            {{-- Clear filters --}}
            <button type="button" x-show="filterCategory || filterPriority"
                    @click="filterCategory=''; filterPriority=''; page=1; load()"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-red-200 bg-red-50 text-red-600 text-xs font-semibold hover:bg-red-100 hover:border-red-300 transition-all focus:outline-none focus:ring-2 focus:ring-red-300/40 min-h-[36px]"
                    aria-label="Clear all filters">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                Clear filters
            </button>

            {{-- Result count (when items loaded) --}}
            <span x-show="!loading && items.length > 0" class="ml-auto text-[10px] text-gray-400 shrink-0" x-text="total + ' result' + (total !== 1 ? 's' : '')"></span>
        </div>
    </div>

    {{-- ── Notification list card ─────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

        {{-- Loading skeleton --}}
        <template x-if="loading">
            <div class="divide-y divide-gray-50">
                <template x-for="i in [1,2,3,4,5]" :key="i">
                    <div class="flex items-start gap-4 px-5 sm:px-6 py-4 animate-pulse">
                        <div class="w-8 h-8 rounded-xl bg-gray-100 shrink-0"></div>
                        <div class="flex-1 space-y-2.5 py-0.5">
                            <div class="h-3 bg-gray-200 rounded-full w-3/5"></div>
                            <div class="h-2.5 bg-gray-100 rounded-full w-4/5"></div>
                            <div class="h-2 bg-gray-100 rounded-full w-24"></div>
                        </div>
                    </div>
                </template>
            </div>
        </template>

        {{-- Empty state —— context-aware messaging --}}
        <template x-if="!loading && items.length === 0">
            <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
                <div class="w-16 h-16 rounded-2xl flex items-center justify-center mb-5 shadow-sm"
                     style="background:linear-gradient(135deg,#ede9fe,#ddd6fe)">
                    <svg class="w-8 h-8 text-[#7B61FF]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                </div>

                {{-- Filtered state --}}
                <template x-if="filterCategory || filterPriority">
                    <div>
                        <h3 class="text-[#1E1B4B] font-bold text-base mb-1">No notifications found</h3>
                        <p class="text-gray-400 text-sm max-w-xs mx-auto mb-4">Try changing or clearing your filters to see more results.</p>
                        <button type="button" @click="filterCategory=''; filterPriority=''; page=1; load()"
                                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold text-white transition-all focus:outline-none focus:ring-2 focus:ring-[#7B61FF]/40"
                                style="background:linear-gradient(135deg,#7B61FF,#6d28d9)">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            Clear filters
                        </button>
                    </div>
                </template>

                {{-- Unread tab, no results --}}
                <template x-if="!filterCategory && !filterPriority && activeTab === 'unread'">
                    <div>
                        <h3 class="text-[#1E1B4B] font-bold text-base mb-1">No unread notifications</h3>
                        <p class="text-gray-400 text-sm max-w-xs mx-auto mb-4">New updates will appear here when they need your attention.</p>
                        <button type="button" @click="activeTab='all'; page=1; load()"
                                class="text-xs font-semibold text-[#7B61FF] hover:text-purple-800 transition-colors underline">
                            View all notifications
                        </button>
                    </div>
                </template>

                {{-- Any other tab, no results --}}
                <template x-if="!filterCategory && !filterPriority && activeTab !== 'unread'">
                    <div>
                        <h3 class="text-[#1E1B4B] font-bold text-base mb-1">You're all caught up</h3>
                        <p class="text-gray-400 text-sm max-w-xs mx-auto">Important updates will appear here as they happen across your workspace.</p>
                    </div>
                </template>
            </div>
        </template>

        {{-- ── Notification rows ────────────────────────────────────────────── --}}
        <template x-if="!loading && items.length > 0">
            <div class="divide-y divide-gray-50">
                <template x-for="n in items" :key="n.id">
                    <div class="group flex items-start gap-4 px-5 sm:px-6 py-4 transition-all hover:bg-gray-50/80 cursor-default"
                         :class="!n.is_read ? 'bg-purple-50/40' : ''">

                        {{-- Priority icon container --}}
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 mt-0.5"
                             :class="{
                                'bg-red-100':    ['critical','urgent'].includes(n.priority),
                                'bg-orange-100': n.priority === 'high',
                                'bg-purple-100': ['normal','medium'].includes(n.priority),
                                'bg-gray-100':   n.priority === 'low',
                             }">
                            {{-- Urgent/Critical: exclamation --}}
                            <template x-if="['critical','urgent'].includes(n.priority)">
                                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </template>
                            {{-- High: arrow up --}}
                            <template x-if="n.priority === 'high'">
                                <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                                </svg>
                            </template>
                            {{-- Normal/Low: bell --}}
                            <template x-if="['normal','medium','low'].includes(n.priority)">
                                <svg class="w-4 h-4" :class="n.priority === 'low' ? 'text-gray-400' : 'text-[#7B61FF]'" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                            </template>
                        </div>

                        {{-- Content --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    {{-- Unread dot + title --}}
                                    <div class="flex items-start gap-2">
                                        <span x-show="!n.is_read"
                                              class="w-2 h-2 rounded-full bg-[#7B61FF] mt-1.5 shrink-0"
                                              :aria-label="'Unread'"></span>
                                        <p class="text-sm leading-snug text-[#1E1B4B]"
                                           :class="!n.is_read ? 'font-bold' : 'font-semibold'"
                                           x-text="n.title || n.message"></p>
                                    </div>

                                    {{-- Message body --}}
                                    <p class="text-sm text-gray-500 mt-0.5 leading-relaxed line-clamp-2"
                                       x-show="n.title"
                                       x-text="n.message"
                                       :class="!n.is_read ? 'ml-4' : ''"></p>

                                    {{-- Meta row --}}
                                    <div class="flex flex-wrap items-center gap-2 mt-2"
                                         :class="!n.is_read ? 'ml-4' : ''">
                                        <span class="text-[10px] text-gray-400 font-medium" x-text="n.created_ago"></span>

                                        {{-- Priority badge (urgent/critical/high only) --}}
                                        <span x-show="['urgent','critical','high'].includes(n.priority)"
                                              class="text-[10px] font-bold px-1.5 py-0.5 rounded-full"
                                              :class="{
                                                  'bg-red-100 text-red-700':     ['critical','urgent'].includes(n.priority),
                                                  'bg-orange-100 text-orange-700': n.priority === 'high',
                                              }"
                                              x-text="n.priority.charAt(0).toUpperCase() + n.priority.slice(1)"></span>

                                        {{-- Category badge --}}
                                        <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-500 capitalize"
                                              x-show="n.category"
                                              x-text="(n.category ?? '').replace(/_/g,' ')"></span>
                                    </div>

                                    {{-- CTA link --}}
                                    <div class="mt-2.5" x-show="n.action_url && n.action_label">
                                        <a :href="n.action_url"
                                           @click.prevent="storeAndNavigate(n)"
                                           class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#7B61FF] hover:text-purple-800 transition-colors focus:outline-none focus:underline"
                                           :class="!n.is_read ? 'ml-4' : ''"
                                           x-text="n.action_label"></a>
                                    </div>
                                </div>

                                {{-- Row actions (hover reveal) --}}
                                <div class="flex items-center gap-1 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button type="button" x-show="!n.is_read"
                                            @click.stop="markRead(n)"
                                            title="Mark as read"
                                            aria-label="Mark as read"
                                            class="p-1.5 rounded-lg hover:bg-purple-100 text-gray-400 hover:text-[#7B61FF] transition-colors focus:outline-none focus:ring-1 focus:ring-[#7B61FF]/40 min-h-[36px] min-w-[36px] flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </button>
                                    <button type="button" @click.stop="archive(n)"
                                            title="Archive"
                                            aria-label="Archive notification"
                                            class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition-colors focus:outline-none focus:ring-1 focus:ring-gray-300 min-h-[36px] min-w-[36px] flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </template>

        {{-- ── Pagination ──────────────────────────────────────────────────────── --}}
        <template x-if="!loading && totalPages > 1">
            <div class="flex items-center justify-between px-5 sm:px-6 py-3.5 border-t border-gray-100 bg-gray-50/80">
                <button type="button" @click="page--; load()"
                        :disabled="page <= 1"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 hover:text-[#7B61FF] disabled:opacity-30 disabled:cursor-not-allowed transition-colors focus:outline-none focus:underline"
                        aria-label="Previous page">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Previous
                </button>
                <span class="text-xs text-gray-400">
                    Page <span class="font-semibold text-gray-600" x-text="page"></span>
                    of <span class="font-semibold text-gray-600" x-text="totalPages"></span>
                    <span class="hidden sm:inline"> · <span x-text="total"></span> total</span>
                </span>
                <button type="button" @click="page++; load()"
                        :disabled="page >= totalPages"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 hover:text-[#7B61FF] disabled:opacity-30 disabled:cursor-not-allowed transition-colors focus:outline-none focus:underline"
                        aria-label="Next page">
                    Next
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>
        </template>

    </div>

</div>

@push('scripts')
<script>
{{-- JavaScript logic unchanged — UI only --}}
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
