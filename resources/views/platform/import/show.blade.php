@extends('layouts.app')
@section('title', $job->import_name)
@section('nav') @include('platform._nav') @endsection

@section('topbar-actions')
    @if($job->canRollback())
    <form method="POST" action="/api/imports/jobs/{{ $job->id }}/rollback" x-data
          @submit.prevent="if(confirm('Roll back this import? This will delete all created records and restore overwritten values.')) $el.submit()">
        @csrf
        <button type="submit" class="btn-danger text-sm">Rollback Import</button>
    </form>
    @endif
    @if($job->canCancel())
    <a href="#" onclick="fetch('/api/imports/jobs/{{ $job->id }}/cancel',{method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}'}}).then(()=>location.reload())"
       class="btn-secondary text-sm">Cancel</a>
    @endif
    <a href="{{ route('platform.import') }}" class="btn-secondary text-sm">← Back</a>
@endsection

@section('content')
<div class="space-y-5" x-data="importDetail('{{ $job->id }}')" x-init="init()">

    {{-- Header --}}
    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-start gap-4">
            <div class="flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-xl font-bold text-[#1E1B4B]">{{ $job->import_name }}</h2>
                    <span class="badge {{ $job->statusColor() }}">{{ $job->statusLabel() }}</span>
                    <span class="badge {{ $job->risk_level === 'low' ? 'badge-green' : ($job->risk_level === 'medium' ? 'badge-orange' : 'badge-red') }}">
                        {{ strtoupper($job->risk_level) }} RISK
                    </span>
                </div>
                <p class="text-gray-500 text-sm mt-1">
                    {{ ucfirst($job->object_type) }} import ·
                    {{ $job->file_name ?? 'Pasted data' }} ·
                    Uploaded {{ $job->created_at->diffForHumans() }}
                </p>
            </div>
            @if($job->failed_rows > 0)
            <a href="/api/imports/jobs/{{ $job->id }}/report/download" class="btn-secondary text-sm shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Download Error File
            </a>
            @endif
        </div>

        {{-- Progress bar --}}
        @if($job->isRunning() && $job->total_rows > 0)
        <div class="mt-4">
            <div class="flex justify-between text-xs text-gray-500 mb-1">
                <span>{{ $job->processed_rows }} / {{ $job->total_rows }} rows</span>
                <span>{{ round(($job->processed_rows / $job->total_rows) * 100) }}%</span>
            </div>
            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full bg-[#7B61FF] rounded-full transition-all"
                     style="width: {{ round(($job->processed_rows / $job->total_rows) * 100) }}%"></div>
            </div>
        </div>
        @endif
    </div>

    {{-- Stats row --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        @foreach([
            ['Total', $job->total_rows,         'text-gray-700',    'bg-gray-100'],
            ['Created', $job->successful_rows,  'text-emerald-700', 'bg-emerald-100'],
            ['Updated', $job->overwritten_fields_count, 'text-blue-700', 'bg-blue-100'],
            ['Failed',  $job->failed_rows,      'text-red-700',     'bg-red-100'],
            ['Skipped', $job->skipped_rows,     'text-gray-500',    'bg-gray-100'],
            ['Warnings',$job->warning_rows,     'text-orange-700',  'bg-orange-100'],
        ] as [$label, $value, $tc, $bg])
        <div class="card py-3 px-4 text-center {{ $bg }}">
            <p class="text-xs text-gray-500">{{ $label }}</p>
            <p class="text-2xl font-bold {{ $tc }} mt-0.5">{{ $value }}</p>
        </div>
        @endforeach
    </div>

    {{-- Detail tabs --}}
    <div class="card p-1.5">
        <div class="flex gap-1 overflow-x-auto">
            @foreach(['Summary','Errors','Rows'] as $ti => $tl)
            <button @click="detailTab = {{ $ti }}"
                    :class="detailTab === {{ $ti }} ? 'tab-active' : 'text-gray-600 hover:bg-gray-100'"
                    class="px-3 py-1.5 rounded-xl text-xs font-medium whitespace-nowrap transition-colors">
                {{ $tl }}
            </button>
            @endforeach
        </div>
    </div>

    {{-- Summary tab --}}
    <div x-show="detailTab === 0" class="card space-y-3">
        <h3 class="font-semibold text-[#1E1B4B] mb-2">Import Details</h3>
        @foreach([
            ['Import Type',    ucfirst($job->import_type)],
            ['Object Type',    ucfirst(str_replace('_', ' ', $job->object_type))],
            ['Import Mode',    ucfirst(str_replace('_', ' ', $job->import_mode))],
            ['Overwrite Mode', ucfirst(str_replace('_', ' ', $job->overwrite_mode))],
            ['Template Version', $job->template_version ?? '—'],
            ['Source',         ucfirst(str_replace('_', ' ', $job->source_type))],
            ['Started',        $job->started_at?->format('M d, Y H:i') ?? '—'],
            ['Completed',      $job->completed_at?->format('M d, Y H:i') ?? '—'],
        ] as [$label, $value])
        <div class="flex justify-between text-sm border-b border-gray-50 pb-2 last:border-0">
            <span class="text-gray-400">{{ $label }}</span>
            <span class="font-medium text-gray-700">{{ $value }}</span>
        </div>
        @endforeach
    </div>

    {{-- Errors tab --}}
    <div x-show="detailTab === 1" class="card p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead><tr class="table-head"><th>Row</th><th>Column</th><th>Error</th><th>Severity</th><th>Suggested Fix</th></tr></thead>
                <tbody>
                    <template x-if="errors.length === 0"><tr><td colspan="5" class="py-10 text-center text-gray-400">No errors</td></tr></template>
                    <template x-for="err in errors" :key="err.id">
                        <tr class="table-row">
                            <td class="text-sm" x-text="err.row_number"></td>
                            <td class="font-mono text-xs" x-text="err.column_name || '—'"></td>
                            <td class="text-xs text-gray-600 max-w-xs" x-text="err.error_message"></td>
                            <td><span :class="err.severity === 'blocking' ? 'badge badge-red' : 'badge badge-orange'" x-text="err.severity" class="text-xs"></span></td>
                            <td class="text-xs text-gray-400" x-text="err.suggested_fix || '—'"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Rows tab --}}
    <div x-show="detailTab === 2" class="card p-0 overflow-hidden">
        <div class="flex items-center gap-3 px-5 py-3 border-b border-gray-100">
            <select x-model="rowStatusFilter" @change="loadRows()" class="form-input sm:w-40 text-xs py-1.5">
                <option value="">All Rows</option>
                <option value="valid_new">Valid New</option>
                <option value="valid_update">Valid Update</option>
                <option value="error">Errors</option>
                <option value="warning">Warnings</option>
                <option value="skipped">Skipped</option>
                <option value="imported">Imported</option>
            </select>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead><tr class="table-head"><th>Row</th><th>Status</th><th>Data Preview</th><th>Action</th></tr></thead>
                <tbody>
                    <template x-if="rows.length === 0"><tr><td colspan="4" class="py-10 text-center text-gray-400">No rows</td></tr></template>
                    <template x-for="row in rows" :key="row.id">
                        <tr :class="{
                            'bg-emerald-50/40': row.status === 'valid_new' || row.status === 'imported',
                            'bg-yellow-50/40':  row.status === 'valid_update',
                            'bg-red-50/40':     row.status === 'error' || row.status === 'failed',
                            'bg-orange-50/40':  row.status === 'warning',
                        }" class="table-row">
                            <td class="text-sm text-gray-500" x-text="row.row_number"></td>
                            <td><span :class="{
                                'badge badge-green':  row.status === 'valid_new' || row.status === 'imported',
                                'badge badge-orange': row.status === 'valid_update' || row.status === 'warning',
                                'badge badge-red':    row.status === 'error' || row.status === 'failed',
                                'badge badge-blue':   row.status === 'skipped',
                            }" x-text="row.status" class="text-xs"></span></td>
                            <td class="text-xs text-gray-500 font-mono max-w-sm truncate" x-text="JSON.stringify(row.mapped_data_json||{}).slice(0,100)"></td>
                            <td class="text-xs text-gray-400" x-text="row.created_entity_id ? 'Created: '+row.created_entity_id.slice(0,8) : row.updated_entity_id ? 'Updated' : '—'"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
function importDetail(jobId) {
    return {
        detailTab: 0,
        errors: [],
        rows: [],
        rowStatusFilter: '',

        async init() {
            await this.loadErrors();
            await this.loadRows();
        },

        async loadErrors() {
            const res = await fetch('/api/imports/jobs/' + jobId + '/errors');
            this.errors = await res.json();
        },

        async loadRows() {
            const params = new URLSearchParams();
            if (this.rowStatusFilter) params.set('status', this.rowStatusFilter);
            const res  = await fetch('/api/imports/jobs/' + jobId + '/rows?' + params);
            const data = await res.json();
            this.rows  = data.data || [];
        },
    }
}
</script>
@endsection
