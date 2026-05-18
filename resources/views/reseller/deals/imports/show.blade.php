@extends('layouts.reseller')
@section('title', 'Deal Import Report')
@section('nav') @include('reseller._nav') @endsection

@section('content')
@php
    $dupCrossReferrer = (int) ($batch->same_email_different_referrer_rows ?? 0);
    $dupPossible      = (int) ($batch->possible_duplicate_rows ?? 0);
    $hasDuplicates    = $dupCrossReferrer > 0 || $dupPossible > 0;

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

{{-- ── Duplicate Deals Warning Modal ──────────────────────────────────── --}}
@if($hasDuplicates)
<div x-data="{ open: true }"
     x-show="open"
     x-cloak
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     style="position:fixed;inset:0;background:rgba(15,15,35,0.55);z-index:9999;display:flex;align-items:center;justify-content:center;padding:16px"
     role="dialog" aria-modal="true" aria-labelledby="dup-modal-title">
    <div style="background:white;border-radius:20px;max-width:460px;width:100%;box-shadow:0 24px 64px rgba(0,0,0,.2);overflow:hidden"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100">

        {{-- Header stripe --}}
        <div style="background:linear-gradient(135deg,#FEF3C7,#FFF7ED);padding:20px 24px 16px;border-bottom:1px solid #FDE68A">
            <div style="display:flex;align-items:flex-start;gap:12px">
                <div style="width:40px;height:40px;border-radius:12px;background:#FEF3C7;border:1.5px solid #FDE68A;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <svg style="width:20px;height:20px;color:#D97706" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div>
                    <h3 id="dup-modal-title" style="font-size:15px;font-weight:700;color:#92400E;margin:0 0 4px">Possible Duplicate Deals Detected</h3>
                    <p style="font-size:12px;color:#B45309;margin:0;line-height:1.5">Some deals in this import may already exist in the system, possibly assigned to other referrers.</p>
                </div>
            </div>
        </div>

        {{-- Body --}}
        <div style="padding:20px 24px">
            <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px">
                @if($dupCrossReferrer > 0)
                <div style="display:flex;align-items:center;gap:12px;padding:12px 14px;background:#FEF3C7;border-radius:10px;border:1px solid #FDE68A">
                    <div style="width:32px;height:32px;border-radius:8px;background:#FDE68A;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:14px;font-weight:700;color:#92400E">
                        {{ $dupCrossReferrer }}
                    </div>
                    <div>
                        <p style="font-size:13px;font-weight:600;color:#92400E;margin:0">{{ Str::plural('deal', $dupCrossReferrer) }} matched another referrer's active deal</p>
                        <p style="font-size:11px;color:#B45309;margin:2px 0 0">These deals may already be actively worked by another referrer in this workspace.</p>
                    </div>
                </div>
                @endif

                @if($dupPossible > 0)
                <div style="display:flex;align-items:center;gap:12px;padding:12px 14px;background:#F3F4F6;border-radius:10px;border:1px solid #E5E7EB">
                    <div style="width:32px;height:32px;border-radius:8px;background:#E5E7EB;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:14px;font-weight:700;color:#374151">
                        {{ $dupPossible }}
                    </div>
                    <div>
                        <p style="font-size:13px;font-weight:600;color:#374151;margin:0">{{ Str::plural('deal', $dupPossible) }} flagged as possible {{ Str::plural('duplicate', $dupPossible) }}</p>
                        <p style="font-size:11px;color:#6B7280;margin:2px 0 0">These may be near-matches. Check the row details below for more information.</p>
                    </div>
                </div>
                @endif
            </div>

            <div style="background:#F0FDFA;border-radius:10px;padding:10px 14px;margin-bottom:20px;border:1px solid #99F6E4">
                <p style="font-size:12px;color:#0F766E;margin:0;line-height:1.5">
                    <strong>What to do:</strong> Review the Row Details below. Deals marked "Already Exists" or "Updated" were matched to existing records. Contact your admin if you believe a deal conflict needs resolution.
                </p>
            </div>

            <div style="display:flex;gap:10px">
                <button @click="open = false"
                        style="flex:1;padding:10px;border-radius:12px;background:linear-gradient(135deg,#0D9488,#0F766E);color:white;border:none;font-size:13px;font-weight:600;cursor:pointer;box-shadow:0 2px 8px rgba(13,148,136,.25)">
                    Review Import Details
                </button>
                <button @click="open = false"
                        style="padding:10px 16px;border-radius:12px;border:1.5px solid #E5E7EB;background:white;color:#6B7280;font-size:13px;font-weight:600;cursor:pointer">
                    Dismiss
                </button>
            </div>
        </div>
    </div>
</div>
@endif

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
                        // If execution failed (error_message set), show Failed regardless of row_action
                    if (!empty($row->error_message)) {
                        [$actCls, $actLabel] = ['bg-red-100 text-red-600', 'Failed'];
                    } else {
                        [$actCls, $actLabel] = $actionLabels[$row->row_action] ?? ['bg-gray-100 text-gray-500', ucfirst($row->row_action ?? '—')];
                    }
                    @endphp
                    <tr class="hover:bg-gray-50/40 transition-colors">
                        <td class="px-4 py-3 text-xs text-gray-400">{{ $row->row_number }}</td>
                        <td class="px-4 py-3">
                            @if($isLguIds ?? false)
                                <p class="font-medium text-[#1E1B4B]">{{ $norm['municipality_or_city'] ?? '—' }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">{{ $norm['province'] ?? '' }}</p>
                            @else
                                <p class="font-medium text-[#1E1B4B]">{{ $norm['deal_name'] ?? '—' }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">{{ $norm['organization_name'] ?? '' }}</p>
                            @endif
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
                                @php
                                    $rawErr = $row->error_message;
                                    $friendlyErr = $rawErr;
                                    if (str_contains($rawErr, 'SQLSTATE') || str_contains($rawErr, 'Integrity constraint') || str_contains($rawErr, 'Undefined column')) {
                                        if (str_contains($rawErr, 'Unique violation') || str_contains($rawErr, '23505')) $friendlyErr = 'Duplicate record — this entry already exists.';
                                        elseif (str_contains($rawErr, 'not-null') || str_contains($rawErr, '23502')) $friendlyErr = 'A required field is missing a value.';
                                        elseif (str_contains($rawErr, 'foreign key') || str_contains($rawErr, '23503')) $friendlyErr = 'Linked record not found.';
                                        elseif (str_contains($rawErr, 'Undefined column') || str_contains($rawErr, '42703')) $friendlyErr = 'Internal configuration error — please contact support.';
                                        else $friendlyErr = 'Database error — please contact support.';
                                    }
                                @endphp
                                <span class="text-red-500">{{ Str::limit($friendlyErr, 80) }}</span>
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
