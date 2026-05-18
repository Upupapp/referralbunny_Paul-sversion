@extends('layouts.app')
@section('title', 'Contact Import Report')

@section('nav')
    @include('tenant._nav')
@endsection

@section('topbar-actions')
    <a href="{{ route('tenant.imports.contacts', $tenant->id) }}" class="btn-secondary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        <span class="hidden sm:inline">Back to Imports</span>
    </a>
    <a href="{{ route('tenant.imports.contacts', $tenant->id) }}"
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-[#1E1B4B] font-bold text-xl leading-tight">Contact Import Report</h1>
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
            <div class="flex flex-col gap-2 shrink-0 min-w-[180px]">
                @if($batch->failed_rows > 0)
                <a href="{{ route('tenant.imports.contacts.failed', [$tenant->id, $batch->id]) }}"
                   class="inline-flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-medium text-red-600 bg-red-50 hover:bg-red-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Download Failed Rows
                </a>
                @endif
                <a href="{{ route('tenant.contacts', $tenant->id) }}"
                   class="inline-flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-medium bg-[#EDE9FE] hover:bg-purple-100 transition-colors" style="color: #7B61FF;">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    View Contacts
                </a>
                @if(isset($batch->unknown_referrer_rows) && $batch->unknown_referrer_rows > 0)
                <a href="{{ route('tenant.resellers', $tenant->id) }}"
                   class="inline-flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-medium text-amber-700 bg-amber-50 hover:bg-amber-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    Invite Referrer Candidates
                </a>
                @endif
                <a href="{{ route('tenant.imports.contacts', $tenant->id) }}"
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
                <p class="text-2xl font-bold tabular-nums text-emerald-600">{{ number_format($batch->successful_rows) }}</p>
                <p class="text-xs text-gray-400 mt-1">New contacts added</p>
            </div>
            <div class="kpi-icon bg-emerald-50">
                <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
        </div>

        {{-- Updated --}}
        <div class="kpi-card">
            <div>
                <p class="text-xs font-medium text-gray-500 mb-1">Updated</p>
                <p class="text-2xl font-bold text-blue-600 tabular-nums">{{ number_format($batch->updated_rows) }}</p>
                <p class="text-xs text-gray-400 mt-1">Existing contacts merged</p>
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

    {{-- ── Extra Summary Cards ──────────────────────────────── --}}
    @php
        $possibleDupCount    = $batch->possible_duplicate_rows ?? 0;
        $sameEmailDiffRefCount = $batch->same_email_different_referrer_rows ?? 0;
        $unknownOrgCount     = $batch->unknown_organization_rows ?? 0;
        $unknownDealCount    = $batch->unknown_deal_rows ?? 0;
        $showAdmin           = isset($isAdmin) && $isAdmin;
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 {{ $showAdmin && $sameEmailDiffRefCount > 0 ? 'lg:grid-cols-4' : 'lg:grid-cols-3' }} gap-4">

        {{-- Possible Duplicates --}}
        <div class="card">
            <div class="flex items-center gap-2.5 mb-3">
                <div class="w-8 h-8 rounded-lg bg-orange-50 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h4 class="text-sm font-semibold text-[#1E1B4B]">Possible Duplicates</h4>
            </div>
            <p class="text-2xl font-bold tabular-nums {{ $possibleDupCount > 0 ? 'text-orange-500' : 'text-gray-400' }}">
                {{ number_format($possibleDupCount) }}
            </p>
            <p class="text-xs text-gray-400 mt-1">Same-owner contacts that may match existing records</p>
        </div>

        {{-- Unknown Organizations --}}
        <div class="card">
            <div class="flex items-center gap-2.5 mb-3">
                <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
                    </svg>
                </div>
                <h4 class="text-sm font-semibold text-[#1E1B4B]">Unknown Organizations</h4>
            </div>
            <p class="text-2xl font-bold tabular-nums {{ $unknownOrgCount > 0 ? 'text-blue-500' : 'text-gray-400' }}">
                {{ number_format($unknownOrgCount) }}
            </p>
            <p class="text-xs text-gray-400 mt-1">Organization names not found in system</p>
        </div>

        {{-- Unknown Deals --}}
        <div class="card">
            <div class="flex items-center gap-2.5 mb-3">
                <div class="w-8 h-8 rounded-lg bg-sky-50 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
                <h4 class="text-sm font-semibold text-[#1E1B4B]">Unknown Deals</h4>
            </div>
            <p class="text-2xl font-bold tabular-nums {{ $unknownDealCount > 0 ? 'text-sky-500' : 'text-gray-400' }}">
                {{ number_format($unknownDealCount) }}
            </p>
            <p class="text-xs text-gray-400 mt-1">Deal references not matched in system</p>
        </div>

        {{-- Same Email Across Referrers (admin only) --}}
        @if($showAdmin && $sameEmailDiffRefCount > 0)
        <div class="card" style="border-left: 3px solid #7B61FF;">
            <div class="flex items-center gap-2.5 mb-3">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background: #EDE9FE;">
                    <svg class="w-4 h-4" style="color: #7B61FF;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-[#1E1B4B]">Same Email Across Referrers</h4>
                    <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full" style="background: #EDE9FE; color: #7B61FF;">Admin View</span>
                </div>
            </div>
            <p class="text-2xl font-bold tabular-nums" style="color: #7B61FF;">
                {{ number_format($sameEmailDiffRefCount) }}
            </p>
            <p class="text-xs text-gray-400 mt-1">Same email found under different Referrers. Listed separately — not merged.</p>
        </div>
        @endif
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
            <table class="w-full min-w-[900px]">
                <thead>
                    <tr class="table-head">
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Contact Type</th>
                        <th>Intended Role</th>
                        <th>Status</th>
                        <th>Action Taken</th>
                        <th>Deal / Org</th>
                        <th>Errors</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                    @php
                        $computed    = is_array($row->computed_data) ? $row->computed_data : (json_decode($row->computed_data, true) ?? []);
                        $errors      = is_array($row->issue_codes) ? $row->issue_codes : (json_decode($row->issue_codes, true) ?? []);
                        $hasContact  = !empty($row->created_deal_id) || !empty($row->updated_contact_id ?? null);
                        $statusKey   = match(true) {
                            !empty($row->error_message)                                                       => 'failed',
                            $row->row_action === 'skip'                                                       => 'skipped',
                            in_array($row->row_action, ['blocked', 'failed'])                                 => 'blocked',
                            in_array($row->row_action, ['create', 'update', 'overwrite', 'merge']) && $hasContact => $row->row_action === 'create' ? 'created' : 'updated',
                            default                                                                           => $row->validation_status ?? 'ready',
                        };
                        $rowBorder = match($statusKey) {
                            'completed','created' => 'border-l-2 border-l-emerald-400',
                            'updated'             => 'border-l-2 border-l-blue-400',
                            'failed','blocked'    => 'border-l-2 border-l-red-400',
                            default               => '',
                        };
                        $badgeMap = [
                            'created'          => ['badge-green',  'Created'],
                            'updated'          => ['badge-blue',   'Updated'],
                            'update'           => ['badge-blue',   'Updated'],
                            'overwrite'        => ['badge-blue',   'Updated'],
                            'merge'            => ['badge-blue',   'Merged'],
                            'skipped'          => ['badge-gray',   'Skipped'],
                            'skip'             => ['badge-gray',   'Skipped'],
                            'already_existing' => ['badge-gray',   'Already Exists'],
                            'duplicate'        => ['badge-gray',   'Already Exists'],
                            'review'           => ['badge-gray',   'Already Exists'],
                            'blocked'          => ['badge-gray',   'Blocked'],
                            'failed'           => ['badge-red',    'Failed'],
                            'completed'        => ['badge-green',  'Completed'],
                        ];
                        $sb = $badgeMap[$statusKey] ?? ['badge-gray', ucfirst($statusKey)];
                    @endphp
                    <tr class="table-row {{ $rowBorder }}">
                        <td class="text-xs text-gray-400 tabular-nums">
                            {{ isset($rows) && method_exists($rows, 'firstItem') ? ($rows->firstItem() + $loop->index) : ($loop->index + 1) }}
                        </td>
                        <td class="font-medium text-[#1E1B4B]">
                            {{ trim(($row->raw_data['first_name'] ?? '') . ' ' . ($row->raw_data['last_name'] ?? '')) ?: '—' }}
                        </td>
                        <td>
                            <span class="text-sm text-gray-700">{{ $row->raw_data['email'] ?? '—' }}</span>
                        </td>
                        <td class="text-xs text-gray-600">
                            {{ $row->raw_data['contact_type'] ?? '—' }}
                        </td>
                        <td class="text-xs text-gray-600">
                            {{ $row->raw_data['intended_role'] ?? '—' }}
                        </td>
                        <td>
                            <span class="badge {{ $sb[0] }}">{{ $sb[1] }}</span>
                        </td>
                        <td class="text-xs text-gray-500">
                            {{ $row->action_taken ? ucfirst(str_replace('_', ' ', $row->action_taken)) : '—' }}
                        </td>
                        <td class="text-sm text-gray-600">
                            @if(!empty($row->raw_data['deal_name']))
                                <span class="text-xs font-medium text-sky-600">{{ $row->raw_data['deal_name'] }}</span>
                                @if(!empty($row->raw_data['organization_name']))
                                    <span class="text-gray-300 mx-1">/</span>
                                @endif
                            @endif
                            @if(!empty($row->raw_data['organization_name']))
                                <span class="text-xs text-gray-600">{{ $row->raw_data['organization_name'] }}</span>
                            @endif
                            @if(empty($row->raw_data['deal_name']) && empty($row->raw_data['organization_name']))
                                <span class="text-gray-300">—</span>
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
            <a href="{{ route('tenant.imports.contacts.failed', [$tenant->id, $batch->id]) }}"
               class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg text-red-600 bg-red-50 hover:bg-red-100 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3"/></svg>
                Download Failed Rows
            </a>
            @endif
            <a href="{{ route('tenant.contacts', $tenant->id) }}"
               class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg bg-[#EDE9FE] hover:bg-purple-100 transition-colors" style="color: #7B61FF;">
                View Contacts
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="{{ route('tenant.resellers', $tenant->id) }}"
               class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg text-amber-700 bg-amber-50 hover:bg-amber-100 transition-colors">
                Invite Referrer Candidates
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="{{ route('tenant.imports.contacts', $tenant->id) }}"
               class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg text-white transition-colors"
               style="background: #7B61FF;" onmouseover="this.style.background='#5B45DF'" onmouseout="this.style.background='#7B61FF'">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                Start New Import
            </a>
        </div>
    </div>

</div>
@endsection
