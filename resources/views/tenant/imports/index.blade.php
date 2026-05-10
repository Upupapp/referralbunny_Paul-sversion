@extends('layouts.app')
@section('title', 'Imports & Exports')

@section('nav')
    @include('tenant._nav')
@endsection

@section('topbar-actions')
    <a href="{{ route('platform.import') }}" class="btn-secondary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
        </svg>
        <span class="hidden sm:inline">Platform Import</span>
    </a>
@endsection

@section('content')
<div class="space-y-5">

    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-[#1E1B4B] font-bold text-lg">Imports & Exports</h2>
                <p class="text-gray-400 text-sm mt-0.5">Bulk import deals, contacts, and referrers</p>
            </div>
        </div>
    </div>

    {{-- Import tool cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">

        {{-- LGU IDS Deal Import — only shown for lgu-ids tenant --}}
        @if(isset($tenant) && $tenant->id === 'lgu-ids')
        <div class="card hover:shadow-md transition-shadow group border-l-4" style="border-left-color: #10B981;">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background: #D1FAE5;">
                    <svg class="w-5 h-5" style="color: #10B981;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 6h18M3 14h18M3 18h18"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-semibold text-[#1E1B4B] text-sm leading-tight">LGU IDS Deal Import</h3>
                    <span class="badge badge-green text-[10px] mt-0.5">LGU IDS Only</span>
                </div>
            </div>
            <p class="text-sm text-gray-400 mb-4">Import municipality deals with automatic pricing validation, Referrer linking, and duplicate detection.</p>
            <div class="flex flex-col gap-2">
                <a href="{{ route('tenant.imports.lgu-ids', $tenant->id) }}"
                   class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-white transition-colors"
                   style="background: #10B981;" onmouseover="this.style.background='#059669'" onmouseout="this.style.background='#10B981'">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Start LGU IDS Import
                </a>
                <a href="{{ route('tenant.imports.lgu-ids.template', $tenant->id) }}"
                   class="inline-flex items-center justify-center gap-1.5 text-xs font-medium transition-colors" style="color: #10B981;"
                   onmouseover="this.style.color='#059669'" onmouseout="this.style.color='#10B981'">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Download Template
                </a>
            </div>
        </div>
        @endif

        {{-- Generic Import Deals — shown for all non-LGU-IDS tenants --}}
        @if(!isset($tenant) || $tenant->id !== 'lgu-ids')
        <div class="card hover:shadow-md transition-shadow group border-l-4" style="border-left-color: #7B61FF;">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background: #EDE9FE;">
                    <svg class="w-5 h-5" style="color: #7B61FF;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-semibold text-[#1E1B4B] text-sm leading-tight">Import Deals</h3>
                    <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full mt-0.5 inline-block" style="background: #EDE9FE; color: #7B61FF;">Your Industry Template</span>
                </div>
            </div>
            <p class="text-sm text-gray-400 mb-4">Bulk import deals into your pipeline using your industry template.</p>
            <div class="flex flex-col gap-2">
                <a href="{{ route('tenant.imports.deals', $tenant->id) }}"
                   class="btn-primary inline-flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Start Deal Import
                </a>
                <a href="{{ route('tenant.imports.deals.template', $tenant->id) }}"
                   class="inline-flex items-center justify-center gap-1.5 text-xs font-medium transition-colors" style="color: #7B61FF;"
                   onmouseover="this.style.color='#5B45DF'" onmouseout="this.style.color='#7B61FF'">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Download Template
                </a>
                <a href="{{ route('tenant.imports.deals.settings', $tenant->id) }}"
                   class="inline-flex items-center justify-center gap-1.5 text-xs font-medium text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Import Settings
                </a>
            </div>
        </div>
        @endif

        {{-- Import Contacts --}}
        <div class="card hover:shadow-md transition-shadow group border-l-4" style="border-left-color: #7B61FF;">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background: #EDE9FE;">
                    <svg class="w-5 h-5" style="color: #7B61FF;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-semibold text-[#1E1B4B] text-sm leading-tight">Import Contacts</h3>
                    <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full mt-0.5 inline-block" style="background: #EDE9FE; color: #7B61FF;">All Tenants</span>
                </div>
            </div>
            <p class="text-sm text-gray-400 mb-4">Bulk import people records, stage invites, and associate contacts to organizations and deals.</p>
            <div class="flex flex-col gap-2">
                <a href="{{ route('tenant.imports.contacts', $tenant->id) }}"
                   class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-white transition-colors"
                   style="background: #7B61FF;" onmouseover="this.style.background='#5B45DF'" onmouseout="this.style.background='#7B61FF'">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Start Contact Import
                </a>
                <a href="{{ route('tenant.imports.contacts.template', $tenant->id) }}"
                   class="inline-flex items-center justify-center gap-1.5 text-xs font-medium transition-colors" style="color: #7B61FF;"
                   onmouseover="this.style.color='#5B45DF'" onmouseout="this.style.color='#7B61FF'">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Download Template
                </a>
            </div>
        </div>

        {{-- Import Referrers --}}
        <div class="card hover:shadow-md transition-shadow cursor-pointer group">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-pink-50 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-[#FF6CAB]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-[#1E1B4B]">Import Referrers</h3>
            </div>
            <p class="text-sm text-gray-400">Upload your referrer list with commission profiles and groups.</p>
            <div class="mt-4 flex items-center text-[#FF6CAB] text-sm font-medium group-hover:gap-2 gap-1 transition-all">
                <span>Get started</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </div>
        </div>
    </div>

    {{-- Recent LGU IDS Imports — only shown for lgu-ids tenant --}}
    @if(isset($tenant) && $tenant->id === 'lgu-ids')
    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <div>
                <h3 class="text-[#1E1B4B] font-semibold text-base">Recent LGU IDS Imports</h3>
                <p class="text-gray-400 text-xs mt-0.5">History of your municipality deal import batches</p>
            </div>
            <a href="{{ route('tenant.imports.lgu-ids', $tenant->id) }}"
               class="btn-secondary text-xs shrink-0">
                View All
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        @if(isset($batches) && $batches && count($batches) > 0)
        <div class="overflow-x-auto -mx-5 sm:mx-0">
            <table class="w-full min-w-[700px]">
                <thead>
                    <tr class="table-head">
                        <th>Date</th>
                        <th>File</th>
                        <th>Rows</th>
                        <th>Created</th>
                        <th>Updated</th>
                        <th>Failed</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($batches as $batch)
                    @php
                        $needsReview = in_array($batch->status, ['previewing', 'previewed', 'needs_review']);
                        $batchUrl = $needsReview
                            ? route('tenant.imports.lgu-ids.preview', [$tenant->id, $batch->id])
                            : route('tenant.imports.lgu-ids.show', [$tenant->id, $batch->id]);
                        $statusMap = [
                            'completed'               => ['class' => 'badge-green',  'label' => 'Completed'],
                            'completed_with_warnings' => ['class' => 'badge-orange', 'label' => 'With Warnings'],
                            'needs_review'            => ['class' => 'badge-orange', 'label' => 'Needs Review'],
                            'failed'                  => ['class' => 'badge-red',    'label' => 'Failed'],
                            'previewed'               => ['class' => 'badge-blue',   'label' => 'Previewed'],
                            'previewing'              => ['class' => 'badge-blue',   'label' => 'Previewing'],
                            'processing'              => ['class' => 'badge-blue',   'label' => 'Processing'],
                        ];
                        $s = $statusMap[$batch->status] ?? ['class' => 'badge-gray', 'label' => ucfirst($batch->status)];
                    @endphp
                    <tr class="table-row cursor-pointer hover:bg-purple-50 transition-colors"
                        onclick="window.location='{{ $batchUrl }}'">
                        <td class="whitespace-nowrap text-gray-500 text-xs">
                            {{ $batch->created_at->format('M d, Y') }}
                        </td>
                        <td class="max-w-[160px]">
                            <a href="{{ $batchUrl }}"
                               class="text-sm font-semibold text-[#7B61FF] hover:underline truncate block transition-colors"
                               title="{{ $batch->file_name }}"
                               onclick="event.stopPropagation()">
                                <svg class="inline-block w-3 h-3 mr-1 shrink-0 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                {{ Str::limit($batch->file_name, 26) }}
                            </a>
                        </td>
                        <td class="tabular-nums">{{ number_format($batch->total_rows) }}</td>
                        <td class="tabular-nums text-emerald-600 font-medium">{{ number_format($batch->successful_rows) }}</td>
                        <td class="tabular-nums text-blue-600 font-medium">{{ number_format($batch->updated_rows) }}</td>
                        <td class="tabular-nums {{ $batch->failed_rows > 0 ? 'text-red-600 font-medium' : 'text-gray-400' }}">
                            {{ number_format($batch->failed_rows) }}
                        </td>
                        <td><span class="badge {{ $s['class'] }}">{{ $s['label'] }}</span></td>
                        <td onclick="event.stopPropagation()">
                            @if($needsReview)
                                <a href="{{ $batchUrl }}" class="text-xs font-semibold text-[#7B61FF] hover:text-purple-800 transition-colors">Review →</a>
                            @else
                                <a href="{{ $batchUrl }}" class="text-xs font-medium text-gray-500 hover:text-gray-700 transition-colors">Report</a>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="flex flex-col items-center justify-center py-10 text-center">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-3" style="background: #D1FAE5;">
                <svg class="w-6 h-6" style="color: #10B981;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M3 6h18M3 14h18M3 18h18"/>
                </svg>
            </div>
            <p class="text-[#1E1B4B] font-medium text-sm">No import history yet</p>
            <p class="text-gray-400 text-xs mt-1">Start your first LGU IDS import to see history here.</p>
            <a href="{{ route('tenant.imports.lgu-ids', $tenant->id) }}"
               class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-medium text-white transition-colors"
               style="background: #10B981;" onmouseover="this.style.background='#059669'" onmouseout="this.style.background='#10B981'">
                Start First Import
            </a>
        </div>
        @endif
    </div>
    @else
    {{-- Generic Deal Import History for non-LGU-IDS tenants --}}
    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <div>
                <h3 class="text-[#1E1B4B] font-semibold text-base">Recent Deal Imports</h3>
                <p class="text-gray-400 text-xs mt-0.5">History of your deal import batches</p>
            </div>
            <a href="{{ route('tenant.imports.deals', $tenant->id) }}"
               class="btn-secondary text-xs shrink-0">
                View All
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        @if(isset($batches) && $batches && count($batches) > 0)
        <div class="overflow-x-auto -mx-5 sm:mx-0">
            <table class="w-full min-w-[700px]">
                <thead>
                    <tr class="table-head">
                        <th>Date</th>
                        <th>File</th>
                        <th>Rows</th>
                        <th>Created</th>
                        <th>Updated</th>
                        <th>Failed</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($batches as $batch)
                    @php
                        $needsReview2 = in_array($batch->status, ['previewing', 'previewed', 'needs_review']);
                        $batchUrl2 = $needsReview2
                            ? route('tenant.imports.deals.preview', [$tenant->id, $batch->id])
                            : route('tenant.imports.deals.show', [$tenant->id, $batch->id]);
                        $statusMap2 = [
                            'completed'               => ['class' => 'badge-green',  'label' => 'Completed'],
                            'completed_with_warnings' => ['class' => 'badge-orange', 'label' => 'With Warnings'],
                            'needs_review'            => ['class' => 'badge-orange', 'label' => 'Needs Review'],
                            'failed'                  => ['class' => 'badge-red',    'label' => 'Failed'],
                            'previewed'               => ['class' => 'badge-blue',   'label' => 'Previewed'],
                            'previewing'              => ['class' => 'badge-blue',   'label' => 'Previewing'],
                            'processing'              => ['class' => 'badge-blue',   'label' => 'Processing'],
                        ];
                        $s2 = $statusMap2[$batch->status] ?? ['class' => 'badge-gray', 'label' => ucfirst($batch->status)];
                    @endphp
                    <tr class="table-row cursor-pointer hover:bg-purple-50 transition-colors"
                        onclick="window.location='{{ $batchUrl2 }}'">
                        <td class="whitespace-nowrap text-gray-500 text-xs">
                            {{ $batch->created_at->format('M d, Y') }}
                        </td>
                        <td class="max-w-[160px]">
                            <a href="{{ $batchUrl2 }}"
                               class="text-sm font-semibold text-[#7B61FF] hover:underline truncate block"
                               title="{{ $batch->file_name }}"
                               onclick="event.stopPropagation()">
                                <svg class="inline-block w-3 h-3 mr-1 shrink-0 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                {{ Str::limit($batch->file_name, 26) }}
                            </a>
                        </td>
                        <td class="tabular-nums">{{ number_format($batch->total_rows) }}</td>
                        <td class="tabular-nums font-medium" style="color:#7B61FF">{{ number_format($batch->successful_rows) }}</td>
                        <td class="tabular-nums text-blue-600 font-medium">{{ number_format($batch->updated_rows) }}</td>
                        <td class="tabular-nums {{ $batch->failed_rows > 0 ? 'text-red-600 font-medium' : 'text-gray-400' }}">
                            {{ number_format($batch->failed_rows) }}
                        </td>
                        <td><span class="badge {{ $s2['class'] }}">{{ $s2['label'] }}</span></td>
                        <td onclick="event.stopPropagation()">
                            @if($needsReview2)
                                <a href="{{ $batchUrl2 }}" class="text-xs font-semibold hover:opacity-80 transition-colors" style="color:#7B61FF">Review →</a>
                            @else
                                <a href="{{ $batchUrl2 }}" class="text-xs font-medium text-gray-500 hover:text-gray-700 transition-colors">Report</a>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="flex flex-col items-center justify-center py-10 text-center">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-3" style="background: #EDE9FE;">
                <svg class="w-6 h-6" style="color: #7B61FF;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
            </div>
            <p class="text-[#1E1B4B] font-medium text-sm">No import history yet</p>
            <p class="text-gray-400 text-xs mt-1">Start your first deal import to see history here.</p>
            <a href="{{ route('tenant.imports.deals', $tenant->id) }}"
               class="mt-4 btn-primary">
                Start First Import
            </a>
        </div>
        @endif
    </div>
    @endif

    {{-- Recent Contact Imports — shown only if contact batches exist --}}
    @php
        $contactBatches = isset($batches) && $batches
            ? (is_a($batches, 'Illuminate\Pagination\LengthAwarePaginator') || is_a($batches, 'Illuminate\Database\Eloquent\Collection')
                ? collect($batches)->filter(fn($b) => ($b->import_type ?? '') === 'contacts')
                : collect($batches)->filter(fn($b) => ($b->import_type ?? '') === 'contacts'))
            : collect();
    @endphp
    @if($contactBatches->count() > 0)
    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <div>
                <h3 class="text-[#1E1B4B] font-semibold text-base">Recent Contact Imports</h3>
                <p class="text-gray-400 text-xs mt-0.5">History of your contact import batches</p>
            </div>
            <a href="{{ route('tenant.imports.contacts', $tenant->id) }}"
               class="btn-secondary text-xs shrink-0">
                View All
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        <div class="overflow-x-auto -mx-5 sm:mx-0">
            <table class="w-full min-w-[700px]">
                <thead>
                    <tr class="table-head">
                        <th>Date</th>
                        <th>File</th>
                        <th>Rows</th>
                        <th>Created</th>
                        <th>Updated</th>
                        <th>Failed</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($contactBatches->take(5) as $batch)
                    <tr class="table-row">
                        <td class="whitespace-nowrap text-gray-500 text-xs">
                            {{ $batch->created_at->format('M d, Y') }}
                        </td>
                        <td class="max-w-[160px]">
                            <span class="text-sm text-[#1E1B4B] font-medium truncate block" title="{{ $batch->file_name }}">
                                {{ Str::limit($batch->file_name, 28) }}
                            </span>
                        </td>
                        <td class="tabular-nums">{{ number_format($batch->total_rows) }}</td>
                        <td class="tabular-nums font-medium" style="color: #7B61FF;">{{ number_format($batch->successful_rows) }}</td>
                        <td class="tabular-nums text-blue-600 font-medium">{{ number_format($batch->updated_rows) }}</td>
                        <td class="tabular-nums {{ $batch->failed_rows > 0 ? 'text-red-600 font-medium' : 'text-gray-400' }}">
                            {{ number_format($batch->failed_rows) }}
                        </td>
                        <td>
                            @php
                                $statusMap = [
                                    'completed'               => ['class' => 'badge-green',  'label' => 'Completed'],
                                    'completed_with_warnings' => ['class' => 'badge-orange', 'label' => 'With Warnings'],
                                    'needs_review'            => ['class' => 'badge-orange', 'label' => 'Needs Review'],
                                    'failed'                  => ['class' => 'badge-red',    'label' => 'Failed'],
                                    'previewed'               => ['class' => 'badge-blue',   'label' => 'Previewed'],
                                    'processing'              => ['class' => 'badge-blue',   'label' => 'Processing'],
                                ];
                                $s = $statusMap[$batch->status] ?? ['class' => 'badge-gray', 'label' => ucfirst($batch->status)];
                            @endphp
                            <span class="badge {{ $s['class'] }}">{{ $s['label'] }}</span>
                        </td>
                        <td>
                            @if(in_array($batch->status, ['previewed', 'needs_review']))
                                <a href="{{ route('tenant.imports.contacts.preview', [$tenant->id, $batch->id]) }}"
                                   class="text-xs font-medium hover:opacity-80 transition-colors" style="color: #7B61FF;">Review</a>
                            @else
                                <a href="{{ route('tenant.imports.contacts.show', [$tenant->id, $batch->id]) }}"
                                   class="text-xs font-medium text-gray-500 hover:text-gray-700 transition-colors">Report</a>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>
@endsection
