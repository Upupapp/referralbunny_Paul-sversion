@extends('layouts.app')
@section('title', 'Export Request — ' . ucfirst($exportRequest->export_type))
@section('nav') @include('tenant._nav') @endsection

@section('topbar-actions')
    <a href="{{ route('tenant.exports', $tenant->id) }}" class="btn-secondary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        <span class="hidden sm:inline">Back to Exports</span>
    </a>
@endsection

@section('content')
@php
    $statusBadge = match($exportRequest->status) {
        'pending'        => 'badge badge-orange',
        'approved'       => 'badge badge-blue',
        'processing',
        'direct_pending' => 'badge badge-purple',
        'ready',
        'direct_ready'   => 'badge badge-green',
        'rejected'       => 'badge badge-red',
        'cancelled',
        'expired'        => 'badge badge-gray',
        'failed'         => 'badge badge-red',
        default          => 'badge badge-gray',
    };
    $isPending  = $exportRequest->isPending();
    $isReady    = $exportRequest->isReady();
    $isRejected = $exportRequest->isRejected();
    $canDownload = $exportRequest->canBeDownloaded();
@endphp

<div class="space-y-5"
     x-data="exportDetail('{{ $tenant->id }}', '{{ $exportRequest->id }}')"
     x-init="init()">

    {{-- Page header --}}
    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-xl bg-[#EDE9FE] flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-[#7B61FF]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                </div>
                <div>
                    <div class="flex items-center flex-wrap gap-2 mb-0.5">
                        <h2 class="text-[#1E1B4B] font-bold text-lg capitalize">
                            {{ str_replace('_', ' ', $exportRequest->export_type) }} Export
                        </h2>
                        <span class="{{ $statusBadge }}">{{ $exportRequest->status_label }}</span>
                        @if($exportRequest->is_sensitive)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-orange-100 text-orange-700">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                Sensitive
                            </span>
                        @endif
                    </div>
                    <p class="text-gray-400 text-xs">
                        Requested {{ $exportRequest->created_at->diffForHumans() }}
                        &middot; {{ $exportRequest->created_at->format('M j, Y \a\t g:i A') }}
                    </p>
                </div>
            </div>

            {{-- Action buttons --}}
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                @if($isPending)
                    <button @click="openRejectModal()"
                            class="btn-secondary !border-red-200 !text-red-600 hover:!bg-red-50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        Reject Export
                    </button>
                    <button @click="confirmApprove()"
                            class="btn-primary !bg-emerald-600 hover:!bg-emerald-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Approve Export
                    </button>
                @endif

                @if($canDownload)
                    <a href="{{ route('tenant.exports.download', [$tenant->id, $exportRequest->id]) }}"
                       class="btn-primary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Download File
                    </a>
                    @if($exportRequest->file_expires_at)
                        <span class="text-xs text-gray-400">
                            Expires {{ $exportRequest->file_expires_at->format('M j, Y') }}
                            ({{ $exportRequest->file_expires_at->diffForHumans() }})
                        </span>
                    @endif
                @endif
            </div>
        </div>

        {{-- Action feedback --}}
        <div x-show="actionSuccess"
             x-transition
             class="mt-3 flex items-start gap-3 p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">
            <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span x-text="actionSuccess"></span>
        </div>
        <div x-show="actionError"
             x-transition
             class="mt-3 flex items-start gap-3 p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm">
            <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <span x-text="actionError"></span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Main details --}}
        <div class="lg:col-span-2 space-y-5">

            {{-- Sensitive data warning --}}
            @if($exportRequest->is_sensitive)
                <div class="flex items-start gap-3 p-4 rounded-xl bg-orange-50 border border-orange-200">
                    <svg class="w-5 h-5 text-orange-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div>
                        <p class="text-sm font-semibold text-orange-700">Sensitive Data Export</p>
                        <p class="text-xs text-orange-600 mt-0.5">
                            This export type ({{ str_replace('_', ' ', $exportRequest->export_type) }}) is classified as sensitive.
                            Review the request carefully before approving. Ensure the requester has a legitimate business need for this data.
                        </p>
                    </div>
                </div>
            @endif

            {{-- Request details card --}}
            <div class="card space-y-0 p-0 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50/50">
                    <h3 class="text-sm font-semibold text-[#1E1B4B]">Request Details</h3>
                </div>
                <div class="divide-y divide-gray-50">
                    <div class="grid grid-cols-2 sm:grid-cols-3 px-5 py-3 gap-x-4 gap-y-3">
                        <div>
                            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-1">Export Type</p>
                            <p class="text-sm font-semibold text-[#1E1B4B] capitalize">{{ str_replace('_', ' ', $exportRequest->export_type) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-1">Format</p>
                            <p class="text-sm font-semibold text-[#1E1B4B] uppercase font-mono">{{ $exportRequest->export_format }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-1">Records (est.)</p>
                            <p class="text-sm font-semibold text-[#1E1B4B]">
                                {{ $exportRequest->records_estimate > 0 ? number_format($exportRequest->records_estimate) : '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-1">Requester Role</p>
                            <p class="text-sm font-semibold text-[#1E1B4B] capitalize">{{ $exportRequest->requester_role }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-1">Requester Type</p>
                            <p class="text-sm font-semibold text-[#1E1B4B] capitalize">{{ str_replace('_', ' ', $exportRequest->requester_type) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-1">Requested</p>
                            <p class="text-sm font-semibold text-[#1E1B4B]">{{ $exportRequest->created_at->format('M j, Y g:i A') }}</p>
                        </div>
                    </div>

                    {{-- Scope / filters --}}
                    @if(!empty($exportRequest->export_scope))
                        <div class="px-5 py-3">
                            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-2">Scope / Filters</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($exportRequest->export_scope as $key => $value)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-purple-50 text-purple-700 text-xs font-medium">
                                        <span class="text-purple-400">{{ $key }}:</span> {{ $value }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Fields --}}
                    @if(!empty($exportRequest->export_fields))
                        <div class="px-5 py-3">
                            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-2">Requested Fields</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($exportRequest->export_fields as $field)
                                    <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 text-xs font-medium capitalize">
                                        {{ str_replace('_', ' ', $field) }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Reason --}}
                    @if($exportRequest->reason)
                        <div class="px-5 py-3">
                            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-1.5">Reason / Justification</p>
                            <p class="text-sm text-gray-700 leading-relaxed">{{ $exportRequest->reason }}</p>
                        </div>
                    @endif

                    {{-- File info (if ready) --}}
                    @if($exportRequest->file_name)
                        <div class="px-5 py-3">
                            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-2">File Information</p>
                            <div class="flex flex-wrap gap-4">
                                <div>
                                    <p class="text-xs text-gray-400">Filename</p>
                                    <p class="text-sm font-mono text-[#1E1B4B] mt-0.5">{{ $exportRequest->file_name }}</p>
                                </div>
                                @if($exportRequest->file_size)
                                    <div>
                                        <p class="text-xs text-gray-400">Size</p>
                                        <p class="text-sm text-[#1E1B4B] mt-0.5">{{ number_format($exportRequest->file_size / 1024, 1) }} KB</p>
                                    </div>
                                @endif
                                @if($exportRequest->file_expires_at)
                                    <div>
                                        <p class="text-xs text-gray-400">Expires</p>
                                        <p class="text-sm text-[#1E1B4B] mt-0.5 {{ $exportRequest->file_expires_at->isPast() ? 'text-red-500' : '' }}">
                                            {{ $exportRequest->file_expires_at->format('M j, Y') }}
                                        </p>
                                    </div>
                                @endif
                                @if($exportRequest->download_count > 0)
                                    <div>
                                        <p class="text-xs text-gray-400">Downloads</p>
                                        <p class="text-sm text-[#1E1B4B] mt-0.5">{{ $exportRequest->download_count }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Rejection reason --}}
                    @if($isRejected && $exportRequest->rejection_reason)
                        <div class="px-5 py-3 bg-red-50">
                            <p class="text-xs text-red-500 font-medium uppercase tracking-wide mb-1.5">Rejection Reason</p>
                            <p class="text-sm text-red-700">{{ $exportRequest->rejection_reason }}</p>
                        </div>
                    @endif

                    {{-- Error message --}}
                    @if($exportRequest->error_message)
                        <div class="px-5 py-3 bg-red-50">
                            <p class="text-xs text-red-500 font-medium uppercase tracking-wide mb-1.5">Error Details</p>
                            <p class="text-xs font-mono text-red-700">{{ $exportRequest->error_message }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Approval history --}}
            <div class="card space-y-0 p-0 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50/50">
                    <h3 class="text-sm font-semibold text-[#1E1B4B]">Approval History</h3>
                </div>
                <div class="divide-y divide-gray-50">
                    {{-- Request created --}}
                    <div class="px-5 py-3 flex items-start gap-3">
                        <div class="w-6 h-6 rounded-full bg-purple-100 flex items-center justify-center shrink-0 mt-0.5">
                            <svg class="w-3 h-3 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-[#1E1B4B]">Export requested</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $exportRequest->created_at->format('M j, Y g:i A') }}</p>
                        </div>
                    </div>

                    {{-- Approved --}}
                    @if($exportRequest->approved_at)
                        <div class="px-5 py-3 flex items-start gap-3">
                            <div class="w-6 h-6 rounded-full bg-emerald-100 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-[#1E1B4B]">Approved by admin</p>
                                <p class="text-xs text-gray-400 mt-0.5">{{ $exportRequest->approved_at->format('M j, Y g:i A') }}</p>
                            </div>
                        </div>
                    @endif

                    {{-- Rejected --}}
                    @if($exportRequest->rejected_at)
                        <div class="px-5 py-3 flex items-start gap-3">
                            <div class="w-6 h-6 rounded-full bg-red-100 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="w-3 h-3 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-[#1E1B4B]">Rejected by admin</p>
                                <p class="text-xs text-gray-400 mt-0.5">{{ $exportRequest->rejected_at->format('M j, Y g:i A') }}</p>
                                @if($exportRequest->rejection_reason)
                                    <p class="text-xs text-gray-500 mt-1 italic">"{{ $exportRequest->rejection_reason }}"</p>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Ready --}}
                    @if($exportRequest->isReady() && $exportRequest->file_name)
                        <div class="px-5 py-3 flex items-start gap-3">
                            <div class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="w-3 h-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-[#1E1B4B]">File generated — ready to download</p>
                                <p class="text-xs text-gray-400 mt-0.5">{{ $exportRequest->updated_at->format('M j, Y g:i A') }}</p>
                            </div>
                        </div>
                    @endif

                    {{-- Downloaded --}}
                    @if($exportRequest->downloaded_at)
                        <div class="px-5 py-3 flex items-start gap-3">
                            <div class="w-6 h-6 rounded-full bg-gray-100 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-[#1E1B4B]">
                                    First downloaded &middot; {{ $exportRequest->download_count }} total download{{ $exportRequest->download_count !== 1 ? 's' : '' }}
                                </p>
                                <p class="text-xs text-gray-400 mt-0.5">{{ $exportRequest->downloaded_at->format('M j, Y g:i A') }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-5">

            {{-- R Bunny helper --}}
            <div class="card bg-gradient-to-br from-[#EDE9FE] to-[#F5F3FF] border-0">
                <div class="flex items-start gap-3">
                    <img src="/images/mascots/r-bunny-portal.webp"
                         alt="R Bunny helper"
                         class="w-12 h-12 object-contain shrink-0">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-bold text-[#7B61FF] uppercase tracking-wide mb-1">R Bunny says</p>
                        @if($isPending)
                            <p class="text-xs text-[#4B3F8C] leading-relaxed">
                                This export is waiting for your review. Check the requested type and scope carefully before approving — especially if it contains sensitive data.
                            </p>
                        @elseif($isReady)
                            <p class="text-xs text-[#4B3F8C] leading-relaxed">
                                The export file is ready! The requester can download it before it expires.
                                @if($exportRequest->file_expires_at)
                                    File expires {{ $exportRequest->file_expires_at->diffForHumans() }}.
                                @endif
                            </p>
                        @elseif($isRejected)
                            <p class="text-xs text-[#4B3F8C] leading-relaxed">
                                This export request was rejected. The requester has been notified and may submit a new request if needed.
                            </p>
                        @else
                            <p class="text-xs text-[#4B3F8C] leading-relaxed">
                                Track the status of this export request here. You'll be notified when the file is ready or if anything needs attention.
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Approval notice (shown when about to approve) --}}
            @if($isPending)
                <div class="card border border-emerald-200 bg-emerald-50/50 space-y-2">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        <p class="text-xs font-bold text-emerald-700">Before you approve</p>
                    </div>
                    <p class="text-xs text-emerald-700 leading-relaxed">
                        You are approving this export. The requester will be able to download only the approved data within the file expiration period.
                        This action is logged in the audit trail.
                    </p>
                </div>
            @endif

            {{-- Meta info card --}}
            <div class="card space-y-3">
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wide">Request Info</h4>
                <div class="space-y-2.5">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs text-gray-400">Request ID</span>
                        <span class="text-xs font-mono text-gray-600 truncate max-w-[140px]" title="{{ $exportRequest->id }}">
                            {{ substr($exportRequest->id, 0, 8) }}…
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs text-gray-400">Status</span>
                        <span class="{{ $statusBadge }}">{{ $exportRequest->status_label }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs text-gray-400">Sensitive</span>
                        <span class="text-xs font-medium {{ $exportRequest->is_sensitive ? 'text-orange-600' : 'text-gray-600' }}">
                            {{ $exportRequest->is_sensitive ? 'Yes' : 'No' }}
                        </span>
                    </div>
                    @if($exportRequest->records_estimate > 0)
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs text-gray-400">Estimated Records</span>
                            <span class="text-xs font-medium text-gray-600">{{ number_format($exportRequest->records_estimate) }}</span>
                        </div>
                    @endif
                    @if($exportRequest->retry_count > 0)
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs text-gray-400">Retries</span>
                            <span class="text-xs font-medium text-gray-600">{{ $exportRequest->retry_count }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Cancel button (for pending requests) --}}
            @if($exportRequest->canBeCancelled())
                <button @click="confirmCancel()"
                        class="w-full btn-secondary !border-gray-200 !text-gray-500 hover:!border-red-200 hover:!text-red-500 text-xs">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Cancel This Request
                </button>
            @endif
        </div>
    </div>
</div>

{{-- ── Reject Modal ──────────────────────────────────────────────── --}}
<div x-data x-show="$store.rejectModal.visible" x-cloak
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
     @click.self="$store.rejectModal.close()">

    <div x-data="rejectForm('{{ $tenant->id }}', '{{ $exportRequest->id }}')"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">

        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-red-100 flex items-center justify-center">
                    <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </div>
                <h3 class="text-[#1E1B4B] font-bold text-base">Reject Export Request</h3>
            </div>
            <button @click="$store.rejectModal.close()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="px-6 py-5 space-y-4">
            <p class="text-sm text-gray-600">
                Provide a reason for rejecting this export request. The requester will be notified.
            </p>

            <div x-show="rejectError" class="p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm" x-text="rejectError"></div>

            <div>
                <label class="form-label">Rejection Reason <span class="text-red-400">*</span></label>
                <textarea x-model="reason"
                          rows="4"
                          placeholder="Explain why this request is being rejected…"
                          class="form-input mt-1 w-full resize-none"></textarea>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/50">
            <button @click="$store.rejectModal.close()" class="btn-secondary" :disabled="submitting">Cancel</button>
            <button @click="submitReject()"
                    class="btn-primary !bg-red-600 hover:!bg-red-700"
                    :disabled="submitting || !reason.trim()">
                <span x-show="!submitting">Confirm Rejection</span>
                <span x-show="submitting" class="flex items-center gap-2">
                    <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Rejecting…
                </span>
            </button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.store('rejectModal', {
        visible: false,
        open()  { this.visible = true; },
        close() { this.visible = false; },
    });
});

function exportDetail(tenantId, requestId) {
    return {
        tenantId,
        requestId,
        actionSuccess: '',
        actionError:   '',

        init() {},

        openRejectModal() {
            this.actionError = '';
            Alpine.store('rejectModal').open();
        },

        async confirmApprove() {
            if (!confirm('Approve this export request? The requester will be notified and file generation will begin.')) {
                return;
            }
            this.actionSuccess = '';
            this.actionError   = '';

            try {
                const res = await fetch('/api/exports/' + this.requestId + '/approve?tenant_id=' + this.tenantId, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept':       'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });
                const json = await res.json();
                if (!res.ok) {
                    this.actionError = json.message ?? 'Failed to approve export request.';
                    return;
                }
                this.actionSuccess = 'Export request approved. The file is being generated and the requester will be notified.';
                setTimeout(() => window.location.reload(), 2000);
            } catch (e) {
                this.actionError = 'A network error occurred. Please try again.';
            }
        },

        async confirmCancel() {
            if (!confirm('Cancel this export request?')) {
                return;
            }
            this.actionSuccess = '';
            this.actionError   = '';

            try {
                const res = await fetch('/api/exports/' + this.requestId + '/cancel?tenant_id=' + this.tenantId, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept':       'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });
                const json = await res.json();
                if (!res.ok) {
                    this.actionError = json.message ?? 'Failed to cancel export request.';
                    return;
                }
                this.actionSuccess = 'Export request cancelled.';
                setTimeout(() => window.location.reload(), 1500);
            } catch (e) {
                this.actionError = 'A network error occurred. Please try again.';
            }
        },
    };
}

function rejectForm(tenantId, requestId) {
    return {
        tenantId,
        requestId,
        reason:      '',
        submitting:  false,
        rejectError: '',

        async submitReject() {
            this.submitting  = true;
            this.rejectError = '';

            try {
                const res = await fetch('/api/exports/' + this.requestId + '/reject?tenant_id=' + this.tenantId, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept':       'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ reason: this.reason }),
                });
                const json = await res.json();
                if (!res.ok) {
                    this.rejectError = json.message ?? 'Failed to reject export request.';
                    return;
                }
                Alpine.store('rejectModal').close();

                // Show success feedback and reload
                const detailEl = document.querySelector('[x-data*="exportDetail"]');
                if (detailEl) {
                    detailEl._x_dataStack[0].actionSuccess = 'Export request rejected. The requester has been notified.';
                }
                setTimeout(() => window.location.reload(), 2000);
            } catch (e) {
                this.rejectError = 'A network error occurred. Please try again.';
            } finally {
                this.submitting = false;
            }
        },
    };
}
</script>
@endpush
