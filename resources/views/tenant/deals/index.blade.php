@extends('layouts.app')
@section('title', 'Deals')
@section('nav') @include('tenant._nav') @endsection

@section('topbar-actions')
    {{-- Import Deals — LGU IDS uses locked import flow; other tenants use generic flow --}}
    @if($tenant->id === 'lgu-ids')
        <a href="{{ route('tenant.imports.lgu-ids', $tenant->id) }}"
           class="btn-secondary"
           title="Uses the locked LGU IDS deal import template and computation rules.">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
            <span class="hidden sm:inline">Import Deals</span>
        </a>
    @else
        <a href="{{ route('tenant.imports.deals', $tenant->id) }}"
           class="btn-secondary"
           title="Upload a standard file to create or update deals.">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
            <span class="hidden sm:inline">Import Deals</span>
        </a>
    @endif
    <button onclick="window.dispatchEvent(new CustomEvent('open-deal-delete'))"
            class="btn-secondary" title="Select deals to delete">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
        </svg>
        <span class="hidden sm:inline">Delete Deals</span>
    </button>
    <button x-data @click="$dispatch('open-add-deal')" class="btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        <span class="hidden sm:inline">New Deal</span>
    </button>
@endsection

@section('content')
<div class="space-y-5"
     x-data="dealsModule('{{ $tenant->id }}', {{ $showLocation ? 'true' : 'false' }}, {{ $canViewReferrers ? 'true' : 'false' }})"
     x-init="init()"
     @open-add-deal.window="showAdd = true; resetForm()"
     @open-deal-delete.window="showDeleteInstructions = true; selectMode = false; selectedDeals = []"
     @rb-del-cancel.window="selectMode = false; selectedDeals = []"
     @rb-del-execute.window="if(selectedDeals.length > 0) showDeleteConfirm = true">

    {{-- Filter bar --}}
    <div class="card space-y-3">
        <div class="flex items-center gap-3">
            <div class="search-group flex-1">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" x-model="search" @input.debounce.250ms="applyFilters()" placeholder="Search deals…">
                <button x-show="search.length > 0" @click="search = ''; applyFilters()"
                        class="text-gray-400 hover:text-gray-600 transition-colors shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
                <button @click="viewMode = 'table'" :class="viewMode==='table' ? 'bg-[#7B61FF] text-white border-[#7B61FF]' : 'text-gray-500 border-gray-200 hover:bg-gray-50'"
                        class="p-2 rounded-xl border transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 6h18M3 14h18M3 18h18"/></svg>
                </button>
                <button @click="viewMode = 'kanban'" :class="viewMode==='kanban' ? 'bg-[#7B61FF] text-white border-[#7B61FF]' : 'text-gray-500 border-gray-200 hover:bg-gray-50'"
                        class="p-2 rounded-xl border transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/></svg>
                </button>
            </div>
        </div>
        <div class="filter-bar">
            @if($showLocation)
            <label class="filter-pill" :class="filterProvince !== '' ? 'active' : ''">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                <select x-model="filterProvince" @change="applyFilters()">
                    <option value="">All Provinces</option>
                    @foreach(['Abra','Agusan del Norte','Agusan del Sur','Aklan','Albay','Antique','Apayao','Aurora','Basilan','Bataan','Batanes','Batangas','Benguet','Biliran','Bohol','Bukidnon','Bulacan','Cagayan','Camarines Norte','Camarines Sur','Camiguin','Capiz','Catanduanes','Cavite','Cebu','Cotabato','Davao de Oro','Davao del Norte','Davao del Sur','Davao Occidental','Davao Oriental','Dinagat Islands','Eastern Samar','Guimaras','Ifugao','Ilocos Norte','Ilocos Sur','Iloilo','Isabela','Kalinga','La Union','Laguna','Lanao del Norte','Lanao del Sur','Leyte','Maguindanao del Norte','Maguindanao del Sur','Marinduque','Masbate','Metro Manila','Misamis Occidental','Misamis Oriental','Mountain Province','Negros Occidental','Negros Oriental','Northern Samar','Nueva Ecija','Nueva Vizcaya','Occidental Mindoro','Oriental Mindoro','Palawan','Pampanga','Pangasinan','Quezon','Quirino','Rizal','Romblon','Samar','Sarangani','Siquijor','Sorsogon','South Cotabato','Southern Leyte','Sultan Kudarat','Sulu','Surigao del Norte','Surigao del Sur','Tarlac','Tawi-Tawi','Zambales','Zamboanga del Norte','Zamboanga del Sur','Zamboanga Sibugay'] as $prov)
                    <option value="{{ $prov }}">{{ $prov }}</option>
                    @endforeach
                </select>
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>
            @endif
            <label class="filter-pill" :class="filterStage !== '' ? 'active' : ''">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <select x-model="filterStage" @change="applyFilters()">
                    <option value="">All Stages</option>
                    <option value="introduction">Introduction</option>
                    <option value="presentation">Presentation</option>
                    <option value="contract_sent">Contract Sent</option>
                    <option value="signed">Signed</option>
                    <option value="paid">Paid</option>
                </select>
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>
            <label class="filter-pill" :class="filterStatus !== '' ? 'active' : ''">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <select x-model="filterStatus" @change="applyFilters()">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="expiring">Expiring</option>
                    <option value="expired">Expired</option>
                </select>
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>
            <label class="filter-pill" :class="filterCommission !== '' ? 'active' : ''">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1"/></svg>
                <select x-model="filterCommission" @change="applyFilters()">
                    <option value="">All Commission</option>
                    <option value="pending">Pending</option>
                    <option value="locked">Locked</option>
                    <option value="paid">Paid</option>
                </select>
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>
            <template x-if="filterStage || filterStatus || filterCommission || filterProvince || search">
            <button @click="filterStage=''; filterStatus=''; filterCommission=''; filterProvince=''; filterReseller=''; search=''; applyFilters()"
                    class="filter-pill !border-red-200 !text-red-500 hover:!bg-red-50">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                Clear
            </button>
            </template>
        </div>
    </div>

    {{-- Loading --}}
    <div x-show="loading" class="card flex items-center justify-center py-16 gap-3 text-gray-400">
        <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
        <span class="text-sm">Loading deals…</span>
    </div>

    {{-- Table view --}}
    <div x-show="!loading && viewMode === 'table'" class="card p-0 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
            <div class="flex items-center gap-2 flex-wrap">
                <p class="text-sm font-semibold text-[#1E1B4B]">
                    <span x-text="filtered.length"></span> deals
                    <span x-show="filterStage || filterStatus || filterCommission || filterProvince || search" class="text-gray-400 font-normal text-xs ml-1">— filtered</span>
                </p>
                <span x-show="sortCol !== 'created_at'"
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-purple-50 text-purple-600 text-xs font-medium">
                    <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 10 6">
                        <template x-if="sortDir === 'asc'"><path d="M1 5L5 1L9 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></template>
                        <template x-if="sortDir === 'desc'"><path d="M1 1L5 5L9 1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></template>
                    </svg>
                    <span x-text="{name:'Deal',stage:'Stage',reseller:'Referrer',value:'Value',created_at:'Date Added',days_left:'Days Left',status:'Status'}[sortCol] || sortCol"></span>
                    <button @click="sortCol = 'created_at'; sortDir = 'desc'" class="ml-0.5 hover:text-purple-800" title="Clear sort">×</button>
                </span>
            </div>
            <div class="flex items-center gap-3 text-xs text-gray-400">
                <span class="tabular-nums" x-text="'₱' + totalValue()"></span>
                <span>pipeline</span>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="table-head">
                        @php
                        $sortCols = [
                            ['key'=>'name',       'label'=>'Deal',       'tip'=>'Sort A → Z or Z → A',                    'hidden'=>''],
                            ['key'=>'stage',      'label'=>'Stage',      'tip'=>'Sort by pipeline progress',              'hidden'=>''],
                            ['key'=>'reseller',   'label'=>'Referrer',   'tip'=>'Sort A → Z or Z → A',                    'hidden'=>'hidden sm:table-cell'],
                            ['key'=>'value',      'label'=>'Value',      'tip'=>'Sort highest or lowest first',           'hidden'=>''],
                            ['key'=>'created_at', 'label'=>'Date Added',  'tip'=>'Sort by date the deal was added',        'hidden'=>'hidden md:table-cell'],
                            ['key'=>'days_left',  'label'=>'Days Left',  'tip'=>'Sort most urgent (fewest days) first',   'hidden'=>'hidden md:table-cell'],
                            ['key'=>'status',     'label'=>'Status',     'tip'=>'Sort by deal status',                    'hidden'=>''],
                        ];
                        @endphp
                        <th x-show="selectMode" class="w-10">
                            <input type="checkbox"
                                   class="rounded border-gray-300 text-red-500 cursor-pointer"
                                   :checked="selectedDeals.length > 0 && selectedDeals.length === sortedFiltered().length"
                                   @change="toggleSelectAll()">
                        </th>
                        @foreach($sortCols as $col)
                        <th class="cursor-pointer select-none hover:bg-gray-100 transition-colors {{ $col['hidden'] }}"
                            @click="sort('{{ $col['key'] }}')"
                            title="{{ $col['tip'] }}">
                            <div class="flex items-center gap-1.5">
                                <span>{{ $col['label'] }}</span>
                                <span class="flex flex-col gap-0.5 shrink-0 ml-auto">
                                    <svg class="w-2.5 h-2.5 transition-colors"
                                         :class="sortCol === '{{ $col['key'] }}' && sortDir === 'asc' ? 'text-[#7B61FF]' : 'text-gray-300'"
                                         fill="none" viewBox="0 0 10 6">
                                        <path d="M1 5L5 1L9 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <svg class="w-2.5 h-2.5 transition-colors"
                                         :class="sortCol === '{{ $col['key'] }}' && sortDir === 'desc' ? 'text-[#7B61FF]' : 'text-gray-300'"
                                         fill="none" viewBox="0 0 10 6">
                                        <path d="M1 1L5 5L9 1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                            </div>
                        </th>
                        @endforeach
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="filtered.length === 0 && !loading">
                        <tr>
                            <td colspan="8" class="py-16 text-center">
                                <div class="text-gray-300 mb-3 flex justify-center">
                                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                </div>
                                <p class="text-gray-400 text-sm" x-text="leads.length === 0 ? 'No deals yet. Add your first deal to get started.' : 'No deals match the current filters.'"></p>
                                <button x-show="leads.length === 0" @click="showAdd = true; resetForm()" class="btn-primary mt-3 text-sm">Add First Deal</button>
                            </td>
                        </tr>
                    </template>
                    <template x-for="lead in sortedFiltered()" :key="lead.id">
                        <tr class="table-row cursor-pointer transition-colors"
                            :class="selectMode && selectedDeals.includes(lead.id) ? 'bg-red-50' : ''"
                            @click="viewDeal(lead.id)">
                            <td x-show="selectMode" class="w-10 pl-3" @click.stop>
                                <input type="checkbox"
                                       style="width:18px;height:18px;cursor:pointer;accent-color:#dc2626"
                                       :checked="selectedDeals.includes(lead.id)"
                                       @click.stop="toggleDeal(lead.id)">
                            </td>
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-xl flex items-center justify-center text-[#7B61FF] font-bold text-xs shrink-0"
                                         style="background:#EDE9FE" x-text="(lead.name||'?').slice(0,2).toUpperCase()"></div>
                                    <div class="min-w-0">
                                        <p class="font-medium text-[#1E1B4B] truncate max-w-48" x-text="lead.name"></p>
                                        <p class="text-xs text-gray-400" x-text="[lead.data?.province, lead.data?.municipality].filter(Boolean).join(' › ') || ''"></p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span :class="stageBadge(lead.stage)" x-text="stageLabel(lead.stage)"></span>
                            </td>
                            <td class="hidden sm:table-cell">
                                <template x-if="!canViewReferrers">
                                    <span class="badge badge-gray text-xs">Restricted</span>
                                </template>
                                <template x-if="canViewReferrers && lead.reseller_name">
                                    <span class="text-gray-700 text-sm" x-text="lead.reseller_name"></span>
                                </template>
                                <template x-if="canViewReferrers && !lead.reseller_name">
                                    <span class="badge badge-gray text-xs">Unassigned</span>
                                </template>
                            </td>
                            <td class="font-semibold text-[#1E1B4B]" x-text="formatValue(lead.deal_value)"></td>
                            <td class="hidden md:table-cell">
                                <span class="text-xs text-gray-500" x-text="lead.created_at ? new Date(lead.created_at).toLocaleDateString('en-PH', {month:'short', day:'numeric', year:'numeric'}) : '—'"></span>
                            </td>
                            <td>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold"
                                      :class="{
                                          'bg-red-100 text-red-700':    (lead.days_left ?? 99) <= 3,
                                          'bg-amber-100 text-amber-700': (lead.days_left ?? 99) > 3 && (lead.days_left ?? 99) <= 7,
                                          'bg-blue-50 text-blue-600':   (lead.days_left ?? 99) > 7,
                                      }">
                                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span x-text="(lead.days_left ?? 0) <= 0 ? 'Overdue' : (lead.days_left + 'd')"></span>
                                </span>
                            </td>
                            <td>
                                <span :class="{
                                    'badge badge-green':  lead.status === 'active',
                                    'badge badge-orange': lead.status === 'expiring',
                                    'badge badge-red':    lead.status === 'expired',
                                    'badge badge-gray':   lead.status === 'reassigned' || lead.status === 'declined',
                                }" x-text="lead.status ? lead.status.charAt(0).toUpperCase() + lead.status.slice(1) : 'Active'"></span>
                            </td>
                            <td @click.stop>
                                <a :href="'/tenant/{{ $tenant->id }}/deals/' + lead.id"
                                   class="px-2.5 py-1.5 rounded-lg text-xs font-medium bg-purple-50 text-purple-700 hover:bg-purple-100 transition-colors">
                                    View →
                                </a>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Kanban view --}}
    <div x-show="!loading && viewMode === 'kanban'" class="overflow-x-auto pb-4">
        <div class="flex gap-4 min-w-max">
            <template x-for="stage in stages" :key="stage.key">
                <div class="w-64 flex-none">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <div class="w-2 h-2 rounded-full" :style="'background:' + stage.color"></div>
                            <span class="text-xs font-semibold text-gray-700 uppercase tracking-wide" x-text="stage.label"></span>
                        </div>
                        <span class="text-xs text-gray-400" x-text="leadsInStage(stage.key).length"></span>
                    </div>
                    <div class="space-y-2">
                        <template x-for="lead in leadsInStage(stage.key)" :key="lead.id">
                            <a :href="'/tenant/{{ $tenant->id }}/deals/' + lead.id"
                               class="block card p-3 hover:shadow-md transition-shadow cursor-pointer">
                                <p class="font-medium text-[#1E1B4B] text-sm truncate" x-text="lead.name"></p>
                                <template x-if="!canViewReferrers">
                                    <p class="text-xs text-gray-300 mt-0.5">Restricted</p>
                                </template>
                                <template x-if="canViewReferrers">
                                    <p class="text-xs text-gray-400 mt-0.5" x-text="lead.reseller_name || 'Unassigned'"></p>
                                </template>
                                <div class="flex items-center justify-between mt-2">
                                    <span class="text-xs font-semibold text-gray-700" x-text="formatValue(lead.deal_value)"></span>
                                    <span :class="commissionBadge(lead.commission_status) + ' text-xs'" x-text="lead.commission_status || 'pending'"></span>
                                </div>
                                {{-- Days-to-move counter on kanban card --}}
                                <div x-show="lead.stage !== 'paid'" class="mt-2 pt-2 border-t border-gray-100 flex items-center gap-1">
                                    <svg class="w-3 h-3 shrink-0"
                                         :class="{
                                             'text-red-500':   (lead.days_left ?? 99) <= 3,
                                             'text-amber-500': (lead.days_left ?? 99) > 3 && (lead.days_left ?? 99) <= 7,
                                             'text-blue-400':  (lead.days_left ?? 99) > 7,
                                         }"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span class="text-[11px] font-semibold"
                                          :class="{
                                              'text-red-600':   (lead.days_left ?? 99) <= 3,
                                              'text-amber-600': (lead.days_left ?? 99) > 3 && (lead.days_left ?? 99) <= 7,
                                              'text-blue-500':  (lead.days_left ?? 99) > 7,
                                          }"
                                          x-text="(lead.days_left ?? 0) <= 0 ? 'Overdue' : (lead.days_left + 'd to move stage')"></span>
                                </div>
                            </a>
                        </template>
                        <div x-show="leadsInStage(stage.key).length === 0" class="text-center py-6 text-gray-300 text-xs">Empty</div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- Add Record Modal --}}
    <div x-show="showAdd" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 sticky top-0 bg-white">
                <h3 class="font-semibold text-[#1E1B4B]" x-text="showSuccessState ? 'Deal Created' : 'New Deal'"></h3>
                <button @click="showAdd = false; showSuccessState = false; createdDeal = null; resetForm()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- ── Success State ── --}}
            <div x-show="showSuccessState" class="p-8 text-center space-y-5">
                <div class="flex justify-center">
                    <div class="w-16 h-16 rounded-2xl flex items-center justify-center" style="background:#D1FAE5">
                        <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-emerald-600 mb-1">Deal Created Successfully</p>
                    <h3 class="text-lg font-bold text-[#1E1B4B]" x-text="createdDeal?.name || 'New Deal'"></h3>
                    <p class="text-sm text-gray-400 mt-1">
                        <span x-text="createdDeal?.data?.municipality && createdDeal?.data?.province ? createdDeal.data.municipality + ', ' + createdDeal.data.province : ''"></span>
                    </p>
                </div>
                <div class="grid grid-cols-3 gap-2 p-3 rounded-xl bg-gray-50">
                    <div class="text-center">
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Stage</p>
                        <p class="text-sm font-semibold text-[#1E1B4B] capitalize mt-0.5" x-text="(createdDeal?.stage || 'introduction').replace('_',' ')"></p>
                    </div>
                    <div class="text-center border-x border-gray-200">
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Referrer</p>
                        <p class="text-sm font-semibold text-[#1E1B4B] truncate mt-0.5" x-text="createdDeal?.reseller_name || '—'"></p>
                    </div>
                    <div class="text-center">
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Value</p>
                        <p class="text-sm font-semibold text-[#1E1B4B] mt-0.5" x-text="createdDeal?.deal_value ? '₱' + Number(createdDeal.deal_value).toLocaleString() : '—'"></p>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row gap-3 justify-center pt-1">
                    <a :href="'/tenant/{{ $tenant->id }}/deals/' + createdDeal?.id"
                       class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-sm font-medium text-white transition-colors"
                       style="background:#7B61FF;" onmouseover="this.style.background='#5B45DF'" onmouseout="this.style.background='#7B61FF'">
                        View Deal
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                    <button @click="showSuccessState = false; createdDeal = null; resetForm()"
                            class="btn-secondary text-sm">
                        Create Another Deal
                    </button>
                </div>
            </div>

            {{-- ── Form (hidden while success state shows) ── --}}
            <div x-show="!showSuccessState">
            <div class="p-6 space-y-4">
                @if($showLocation)
                {{-- Province + Municipality — LGU IDS specific (driven by tenant field config) --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">Province *</label>
                        <select x-model="form.province"
                                @change="form.municipality = ''; municipalityOptions = PH_MUNICIPALITIES[form.province] || []; deriveName()"
                                class="form-input">
                            <option value="">Select province…</option>
                            @foreach(\App\Support\PhilippineMunicipalities::all() as $prov => $cities)
                            <option value="{{ $prov }}">{{ $prov }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Municipality / City *</label>
                        <select x-model="form.municipality"
                                @change="deriveName()"
                                :disabled="!form.province || municipalityOptions.length === 0"
                                class="form-input"
                                :class="!form.province ? 'opacity-50 cursor-not-allowed' : ''">
                            <option value="">
                                <span x-show="!form.province">Select province first…</span>
                                <span x-show="form.province">Select municipality / city…</span>
                            </option>
                            <template x-for="city in municipalityOptions" :key="city">
                                <option :value="city" x-text="city"></option>
                            </template>
                        </select>
                        <p x-show="!form.province" class="text-[11px] text-gray-400 mt-0.5">Select a province first.</p>
                    </div>
                </div>
                @endif

                <div>
                    <label class="form-label">
                        Deal Name *
                        @if($showLocation)
                        <span x-show="nameAutoFilled" class="ml-1 text-xs text-purple-500 font-normal">(auto-filled from location)</span>
                        @endif
                    </label>
                    <input type="text" x-model="form.name" @input="nameAutoFilled = false" class="form-input"
                           placeholder="{{ $showLocation ? 'Auto-fills from Province + Municipality' : 'Enter ' . strtolower($leadLabel) . ' name' }}">
                </div>

                <div>
                    <label class="form-label">Stage</label>
                    <select x-model="form.stage" class="form-input">
                        <option value="introduction">Introduction</option>
                        <option value="presentation">Presentation</option>
                        <option value="contract_sent">Contract Sent</option>
                        <option value="signed">Signed</option>
                        <option value="paid">Paid</option>
                    </select>
                </div>

                {{-- Referrer assignment — searchable combobox --}}
                <div>
                    <label class="form-label">Referrer *</label>
                    <p class="text-[11px] text-gray-400 mb-2">Select an existing referrer, admin, or manager. Use manual add only if the person is not yet in the system.</p>

                    {{-- Combobox mode (default) --}}
                    <div x-show="!manualReferrer">

                        {{-- Selected referrer pill --}}
                        <div x-show="referrerSelected"
                             class="flex items-center gap-2 p-2.5 border border-purple-200 rounded-xl bg-purple-50 mb-2">
                            <div class="w-7 h-7 rounded-full bg-purple-200 flex items-center justify-center text-purple-700 text-xs font-bold shrink-0"
                                 x-text="(referrerSelected?.name||'?').slice(0,2).toUpperCase()"></div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-[#1E1B4B] truncate" x-text="referrerSelected?.name"></p>
                                <p class="text-xs text-gray-400 truncate" x-text="referrerSelected?.email"></p>
                            </div>
                            <div class="flex gap-1 items-center shrink-0 flex-wrap justify-end max-w-[130px]">
                                <template x-for="badge in (referrerSelected?.role_badges||[]).slice(0,2)">
                                    <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full"
                                          :class="badge.includes('Admin')||badge.includes('Owner') ? 'bg-blue-100 text-blue-700' : badge.includes('Manager') ? 'bg-teal-100 text-teal-700' : badge.includes('Pending') ? 'bg-amber-100 text-amber-700' : 'bg-purple-100 text-purple-700'"
                                          x-text="badge"></span>
                                </template>
                                <span x-show="referrerSelected?.status === 'invited' && !(referrerSelected?.role_badges||[]).some(b => b.includes('Pending'))"
                                      class="text-[10px] font-medium px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700">Pending</span>
                            </div>
                            <button type="button" @click="clearReferrerSelection()" title="Change referrer"
                                    class="ml-1 p-0.5 text-gray-400 hover:text-gray-600 shrink-0 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        {{-- Search input + dropdown --}}
                        {{-- @click.outside is on the wrapper so clicking the input (a sibling of the dropdown) doesn't trigger a close --}}
                        <div x-show="!referrerSelected" class="relative" @click.outside="referrerOpen = false">
                            <div class="relative">
                                <input type="text"
                                       x-model="referrerQuery"
                                       @input.debounce.300ms="referrerOpen = true; searchReferrers()"
                                       @keydown.escape="referrerOpen = false"
                                       @keydown.arrow-down.prevent="referrerFocusNext()"
                                       @keydown.arrow-up.prevent="referrerFocusPrev()"
                                       @keydown.enter.prevent="referrerSelectFocused()"
                                       placeholder="Search referrers, admins, or managers…"
                                       autocomplete="off"
                                       class="form-input pr-8">
                                <div class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer"
                                     @click="referrerOpen = true; searchReferrers()"
                                     title="Search referrers">
                                    <svg x-show="!loadingReferrers" class="w-4 h-4 text-gray-400 hover:text-purple-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                    <svg x-show="loadingReferrers" class="w-4 h-4 text-gray-400 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                </div>
                            </div>

                            {{-- Dropdown list --}}
                            <div x-show="referrerOpen"
                                 class="absolute z-[60] w-full mt-1 bg-white rounded-xl shadow-xl border border-gray-100 max-h-56 overflow-y-auto"
                                 style="display:none">

                                {{-- Loading --}}
                                <div x-show="loadingReferrers" class="flex items-center gap-2 px-4 py-3 text-sm text-gray-400">
                                    <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    Loading referrers…
                                </div>

                                {{-- Error --}}
                                <div x-show="!loadingReferrers && referrerLoadError" class="px-4 py-3">
                                    <p class="text-xs text-red-500" x-text="referrerLoadError"></p>
                                    <button type="button" @click="searchReferrers()" class="text-xs text-purple-600 hover:underline mt-1">Try again</button>
                                </div>

                                {{-- Options --}}
                                <div x-show="!loadingReferrers && !referrerLoadError">
                                    <template x-for="(r, idx) in activatedReferrers" :key="r.id">
                                        <button type="button"
                                                @click="selectReferrer(r)"
                                                :class="referrerFocusIdx === idx ? 'bg-[#F0EFFA]' : 'hover:bg-gray-50'"
                                                class="w-full flex items-center gap-3 px-4 py-2.5 text-left transition-colors">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                                                 :class="r.status === 'invited' ? 'bg-amber-100 text-amber-700' : (r.type === 'admin' || r.type === 'owner') ? 'bg-blue-100 text-blue-700' : r.type === 'manager' ? 'bg-teal-100 text-teal-700' : 'bg-purple-100 text-purple-700'"
                                                 x-text="(r.name||'?').slice(0,2).toUpperCase()"></div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-[#1E1B4B] truncate" x-text="r.name"></p>
                                                <p class="text-xs text-gray-400 truncate" x-text="r.email"></p>
                                            </div>
                                            <div class="flex flex-wrap gap-1 items-center justify-end shrink-0 max-w-[120px]">
                                                <template x-for="badge in (r.role_badges||[]).slice(0,2)">
                                                    <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full"
                                                          :class="badge.includes('Admin')||badge.includes('Owner') ? 'bg-blue-100 text-blue-700' : badge.includes('Manager') ? 'bg-teal-100 text-teal-700' : badge.includes('Pending') ? 'bg-amber-100 text-amber-700' : 'bg-purple-100 text-purple-700'"
                                                          x-text="badge"></span>
                                                </template>
                                            </div>
                                        </button>
                                    </template>

                                    {{-- Empty state --}}
                                    <div x-show="activatedReferrers.length === 0 && !loadingReferrers && !referrerLoadError"
                                         class="px-4 py-6 text-center">
                                        <p class="text-sm text-gray-400" x-text="referrerQuery ? 'No matching referrers found in this tenant.' : 'No referrers found. Start typing to search.'"></p>
                                    </div>
                                </div>

                                {{-- Footer: manual add --}}
                                <div class="border-t border-gray-100 px-4 py-2.5">
                                    <button type="button"
                                            @click="manualReferrer = true; referrerOpen = false; form.reseller_name = ''; form.reseller_email = ''"
                                            class="text-xs text-[#7B61FF] hover:underline font-medium flex items-center gap-1.5">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        Can't find the referrer? Add manually
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Manual mode --}}
                    <div x-show="manualReferrer" class="space-y-2">
                        <input type="text"  x-model="form.reseller_name"  class="form-input" placeholder="Referrer full name *">
                        <input type="email" x-model="form.reseller_email" class="form-input" placeholder="referrer@email.com *">
                        <p class="text-[11px] text-gray-400">Enter name and email. An invitation can be sent from the Referrers tab after the deal is created.</p>
                        <button type="button" @click="manualReferrer = false; form.reseller_name = ''; form.reseller_email = ''; clearReferrerSelection()"
                                class="text-[11px] text-[#7B61FF] hover:underline">← Back to referrer list</button>
                    </div>
                </div>

                {{-- Financial inputs --}}
                <div class="space-y-3 pt-1">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Financial Breakdown</p>

                    {{-- Deal Value --}}
                    <div>
                        <label class="form-label flex items-center gap-1.5">
                            Deal Value (₱)
                            @if($showLocation)
                            <span class="text-[10px] text-purple-500 font-normal">(LGU IDS default: ₱4,000,000)</span>
                            @endif
                        </label>
                        <input type="number" x-model.number="form.deal_value"
                               @input="syncFromDealValue()"
                               class="form-input" placeholder="4000000" min="0" step="1000">
                        <div class="mt-1 space-y-0.5">
                            <div x-show="form.deal_value > 0 && form.base_cost > 0" class="flex items-center gap-1.5">
                                <span :class="isConsistent() ? 'text-emerald-500' : 'text-red-500'" class="text-[11px] font-medium">
                                    <span x-show="isConsistent()">✓ Base Cost + Added Amount = Deal Value</span>
                                    <span x-show="!isConsistent()">⚠ Mismatch — Deal Value ≠ Base Cost + Added Amount</span>
                                </span>
                            </div>
                            <p x-show="isNonStandardTier()" class="text-[11px] text-orange-500">
                                Non-standard tier — enter Base Cost manually.
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Base Cost (₱)</label>
                            <input type="number" x-model.number="form.base_cost"
                                   @input="syncFromBaseCost()"
                                   class="form-input" placeholder="0" min="0" step="100">
                            <p class="text-xs text-gray-400 mt-1"
                               x-text="showLocation && form.deal_value > 0
                                   ? (form.deal_value <= 6000000  ? 'Auto-fills at 60% of Deal Value. Edit to override.'
                                   : form.deal_value <= 12000000 ? 'Auto-fills at 58% of Deal Value. Edit to override.'
                                   : form.deal_value <= 15000000 ? 'Auto-fills at 48% of Deal Value. Edit to override.'
                                   : 'Auto-fills at 41% of Deal Value. Edit to override.')
                                   : 'Auto-fills from Deal Value %. Edit to override.'"></p>
                        </div>
                        <div>
                            <label class="form-label">Added Amount (₱)</label>
                            <input type="number" x-model.number="form.added_amount"
                                   @input="syncFromAddedAmount()"
                                   class="form-input" placeholder="0" min="0" step="100">
                            <p class="text-xs text-gray-400 mt-1">Deal Value − Base Cost. Edit to override.</p>
                        </div>
                    </div>

                    {{-- Live preview --}}
                    <div x-show="(form.base_cost > 0) || (form.added_amount > 0)"
                         class="grid grid-cols-3 gap-2 p-3 rounded-xl bg-gray-50 border border-gray-100">
                        <div class="text-center">
                            <p class="text-xs text-gray-400 uppercase tracking-wide">Contract Value</p>
                            <p class="text-sm font-bold text-[#1E1B4B] tabular-nums mt-0.5" x-text="'₱' + Math.round((form.base_cost||0)+(form.added_amount||0)).toLocaleString()"></p>
                        </div>
                        <div class="text-center border-x border-gray-200">
                            <p class="text-xs text-blue-500 uppercase tracking-wide">Company Share</p>
                            <p class="text-sm font-bold text-blue-700 tabular-nums mt-0.5" x-text="'₱' + Math.round((form.added_amount||0)*0.30).toLocaleString()"></p>
                            <p class="text-xs text-blue-400">30% margin</p>
                        </div>
                        <div class="text-center">
                            <p class="text-xs text-emerald-500 uppercase tracking-wide">Commission Pool</p>
                            <p class="text-sm font-bold text-emerald-700 tabular-nums mt-0.5" x-text="'₱' + Math.round((form.added_amount||0)*0.70).toLocaleString()"></p>
                            <p class="text-xs text-emerald-400">70% margin</p>
                        </div>
                    </div>
                </div>

                <p x-show="formError" class="text-xs text-red-600" x-text="formError"></p>
                <div class="flex justify-end gap-3 pt-1">
                    <button @click="showAdd = false; resetForm()" class="btn-secondary">Cancel</button>
                    <button @click="if(!saving){saving=true;addRecord()}" :disabled="saving" class="btn-primary" x-text="saving ? 'Creating deal…' : 'Create Deal'"></button>
                </div>
            </div>

            </div>{{-- end x-show="!showSuccessState" --}}
        </div>
    </div>

    {{-- ── Delete Instructions Modal ─────────────────────────────── --}}
    <div :style="showDeleteInstructions ? 'display:flex' : 'display:none'"
         class="fixed inset-0 bg-black/50 z-[9999] items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6 text-center"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">
            <div class="w-14 h-14 rounded-2xl bg-red-50 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </div>
            <h3 class="text-[#1E1B4B] font-bold text-lg mb-2">Delete Deals</h3>
            <p class="text-gray-500 text-sm mb-6 leading-relaxed">
                Tick the checkbox next to each deal you want to delete.<br>
                A <strong>Delete</strong> button will appear once you've made your selection.
            </p>
            <div class="flex gap-3">
                <button @click="showDeleteInstructions = false"
                        class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button @click="showDeleteInstructions = false; selectMode = true"
                        class="flex-1 py-2.5 rounded-xl bg-red-600 text-white text-sm font-semibold hover:bg-red-700 transition-colors">
                    Start Selecting
                </button>
            </div>
        </div>
    </div>

    {{-- Floating Delete Bar is rendered outside Alpine — see below --}}

    {{-- ── Delete Confirmation Modal ────────────────────────────────── --}}
    <div :style="showDeleteConfirm ? 'display:flex' : 'display:none'"
         class="fixed inset-0 bg-black/50 z-[9999] items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6 text-center"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">
            <div class="w-12 h-12 rounded-2xl bg-red-100 flex items-center justify-center mx-auto mb-4">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </div>
            <h3 class="text-[#1E1B4B] font-bold text-lg mb-2">Delete Deals?</h3>
            <p class="text-gray-500 text-sm mb-1">You are about to permanently delete</p>
            <p class="text-red-600 font-bold text-lg mb-4" x-text="selectedDeals.length + ' deal' + (selectedDeals.length !== 1 ? 's' : '')"></p>
            <p class="text-gray-400 text-xs mb-6">This action cannot be undone. All deal history and commission data will be removed.</p>
            <div class="flex gap-3">
                <button @click="showDeleteConfirm=false"
                        class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button @click="deleteSelected()"
                        :disabled="deleting"
                        class="flex-1 py-2.5 rounded-xl bg-red-600 text-white text-sm font-semibold hover:bg-red-700 transition-colors inline-flex items-center justify-center gap-1.5">
                    <svg x-show="deleting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                    <span x-text="deleting ? 'Deleting…' : 'Yes, Delete'"></span>
                </button>
            </div>
        </div>
    </div>

</div>

<script>
// Philippine municipalities by province — used for the deal creation form dropdown
const PH_MUNICIPALITIES = @json(\App\Support\PhilippineMunicipalities::all());

// LGU IDS pricing — range-based % of deal amount (mirrors LguIdsPricingService::RANGES)
// ₱0–₱6M → 60% | ₱6M+–₱12M → 58% | ₱12M+–₱15M → 48% | ₱15M+ → 41%
function lguBaseCost(dv) {
    if (dv <= 6_000_000)  return Math.round(dv * 0.60);
    if (dv <= 12_000_000) return Math.round(dv * 0.58);
    if (dv <= 15_000_000) return Math.round(dv * 0.48);
    return Math.round(dv * 0.41);
}

function dealsModule(tenantId, showLocation, canViewReferrers = true) {
    return {
        leads: [], filtered: [], loading: true,
        canViewReferrers,
        showLocation,
        viewMode: 'table',
        search: '', filterStage: '', filterStatus: '', filterCommission: '', filterProvince: '', filterReseller: '',
        sortCol: 'created_at', sortDir: 'desc',
        showAdd: false, saving: false, formError: '', nameAutoFilled: false,
        showSuccessState: false, createdDeal: null,
        selectMode: false, selectedDeals: [], deleting: false, showDeleteConfirm: false, showDeleteInstructions: false,
        municipalityOptions: [],
        // Referrer combobox
        activatedReferrers: [], loadingReferrers: false, manualReferrer: false,
        referrerQuery: '', referrerOpen: false, referrerSelected: null, referrerFocusIdx: -1, referrerLoadError: '',
        form: { name: '', stage: 'introduction', deal_value: 0, base_cost: 0, added_amount: 0, reseller_name: '', reseller_email: '', province: '', municipality: '' },

        stages: [
            { key: 'introduction',  label: 'Introduction',  color: '#9CA3AF' },
            { key: 'presentation',  label: 'Presentation',  color: '#3B82F6' },
            { key: 'contract_sent', label: 'Contract Sent', color: '#F59E0B' },
            { key: 'signed',        label: 'Signed',        color: '#8B5CF6' },
            { key: 'paid',          label: 'Paid',          color: '#10B981' },
        ],

        async init() {
            // Drive the standalone floating delete bar via $watch
            this.$watch('selectMode', (val) => {
                const bar = document.getElementById('rb-del-bar');
                if (bar) bar.style.display = val ? 'flex' : 'none';
                if (!val) rbDelBarCount(0);
            });

            // Pre-filter from URL params (e.g. from expiry alert notifications)
            const urlParams    = new URLSearchParams(window.location.search);
            const preStatus    = urlParams.get('status');
            const preReseller  = urlParams.get('reseller_name');
            if (['expiring', 'expired', 'active'].includes(preStatus)) {
                this.filterStatus = preStatus;
            }
            if (preReseller) {
                this.filterReseller = decodeURIComponent(preReseller);
            }
            try {
                const res  = await fetch(`/api/leads?tenant_id=${tenantId}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                this.leads = Array.isArray(data) ? data : (data.data || []);
            } catch(e) { this.leads = []; }
            this.applyFilters();
            this.loading = false;
        },

        applyFilters() {
            const q = this.search.toLowerCase();
            this.filtered = this.leads.filter(l => {
                const matchQ  = !q || (l.name||'').toLowerCase().includes(q)
                                   || (this.canViewReferrers && (l.reseller_name||'').toLowerCase().includes(q))
                                   || (l.data?.province||'').toLowerCase().includes(q)
                                   || (l.data?.municipality||'').toLowerCase().includes(q);
                const matchSt = !this.filterStage      || l.stage             === this.filterStage;
                const matchSx = !this.filterStatus     || l.status            === this.filterStatus;
                const matchCo = !this.filterCommission || l.commission_status  === this.filterCommission;
                const matchPr = !this.filterProvince   || (l.data?.province||'') === this.filterProvince;
                const matchRs = !this.filterReseller   || (l.reseller_name||'') === this.filterReseller;
                return matchQ && matchSt && matchSx && matchCo && matchPr && matchRs;
            });
        },

        sort(col) {
            if (this.sortCol === col) {
                this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortCol = col;
                // Sensible default direction per column type
                const descByDefault = ['value', 'created_at'];
                const ascByDefault  = ['name', 'reseller', 'stage', 'commission', 'status', 'days_left'];
                this.sortDir = descByDefault.includes(col) ? 'desc' : 'asc';
            }
        },

        sortedFiltered() {
            const stageOrder   = { introduction:0, presentation:1, contract_sent:2, signed:3, paid:4 };
            const commOrder    = { pending:0, locked:1, paid:2 };
            const statusOrder  = { active:0, expiring:1, expired:2, reassigned:3, declined:3 };

            return [...this.filtered].sort((a, b) => {
                let va, vb;
                switch (this.sortCol) {
                    case 'name':
                        va = (a.name||'').toLowerCase();
                        vb = (b.name||'').toLowerCase();
                        break;
                    case 'stage':
                        va = stageOrder[a.stage] ?? 99;
                        vb = stageOrder[b.stage] ?? 99;
                        break;
                    case 'reseller':
                        va = (a.reseller_name||'').toLowerCase();
                        vb = (b.reseller_name||'').toLowerCase();
                        break;
                    case 'value':
                        va = Number(a.deal_value) || 0;
                        vb = Number(b.deal_value) || 0;
                        break;
                    case 'created_at':
                        va = new Date(a.created_at || 0).getTime();
                        vb = new Date(b.created_at || 0).getTime();
                        break;
                    case 'days_left':
                        va = a.days_left ?? 21;
                        vb = b.days_left ?? 21;
                        break;
                    case 'status':
                        va = statusOrder[a.status] ?? 99;
                        vb = statusOrder[b.status] ?? 99;
                        break;
                    default: // created_at
                        va = new Date(a.created_at || 0).getTime();
                        vb = new Date(b.created_at || 0).getTime();
                }
                if (va < vb) return this.sortDir === 'asc' ? -1 : 1;
                if (va > vb) return this.sortDir === 'asc' ?  1 : -1;
                return 0;
            });
        },

        deriveName() {
            // Only auto-fill the deal name when BOTH province and municipality are selected
            if (this.form.province && this.form.municipality) {
                if (this.nameAutoFilled || !this.form.name) {
                    this.form.name = this.form.municipality + ', ' + this.form.province;
                    this.nameAutoFilled = true;
                }
            }
        },

        leadsInStage(stage) { return this.leads.filter(l => l.stage === stage); },

        totalValue() {
            const t = Math.round(this.filtered.reduce((s, l) => s + (Number(l.deal_value) || 0), 0));
            return t.toLocaleString('en');
        },

        stageLabel(s) {
            const m = { introduction:'Intro', presentation:'Presentation', contract_sent:'Contract', signed:'Signed', paid:'Paid' };
            return m[s] || s;
        },

        stageBadge(s) {
            const m = { introduction:'badge badge-gray', presentation:'badge badge-blue', contract_sent:'badge badge-orange', signed:'badge badge-purple', paid:'badge badge-green' };
            return m[s] || 'badge badge-gray';
        },

        commissionBadge(s) {
            const m = { pending:'badge badge-gray', locked:'badge badge-orange', paid:'badge badge-green' };
            return m[s] || 'badge badge-gray';
        },

        daysClass(d) {
            const n = d ?? 21;
            if (n <= 0)  return 'text-red-600 font-bold text-xs tabular-nums';
            if (n <= 7)  return 'text-orange-500 font-semibold text-xs tabular-nums';
            return 'text-gray-500 text-xs tabular-nums';
        },

        formatValue(v) {
            const n = Math.round(Number(v) || 0);
            return n > 0 ? '₱' + n.toLocaleString('en') : '—';
        },

        toggleDeal(id) {
            const idx = this.selectedDeals.indexOf(id);
            if (idx === -1) this.selectedDeals.push(id);
            else            this.selectedDeals.splice(idx, 1);
            rbDelBarCount(this.selectedDeals.length);
        },

        toggleSelectAll() {
            const ids = this.sortedFiltered().map(l => l.id);
            this.selectedDeals = this.selectedDeals.length === ids.length ? [] : ids;
        },

        async deleteSelected() {
            if (!this.selectedDeals.length || this.deleting) return;
            this.deleting = true;
            const csrf = document.querySelector('meta[name=csrf-token]').content;
            let failed = 0;
            for (const id of this.selectedDeals) {
                try {
                    await fetch(`/api/leads/${id}`, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                } catch { failed++; }
            }
            this.leads = this.leads.filter(l => !this.selectedDeals.includes(l.id));
            this.selectedDeals = [];
            this.selectMode     = false;
            this.showDeleteConfirm = false;
            this.deleting       = false;
            this.applyFilters();
            if (failed > 0) this.$dispatch('show-toast', { type: 'error', message: `${failed} deal(s) could not be deleted.` });
            else            this.$dispatch('show-toast', { type: 'success', message: 'Selected deals deleted.' });
        },

        viewDeal(id) { if (this.selectMode) { this.toggleDeal(id); return; } window.location.href = `/tenant/${tenantId}/deals/${id}`; },

        // ── Referrer combobox ─────────────────────────────────────────────────

        async searchReferrers() {
            this.loadingReferrers = true;
            this.referrerLoadError = '';
            try {
                const q   = encodeURIComponent(this.referrerQuery || '');
                const res = await fetch(`/api/resellers/activated-options?tenant_id=${tenantId}&search=${q}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) throw new Error('Server error ' + res.status);
                const data = await res.json();
                this.activatedReferrers = Array.isArray(data) ? data : [];
                this.referrerFocusIdx   = -1;
            } catch(e) {
                this.referrerLoadError  = 'Unable to load referrers. Try again.';
                this.activatedReferrers = [];
            }
            this.loadingReferrers = false;
        },

        selectReferrer(r) {
            this.referrerSelected       = r;
            this.form.reseller_name     = r.name;
            this.form.reseller_email    = '';   // no invite for dropdown selections
            this.referrerOpen           = false;
            this.referrerQuery          = r.name;
        },

        clearReferrerSelection() {
            this.referrerSelected    = null;
            this.form.reseller_name  = '';
            this.form.reseller_email = '';
            this.referrerQuery       = '';
            this.referrerOpen        = false;
            this.referrerFocusIdx    = -1;
        },

        referrerFocusNext() {
            if (!this.referrerOpen) { this.referrerOpen = true; return; }
            this.referrerFocusIdx = Math.min(this.referrerFocusIdx + 1, this.activatedReferrers.length - 1);
        },

        referrerFocusPrev() {
            this.referrerFocusIdx = Math.max(this.referrerFocusIdx - 1, -1);
        },

        referrerSelectFocused() {
            if (this.referrerFocusIdx >= 0 && this.referrerFocusIdx < this.activatedReferrers.length) {
                this.selectReferrer(this.activatedReferrers[this.referrerFocusIdx]);
            }
        },

        // ── LGU IDS deal value ↔ cost parts sync ─────────────────────────────
        //
        // Deal Value is the primary field.
        // Changing Deal Value → Base Cost auto-fills from tier % → Added Amount = DV − BC
        // Changing Base Cost manually → Added Amount = Deal Value − Base Cost (DV stays)
        // Changing Added Amount manually → Base Cost = Deal Value − Added Amount (DV stays)
        // All values remain editable — these are smart defaults, not locked rules.

        syncFromDealValue() {
            const dv = Number(this.form.deal_value) || 0;
            if (dv <= 0) return;
            const bc = lguBaseCost(dv);
            this.form.base_cost    = bc;
            this.form.added_amount = Math.round(dv - bc);
        },

        syncFromBaseCost() {
            // User manually adjusted Base Cost — recalculate Added Amount to keep total = Deal Value
            const dv = Number(this.form.deal_value)  || 0;
            const bc = Number(this.form.base_cost)   || 0;
            if (dv > 0) {
                this.form.added_amount = Math.round(dv - bc);
            }
        },

        syncFromAddedAmount() {
            // User manually adjusted Added Amount — recalculate Base Cost to keep total = Deal Value
            const dv = Number(this.form.deal_value)    || 0;
            const aa = Number(this.form.added_amount)  || 0;
            if (dv > 0) {
                this.form.base_cost = Math.round(dv - aa);
            }
        },

        isConsistent() {
            const dv = Number(this.form.deal_value)   || 0;
            const bc = Number(this.form.base_cost)    || 0;
            const aa = Number(this.form.added_amount) || 0;
            if (dv === 0 || (bc === 0 && aa === 0)) return true;
            return Math.abs(dv - (bc + aa)) < 1;
        },

        isNonStandardTier() {
            return false; // All amounts are valid in the range-based system
        },

        resetForm() {
            this.form = { name:'', stage:'introduction', deal_value: showLocation ? 4000000 : 0, base_cost:0, added_amount:0, reseller_name:'', reseller_email:'', province:'', municipality:'' };
            this.formError      = '';
            this.nameAutoFilled = false;
            this.municipalityOptions = [];
            this.manualReferrer      = false;
            this.referrerSelected    = null;
            this.referrerQuery       = '';
            this.referrerOpen        = false;
            this.referrerFocusIdx    = -1;
            this.referrerLoadError   = '';
            if (showLocation) this.syncFromDealValue();
        },

        async addRecord() {
            if (showLocation && !this.form.province)     { this.formError = 'Province is required.'; this.saving = false; return; }
            if (showLocation && !this.form.municipality) { this.formError = 'Municipality / City is required.'; this.saving = false; return; }
            if (!this.form.name) { this.formError = 'Deal name is required.'; this.saving = false; return; }
            if (!this.manualReferrer && !this.referrerSelected) {
                this.formError = 'Please select a referrer from the list, or use "Add manually".'; this.saving = false; return;
            }
            if (this.manualReferrer && !this.form.reseller_name)  { this.formError = 'Referrer name is required.'; this.saving = false; return; }
            if (this.manualReferrer && !this.form.reseller_email) { this.formError = 'Referrer email is required when entering manually.'; this.saving = false; return; }
            if (!this.isConsistent()) { this.formError = 'Deal Value must equal Base Cost + Added Amount. Please review the amounts.'; this.saving = false; return; }
            this.saving = true; this.formError = '';
            try {
                const bc = Number(this.form.base_cost)    || 0;
                const aa = Number(this.form.added_amount) || 0;
                const dv = Number(this.form.deal_value)   || (bc + aa) || (showLocation ? 4000000 : 0);
                const payload = {
                    ...this.form,
                    base_cost:        bc,
                    added_amount:     aa,
                    deal_value:       dv,
                    tenant_id:        tenantId,
                    new_reseller_email: this.manualReferrer ? this.form.reseller_email : null,
                    data: { province: this.form.province, municipality: this.form.municipality },
                };
                const res = await fetch('/api/leads', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(payload),
                });
                const lead = await res.json();
                if (lead.id) {
                    this.leads.unshift(lead);
                    this.applyFilters();
                    // Show in-modal success state instead of silently closing
                    this.createdDeal = lead;
                    this.showSuccessState = true;
                    this.saving = false;
                } else {
                    this.formError = lead.message || 'Failed to create deal.';
                    this.$dispatch('show-toast', { type: 'error', message: lead.message || 'Failed to create deal.' });
                }
            } catch(e) { this.formError = 'Network error. Please try again.'; this.$dispatch('show-toast', { type: 'error', message: 'Network error. Please try again.' }); }
            finally { this.saving = false; }
        },
    }
}

// ── Standalone Delete Bar JS (completely outside Alpine) ─────────────────────
function rbDelBarCount(n) {
    const label = document.getElementById('rb-del-label');
    const btn   = document.getElementById('rb-del-btn');
    if (label) label.textContent = n === 0
        ? 'Tap a deal row to select it'
        : n + ' deal' + (n !== 1 ? 's' : '') + ' selected';
    if (btn) {
        btn.textContent = n > 0 ? 'Delete (' + n + ')' : 'Delete';
        btn.style.background = n > 0 ? '#dc2626' : '#9ca3af';
        btn.style.cursor     = n > 0 ? 'pointer'  : 'not-allowed';
    }
}
window.rbDelBarCount = rbDelBarCount;
</script>

{{-- ── Standalone Floating Delete Bar (pure HTML, no Alpine) ─────────────── --}}
<div id="rb-del-bar"
     style="display:none;position:fixed;bottom:24px;left:0;right:0;z-index:9001;justify-content:center;padding:0 16px;pointer-events:none">
    <div style="display:flex;align-items:center;gap:12px;padding:16px 20px;border-radius:18px;background:white;box-shadow:0 8px 40px rgba(0,0,0,0.2);border:1.5px solid #e5e7eb;pointer-events:auto">
        <div style="flex:1;min-width:0">
            <p id="rb-del-label" style="font-size:14px;font-weight:600;color:#1E1B4B;margin:0;white-space:nowrap">Tap a deal row to select it</p>
            <p style="font-size:11px;color:#9ca3af;margin:2px 0 0">Tap again to deselect · Cancel to exit</p>
        </div>
        <button onclick="window.dispatchEvent(new CustomEvent('rb-del-cancel'))"
                style="font-size:12px;font-weight:600;color:#6b7280;background:none;border:none;cursor:pointer;padding:8px 14px;border-radius:10px;white-space:nowrap;transition:background .15s"
                onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='transparent'">
            Cancel
        </button>
        <button id="rb-del-btn"
                onclick="window.dispatchEvent(new CustomEvent('rb-del-execute'))"
                style="display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border-radius:12px;background:#9ca3af;color:white;border:none;font-size:13px;font-weight:700;cursor:not-allowed;white-space:nowrap;transition:background .15s">
            <svg style="width:14px;height:14px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            Delete
        </button>
    </div>
</div>

@endsection


