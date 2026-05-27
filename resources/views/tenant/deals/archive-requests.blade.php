@extends('layouts.app')
@section('title', 'Archive Requests')
@section('nav') @include('tenant._nav') @endsection

@section('content')
@php
$_badgeCounts = \Illuminate\Support\Facades\Cache::remember("subtab_badge_counts:{$tenant->id}", 60, fn() => [
    'expired'         => \App\Models\Lead::where('tenant_id', $tenant->id)->where('status','expired')->whereNull('deleted_at')->count(),
    'pending_archive' => \App\Models\DealApprovalRequest::where('tenant_id', $tenant->id)->where('type','deal_archive')->where('status','pending')->count(),
    'deleted_archived'=> \App\Models\Lead::withTrashed()->where('tenant_id', $tenant->id)->where(fn($q) => $q->where('status','archived')->orWhereNotNull('deleted_at'))->count(),
]);
$subtabCounts = array_merge($_badgeCounts, ['pending_archive' => $metrics['pending']]);
@endphp

<div class="space-y-5"
     x-data="{
         confirmModal: { open: false, type: '', requestId: '', dealName: '' },
         clarifyModal: { open: false, requestId: '' },
         clarifyMessage: '',
         clarifyDue: '',
         submitting: false,
         openApprove(id, name) { this.confirmModal = { open: true, type: 'approve', requestId: id, dealName: name }; },
         openReject(id, name)  { this.confirmModal = { open: true, type: 'reject',  requestId: id, dealName: name }; },
         openClarify(id) { this.clarifyModal = { open: true, requestId: id }; this.clarifyMessage = ''; this.clarifyDue = ''; },
     }">

    {{-- Subtab navigation --}}
    @include('tenant.deals._subtabs')

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700 flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700 flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('error') }}
        </div>
    @endif

    {{-- Metrics --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="card p-4">
            <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Pending</p>
            <p class="text-2xl font-bold text-orange-500 mt-1">{{ number_format($metrics['pending']) }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Approved</p>
            <p class="text-2xl font-bold text-green-600 mt-1">{{ number_format($metrics['approved']) }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Rejected</p>
            <p class="text-2xl font-bold text-red-500 mt-1">{{ number_format($metrics['rejected']) }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Clarification</p>
            <p class="text-2xl font-bold text-blue-500 mt-1">{{ number_format($metrics['clarification']) }}</p>
        </div>
    </div>

    {{-- Filter bar --}}
    <div class="card space-y-3">
        <form method="GET" action="{{ route('tenant.deals.archive-requests', $tenant->id) }}" class="flex flex-wrap gap-2 items-center">
            <div class="search-group flex-1 min-w-[180px]">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="q" value="{{ $search }}" placeholder="Search by deal name…">
            </div>
            <select name="status" class="pill-select">
                <option value="all" @selected($status === 'all')>All Statuses</option>
                <option value="pending" @selected($status === 'pending')>Pending</option>
                <option value="approved" @selected($status === 'approved')>Approved</option>
                <option value="rejected" @selected($status === 'rejected')>Rejected</option>
                <option value="clarification_requested" @selected($status === 'clarification_requested')>Clarification Requested</option>
            </select>
            <button type="submit" class="btn-primary">Filter</button>
            @if($search || ($status && $status !== 'pending'))
                <a href="{{ route('tenant.deals.archive-requests', $tenant->id) }}" class="btn-secondary">Clear</a>
            @endif
        </form>
    </div>

    {{-- Request list --}}
    <div class="card overflow-hidden">
        @if($requests->isEmpty())
            <div class="flex flex-col items-center justify-center py-16 text-center px-4">
                <div class="w-16 h-16 rounded-2xl bg-orange-50 flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-orange-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 4H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-2m-4-1v8m0 0l-3-3m3 3l3-3"/>
                    </svg>
                </div>
                <p class="text-gray-500 font-medium">No archive requests found</p>
                <p class="text-gray-400 text-sm mt-1">Referrers can request to archive their deals from the deal detail page.</p>
            </div>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($requests as $req)
                @php
                    $lead       = $req->lead;
                    $statusMap  = [
                        'pending'                  => ['bg-orange-100 text-orange-700', 'Pending'],
                        'approved'                 => ['bg-green-100 text-green-700', 'Approved'],
                        'rejected'                 => ['bg-red-100 text-red-700', 'Rejected'],
                        'clarification_requested'  => ['bg-blue-100 text-blue-700', 'Clarification Requested'],
                    ];
                    [$statusCls, $statusLabel] = $statusMap[$req->status] ?? ['bg-gray-100 text-gray-600', ucfirst($req->status)];
                @endphp
                <div class="p-4 hover:bg-gray-50/40 transition-colors">
                    <div class="flex items-start gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <a href="{{ route('tenant.deals.archive-requests.show', [$tenant->id, $req->id]) }}"
                                   class="font-semibold text-[#1E1B4B] hover:text-[#7B61FF] transition-colors truncate">
                                    {{ $lead?->name ?? 'Unknown Deal' }}
                                </a>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-medium {{ $statusCls }}">
                                    {{ $statusLabel }}
                                </span>
                            </div>
                            <div class="flex flex-wrap gap-x-4 gap-y-1 mt-1 text-xs text-gray-500">
                                @if($lead)
                                    <span>Stage: {{ ucwords(str_replace('_',' ',$lead->stage)) }}</span>
                                    <span>Referrer: {{ $lead->reseller_name ?: '—' }}</span>
                                    <span>Value: ₱{{ number_format((float)$lead->deal_value, 0) }}</span>
                                @endif
                                <span>Submitted {{ $req->created_at->diffForHumans() }}</span>
                            </div>
                            @if($req->reason)
                                <p class="mt-1.5 text-sm text-gray-600 bg-gray-50 rounded-lg px-3 py-2 line-clamp-2">
                                    "{{ $req->reason }}"
                                </p>
                            @endif
                            @if($req->clarification_message)
                                <p class="mt-1 text-xs text-blue-600 bg-blue-50 rounded-lg px-3 py-1.5">
                                    Clarification requested: {{ Str::limit($req->clarification_message, 100) }}
                                </p>
                            @endif
                        </div>

                        {{-- Actions --}}
                        @if($req->status === 'pending')
                        <div class="flex items-center gap-1.5 shrink-0">
                            <button @click="openApprove('{{ $req->id }}', '{{ addslashes($lead?->name ?? 'this deal') }}')"
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-green-600 text-white text-xs font-semibold hover:bg-green-700 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Approve
                            </button>
                            <button @click="openClarify('{{ $req->id }}')"
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-blue-100 text-blue-700 text-xs font-semibold hover:bg-blue-200 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Clarify
                            </button>
                            <button @click="openReject('{{ $req->id }}', '{{ addslashes($lead?->name ?? 'this deal') }}')"
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-red-100 text-red-700 text-xs font-semibold hover:bg-red-200 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                Reject
                            </button>
                        </div>
                        @else
                        <a href="{{ route('tenant.deals.archive-requests.show', [$tenant->id, $req->id]) }}"
                           class="text-xs text-[#7B61FF] hover:underline font-medium shrink-0">
                            View
                        </a>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>

            @if($requests->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">
                    {{ $requests->links() }}
                </div>
            @endif
        @endif
    </div>

    {{-- Confirm Approve / Reject modal --}}
    <div x-show="confirmModal.open" x-cloak
         class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
         role="dialog" aria-modal="true"
         @keydown.escape.window="confirmModal.open = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm" @click.stop>
            <div class="p-6 space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                         :class="confirmModal.type === 'approve' ? 'bg-green-100' : 'bg-red-100'">
                        <svg class="w-5 h-5" :class="confirmModal.type === 'approve' ? 'text-green-600' : 'text-red-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <template x-if="confirmModal.type === 'approve'">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </template>
                            <template x-if="confirmModal.type !== 'approve'">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </template>
                        </svg>
                    </div>
                    <div>
                        <p class="font-semibold text-[#1E1B4B]" x-text="(confirmModal.type === 'approve' ? 'Approve' : 'Reject') + ' archive request?'"></p>
                        <p class="text-sm text-gray-500" x-text="'Deal: ' + confirmModal.dealName"></p>
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <button @click="confirmModal.open = false" class="btn-secondary">Cancel</button>
                    <template x-if="confirmModal.type === 'approve'">
                        <form :action="`{{ url('/tenant/' . $tenant->id . '/approvals/`) }}${confirmModal.requestId}/approve`" method="POST" @submit="submitting = true">
                            @csrf
                            <button type="submit" :disabled="submitting"
                                    class="btn-primary bg-green-600 hover:bg-green-700">
                                <span x-show="!submitting">Confirm Approve</span>
                                <span x-show="submitting">Approving…</span>
                            </button>
                        </form>
                    </template>
                    <template x-if="confirmModal.type === 'reject'">
                        <form :action="`{{ url('/tenant/' . $tenant->id . '/approvals/`) }}${confirmModal.requestId}/reject`" method="POST" @submit="submitting = true">
                            @csrf
                            <button type="submit" :disabled="submitting"
                                    class="btn-primary bg-red-600 hover:bg-red-700">
                                <span x-show="!submitting">Confirm Reject</span>
                                <span x-show="submitting">Rejecting…</span>
                            </button>
                        </form>
                    </template>
                </div>
            </div>
        </div>
    </div>

    {{-- Clarify modal --}}
    <div x-show="clarifyModal.open" x-cloak
         class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
         role="dialog" aria-modal="true"
         @keydown.escape.window="clarifyModal.open = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md" @click.stop>
            <form :action="`{{ url('/tenant/' . $tenant->id . '/deals/archive-requests/') }}${clarifyModal.requestId}/clarify`"
                  method="POST" @submit="submitting = true">
                @csrf
                <div class="p-6 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-semibold text-[#1E1B4B]">Request Clarification</p>
                            <p class="text-sm text-gray-500">Ask the Referrer for more details.</p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Message <span class="text-red-500">*</span></label>
                        <textarea name="clarification_message" x-model="clarifyMessage" rows="3"
                                  class="form-input w-full resize-none"
                                  placeholder="What information do you need from the Referrer?" required maxlength="1000"></textarea>
                        <p class="text-xs text-gray-400 mt-1" x-text="clarifyMessage.length + '/1000'"></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Response Due By (optional)</label>
                        <input type="date" name="clarification_due_at" x-model="clarifyDue"
                               min="{{ now()->addDay()->format('Y-m-d') }}"
                               class="form-input w-full">
                    </div>
                </div>
                <div class="px-6 pb-6 flex justify-end gap-3">
                    <button type="button" @click="clarifyModal.open = false" class="btn-secondary">Cancel</button>
                    <button type="submit" :disabled="submitting || !clarifyMessage.trim()"
                            class="btn-primary">
                        <span x-show="!submitting">Send Clarification Request</span>
                        <span x-show="submitting">Sending…</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
