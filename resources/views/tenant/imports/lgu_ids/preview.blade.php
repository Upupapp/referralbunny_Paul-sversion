@extends('layouts.app')
@section('title', 'Import Preview — LGU IDS')

@section('nav')
    @include('tenant._nav')
@endsection

@section('content')
<div x-data="lguImportPreview()" x-init="init()" class="space-y-5">

    {{-- ── Upload success banner ───────────────────────────── --}}
    @if(session('success'))
    <div style="display:flex;align-items:center;gap:12px;padding:14px 18px;background:#dcfce7;border:1px solid #86efac;border-radius:14px;color:#15803d;font-size:14px;font-weight:600">
        <svg style="width:18px;height:18px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
        {{ session('success') }}
    </div>
    @endif
    @if($errors->has('import'))
    <div style="display:flex;align-items:center;gap:12px;padding:14px 18px;background:#fef2f2;border:1px solid #fecaca;border-radius:14px;color:#dc2626;font-size:13px;font-weight:600">
        <svg style="width:18px;height:18px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ $errors->first('import') }}
    </div>
    @endif

    {{-- ── Sticky Summary Bar ───────────────────────────────── --}}
    <div class="sticky top-0 z-20 card py-3 shadow-md border-b border-gray-100">
        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
            {{-- Count pills --}}
            <div class="flex flex-wrap gap-2 flex-1">
                <button @click="activeTab = 'ready'" :class="activeTab === 'ready' ? 'ring-2 ring-emerald-400' : ''"
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 transition-all">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    Ready <span class="font-bold">{{ $grouped['ready']->count() }}</span>
                </button>
                <button @click="activeTab = 'duplicate'" :class="activeTab === 'duplicate' ? 'ring-2 ring-amber-400' : ''"
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-orange-100 text-orange-700 transition-all">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                    Duplicates <span class="font-bold">{{ $grouped['duplicate']->count() }}</span>
                </button>
                <button @click="activeTab = 'unknown_referrer'" :class="activeTab === 'unknown_referrer' ? 'ring-2 ring-yellow-400' : ''"
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700 transition-all">
                    <span class="w-1.5 h-1.5 rounded-full bg-yellow-500"></span>
                    Unknown Referrers <span class="font-bold">{{ $grouped['unknown_referrer']->count() }}</span>
                </button>
                <button @click="activeTab = 'pricing_issue'" :class="activeTab === 'pricing_issue' ? 'ring-2 ring-red-400' : ''"
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700 transition-all">
                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                    Pricing Issues <span class="font-bold">{{ $grouped['pricing_issue']->count() }}</span>
                </button>
                @if($grouped['failed']->count() > 0)
                <button @click="activeTab = 'failed'" :class="activeTab === 'failed' ? 'ring-2 ring-red-600' : ''"
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700 transition-all">
                    <span class="w-1.5 h-1.5 rounded-full bg-red-600"></span>
                    Failed <span class="font-bold">{{ $grouped['failed']->count() }}</span>
                </button>
                @endif
                @if($grouped['blocked']->count() > 0)
                <button @click="activeTab = 'blocked'" :class="activeTab === 'blocked' ? 'ring-2 ring-gray-500' : ''"
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 transition-all">
                    <span class="w-1.5 h-1.5 rounded-full bg-gray-500"></span>
                    Blocked <span class="font-bold">{{ $grouped['blocked']->count() }}</span>
                </button>
                @endif
            </div>

            {{-- CTA Buttons --}}
            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('tenant.imports.lgu-ids', $tenant->id) }}"
                   class="btn-secondary text-sm">Cancel</a>
                <form action="{{ route('tenant.imports.lgu-ids.execute', [$tenant->id, $batch->id]) }}" method="POST" id="confirm-import-form">
                    @csrf
                    <button type="submit"
                            :disabled="unresolvedDuplicates > 0"
                            :class="unresolvedDuplicates > 0 ? 'opacity-50 cursor-not-allowed' : ''"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-white transition-colors"
                            style="background: #10B981;" onmouseover="if(!this.disabled) this.style.background='#059669'" onmouseout="this.style.background='#10B981'">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Confirm & Import
                    </button>
                </form>
            </div>
        </div>

        <p x-show="unresolvedDuplicates > 0" class="text-xs text-amber-600 mt-2">
            <svg class="w-3.5 h-3.5 inline -mt-0.5 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <span x-text="unresolvedDuplicates + ' duplicate' + (unresolvedDuplicates !== 1 ? 's' : '') + ' must be resolved before importing.'"></span>
        </p>
    </div>

    {{-- ── Filter Tabs ─────────────────────────────────────── --}}
    <div class="card py-3">
        <div class="filter-bar">
            @php
                $tabs = [
                    ['key' => 'all',              'label' => 'All',                'count' => collect($grouped)->flatten(1)->count()],
                    ['key' => 'ready',            'label' => 'Ready',              'count' => $grouped['ready']->count()],
                    ['key' => 'duplicate',        'label' => 'Duplicate',          'count' => $grouped['duplicate']->count()],
                    ['key' => 'unknown_referrer', 'label' => 'Unknown Referrer',   'count' => $grouped['unknown_referrer']->count()],
                    ['key' => 'pricing_issue',    'label' => 'Pricing Issue',      'count' => $grouped['pricing_issue']->count()],
                    ['key' => 'unknown_lgu',      'label' => 'Unknown LGU',        'count' => $grouped['unknown_lgu']->count()],
                    ['key' => 'failed',           'label' => 'Failed',             'count' => $grouped['failed']->count()],
                    ['key' => 'blocked',          'label' => 'Blocked',            'count' => $grouped['blocked']->count()],
                ];
            @endphp
            @foreach($tabs as $tab)
            @if($tab['count'] > 0 || $tab['key'] === 'all')
            <button @click="activeTab = '{{ $tab['key'] }}'"
                    :class="activeTab === '{{ $tab['key'] }}' ? 'active' : ''"
                    class="filter-pill">
                {{ $tab['label'] }}
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold"
                      :class="activeTab === '{{ $tab['key'] }}' ? 'bg-[#7B61FF] text-white' : 'bg-gray-100 text-gray-600'">
                    {{ $tab['count'] }}
                </span>
            </button>
            @endif
            @endforeach
        </div>
    </div>

    {{-- ── Bulk Actions (Duplicates) ─────────────────────────── --}}
    <div x-show="activeTab === 'duplicate' && {{ $grouped['duplicate']->count() }} > 0"
         class="card py-3">
        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
            <label class="flex items-center gap-2 cursor-pointer select-none">
                <input type="checkbox" @change="toggleSelectAll($event.target.checked)" class="rounded border-gray-300 text-[#7B61FF]">
                <span class="text-sm font-medium text-gray-700">Select All Duplicates</span>
            </label>
            <div class="flex items-center gap-2 sm:ml-auto">
                <select x-model="bulkAction" class="form-input py-1.5 text-sm w-auto">
                    <option value="">Apply to selected…</option>
                    <option value="skip">Skip</option>
                    <option value="merge">Merge Missing Fields</option>
                    <option value="overwrite">Overwrite All</option>
                </select>
                <button @click="applyBulk()"
                        :disabled="!bulkAction || selectedRows.length === 0"
                        :class="!bulkAction || selectedRows.length === 0 ? 'opacity-50 cursor-not-allowed' : ''"
                        class="btn-primary text-sm py-1.5">
                    Apply
                    <span x-show="selectedRows.length > 0" x-text="'(' + selectedRows.length + ')'" class="text-xs opacity-75"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- ── Row Table ─────────────────────────────────────────── --}}
    <div class="card p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1100px]">
                <thead>
                    <tr class="table-head">
                        <th class="w-8">
                            <span class="sr-only">Select</span>
                        </th>
                        <th>#</th>
                        <th>Municipality / City</th>
                        <th>Province</th>
                        <th>Referrer Email</th>
                        <th>Deal Amount</th>
                        <th>Base Cost</th>
                        <th>Added Amount</th>
                        <th>Display %</th>
                        <th>Stage</th>
                        <th>Status</th>
                        <th>Issues</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $allRows = collect($grouped)->flatten(1);
                    @endphp
                    @forelse($allRows as $row)
                    @php
                        $statusKey = $row->validation_status ?? 'ready';
                        $computed  = is_array($row->computed_data) ? $row->computed_data : (json_decode($row->computed_data, true) ?? []);
                        $issues    = is_array($row->issue_codes)   ? $row->issue_codes   : (json_decode($row->issue_codes, true)   ?? []);
                        $rowBorder = match($statusKey) {
                            'ready'           => 'border-l-2 border-l-emerald-400',
                            'duplicate'       => 'border-l-2 border-l-amber-400',
                            'failed','blocked'=> 'border-l-2 border-l-red-400',
                            'pricing_issue'   => 'border-l-2 border-l-yellow-400',
                            'unknown_referrer'=> 'border-l-2 border-l-blue-400',
                            default           => '',
                        };
                    @endphp
                    <tr class="table-row {{ $rowBorder }} transition-all"
                        x-show="activeTab === 'all' || activeTab === '{{ $statusKey }}'"
                        data-row-id="{{ $row->id }}"
                        data-status="{{ $statusKey }}">
                        {{-- Checkbox (duplicates only) --}}
                        <td class="w-8 px-3">
                            @if($statusKey === 'duplicate')
                            <input type="checkbox"
                                   class="rounded border-gray-300 text-[#7B61FF] row-checkbox"
                                   :checked="selectedRows.includes({{ $row->id }})"
                                   @change="toggleRow({{ $row->id }}, $event.target.checked)">
                            @endif
                        </td>

                        <td class="text-xs text-gray-400 tabular-nums">{{ $loop->index + 1 }}</td>

                        <td>
                            <span class="font-medium text-[#1E1B4B] text-sm">
                                {{ $row->normalized_data['municipality_or_city'] ?? '—' }}
                            </span>
                        </td>

                        <td class="text-sm text-gray-600">
                            {{ $row->normalized_data['province'] ?? '—' }}
                        </td>

                        <td>
                            <span class="text-sm {{ $statusKey === 'unknown_referrer' ? 'text-blue-600 font-medium' : 'text-gray-700' }}">
                                {{ $row->normalized_data['referrer_email'] ?? '—' }}
                            </span>
                        </td>

                        {{-- Deal Amount --}}
                        <td class="tabular-nums text-sm font-medium text-[#1E1B4B]">
                            @if(isset($computed['normalized_deal_amount']))
                                ₱{{ number_format($computed['normalized_deal_amount'], 2) }}
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>

                        {{-- Base Cost --}}
                        <td class="tabular-nums text-sm text-gray-600">
                            @if(isset($computed['normalized_base_cost']))
                                ₱{{ number_format($computed['normalized_base_cost'], 2) }}
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>

                        {{-- Added Amount --}}
                        <td class="tabular-nums text-sm text-gray-600">
                            @if(isset($computed['normalized_added_amount']))
                                ₱{{ number_format($computed['normalized_added_amount'], 2) }}
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>

                        {{-- Display % --}}
                        <td class="tabular-nums text-sm">
                            @if(isset($computed['display_pct']))
                                <span class="font-medium text-[#1E1B4B]">{{ $computed['display_pct'] }}%</span>
                                @if(isset($computed['pricing_tier_matched']) && $computed['pricing_tier_matched'])
                                    <span class="ml-1 text-emerald-600" title="Standard Tier">
                                        <svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    </span>
                                @elseif(isset($computed['pricing_status']))
                                    <span class="ml-1 text-amber-500" :title="'{{ addslashes($computed['pricing_status'] ?? '') }}'">
                                        <svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                    </span>
                                @endif
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>

                        <td>
                            <span class="text-xs text-gray-600">{{ $row->normalized_data['stage'] ?? '—' }}</span>
                        </td>

                        {{-- Row Status --}}
                        <td>
                            @php
                                $badgeMap = [
                                    'ready'            => 'badge-green',
                                    'duplicate'        => 'badge-orange',
                                    'unknown_referrer' => 'badge-blue',
                                    'pricing_issue'    => ['bg-yellow-100 text-yellow-800'],
                                    'unknown_lgu'      => 'badge-gray',
                                    'failed'           => 'badge-red',
                                    'blocked'          => 'badge-gray',
                                ];
                                $bc = is_array($badgeMap[$statusKey] ?? null) ? $badgeMap[$statusKey][0] : ($badgeMap[$statusKey] ?? 'badge-gray');
                            @endphp
                            <span class="badge {{ $bc }}">{{ ucfirst(str_replace('_', ' ', $statusKey)) }}</span>
                        </td>

                        {{-- Issues --}}
                        <td>
                            <div class="flex flex-wrap gap-1">
                                @forelse($issues as $issue)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-600 whitespace-nowrap">
                                    {{ str_replace('_', ' ', $issue) }}
                                </span>
                                @empty
                                <span class="text-gray-300 text-xs">—</span>
                                @endforelse
                            </div>
                        </td>

                        {{-- Action --}}
                        <td>
                            @if($statusKey === 'duplicate')
                            <div x-data="{ open: false, action: rowActions[{{ $row->id }}] ?? 'skip', busy: false }"
                                 class="relative"
                                 @click.outside="open = false">
                                <button @click="open = !open"
                                        class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1.5 rounded-lg border border-gray-200 hover:border-[#7B61FF] hover:text-[#7B61FF] transition-colors"
                                        :class="action === 'skip' ? 'text-gray-500' : action === 'merge' ? 'text-blue-600 border-blue-200' : 'text-amber-600 border-amber-200'">
                                    <span x-text="action === 'skip' ? 'Skip' : action === 'merge' ? 'Merge' : 'Overwrite'"></span>
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div x-show="open" x-cloak
                                     class="absolute right-0 top-full mt-1 w-44 bg-white rounded-xl border border-gray-100 shadow-lg z-10 overflow-hidden py-1">
                                    <button @click="setRowAction({{ $row->id }}, 'skip'); open = false"
                                            class="w-full text-left px-3 py-2 text-xs hover:bg-gray-50 text-gray-600 transition-colors">
                                        Skip this row
                                    </button>
                                    <button @click="setRowAction({{ $row->id }}, 'merge'); open = false"
                                            class="w-full text-left px-3 py-2 text-xs hover:bg-blue-50 text-blue-700 transition-colors">
                                        Merge Missing Fields
                                    </button>
                                    <button @click="setRowAction({{ $row->id }}, 'overwrite'); open = false"
                                            class="w-full text-left px-3 py-2 text-xs hover:bg-amber-50 text-amber-700 transition-colors">
                                        Overwrite All
                                    </button>
                                </div>
                            </div>
                            @elseif(in_array($statusKey, ['failed', 'blocked']))
                            <span class="text-xs text-gray-400 italic">Will be skipped</span>
                            @else
                            <span class="text-xs text-emerald-600 font-medium">Will import</span>
                            @endif
                        </td>
                    </tr>

                    {{-- Existing Deal Details (collapsible for duplicates) --}}
                    @if($statusKey === 'duplicate' && in_array('existing_deal', $issues))
                    @php $existing = $computed['existing_deal'] ?? null; @endphp
                    @if($existing)
                    <tr x-show="activeTab === 'all' || activeTab === 'duplicate'"
                        class="bg-amber-50/50">
                        <td colspan="13" class="px-8 py-3">
                            <div class="text-xs text-amber-800 font-semibold mb-2 flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Existing deal in system
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <div>
                                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Referrer</p>
                                    <p class="text-xs text-gray-700 mt-0.5">{{ $existing['referrer_email'] ?? '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Stage</p>
                                    <p class="text-xs {{ ($existing['stage'] ?? '') !== ($row->raw_data['stage'] ?? '') ? 'text-amber-700 font-semibold' : 'text-gray-700' }} mt-0.5">
                                        {{ $existing['stage'] ?? '—' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Deal Value</p>
                                    <p class="text-xs {{ isset($existing['deal_amount'], $computed['deal_amount']) && $existing['deal_amount'] != $computed['deal_amount'] ? 'text-amber-700 font-semibold' : 'text-gray-700' }} mt-0.5">
                                        ₱{{ number_format($existing['deal_amount'] ?? 0, 2) }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Status</p>
                                    <p class="text-xs text-gray-700 mt-0.5">{{ $existing['status'] ?? '—' }}</p>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endif
                    @endif

                    @empty
                    <tr>
                        <td colspan="13" class="py-12 text-center text-gray-400 text-sm">No rows found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Confirm Import Section ───────────────────────────── --}}
    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h3 class="text-[#1E1B4B] font-semibold text-base mb-1">Ready to Import?</h3>
                <p class="text-sm text-gray-500">
                    <span class="text-emerald-600 font-semibold">{{ $grouped['ready']->count() }} rows ready</span>
                    @if($grouped['duplicate']->count() > 0)
                    · <span class="text-amber-600 font-semibold">{{ $grouped['duplicate']->count() }} duplicates to resolve</span>
                    @endif
                    @if($grouped['failed']->count() > 0 || $grouped['blocked']->count() > 0)
                    · <span class="text-gray-500">{{ $grouped['failed']->count() + $grouped['blocked']->count() }} will be skipped</span>
                    @endif
                </p>
                <p x-show="unresolvedDuplicates > 0" class="text-xs text-amber-600 mt-1.5">
                    Resolve all duplicate actions before importing.
                </p>
            </div>
            <div class="flex flex-col sm:flex-row gap-2 shrink-0">
                {{-- Import Approved Rows Only --}}
                <form action="{{ route('tenant.imports.lgu-ids.execute', [$tenant->id, $batch->id]) }}" method="POST">
                    @csrf
                    <input type="hidden" name="approved_only" value="1">
                    <button type="submit" class="btn-secondary text-sm w-full sm:w-auto">
                        Import Approved Rows Only
                    </button>
                </form>
                {{-- Import Now (all resolved) --}}
                <button form="confirm-import-form"
                        type="submit"
                        :disabled="unresolvedDuplicates > 0"
                        :class="unresolvedDuplicates > 0 ? 'opacity-50 cursor-not-allowed' : ''"
                        class="inline-flex items-center justify-center gap-2 px-5 py-2 rounded-xl text-sm font-medium text-white transition-colors"
                        style="background: #10B981;" onmouseover="if(!this.disabled) this.style.background='#059669'" onmouseout="this.style.background='#10B981'">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Import Now
                </button>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script>
function lguImportPreview() {
    return {
        activeTab: 'all',
        selectedRows: [],
        bulkAction: '',
        rowActions: {},
        unresolvedDuplicates: {{ $grouped['duplicate']->count() }},

        init() {
            // Default all duplicates to 'skip'
            @foreach($grouped['duplicate'] as $row)
            this.rowActions[{{ $row->id }}] = 'skip';
            @endforeach
        },

        toggleSelectAll(checked) {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            this.selectedRows = [];
            checkboxes.forEach(cb => {
                cb.checked = checked;
                if (checked) {
                    const id = parseInt(cb.closest('tr').dataset.rowId);
                    if (!isNaN(id)) this.selectedRows.push(id);
                }
            });
        },

        toggleRow(id, checked) {
            if (checked) {
                if (!this.selectedRows.includes(id)) this.selectedRows.push(id);
            } else {
                this.selectedRows = this.selectedRows.filter(r => r !== id);
            }
        },

        async setRowAction(rowId, action) {
            this.rowActions[rowId] = action;
            const csrf = document.querySelector('meta[name=csrf-token]').content;
            try {
                const res = await fetch('{{ route('tenant.imports.lgu-ids.approve-row', [$tenant->id, $batch->id, '__ROW__']) }}'.replace('__ROW__', rowId), {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ action }),
                });
                const data = await res.json();
                if (data.success) {
                    this.$dispatch('show-toast', { type: 'success', message: 'Row action saved.' });
                    this.updateUnresolved();
                } else {
                    this.$dispatch('show-toast', { type: 'error', message: data.message || 'Failed to save action.' });
                }
            } catch (e) {
                this.$dispatch('show-toast', { type: 'error', message: 'Network error. Please try again.' });
            }
        },

        async applyBulk() {
            if (!this.bulkAction || this.selectedRows.length === 0) return;
            const csrf = document.querySelector('meta[name=csrf-token]').content;
            try {
                const res = await fetch('{{ route('tenant.imports.lgu-ids.bulk-approve', [$tenant->id, $batch->id]) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        row_ids: this.selectedRows,
                        action: this.bulkAction,
                    }),
                });
                const data = await res.json();
                if (data.success) {
                    this.selectedRows.forEach(id => { this.rowActions[id] = this.bulkAction; });
                    this.$dispatch('show-toast', { type: 'success', message: data.message || 'Bulk action applied.' });
                    this.updateUnresolved();
                    this.bulkAction = '';
                } else {
                    this.$dispatch('show-toast', { type: 'error', message: data.message || 'Failed to apply bulk action.' });
                }
            } catch (e) {
                this.$dispatch('show-toast', { type: 'error', message: 'Network error. Please try again.' });
            }
        },

        updateUnresolved() {
            // Count duplicates that still have no explicit action set beyond skip
            // (all are set, so unresolved = 0 once any action is chosen)
            this.unresolvedDuplicates = 0;
        },
    };
}
</script>
@endpush
@endsection
