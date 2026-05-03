@extends('layouts.app')
@section('title', 'Messaging')
@section('nav') @include('platform._nav') @endsection

@section('topbar-actions')
    <button x-data @click="$dispatch('open-compose')" class="btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        <span class="hidden sm:inline">New Message</span>
    </button>
@endsection

@section('content')
<div class="space-y-5" x-data="messaging()" x-init="init()" @open-compose.window="showCompose = true">

    {{-- Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach([
            ['Total Sent', 'total', 'bg-purple-100', 'text-purple-600'],
            ['Delivered', 'delivered', 'bg-emerald-100', 'text-emerald-600'],
            ['Failed', 'failed', 'bg-red-100', 'text-red-600'],
            ['Pending', 'pending', 'bg-orange-100', 'text-orange-600'],
        ] as [$label, $key, $bg, $color])
        <div class="kpi-card">
            <div>
                <p class="text-gray-500 text-sm">{{ $label }}</p>
                <p class="text-2xl font-bold text-[#1E1B4B] mt-1" x-text="stats.{{ $key }} ?? '—'"></p>
            </div>
            <div class="kpi-icon {{ $bg }}">
                <svg class="w-5 h-5 {{ $color }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                </svg>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Filters + Table --}}
    <div class="card p-0 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="flex-1">
                    <p class="font-semibold text-[#1E1B4B] text-sm">Message Log</p>
                    <p class="text-xs text-gray-400 mt-0.5" x-text="messages.length + ' messages'"></p>
                </div>
                <div class="flex flex-col sm:flex-row gap-3">
                    <select x-model="filter.tenant" @change="load()" class="form-input sm:w-48">
                        <option value="">All Tenants</option>
                        @foreach($tenants as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                        @endforeach
                    </select>
                    <select x-model="filter.channel" @change="load()" class="form-input sm:w-36">
                        <option value="">All Channels</option>
                        <option value="email">Email</option>
                        <option value="sms">SMS</option>
                        <option value="in_app">In-App</option>
                    </select>
                    <select x-model="filter.status" @change="load()" class="form-input sm:w-36">
                        <option value="">All Status</option>
                        <option value="sent">Sent</option>
                        <option value="delivered">Delivered</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead><tr class="table-head">
                    <th>Recipient</th><th>Subject</th><th>Channel</th><th>Status</th><th>Date</th>
                </tr></thead>
                <tbody>
                    <template x-if="loading">
                        <tr><td colspan="5" class="py-10 text-center text-gray-400">Loading...</td></tr>
                    </template>
                    <template x-if="!loading && messages.length === 0">
                        <tr><td colspan="5" class="py-10 text-center text-gray-400">No messages found</td></tr>
                    </template>
                    <template x-for="msg in messages" :key="msg.id">
                        <tr class="table-row">
                            <td x-text="msg.recipient_name || msg.recipient_email || '—'"></td>
                            <td class="max-w-xs truncate" x-text="msg.subject || msg.body?.slice(0,50) || '—'"></td>
                            <td>
                                <span class="badge badge-purple capitalize" x-text="msg.channel || 'in_app'"></span>
                            </td>
                            <td>
                                <span :class="{'badge':true,'badge-green':msg.status==='delivered','badge-red':msg.status==='failed','badge-orange':msg.status==='pending','badge-blue':msg.status==='sent'}"
                                      x-text="msg.status || 'sent'"></span>
                            </td>
                            <td class="text-gray-400 text-xs" x-text="msg.created_at ? new Date(msg.created_at).toLocaleDateString() : '—'"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Compose Modal --}}
    <div x-show="showCompose" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-[#1E1B4B]">New Message</h3>
                <button @click="showCompose = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="form-label">Tenant</label>
                    <select x-model="compose.tenant_id" class="form-input">
                        <option value="">Select tenant</option>
                        @foreach($tenants as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Channel</label>
                    <select x-model="compose.channel" class="form-input">
                        <option value="in_app">In-App</option>
                        <option value="email">Email</option>
                        <option value="sms">SMS</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Subject</label>
                    <input type="text" x-model="compose.subject" class="form-input" placeholder="Message subject">
                </div>
                <div>
                    <label class="form-label">Message *</label>
                    <textarea x-model="compose.body" rows="4" class="form-input" placeholder="Write your message..."></textarea>
                </div>
                <div class="flex justify-end gap-3">
                    <button @click="showCompose = false" class="btn-secondary">Cancel</button>
                    <button @click="send()" :disabled="sending" class="btn-primary">
                        <span x-text="sending ? 'Sending...' : 'Send Message'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function messaging() {
    return {
        messages: [], stats: {}, loading: true, showCompose: false, sending: false,
        filter: { tenant: '', channel: '', status: '' },
        compose: { tenant_id: '', channel: 'in_app', subject: '', body: '' },

        async init() {
            await this.load();
            this.stats = {
                total: this.messages.length,
                delivered: this.messages.filter(m => m.status === 'delivered').length,
                failed: this.messages.filter(m => m.status === 'failed').length,
                pending: this.messages.filter(m => m.status === 'pending').length,
            };
        },

        async load() {
            this.loading = true;
            const params = new URLSearchParams();
            if (this.filter.tenant) params.set('tenant_id', this.filter.tenant);
            if (this.filter.channel) params.set('channel', this.filter.channel);
            if (this.filter.status) params.set('status', this.filter.status);
            const res = await fetch(`/api/messages?${params}`);
            this.messages = await res.json();
            this.loading = false;
        },

        async send() {
            if (!this.compose.body) return;
            this.sending = true;
            try {
                await fetch('/api/messages', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify(this.compose),
                });
                this.showCompose = false;
                this.compose = { tenant_id: '', channel: 'in_app', subject: '', body: '' };
                await this.load();
            } finally {
                this.sending = false;
            }
        },
    }
}
</script>
@endsection
