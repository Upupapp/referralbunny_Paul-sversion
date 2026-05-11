@extends('layouts.reseller')
@section('title', 'Deal Import Report')
@section('nav') @include('reseller._nav') @endsection

@section('content')
@php
    $statusInfo = match($batch->status) {
        'completed'               => ['badge-green',  'Completed'],
        'completed_with_warnings' => ['badge-orange', 'Completed with Warnings'],
        'needs_review'            => ['badge-orange', 'Needs Review'],
        'failed'                  => ['badge-red',    'Failed'],
        default                   => ['badge-gray',   ucfirst($batch->status)],
    };
    $actionLabels = [
        'create'    => ['bg-teal-100 text-teal-700',  'Created'],
        'update'    => ['bg-blue-100 text-blue-700',  'Updated'],
        'overwrite' => ['bg-blue-100 text-blue-700',  'Updated'],
        'merge'     => ['bg-blue-100 text-blue-700',  'Merged'],
        'skip'      => ['bg-gray-100 text-gray-500',  'Skipped'],
        'blocked'   => ['bg-gray-100 text-gray-500',  'Blocked'],
        'duplicate' => ['bg-gray-100 text-gray-500',  'Already Exists'],
        'review'    => ['bg-gray-100 text-gray-500',  'Already Exists'],
        'failed'    => ['bg-red-100 text-red-600',    'Failed'],
    ];
@endphp

<div class="space-y-5 max-w-4xl mx-auto">

    <a href="{{ route('reseller.deals.imports', $tenant->id) }}"
       class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-teal-600 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Import History
    </a>

    {{-- Header --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-[#1E1B4B]">Deal Import Report</h1>
                <p class="text-sm text-gray-400 mt-1">
                    <span class="font-medium text-gray-600">{{ $batch->file_name }}</span>
                    &nbsp;·&nbsp; {{ $batch->created_at->format('M d, Y \a\t h:i A') }}
                </p>
                <span class="inline-flex items-center mt-2 px-2.5 py-1 rounded-full text-xs font-semibold badge {{ $statusInfo[0] }}">
                    {{ $statusInfo[1] }}
                </span>
            </div>
            @if(session('success'))
            <div class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-teal-50 border border-teal-200 text-teal-700 text-xs font-medium shrink-0">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                {{ session('success') }}
            </div>
            @endif
        </div>
    </div>

    {{-- KPI row --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 text-center">
            <p class="text-2xl font-bold text-[#1E1B4B] tabular-nums">{{ number_format($batch->total_rows) }}</p>
            <p class="text-xs text-gray-400 mt-1">Total Rows</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 text-center">
            <p class="text-2xl font-bold text-teal-600 tabular-nums">{{ number_format($batch->successful_rows) }}</p>
            <p class="text-xs text-gray-400 mt-1">Created</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 text-center">
            <p class="text-2xl font-bold text-blue-600 tabular-nums">{{ number_format($batch->updated_rows ?? 0) }}</p>
            <p class="text-xs text-gray-400 mt-1">Updated</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 text-center">
            <p class="text-2xl font-bold tabular-nums {{ $batch->failed_rows > 0 ? 'text-red-500' : 'text-gray-300' }}">
                {{ number_format($batch->failed_rows) }}
            </p>
            <p class="text-xs text-gray-400 mt-1">Failed</p>
        </div>
    </div>

    {{-- Row table --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100">
            <h2 class="text-sm font-bold text-[#1E1B4B]">Row Details</h2>
        </div>
        @if($rows->isEmpty())
        <div class="px-5 py-10 text-center text-sm text-gray-400">No rows found.</div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100">
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">#</th>
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Deal / Org</th>
                        <th class="text-right px-4 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Amount</th>
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Result</th>
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Note</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($rows as $row)
                    @php
                        $norm = is_array($row->normalized_data) ? $row->normalized_data : (json_decode($row->normalized_data ?? '{}', true) ?? []);
                        [$actCls, $actLabel] = $actionLabels[$row->row_action] ?? ['bg-gray-100 text-gray-500', ucfirst($row->row_action ?? '—')];
                    @endphp
                    <tr class="hover:bg-gray-50/40 transition-colors">
                        <td class="px-4 py-3 text-xs text-gray-400">{{ $row->row_number }}</td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-[#1E1B4B]">{{ $norm['deal_name'] ?? '—' }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $norm['organization_name'] ?? '' }}</p>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-600">
                            @if(!empty($norm['deal_amount']))
                                ₱{{ number_format((float)$norm['deal_amount'], 0) }}
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $actCls }}">
                                {{ $actLabel }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-400 max-w-[200px]">
                            @if($row->error_message)
                                <span class="text-red-500">{{ Str::limit($row->error_message, 60) }}</span>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($rows->hasPages())
        <div class="px-5 py-3 border-t border-gray-100">{{ $rows->links() }}</div>
        @endif
        @endif
    </div>

    <a href="{{ route('reseller.deals.imports', $tenant->id) }}"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200 transition-colors">
        Back to Import History
    </a>

</div>
@endsection
