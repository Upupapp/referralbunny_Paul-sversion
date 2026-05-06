@extends('layouts.app')
@section('title', 'Deal Import')

@section('nav')
    @include('tenant._nav')
@endsection

@section('topbar-actions')
    <a href="{{ route('tenant.imports.deals.template', $tenant->id) }}" class="btn-secondary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <span class="hidden sm:inline">Download Template</span>
    </a>
    <a href="{{ route('tenant.imports.deals.settings', $tenant->id) }}" class="btn-secondary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        <span class="hidden sm:inline">Import Settings</span>
    </a>
    <button @click="showUpload = true" class="btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
        </svg>
        <span class="hidden sm:inline">New Import</span>
    </button>
@endsection

@section('content')
<div x-data="{ showUpload: false }" class="space-y-5">

    {{-- ── Page header ─────────────────────────────────────── --}}
    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background: #EDE9FE;">
                        <svg class="w-4 h-4" style="color: #7B61FF;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                        </svg>
                    </div>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full" style="background: #EDE9FE; color: #7B61FF;">
                        {{ ucfirst(str_replace('_', ' ', $tenant->industry ?? 'Generic')) }}
                    </span>
                </div>
                <h1 class="text-[#1E1B4B] font-bold text-xl">Deal Import</h1>
                <p class="text-gray-400 text-sm mt-0.5">Import deals into your pipeline using your industry template.</p>
            </div>
            <a href="{{ route('tenant.imports', $tenant->id) }}" class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-gray-600 transition-colors shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Back to Imports
            </a>
        </div>
    </div>

    {{-- ── Pending Referrer Invites alert ──────────────────── --}}
    @if(isset($pendingInvites) && $pendingInvites > 0)
    <div class="flex items-start gap-3 p-4 rounded-2xl border" style="background: #FFFBEB; border-color: #FDE68A;">
        <svg class="w-5 h-5 shrink-0 mt-0.5" style="color: #D97706;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold" style="color: #92400E;">
                {{ number_format($pendingInvites) }} {{ Str::plural('referrer', $pendingInvites) }} from import need{{ $pendingInvites === 1 ? 's' : '' }} to be invited.
            </p>
            <p class="text-xs mt-0.5" style="color: #B45309;">
                Go to the Referrers tab to send their invitation emails.
            </p>
        </div>
        <a href="{{ route('tenant.resellers', $tenant->id) }}"
           class="shrink-0 inline-flex items-center gap-1 text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors"
           style="background: #FDE68A; color: #92400E;" onmouseover="this.style.background='#FCD34D'" onmouseout="this.style.background='#FDE68A'">
            Go to Referrers
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
    </div>
    @endif

    {{-- ── KPI Stats ─────────────────────────────────────────── --}}
    @if(isset($batches) && $batches->total() > 0)
    @php
        $totalImports   = $batches->total();
        $totalCreated   = $batches->sum('successful_rows');
        $needsAttention = $batches->getCollection()->filter(fn($b) => in_array($b->status, ['needs_review', 'completed_with_warnings']))->count();
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        {{-- Total Imports --}}
        <div class="kpi-card">
            <div>
                <p class="text-xs font-medium text-gray-500 mb-1">Total Imports</p>
                <p class="text-2xl font-bold text-[#1E1B4B] tabular-nums">{{ number_format($totalImports) }}</p>
                <p class="text-xs text-gray-400 mt-1">All-time batches</p>
            </div>
            <div class="kpi-icon" style="background: #EDE9FE;">
                <svg class="w-5 h-5" style="color: #7B61FF;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/>
                </svg>
            </div>
        </div>

        {{-- Total Deals Created --}}
        <div class="kpi-card">
            <div>
                <p class="text-xs font-medium text-gray-500 mb-1">Total Deals Created</p>
                <p class="text-2xl font-bold text-[#1E1B4B] tabular-nums">{{ number_format($totalCreated) }}</p>
                <p class="text-xs text-gray-400 mt-1">Deals successfully imported</p>
            </div>
            <div class="kpi-icon bg-[#EDE9FE]">
                <svg class="w-5 h-5 text-[#7B61FF]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/>
                </svg>
            </div>
        </div>

        {{-- Needs Attention --}}
        <div class="kpi-card">
            <div>
                <p class="text-xs font-medium text-gray-500 mb-1">Needs Attention</p>
                <p class="text-2xl font-bold tabular-nums {{ $needsAttention > 0 ? 'text-amber-600' : 'text-[#1E1B4B]' }}">{{ number_format($needsAttention) }}</p>
                <p class="text-xs text-gray-400 mt-1">Batches with warnings or pending review</p>
            </div>
            <div class="kpi-icon {{ $needsAttention > 0 ? 'bg-amber-50' : 'bg-gray-50' }}">
                <svg class="w-5 h-5 {{ $needsAttention > 0 ? 'text-amber-500' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
        </div>
    </div>
    @endif

    {{-- ── Recent Imports table ─────────────────────────────── --}}
    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <h3 class="text-[#1E1B4B] font-semibold text-base">Import History</h3>
        </div>

        @if(isset($batches) && $batches->total() > 0)
        <div class="overflow-x-auto -mx-5 sm:mx-0">
            <table class="w-full min-w-[800px]">
                <thead>
                    <tr class="table-head">
                        <th>Date</th>
                        <th>File</th>
                        <th>Total</th>
                        <th>Created</th>
                        <th>Updated</th>
                        <th>Skipped</th>
                        <th>Failed</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($batches as $batch)
                    <tr class="table-row">
                        <td class="whitespace-nowrap">
                            <span class="text-xs text-gray-500">{{ $batch->created_at->format('M d, Y') }}</span>
                            <span class="block text-[10px] text-gray-400">{{ $batch->created_at->format('h:i A') }}</span>
                        </td>
                        <td class="max-w-[180px]">
                            <span class="text-sm text-[#1E1B4B] font-medium truncate block" title="{{ $batch->file_name }}">
                                {{ Str::limit($batch->file_name, 30) }}
                            </span>
                        </td>
                        <td class="tabular-nums text-gray-700">{{ number_format($batch->total_rows) }}</td>
                        <td class="tabular-nums font-medium" style="color: #7B61FF;">{{ number_format($batch->successful_rows) }}</td>
                        <td class="tabular-nums font-medium text-blue-600">{{ number_format($batch->updated_rows) }}</td>
                        <td class="tabular-nums text-gray-400">{{ number_format($batch->skipped_rows) }}</td>
                        <td class="tabular-nums {{ $batch->failed_rows > 0 ? 'font-medium text-red-600' : 'text-gray-400' }}">
                            {{ number_format($batch->failed_rows) }}
                        </td>
                        <td>
                            @php
                                $s = match($batch->status) {
                                    'completed'               => ['badge-green',  'Completed'],
                                    'completed_with_warnings' => ['badge-orange', 'Warnings'],
                                    'needs_review'            => ['badge-orange', 'Needs Review'],
                                    'failed'                  => ['badge-red',    'Failed'],
                                    'previewed'               => ['badge-blue',   'Previewed'],
                                    'processing'              => ['badge-blue',   'Processing'],
                                    default                   => ['badge-gray',   ucfirst($batch->status)],
                                };
                            @endphp
                            <span class="badge {{ $s[0] }}">{{ $s[1] }}</span>
                        </td>
                        <td>
                            <div class="flex items-center gap-2">
                                @if(in_array($batch->status, ['previewed', 'needs_review']))
                                    <a href="{{ route('tenant.imports.deals.preview', [$tenant->id, $batch->id]) }}"
                                       class="text-xs font-semibold hover:opacity-80 transition-colors" style="color: #7B61FF;">
                                        Review
                                    </a>
                                @else
                                    <a href="{{ route('tenant.imports.deals.show', [$tenant->id, $batch->id]) }}"
                                       class="text-xs font-medium text-gray-500 hover:text-gray-700 transition-colors">
                                        Report
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($batches->hasPages())
        <div class="mt-4 px-1">
            {{ $batches->links() }}
        </div>
        @endif

        @else
        {{-- Empty state --}}
        <div class="flex flex-col items-center justify-center py-16 text-center">
            <x-r-bunny variant="sleeping" size="md" :decorative="true" class="mb-5 opacity-80" />
            <h3 class="text-[#1E1B4B] font-semibold text-base">No import history yet</h3>
            <p class="text-gray-400 text-sm mt-1 max-w-xs">Upload your first file to get started.</p>
            <button @click="showUpload = true"
                    class="mt-5 inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-white transition-colors"
                    style="background: #7B61FF;" onmouseover="this.style.background='#5B45DF'" onmouseout="this.style.background='#7B61FF'">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Upload Your First File
            </button>
        </div>
        @endif
    </div>

    {{-- ── Upload Modal ─────────────────────────────────────── --}}
    <div x-show="showUpload"
         x-cloak
         class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click.self="showUpload = false">

        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95">

            {{-- Modal header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background: #EDE9FE;">
                        <svg class="w-4 h-4" style="color: #7B61FF;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-[#1E1B4B] font-semibold text-base">Upload Deal Import File</h2>
                        <p class="text-xs text-gray-400">{{ ucfirst(str_replace('_', ' ', $tenant->industry ?? 'Generic')) }} template</p>
                    </div>
                </div>
                <button @click="showUpload = false" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Modal body --}}
            <form action="{{ route('tenant.imports.deals.upload', $tenant->id) }}"
                  method="POST"
                  enctype="multipart/form-data"
                  x-data="{ dragging: false, fileName: null }">
                @csrf
                <div class="p-6 space-y-4">

                    {{-- Drag-drop zone --}}
                    <div class="relative"
                         @dragover.prevent="dragging = true"
                         @dragleave.prevent="dragging = false"
                         @drop.prevent="dragging = false; fileName = $event.dataTransfer.files[0]?.name; $el.querySelector('input[type=file]').files = $event.dataTransfer.files">
                        <label :class="dragging ? 'border-[#7B61FF] bg-[#EDE9FE]' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'"
                               class="flex flex-col items-center justify-center gap-3 w-full border-2 border-dashed rounded-2xl p-8 text-center cursor-pointer transition-all">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center" :style="dragging ? 'background:#EDE9FE' : 'background:#F3F4F6'">
                                <svg class="w-6 h-6 transition-colors" :style="dragging ? 'color:#7B61FF' : 'color:#9CA3AF'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-[#1E1B4B]" x-text="fileName ? fileName : 'Drop your file here, or click to browse'"></p>
                                <p class="text-xs text-gray-400 mt-1">Accepted: .csv or .xlsx · Max 10MB · Columns can be in any order</p>
                            </div>
                            <input type="file"
                                   name="file"
                                   accept=".csv,.xlsx"
                                   class="sr-only"
                                   required
                                   @change="fileName = $event.target.files[0]?.name">
                        </label>
                    </div>

                    {{-- Template note --}}
                    <p class="text-xs text-gray-400 text-center">
                        Not sure how to format the file?
                        <a href="{{ route('tenant.imports.deals.template', $tenant->id) }}"
                           class="font-medium hover:underline" style="color: #7B61FF;">
                            Download our template first.
                        </a>
                    </p>
                </div>

                {{-- Modal footer --}}
                <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50">
                    <button type="button" @click="showUpload = false" class="btn-secondary">Cancel</button>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-5 py-2 rounded-xl text-sm font-medium text-white transition-colors"
                            style="background: #7B61FF;" onmouseover="this.style.background='#5B45DF'" onmouseout="this.style.background='#7B61FF'">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        Upload & Preview
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
