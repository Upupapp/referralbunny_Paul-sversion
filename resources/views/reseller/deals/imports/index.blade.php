@extends('layouts.reseller')
@section('title', 'Import Deals')
@section('nav') @include('reseller._nav') @endsection

@section('content')
<div x-data="{ showUpload: false }" class="space-y-5 max-w-4xl mx-auto">

    {{-- Header --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-[#1E1B4B]">Import Deals</h1>
                <p class="text-sm text-gray-400 mt-0.5">Upload a spreadsheet of deals to add them to your pipeline. Only your email will be used as the Referrer.</p>
            </div>
            <a href="{{ route('reseller.deals', $tenant->id) }}"
               class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-gray-600 transition-colors shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                My Deals
            </a>
        </div>
    </div>

    {{-- Errors from upload --}}
    @if($errors->has('file'))
    <div class="flex items-start gap-3 p-4 rounded-2xl bg-red-50 border border-red-200">
        <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <p class="text-sm text-red-700">{{ $errors->first('file') }}</p>
    </div>
    @endif

    {{-- Action row --}}
    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ route('reseller.deals.imports.template', $tenant->id) }}" class="btn-secondary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Download Template
        </a>
        <button @click="showUpload = true"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white transition-colors"
                style="background:#0D9488">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
            New Import
        </button>
    </div>

    {{-- Import history --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100">
            <h2 class="text-sm font-bold text-[#1E1B4B]">My Import History</h2>
        </div>

        @if($batches->total() > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100">
                        <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Date</th>
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">File</th>
                        <th class="text-right px-4 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Total</th>
                        <th class="text-right px-4 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Created</th>
                        <th class="text-right px-4 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Failed</th>
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Status</th>
                        <th class="text-right px-5 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($batches as $batch)
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
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-5 py-3 whitespace-nowrap">
                            <p class="text-xs text-gray-600">{{ $batch->created_at->format('M d, Y') }}</p>
                            <p class="text-[10px] text-gray-400">{{ $batch->created_at->format('h:i A') }}</p>
                        </td>
                        <td class="px-4 py-3 max-w-[180px]">
                            <p class="text-sm text-[#1E1B4B] truncate font-medium" title="{{ $batch->file_name }}">
                                {{ Str::limit($batch->file_name, 28) }}
                            </p>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-600">{{ number_format($batch->total_rows) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-medium text-teal-600">{{ number_format($batch->successful_rows) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums {{ $batch->failed_rows > 0 ? 'font-medium text-red-600' : 'text-gray-400' }}">
                            {{ number_format($batch->failed_rows) }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="badge {{ $s[0] }}">{{ $s[1] }}</span>
                        </td>
                        <td class="px-5 py-3 text-right">
                            @if(in_array($batch->status, ['previewed', 'needs_review']))
                                <a href="{{ route('reseller.deals.imports.preview', [$tenant->id, $batch->id]) }}"
                                   class="text-xs font-semibold text-teal-600 hover:text-teal-800 transition-colors">
                                    Review
                                </a>
                            @else
                                <a href="{{ route('reseller.deals.imports.show', [$tenant->id, $batch->id]) }}"
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
        @if($batches->hasPages())
        <div class="px-5 py-3 border-t border-gray-100">{{ $batches->links() }}</div>
        @endif

        @else
        <div class="flex flex-col items-center justify-center py-14 text-center px-6">
            <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-3" style="background:#CCFBF1">
                <svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
            <p class="text-sm font-semibold text-[#1E1B4B]">No imports yet</p>
            <p class="text-xs text-gray-400 mt-1 max-w-xs">Download the template, fill in your deals, and upload to get started.</p>
            <button @click="showUpload = true"
                    class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white"
                    style="background:#0D9488">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                Upload Your First File
            </button>
        </div>
        @endif
    </div>

    {{-- Upload Modal --}}
    <div x-show="showUpload" x-cloak
         class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
         @click.self="showUpload = false">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">

            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div>
                    <h2 class="text-base font-bold text-[#1E1B4B]">Upload Deal Import File</h2>
                    <p class="text-xs text-gray-400">Your deals will be assigned to you as the Referrer</p>
                </div>
                <button @click="showUpload = false" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form action="{{ route('reseller.deals.imports.upload', $tenant->id) }}"
                  method="POST"
                  enctype="multipart/form-data"
                  x-data="{ dragging: false, fileName: null }">
                @csrf
                <div class="p-6 space-y-4">
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
                                <p class="text-sm font-medium text-[#1E1B4B]" x-text="fileName ?? 'Drop your file here, or click to browse'"></p>
                                <p class="text-xs text-gray-400 mt-1">Accepted: .csv or .xlsx · Max 10 MB</p>
                            </div>
                            <input type="file" name="file" accept=".csv,.xlsx" class="sr-only" required
                                   @change="fileName = $event.target.files[0]?.name">
                        </label>
                    </div>
                    <p class="text-xs text-gray-400 text-center">
                        Your referrer email will be automatically attached to all rows.
                        <a href="{{ route('reseller.deals.imports.template', $tenant->id) }}" class="font-medium text-teal-600 hover:underline">
                            Download our template first.
                        </a>
                    </p>
                </div>
                <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50">
                    <button type="button" @click="showUpload = false" class="btn-secondary">Cancel</button>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-5 py-2 rounded-xl text-sm font-semibold text-white transition-colors"
                            style="background:#0D9488">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        Upload & Preview
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
