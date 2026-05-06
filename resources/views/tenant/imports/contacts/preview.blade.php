@extends('layouts.app')
@section('title', 'Import Preview — Contact Import')

@section('nav')
    @include('tenant._nav')
@endsection

@section('content')
<div x-data="contactImportPreview()" x-init="init()" class="space-y-5">

    {{-- ── Sticky Summary Bar ───────────────────────────────── --}}
    <div class="sticky top-0 z-20 card py-3 shadow-md border-b border-gray-100">
        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
            {{-- Count pills --}}
            <div class="flex flex-wrap gap-2 flex-1">
                <button @click="activeTab = 'ready'" :class="activeTab === 'ready' ? 'ring-2 ring-[#7B61FF]' : ''"
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold transition-all"
                        style="background: #EDE9FE; color: #5B45DF;">
                    <span class="w-1.5 h-1.5 rounded-full" style="background: #7B61FF;"></span>
                    Ready <span class="font-bold">{{ $grouped['ready']->count() }}</span>
                </button>
                <button @click="activeTab = 'duplicate'" :class="activeTab === 'duplicate' ? 'ring-2 ring-amber-400' : ''"
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-orange-100 text-orange-700 transition-all">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                    Duplicate (same owner) <span class="font-bold">{{ $grouped['duplicate']->count() }}</span>
                </button>
                <button @click="activeTab = 'possible_duplicate'" :class="activeTab === 'possible_duplicate' ? 'ring-2 ring-orange-300' : ''"
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-orange-50 text-orange-600 transition-all">
                    <span class="w-1.5 h-1.5 rounded-full bg-orange-400"></span>
                    Possible Duplicate <span class="font-bold">{{ $grouped['possible_duplicate']->count() }}</span>
                </button>
                @if($isAdmin)
                <button @click="activeTab = 'same_email_other_referrer'" :class="activeTab === 'same_email_other_referrer' ? 'ring-2 ring-[#7B61FF]' : ''"
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold transition-all"
                        style="background: #EDE9FE; color: #7B61FF;">
                    <span class="w-1.5 h-1.5 rounded-full" style="background: #7B61FF;"></span>
                    Same Email (Other Referrer) <span class="font-bold">{{ $grouped['same_email_other_referrer']->count() }}</span>
                </button>
                @endif
                <button @click="activeTab = 'unknown_org'" :class="activeTab === 'unknown_org' ? 'ring-2 ring-blue-400' : ''"
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700 transition-all">
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                    Unknown Org <span class="font-bold">{{ $grouped['unknown_org']->count() }}</span>
                </button>
                <button @click="activeTab = 'unknown_deal'" :class="activeTab === 'unknown_deal' ? 'ring-2 ring-sky-400' : ''"
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-sky-100 text-sky-700 transition-all">
                    <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                    Unknown Deal <span class="font-bold">{{ $grouped['unknown_deal']->count() }}</span>
                </button>
                <button @click="activeTab = 'existing_user'" :class="activeTab === 'existing_user' ? 'ring-2 ring-teal-400' : ''"
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-teal-100 text-teal-700 transition-all">
                    <span class="w-1.5 h-1.5 rounded-full bg-teal-500"></span>
                    Existing User <span class="font-bold">{{ $grouped['existing_user']->count() }}</span>
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
                <a href="{{ route('tenant.imports.contacts', $tenant->id) }}"
                   class="btn-secondary text-sm">Cancel</a>
                {{-- Import Approved Rows Only --}}
                <form action="{{ route('tenant.imports.contacts.execute', [$tenant->id, $batch->id]) }}" method="POST">
                    @csrf
                    <input type="hidden" name="approved_only" value="1">
                    <button type="submit" class="btn-secondary text-sm">
                        Import Approved Rows Only
                    </button>
                </form>
                {{-- Import Now --}}
                <form action="{{ route('tenant.imports.contacts.execute', [$tenant->id, $batch->id]) }}" method="POST" id="confirm-import-form">
                    @csrf
                    <button type="submit"
                            :disabled="unresolvedDuplicates > 0"
                            :class="unresolvedDuplicates > 0 ? 'opacity-50 cursor-not-allowed' : ''"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-white transition-colors"
                            style="background: #7B61FF;" onmouseover="if(!this.disabled) this.style.background='#5B45DF'" onmouseout="this.style.background='#7B61FF'">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Import Now
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
                    ['key' => 'all',                      'label' => 'All',                        'count' => collect($grouped)->flatten(1)->count()],
                    ['key' => 'ready',                    'label' => 'Ready',                      'count' => $grouped['ready']->count()],
                    ['key' => 'duplicate',                'label' => 'Duplicate (same owner)',      'count' => $grouped['duplicate']->count()],
                    ['key' => 'possible_duplicate',       'label' => 'Possible Duplicate',         'count' => $grouped['possible_duplicate']->count()],
                    ['key' => 'unknown_org',              'label' => 'Unknown Organization',       'count' => $grouped['unknown_org']->count()],
                    ['key' => 'unknown_deal',             'label' => 'Unknown Deal',               'count' => $grouped['unknown_deal']->count()],
                    ['key' => 'existing_user',            'label' => 'Existing User',              'count' => $grouped['existing_user']->count()],
                    ['key' => 'failed',                   'label' => 'Failed',                     'count' => $grouped['failed']->count()],
                    ['key' => 'blocked',                  'label' => 'Blocked',                    'count' => $grouped['blocked']->count()],
                ];
                if ($isAdmin) {
                    $tabs[] = ['key' => 'same_email_other_referrer', 'label' => 'Same Email (Other Referrer)', 'count' => $grouped['same_email_other_referrer']->count()];
                }
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
    <div x-show="(activeTab === 'duplicate' || activeTab === 'possible_duplicate') && {{ $grouped['duplicate']->count() + $grouped['possible_duplicate']->count() }} > 0"
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

    {{-- ── Admin info banner: Same Email (Other Referrer) ────── --}}
    @if($isAdmin && $grouped['same_email_other_referrer']->count() > 0)
    <div x-show="activeTab === 'same_email_other_referrer' || activeTab === 'all'"
         class="flex items-start gap-3 p-4 rounded-2xl border" style="background: #EDE9FE; border-color: #C4B5FD;">
        <svg class="w-5 h-5 shrink-0 mt-0.5" style="color: #7B61FF;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div class="flex-1">
            <p class="text-sm font-semibold" style="color: #5B45DF;">Same email exists under another Referrer</p>
            <p class="text-xs mt-0.5" style="color: #7B61FF;">
                {{ number_format($grouped['same_email_other_referrer']->count()) }} {{ Str::plural('row', $grouped['same_email_other_referrer']->count()) }} listed separately for admin visibility. These contacts will be created as private under the importing Referrer — they are not blocking duplicates.
            </p>
        </div>
    </div>
    @endif

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
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Contact Type</th>
                        <th>Intended Role</th>
                        <th>Organization</th>
                        <th>Deal</th>
                        <th>Visibility</th>
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
                        $statusKey = $row->status ?? 'ready';
                        $computed  = is_array($row->computed_data) ? $row->computed_data : (json_decode($row->computed_data, true) ?? []);
                        $issues    = is_array($row->issue_codes)   ? $row->issue_codes   : (json_decode($row->issue_codes, true)   ?? []);
                        $isSameEmail = $statusKey === 'same_email_other_referrer';
                        $isDuplicate = in_array($statusKey, ['duplicate', 'possible_duplicate']);
                        $rowBorder = match($statusKey) {
                            'ready'                    => 'border-l-2 border-l-[#7B61FF]',
                            'duplicate'                => 'border-l-2 border-l-amber-400',
                            'possible_duplicate'       => 'border-l-2 border-l-orange-300',
                            'same_email_other_referrer'=> 'border-l-2 border-l-purple-400',
                            'failed', 'blocked'        => 'border-l-2 border-l-red-400',
                            'unknown_org'              => 'border-l-2 border-l-blue-400',
                            'unknown_deal'             => 'border-l-2 border-l-sky-400',
                            'existing_user'            => 'border-l-2 border-l-teal-400',
                            default                    => '',
                        };
                        $showRow = $isAdmin || $statusKey !== 'same_email_other_referrer';
                    @endphp
                    @if($showRow)
                    <tr class="table-row {{ $rowBorder }} transition-all"
                        x-show="activeTab === 'all' || activeTab === '{{ $statusKey }}'"
                        data-row-id="{{ $row->id }}"
                        data-status="{{ $statusKey }}">
                        {{-- Checkbox (duplicates only) --}}
                        <td class="w-8 px-3">
                            @if($isDuplicate)
                            <input type="checkbox"
                                   class="rounded border-gray-300 text-[#7B61FF] row-checkbox"
                                   :checked="selectedRows.includes({{ $row->id }})"
                                   @change="toggleRow({{ $row->id }}, $event.target.checked)">
                            @endif
                        </td>

                        <td class="text-xs text-gray-400 tabular-nums">{{ $loop->index + 1 }}</td>

                        {{-- Name --}}
                        <td>
                            <span class="font-medium text-[#1E1B4B] text-sm">
                                {{ trim(($row->raw_data['first_name'] ?? '') . ' ' . ($row->raw_data['last_name'] ?? '')) ?: '—' }}
                            </span>
                        </td>

                        {{-- Email --}}
                        <td>
                            <span class="text-sm text-gray-700">{{ $row->raw_data['email'] ?? '—' }}</span>
                        </td>

                        {{-- Phone --}}
                        <td>
                            <span class="text-sm text-gray-600">{{ $row->raw_data['phone'] ?? '—' }}</span>
                        </td>

                        {{-- Contact Type --}}
                        <td>
                            <span class="text-xs text-gray-600">{{ $row->raw_data['contact_type'] ?? '—' }}</span>
                        </td>

                        {{-- Intended Role --}}
                        <td>
                            <span class="text-xs text-gray-600">{{ $row->raw_data['intended_role'] ?? '—' }}</span>
                        </td>

                        {{-- Organization --}}
                        <td>
                            <span class="text-sm {{ $statusKey === 'unknown_org' ? 'text-blue-600 font-medium' : 'text-gray-700' }}">
                                {{ $row->raw_data['organization_name'] ?? $row->raw_data['organization'] ?? '—' }}
                            </span>
                        </td>

                        {{-- Deal --}}
                        <td>
                            <span class="text-sm {{ $statusKey === 'unknown_deal' ? 'text-sky-600 font-medium' : 'text-gray-700' }}">
                                {{ $row->raw_data['deal_name'] ?? $row->raw_data['deal'] ?? '—' }}
                            </span>
                        </td>

                        {{-- Visibility --}}
                        <td>
                            <span class="text-xs text-gray-600">{{ ucfirst($row->raw_data['visibility'] ?? 'private') }}</span>
                        </td>

                        {{-- Row Status --}}
                        <td>
                            @php
                                $badgeMap = [
                                    'ready'                     => 'badge-green',
                                    'duplicate'                 => 'badge-orange',
                                    'possible_duplicate'        => 'badge-orange',
                                    'same_email_other_referrer' => 'badge-purple',
                                    'unknown_org'               => 'badge-blue',
                                    'unknown_deal'              => 'badge-blue',
                                    'existing_user'             => 'badge-blue',
                                    'failed'                    => 'badge-red',
                                    'blocked'                   => 'badge-gray',
                                ];
                                $bc = $badgeMap[$statusKey] ?? 'badge-gray';
                                $labelMap = [
                                    'ready'                     => 'Ready',
                                    'duplicate'                 => 'Duplicate',
                                    'possible_duplicate'        => 'Possible Dup.',
                                    'same_email_other_referrer' => 'Same Email',
                                    'unknown_org'               => 'Unknown Org',
                                    'unknown_deal'              => 'Unknown Deal',
                                    'existing_user'             => 'Existing User',
                                    'failed'                    => 'Failed',
                                    'blocked'                   => 'Blocked',
                                ];
                                $label = $labelMap[$statusKey] ?? ucfirst(str_replace('_', ' ', $statusKey));
                            @endphp
                            <span class="badge {{ $bc }}">{{ $label }}</span>
                        </td>

                        {{-- Issues --}}
                        <td>
                            <div class="flex flex-wrap gap-1">
                                @forelse($issues as $issue)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-600 whitespace-nowrap">
                                    {{ str_replace('_', ' ', is_array($issue) ? ($issue['code'] ?? '') : $issue) }}
                                </span>
                                @empty
                                <span class="text-gray-300 text-xs">—</span>
                                @endforelse
                            </div>
                        </td>

                        {{-- Action --}}
                        <td>
                            @if($isSameEmail)
                            {{-- Same-email cross-referrer: informational only, not a blocker --}}
                            <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-lg" style="background: #EDE9FE; color: #7B61FF;">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                Will Create (Private)
                            </span>
                            @elseif($isDuplicate)
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
                            <span class="text-xs font-medium" style="color: #7B61FF;">Will import</span>
                            @endif
                        </td>
                    </tr>

                    {{-- Existing Contact Details (collapsible for duplicates) --}}
                    @if($isDuplicate)
                    @php $existing = $computed['existing_contact'] ?? null; @endphp
                    @if($existing)
                    <tr x-show="activeTab === 'all' || activeTab === '{{ $statusKey }}'"
                        class="bg-amber-50/50">
                        <td colspan="13" class="px-8 py-3">
                            <div class="text-xs text-amber-800 font-semibold mb-2 flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Existing contact in system
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <div>
                                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Name</p>
                                    <p class="text-xs text-gray-700 mt-0.5">{{ $existing['name'] ?? '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Email</p>
                                    <p class="text-xs text-gray-700 mt-0.5">{{ $existing['email'] ?? '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Organization</p>
                                    <p class="text-xs text-gray-700 mt-0.5">{{ $existing['organization'] ?? '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Status</p>
                                    <p class="text-xs text-gray-700 mt-0.5">{{ ucfirst($existing['status'] ?? '—') }}</p>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endif
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
                    <span class="font-semibold" style="color: #7B61FF;">{{ $grouped['ready']->count() }} rows ready</span>
                    @if($grouped['duplicate']->count() > 0)
                    · <span class="text-amber-600 font-semibold">{{ $grouped['duplicate']->count() }} duplicates to resolve</span>
                    @endif
                    @if($grouped['possible_duplicate']->count() > 0)
                    · <span class="text-orange-500 font-semibold">{{ $grouped['possible_duplicate']->count() }} possible duplicates</span>
                    @endif
                    @if($isAdmin && $grouped['same_email_other_referrer']->count() > 0)
                    · <span class="font-semibold" style="color: #7B61FF;">{{ $grouped['same_email_other_referrer']->count() }} same-email (other referrer) — will create as private</span>
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
                <form action="{{ route('tenant.imports.contacts.execute', [$tenant->id, $batch->id]) }}" method="POST">
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
                        style="background: #7B61FF;" onmouseover="if(!this.disabled) this.style.background='#5B45DF'" onmouseout="this.style.background='#7B61FF'">
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
function contactImportPreview() {
    return {
        activeTab: 'all',
        selectedRows: [],
        bulkAction: '',
        rowActions: {},
        unresolvedDuplicates: {{ $grouped['duplicate']->count() + $grouped['possible_duplicate']->count() }},

        init() {
            // Default all duplicates to 'skip'
            @foreach(collect($grouped['duplicate'])->merge($grouped['possible_duplicate']) as $row)
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
                const res = await fetch('{{ route('tenant.imports.contacts.approve-row', [$tenant->id, $batch->id, '__ROW__']) }}'.replace('__ROW__', rowId), {
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
                const res = await fetch('{{ route('tenant.imports.contacts.bulk-approve', [$tenant->id, $batch->id]) }}', {
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
            this.unresolvedDuplicates = 0;
        },
    };
}
</script>
@endpush
@endsection
