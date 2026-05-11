@extends('layouts.reseller')
@section('title', 'My Contacts — Import')

@section('nav')
    @include('reseller._nav')
@endsection

@section('content')
<div x-data="{ showUpload: false }" class="space-y-5">

    {{-- ── Header ───────────────────────────────────────────── --}}
    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 bg-teal-100">
                        <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-teal-100 text-teal-700">
                        My Contacts
                    </span>
                </div>
                <h1 class="text-[#1E1B4B] font-bold text-xl">Contact Import</h1>
                <p class="text-gray-400 text-sm mt-0.5">Import your personal contacts. Your contacts are private to you — other referrers cannot see them.</p>
            </div>
            <a href="{{ route('reseller.dashboard', $tenant->id) }}" class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-gray-600 transition-colors shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Back to Dashboard
            </a>
        </div>
    </div>

    {{-- ── Privacy notice ──────────────────────────────────── --}}
    <div class="flex items-start gap-3 p-4 rounded-2xl border bg-teal-50 border-teal-200">
        <svg class="w-5 h-5 shrink-0 mt-0.5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
        </svg>
        <div>
            <p class="text-sm font-semibold text-teal-800">Your contacts are private</p>
            <p class="text-xs text-teal-700 mt-0.5">Only you and the platform admin can view contacts you import. Other referrers cannot see them.</p>
        </div>
    </div>

    {{-- ── Upload error ────────────────────────────────────── --}}
    @if($errors->has('file'))
    <div class="flex items-start gap-3 p-4 rounded-2xl bg-red-50 border border-red-200">
        <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <p class="text-sm text-red-700">{{ $errors->first('file') }}</p>
    </div>
    @endif

    {{-- ── Action buttons ──────────────────────────────────── --}}
    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ route('reseller.contacts.imports.template', $tenant->id) }}"
           class="btn-secondary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <span class="hidden sm:inline">Download Template</span>
        </a>
        <button @click="showUpload = true"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-white transition-colors bg-teal-600 hover:bg-teal-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
            New Import
        </button>
    </div>

    {{-- ── Import History table ─────────────────────────────── --}}
    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <h3 class="text-[#1E1B4B] font-semibold text-base">My Import History</h3>
        </div>

        @if(isset($batches) && count($batches) > 0)
        <div class="overflow-x-auto -mx-5 sm:mx-0">
            <table class="w-full min-w-[700px]">
                <thead>
                    <tr class="table-head">
                        <th>Date</th>
                        <th>File</th>
                        <th>Total</th>
                        <th>Created</th>
                        <th>Updated</th>
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
                        <td class="tabular-nums font-medium text-teal-600">{{ number_format($batch->successful_rows) }}</td>
                        <td class="tabular-nums font-medium text-blue-600">{{ number_format($batch->updated_rows) }}</td>
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
                            @if(in_array($batch->status, ['previewed', 'needs_review']))
                                <a href="{{ route('reseller.contacts.imports.preview', [$tenant->id, $batch->id]) }}"
                                   class="text-xs font-semibold text-teal-600 hover:text-teal-800 transition-colors">
                                    Review
                                </a>
                            @else
                                <a href="{{ route('reseller.contacts.imports.show', [$tenant->id, $batch->id]) }}"
                                   class="text-xs font-medium text-gray-500 hover:text-gray-700 transition-colors">
                                    Report
                                </a>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if(isset($batches) && method_exists($batches, 'hasPages') && $batches->hasPages())
        <div class="mt-4 px-1">
            {{ $batches->links() }}
        </div>
        @endif

        @else
        {{-- Empty state --}}
        <div class="flex flex-col items-center justify-center py-16 text-center">
            <x-r-bunny variant="sleeping" size="md" :decorative="true" class="mb-5 opacity-80" />
            <h3 class="text-[#1E1B4B] font-semibold text-base">No imports yet</h3>
            <p class="text-gray-400 text-sm mt-1 max-w-xs">Upload a contacts file to get started. Your contacts will remain private to you.</p>
            <button @click="showUpload = true"
                    class="mt-5 inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-white transition-colors bg-teal-600 hover:bg-teal-700">
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
                    <div class="w-8 h-8 rounded-lg bg-teal-100 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-[#1E1B4B] font-semibold text-base">Upload Contact File</h2>
                        <p class="text-xs text-gray-400">Private to you · Max 1,000 rows</p>
                    </div>
                </div>
                <button @click="showUpload = false" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Modal body --}}
            <form action="{{ route('reseller.contacts.imports.upload', $tenant->id) }}"
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
                        <label :class="dragging ? 'border-teal-500 bg-teal-50' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'"
                               class="flex flex-col items-center justify-center gap-3 w-full border-2 border-dashed rounded-2xl p-8 text-center cursor-pointer transition-all">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center" :class="dragging ? 'bg-teal-100' : 'bg-gray-100'">
                                <svg class="w-6 h-6 transition-colors" :class="dragging ? 'text-teal-600' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-[#1E1B4B]" x-text="fileName ? fileName : 'Drop your file here, or click to browse'"></p>
                                <p class="text-xs text-gray-400 mt-1">Accepted: .csv or .xlsx · Max 10MB · Up to 1,000 rows</p>
                            </div>
                            <input type="file"
                                   name="file"
                                   accept=".csv,.xlsx"
                                   class="sr-only"
                                   required
                                   @change="fileName = $event.target.files[0]?.name">
                        </label>
                    </div>

                    {{-- Helper text --}}
                    <div class="flex items-start gap-2 p-3 rounded-xl bg-teal-50">
                        <svg class="w-4 h-4 shrink-0 mt-0.5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-xs text-teal-700">
                            At least one of <span class="font-semibold">Email</span> or <span class="font-semibold">Phone</span> is required per contact row. Maximum <span class="font-semibold">1,000 rows</span> per import.
                        </p>
                    </div>

                    {{-- Template note --}}
                    <p class="text-xs text-gray-400 text-center">
                        Not sure how to format the file?
                        <a href="{{ route('reseller.contacts.imports.template', $tenant->id) }}"
                           class="font-medium text-teal-600 hover:underline">
                            Download our template first.
                        </a>
                    </p>
                </div>

                {{-- Modal footer --}}
                <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50">
                    <button type="button" @click="showUpload = false" class="btn-secondary">Cancel</button>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-5 py-2 rounded-xl text-sm font-medium text-white transition-colors bg-teal-600 hover:bg-teal-700">
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
