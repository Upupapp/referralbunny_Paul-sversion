@extends('layouts.reseller')
@section('title', 'Review Import')
@section('nav') @include('reseller._nav') @endsection

@section('content')
@php
    $ready    = $grouped['ready']              ?? collect();
    $dupes    = $grouped['duplicate']          ?? collect();
    $possible = $grouped['possible_duplicate'] ?? collect();
    $failed   = $grouped['failed']             ?? collect();
    $total    = $rows->count();
    $CSRF     = csrf_token();
    $executeUrl  = route('reseller.contacts.imports.execute', [$tenant->id, $batch->id]);
    $approveBase = url("reseller/{$tenant->id}/contacts/imports/{$batch->id}/rows");
    $bulkUrl     = route('reseller.contacts.imports.bulk-approve', [$tenant->id, $batch->id]);
    $backUrl     = route('reseller.contacts.imports', $tenant->id);
@endphp

<div x-data="{
        busy: false,
        rowLoading: {},
        async approveRow(rowId, action) {
            this.rowLoading[rowId] = true;
            try {
                await fetch('{{ $approveBase }}/' + rowId, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ $CSRF }}', 'Accept': 'application/json' },
                    body: JSON.stringify({ action }),
                });
            } catch(e) {}
            this.rowLoading[rowId] = false;
        },
        async bulkApprove(action) {
            this.busy = true;
            try {
                const ids = @json($ready->pluck('id'));
                await fetch('{{ $bulkUrl }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ $CSRF }}', 'Accept': 'application/json' },
                    body: JSON.stringify({ row_ids: ids, action }),
                });
            } catch(e) {}
            this.busy = false;
        },
    }" class="space-y-5 max-w-4xl mx-auto">

    {{-- Back --}}
    <a href="{{ $backUrl }}" class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-teal-600 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Import History
    </a>

    {{-- Header --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-[#1E1B4B]">Review Import</h1>
                <p class="text-sm text-gray-400 mt-1">
                    <span class="font-medium text-gray-600">{{ $batch->file_name }}</span>
                    &nbsp;·&nbsp; {{ $batch->created_at->format('M d, Y \a\t h:i A') }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2 shrink-0">
                <form action="{{ $executeUrl }}" method="POST">
                    @csrf
                    <button type="submit" :disabled="busy"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold text-white transition-all disabled:opacity-50"
                            style="background:#0D9488">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        Run Import
                    </button>
                </form>
                <a href="{{ $backUrl }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200 transition-all">
                    Cancel
                </a>
            </div>
        </div>
    </div>

    {{-- Execute error --}}
    @if($errors->has('import'))
    <div class="flex items-start gap-3 p-4 rounded-2xl bg-red-50 border border-red-200">
        <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <p class="text-sm text-red-700">{{ $errors->first('import') }}</p>
    </div>
    @endif

    {{-- Summary pills --}}
    <div class="flex flex-wrap gap-2">
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-teal-100 text-teal-700">
            <span class="w-1.5 h-1.5 rounded-full bg-teal-500"></span>
            Ready: {{ $ready->count() }}
        </span>
        @if($dupes->count() > 0)
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">
            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
            Duplicate: {{ $dupes->count() }}
        </span>
        @endif
        @if($possible->count() > 0)
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-orange-100 text-orange-600">
            <span class="w-1.5 h-1.5 rounded-full bg-orange-400"></span>
            Possible Duplicate: {{ $possible->count() }}
        </span>
        @endif
        @if($failed->count() > 0)
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">
            <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
            Failed: {{ $failed->count() }}
        </span>
        @endif
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">
            Total: {{ $total }}
        </span>
    </div>

    {{-- Row table --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-[#1E1B4B]">Import Rows</h2>
            @if($ready->count() > 0)
            <button @click="bulkApprove('create')" :disabled="busy"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-teal-700 bg-teal-50 hover:bg-teal-100 transition-colors disabled:opacity-50">
                <svg x-show="busy" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                Approve All Ready ({{ $ready->count() }})
            </button>
            @endif
        </div>
        @if($rows->isEmpty())
        <div class="px-5 py-10 text-center text-sm text-gray-400">No rows found in this import.</div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100">
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">#</th>
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Name</th>
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Email</th>
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Phone</th>
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Status</th>
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($rows as $row)
                    @php
                        $norm = is_array($row->normalized_data) ? $row->normalized_data : (json_decode($row->normalized_data ?? '{}', true) ?? []);
                        $statusMap = [
                            'ready'             => ['bg-teal-100 text-teal-700',   'Ready'],
                            'duplicate'         => ['bg-amber-100 text-amber-700', 'Duplicate'],
                            'possible_duplicate'=> ['bg-orange-100 text-orange-600','Possible Dup'],
                            'failed'            => ['bg-red-100 text-red-700',     'Failed'],
                            'blocked'           => ['bg-gray-100 text-gray-500',   'Blocked'],
                        ];
                        [$badgeCls, $badgeLabel] = $statusMap[$row->validation_status] ?? ['bg-gray-100 text-gray-500', ucfirst($row->validation_status)];
                    @endphp
                    <tr class="hover:bg-gray-50/50 transition-colors" id="row-{{ $row->id }}">
                        <td class="px-4 py-3 text-xs text-gray-400">{{ $row->row_number }}</td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-[#1E1B4B]">{{ $norm['first_name'] ?? '' }} {{ $norm['last_name'] ?? '' }}</p>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500 max-w-[160px] truncate">{{ $norm['email'] ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500">{{ $norm['phone'] ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $badgeCls }}">
                                {{ $badgeLabel }}
                            </span>
                            @if($row->validation_errors)
                            @php $errs = is_array($row->validation_errors) ? $row->validation_errors : (json_decode($row->validation_errors, true) ?? []); @endphp
                            @if(!empty($errs))
                            <p class="text-[10px] text-red-500 mt-0.5">{{ implode(', ', array_slice($errs, 0, 2)) }}</p>
                            @endif
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($row->validation_status === 'ready')
                            <span class="text-xs text-teal-600 font-medium">Will be created</span>
                            @elseif($row->validation_status === 'duplicate')
                            <span class="text-xs text-amber-600">Skipped (duplicate)</span>
                            @elseif($row->validation_status === 'failed')
                            <span class="text-xs text-red-500">Will be skipped</span>
                            @else
                            <span class="text-xs text-gray-400">{{ ucfirst($row->row_action ?? 'pending') }}</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- Execute footer --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <p class="text-sm font-semibold text-[#1E1B4B]">Ready to import {{ $ready->count() }} contact{{ $ready->count() !== 1 ? 's' : '' }}</p>
            <p class="text-xs text-gray-400 mt-0.5">
                {{ $dupes->count() + $failed->count() }} row(s) will be skipped.
                Your contacts are private and only visible to you.
            </p>
        </div>
        <form action="{{ $executeUrl }}" method="POST">
            @csrf
            <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white transition-all hover:shadow-md"
                    style="background:#0D9488">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Confirm & Import
            </button>
        </form>
    </div>

</div>
@endsection
