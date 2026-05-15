@extends('layouts.partner')
@section('title', 'Messages')
@section('nav') @include('partner._nav') @endsection

@section('content')
<div x-data="partnerMessages()" x-init="init()" class="flex flex-col" style="height:calc(100dvh - 88px); min-height:400px;">
    <div class="card p-0 overflow-hidden flex flex-1 min-h-0">

        {{-- Left: Thread list (hidden on mobile when chat open) --}}
        <div :class="mobilePane === 'list' ? 'flex' : 'hidden lg:flex'"
             class="w-full lg:w-72 border-r border-gray-100 flex-col shrink-0 min-h-0">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between shrink-0">
                <div>
                    <h2 class="text-sm font-bold text-[#1E1B4B]">Messages</h2>
                    <p class="text-[10px] text-gray-400 mt-0.5">Deal-scoped conversations</p>
                </div>
            </div>
            {{-- Tabs --}}
            <div class="flex border-b border-gray-100 shrink-0">
                <button @click="msgTab = 'deals'"
                        :class="msgTab === 'deals' ? 'text-blue-600 border-b-2 border-blue-500 font-semibold' : 'text-gray-400 hover:text-gray-600'"
                        class="flex-1 text-[11px] py-2.5 transition-colors">Deal Threads</button>
                <button @click="msgTab = 'admin'"
                        :class="msgTab === 'admin' ? 'text-blue-600 border-b-2 border-blue-500 font-semibold' : 'text-gray-400 hover:text-gray-600'"
                        class="flex-1 text-[11px] py-2.5 transition-colors relative">
                    Contact Admin
                    <span x-show="adminDirectUnread > 0"
                          class="absolute top-1.5 right-1 w-3.5 h-3.5 rounded-full bg-red-500 text-white text-[8px] font-bold flex items-center justify-center"
                          x-text="adminDirectUnread > 9 ? '9+' : adminDirectUnread"></span>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto">
                {{-- Deal threads tab --}}
                <template x-if="msgTab === 'deals'">
                    <div>
                        @forelse($dealThreads as $thread)
                        <button @click="selectThread('{{ $thread->id }}')"
                                :class="activeThreadId === '{{ $thread->id }}' ? 'bg-blue-50 border-l-[3px] border-l-blue-500' : 'border-l-[3px] border-l-transparent'"
                                class="w-full text-left px-3 py-3 hover:bg-gray-50 transition-colors border-b border-gray-50/70">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-xs shrink-0">
                                    {{ strtoupper(substr($thread->deal->name ?? 'D', 0, 2)) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-1">
                                        <span class="text-xs font-semibold text-[#1E1B4B] truncate">{{ $thread->deal->name ?? 'Deal' }}</span>
                                        <span class="text-[9px] text-gray-400 shrink-0">{{ $thread->last_message_at?->diffForHumans() }}</span>
                                    </div>
                                    <p class="text-[11px] text-gray-400 truncate mt-0.5">{{ $thread->last_message_preview ?? 'No messages yet' }}</p>
                                </div>
                                @if(($thread->partner_unread ?? 0) > 0)
                                <span class="w-4 h-4 rounded-full bg-blue-500 text-white text-[8px] font-bold flex items-center justify-center shrink-0">
                                    {{ $thread->partner_unread > 9 ? '9+' : $thread->partner_unread }}
                                </span>
                                @endif
                            </div>
                        </button>
                        @empty
                        <div class="px-4 py-8 text-center">
                            <img src="/images/mascots/r-bunny-sleeping.webp" alt="" aria-hidden="true" class="w-12 h-12 object-contain mx-auto mb-2 opacity-50">
                            <p class="text-xs text-gray-400">No deal conversations yet.</p>
                            <p class="text-xs text-gray-400 mt-1">View a deal to start messaging your referrer.</p>
                        </div>
                        @endforelse
                    </div>
                </template>

                {{-- Admin direct message tab --}}
                <template x-if="msgTab === 'admin'">
                    <div>
                        @if($directThreads->isEmpty())
                        <div class="px-4 py-6 text-center">
                            <div class="w-12 h-12 rounded-2xl bg-purple-50 flex items-center justify-center mx-auto mb-2">
                                <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                                </svg>
                            </div>
                            <p class="text-xs font-medium text-gray-600">Message the Admin Team</p>
                            <p class="text-[11px] text-gray-400 mt-1">Have a question or need help? Send a direct message to the admin.</p>
                        </div>
                        @else
                        @foreach($directThreads as $thread)
                        <button @click="selectThread('{{ $thread->id }}')"
                                :class="activeThreadId === '{{ $thread->id }}' ? 'bg-purple-50 border-l-[3px] border-l-purple-500' : 'border-l-[3px] border-l-transparent'"
                                class="w-full text-left px-3 py-3 hover:bg-gray-50 transition-colors border-b border-gray-50/70">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-purple-700 font-bold text-xs shrink-0">A</div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-1">
                                        <span class="text-xs font-semibold text-[#1E1B4B] truncate">Admin / Support</span>
                                        <span class="text-[9px] text-gray-400 shrink-0">{{ $thread->last_message_at?->diffForHumans() }}</span>
                                    </div>
                                    <p class="text-[11px] text-gray-400 truncate mt-0.5">{{ $thread->last_message_preview ?? 'No messages yet' }}</p>
                                </div>
                                @if(($thread->partner_unread ?? 0) > 0)
                                <span class="w-4 h-4 rounded-full bg-purple-500 text-white text-[8px] font-bold flex items-center justify-center shrink-0">
                                    {{ $thread->partner_unread > 9 ? '9+' : $thread->partner_unread }}
                                </span>
                                @endif
                            </div>
                        </button>
                        @endforeach
                        @endif
                    </div>
                </template>
            </div>
        </div>

        {{-- Right: Chat area --}}
        <div :class="mobilePane === 'chat' ? 'flex' : 'hidden lg:flex'"
             class="flex-1 flex-col min-w-0">

            <template x-if="!activeThreadId && !isPendingNew && !isPendingDirect">
                <div class="flex-1 flex flex-col items-center justify-center text-center p-8 h-full">
                    <div class="w-14 h-14 rounded-2xl bg-blue-50 flex items-center justify-center mb-3">
                        <svg class="w-7 h-7 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                        </svg>
                    </div>
                    <h3 class="text-[#1E1B4B] font-semibold text-sm">Select a conversation</h3>
                    <p class="text-gray-400 text-xs mt-1 max-w-xs">
                        Choose a deal thread from the list, or switch to <strong>Contact Admin</strong> to send a direct message to the workspace team.
                    </p>
                    <button @click="startDirectAdminMessage()"
                            class="mt-3 inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold text-white transition-all hover:shadow-md"
                            style="background:linear-gradient(135deg,#7B61FF,#9B8BFF)">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                        </svg>
                        Message the Admin
                    </button>
                </div>
            </template>

            <template x-if="activeThreadId || isPendingNew || isPendingDirect">
                <div class="flex flex-col h-full min-h-0">

                    {{-- Thread header --}}
                    <div class="px-3 sm:px-4 py-3 border-b border-gray-100 flex items-center gap-2 shrink-0">
                        <button @click="mobilePane = 'list'" class="lg:hidden p-1.5 rounded-lg text-gray-400 hover:bg-gray-100">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>
                        <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-xs shrink-0"
                             x-text="activeThreadName.slice(0,2).toUpperCase()"></div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm text-[#1E1B4B] truncate" x-text="activeThreadName"></p>
                            <p class="text-[10px] text-gray-400" x-text="activeThreadType === 'direct' ? 'Direct message · Admin/Support' : 'Deal conversation · Referrer'"></p>
                        </div>
                        <span class="hidden sm:inline-flex items-center gap-1 text-[10px] text-gray-400 bg-gray-50 rounded-full px-2.5 py-1 shrink-0">
                            <span class="w-1.5 h-1.5 rounded-full bg-blue-400"></span>
                            In-app
                        </span>
                    </div>

                    {{-- Messages --}}
                    <div class="flex-1 overflow-y-auto p-3 sm:p-4 space-y-3 min-h-0" id="partnerMessageArea">

                        <template x-if="loadingMessages">
                            <div class="flex justify-center py-8">
                                <svg class="w-4 h-4 animate-spin text-blue-500" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4z"/>
                                </svg>
                            </div>
                        </template>

                        <template x-if="!loadingMessages && messages.length === 0">
                            <div class="text-center text-gray-400 text-xs py-10">
                                No messages yet — send the first one below.
                            </div>
                        </template>

                        <template x-for="msg in messages" :key="msg.id">
                            <div :class="msg.sender_type === 'partner' ? 'flex justify-end' : 'flex justify-start'">
                                <div :class="msg.sender_type === 'partner'
                                        ? 'text-white rounded-2xl rounded-br-sm'
                                        : 'bg-gray-100 text-[#1E1B4B] rounded-2xl rounded-bl-sm'"
                                     :style="msg.sender_type === 'partner' ? 'background:linear-gradient(135deg,#2563EB,#3B82F6)' : ''"
                                     class="max-w-[78%] sm:max-w-[70%] px-3.5 sm:px-4 py-2.5">
                                    <p class="text-sm leading-relaxed whitespace-pre-wrap" x-text="msg.body"></p>
                                    <p :class="msg.sender_type === 'partner' ? 'text-white/50' : 'text-gray-400'"
                                       class="text-[10px] mt-1 text-right" x-text="msg.created_ago"></p>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Send error (persistent, dismissible) --}}
                    <div x-show="sendError"
                         class="mx-3 mb-1 flex items-center gap-2 px-3 py-2 bg-red-50 border border-red-200 rounded-xl text-xs text-red-600 shrink-0">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01"/>
                        </svg>
                        <span class="flex-1" x-text="sendError"></span>
                        <button @click="sendError = null" class="shrink-0 text-red-400 hover:text-red-600">✕</button>
                    </div>

                    {{-- Compose --}}
                    <div class="p-2.5 sm:p-3 border-t border-gray-100 shrink-0">
                        <form @submit.prevent="send()" class="flex gap-2 items-end">
                            <textarea x-model="body"
                                      :placeholder="isPendingDirect ? 'Send a message to Admin/Support…' : (isPendingNew ? 'Send your first message to your Referrer…' : 'Type a message…')"
                                      rows="1"
                                      class="flex-1 text-sm bg-gray-50 border border-gray-200 rounded-xl px-3 sm:px-3.5 py-2.5 resize-none outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"
                                      style="min-height:40px; max-height:120px;"
                                      @input="$el.style.height='40px'; $el.style.height=$el.scrollHeight+'px'"
                                      @keydown.enter.prevent.exact="send()"
                                      @keydown.enter.shift.prevent="body += '\n'"></textarea>
                            <button type="submit"
                                    class="shrink-0 w-10 h-10 rounded-xl flex items-center justify-center text-white transition-all hover:shadow-md disabled:opacity-40"
                                    style="background:linear-gradient(135deg,#2563EB,#3B82F6)"
                                    :disabled="!body.trim() || sending">
                                <svg x-show="!sending" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                </svg>
                                <svg x-show="sending" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4z"/>
                                </svg>
                            </button>
                        </form>
                    </div>

                </div>
            </template>
        </div>

    </div>
</div>

@push('scripts')
<script>
function partnerMessages() {
    const CSRF = document.querySelector('meta[name=csrf-token]')?.content ?? '';
    const hdrs = () => ({ 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' });
    const postHdrs = () => ({ ...hdrs(), 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF });

    @php
        $threadData = $threads->map(fn($t) => [
            'id'             => $t->id,
            'deal_id'        => $t->deal_id,
            'deal_name'      => optional($t->deal)->name ?? 'Deal',
            'last_preview'   => $t->last_message_preview,
            'partner_unread' => $t->partner_unread ?? 0,
        ])->values()->all();
    @endphp
    const serverThreads = @json($threadData);

    // Pending deal = deal_id param was provided but no thread exists yet
    const pendingDeal = @json($pendingDeal);

    return {
        threads: serverThreads,
        activeThreadId: null,
        activeThreadName: '',
        activeThreadType: 'deal',
        activeDealId: null,
        isPendingNew: false,
        isPendingDirect: false,
        messages: [],
        body: '',
        sending: false,
        sendError: null,
        loadingMessages: false,
        mobilePane: 'list',
        msgTab: 'deals',

        get adminDirectUnread() {
            @php
                $directUnreadCount = $directThreads->sum('partner_unread');
            @endphp
            return {{ $directUnreadCount }};
        },

        init() {
            const urlParams = new URLSearchParams(window.location.search);
            const dealId = urlParams.get('deal_id');
            if (dealId) {
                const t = this.threads.find(x => String(x.deal_id) === String(dealId));
                if (t) {
                    this.selectThread(t.id);
                } else if (pendingDeal && String(pendingDeal.id) === String(dealId)) {
                    this.startPendingConversation(pendingDeal);
                }
            } else if (urlParams.get('tab') === 'admin') {
                this.msgTab = 'admin';
                this.startDirectAdminMessage();
            } else if (this.threads.length > 0 && window.innerWidth >= 1024) {
                this.selectThread(this.threads[0].id);
            }
        },

        startDirectAdminMessage() {
            // Check if direct thread already exists in serverThreads (deal_id = null)
            const existing = serverThreads.find(t => t.deal_id === null || t.deal_id === undefined);
            if (existing) {
                this.selectThread(existing.id);
                return;
            }
            this.activeThreadId   = null;
            this.activeDealId     = null;
            this.activeThreadName = 'Admin / Support';
            this.activeThreadType = 'direct';
            this.isPendingDirect  = true;
            this.isPendingNew     = false;
            this.messages         = [];
            this.mobilePane       = 'chat';
            this.msgTab           = 'admin';
        },

        startPendingConversation(deal) {
            this.activeThreadId   = null;
            this.activeDealId     = deal.id;
            this.activeThreadName = deal.name;
            this.activeThreadType = 'deal';
            this.isPendingNew     = true;
            this.isPendingDirect  = false;
            this.messages         = [];
            this.mobilePane       = 'chat';
        },

        async selectThread(threadId) {
            this.activeThreadId   = threadId;
            this.isPendingNew     = false;
            this.isPendingDirect  = false;
            this.mobilePane       = 'chat';
            const t = this.threads.find(x => x.id === threadId);
            this.activeDealId     = t?.deal_id ?? null;
            this.activeThreadName = t?.deal_id ? (t?.deal_name ?? 'Deal') : 'Admin / Support';
            this.activeThreadType = t?.deal_id ? 'deal' : 'direct';
            this.messages         = [];
            this.loadingMessages  = true;
            try {
                const r = await fetch(`/partner/messages/thread/${threadId}`, { headers: hdrs() });
                const d = await r.json();
                this.messages = d.messages ?? [];
                if (t) t.partner_unread = 0;
            } catch (e) {
                this.messages = [];
                this.$dispatch('show-toast', { type: 'error', message: 'Unable to load messages. Please try again.' });
            } finally {
                this.loadingMessages = false;
                this.$nextTick(() => this.scrollToBottom());
            }
        },

        async send() {
            if (!this.body.trim() || this.sending) return;
            if (!this.isPendingDirect && !this.activeDealId && !this.activeThreadId) return;
            this.sending = true;
            const msgBody = this.body;
            this.body = '';

            // Route to direct endpoint if sending to admin with no deal
            const isDirectAdmin = this.isPendingDirect || this.activeThreadType === 'direct';
            const url    = isDirectAdmin ? '/partner/messages/send-direct' : '/partner/messages/send';
            const payload = isDirectAdmin
                ? { body: msgBody }
                : { deal_id: this.activeDealId, thread_id: this.activeThreadId, body: msgBody };

            try {
                const r = await fetch(url, {
                    method: 'POST',
                    headers: postHdrs(),
                    body: JSON.stringify(payload),
                });
                const d = await r.json();

                if (!r.ok) {
                    this.body      = msgBody;
                    this.sendError = d?.message || d?.error || 'Failed to send. Please try again.';
                    return;
                }
                this.sendError = null;

                if (d.message?.body) {
                    this.messages.push(d.message);

                    if ((this.isPendingNew || this.isPendingDirect) && d.thread_id) {
                        this.activeThreadId  = d.thread_id;
                        this.isPendingNew    = false;
                        this.isPendingDirect = false;
                        this.threads.unshift({
                            id:             d.thread_id,
                            deal_id:        this.activeDealId,
                            deal_name:      this.activeThreadName,
                            last_preview:   msgBody.slice(0, 60),
                            partner_unread: 0,
                        });
                    } else {
                        const t = this.threads.find(x => x.id === this.activeThreadId);
                        if (t) t.last_preview = msgBody.slice(0, 60);
                    }

                    this.$nextTick(() => {
                        this.scrollToBottom();
                        this.$el.querySelector('textarea')?.dispatchEvent(new Event('input'));
                    });
                }
            } catch (e) {
                this.body      = msgBody;
                this.sendError = 'Network error. Please check your connection and try again.';
            } finally {
                this.sending = false;
            }
        },

        scrollToBottom() {
            const el = document.getElementById('partnerMessageArea');
            if (el) el.scrollTop = el.scrollHeight;
        },
    };
}
</script>
@endpush
@endsection
