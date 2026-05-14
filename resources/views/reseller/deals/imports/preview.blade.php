@extends('layouts.reseller')
@section('title', 'Review Deal Import')
@section('nav') @include('reseller._nav') @endsection

@section('content')
@php
    $ready    = $grouped['ready']              ?? collect();
    $dupes    = $grouped['duplicate']          ?? collect();
    $blocked  = $grouped['blocked']            ?? collect();
    $failed   = $grouped['failed']             ?? collect();
    $total    = $rows->count();
    $CSRF     = csrf_token();
    $executeUrl  = route('reseller.deals.imports.execute',     [$tenant->id, $batch->id]);
    $bulkUrl     = route('reseller.deals.imports.bulk-approve',[$tenant->id, $batch->id]);
    $approveBase = url("reseller/{$tenant->id}/deals/imports/{$batch->id}/rows");
    $correctBase = url("reseller/{$tenant->id}/deals/imports/{$batch->id}/rows");
    $backUrl     = route('reseller.deals.imports', $tenant->id);
    $isLguIds  = $isLguIds ?? false;
    $provinces = $provinces ?? [];
@endphp

<div class="space-y-5 max-w-5xl mx-auto">

    {{-- Back --}}
    <a href="{{ $backUrl }}" class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-teal-600 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Import History
    </a>

    {{-- Header + Execute button --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-[#1E1B4B]">Review Deal Import</h1>
                <p class="text-sm text-gray-400 mt-1">
                    <span class="font-medium text-gray-600">{{ $batch->file_name }}</span>
                    &nbsp;·&nbsp; {{ $batch->created_at->format('M d, Y \a\t h:i A') }}
                </p>
            </div>
            <div class="flex gap-2 shrink-0">
                <form action="{{ $executeUrl }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold text-white transition-all hover:shadow-md"
                            style="background:#0D9488">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Confirm & Import
                    </button>
                </form>
                <a href="{{ $backUrl }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200 transition-colors">
                    Cancel
                </a>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="flex items-center gap-3 p-4 rounded-2xl bg-teal-50 border border-teal-200 text-teal-700 text-sm font-medium">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        {{ session('success') }}
    </div>
    @endif

    @if($errors->has('import'))
    <div class="flex items-start gap-3 p-4 rounded-2xl bg-red-50 border border-red-200">
        <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01"/></svg>
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
        @if($blocked->count() > 0)
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">
            <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
            Blocked: {{ $blocked->count() }}
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
        <div class="px-5 py-3.5 border-b border-gray-100">
            <h2 class="text-sm font-bold text-[#1E1B4B]">Deal Rows</h2>
        </div>

        @if($rows->isEmpty())
        <div class="px-5 py-10 text-center text-sm text-gray-400">No rows found in this import.</div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100">
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">#</th>
                        @if($isLguIds)
                            <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Province</th>
                            <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Municipality / City</th>
                        @else
                            <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Deal / Org</th>
                        @endif
                        <th class="text-right px-4 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Amount</th>
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Stage</th>
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Status</th>
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Note</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($rows as $row)
                    @php
                        $norm = is_array($row->normalized_data)
                            ? $row->normalized_data
                            : (json_decode($row->normalized_data ?? '{}', true) ?? []);
                        $issues = is_array($row->issue_codes)
                            ? $row->issue_codes
                            : (json_decode($row->issue_codes ?? '[]', true) ?? []);
                        $issueMsg = collect($issues)->pluck('message')->filter()->first();
                        $statusMap = [
                            'ready'              => ['bg-teal-100 text-teal-700',   'Ready'],
                            'duplicate'          => ['bg-amber-100 text-amber-700', 'Duplicate'],
                            'possible_duplicate' => ['bg-orange-100 text-orange-600','Possible Dup'],
                            'blocked'            => ['bg-gray-100 text-gray-500',   'Blocked'],
                            'failed'             => ['bg-red-100 text-red-700',     'Failed'],
                            'unknown_referrer'   => ['bg-amber-100 text-amber-700', 'Unknown Referrer'],
                            'needs_review'       => ['bg-blue-100 text-blue-700',   'Needs Review'],
                            'unknown_lgu'        => ['bg-orange-100 text-orange-600','Unknown LGU'],
                            'pricing_issue'      => ['bg-red-100 text-red-700',     'Pricing Issue'],
                        ];
                        [$badgeCls, $badgeLabel] = $statusMap[$row->validation_status] ?? ['bg-gray-100 text-gray-500', ucfirst($row->validation_status)];
                    @endphp
                    @if($isLguIds)
                    @php
                        $rowProvince = $norm['province'] ?? '';
                        $rowMunicipality = $norm['municipality_or_city'] ?? '';
                    @endphp
                    {{-- LGU IDS: Province + Municipality row with dynamic org lookup --}}
                    <tr class="hover:bg-gray-50/40 transition-colors"
                        x-data="{
                            province: '{{ addslashes($rowProvince) }}',
                            municipality: '{{ addslashes($rowMunicipality) }}',
                            municipalities: [],
                            loading: false,
                            saving: false,
                            saved: false,
                            saveError: false,
                            validationStatus: '{{ $row->validation_status }}',
                            rowAction: '{{ $row->row_action ?? '' }}',
                            async loadMunicipalities(prov) {
                                if (!prov) { this.municipalities = []; return; }
                                this.loading = true;
                                try {
                                    const r = await fetch('/api/lgu-ids/municipalities?province=' + encodeURIComponent(prov));
                                    this.municipalities = await r.json();
                                } catch(e) { this.municipalities = []; }
                                this.loading = false;
                            },
                            async saveCorrection() {
                                this.saving = true; this.saved = false; this.saveError = false;
                                try {
                                    const res = await fetch('{{ $correctBase }}/{{ $row->id }}/correct', {
                                        method: 'PATCH',
                                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ $CSRF }}', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                        body: JSON.stringify({ province: this.province, municipality: this.municipality }),
                                    });
                                    const data = await res.json();
                                    if (data.saved) {
                                        this.saved = true;
                                        this.validationStatus = data.validation_status;
                                        this.rowAction = data.row_action;
                                        setTimeout(() => { this.saved = false; }, 3000);
                                    } else {
                                        this.saveError = true;
                                    }
                                } catch(e) { this.saveError = true; }
                                this.saving = false;
                            },
                            init() { this.loadMunicipalities(this.province); },
                        }"
                        x-init="init()">
                        <td class="px-4 py-3 text-xs text-gray-400">{{ $row->row_number }}</td>
                        <td class="px-4 py-3 min-w-[160px]">
                            <select x-model="province"
                                    @change="municipality = ''; loadMunicipalities(province)"
                                    class="w-full text-xs border border-gray-200 rounded-lg px-2 py-1.5 bg-white focus:outline-none focus:ring-1 focus:ring-teal-400 text-gray-700"
                                    :class="saved ? 'border-emerald-400' : saveError ? 'border-red-400' : ''">
                                <option value="">— Select Province —</option>
                                @foreach($provinces as $prov)
                                    <option value="{{ $prov }}" @if($rowProvince === $prov) selected @endif>{{ $prov }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="px-4 py-3 min-w-[200px]">
                            <select x-model="municipality"
                                    @change="saveCorrection()"
                                    :disabled="loading"
                                    class="w-full text-xs border border-gray-200 rounded-lg px-2 py-1.5 bg-white focus:outline-none focus:ring-1 focus:ring-teal-400 text-gray-700 disabled:opacity-50"
                                    :class="saved ? 'border-emerald-400' : saveError ? 'border-red-400' : ''">
                                <option value="" x-text="loading ? 'Loading…' : '— Select Municipality / City —'"></option>
                                <template x-for="mun in municipalities" :key="mun">
                                    <option :value="mun" :selected="mun === municipality" x-text="mun"></option>
                                </template>
                                {{-- Always keep the CSV value as an option even if not in the DB list --}}
                                <template x-if="municipality && !municipalities.includes(municipality)">
                                    <option :value="municipality" selected x-text="municipality"></option>
                                </template>
                            </select>
                            {{-- Save feedback --}}
                            <div class="mt-1 flex items-center gap-1 text-[10px] font-semibold min-h-[14px]">
                                <span x-show="saving" class="text-gray-400">Saving…</span>
                                <span x-show="saved" class="text-emerald-600">✓ Saved — row marked for import</span>
                                <span x-show="saveError" class="text-red-500">✗ Could not save — try again</span>
                                <span x-show="!saving && !saved && !saveError && validationStatus === 'valid'" class="text-emerald-600">✓ Ready to import</span>
                            </div>
                        </td>
                    @else
                    {{-- Generic: Deal/Org row --}}
                    <tr class="hover:bg-gray-50/40 transition-colors">
                        <td class="px-4 py-3 text-xs text-gray-400">{{ $row->row_number }}</td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-[#1E1B4B]">{{ $norm['deal_name'] ?? '—' }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $norm['organization_name'] ?? '' }}</p>
                        </td>
                    @endif
                        <td class="px-4 py-3 text-right tabular-nums text-gray-600">
                            @if(!empty($norm['deal_amount']))
                                ₱{{ number_format((float)$norm['deal_amount'], 0) }}
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">{{ ucfirst(str_replace('_', ' ', $norm['deal_stage'] ?? $norm['stage'] ?? 'introduction')) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $badgeCls }}">
                                {{ $badgeLabel }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-400 max-w-[200px]">
                            {{ $issueMsg ? Str::limit($issueMsg, 60) : '—' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- Footer execute --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <p class="text-sm font-semibold text-[#1E1B4B]">{{ $ready->count() }} deal{{ $ready->count() !== 1 ? 's' : '' }} ready to import</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ ($dupes->count() + $blocked->count() + $failed->count()) }} rows will be skipped.</p>
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
