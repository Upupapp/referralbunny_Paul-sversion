@extends('layouts.app')
@section('title', 'Import Rollback Report')

@section('nav') @include('tenant._nav') @endsection

@section('topbar-actions')
    <a href="{{ route('tenant.imports.deals', $tenantId) }}" class="btn-secondary text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Back to Imports
    </a>
@endsection

@section('content')
@php
    $statusInfo = match($rollback->status) {
        'completed'               => ['class' => 'badge-green',  'label' => 'Completed'],
        'completed_with_warnings' => ['class' => 'badge-orange', 'label' => 'Completed with Warnings'],
        'processing'              => ['class' => 'badge-blue',   'label' => 'Processing'],
        'failed'                  => ['class' => 'badge-red',    'label' => 'Failed'],
        'pending'                 => ['class' => 'badge-gray',   'label' => 'Pending'],
        default                   => ['class' => 'badge-gray',   'label' => ucfirst($rollback->status)],
    };
@endphp

<div class="space-y-5">

    {{-- Header --}}
    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5 mb-2">
                    <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-[#1E1B4B] font-bold text-xl">Import Rollback Report</h1>
                        <p class="text-gray-400 text-sm mt-0.5">
                            <span class="font-medium text-gray-600">{{ $batch->file_name }}</span>
                            &nbsp;·&nbsp; Rolled back {{ $rollback->completed_at?->format('M d, Y \a\t h:i A') ?? 'in progress' }}
                        </p>
                    </div>
                </div>
                <span class="badge {{ $statusInfo['class'] }} text-sm px-3 py-1">{{ $statusInfo['label'] }}</span>
            </div>

            @if(in_array($rollback->status, ['processing', 'pending']))
            <div x-data="{ done: false }" x-init="
                const poll = setInterval(async () => {
                    const r = await fetch('{{ route('tenant.imports.rollback.status', [$tenantId, $batch->id, $rollback->id]) }}', {
                        credentials: 'same-origin', headers: { Accept: 'application/json' }
                    });
                    const d = await r.json();
                    if (d.is_done) { clearInterval(poll); window.location.reload(); }
                }, 4000);
            ">
                <div class="flex items-center gap-2 px-4 py-2 bg-blue-50 border border-blue-200 rounded-xl text-sm text-blue-700">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Rollback in progress… Page will refresh automatically.
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- KPI Summary --}}
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
        @php
            $kpis = [
                ['label' => 'Removed',   'value' => $rollback->records_deleted  ?? 0, 'color' => 'text-red-700',    'bg' => 'bg-red-50'],
                ['label' => 'Restored',  'value' => $rollback->records_restored ?? 0, 'color' => 'text-blue-700',   'bg' => 'bg-blue-50'],
                ['label' => 'Skipped',   'value' => $rollback->records_skipped  ?? 0, 'color' => 'text-gray-600',   'bg' => 'bg-gray-50'],
                ['label' => 'Conflicts', 'value' => $rollback->records_conflict ?? 0, 'color' => 'text-amber-700',  'bg' => 'bg-amber-50'],
                ['label' => 'Failed',    'value' => $rollback->records_failed   ?? 0, 'color' => 'text-red-700',    'bg' => 'bg-red-50'],
            ];
        @endphp
        @foreach($kpis as $kpi)
        <div class="card text-center py-4">
            <p class="text-2xl font-bold {{ $kpi['color'] }} tabular-nums">{{ number_format($kpi['value']) }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $kpi['label'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Conflicts / Warnings --}}
    @php $conflicts = collect($snapshots->items())->where('rollback_status', 'conflict'); @endphp
    @if($conflicts->count() > 0)
    <div class="card space-y-3">
        <h3 class="font-semibold text-[#1E1B4B] text-sm flex items-center gap-2">
            <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            Items Needing Review ({{ $conflicts->count() }})
        </h3>
        <div class="space-y-2">
            @foreach($conflicts as $snap)
            <div class="flex items-start gap-3 px-3 py-2.5 bg-amber-50 border border-amber-100 rounded-xl">
                <div class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-200 text-amber-800 shrink-0 mt-0.5">{{ strtoupper($snap->entity_type) }}</div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-amber-800">{{ $snap->rollback_reason }}</p>
                    <p class="text-[10px] text-amber-600 mt-0.5 font-mono truncate">ID: {{ $snap->entity_id }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Snapshot log --}}
    <div class="card p-0 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100">
            <h3 class="font-semibold text-[#1E1B4B] text-sm">Rollback Log</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/50">
                        <th class="text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider px-4 py-3">Type</th>
                        <th class="text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider px-4 py-3">Operation</th>
                        <th class="text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider px-4 py-3">Rollback Status</th>
                        <th class="text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider px-4 py-3 hidden sm:table-cell">Reason</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($snapshots as $snap)
                    @php
                        $rb = match($snap->rollback_status) {
                            'rolled_back' => ['badge-green', 'Rolled Back'],
                            'conflict'    => ['badge-orange', 'Conflict'],
                            'skipped'     => ['badge-gray', 'Skipped'],
                            'failed'      => ['badge-red', 'Failed'],
                            default       => ['badge-gray', ucfirst($snap->rollback_status)],
                        };
                    @endphp
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-4 py-3">
                            <span class="text-xs font-medium text-gray-700 capitalize">{{ $snap->entity_type }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-xs text-gray-500 capitalize">{{ str_replace('_', ' ', $snap->operation_type ?? $snap->action ?? '—') }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="badge {{ $rb[0] }} text-xs">{{ $rb[1] }}</span>
                        </td>
                        <td class="px-4 py-3 hidden sm:table-cell">
                            <span class="text-xs text-gray-400 truncate max-w-xs block">{{ $snap->rollback_reason ?: '—' }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="py-10 text-center text-gray-400 text-sm">No rollback log entries found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(method_exists($snapshots, 'hasPages') && $snapshots->hasPages())
        <div class="px-5 py-4 border-t border-gray-100">
            {{ $snapshots->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
