@extends('layouts.app')
@section('title', 'Messages')

@section('nav')
    @include('tenant._nav')
@endsection

@section('topbar-actions')
    <button onclick="window.__messaging && (window.__messaging.openCompose = true)"
            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-sm font-semibold text-white transition-all"
            style="background: linear-gradient(135deg,#7B61FF,#9B8BFF)">
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
    class="flex flex-col"
    style="height: calc(100vh - 88px);"
>

    {{-- Chat Panel — fills remaining height --}}
    <div class="card p-0 overflow-hidden flex flex-1 min-h-0">

        {{-- ── Left: Thread List ────────────────────────────── --}}
        <div class="w-72 border-r border-gray-100 flex flex-col shrink-0 min-h-0">

            {{-- Left header --}}
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-[#1E1B4B]">Messages</h2>
                    <p class="text-[10px] text-gray-400 mt-0.5">
                        <span x-text="threads.length">0</span> conversation<span x-show="threads.length !== 1">s</span>
                        <template x-if="totalUnread > 0">
                            <span> · <span x-text="totalUnread"></span> unread</span>
                        </template>
                    </p>
                </div>
                <button @click="openCompose = true"
                        class="w-7 h-7 rounded-full flex items-center justify-center text-white transition-all hover:shadow-md shrink-0"
                        style="background: linear-gradient(135deg,#7B61FF,#9B8BFF)"
                        title="New Message">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                    </svg>
                </button>
            </div>

            {{-- Tabs --}}
            <div class="flex border-b border-gray-100 shrink-0">
                <button
                    @click="activeTab = 'all'; loadThreads()"
                    :class="activeTab === 'all'
                        ? 'text-[#7B61FF] border-b-2 border-[#7B61FF] font-semibold'
                        : 'text-gray-400 hover:text-gray-600'"
                    class="flex-1 text-[11px] py-2.5 transition-colors"
                >All</button>
                <button
                    @click="activeTab = 'needs_reply'; loadNeedsReply()"
                    :class="activeTab === 'needs_reply'
                        ? 'text-[#7B61FF] border-b-2 border-[#7B61FF] font-semibold'
                        : 'text-gray-400 hover:text-gray-600'"
                    class="flex-1 text-[11px] py-2.5 transition-colors relative"
                >
                    Needs Reply
                    <span x-show="needsReplyCount > 0"
                          class="absolute top-1.5 right-1.5 w-3.5 h-3.5 rounded-full bg-orange-400 text-white text-[8px] font-bold flex items-center justify-center"
                          x-text="needsReplyCount > 9 ? '9+' : needsReplyCount"></span>
                </button>
                <button
                    @click="activeTab = 'unread'; loadThreads()"
                    :class="activeTab === 'unread'
                        ? 'text-[#7B61FF] border-b-2 border-[#7B61FF] font-semibold'
                        : 'text-gray-400 hover:text-gray-600'"
                    class="flex-1 text-[11px] py-2.5 transition-colors"
                >Unread</button>
            </div>

            {{-- Search --}}
            <div class="p-2.5 border-b border-gray-100 shrink-0">
                <div class="relative">
                    <svg class="w-3.5 h-3.5 text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input x-model="search" type="text" placeholder="Search..."
                           class="w-full text-xs py-2 pl-8 pr-3 bg-gray-50 rounded-lg border-0 outline-none focus:ring-1 focus:ring-[#7B61FF] focus:bg-white transition-all">
                </div>
            </div>

            {{-- Thread list --}}
            <div class="flex-1 overflow-y-auto">
                <template x-if="loading">
                    <div class="p-6 text-center">
                        <svg class="w-4 h-4 animate-spin text-[#7B61FF] mx-auto" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4z"/>
                        </svg>
                    </div>
                </template>

                <template x-if="!loading && filteredThreads.length === 0">
                    <div class="px-4 py-8 text-center text-gray-400 text-xs">
                        <svg class="w-8 h-8 text-gray-200 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                        </svg>
                        <p x-show="search">No results</p>
                        <p x-show="!search && activeTab === 'needs_reply'">No pending replies</p>
                        <p x-show="!search && activeTab !== 'needs_reply'">No conversations yet</p>
                    </div>
                </template>

                <template x-for="thread in filteredThreads" :key="thread.id">
                    <button
                        @click="selectThread(thread)"
                        class="w-full text-left px-3 py-3 hover:bg-gray-50 transition-colors border-b border-gray-50/70"
                        :class="activeThread?.id === thread.id
                            ? 'bg-[#F5F3FF] border-l-[3px] border-l-[#7B61FF]'
                            : 'border-l-[3px] border-l-transparent'"
                    >
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-full bg-[#EDE9FE] flex items-center justify-center shrink-0 text-[#7B61FF] font-bold text-xs"
                                 x-text="thread.reseller_name.charAt(0).toUpperCase()"></div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-1">
                                    <span class="text-xs font-semibold text-[#1E1B4B] truncate"
                                          :class="thread.admin_unread > 0 ? 'font-bold' : ''"
                                          x-text="thread.reseller_name"></span>
                                    <span class="text-[9px] text-gray-400 shrink-0" x-text="thread.last_message_at ?? ''"></span>
                                </div>
                                <div class="flex items-center justify-between gap-1 mt-0.5">
                                    <p class="text-[11px] text-gray-400 truncate" x-text="thread.last_message_preview || 'No messages yet'"></p>
                                    <span x-show="thread.admin_unread > 0"
                                          class="shrink-0 w-4 h-4 rounded-full bg-[#7B61FF] flex items-center justify-center">
                                        <span class="text-[8px] font-bold text-white" x-text="thread.admin_unread"></span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </button>
                </template>
            </div>
        </div>

        {{-- ── Right: Active Thread ─────────────────────────── --}}
        <div class="flex-1 flex flex-col min-w-0">

            {{-- No thread selected --}}
            <template x-if="!activeThread">
                <div class="flex-1 flex flex-col items-center justify-center text-center p-8">
                    <div class="w-14 h-14 rounded-2xl bg-[#EDE9FE] flex items-center justify-center mb-3">
                        <svg class="w-7 h-7 text-[#7B61FF]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                        </svg>
                    </div>
                    <h3 class="text-[#1E1B4B] font-semibold text-sm">Select a conversation</h3>
                    <p class="text-gray-400 text-xs mt-1 max-w-xs">Choose from the list or start a new message.</p>
                    <button @click="openCompose = true"
                            class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold text-white transition-all hover:shadow-md"
                            style="background:linear-gradient(135deg,#7B61FF,#9B8BFF)">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        New Message
                    </button>
                </div>
            </template>

            {{-- Active thread --}}
            <template x-if="activeThread">
                <div class="flex flex-col h-full min-h-0">

                    {{-- Thread header --}}
                    <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-3 shrink-0">
                        <div class="w-8 h-8 rounded-full bg-[#EDE9FE] flex items-center justify-center text-[#7B61FF] font-bold text-xs shrink-0"
                             x-text="activeThread.reseller_name?.charAt(0).toUpperCase()"></div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm text-[#1E1B4B] truncate" x-text="activeThread.reseller_name"></p>
                            <p class="text-[10px] text-gray-400 truncate" x-text="activeThread.reseller_email"></p>
                        </div>
                        <span class="inline-flex items-center gap-1 text-[10px] text-gray-400 bg-gray-50 rounded-full px-2.5 py-1 shrink-0">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-400"></span>
                            In-app
                        </span>
                    </div>

                    {{-- Reminder banner --}}
                    <template x-if="activeThreadReminder">
                        <div class="mx-3 mt-2 flex items-center gap-2 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2">
                            <svg class="w-3.5 h-3.5 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <p class="text-[11px] text-amber-700 flex-1" x-text="activeThreadReminder?.body"></p>
                            <button @click="resolveThreadReminder()" class="text-[10px] text-amber-500 hover:text-amber-700 font-medium shrink-0">✕</button>
                        </div>
                    </template>

                    {{-- Messages --}}
                    <div class="flex-1 overflow-y-auto p-4 space-y-3 min-h-0" x-ref="messageArea">
                        <template x-if="loadingMessages">
                            <div class="flex justify-center py-8">
                                <svg class="w-4 h-4 animate-spin text-[#7B61FF]" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4z"/>
                                </svg>
                            </div>
                        </template>

                        <template x-if="!loadingMessages && activeMessages.length === 0">
                            <div class="text-center text-gray-400 text-xs py-10">No messages yet — send the first one below.</div>
                        </template>

                        <template x-for="msg in activeMessages" :key="msg.id">
                            <div :class="msg.sender_type === 'admin' ? 'flex justify-end' : 'flex justify-start'">
                                <div :class="msg.sender_type === 'admin'
                                        ? 'text-white rounded-2xl rounded-br-sm'
                                        : 'bg-gray-100 text-[#1E1B4B] rounded-2xl rounded-bl-sm'"
                                     :style="msg.sender_type === 'admin' ? 'background:linear-gradient(135deg,#7B61FF,#9B8BFF)' : ''"
                                     class="max-w-[70%] px-4 py-2.5">
                                    <p class="text-sm leading-relaxed whitespace-pre-wrap" x-text="msg.body"></p>
                                    <p :class="msg.sender_type === 'admin' ? 'text-white/50' : 'text-gray-400'"
                                       class="text-[10px] mt-1 text-right" :title="msg.created_at_full"
                                       x-text="msg.created_at"></p>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Compose --}}
                    <div class="p-3 border-t border-gray-100 shrink-0">
                        <form @submit.prevent="sendReply()" class="flex gap-2 items-end">
                            <textarea x-model="replyBody" placeholder="Type a message…" rows="1"
                                      class="flex-1 text-sm bg-gray-50 border border-gray-200 rounded-xl px-3.5 py-2.5 resize-none outline-none focus:ring-2 focus:ring-[#7B61FF]/20 focus:border-[#7B61FF] transition-all"
                                      style="min-height:40px; max-height:120px;"
                                      @input="$el.style.height='40px'; $el.style.height=$el.scrollHeight+'px'"
                                      @keydown.enter.prevent.exact="sendReply()"
                                      @keydown.enter.shift.prevent="replyBody += '\n'"></textarea>
                            <button type="submit"
                                    class="shrink-0 w-10 h-10 rounded-xl flex items-center justify-center text-white transition-all hover:shadow-md disabled:opacity-40"
                                    style="background:linear-gradient(135deg,#7B61FF,#9B8BFF)"
                                    :disabled="!replyBody.trim() || sending">
                                <svg x-show="!sending" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                </svg>
                                <svg x-show="sending" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4z"/>
                                </svg>
                            </button>
                        </form>
                        <p class="text-[9px] text-gray-400 mt-1.5 pl-1">Enter to send · Shift+Enter for new line</p>
                    </div>

                </div>
            </template>
        </div>
    </div>

    {{-- New Message Modal --}}
    <div x-show="openCompose" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div @click="openCompose = false" class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg" @click.stop>
            <div class="flex items-center justify-between px-6 pt-5 pb-4 border-b border-gray-100">
                <div>
                    <h3 class="text-[#1E1B4B] font-bold text-base">New Message</h3>
                    <p class="text-gray-400 text-xs mt-0.5">Send a message to a reseller</p>
                </div>
                <button @click="openCompose = false" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form @submit.prevent="startThread()" class="px-6 py-5 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">To</label>
                    <select x-model="compose.reseller_id" class="form-input w-full text-sm" required>
                        <option value="">Select a reseller…</option>
                        @foreach($resellers as $reseller)
                            <option value="{{ $reseller->id }}">{{ $reseller->name }} · {{ $reseller->email }}</option>
                        @endforeach
                    </select>
                    @if($resellers->isEmpty())
                        <p class="text-xs text-amber-600 mt-1.5">No active resellers yet.</p>
                    @endif
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Message</label>
                    <textarea x-model="compose.body" rows="5" placeholder="Write your message…"
                              class="form-input w-full text-sm resize-none" required></textarea>
                </div>
                <div class="flex gap-3 justify-end">
                    <button type="button" @click="openCompose = false" class="btn-secondary text-sm">Cancel</button>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white transition-all disabled:opacity-50"
                            style="background:linear-gradient(135deg,#7B61FF,#9B8BFF)"
                            :disabled="sending || !compose.reseller_id || !compose.body.trim()">
                        <svg x-show="sending" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4z"/>
                        </svg>
                        <span x-text="sending ? 'Sending…' : 'Send Message'"></span>
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
    const hdrs = (extra = {}) => ({ 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...extra });
    const postHdrs = () => ({ ...hdrs(), 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF });

    return {
        threads:              [],
        activeThread:         null,
        activeMessages:       [],
        openCompose:          false,
        search:               '',
        replyBody:            '',
        sending:              false,
        loading:              false,
        loadingMessages:      false,
        compose:              { reseller_id: '', body: '' },
        activeTab:            'all',
        needsReplyCount:      0,
        activeThreadReminder: null,

        get filteredThreads() {
            const q    = this.search.toLowerCase();
            let   list = this.threads;
            if (this.activeTab === 'unread') list = list.filter(t => t.admin_unread > 0);
            if (!q) return list;
            return list.filter(t =>
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
            await this.loadNeedsReplyCount();
        },

        async loadNeedsReplyCount() {
            try {
                const r = await fetch('/api/message-reminders/active', { headers: hdrs() });
                const d = await r.json();
                this.needsReplyCount = d.count ?? 0;
            } catch(e) {}
        },

        async loadNeedsReply() {
            this.activeTab = 'needs_reply';
            this.loading   = true;
            try {
                const r = await fetch('/api/message-reminders/needs-reply', { headers: hdrs() });
                const d = await r.json();
                this.threads = d.threads ?? [];
            } finally { this.loading = false; }
        },

        async loadThreads() {
            this.activeTab = 'all';
            this.loading   = true;
            try {
                const r      = await fetch(`${BASE}/threads`, { headers: hdrs() });
                this.threads = await r.json();
            } finally { this.loading = false; }
        },

        async selectThread(thread) {
            this.activeThread         = { ...thread };
            this.activeMessages       = [];
            this.activeThreadReminder = null;
            this.loadingMessages      = true;

            try {
                const r = await fetch('/api/message-reminders/active', { headers: hdrs() });
                const d = await r.json();
                const m = (d.reminders ?? []).find(rem => rem.thread_id === thread.id);
                if (m) this.activeThreadReminder = m;
            } catch(e) {}

            try {
                const r = await fetch(`${BASE}/threads/${thread.id}`, { headers: hdrs() });
                const d = await r.json();
                this.activeThread   = { ...this.activeThread, ...d.thread };
                this.activeMessages = d.messages;
                const t = this.threads.find(x => x.id === thread.id);
                if (t) t.admin_unread = 0;
            } finally {
                this.loadingMessages = false;
                this.$nextTick(() => this.scrollToBottom());
            }
        },

        async resolveThreadReminder() {
            if (!this.activeThreadReminder) return;
            try {
                await fetch('/api/message-reminders/dismiss', {
                    method: 'POST', headers: postHdrs(),
                    body: JSON.stringify({ deduplication_key: this.activeThreadReminder.deduplication_key }),
                });
            } catch(e) {}
            this.activeThreadReminder = null;
        },

        async sendReply() {
            if (!this.replyBody.trim() || this.sending) return;
            this.sending = true;
            try {
                const r   = await fetch(`${BASE}/threads/${this.activeThread.id}`, {
                    method: 'POST', headers: postHdrs(),
                    body: JSON.stringify({ body: this.replyBody }),
                });
                const msg = await r.json();
                this.activeMessages.push(msg);
                this.updateThreadPreview(this.activeThread.id, msg);
                this.replyBody            = '';
                this.activeThreadReminder = null;
                this.needsReplyCount      = Math.max(0, this.needsReplyCount - 1);
                this.$nextTick(() => {
                    this.scrollToBottom();
                    this.$el.querySelector('textarea')?.dispatchEvent(new Event('input'));
                });
            } finally { this.sending = false; }
        },

        async startThread() {
            if (this.sending || !this.compose.reseller_id || !this.compose.body.trim()) return;
            this.sending = true;
            try {
                const r = await fetch(`${BASE}/threads`, {
                    method: 'POST', headers: postHdrs(),
                    body: JSON.stringify(this.compose),
                });
                const d = await r.json();
                this.openCompose = false;
                this.compose     = { reseller_id: '', body: '' };
                await this.loadThreads();
                const thread = this.threads.find(t => t.id === d.thread_id);
                if (thread) this.selectThread(thread);
            } finally { this.sending = false; }
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
