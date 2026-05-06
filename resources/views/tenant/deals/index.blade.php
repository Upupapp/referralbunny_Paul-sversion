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
    <button x-data @click="$dispatch('open-add-deal')" class="btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        <span class="hidden sm:inline">New Deal</span>
    </button>
@endsection

@section('content')
<div class="space-y-5"
     x-data="dealsModule('{{ $tenant->id }}', {{ $showLocation ? 'true' : 'false' }}, {{ $canViewReferrers ? 'true' : 'false' }})"
     x-init="init()"
     @open-add-deal.window="showAdd = true">

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
            <button x-show="filterStage || filterStatus || filterCommission || filterProvince || search"
                    @click="filterStage=''; filterStatus=''; filterCommission=''; filterProvince=''; filterReseller=''; search=''; applyFilters()"
                    class="filter-pill !border-red-200 !text-red-500 hover:!bg-red-50">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                Clear
            </button>
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
                    <span x-text="{name:'Deal',stage:'Stage',reseller:'Referrer',value:'Value',commission:'Commission',days_left:'Days Left',status:'Status'}[sortCol] || sortCol"></span>
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
                            ['key'=>'commission', 'label'=>'Commission', 'tip'=>'Sort by commission status',              'hidden'=>'hidden md:table-cell'],
                            ['key'=>'days_left',  'label'=>'Days Left',  'tip'=>'Sort most urgent (fewest days) first',   'hidden'=>'hidden md:table-cell'],
                            ['key'=>'status',     'label'=>'Status',     'tip'=>'Sort by deal status',                    'hidden'=>''],
                        ];
                        @endphp
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
                                <button x-show="leads.length === 0" @click="showAdd = true" class="btn-primary mt-3 text-sm">Add First Deal</button>
                            </td>
                        </tr>
                    </template>
                    <template x-for="lead in sortedFiltered()" :key="lead.id">
                        <tr class="table-row cursor-pointer" @click="viewDeal(lead.id)">
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
                                <span :class="commissionBadge(lead.commission_status)" x-text="(lead.commission_status || 'pending').charAt(0).toUpperCase() + (lead.commission_status || 'pending').slice(1)"></span>
                            </td>
                            <td class="hidden md:table-cell">
                                <span :class="daysClass(lead.days_left)" x-text="(lead.days_left ?? 21) + 'd'"></span>
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
                <h3 class="font-semibold text-[#1E1B4B]">New Deal</h3>
                <button @click="showAdd = false; resetForm()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
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
                        {{ $leadLabel }} Name *
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

                <div>
                    <label class="form-label">Referrer Name *</label>
                    <input type="text" x-model="form.reseller_name" class="form-input" placeholder="Assigned referrer">
                </div>

                {{-- Financial inputs --}}
                <div class="space-y-3 pt-1">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Financial Breakdown</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Base Cost (₱)</label>
                            <input type="number" x-model.number="form.base_cost" class="form-input" placeholder="0" min="0" step="100">
                            <p class="text-xs text-gray-400 mt-1">Delivery cost</p>
                        </div>
                        <div>
                            <label class="form-label">Added Amount (₱)</label>
                            <input type="number" x-model.number="form.added_amount" class="form-input" placeholder="0" min="0" step="100">
                            <p class="text-xs text-gray-400 mt-1">Markup / margin</p>
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
                    <button @click="addRecord()" :disabled="saving" class="btn-primary" x-text="saving ? 'Saving…' : 'Add Deal'"></button>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
// Philippine municipalities by province — used for the deal creation form dropdown
const PH_MUNICIPALITIES = @json(\App\Support\PhilippineMunicipalities::all());

function dealsModule(tenantId, showLocation, canViewReferrers = true) {
    return {
        leads: [], filtered: [], loading: true,
        canViewReferrers,
        viewMode: 'table',
        search: '', filterStage: '', filterStatus: '', filterCommission: '', filterProvince: '', filterReseller: '',
        sortCol: 'created_at', sortDir: 'desc',
        showAdd: false, saving: false, formError: '', nameAutoFilled: false,
        municipalityOptions: [],
        form: { name: '', stage: 'introduction', base_cost: 0, added_amount: 0, reseller_name: '', province: '', municipality: '' },

        stages: [
            { key: 'introduction',  label: 'Introduction',  color: '#9CA3AF' },
            { key: 'presentation',  label: 'Presentation',  color: '#3B82F6' },
            { key: 'contract_sent', label: 'Contract Sent', color: '#F59E0B' },
            { key: 'signed',        label: 'Signed',        color: '#8B5CF6' },
            { key: 'paid',          label: 'Paid',          color: '#10B981' },
        ],

        async init() {
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
                const res = await fetch(`/api/leads?tenant_id=${tenantId}`);
                this.leads = Array.isArray(await res.clone().json()) ? await res.json() : [];
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
                    case 'commission':
                        va = commOrder[a.commission_status] ?? 99;
                        vb = commOrder[b.commission_status] ?? 99;
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
            const t = this.filtered.reduce((s, l) => s + (Number(l.deal_value) || 0), 0);
            if (t >= 1000000) return (t/1000000).toFixed(1) + 'M';
            if (t >= 1000)    return Math.round(t/1000) + 'K';
            return t.toLocaleString();
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
            if (n >= 1000000) return '₱' + (n/1000000).toFixed(1) + 'M';
            if (n >= 1000)    return '₱' + Math.round(n/1000) + 'K';
            return n > 0 ? '₱' + n.toLocaleString('en') : '—';
        },

        viewDeal(id) { window.location.href = `/tenant/${tenantId}/deals/${id}`; },

        resetForm() {
            this.form = { name:'', stage:'introduction', base_cost:0, added_amount:0, reseller_name:'', province:'', municipality:'' };
            this.formError = '';
            this.nameAutoFilled = false;
            this.municipalityOptions = [];
        },

        async addRecord() {
            if (showLocation && !this.form.province)     { this.formError = 'Province is required.'; return; }
            if (showLocation && !this.form.municipality) { this.formError = 'Municipality / City is required.'; return; }
            if (!this.form.name)          { this.formError = 'Deal name is required.'; return; }
            if (!this.form.reseller_name) { this.formError = 'Referrer name is required.'; return; }
            this.saving = true; this.formError = '';
            try {
                const bc = Number(this.form.base_cost)    || 0;
                const aa = Number(this.form.added_amount) || 0;
                const res = await fetch('/api/leads', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ ...this.form, base_cost: bc, added_amount: aa, deal_value: bc + aa, tenant_id: tenantId, data: { province: this.form.province, municipality: this.form.municipality } }),
                });
                const lead = await res.json();
                if (lead.id) {
                    this.leads.unshift(lead);
                    this.applyFilters();
                    this.showAdd = false;
                    this.resetForm();
                    this.$dispatch('show-toast', { type: 'success', message: 'Deal added successfully.' });
                } else {
                    this.formError = lead.message || 'Failed to create deal.';
                    this.$dispatch('show-toast', { type: 'error', message: lead.message || 'Failed to create deal.' });
                }
            } catch(e) { this.formError = 'Network error. Please try again.'; this.$dispatch('show-toast', { type: 'error', message: 'Network error. Please try again.' }); }
            finally { this.saving = false; }
        },
    }
}
</script>
@endsection


