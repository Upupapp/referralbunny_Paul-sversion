@extends('layouts.app')
@section('title', 'Messages')

@section('nav')
    @include('tenant._nav')
@endsection

@section('topbar-actions')
    <button onclick="window.__messaging && (window.__messaging.openCompose = true)" class="btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        <span class="hidden sm:inline">New Message</span>
    </button>
@endsection

@section('content')
<div
    x-data="messaging()"
    x-init="init()"
    x-ref="root"
    class="flex flex-col gap-4"
>

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="card">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-50 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-[#7B61FF]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-[#1E1B4B]" x-text="threads.length">0</p>
                    <p class="text-xs text-gray-400">Conversations</p>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-[#1E1B4B]" x-text="totalUnread">0</p>
                    <p class="text-xs text-gray-400">Unread</p>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-[#1E1B4B]">{{ $resellers->count() }}</p>
                    <p class="text-xs text-gray-400">Resellers</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Chat Panel --}}
    <div class="card p-0 overflow-hidden flex" style="height: calc(100vh - 260px); min-height: 420px;">

        {{-- Left: Thread List --}}
        <div class="w-72 border-r border-gray-100 flex flex-col shrink-0">
            <div class="p-3 border-b border-gray-100">
                <input
                    x-model="search"
                    type="text"
                    placeholder="Search conversations..."
                    class="form-input text-sm py-2 w-full"
                >
            </div>

            <div class="flex-1 overflow-y-auto">
                {{-- Loading --}}
                <template x-if="loading">
                    <div class="p-6 text-center">
                        <svg class="w-5 h-5 animate-spin text-[#7B61FF] mx-auto" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4z"/>
                        </svg>
                    </div>
                </template>

                {{-- Empty state --}}
                <template x-if="!loading && filteredThreads.length === 0">
                    <div class="p-6 text-center text-gray-400 text-sm">
                        <p x-show="search">No results for "<span x-text="search"></span>"</p>
                        <p x-show="!search">No conversations yet</p>
                    </div>
                </template>

                {{-- Thread items --}}
                <template x-for="thread in filteredThreads" :key="thread.id">
                    <button
                        @click="selectThread(thread)"
                        class="w-full text-left px-4 py-3 hover:bg-gray-50 transition-colors border-b border-gray-50"
                        :class="activeThread?.id === thread.id ? 'bg-purple-50 border-l-[3px] border-l-[#7B61FF]' : 'border-l-[3px] border-l-transparent'"
                    >
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-full bg-[#EDE9FE] flex items-center justify-center shrink-0">
                                <span class="text-[#7B61FF] font-bold text-sm" x-text="thread.reseller_name.charAt(0).toUpperCase()"></span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-1">
                                    <span class="text-sm font-semibold text-[#1E1B4B] truncate" x-text="thread.reseller_name"></span>
                                    <span class="text-[10px] text-gray-400 shrink-0" x-text="thread.last_message_at ?? ''"></span>
                                </div>
                                <div class="flex items-center justify-between gap-1 mt-0.5">
                                    <p class="text-xs text-gray-400 truncate" x-text="thread.last_message_preview || 'Start a conversation'"></p>
                                    <span x-show="thread.admin_unread > 0" class="shrink-0 w-4 h-4 rounded-full bg-[#7B61FF] flex items-center justify-center">
                                        <span class="text-[9px] font-bold text-white" x-text="thread.admin_unread"></span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </button>
                </template>
            </div>

            <div class="p-3 border-t border-gray-100">
                <button @click="openCompose = true" class="btn-primary w-full justify-center text-sm gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    New Message
                </button>
            </div>
        </div>

        {{-- Right: Active Thread --}}
        <div class="flex-1 flex flex-col min-w-0">

            {{-- No thread selected --}}
            <template x-if="!activeThread">
                <div class="flex-1 flex flex-col items-center justify-center text-center p-8">
                    <div class="w-16 h-16 rounded-2xl bg-[#EDE9FE] flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-[#7B61FF]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                        </svg>
                    </div>
                    <h3 class="text-[#1E1B4B] font-semibold text-base">Select a conversation</h3>
                    <p class="text-gray-400 text-sm mt-1 max-w-xs">Choose a conversation from the list, or start a new message to a reseller.</p>
                    <button @click="openCompose = true" class="btn-primary mt-5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        New Message
                    </button>
                </div>
            </template>

            {{-- Active thread --}}
            <template x-if="activeThread">
                <div class="flex flex-col h-full">

                    {{-- Thread header --}}
                    <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-3 shrink-0">
                        <div class="w-9 h-9 rounded-full bg-[#EDE9FE] flex items-center justify-center">
                            <span class="text-[#7B61FF] font-bold text-sm" x-text="activeThread.reseller_name?.charAt(0).toUpperCase()"></span>
                        </div>
                        <div>
                            <p class="font-semibold text-sm text-[#1E1B4B]" x-text="activeThread.reseller_name"></p>
                            <p class="text-xs text-gray-400" x-text="activeThread.reseller_email"></p>
                        </div>
                        <div class="ml-auto">
                            <span class="inline-flex items-center gap-1 text-xs text-gray-400 bg-gray-50 rounded-full px-2.5 py-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-300"></span>
                                In-app · delivery via app & email later
                            </span>
                        </div>
                    </div>

                    {{-- Messages scroll area --}}
                    <div class="flex-1 overflow-y-auto p-4 space-y-3" x-ref="messageArea">

                        <template x-if="loadingMessages">
                            <div class="flex justify-center py-8">
                                <svg class="w-5 h-5 animate-spin text-[#7B61FF]" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4z"/>
                                </svg>
                            </div>
                        </template>

                        <template x-if="!loadingMessages && activeMessages.length === 0">
                            <div class="text-center text-gray-400 text-sm py-10">No messages yet — send the first one below.</div>
                        </template>

                        <template x-for="msg in activeMessages" :key="msg.id">
                            <div :class="msg.sender_type === 'admin' ? 'flex justify-end' : 'flex justify-start'">
                                <div
                                    :class="msg.sender_type === 'admin'
                                        ? 'bg-[#7B61FF] text-white rounded-2xl rounded-br-sm'
                                        : 'bg-gray-100 text-[#1E1B4B] rounded-2xl rounded-bl-sm'"
                                    class="max-w-[72%] px-4 py-2.5"
                                >
                                    <p class="text-sm leading-relaxed whitespace-pre-wrap" x-text="msg.body"></p>
                                    <p
                                        :class="msg.sender_type === 'admin' ? 'text-white/60' : 'text-gray-400'"
                                        class="text-[10px] mt-1 text-right"
                                        :title="msg.created_at_full"
                                        x-text="msg.created_at"
                                    ></p>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Compose bar --}}
                    <div class="p-3 border-t border-gray-100 shrink-0">
                        <form @submit.prevent="sendReply()" class="flex gap-2 items-end">
                            <textarea
                                x-model="replyBody"
                                placeholder="Type a message..."
                                rows="1"
                                class="form-input flex-1 text-sm resize-none"
                                style="min-height:38px; max-height:120px;"
                                @input="$el.style.height='38px'; $el.style.height=$el.scrollHeight+'px'"
                                @keydown.enter.prevent.exact="sendReply()"
                                @keydown.enter.shift.prevent="replyBody += '\n'"
                            ></textarea>
                            <button
                                type="submit"
                                class="btn-primary shrink-0 h-[38px] px-4"
                                :disabled="!replyBody.trim() || sending"
                            >
                                <svg x-show="!sending" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                </svg>
                                <svg x-show="sending" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4z"/>
                                </svg>
                            </button>
                        </form>
                        <p class="text-[10px] text-gray-400 mt-1.5">Enter to send · Shift+Enter for new line</p>
                    </div>

                </div>
            </template>

        </div>
    </div>

    {{-- New Message Modal --}}
    <div x-show="openCompose" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div @click="openCompose = false" class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg" @click.stop>
            <div class="flex items-center justify-between px-6 pt-6 pb-4 border-b border-gray-100">
                <div>
                    <h3 class="text-[#1E1B4B] font-bold text-lg">New Message</h3>
                    <p class="text-gray-400 text-sm mt-0.5">Send a message to a reseller</p>
                </div>
                <button @click="openCompose = false" class="text-gray-400 hover:text-gray-600 transition-colors p-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form @submit.prevent="startThread()" class="px-6 py-5 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">To</label>
                    <select x-model="compose.reseller_id" class="form-input w-full text-sm" required>
                        <option value="">Select a reseller...</option>
                        @foreach($resellers as $reseller)
                            <option value="{{ $reseller->id }}">{{ $reseller->name }}  ·  {{ $reseller->email }}</option>
                        @endforeach
                    </select>
                    @if($resellers->isEmpty())
                        <p class="text-xs text-amber-600 mt-1.5">No active resellers yet. Invite resellers from the Resellers page first.</p>
                    @endif
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Message</label>
                    <textarea
                        x-model="compose.body"
                        rows="5"
                        placeholder="Write your message here..."
                        class="form-input w-full text-sm resize-none"
                        required
                    ></textarea>
                </div>
                <div class="flex gap-3 justify-end pt-1">
                    <button type="button" @click="openCompose = false" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="sending || !compose.reseller_id || !compose.body.trim()">
                        <svg x-show="sending" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4z"/>
                        </svg>
                        <span x-text="sending ? 'Sending...' : 'Send Message'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

@push('scripts')
<script>
function messaging() {
    const BASE = '{{ url("tenant/" . $tenant->id . "/messages") }}';
    const CSRF = document.querySelector('meta[name=csrf-token]')?.content ?? '';

    return {
        threads:        [],
        activeThread:   null,
        activeMessages: [],
        openCompose:    false,
        search:         '',
        replyBody:      '',
        sending:        false,
        loading:        false,
        loadingMessages: false,
        compose:        { reseller_id: '', body: '' },

        get filteredThreads() {
            if (!this.search) return this.threads;
            const q = this.search.toLowerCase();
            return this.threads.filter(t =>
                t.reseller_name.toLowerCase().includes(q) ||
                (t.last_message_preview ?? '').toLowerCase().includes(q)
            );
        },

        get totalUnread() {
            return this.threads.reduce((sum, t) => sum + (t.admin_unread ?? 0), 0);
        },

        async init() {
            window.__messaging = this;
            await this.loadThreads();
        },

        async loadThreads() {
            this.loading = true;
            try {
                const r = await fetch(`${BASE}/threads`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                this.threads = await r.json();
            } finally {
                this.loading = false;
            }
        },

        async selectThread(thread) {
            this.activeThread   = { ...thread };
            this.activeMessages = [];
            this.loadingMessages = true;
            try {
                const r = await fetch(`${BASE}/threads/${thread.id}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await r.json();
                this.activeThread   = { ...this.activeThread, ...data.thread };
                this.activeMessages = data.messages;
                // clear unread in list
                const t = this.threads.find(x => x.id === thread.id);
                if (t) t.admin_unread = 0;
            } finally {
                this.loadingMessages = false;
                this.$nextTick(() => this.scrollToBottom());
            }
        },

        async sendReply() {
            if (!this.replyBody.trim() || this.sending) return;
            this.sending = true;
            try {
                const r = await fetch(`${BASE}/threads/${this.activeThread.id}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type':    'application/json',
                        'Accept':          'application/json',
                        'X-CSRF-TOKEN':    CSRF,
                        'X-Requested-With':'XMLHttpRequest',
                    },
                    body: JSON.stringify({ body: this.replyBody }),
                });
                const msg = await r.json();
                this.activeMessages.push(msg);
                this.updateThreadPreview(this.activeThread.id, msg);
                this.replyBody = '';
                this.$nextTick(() => {
                    this.scrollToBottom();
                    this.$el.querySelector('textarea')?.dispatchEvent(new Event('input'));
                });
            } finally {
                this.sending = false;
            }
        },

        async startThread() {
            if (this.sending || !this.compose.reseller_id || !this.compose.body.trim()) return;
            this.sending = true;
            try {
                const r = await fetch(`${BASE}/threads`, {
                    method: 'POST',
                    headers: {
                        'Content-Type':    'application/json',
                        'Accept':          'application/json',
                        'X-CSRF-TOKEN':    CSRF,
                        'X-Requested-With':'XMLHttpRequest',
                    },
                    body: JSON.stringify(this.compose),
                });
                const data = await r.json();
                this.openCompose = false;
                this.compose     = { reseller_id: '', body: '' };
                await this.loadThreads();
                const thread = this.threads.find(t => t.id === data.thread_id);
                if (thread) this.selectThread(thread);
            } finally {
                this.sending = false;
            }
        },

        updateThreadPreview(threadId, msg) {
            const t = this.threads.find(x => x.id === threadId);
            if (t) {
                t.last_message_preview = msg.body.substring(0, 80);
                t.last_message_at      = msg.created_at;
            }
        },

        scrollToBottom() {
            const el = this.$refs.messageArea;
            if (el) el.scrollTop = el.scrollHeight;
        },
    };
}
</script>
@endpush
@endsection
