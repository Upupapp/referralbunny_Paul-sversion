@extends('layouts.app')
@section('title', 'Import Report — Deal Import')

@section('nav')
    @include('tenant._nav')
@endsection

@section('topbar-actions')
    <a href="{{ route('tenant.imports.deals', $tenant->id) }}" class="btn-secondary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        <span class="hidden sm:inline">Back to Imports</span>
    </a>
    <a href="{{ route('tenant.imports.deals', $tenant->id) }}"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-white transition-colors"
       style="background: #7B61FF;" onmouseover="this.style.background='#5B45DF'" onmouseout="this.style.background='#7B61FF'">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
        </svg>
        <span class="hidden sm:inline">New Import</span>
    </a>
@endsection

@section('content')
@php
    $summary    = is_array($batch->summary_json) ? $batch->summary_json : (json_decode($batch->summary_json, true) ?? []);
    $statusInfo = match($batch->status) {
        'completed'               => ['class' => 'badge-green',  'label' => 'Completed'],
        'completed_with_warnings' => ['class' => 'badge-orange', 'label' => 'Completed with Warnings'],
        'needs_review'            => ['class' => 'badge-orange', 'label' => 'Needs Review'],
        'failed'                  => ['class' => 'badge-red',    'label' => 'Failed'],
        'previewed'               => ['class' => 'badge-blue',   'label' => 'Previewed'],
        'processing'              => ['class' => 'badge-blue',   'label' => 'Processing'],
        default                   => ['class' => 'badge-gray',   'label' => ucfirst($batch->status)],
    };
@endphp
<div class="space-y-5">

    {{-- ── Header ──────────────────────────────────────────── --}}
    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5 mb-2">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background: #EDE9FE;">
                        <svg class="w-5 h-5" style="color: #7B61FF;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-[#1E1B4B] font-bold text-xl leading-tight">Import Report</h1>
                        <p class="text-gray-400 text-sm mt-0.5">
                            <span class="font-medium text-gray-600">{{ $batch->file_name }}</span>
                            &nbsp;·&nbsp;
                            {{ $batch->created_at->format('M d, Y \a\t h:i A') }}
                        </p>
                    </div>
                </div>
                <span class="badge {{ $statusInfo['class'] }} text-sm px-3 py-1">
                    @if($batch->status === 'completed')
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    @elseif(in_array($batch->status, ['completed_with_warnings', 'needs_review']))
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    @elseif($batch->status === 'failed')
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                    @endif
                    {{ $statusInfo['label'] }}
                </span>
            </div>

            {{-- Quick actions panel --}}
            <div class="flex flex-col gap-2 shrink-0 min-w-[180px]" x-data="importRollback('{{ $batch->id }}', '{{ $tenant->id }}')">
                @if($batch->failed_rows > 0)
                <a href="{{ route('tenant.imports.deals.failed', [$tenant->id, $batch->id]) }}"
                   class="inline-flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-medium text-red-600 bg-red-50 hover:bg-red-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Download Failed Rows
                </a>
                @endif
                <a href="{{ route('tenant.deals', $tenant->id) }}"
                   class="inline-flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-medium bg-[#EDE9FE] hover:bg-purple-100 transition-colors" style="color: #7B61FF;">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/>
                    </svg>
                    View Imported Deals
                </a>

                {{-- Undo Import button --}}
                @if(in_array($batch->status, ['completed', 'completed_with_warnings']))
                    @if($batch->isAlreadyRolledBack())
                        {{-- Already rolled back — show report link --}}
                        @if($batch->rollback_id)
                        <a href="{{ route('tenant.imports.rollback.show', [$tenant->id, $batch->id, $batch->rollback_id]) }}"
                           class="inline-flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-medium text-gray-500 bg-gray-100 hover:bg-gray-200 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            View Rollback Report
                        </a>
                        @endif
                    @elseif($batch->rollback_status === 'processing')
                        <div class="inline-flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-medium text-amber-600 bg-amber-50 border border-amber-200">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            Undoing import…
                        </div>
                    @else
                        <button @click="openRollbackModal()"
                                class="inline-flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-medium text-red-600 bg-red-50 hover:bg-red-100 border border-red-200 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                            Undo Import
                        </button>
                    @endif
                @endif

                {{-- Rollback modal --}}
                <template x-teleport="body">
                <div x-show="showModal" x-cloak
                     class="fixed inset-0 bg-black/50 z-[9999] flex items-end sm:items-center justify-center p-4"
                     @keydown.escape.window="showModal = false">
                    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto" @click.stop>

                        {{-- Header --}}
                        <div class="flex items-start justify-between px-6 py-4 border-b border-gray-100 sticky top-0 bg-white rounded-t-2xl">
                            <div>
                                <h3 class="font-bold text-[#1E1B4B]">Undo Import</h3>
                                <p class="text-xs text-gray-400 mt-0.5">Review what will be changed before confirming.</p>
                            </div>
                            <button @click="showModal = false" class="text-gray-400 hover:text-gray-600 mt-0.5">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        {{-- Loading preview --}}
                        <div x-show="loadingPreview" class="flex items-center gap-3 p-8 justify-center text-gray-400">
                            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <span class="text-sm">Loading preview…</span>
                        </div>

                        {{-- Not eligible --}}
                        <div x-show="!loadingPreview && !preview?.eligible" class="p-6">
                            <div class="flex items-start gap-3 p-4 bg-amber-50 border border-amber-200 rounded-xl">
                                <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                <p class="text-sm text-amber-800 font-medium" x-text="preview?.reason || 'This import cannot be undone.'"></p>
                            </div>
                            <div class="flex justify-end mt-4">
                                <button @click="showModal = false" class="btn-secondary text-sm">Close</button>
                            </div>
                        </div>

                        {{-- Preview summary --}}
                        <div x-show="!loadingPreview && preview?.eligible" class="p-6 space-y-5">

                            {{-- Warning banner --}}
                            <div class="flex items-start gap-3 p-4 bg-red-50 border border-red-200 rounded-xl">
                                <svg class="w-5 h-5 text-red-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                <p class="text-xs text-red-700 leading-relaxed">
                                    Undoing this import will remove records created by the import and restore previous values for records updated by the import. This action may affect deals, contacts, referrers, organizations, reports, and related activity. Review the summary before continuing.
                                </p>
                            </div>

                            {{-- Summary counts --}}
                            <div class="grid grid-cols-3 gap-3">
                                <div class="text-center p-3 bg-red-50 rounded-xl">
                                    <p class="text-xl font-bold text-red-700" x-text="preview?.summary?.remove_count ?? 0"></p>
                                    <p class="text-xs text-red-500 mt-0.5">To remove</p>
                                </div>
                                <div class="text-center p-3 bg-blue-50 rounded-xl">
                                    <p class="text-xl font-bold text-blue-700" x-text="preview?.summary?.restore_count ?? 0"></p>
                                    <p class="text-xs text-blue-500 mt-0.5">To restore</p>
                                </div>
                                <div class="text-center p-3 bg-amber-50 rounded-xl">
                                    <p class="text-xl font-bold text-amber-700" x-text="preview?.summary?.conflict_count ?? 0"></p>
                                    <p class="text-xs text-amber-500 mt-0.5">Conflicts</p>
                                </div>
                            </div>

                            {{-- Conflicts list --}}
                            <div x-show="(preview?.summary?.conflicts?.length ?? 0) > 0">
                                <p class="text-xs font-semibold text-amber-700 uppercase tracking-wide mb-2">Items that cannot be automatically undone</p>
                                <div class="space-y-1.5 max-h-32 overflow-y-auto">
                                    <template x-for="c in (preview?.summary?.conflicts ?? [])" :key="c.entity_id">
                                        <div class="flex items-start gap-2 px-3 py-2 bg-amber-50 border border-amber-100 rounded-lg">
                                            <svg class="w-3.5 h-3.5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            <p class="text-xs text-amber-800" x-text="c.reason"></p>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            {{-- Confirmation --}}
                            <div class="space-y-3 pt-2 border-t border-gray-100">
                                <label class="flex items-start gap-2 cursor-pointer">
                                    <input type="checkbox" x-model="confirmed" class="mt-0.5 rounded border-gray-300 text-red-500 focus:ring-red-300">
                                    <span class="text-xs text-gray-600">I understand this will undo the selected import and the action cannot be automatically reversed.</span>
                                </label>

                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1.5">
                                        Type <span class="font-bold text-gray-700">UNDO IMPORT</span> to confirm
                                    </label>
                                    <input type="text" x-model="confirmPhrase"
                                           class="form-input text-sm"
                                           placeholder="UNDO IMPORT"
                                           @keydown.enter.prevent="if(canSubmit()) submitRollback()">
                                </div>
                            </div>

                            {{-- Error --}}
                            <div x-show="rollbackError" class="flex items-center gap-2 px-3 py-2 bg-red-50 border border-red-200 rounded-xl text-xs text-red-700">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span x-text="rollbackError"></span>
                            </div>

                            {{-- Actions --}}
                            <div class="flex gap-3 justify-end pt-2">
                                <button @click="showModal = false" class="btn-secondary text-sm">Cancel</button>
                                <button @click="submitRollback()"
                                        :disabled="!canSubmit() || submitting"
                                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white bg-red-500 hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                    <svg x-show="submitting" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    <span x-text="submitting ? 'Undoing import…' : 'Undo Import'"></span>
                                </button>
                            </div>
                        </div>

                        {{-- Processing state --}}
                        <div x-show="processing" class="p-8 text-center space-y-3">
                            <svg class="w-10 h-10 text-purple-400 animate-spin mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <p class="font-semibold text-[#1E1B4B]">Undoing import…</p>
                            <p class="text-sm text-gray-400">Restoring previous records and removing imported records. This may take a moment.</p>
                        </div>
                    </div>
                </div>
                </template>
            </div>
                @if(isset($batch->unknown_referrer_rows) && $batch->unknown_referrer_rows > 0)
                <a href="{{ route('tenant.resellers', $tenant->id) }}"
                   class="inline-flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-medium text-amber-700 bg-amber-50 hover:bg-amber-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    Invite Unknown Referrers
                </a>
                @endif
                <a href="{{ route('tenant.imports.deals', $tenant->id) }}"
                   class="inline-flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-medium text-gray-600 bg-gray-50 hover:bg-gray-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Start New Import
                </a>
            </div>
        </div>
    </div>

    {{-- ── Result KPI Grid ──────────────────────────────────── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        {{-- Created --}}
        <div class="kpi-card">
            <div>
                <p class="text-xs font-medium text-gray-500 mb-1">Created</p>
                <p class="text-2xl font-bold tabular-nums" style="color: #7B61FF;">{{ number_format($batch->successful_rows) }}</p>
                <p class="text-xs text-gray-400 mt-1">New deals added</p>
            </div>
            <div class="kpi-icon" style="background: #EDE9FE;">
                <svg class="w-5 h-5" style="color: #7B61FF;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
        </div>

        {{-- Updated --}}
        <div class="kpi-card">
            <div>
                <p class="text-xs font-medium text-gray-500 mb-1">Updated</p>
                <p class="text-2xl font-bold text-blue-600 tabular-nums">{{ number_format($batch->updated_rows) }}</p>
                <p class="text-xs text-gray-400 mt-1">Existing deals merged</p>
            </div>
            <div class="kpi-icon bg-blue-50">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
            </div>
        </div>

        {{-- Skipped --}}
        <div class="kpi-card">
            <div>
                <p class="text-xs font-medium text-gray-500 mb-1">Skipped</p>
                <p class="text-2xl font-bold text-gray-500 tabular-nums">{{ number_format($batch->skipped_rows) }}</p>
                <p class="text-xs text-gray-400 mt-1">Rows skipped</p>
            </div>
            <div class="kpi-icon bg-gray-100">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
                </svg>
            </div>
        </div>

        {{-- Failed --}}
        <div class="kpi-card">
            <div>
                <p class="text-xs font-medium text-gray-500 mb-1">Failed</p>
                <p class="text-2xl font-bold tabular-nums {{ $batch->failed_rows > 0 ? 'text-red-600' : 'text-gray-400' }}">{{ number_format($batch->failed_rows) }}</p>
                <p class="text-xs text-gray-400 mt-1">Could not import</p>
            </div>
            <div class="kpi-icon {{ $batch->failed_rows > 0 ? 'bg-red-50' : 'bg-gray-50' }}">
                <svg class="w-5 h-5 {{ $batch->failed_rows > 0 ? 'text-red-500' : 'text-gray-300' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </div>
        </div>
    </div>

    {{-- ── Summary Cards Row ────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

        {{-- Pipeline Total --}}
        <div class="card">
            <div class="flex items-center gap-2.5 mb-3">
                <div class="w-8 h-8 rounded-lg bg-[#EDE9FE] flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-[#7B61FF]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h4 class="text-sm font-semibold text-[#1E1B4B]">Pipeline Total</h4>
            </div>
            <p class="text-2xl font-bold text-[#1E1B4B] tabular-nums">
                {{ number_format($batch->successful_rows ?? 0) }}
            </p>
            <p class="text-xs text-gray-400 mt-1">Deals successfully created or updated</p>
        </div>

        {{-- Unknown Referrers --}}
        <div class="card">
            <div class="flex items-center gap-2.5 mb-3">
                <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <h4 class="text-sm font-semibold text-[#1E1B4B]">Unknown Referrers</h4>
            </div>
            <p class="text-2xl font-bold tabular-nums {{ ($batch->unknown_referrer_rows ?? 0) > 0 ? 'text-amber-600' : 'text-gray-400' }}">
                {{ number_format($batch->unknown_referrer_rows ?? 0) }}
            </p>
            <p class="text-xs text-gray-400 mt-1">Referrer emails not found in system.</p>
            @if(($batch->unknown_referrer_rows ?? 0) > 0)
            <a href="{{ route('tenant.referrers', $tenant->id) }}"
               class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-amber-600 hover:text-amber-800 transition-colors">
                Invite them now
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            @endif
        </div>
    </div>

    {{-- ── All Rows Table ───────────────────────────────────── --}}
    <div class="card p-0 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <h3 class="text-[#1E1B4B] font-semibold text-base">All Import Rows</h3>
            <p class="text-xs text-gray-400">
                Showing {{ isset($rows) && method_exists($rows, 'firstItem') ? $rows->firstItem() . '–' . $rows->lastItem() . ' of ' . $rows->total() : (isset($rows) ? count($rows) : 0) }} rows
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[800px]">
                <thead>
                    <tr class="table-head">
                        <th>#</th>
                        <th>Deal Name</th>
                        <th>Organization</th>
                        <th>Referrer Email</th>
                        <th>Deal Amount</th>
                        <th>Status</th>
                        <th>Action Taken</th>
                        <th>Deal</th>
                        <th>Errors</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                    @php
                        $statusKey = $row->status ?? 'ready';
                        $computed     = is_array($row->computed_data) ? $row->computed_data : (json_decode($row->computed_data, true) ?? []);
                        $errorMessage = $row->error_message ?? null;
                        $errors       = $errorMessage ? [$errorMessage] : [];
                        $rowBorder = match($statusKey) {
                            'completed','created' => 'border-l-2 border-l-[#7B61FF]',
                            'updated'             => 'border-l-2 border-l-blue-400',
                            'failed','blocked'    => 'border-l-2 border-l-red-400',
                            default               => '',
                        };
                        $badgeMap = [
                            'created'   => ['badge-green',  'Created'],
                            'updated'   => ['badge-blue',   'Updated'],
                            'skipped'   => ['badge-gray',   'Skipped'],
                            'failed'    => ['badge-red',    'Failed'],
                            'blocked'   => ['badge-gray',   'Blocked'],
                            'completed' => ['badge-green',  'Completed'],
                        ];
                        $sb = $badgeMap[$statusKey] ?? ['badge-gray', ucfirst($statusKey)];
                    @endphp
                    <tr class="table-row {{ $rowBorder }}">
                        <td class="text-xs text-gray-400 tabular-nums">
                            {{ isset($rows) && method_exists($rows, 'firstItem') ? ($rows->firstItem() + $loop->index) : ($loop->index + 1) }}
                        </td>
                        <td class="font-medium text-[#1E1B4B]">
                            {{ $row->raw_data['deal_name'] ?? '—' }}
                        </td>
                        <td class="text-gray-600">
                            {{ $row->raw_data['organization_name'] ?? '—' }}
                        </td>
                        <td>
                            <span class="text-sm text-gray-700">{{ $row->raw_data['referrer_email'] ?? '—' }}</span>
                        </td>
                        <td class="tabular-nums font-medium text-[#1E1B4B]">
                            @if(isset($computed['deal_amount']))
                                {{ number_format($computed['deal_amount'], 2) }}
                            @elseif(!empty($row->raw_data['deal_amount']))
                                {{ $row->raw_data['deal_amount'] }}
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $sb[0] }}">{{ $sb[1] }}</span>
                        </td>
                        <td class="text-xs text-gray-500">
                            {{ $row->row_action ? ucfirst(str_replace('_', ' ', $row->row_action)) : '—' }}
                        </td>
                        <td>
                            @if($row->created_deal_id)
                                <a href="{{ route('tenant.deals.show', [$tenant->id, $row->created_deal_id]) }}"
                                   class="text-xs font-medium hover:opacity-80 transition-colors" style="color: #7B61FF;">
                                    View →
                                </a>
                            @else
                                <span class="text-gray-300 text-xs">—</span>
                            @endif
                        </td>
                        <td class="max-w-[200px]">
                            @if(count($errors) > 0)
                                <div class="flex flex-col gap-0.5">
                                    @foreach(array_slice($errors, 0, 2) as $err)
                                    <span class="text-xs text-red-600 leading-tight">{{ is_array($err) ? ($err['message'] ?? '') : $err }}</span>
                                    @endforeach
                                    @if(count($errors) > 2)
                                    <span class="text-[10px] text-gray-400">+{{ count($errors) - 2 }} more</span>
                                    @endif
                                </div>
                            @else
                                <span class="text-gray-300 text-xs">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="py-12 text-center text-gray-400 text-sm">No rows found for this import batch.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if(isset($rows) && method_exists($rows, 'hasPages') && $rows->hasPages())
        <div class="px-5 py-4 border-t border-gray-100">
            {{ $rows->links() }}
        </div>
        @endif
    </div>

    {{-- ── Footer action strip ──────────────────────────────── --}}
    <div class="card flex flex-col sm:flex-row items-center justify-between gap-3">
        <p class="text-sm text-gray-500">
            Import completed on <span class="font-medium text-gray-700">{{ $batch->created_at->format('M d, Y \a\t h:i A') }}</span>
        </p>
        <div class="flex flex-wrap items-center gap-2">
            @if($batch->failed_rows > 0)
            <a href="{{ route('tenant.imports.deals.failed', [$tenant->id, $batch->id]) }}"
               class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg text-red-600 bg-red-50 hover:bg-red-100 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3"/></svg>
                Download Failed Rows
            </a>
            @endif
            <a href="{{ route('tenant.deals', $tenant->id) }}"
               class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg bg-[#EDE9FE] hover:bg-purple-100 transition-colors" style="color: #7B61FF;">
                View Deals Pipeline
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="{{ route('tenant.imports.deals', $tenant->id) }}"
               class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg text-white transition-colors"
               style="background: #7B61FF;" onmouseover="this.style.background='#5B45DF'" onmouseout="this.style.background='#7B61FF'">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                Start New Import
            </a>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function importRollback(batchId, tenantId) {
    return {
        showModal:     false,
        loadingPreview:false,
        processing:    false,
        submitting:    false,
        confirmed:     false,
        confirmPhrase: '',
        preview:       null,
        rollbackError: '',

        csrf() {
            return (document.querySelector('meta[name=csrf-token]') || {}).content || '';
        },

        async openRollbackModal() {
            this.showModal      = true;
            this.loadingPreview = true;
            this.confirmed      = false;
            this.confirmPhrase  = '';
            this.rollbackError  = '';
            this.preview        = null;

            try {
                const res = await fetch(`/tenant/${tenantId}/imports/${batchId}/rollback/preview`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                this.preview = await res.json();
            } catch(e) {
                this.preview = { eligible: false, reason: 'Unable to load rollback preview. Please try again.' };
            }
            this.loadingPreview = false;
        },

        canSubmit() {
            return this.confirmed
                && this.confirmPhrase.trim().toUpperCase() === 'UNDO IMPORT'
                && !this.submitting;
        },

        async submitRollback() {
            if (!this.canSubmit()) return;
            this.submitting    = true;
            this.rollbackError = '';

            try {
                const res = await fetch(`/tenant/${tenantId}/imports/${batchId}/rollback`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ confirmation_phrase: this.confirmPhrase }),
                });
                const data = await res.json();

                if (res.ok) {
                    this.processing = true;
                    this.showModal  = true;
                    // Poll for completion
                    const rollbackId = data.rollback_id;
                    const poll = setInterval(async () => {
                        try {
                            const sr = await fetch(`/tenant/${tenantId}/imports/${batchId}/rollback/${rollbackId}/status`, {
                                credentials: 'same-origin',
                                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            });
                            const sd = await sr.json();
                            if (sd.is_done) {
                                clearInterval(poll);
                                // Redirect to rollback report
                                window.location.href = `/tenant/${tenantId}/imports/${batchId}/rollback/${rollbackId}`;
                            }
                        } catch(e) {}
                    }, 3000);
                } else {
                    this.rollbackError = data.error || 'Unable to start rollback. Please try again.';
                    this.submitting = false;
                }
            } catch(e) {
                this.rollbackError = 'Network error. Please check your connection and try again.';
                this.submitting = false;
            }
        },
    };
}
</script>
@endpush
