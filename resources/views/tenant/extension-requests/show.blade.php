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
    $bc = $batchStatusColors[$batch->status] ?? $batchStatusColors['pending'];
    $referrerName = $batch->requestedByReseller?->name ?? 'Unknown Referrer';
    $hasPending = $batch->pending_count > 0;
@endphp

<script>
window.__bulkExtBatch = {
    id:       '{{ $batch->id }}',
    tenantId: '{{ $tenantId }}',
    csrf:     '{{ csrf_token() }}',
    baseApi:  '{{ url("/api") }}',
};
</script>

<div class="space-y-5 max-w-3xl mx-auto"
     x-data="bulkExtReview()"
     x-init="init()">

    {{-- Alert toast --}}
    <div x-show="toast.show" x-cloak x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-end="opacity-0"
         class="fixed top-4 right-4 z-50 max-w-sm w-full px-4 py-3 rounded-2xl shadow-lg border text-sm font-medium flex items-center gap-2"
         :class="toast.type === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-red-50 border-red-200 text-red-800'">
        <svg x-show="toast.type === 'success'" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <svg x-show="toast.type === 'error'" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
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
                        <span x-text="batchStatus || '{{ $bc['label'] }}'">{{ $bc['label'] }}</span>
                    </span>
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
                </div>
                <p class="text-gray-500 text-sm mt-2">{{ $batch->shared_reason }}</p>
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

    {{-- Bulk action bar (only if pending items exist) --}}
    @if($hasPending)
    <div class="card" x-show="pendingCount > 0">
        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
            <div class="flex-1">
                <p class="text-sm font-semibold text-[#1E1B4B]">Bulk actions</p>
                <p class="text-xs text-gray-400 mt-0.5">Apply an action to all pending items at once, or act on individual deals below.</p>
            </div>
            <div class="flex flex-wrap gap-2 shrink-0">
                <button @click="openBulkAction('approve_all')"
                        :disabled="busy || pendingCount === 0"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-emerald-600 text-white hover:bg-emerald-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Approve All Pending
                </button>
                <button @click="openBulkAction('skip_all')"
                        :disabled="busy || pendingCount === 0"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-gray-100 text-gray-700 hover:bg-gray-200 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
                    </svg>
                    Skip All Pending
                </button>
                <button @click="openBulkAction('decline_all')"
                        :disabled="busy || pendingCount === 0"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-red-50 text-red-700 hover:bg-red-100 border border-red-200 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Decline All Pending
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Deal items --}}
    <div class="space-y-3">
        @foreach($batch->items as $item)
        @php
            $deal = $item->lead;
            $isPending = $item->status === 'pending_review';
            $isApproved = $item->status === 'approved';
            $isDeclined = $item->status === 'rejected';
            $isSkipped  = $item->status === 'skipped';

            $itemBg     = $isPending ? 'bg-amber-50 border-amber-200' : ($isApproved ? 'bg-emerald-50 border-emerald-200' : ($isDeclined ? 'bg-red-50 border-red-200' : 'bg-gray-50 border-gray-200'));
            $statusDot  = $isPending ? 'bg-amber-400' : ($isApproved ? 'bg-emerald-400' : ($isDeclined ? 'bg-red-400' : 'bg-gray-300'));
            $statusText = $isPending ? 'text-amber-700' : ($isApproved ? 'text-emerald-700' : ($isDeclined ? 'text-red-700' : 'text-gray-500'));
            $statusLabel= $isPending ? 'Pending Review' : ($isApproved ? 'Approved' : ($isDeclined ? 'Declined' : 'Skipped for Now'));
        @endphp

        <div class="card border {{ $itemBg }} rounded-2xl"
             x-data="itemAction('{{ $item->id }}', '{{ $item->status }}', {{ (int)$item->requested_days }})"
             id="item-{{ $item->id }}">

            {{-- Item header --}}
            <div class="flex flex-col sm:flex-row sm:items-start gap-3">
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
                            <span x-text="statusLabel">{{ $statusLabel }}</span>
                        </span>
                    </div>

                    @if($deal)
                    <div class="flex flex-wrap gap-3 text-xs text-gray-500">
                        <span>Stage: <span class="font-medium text-gray-700">{{ ucfirst(str_replace('_', ' ', $deal->stage)) }}</span></span>
                        <span>Days left at request: <span class="font-medium text-gray-700">{{ $item->current_days_left }}</span></span>
                        <span>Current days left: <span class="font-medium text-gray-700">{{ $deal->days_left ?? 'N/A' }}</span></span>
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
                    <p class="text-[11px] text-gray-400">Approved {{ $item->reviewed_at?->diffForHumans() }}</p>
                    @elseif($isDeclined)
                    <p class="text-red-600 font-semibold text-sm">Declined</p>
                    @if($item->admin_note)
                    <p class="text-[11px] text-gray-500 mt-0.5 max-w-[160px]">{{ \Illuminate\Support\Str::limit($item->admin_note, 60) }}</p>
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
            @if($isPending || $isSkipped)
            <div class="mt-3 pt-3 border-t border-gray-200/60" x-show="!resolved">

                {{-- Approve form --}}
                <div x-show="action === 'approve'" x-cloak class="space-y-2">
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-medium text-gray-700 shrink-0">Approve with</label>
                        <div class="flex items-center gap-1 border border-gray-200 rounded-xl overflow-hidden">
                            <input type="number" x-model.number="approvedDays" min="1" max="90"
                                   class="w-16 px-2 py-1.5 text-sm text-center bg-white outline-none border-none"
                                   placeholder="{{ $item->requested_days }}">
                            <span class="px-2 py-1.5 text-xs text-gray-500 bg-gray-50 border-l border-gray-200">days</span>
                        </div>
                        <span class="text-xs text-gray-400">(requested: {{ $item->requested_days }}d)</span>
                    </div>
                    <input type="text" x-model="note" placeholder="Optional note to referrer…"
                           class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 focus:outline-none focus:border-violet-400">
                    <div class="flex gap-2">
                        <button @click="submitApprove()"
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
                        <button @click="action = null" class="px-3 py-2 rounded-xl text-xs font-medium text-gray-500 hover:bg-gray-100 transition-colors">Cancel</button>
                    </div>
                </div>

                {{-- Decline form --}}
                <div x-show="action === 'decline'" x-cloak class="space-y-2">
                    <textarea x-model="note" rows="2" required
                              placeholder="Reason for declining (required, sent to referrer)…"
                              class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 focus:outline-none focus:border-red-400 resize-none"></textarea>
                    <div class="flex gap-2">
                        <button @click="submitDecline()"
                                :disabled="busy || note.trim().length < 5"
                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-red-600 text-white hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg x-show="!busy" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            <svg x-show="busy" x-cloak class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                            </svg>
                            <span x-text="busy ? 'Declining…' : 'Confirm Decline'"></span>
                        </button>
                        <button @click="action = null" class="px-3 py-2 rounded-xl text-xs font-medium text-gray-500 hover:bg-gray-100 transition-colors">Cancel</button>
                    </div>
                </div>

                {{-- Default action buttons --}}
                <div x-show="action === null" class="flex flex-wrap gap-2">
                    <button @click="action = 'approve'"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-emerald-600 text-white hover:bg-emerald-700 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Approve
                    </button>
                    <button @click="submitSkip()"
                            :disabled="busy"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-gray-100 text-gray-700 hover:bg-gray-200 transition-colors disabled:opacity-50">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
                        </svg>
                        Skip for Now
                    </button>
                    <button @click="action = 'decline'"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-red-50 text-red-700 hover:bg-red-100 border border-red-200 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        Decline
                    </button>
                </div>

            </div>
            @endif

            {{-- Error display --}}
            <p x-show="error" x-cloak class="text-xs text-red-600 mt-2" x-text="error"></p>
        </div>
        @endforeach
    </div>

    {{-- Bulk action modal --}}
    <div x-show="bulkModal.show" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
         @click.self="bulkModal.show = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 space-y-4">
            <h3 class="font-bold text-[#1E1B4B] text-base" x-text="bulkModal.title"></h3>
            <p class="text-sm text-gray-500" x-text="bulkModal.description"></p>

            <div x-show="bulkModal.action === 'approve_all'" class="space-y-2">
                <label class="text-xs font-medium text-gray-700">Days to approve</label>
                <div class="flex items-center gap-2">
                    <input type="number" x-model.number="bulkModal.approvedDays" min="1" max="90"
                           class="w-24 px-3 py-2 border border-gray-200 rounded-xl text-sm text-center focus:outline-none focus:border-violet-400">
                    <span class="text-xs text-gray-400">days (1–90)</span>
                </div>
                <input type="text" x-model="bulkModal.note" placeholder="Optional note…"
                       class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-violet-400">
            </div>

            <div x-show="bulkModal.action === 'decline_all'" class="space-y-2">
                <label class="text-xs font-medium text-gray-700">Reason for declining <span class="text-red-500">*</span></label>
                <textarea x-model="bulkModal.note" rows="3"
                          placeholder="Reason for declining all (required, sent to referrer)…"
                          class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-red-400 resize-none"></textarea>
            </div>

            <div class="flex gap-2 pt-1">
                <button @click="submitBulkAction()"
                        :disabled="bulkModal.busy || (bulkModal.action === 'approve_all' && (bulkModal.approvedDays < 1 || bulkModal.approvedDays > 90)) || (bulkModal.action === 'decline_all' && bulkModal.note.trim().length < 5)"
                        class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl text-sm font-semibold transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        :class="bulkModal.action === 'approve_all' ? 'bg-emerald-600 text-white hover:bg-emerald-700' : (bulkModal.action === 'decline_all' ? 'bg-red-600 text-white hover:bg-red-700' : 'bg-gray-700 text-white hover:bg-gray-800')">
                    <svg x-show="bulkModal.busy" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    <span x-text="bulkModal.busy ? 'Processing…' : bulkModal.confirmLabel"></span>
                </button>
                <button @click="bulkModal.show = false" :disabled="bulkModal.busy"
                        class="px-4 py-2.5 rounded-xl text-sm font-medium text-gray-500 hover:bg-gray-100 transition-colors disabled:opacity-50">
                    Cancel
                </button>
            </div>
            <p x-show="bulkModal.error" x-cloak class="text-xs text-red-600" x-text="bulkModal.error"></p>
        </div>
    </div>

</div>

<script>
function bulkExtReview() {
    const cfg = window.__bulkExtBatch;

    return {
        pendingCount: {{ $batch->pending_count }},
        counts: {
            total:    {{ $batch->total_items }},
            pending:  {{ $batch->pending_count }},
            approved: {{ $batch->approved_count }},
            declined: {{ $batch->declined_count }},
            skipped:  {{ $batch->skipped_count }},
        },
        batchStatus: '',
        busy: false,
        toast: { show: false, type: 'success', message: '' },
        bulkModal: {
            show: false, action: null, title: '', description: '',
            approvedDays: {{ $batch->requested_extension_days }}, note: '',
            confirmLabel: '', error: '', busy: false,
        },

        init() {},

        showToast(type, message) {
            this.toast = { show: true, type, message };
            setTimeout(() => { this.toast.show = false; }, 4000);
        },

        openBulkAction(action) {
            const labels = {
                approve_all: { title: 'Approve All Pending Deals', desc: 'All pending deals in this batch will be approved with the specified days.', confirm: 'Approve All' },
                decline_all: { title: 'Decline All Pending Deals', desc: 'All pending deals in this batch will be declined. A reason is required.', confirm: 'Decline All' },
                skip_all:    { title: 'Skip All Pending Deals',    desc: 'All pending deals will be skipped for now. They remain actionable later.', confirm: 'Skip All' },
            };
            const l = labels[action];
            this.bulkModal = {
                show: true, action, title: l.title, description: l.desc,
                approvedDays: {{ $batch->requested_extension_days }}, note: '',
                confirmLabel: l.confirm, error: '', busy: false,
            };
        },

        async submitBulkAction() {
            const m = this.bulkModal;
            m.error = '';

            const endpoints = {
                approve_all: `${cfg.baseApi}/extension-requests/batches/${cfg.id}/approve-all`,
                decline_all: `${cfg.baseApi}/extension-requests/batches/${cfg.id}/decline-all`,
                skip_all:    `${cfg.baseApi}/extension-requests/batches/${cfg.id}/skip-all`,
            };

            const bodies = {
                approve_all: { approved_days: m.approvedDays, reviewer_note: m.note || null },
                decline_all: { reviewer_note: m.note },
                skip_all:    { reviewer_note: m.note || null },
            };

            if (m.action === 'decline_all' && m.note.trim().length < 5) {
                m.error = 'A reason of at least 5 characters is required.';
                return;
            }
            if (m.action === 'approve_all' && (m.approvedDays < 1 || m.approvedDays > 90)) {
                m.error = 'Approved days must be between 1 and 90.';
                return;
            }

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
                if (!res.ok) throw new Error(data.message || 'Action failed.');

                m.show = false;
                this.showToast('success', data.message || 'Bulk action completed.');
                setTimeout(() => location.reload(), 1200);
            } catch (e) {
                m.error = e.message;
                m.busy = false;
            }
        },
    };
}

function itemAction(requestId, initialStatus, requestedDays) {
    const cfg = window.__bulkExtBatch;

    const statusMap = {
        pending_review: { classes: 'bg-amber-100 text-amber-700', dot: 'bg-amber-400', label: 'Pending Review' },
        approved:       { classes: 'bg-emerald-100 text-emerald-700', dot: 'bg-emerald-400', label: 'Approved' },
        rejected:       { classes: 'bg-red-100 text-red-700', dot: 'bg-red-400', label: 'Declined' },
        skipped:        { classes: 'bg-gray-100 text-gray-500', dot: 'bg-gray-300', label: 'Skipped for Now' },
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
            if (!res.ok) throw new Error(data.message || 'Action failed.');
            return data;
        },

        async submitApprove() {
            if (this.approvedDays < 1 || this.approvedDays > 90) {
                this.error = 'Days must be between 1 and 90.';
                return;
            }
            this.busy = true; this.error = '';
            try {
                await this.apiPost(`${cfg.baseApi}/extension-requests/${this.requestId}/approve`, {
                    approved_days: this.approvedDays,
                    reviewer_note: this.note || null,
                });
                this.status = 'approved';
                this.resolved = true;
                this.action = null;
                window.dispatchEvent(new CustomEvent('item-resolved', { detail: { result: 'approved' } }));
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
                await this.apiPost(`${cfg.baseApi}/extension-requests/${this.requestId}/decline`, {
                    reviewer_note: this.note,
                });
                this.status = 'rejected';
                this.resolved = true;
                this.action = null;
                window.dispatchEvent(new CustomEvent('item-resolved', { detail: { result: 'declined' } }));
            } catch (e) {
                this.error = e.message;
            } finally {
                this.busy = false;
            }
        },

        async submitSkip() {
            this.busy = true; this.error = '';
            try {
                await this.apiPost(`${cfg.baseApi}/extension-requests/${this.requestId}/skip`, {});
                this.status = 'skipped';
                this.action = null;
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
