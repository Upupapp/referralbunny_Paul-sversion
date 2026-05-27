@extends('layouts.app')
@section('title', 'Archive Request Detail')
@section('nav') @include('tenant._nav') @endsection

@section('content')
@php
$lead       = $archiveRequest->lead;
$statusMap  = [
    'pending'                 => ['bg-orange-100 text-orange-700', 'Pending'],
    'approved'                => ['bg-green-100 text-green-700', 'Approved'],
    'rejected'                => ['bg-red-100 text-red-700', 'Rejected'],
    'clarification_requested' => ['bg-blue-100 text-blue-700', 'Clarification Requested'],
];
[$statusCls, $statusLabel] = $statusMap[$archiveRequest->status] ?? ['bg-gray-100 text-gray-600', ucfirst($archiveRequest->status)];
@endphp

<div class="space-y-5"
     x-data="{
         submitting: false,
         clarifyMessage: '',
         clarifyDue: '',
         showClarify: false,
     }">

    {{-- Breadcrumb --}}
    <div class="flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('tenant.deals', $tenant->id) }}" class="hover:text-[#7B61FF] transition-colors">Deals</a>
        <span>/</span>
        <a href="{{ route('tenant.deals.archive-requests', $tenant->id) }}" class="hover:text-[#7B61FF] transition-colors">Archive Requests</a>
        <span>/</span>
        <span class="text-[#1E1B4B] font-medium">{{ $lead?->name ?? 'Request Detail' }}</span>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700 flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Main info --}}
        <div class="lg:col-span-2 space-y-4">

            {{-- Deal summary card --}}
            <div class="card p-5 space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h1 class="text-xl font-bold text-[#1E1B4B]">{{ $lead?->name ?? 'Unknown Deal' }}</h1>
                        <div class="flex flex-wrap gap-x-4 gap-y-1 mt-1 text-sm text-gray-500">
                            @if($lead)
                                <span>Stage: <span class="font-medium text-gray-700">{{ ucwords(str_replace('_',' ',$lead->stage)) }}</span></span>
                                <span>Referrer: <span class="font-medium text-gray-700">{{ $lead->reseller_name ?: '—' }}</span></span>
                                <span>Value: <span class="font-medium text-gray-700">₱{{ number_format((float)$lead->deal_value, 0) }}</span></span>
                            @endif
                        </div>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-xl text-xs font-semibold {{ $statusCls }} shrink-0">
                        {{ $statusLabel }}
                    </span>
                </div>

                @if($lead)
                    <div class="pt-3 border-t border-gray-100">
                        <a href="{{ route('tenant.deals.show', [$tenant->id, $lead->id]) }}"
                           class="text-sm text-[#7B61FF] hover:underline font-medium inline-flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            View full deal
                        </a>
                    </div>
                @endif
            </div>

            {{-- Archive request details --}}
            <div class="card p-5 space-y-3">
                <h2 class="font-semibold text-[#1E1B4B]">Archive Request</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex gap-3">
                        <dt class="text-gray-500 w-28 shrink-0">Submitted</dt>
                        <dd class="text-gray-800">{{ $archiveRequest->created_at->format('M d, Y h:i A') }} ({{ $archiveRequest->created_at->diffForHumans() }})</dd>
                    </div>
                    @if($archiveRequest->reason)
                    <div class="flex gap-3">
                        <dt class="text-gray-500 w-28 shrink-0">Reason</dt>
                        <dd class="text-gray-800">{{ $archiveRequest->reason }}</dd>
                    </div>
                    @endif
                    @if($archiveRequest->expires_at)
                    <div class="flex gap-3">
                        <dt class="text-gray-500 w-28 shrink-0">Expires</dt>
                        <dd class="text-gray-800">{{ $archiveRequest->expires_at->format('M d, Y') }}</dd>
                    </div>
                    @endif
                </dl>
            </div>

            {{-- Clarification block --}}
            @if($archiveRequest->clarification_message)
            <div class="card p-5 space-y-2 border-blue-200 bg-blue-50/40">
                <h2 class="font-semibold text-blue-700 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Clarification Requested
                </h2>
                <p class="text-sm text-blue-800">{{ $archiveRequest->clarification_message }}</p>
                @if($archiveRequest->clarification_due_at)
                    <p class="text-xs text-blue-600">Response due: {{ $archiveRequest->clarification_due_at->format('M d, Y') }}</p>
                @endif
                @if($archiveRequest->visible_response)
                    <div class="mt-2 pt-3 border-t border-blue-200">
                        <p class="text-xs font-semibold text-blue-700 mb-1">Referrer's Response</p>
                        <p class="text-sm text-blue-800">{{ $archiveRequest->visible_response }}</p>
                    </div>
                @endif
            </div>
            @endif

            {{-- Decision block (for approved/rejected) --}}
            @if(in_array($archiveRequest->status, ['approved','rejected']))
            <div class="card p-5 space-y-2">
                <h2 class="font-semibold text-[#1E1B4B]">Decision</h2>
                <dl class="space-y-2 text-sm">
                    @if($archiveRequest->reviewer_note)
                    <div class="flex gap-3">
                        <dt class="text-gray-500 w-28 shrink-0">Note</dt>
                        <dd class="text-gray-800">{{ $archiveRequest->reviewer_note }}</dd>
                    </div>
                    @endif
                    @if($archiveRequest->approved_at)
                    <div class="flex gap-3">
                        <dt class="text-gray-500 w-28 shrink-0">Approved at</dt>
                        <dd class="text-gray-800">{{ $archiveRequest->approved_at->format('M d, Y h:i A') }}</dd>
                    </div>
                    @endif
                    @if($archiveRequest->rejected_at)
                    <div class="flex gap-3">
                        <dt class="text-gray-500 w-28 shrink-0">Rejected at</dt>
                        <dd class="text-gray-800">{{ $archiveRequest->rejected_at->format('M d, Y h:i A') }}</dd>
                    </div>
                    @endif
                </dl>
            </div>
            @endif

        </div>

        {{-- Decision panel --}}
        <div class="space-y-4">
            @if($archiveRequest->status === 'pending')
            <div class="card p-5 space-y-3">
                <h2 class="font-semibold text-[#1E1B4B]">Decision</h2>

                {{-- Approve --}}
                <form action="{{ route('tenant.approvals.approve', [$tenant->id, $archiveRequest->id]) }}" method="POST"
                      @submit="submitting = true">
                    @csrf
                    <button type="submit" :disabled="submitting"
                            class="w-full btn-primary bg-green-600 hover:bg-green-700 flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span x-show="!submitting">Approve Archive</span>
                        <span x-show="submitting">Processing…</span>
                    </button>
                </form>

                {{-- Clarify --}}
                <button @click="showClarify = !showClarify"
                        class="w-full btn-secondary flex items-center justify-center gap-2 text-blue-600 border-blue-200 hover:bg-blue-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Request Clarification
                </button>

                {{-- Inline clarify form --}}
                <div x-show="showClarify" x-cloak class="border border-blue-200 rounded-xl p-4 space-y-3 bg-blue-50/40">
                    <form action="{{ route('tenant.deals.archive-requests.clarify', [$tenant->id, $archiveRequest->id]) }}"
                          method="POST" @submit="submitting = true">
                        @csrf
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Message <span class="text-red-500">*</span></label>
                                <textarea name="clarification_message" x-model="clarifyMessage" rows="3"
                                          class="form-input w-full resize-none text-sm"
                                          placeholder="What do you need from the Referrer?" required maxlength="1000"></textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Response Due (optional)</label>
                                <input type="date" name="clarification_due_at"
                                       min="{{ now()->addDay()->format('Y-m-d') }}"
                                       class="form-input w-full text-sm">
                            </div>
                            <div class="flex gap-2">
                                <button type="submit" :disabled="submitting || !clarifyMessage.trim()"
                                        class="btn-primary flex-1">
                                    <span x-show="!submitting">Send</span>
                                    <span x-show="submitting">Sending…</span>
                                </button>
                                <button type="button" @click="showClarify = false" class="btn-secondary">Cancel</button>
                            </div>
                        </div>
                    </form>
                </div>

                {{-- Reject --}}
                <form action="{{ route('tenant.approvals.reject', [$tenant->id, $archiveRequest->id]) }}" method="POST"
                      @submit="submitting = true">
                    @csrf
                    <button type="submit" :disabled="submitting"
                            class="w-full btn-secondary text-red-600 border-red-200 hover:bg-red-50 flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        <span x-show="!submitting">Reject Request</span>
                        <span x-show="submitting">Processing…</span>
                    </button>
                </form>
            </div>
            @endif

            {{-- Back button --}}
            <a href="{{ route('tenant.deals.archive-requests', $tenant->id) }}"
               class="flex items-center gap-2 text-sm text-gray-500 hover:text-[#7B61FF] transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back to Archive Requests
            </a>
        </div>
    </div>

</div>
@endsection
