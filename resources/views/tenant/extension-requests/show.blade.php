@extends('layouts.app')
@section('title', 'Review Extension Batch — ' . ($batch->batch_reference ?? ''))
@section('nav') @include('tenant._nav') @endsection

@section('topbar-actions')
    <a href="{{ route('tenant.extension-requests.index', $tenantId) }}" class="btn-secondary text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        <span class="hidden sm:inline">All Batches</span>
    </a>
@endsection

@section('content')
@php
    $batchStatusColors = [
        'pending'             => ['bg' => 'bg-amber-100',   'text' => 'text-amber-700',   'dot' => 'bg-amber-500',   'label' => 'Pending Review'],
        'partially_approved'  => ['bg' => 'bg-blue-100',    'text' => 'text-blue-700',    'dot' => 'bg-blue-500',    'label' => 'Partially Approved'],
        'approved'            => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'dot' => 'bg-emerald-500', 'label' => 'Fully Approved'],
        'declined'            => ['bg' => 'bg-red-100',     'text' => 'text-red-700',     'dot' => 'bg-red-500',     'label' => 'Fully Declined'],
        'partially_declined'  => ['bg' => 'bg-orange-100',  'text' => 'text-orange-700',  'dot' => 'bg-orange-500',  'label' => 'Partially Declined'],
        'cancelled'           => ['bg' => 'bg-gray-100',    'text' => 'text-gray-500',    'dot' => 'bg-gray-400',    'label' => 'Cancelled'],
    ];
    $bc           = $batchStatusColors[$batch->status] ?? $batchStatusColors['pending'];
    $referrerName = $batch->requestedByReseller?->name ?? 'Unknown Referrer';
    $hasPending   = ($batch->pending_count + $batch->skipped_count) > 0;
    $isResolved   = in_array($batch->status, ['approved', 'declined', 'cancelled']);
    $pendingItems = $batch->items->whereIn('status', ['pending_review', 'skipped']);
@endphp

<script>
window.__bulkExtBatch = {
    id:       '{{ $batch->id }}',
    tenantId: '{{ $tenantId }}',
    csrf:     '{{ csrf_token() }}',
    baseApi:  '{{ url("/api") }}',
    requestedDays: {{ (int) $batch->requested_extension_days }},
};
</script>

<div class="space-y-5 max-w-4xl mx-auto"
     x-data="bulkExtReview()"
     x-init="init()">

    {{-- Alert toast --}}
    <div x-show="toast.show" x-cloak x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-end="opacity-0"
         class="fixed top-4 right-4 z-50 max-w-sm w-full px-4 py-3 rounded-2xl shadow-lg border text-sm font-medium flex items-center gap-2"
         :class="toast.type === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : (toast.type === 'warning' ? 'bg-amber-50 border-amber-200 text-amber-800' : 'bg-red-50 border-red-200 text-red-800')">
        <svg x-show="toast.type === 'success'" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <svg x-show="toast.type !== 'success'" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M12 3a9 9 0 110 18A9 9 0 0112 3z"/>
        </svg>
        <span x-text="toast.message"></span>
    </div>

    {{-- Header --}}
    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-violet-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-1">
                    <h2 class="text-[#1E1B4B] font-bold text-lg">{{ $batch->batch_reference }}</h2>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $bc['bg'] }} {{ $bc['text'] }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $bc['dot'] }}"></span>
                        <span x-text="batchStatusLabel || '{{ $bc['label'] }}'">{{ $bc['label'] }}</span>
                    </span>
                    @if($isResolved)
                    <span class="text-xs text-gray-400 italic">Read-only — already reviewed</span>
                    @endif
                </div>
                <div class="flex flex-wrap gap-3 text-xs text-gray-500">
                    <span>
                        <svg class="w-3 h-3 inline-block mr-0.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0M12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        Referrer: <span class="font-medium text-gray-700">{{ $referrerName }}</span>
                    </span>
                    <span>
                        <svg class="w-3 h-3 inline-block mr-0.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        Submitted {{ $batch->created_at->diffForHumans() }}
                    </span>
                    <span>
                        <svg class="w-3 h-3 inline-block mr-0.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ $batch->requested_extension_days }} days requested
                    </span>
                    @if($batch->resolved_at)
                    <span>
                        <svg class="w-3 h-3 inline-block mr-0.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Resolved {{ $batch->resolved_at->diffForHumans() }}
                    </span>
                    @endif
                </div>
                <p class="text-gray-600 text-sm mt-2 leading-relaxed">{{ $batch->shared_reason }}</p>
            </div>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 mt-4 pt-4 border-t border-gray-100">
            <div class="text-center p-2 rounded-xl bg-gray-50">
                <p class="text-xl font-bold text-[#1E1B4B]" x-text="counts.total">{{ $batch->total_items }}</p>
                <p class="text-[10px] text-gray-400 uppercase tracking-wide mt-0.5">Total</p>
            </div>
            <div class="text-center p-2 rounded-xl bg-amber-50">
                <p class="text-xl font-bold text-amber-600" x-text="counts.pending">{{ $batch->pending_count }}</p>
                <p class="text-[10px] text-gray-400 uppercase tracking-wide mt-0.5">Pending</p>
            </div>
            <div class="text-center p-2 rounded-xl bg-emerald-50">
                <p class="text-xl font-bold text-emerald-600" x-text="counts.approved">{{ $batch->approved_count }}</p>
                <p class="text-[10px] text-gray-400 uppercase tracking-wide mt-0.5">Approved</p>
            </div>
            <div class="text-center p-2 rounded-xl bg-red-50">
                <p class="text-xl font-bold text-red-500" x-text="counts.declined">{{ $batch->declined_count }}</p>
                <p class="text-[10px] text-gray-400 uppercase tracking-wide mt-0.5">Declined</p>
            </div>
            <div class="text-center p-2 rounded-xl bg-gray-50">
                <p class="text-xl font-bold text-gray-500" x-text="counts.skipped">{{ $batch->skipped_count }}</p>
                <p class="text-[10px] text-gray-400 uppercase tracking-wide mt-0.5">Skipped</p>
            </div>
        </div>
    </div>

    {{-- R Bunny guidance (only while pending) --}}
    @if($hasPending)
    <div class="card bg-violet-50 border-violet-100" x-show="pendingCount > 0" x-cloak>
        <div class="flex items-start gap-3">
            <div class="w-8 h-8 rounded-xl bg-violet-100 flex items-center justify-center shrink-0 mt-0.5">
                <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-violet-800">R Bunny says</p>
                <p class="text-xs text-violet-700 mt-0.5">
                    This request includes <strong>{{ $batch->total_items }} deals</strong>.
                    You can approve all, reject all, or decide deal by deal.
                    Use checkboxes to select specific deals, then choose <strong>Reject Selected + Approve Rest</strong> or <strong>Approve Selected + Reject Rest</strong> for mixed decisions.
                </p>
            </div>
        </div>
    </div>
    @endif

    {{-- Bulk action bar (only if pending items exist) --}}
    @if($hasPending)
    <div class="card" x-show="pendingCount > 0">
        <div class="flex flex-col gap-3">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="flex-1">
                    <p class="text-sm font-semibold text-[#1E1B4B]">Bulk actions</p>
                    <p class="text-xs text-gray-400 mt-0.5">Act on all pending items at once, or select deals below for mixed decisions.</p>
                </div>
                <div class="flex flex-wrap gap-2 shrink-0">
                    <button type="button" @click="openBulkModal('approve_all')"
                            :disabled="busy || pendingCount === 0"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-emerald-600 text-white hover:bg-emerald-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Approve All
                    </button>
                    <button type="button" @click="openBulkModal('skip_all')"
                            :disabled="busy || pendingCount === 0"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-gray-100 text-gray-700 hover:bg-gray-200 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
                        </svg>
                        Skip All
                    </button>
                    <button type="button" @click="openBulkModal('decline_all')"
                            :disabled="busy || pendingCount === 0"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-red-50 text-red-700 hover:bg-red-100 border border-red-200 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        Reject All
                    </button>
                </div>
            </div>

            {{-- Selection toolbar (visible when items are checked) --}}
            <div x-show="selectedIds.length > 0" x-cloak
                 class="flex flex-col sm:flex-row sm:items-center gap-2 pt-3 border-t border-gray-100">
                <div class="flex items-center gap-2 text-sm text-gray-600 flex-1">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-violet-100 text-violet-700 rounded-lg text-xs font-semibold">
                        <span x-text="selectedIds.length"></span> selected
                    </span>
                    <button type="button" @click="selectedIds = []" class="text-xs text-gray-400 hover:text-gray-600 underline">Clear</button>
                    <button type="button" @click="selectAllPending()" class="text-xs text-gray-400 hover:text-gray-600 underline">Select all pending &amp; skipped</button>
                </div>
                <div class="flex flex-wrap gap-2 shrink-0">
                    <button type="button" @click="openBulkModal('approve_selected')"
                            :disabled="busy || selectedIds.length === 0"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-emerald-600 text-white hover:bg-emerald-700 transition-colors disabled:opacity-50">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Approve Selected
                    </button>
                    <button type="button" @click="openBulkModal('decline_selected')"
                            :disabled="busy || selectedIds.length === 0"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-red-50 text-red-700 hover:bg-red-100 border border-red-200 transition-colors disabled:opacity-50">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        Reject Selected
                    </button>
                    <button type="button" @click="openBulkModal('reject_selected_approve_rest')"
                            :disabled="busy || selectedIds.length === 0"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-orange-50 text-orange-700 hover:bg-orange-100 border border-orange-200 transition-colors disabled:opacity-50">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                        </svg>
                        Reject Selected + Approve Rest
                    </button>
                    <button type="button" @click="openBulkModal('approve_selected_reject_rest')"
                            :disabled="busy || selectedIds.length === 0"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 transition-colors disabled:opacity-50">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                        </svg>
                        Approve Selected + Reject Rest
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Deal items --}}
    <div class="space-y-3">
        @foreach($batch->items as $item)
        @php
            $deal       = $item->lead;
            $isPending  = $item->status === 'pending_review';
            $isApproved = $item->status === 'approved';
            $isDeclined = $item->status === 'rejected';
            $isSkipped  = $item->status === 'skipped';
            $isActionable = $isPending || $isSkipped;

            $itemBg    = $isPending  ? 'bg-amber-50 border-amber-200'
                       : ($isApproved ? 'bg-emerald-50 border-emerald-200'
                       : ($isDeclined  ? 'bg-red-50 border-red-200'
                       : 'bg-gray-50 border-gray-200'));
        @endphp

        <div class="card border {{ $itemBg }} rounded-2xl"
             x-data="itemAction('{{ $item->id }}', '{{ $item->status }}', {{ (int)$item->requested_days }})"
             id="item-{{ $item->id }}">

            <div class="flex flex-col sm:flex-row sm:items-start gap-3">

                {{-- Checkbox (only for actionable items) --}}
                @if($isActionable)
                <div class="flex items-center justify-center sm:pt-0.5 shrink-0">
                    <input type="checkbox"
                           x-model="$root.selectedIds"
                           :value="'{{ $item->id }}'"
                           x-show="!resolved"
                           class="w-4 h-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500 cursor-pointer">
                </div>
                @endif

                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        @if($deal)
                        <a href="{{ route('tenant.deals.show', ['tenantId' => $tenantId, 'dealId' => $deal->id]) }}"
                           class="font-semibold text-[#1E1B4B] text-sm hover:underline">
                            {{ $deal->name }}
                        </a>
                        @else
                        <span class="font-semibold text-gray-400 text-sm italic">Deal removed</span>
                        @endif

                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium"
                              :class="statusClasses">
                            <span class="w-1.5 h-1.5 rounded-full" :class="statusDot"></span>
                            <span x-text="statusLabel">{{ $item->status_label }}</span>
                        </span>
                    </div>

                    @if($deal)
                    <div class="flex flex-wrap gap-3 text-xs text-gray-500">
                        <span>Stage: <span class="font-medium text-gray-700">{{ ucfirst(str_replace('_', ' ', $deal->stage)) }}</span></span>
                        <span>Days left at request: <span class="font-medium text-gray-700">{{ $item->current_days_left }}</span></span>
                        <span>Current days left: <span class="font-medium text-gray-700 {{ ($deal->days_left ?? 0) <= 3 ? 'text-red-600' : (($deal->days_left ?? 0) <= 7 ? 'text-amber-600' : '') }}">{{ $deal->days_left ?? 'N/A' }}</span></span>
                        @if($item->current_expiry_at)
                        <span>Req. expiry: <span class="font-medium text-gray-700">{{ $item->current_expiry_at->format('M j, Y') }}</span></span>
                        @endif
                        @if($isApproved && $item->approved_new_expiry_at)
                        <span class="text-emerald-700">New expiry: <span class="font-semibold">{{ $item->approved_new_expiry_at->format('M j, Y') }}</span></span>
                        @endif
                    </div>
                    @endif

                    @if($item->per_deal_note)
                    <p class="text-xs text-gray-500 mt-1 italic">"{{ $item->per_deal_note }}"</p>
                    @endif
                </div>

                {{-- Decision display (resolved items) --}}
                <div class="shrink-0 text-right min-w-[120px]">
                    @if($isApproved)
                    <p class="text-emerald-700 font-bold text-base">+{{ $item->approved_days }} days</p>
                    <p class="text-[11px] text-gray-400">{{ $item->reviewed_at?->diffForHumans() }}</p>
                    @elseif($isDeclined)
                    <p class="text-red-600 font-semibold text-sm">Declined</p>
                    @if($item->admin_note)
                    <p class="text-[11px] text-gray-500 mt-0.5 max-w-[160px] text-left sm:text-right">{{ \Illuminate\Support\Str::limit($item->admin_note, 60) }}</p>
                    @endif
                    <p class="text-[11px] text-gray-400">{{ $item->reviewed_at?->diffForHumans() }}</p>
                    @elseif($isSkipped)
                    <p class="text-gray-500 text-sm font-medium">Skipped</p>
                    <p class="text-[11px] text-gray-400">Can still be reviewed</p>
                    @else
                    <p class="text-amber-600 font-medium text-sm">Awaiting decision</p>
                    @endif
                </div>
            </div>

            {{-- Action panel for pending + skipped items --}}
            @if($isActionable)
            <div class="mt-3 pt-3 border-t border-gray-200/60" x-show="!resolved">

                {{-- Approve form --}}
                <div x-show="action === 'approve'" x-cloak class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <label class="text-xs font-medium text-gray-700 shrink-0">Approve with</label>
                        <div class="flex items-center gap-1 border border-gray-200 rounded-xl overflow-hidden">
                            <input type="number" x-model.number="approvedDays" min="1" max="90"
                                   class="w-16 px-2 py-1.5 text-sm text-center bg-white outline-none border-none">
                            <span class="px-2 py-1.5 text-xs text-gray-500 bg-gray-50 border-l border-gray-200">days</span>
                        </div>
                        <span class="text-xs text-gray-400">(requested: {{ $item->requested_days }}d)</span>
                    </div>
                    <input type="text" x-model="note" placeholder="Optional note to referrer…"
                           class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 focus:outline-none focus:border-violet-400">
                    <div class="flex gap-2">
                        <button type="button" @click="submitApprove()"
                                :disabled="busy || approvedDays < 1 || approvedDays > 90"
                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-emerald-600 text-white hover:bg-emerald-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg x-show="!busy" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <svg x-show="busy" x-cloak class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                            </svg>
                            <span x-text="busy ? 'Approving…' : 'Confirm Approve'"></span>
                        </button>
                        <button type="button" @click="action = null" class="px-3 py-2 rounded-xl text-xs font-medium text-gray-500 hover:bg-gray-100 transition-colors">Cancel</button>
                    </div>
                </div>

                {{-- Decline form --}}
                <div x-show="action === 'decline'" x-cloak class="space-y-2">
                    <textarea x-model="note" rows="2" required
                              placeholder="Reason for declining (required, sent to referrer)…"
                              class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 focus:outline-none focus:border-red-400 resize-none"></textarea>
                    <div class="flex gap-2">
                        <button type="button" @click="submitDecline()"
                                :disabled="busy || note.trim().length < 5"
                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-red-600 text-white hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg x-show="busy" x-cloak class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                            </svg>
                            <span x-text="busy ? 'Declining…' : 'Confirm Decline'"></span>
                        </button>
                        <button type="button" @click="action = null" class="px-3 py-2 rounded-xl text-xs font-medium text-gray-500 hover:bg-gray-100 transition-colors">Cancel</button>
                    </div>
                </div>

                {{-- Default action buttons --}}
                <div x-show="action === null" class="flex flex-wrap gap-2">
                    <button type="button" @click="action = 'approve'"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-emerald-600 text-white hover:bg-emerald-700 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Approve
                    </button>
                    <button type="button" @click="submitSkip()"
                            :disabled="busy"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-gray-100 text-gray-700 hover:bg-gray-200 transition-colors disabled:opacity-50">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
                        </svg>
                        Skip for Now
                    </button>
                    <button type="button" @click="action = 'decline'"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-red-50 text-red-700 hover:bg-red-100 border border-red-200 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        Decline
                    </button>
                </div>
            </div>
            @endif

            <p x-show="error" x-cloak class="text-xs text-red-600 mt-2" x-text="error"></p>
        </div>
        @endforeach
    </div>

    {{-- ── MODALS ──────────────────────────────────────────────────── --}}
    <div x-show="modal.show" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
         @click.self="closeModal()">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4 max-h-[90vh] overflow-y-auto">
            <h3 class="font-bold text-[#1E1B4B] text-base" x-text="modal.title"></h3>
            <p class="text-sm text-gray-500" x-text="modal.description"></p>

            {{-- APPROVE ALL / APPROVE SELECTED --}}
            <template x-if="['approve_all', 'approve_selected'].includes(modal.action)">
                <div class="space-y-3">
                    <div>
                        <label class="text-xs font-medium text-gray-700 block mb-1">Days to approve <span class="text-red-500">*</span></label>
                        <div class="flex items-center gap-2">
                            <input type="number" x-model.number="modal.approvedDays" min="1" max="90"
                                   class="w-24 px-3 py-2 border border-gray-200 rounded-xl text-sm text-center focus:outline-none focus:border-violet-400">
                            <span class="text-xs text-gray-400">days (1–90)</span>
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-700 block mb-1">Optional note</label>
                        <input type="text" x-model="modal.approvalNote" placeholder="Note to referrer (optional)…"
                               class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-violet-400">
                    </div>
                </div>
            </template>

            {{-- DECLINE ALL / DECLINE SELECTED --}}
            <template x-if="['decline_all', 'decline_selected'].includes(modal.action)">
                <div class="space-y-3">
                    <div>
                        <label class="text-xs font-medium text-gray-700 block mb-1">Rejection reason <span class="text-red-500">*</span></label>
                        <textarea x-model="modal.rejectionReason" rows="3"
                                  placeholder="Reason for declining (required, sent to referrer)…"
                                  class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-red-400 resize-none"></textarea>
                    </div>
                </div>
            </template>

            {{-- REJECT SELECTED + APPROVE REST --}}
            <template x-if="modal.action === 'reject_selected_approve_rest'">
                <div class="space-y-3">
                    <div class="rounded-xl bg-red-50 border border-red-100 p-3 text-xs text-red-700">
                        <span class="font-semibold" x-text="selectedIds.length"></span> selected deal<span x-show="selectedIds.length !== 1">s</span> will be <strong>rejected</strong>.
                    </div>
                    <div class="rounded-xl bg-emerald-50 border border-emerald-100 p-3 text-xs text-emerald-700">
                        All remaining pending deal<span x-show="pendingCount - selectedIds.length !== 1">s</span>
                        (<span x-text="Math.max(0, pendingCount - selectedIds.length)"></span>)
                        will be <strong>approved</strong>.
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-700 block mb-1">Rejection reason <span class="text-red-500">*</span></label>
                        <textarea x-model="modal.rejectionReason" rows="2"
                                  placeholder="Reason for rejecting selected deals…"
                                  class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-red-400 resize-none"></textarea>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-700 block mb-1">Days to approve (for rest)</label>
                        <div class="flex items-center gap-2">
                            <input type="number" x-model.number="modal.approvedDays" min="1" max="90"
                                   class="w-24 px-3 py-2 border border-gray-200 rounded-xl text-sm text-center focus:outline-none focus:border-violet-400">
                            <span class="text-xs text-gray-400">days</span>
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-700 block mb-1">Optional approval note</label>
                        <input type="text" x-model="modal.approvalNote" placeholder="Note for approved deals (optional)…"
                               class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-violet-400">
                    </div>
                </div>
            </template>

            {{-- APPROVE SELECTED + REJECT REST --}}
            <template x-if="modal.action === 'approve_selected_reject_rest'">
                <div class="space-y-3">
                    <div class="rounded-xl bg-emerald-50 border border-emerald-100 p-3 text-xs text-emerald-700">
                        <span class="font-semibold" x-text="selectedIds.length"></span> selected deal<span x-show="selectedIds.length !== 1">s</span> will be <strong>approved</strong>.
                    </div>
                    <div class="rounded-xl bg-red-50 border border-red-100 p-3 text-xs text-red-700">
                        All remaining pending deal<span x-show="pendingCount - selectedIds.length !== 1">s</span>
                        (<span x-text="Math.max(0, pendingCount - selectedIds.length)"></span>)
                        will be <strong>rejected</strong>.
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-700 block mb-1">Days to approve (for selected)</label>
                        <div class="flex items-center gap-2">
                            <input type="number" x-model.number="modal.approvedDays" min="1" max="90"
                                   class="w-24 px-3 py-2 border border-gray-200 rounded-xl text-sm text-center focus:outline-none focus:border-violet-400">
                            <span class="text-xs text-gray-400">days</span>
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-700 block mb-1">Rejection reason (for rest) <span class="text-red-500">*</span></label>
                        <textarea x-model="modal.rejectionReason" rows="2"
                                  placeholder="Reason for rejecting the remaining deals…"
                                  class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-red-400 resize-none"></textarea>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-700 block mb-1">Optional approval note</label>
                        <input type="text" x-model="modal.approvalNote" placeholder="Note for approved deals (optional)…"
                               class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-violet-400">
                    </div>
                </div>
            </template>

            {{-- Error --}}
            <p x-show="modal.error" x-cloak class="text-xs text-red-600" x-text="modal.error"></p>

            {{-- Result summary (after action completes) --}}
            <div x-show="modal.result" x-cloak
                 class="rounded-xl p-3 text-xs space-y-1"
                 :class="modal.result?.approved > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">
                <p class="font-semibold" x-text="modal.resultMessage"></p>
                <p x-show="modal.result?.failed > 0" class="text-gray-500" x-text="modal.result?.failed + ' item(s) could not be processed.'"></p>
            </div>

            <div class="flex gap-2 pt-1" x-show="!modal.result">
                <button type="button" @click="submitModal()"
                        :disabled="modal.busy || !isModalValid()"
                        class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl text-sm font-semibold transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        :class="['approve_all','approve_selected','approve_selected_reject_rest'].includes(modal.action)
                            ? 'bg-emerald-600 text-white hover:bg-emerald-700'
                            : (['decline_all','decline_selected','reject_selected_approve_rest'].includes(modal.action)
                                ? 'bg-red-600 text-white hover:bg-red-700'
                                : 'bg-violet-600 text-white hover:bg-violet-700')">
                    <svg x-show="modal.busy" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    <span x-text="modal.busy ? 'Processing…' : modal.confirmLabel"></span>
                </button>
                <button type="button" @click="closeModal()" :disabled="modal.busy"
                        class="px-4 py-2.5 rounded-xl text-sm font-medium text-gray-500 hover:bg-gray-100 transition-colors disabled:opacity-50">
                    Cancel
                </button>
            </div>
            <div class="flex gap-2 pt-1" x-show="modal.result">
                <button type="button" @click="closeModal(); location.reload()"
                        class="flex-1 px-4 py-2.5 rounded-xl text-sm font-semibold bg-violet-600 text-white hover:bg-violet-700 transition-colors">
                    Done — Refresh
                </button>
            </div>
        </div>
    </div>

</div>

<script>
function bulkExtReview() {
    const cfg = window.__bulkExtBatch;

    return {
        pendingCount: {{ $batch->pending_count + $batch->skipped_count }},
        counts: {
            total:    {{ $batch->total_items }},
            pending:  {{ $batch->pending_count }},
            approved: {{ $batch->approved_count }},
            declined: {{ $batch->declined_count }},
            skipped:  {{ $batch->skipped_count }},
        },
        batchStatusLabel: '',
        selectedIds: [],
        busy: false,
        toast: { show: false, type: 'success', message: '' },
        modal: {
            show: false, action: null, title: '', description: '',
            approvedDays: {{ $batch->requested_extension_days }},
            rejectionReason: '', approvalNote: '',
            confirmLabel: '', error: '', busy: false,
            result: null, resultMessage: '',
        },

        init() {
            window.addEventListener('item-resolved', (e) => {
                const { result, requestId, priorStatus } = e.detail;
                if (priorStatus === 'skipped') {
                    this.counts.skipped = Math.max(0, this.counts.skipped - 1);
                } else {
                    this.counts.pending = Math.max(0, this.counts.pending - 1);
                }
                if (result === 'approved') this.counts.approved++;
                if (result === 'declined') this.counts.declined++;
                if (result === 'skipped')  this.counts.skipped++;
                this.pendingCount = this.counts.pending + this.counts.skipped;
                this.selectedIds = this.selectedIds.filter(id => id !== requestId);
            });
        },

        showToast(type, msg) {
            this.toast = { show: true, type, message: msg };
            setTimeout(() => { this.toast.show = false; }, 5000);
        },

        selectAllPending() {
            this.selectedIds = @json($pendingItems->pluck('id')->values());
        },

        openBulkModal(action) {
            const labels = {
                approve_all:                 { title: 'Approve all pending deals?',           desc: `All ${this.pendingCount} pending deal(s) will be approved.`,                 confirm: 'Approve All' },
                decline_all:                 { title: 'Reject entire request?',               desc: `All ${this.pendingCount} pending deal(s) will be rejected. A reason is required.`, confirm: 'Reject All' },
                skip_all:                    { title: 'Skip all pending deals?',              desc: 'All pending deals will be skipped. They can still be reviewed later.',       confirm: 'Skip All' },
                approve_selected:            { title: 'Approve selected deals?',              desc: `${this.selectedIds.length} selected deal(s) will be approved.`,             confirm: 'Approve Selected' },
                decline_selected:            { title: 'Reject selected deals?',              desc: `${this.selectedIds.length} selected deal(s) will be rejected.`,             confirm: 'Reject Selected' },
                reject_selected_approve_rest:{ title: 'Reject selected + approve rest?',     desc: 'Selected deals will be rejected. All remaining pending deals will be approved.', confirm: 'Confirm Mixed Decision' },
                approve_selected_reject_rest:{ title: 'Approve selected + reject rest?',     desc: 'Selected deals will be approved. All remaining pending deals will be rejected.', confirm: 'Confirm Mixed Decision' },
            };
            const l = labels[action] || { title: action, desc: '', confirm: 'Confirm' };
            this.modal = {
                show: true, action, title: l.title, description: l.desc,
                approvedDays: cfg.requestedDays, rejectionReason: '', approvalNote: '',
                confirmLabel: l.confirm, error: '', busy: false, result: null, resultMessage: '',
            };
        },

        closeModal() {
            if (!this.modal.busy) this.modal.show = false;
        },

        isModalValid() {
            const m = this.modal;
            if (['approve_all','approve_selected'].includes(m.action)) {
                return m.approvedDays >= 1 && m.approvedDays <= 90;
            }
            if (['decline_all','decline_selected'].includes(m.action)) {
                return m.rejectionReason.trim().length >= 5;
            }
            if (['reject_selected_approve_rest','approve_selected_reject_rest'].includes(m.action)) {
                return m.rejectionReason.trim().length >= 5 && m.approvedDays >= 1 && m.approvedDays <= 90;
            }
            if (['skip_all','skip_selected'].includes(m.action)) {
                return true;
            }
            return true;
        },

        async submitModal() {
            const m = this.modal;
            m.error = '';
            if (!this.isModalValid()) {
                m.error = m.action.includes('decline') || m.action.includes('reject')
                    ? 'A rejection reason of at least 5 characters is required.'
                    : 'Please enter valid approved days (1–90).';
                return;
            }

            const endpoints = {
                approve_all:                  `${cfg.baseApi}/extension-requests/batches/${cfg.id}/approve-all`,
                decline_all:                  `${cfg.baseApi}/extension-requests/batches/${cfg.id}/decline-all`,
                skip_all:                     `${cfg.baseApi}/extension-requests/batches/${cfg.id}/skip-all`,
                approve_selected:             `${cfg.baseApi}/extension-requests/batches/${cfg.id}/approve-selected`,
                decline_selected:             `${cfg.baseApi}/extension-requests/batches/${cfg.id}/decline-selected`,
                reject_selected_approve_rest: `${cfg.baseApi}/extension-requests/batches/${cfg.id}/reject-selected-approve-rest`,
                approve_selected_reject_rest: `${cfg.baseApi}/extension-requests/batches/${cfg.id}/approve-selected-reject-rest`,
            };

            const bodies = {
                approve_all:                  { approved_days: m.approvedDays, reviewer_note: m.approvalNote || null },
                decline_all:                  { reviewer_note: m.rejectionReason },
                skip_all:                     { reviewer_note: m.approvalNote || null },
                approve_selected:             { request_ids: this.selectedIds, approved_days: m.approvedDays, reviewer_note: m.approvalNote || null },
                decline_selected:             { request_ids: this.selectedIds, reviewer_note: m.rejectionReason },
                reject_selected_approve_rest: { request_ids: this.selectedIds, approved_days: m.approvedDays, rejection_reason: m.rejectionReason, approval_note: m.approvalNote || null },
                approve_selected_reject_rest: { request_ids: this.selectedIds, approved_days: m.approvedDays, rejection_reason: m.rejectionReason, approval_note: m.approvalNote || null },
            };

            m.busy = true;
            try {
                const res = await fetch(endpoints[m.action], {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': cfg.csrf,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(bodies[m.action]),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || data.error || 'Action failed.');

                const approved = data.approved ?? data.processed ?? 0;
                const declined = data.declined ?? 0;
                const failed   = data.failed ?? 0;

                m.result        = { approved, declined, failed };
                m.resultMessage = data.message || `Done: ${approved} approved, ${declined} rejected.`;
                m.busy          = false;

                this.selectedIds = [];
                this.showToast('success', m.resultMessage);
            } catch (e) {
                m.error = e.message;
                m.busy  = false;
            }
        },
    };
}

function itemAction(requestId, initialStatus, requestedDays) {
    const cfg = window.__bulkExtBatch;

    const statusMap = {
        pending_review: { classes: 'bg-amber-100 text-amber-700',   dot: 'bg-amber-400',   label: 'Pending Review' },
        approved:       { classes: 'bg-emerald-100 text-emerald-700', dot: 'bg-emerald-400', label: 'Approved' },
        rejected:       { classes: 'bg-red-100 text-red-700',       dot: 'bg-red-400',     label: 'Declined' },
        skipped:        { classes: 'bg-gray-100 text-gray-500',     dot: 'bg-gray-300',    label: 'Skipped for Now' },
    };

    return {
        requestId,
        status: initialStatus,
        requestedDays,
        action: null,
        approvedDays: requestedDays,
        note: '',
        busy: false,
        error: '',
        resolved: initialStatus === 'approved' || initialStatus === 'rejected',

        get statusClasses() { return (statusMap[this.status] || statusMap.pending_review).classes; },
        get statusDot()     { return (statusMap[this.status] || statusMap.pending_review).dot; },
        get statusLabel()   { return (statusMap[this.status] || statusMap.pending_review).label; },

        async apiPost(url, body) {
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': cfg.csrf,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(body),
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || data.error || 'Action failed.');
            return data;
        },

        async submitApprove() {
            if (this.approvedDays < 1 || this.approvedDays > 90) {
                this.error = 'Days must be between 1 and 90.'; return;
            }
            this.busy = true; this.error = '';
            try {
                await this.apiPost(`${cfg.baseApi}/bulk-extension-requests/${this.requestId}/approve`, {
                    approved_days: this.approvedDays,
                    reviewer_note: this.note || null,
                });
                const priorStatus = this.status;
                this.status   = 'approved';
                this.resolved = true;
                this.action   = null;
                window.dispatchEvent(new CustomEvent('item-resolved', { detail: { result: 'approved', requestId: this.requestId, priorStatus } }));
            } catch (e) {
                this.error = e.message;
            } finally {
                this.busy = false;
            }
        },

        async submitDecline() {
            if (!this.note.trim()) { this.error = 'A reason is required.'; return; }
            this.busy = true; this.error = '';
            try {
                await this.apiPost(`${cfg.baseApi}/bulk-extension-requests/${this.requestId}/decline`, {
                    reviewer_note: this.note,
                });
                const priorStatus = this.status;
                this.status   = 'rejected';
                this.resolved = true;
                this.action   = null;
                window.dispatchEvent(new CustomEvent('item-resolved', { detail: { result: 'declined', requestId: this.requestId, priorStatus } }));
            } catch (e) {
                this.error = e.message;
            } finally {
                this.busy = false;
            }
        },

        async submitSkip() {
            this.busy = true; this.error = '';
            try {
                await this.apiPost(`${cfg.baseApi}/bulk-extension-requests/${this.requestId}/skip`, {});
                const priorStatus = this.status;
                this.status = 'skipped';
                this.action = null;
                window.dispatchEvent(new CustomEvent('item-resolved', { detail: { result: 'skipped', requestId: this.requestId, priorStatus } }));
            } catch (e) {
                this.error = e.message;
            } finally {
                this.busy = false;
            }
        },
    };
}
</script>
@endsection
