@extends('layouts.app')
@section('title', 'Lead Detail')
@section('nav')
    @include('tenant._nav')
@endsection

@section('content')
<div class="space-y-5" x-data="leadDetail('{{ $leadId }}', '{{ $tenant->id }}')" x-init="init()">

    <a href="{{ route('tenant.leads', $tenant->id) }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Back to Leads
    </a>

    <template x-if="lead">
        <div class="space-y-5">
            {{-- Header --}}
            <div class="card">
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-[#7B61FF] font-bold text-lg shrink-0" style="background:#EDE9FE" x-text="lead.name?.slice(0,2).toUpperCase()"></div>
                    <div class="flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-bold text-[#1E1B4B]" x-text="lead.name"></h2>
                            <span :class="{'badge':true,'badge-green':lead.status==='active','badge-orange':lead.status==='expiring','badge-red':lead.status==='expired','badge-gray':true}" x-text="lead.status"></span>
                        </div>
                        <p class="text-gray-500 text-sm mt-0.5 capitalize" x-text="'Stage: ' + (lead.stage?.replace('_',' ') || '—')"></p>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-2xl font-bold text-[#1E1B4B]" x-text="lead.deal_value ? '₱' + (lead.deal_value/1000000).toFixed(2) + 'M' : '₱0'"></p>
                        <p class="text-xs text-gray-400">Deal Value</p>
                    </div>
                </div>

                {{-- Stage progress --}}
                <div class="mt-5">
                    <div class="flex items-center gap-0">
                        <template x-for="(stage, i) in stages" :key="stage.key">
                            <div class="flex-1 flex flex-col items-center gap-1">
                                <div :class="stageIndex >= i ? 'bg-[#7B61FF] text-white' : 'bg-gray-100 text-gray-400'"
                                     class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-colors"
                                     x-text="i+1"></div>
                                <span class="text-xs text-gray-500 text-center leading-tight" x-text="stage.label"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 mt-4">
                    <button @click="moveStage()" x-show="stageIndex < stages.length - 1" :disabled="moving"
                            class="btn-primary text-sm" x-text="moving ? 'Moving...' : 'Move to Next Stage'"></button>
                    <button @click="showReassign = true" class="btn-secondary text-sm">Reassign</button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

                {{-- Details --}}
                <div class="card space-y-3">
                    <h3 class="font-semibold text-[#1E1B4B]">Lead Details</h3>
                    <div class="flex justify-between text-sm"><span class="text-gray-400">Referrer</span><span class="font-medium" x-text="lead.reseller_name || '—'"></span></div>
                    <div class="flex justify-between text-sm" x-show="lead.data?.province"><span class="text-gray-400">Province</span><span class="font-medium" x-text="lead.data?.province"></span></div>
                    <div class="flex justify-between text-sm" x-show="lead.data?.municipality"><span class="text-gray-400">Municipality</span><span class="font-medium" x-text="lead.data?.municipality"></span></div>
                    <div class="flex justify-between text-sm"><span class="text-gray-400">Commission</span><span :class="{'badge':true,'badge-green':lead.commission_status==='paid','badge-orange':lead.commission_status==='locked','badge-blue':lead.commission_status==='pending'}" x-text="lead.commission_status || '—'"></span></div>
                    <div class="flex justify-between text-sm"><span class="text-gray-400">Days Left</span><span class="font-medium" x-text="(lead.days_left || 0) + ' days'"></span></div>
                </div>

                {{-- Notes --}}
                <div class="card space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-[#1E1B4B]">Notes</h3>
                        <button @click="showNote = true" class="text-xs text-purple-600 hover:text-purple-700 font-medium">+ Add</button>
                    </div>
                    <template x-for="note in (lead.notes || [])" :key="note.id">
                        <div class="p-3 bg-[#F0EFFA] rounded-xl">
                            <p class="text-sm text-gray-700 break-words" x-html="$rbLinkify(note.text)"></p>
                            <p class="text-xs text-gray-400 mt-1" x-text="note.author + ' · ' + new Date(note.created_at).toLocaleDateString()"></p>
                        </div>
                    </template>
                    <template x-if="!lead.notes || lead.notes.length === 0">
                        <p class="text-gray-400 text-sm text-center py-3">No notes yet</p>
                    </template>
                </div>
            </div>

            {{-- History --}}
            <div class="card">
                <h3 class="font-semibold text-[#1E1B4B] mb-4">Activity History</h3>
                <div class="space-y-3">
                    <template x-for="h in (lead.history || [])" :key="h.id">
                        <div class="flex items-start gap-3">
                            <div class="w-2 h-2 rounded-full bg-purple-400 mt-1.5 shrink-0"></div>
                            <div>
                                <p class="text-sm text-gray-700" x-text="h.action"></p>
                                <p class="text-xs text-gray-400 mt-0.5" x-text="new Date(h.date || h.created_at).toLocaleDateString()"></p>
                            </div>
                        </div>
                    </template>
                    <template x-if="!lead.history || lead.history.length === 0">
                        <p class="text-gray-400 text-sm text-center py-3">No history yet</p>
                    </template>
                </div>
            </div>
        </div>
    </template>

    {{-- Loading --}}
    <template x-if="!lead">
        <div class="card text-center py-12 text-gray-400">Loading lead...</div>
    </template>

    {{-- Add Note Modal --}}
    <div x-show="showNote" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-[#1E1B4B]">Add Note</h3>
                <button @click="showNote = false" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <div class="p-6 space-y-4">
                <div><label class="form-label">Note</label><textarea x-model="noteText" rows="3" class="form-input" placeholder="Write your note..."></textarea></div>
                <div class="flex justify-end gap-3">
                    <button @click="showNote = false" class="btn-secondary">Cancel</button>
                    <button @click="addNote()" :disabled="saving" class="btn-primary" x-text="saving ? 'Saving...' : 'Save Note'"></button>
                </div>
            </div>
        </div>
    </div>

    {{-- Reassign Modal --}}
    <div x-show="showReassign" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-[#1E1B4B]">Reassign Lead</h3>
                <button @click="showReassign = false" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <div class="p-6 space-y-4">
                <div><label class="form-label">New Referrer Name</label><input type="text" x-model="reassignName" class="form-input" placeholder="Referrer name"></div>
                <div class="flex justify-end gap-3">
                    <button @click="showReassign = false" class="btn-secondary">Cancel</button>
                    <button @click="reassign()" :disabled="saving" class="btn-primary" x-text="saving ? 'Reassigning...' : 'Reassign'"></button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function leadDetail(leadId, tenantId) {
    return {
        lead: null, saving: false, moving: false,
        showNote: false, showReassign: false,
        noteText: '', reassignName: '',
        stages: [
            { key:'introduction', label:'Intro' },
            { key:'presentation', label:'Presentation' },
            { key:'contract_sent', label:'Contract' },
            { key:'signed', label:'Signed' },
            { key:'paid', label:'Paid' },
        ],

        get stageIndex() {
            return this.stages.findIndex(s => s.key === this.lead?.stage) ?? 0;
        },

        async init() {
            const res = await fetch(`/api/leads/${leadId}`);
            this.lead = await res.json();
        },

        async moveStage() {
            this.moving = true;
            try {
                const csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
                const res  = await fetch(`/api/leads/${leadId}/stage`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const data = await res.json();
                if (data.id) this.lead = data;
            } finally { this.moving = false; }
        },

        async addNote() {
            if (!this.noteText) return;
            this.saving = true;
            try {
                const res = await fetch(`/api/leads/${leadId}/notes`, {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ text: this.noteText, author: 'Admin' }),
                });
                const note = await res.json();
                if (!this.lead.notes) this.lead.notes = [];
                this.lead.notes.unshift(note);
                this.showNote = false;
                this.noteText = '';
            } finally { this.saving = false; }
        },

        async reassign() {
            if (!this.reassignName) return;
            this.saving = true;
            try {
                const csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
                const res  = await fetch(`/api/leads/${leadId}/reassign`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ reseller_name: this.reassignName }),
                });
                const data = await res.json();
                if (data.id) {
                    this.lead = data;
                    this.showReassign = false;
                    this.reassignName = '';
                }
            } finally { this.saving = false; }
        },
    }
}
</script>
@endsection

