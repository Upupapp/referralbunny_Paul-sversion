@extends('layouts.reseller')
@section('title', 'Messages')
@section('nav') @include('reseller._nav') @endsection

@section('content')
<div x-data="resellerChat()" x-init="init()" class="flex flex-col" style="height:calc(100dvh - 88px); min-height:400px;">

    <div class="card p-0 overflow-hidden flex flex-1 min-h-0">

        {{-- ── Left: Info panel ────────────────────────────── --}}
        <div class="w-64 border-r border-gray-100 flex flex-col shrink-0 min-h-0 hidden sm:flex">
            <div class="px-4 py-4 border-b border-gray-100">
                <h2 class="text-sm font-bold text-[#1E1B4B]">Messages</h2>
                <p class="text-[10px] text-gray-400 mt-0.5">Your conversation with {{ $tenant->name }}</p>
            </div>

            {{-- Tenant card --}}
            <div class="px-4 py-4">
                <div class="flex items-center gap-3 p-3 bg-teal-50 rounded-xl border border-teal-100">
                    <div class="w-9 h-9 rounded-full bg-teal-600 flex items-center justify-center text-white font-bold text-sm shrink-0">
                        {{ strtoupper(substr($tenant->name, 0, 1)) }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-[#1E1B4B] truncate">{{ $tenant->name }}</p>
                        <p class="text-[10px] text-gray-400">Workspace Admin</p>
                    </div>
                </div>
            </div>

            <div class="px-4 py-2">
                <p class="text-[10px] text-gray-400 leading-relaxed">
                    Send messages to your workspace admin here. They'll be notified and can reply directly.
                </p>
            </div>

            {{-- Reminder banner if has active reminder --}}
            <div id="reminder-banner" class="hidden mx-4 mt-2 p-3 bg-amber-50 border border-amber-200 rounded-xl">
                <p class="text-xs text-amber-700 font-medium">Your admin is waiting for a reply.</p>
            </div>
        </div>

        {{-- ── Right: Chat ──────────────────────────────────── --}}
        <div class="flex-1 flex flex-col min-h-0">

            {{-- Thread header --}}
            <div class="px-3 sm:px-4 py-3 border-b border-gray-100 flex items-center gap-2.5 shrink-0">
                <div class="w-8 h-8 rounded-full bg-teal-100 flex items-center justify-center text-teal-700 font-bold text-xs shrink-0">
                    {{ strtoupper(substr($tenant->name, 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-sm text-[#1E1B4B] truncate">{{ $tenant->name }}</p>
                    <p class="text-[10px] text-gray-400">Workspace Admin · In-app</p>
                </div>
                <span class="inline-flex items-center gap-1 text-[10px] text-gray-400 bg-gray-50 rounded-full px-2 py-1 shrink-0">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-400"></span>
                    <span class="hidden sm:inline">Connected</span>
                </span>
            </div>

            {{-- Messages scroll area --}}
            <div class="flex-1 overflow-y-auto p-4 space-y-3 min-h-0" id="messageArea">

                @if($messages->isEmpty())
                    <div class="flex flex-col items-center justify-center h-full text-center py-10">
                        <div class="w-12 h-12 rounded-2xl bg-teal-50 flex items-center justify-center mb-3">
                            <svg class="w-6 h-6 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-[#1E1B4B]">No messages yet</p>
                        <p class="text-xs text-gray-400 mt-1 max-w-xs">Send a message to your workspace admin below. They'll be notified and can reply.</p>
                    </div>
                @else
                    <template x-for="msg in messages" :key="msg.id">
                        <div :class="msg.sender_type === 'reseller' ? 'flex justify-end' : 'flex justify-start'">
                            <div :class="msg.sender_type === 'reseller'
                                    ? 'text-white rounded-2xl rounded-br-sm'
                                    : 'bg-gray-100 text-[#1E1B4B] rounded-2xl rounded-bl-sm'"
                                 :style="msg.sender_type === 'reseller' ? 'background:linear-gradient(135deg,#0D9488,#14B8A6)' : ''"
                                 class="max-w-[78%] sm:max-w-[72%] px-3.5 sm:px-4 py-2.5">
                                <p class="text-sm leading-relaxed whitespace-pre-wrap" x-text="msg.body"></p>
                                <p :class="msg.sender_type === 'reseller' ? 'text-white/50' : 'text-gray-400'"
                                   class="text-[10px] mt-1 text-right"
                                   x-text="msg.created_ago ?? ''"></p>
                            </div>
                        </div>
                    </template>
                @endif

                {{-- New messages appended via Alpine --}}
                <template x-for="msg in newMessages" :key="msg.id">
                    <div :class="msg.sender_type === 'reseller' ? 'flex justify-end' : 'flex justify-start'">
                        <div :class="msg.sender_type === 'reseller'
                                ? 'text-white rounded-2xl rounded-br-sm'
                                : 'bg-gray-100 text-[#1E1B4B] rounded-2xl rounded-bl-sm'"
                             :style="msg.sender_type === 'reseller' ? 'background:linear-gradient(135deg,#0D9488,#14B8A6)' : ''"
                             class="max-w-[78%] sm:max-w-[72%] px-3.5 sm:px-4 py-2.5">
                            <p class="text-sm leading-relaxed whitespace-pre-wrap" x-text="msg.body"></p>
                            <p :class="msg.sender_type === 'reseller' ? 'text-white/50' : 'text-gray-400'"
                               class="text-[10px] mt-1 text-right"
                               x-text="msg.created_ago ?? 'just now'"></p>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Compose --}}
            <div class="p-2.5 sm:p-3 border-t border-gray-100 shrink-0">
                @if(!$thread)
                    <p class="text-xs text-gray-400 mb-2 text-center">Start the conversation — your admin will be notified.</p>
                @endif
                {{-- Send error banner --}}
                <div x-show="sendError" x-transition style="display:none"
                     class="flex items-center gap-2 px-3 py-2 mb-2 bg-red-50 border border-red-200 rounded-xl text-xs text-red-700">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span x-text="sendError"></span>
                </div>
                <form @submit.prevent="send()" class="flex gap-2 items-end">
                    <textarea x-model="body" placeholder="Type a message…" rows="1"
                              class="flex-1 text-sm bg-gray-50 border border-gray-200 rounded-xl px-3 sm:px-3.5 py-2.5 resize-none outline-none focus:ring-2 focus:ring-teal-400/20 focus:border-teal-400 transition-all"
                              style="min-height:40px; max-height:120px;"
                              @input="$el.style.height='40px'; $el.style.height=$el.scrollHeight+'px'"
                              @keydown.enter.prevent.exact="send()"
                              @keydown.enter.shift.prevent="body += '\n'"></textarea>
                    <button type="submit"
                            class="shrink-0 w-10 h-10 rounded-xl flex items-center justify-center text-white transition-all hover:shadow-md disabled:opacity-40"
                            style="background:linear-gradient(135deg,#0D9488,#14B8A6)"
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
    </div>
</div>

@push('scripts')
<script>
function resellerChat() {
    const TENANT_ID  = '{{ $tenant->id }}';
    const THREAD_ID  = '{{ $thread?->id ?? "" }}';
    const BASE       = `/tenant/${TENANT_ID}/messages`;
    const CSRF       = document.querySelector('meta[name=csrf-token]')?.content ?? '';
    const hdrs       = () => ({ 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' });
    const postHdrs   = () => ({ ...hdrs(), 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF });

    // Pre-populate from server-rendered messages
    @php
        $serverMsgData = $messages->map(fn($m) => [
            'id'          => (string) $m->id,
            'sender_type' => (string) $m->sender_type,
            'body'        => (string) $m->body,
            'created_ago' => $m->created_at?->diffForHumans() ?? '',
        ])->values()->all();
    @endphp
    const serverMessages = {!! json_encode($serverMsgData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};

    return {
        messages:    serverMessages,
        newMessages: [],
        body:        '',
        sending:     false,
        threadId:    THREAD_ID,
        _knownIds:   serverMessages.map(m => m.id),

        init() {
            this.$nextTick(() => this.scrollToBottom());
            if (THREAD_ID) {
                setInterval(() => this.pollMessages(), 15000);
            }
        },

        async pollMessages() {
            if (!THREAD_ID) return;
            try {
                const r = await fetch(`${BASE}/threads/${THREAD_ID}/messages`, { headers: hdrs() });
                if (!r.ok) { this._pollFails = (this._pollFails || 0) + 1; return; }
                this._pollFails = 0;
                const d = await r.json();
                const serverMsgs = d.messages ?? [];
                const existingIds = new Set(this._knownIds);
                let added = 0;
                serverMsgs.forEach(msg => {
                    if (!existingIds.has(msg.id)) {
                        this._knownIds.push(msg.id);
                        msg.created_ago = msg.created_at;
                        this.newMessages.push(msg);
                        added++;
                    }
                });
                if (added > 0) this.$nextTick(() => this.scrollToBottom());
            } catch(e) {
                this._pollFails = (this._pollFails || 0) + 1;
                if (this._pollFails >= 3) this.sendError = 'Connection issue — messages may be delayed.';
            }
        },

        sendError: '',

        async send() {
            if (!this.body.trim() || this.sending) return;
            this.sending   = true;
            this.sendError = '';
            const msgBody  = this.body;
            this.body      = '';

            try {
                let url, method, payload;

                if (this.threadId) {
                    url     = `${BASE}/threads/${this.threadId}`;
                    method  = 'POST';
                    payload = { body: msgBody };
                } else {
                    url     = `${BASE}/threads`;
                    method  = 'POST';
                    payload = {
                        reseller_id: '{{ auth("reseller")->id() }}',
                        body:        msgBody,
                        sender_type: 'reseller',
                    };
                }

                const r    = await fetch(url, { method, headers: postHdrs(), body: JSON.stringify(payload) });
                const data = await r.json();

                if (!r.ok) {
                    this.body      = msgBody; // restore text so user can retry
                    this.sendError = data?.message || data?.error || 'Failed to send message. Please try again.';
                    return;
                }

                const newMsg = data.message ?? data;
                if (newMsg?.body) {
                    newMsg.sender_type = 'reseller';
                    newMsg.created_ago = 'just now';
                    this.newMessages.push(newMsg);
                    if (newMsg.id) this._knownIds.push(newMsg.id);
                }

                if (!this.threadId && data.thread_id) {
                    this.threadId = data.thread_id;
                }

                this.$nextTick(() => {
                    this.scrollToBottom();
                    this.$el.querySelector('textarea')?.dispatchEvent(new Event('input'));
                });
            } catch(e) {
                this.body      = msgBody;
                this.sendError = 'Network error. Please check your connection and try again.';
            } finally {
                this.sending = false;
            }
        },

        scrollToBottom() {
            const el = document.getElementById('messageArea');
            if (el) el.scrollTop = el.scrollHeight;
        },
    };
}
</script>
@endpush
@endsection
