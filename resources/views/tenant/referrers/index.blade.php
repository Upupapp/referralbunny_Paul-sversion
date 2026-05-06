@extends('layouts.app')
@section('title', 'Referrers')
@section('nav') @include('tenant._nav') @endsection

@section('topbar-actions')
    <button onclick="rbInviteOpen()" class="btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
        <span class="hidden sm:inline">Invite Referrer</span>
    </button>
@endsection

@section('content')
<div class="space-y-5"
     x-data="referrersModule('{{ $tenant->id }}')"
     x-init="init()">

    {{-- Summary KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-3 sm:gap-4">
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Total Referrers</span>
                <p class="text-2xl font-bold text-[#1E1B4B] mt-1.5" x-text="referrers.length"></p>
            </div>
            <div class="kpi-icon bg-purple-100 ml-3 shrink-0"><svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/></svg></div>
        </div>
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Active</span>
                <p class="text-2xl font-bold text-[#1E1B4B] mt-1.5" x-text="referrers.filter(r => r.status === 'active' || r.status === 'nda_signed').length"></p>
            </div>
            <div class="kpi-icon bg-emerald-100 ml-3 shrink-0"><svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
        </div>
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Pending Invites</span>
                <p class="text-2xl font-bold text-orange-500 mt-1.5" x-text="referrers.filter(r => r.status === 'invited').length"></p>
            </div>
            <div class="kpi-icon bg-orange-100 ml-3 shrink-0"><svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg></div>
        </div>
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Missing Agreements</span>
                <p class="text-2xl font-bold mt-1.5"
                   :class="nonCompliantCount() > 0 ? 'text-orange-500' : 'text-[#1E1B4B]'"
                   x-text="totalRequiredAgreements > 0 ? nonCompliantCount() : '—'"></p>
            </div>
            <div class="kpi-icon bg-orange-100 ml-3 shrink-0"><svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></div>
        </div>
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Missing Docs</span>
                <p class="text-2xl font-bold mt-1.5"
                   :class="docNonCompliantCount() > 0 ? 'text-red-500' : 'text-[#1E1B4B]'"
                   x-text="totalRequiredDocs > 0 ? docNonCompliantCount() : '—'"></p>
            </div>
            <div class="kpi-icon bg-red-100 ml-3 shrink-0"><svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0"/></svg></div>
        </div>
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide flex items-center gap-1">Total Closed @if($showLocation ?? false)<x-tax-tip />@endif</span>
                <p class="text-2xl font-bold text-[#1E1B4B] mt-1.5" x-text="'&#8369;' + totalClosed()"></p>
            </div>
            <div class="kpi-icon bg-blue-100 ml-3 shrink-0"><svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
        </div>
    </div>

    {{-- Referrer breakdown info bar --}}
    <div x-show="referrers.length > 0" class="card !py-2.5 !px-4">
        <div class="flex items-center gap-2 text-xs text-gray-500 flex-wrap">
            <svg class="w-3.5 h-3.5 text-[#7B61FF] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>
                <strong x-text="referrers.length" class="text-[#1E1B4B]"></strong> total referrers:
                <span x-text="referrers.filter(r=>r.status==='active'||r.status==='nda_signed').length" class="text-emerald-600 font-medium"></span> active ·
                <span x-text="referrers.filter(r=>r.status==='invited').length" class="text-orange-500 font-medium"></span> pending ·
                <span x-text="referrers.filter(r=>!r.email).length" class="text-red-500 font-medium"></span> need details
            </span>
            <span class="text-gray-300">|</span>
            <span class="text-gray-400">Showing all statuses by default. Use filters to narrow down.</span>
        </div>
    </div>

    {{-- Filter bar --}}
    <div class="card space-y-3">
        <div class="search-group">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" x-model="search" @input.debounce.250ms="applyFilters()" placeholder="Search referrers…">
            <button x-show="search.length > 0" @click="search = ''; applyFilters()"
                    class="text-gray-400 hover:text-gray-600 transition-colors shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="filter-bar">
            <label class="filter-pill" :class="filterStatus !== '' ? 'active' : ''">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <select x-model="filterStatus" @change="applyFilters()">
                    <option value="">All Status</option>
                    <option value="invited">Invited</option>
                    <option value="active">Active</option>
                    <option value="nda_signed">NDA Signed</option>
                </select>
                <template x-if="filterStatus === 'invited'">
                    <span class="ml-1 text-orange-500 font-bold" x-text="`(${referrers.filter(r=>r.status==='invited').length})`"></span>
                </template>
                <template x-if="filterStatus === 'active'">
                    <span class="ml-1 text-emerald-600 font-bold" x-text="`(${referrers.filter(r=>r.status==='active').length})`"></span>
                </template>
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>
            <label x-show="totalRequiredAgreements > 0" class="filter-pill" :class="filterAgreement !== '' ? 'active' : ''">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <select x-model="filterAgreement" @change="applyFilters()">
                    <option value="">All Agreements</option>
                    <option value="compliant">Fully Signed</option>
                    <option value="missing">Missing Agreements</option>
                </select>
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>
            <label x-show="totalRequiredDocs > 0" class="filter-pill" :class="filterDoc !== '' ? 'active' : ''">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0"/></svg>
                <select x-model="filterDoc" @change="applyFilters()">
                    <option value="">All Doc Status</option>
                    <option value="compliant">Docs Complete</option>
                    <option value="missing">Missing Docs</option>
                </select>
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>
            <button x-show="filterStatus || filterAgreement || filterDoc || search"
                    @click="filterStatus=''; filterAgreement=''; filterDoc=''; search=''; applyFilters()"
                    class="filter-pill !border-red-200 !text-red-500 hover:!bg-red-50">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                Clear
            </button>
        </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="card p-0 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
            <p class="text-sm font-semibold text-[#1E1B4B]">
                <span x-text="filtered.length"></span> referrers
                <span x-show="filterStatus || search || filterAgreement" class="text-gray-400 font-normal text-xs ml-1">— filtered</span>
            </p>
            <div x-show="totalRequiredAgreements > 0 && nonCompliantCount() > 0"
                 class="flex items-center gap-1.5 text-xs text-orange-600 bg-orange-50 px-3 py-1.5 rounded-full">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span x-text="nonCompliantCount() + ' reseller' + (nonCompliantCount() === 1 ? '' : 's') + ' have unsigned agreements'"></span>
            </div>
        </div>

        {{-- Loading --}}
        <div x-show="loading" class="flex items-center justify-center py-10 gap-3 text-gray-400">
            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            <span class="text-sm">Loading referrers…</span>
        </div>

        <div x-show="!loading" class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="table-head">
                        <th>Referrer</th>
                        <th class="hidden md:table-cell">Territory</th>
                        <th>Deals</th>
                        <th class="flex items-center gap-1">Closed Value @if($showLocation ?? false)<x-tax-tip />@endif</th>
                        <th class="hidden md:table-cell">Performance</th>
                        <th>Status</th>
                        <th class="hidden lg:table-cell" x-show="totalRequiredAgreements > 0">Agreements</th>
                        <th class="hidden lg:table-cell" x-show="totalRequiredDocs > 0">Documents</th>
                        <th class="hidden lg:table-cell">Joined</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="filtered.length === 0 && !loading">
                        <tr>
                            <td colspan="9" class="py-16 text-center">
                                <div class="flex justify-center text-gray-300 mb-3">
                                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/></svg>
                                </div>
                                <template x-if="referrers.length === 0">
                                    <div>
                                        <p class="text-gray-400 text-sm">No referrers yet. Invite your first referrer.</p>
                                        <button onclick="rbInviteOpen()" class="btn-primary mt-3 text-sm">Invite First Referrer</button>
                                    </div>
                                </template>
                                <template x-if="referrers.length > 0">
                                    <div>
                                        <p class="text-gray-400 text-sm">No referrers match the current filters.</p>
                                        <button @click="filterStatus=''; filterAgreement=''; filterDoc=''; search=''; applyFilters()"
                                                class="mt-2 text-sm text-[#7B61FF] hover:underline font-medium">
                                            Clear filters to see all <span x-text="referrers.length"></span> referrers
                                        </button>
                                    </div>
                                </template>
                            </td>
                        </tr>
                    </template>
                    <template x-for="r in filtered" :key="r.id">
                        <tr class="table-row">
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                                         :class="r.is_anonymous ? 'bg-gray-100 text-gray-400' : 'bg-purple-100 text-purple-700'"
                                         x-text="r.is_anonymous ? '🔒' : (r.name||'?').slice(0,2).toUpperCase()"></div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <p class="font-medium text-[#1E1B4B] truncate" x-text="r.name"></p>
                                            <span :class="{
                                                'badge badge-green':  ['active','nda_signed'].includes(r.status),
                                                'badge badge-orange': r.status === 'invited',
                                                'badge badge-gray':   !['active','nda_signed','invited'].includes(r.status)
                                            }" class="text-[10px] shrink-0 hidden sm:inline-flex"
                                            x-text="r.status === 'nda_signed' ? 'NDA Signed' : r.status === 'invited' ? 'Pending' : 'Active'"></span>
                                            <span x-show="r.is_anonymous"
                                                  class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-semibold bg-gray-100 text-gray-500 shrink-0">
                                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/></svg>
                                                anonymous
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-400 truncate" x-text="r.email"></p>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden md:table-cell text-gray-500 text-sm" x-text="r.territory || '—'"></td>
                            <td class="text-sm tabular-nums">
                                <span class="font-semibold text-[#1E1B4B]" x-text="r.assigned_leads || 0"></span>
                            </td>
                            <td class="font-semibold text-[#1E1B4B] tabular-nums" x-text="formatValue(r.closed_value)"></td>
                            <td class="hidden md:table-cell">
                                <div class="flex items-center gap-2 min-w-24">
                                    <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full transition-all"
                                             :class="(r.performance_score||0) >= 70 ? 'bg-emerald-500' : (r.performance_score||0) >= 40 ? 'bg-orange-400' : 'bg-gray-300'"
                                             :style="'width:' + Math.min(r.performance_score||0, 100) + '%'"></div>
                                    </div>
                                    <span class="text-xs tabular-nums text-gray-500" x-text="(r.performance_score||0) + '%'"></span>
                                </div>
                            </td>
                            <td>
                                <span :class="{
                                    'badge badge-gray':  r.status === 'invited',
                                    'badge badge-green': r.status === 'active',
                                    'badge badge-blue':  r.status === 'nda_signed',
                                }" x-text="r.status === 'nda_signed' ? 'NDA Signed' : r.status ? r.status.charAt(0).toUpperCase()+r.status.slice(1) : '—'"></span>
                            </td>

                            {{-- Agreements column (only shown when agreements exist) --}}
                            <td class="hidden lg:table-cell" x-show="totalRequiredAgreements > 0">
                                <button @click="openAgreements(r)"
                                        class="flex items-center gap-1.5 group/agr"
                                        :title="agreementTooltip(r.id)">
                                    <template x-if="isCompliant(r.id)">
                                        <span class="badge badge-green text-xs py-0.5">
                                            <svg class="w-3 h-3 mr-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                            All signed
                                        </span>
                                    </template>
                                    <template x-if="!isCompliant(r.id)">
                                        <span class="badge badge-orange text-xs py-0.5">
                                            <svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                            <span x-text="agreementSummary(r.id)"></span>
                                        </span>
                                    </template>
                                </button>
                            </td>

                            {{-- Documents column --}}
                            <td class="hidden lg:table-cell" x-show="totalRequiredDocs > 0">
                                <button @click="openDocuments(r)"
                                        class="flex items-center gap-1.5 group/doc"
                                        :title="docTooltip(r.id)">
                                    <template x-if="isDocCompliant(r.id)">
                                        <span class="badge badge-green text-xs py-0.5">
                                            <svg class="w-3 h-3 mr-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                            Complete
                                        </span>
                                    </template>
                                    <template x-if="!isDocCompliant(r.id)">
                                        <span class="badge badge-red text-xs py-0.5">
                                            <svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                            <span x-text="docSummary(r.id)"></span>
                                        </span>
                                    </template>
                                </button>
                            </td>

                            <td class="hidden lg:table-cell text-gray-400 text-sm tabular-nums"
                                x-text="r.joined_date ? new Date(r.joined_date).toLocaleDateString('en',{month:'short',day:'numeric',year:'numeric'}) : '—'"></td>
                            <td>
                                <div class="flex items-center gap-1.5 justify-end">
                                    <button x-show="r.status === 'invited'" @click="updateStatus(r.id, 'active')"
                                            class="px-2.5 py-1.5 rounded-lg text-xs font-medium bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition-colors">
                                        Activate
                                    </button>
                                    <button x-show="r.status === 'active'" @click="updateStatus(r.id, 'nda_signed')"
                                            class="px-2.5 py-1.5 rounded-lg text-xs font-medium bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors">
                                        Mark NDA
                                    </button>
                                    <button x-show="totalRequiredDocs > 0" @click="openDocuments(r)"
                                            class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 hover:text-red-500 transition-colors"
                                            title="Manage documents">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0"/></svg>
                                    </button>
                                    <button x-show="totalRequiredAgreements > 0" @click="openAgreements(r)"
                                            class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 hover:text-[#7B61FF] transition-colors"
                                            title="Manage agreements">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </button>
                                    {{-- Anonymity toggle — tenant admin only --}}
                                    <button @click="toggleAnonymous(r)"
                                            :title="r.is_anonymous ? 'Remove anonymity' : 'Make anonymous'"
                                            :class="r.is_anonymous ? 'bg-gray-100 text-gray-600 hover:bg-red-50 hover:text-red-500' : 'text-gray-400 hover:bg-gray-100 hover:text-gray-600'"
                                            class="p-1.5 rounded-lg transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Document Management Modal --}}
    <div x-show="showDocuments" x-cloak
         class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4"
         @keydown.escape.window="showDocuments = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div>
                    <h3 class="font-semibold text-[#1E1B4B]">Required Documents</h3>
                    <p class="text-xs text-gray-400 mt-0.5" x-text="activeDocReseller?.name"></p>
                </div>
                <button @click="showDocuments = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6">
                <div x-show="loadingDocs" class="flex items-center justify-center py-8 gap-2 text-gray-400">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span class="text-sm">Loading…</span>
                </div>
                <div x-show="!loadingDocs" class="space-y-3">
                    <template x-for="d in activeResellerDocs" :key="d.id">
                        <div class="p-4 rounded-xl border transition-colors"
                             :class="{
                                 'border-emerald-100 bg-emerald-50/40': d.submission_status === 'approved',
                                 'border-blue-100 bg-blue-50/40':       d.submission_status === 'submitted',
                                 'border-red-100 bg-red-50/40':         d.submission_status === 'rejected',
                                 'border-gray-100':                      !d.submission_status || d.submission_status === 'not_submitted',
                             }">
                            <div class="flex items-start gap-3">
                                {{-- Status icon --}}
                                <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 mt-0.5"
                                     :class="{
                                         'bg-emerald-100': d.submission_status === 'approved',
                                         'bg-blue-100':    d.submission_status === 'submitted',
                                         'bg-red-100':     d.submission_status === 'rejected',
                                         'bg-gray-100':    !d.submission_status || d.submission_status === 'not_submitted',
                                     }">
                                    <template x-if="d.submission_status === 'approved'">
                                        <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    </template>
                                    <template x-if="d.submission_status === 'submitted'">
                                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </template>
                                    <template x-if="d.submission_status === 'rejected'">
                                        <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </template>
                                    <template x-if="!d.submission_status || d.submission_status === 'not_submitted'">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                    </template>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-semibold text-[#1E1B4B]" x-text="d.label"></p>
                                        <span x-show="d.is_required" class="badge badge-red text-xs py-0.5 px-1.5">Required</span>
                                        <span x-show="!d.is_required" class="badge badge-gray text-xs py-0.5 px-1.5">Optional</span>
                                    </div>
                                    <p x-show="d.description" class="text-xs text-gray-400 mt-0.5" x-text="d.description"></p>

                                    {{-- Status text --}}
                                    <template x-if="d.submission_status === 'approved'">
                                        <p class="text-xs text-emerald-600 font-medium mt-1">
                                            Approved <span x-text="d.reviewed_at ? '— ' + new Date(d.reviewed_at).toLocaleDateString('en',{month:'short',day:'numeric'}) : ''"></span>
                                        </p>
                                    </template>
                                    <template x-if="d.submission_status === 'submitted'">
                                        <p class="text-xs text-blue-600 mt-1">Submitted — awaiting review</p>
                                    </template>
                                    <template x-if="d.submission_status === 'rejected'">
                                        <div class="mt-1">
                                            <p class="text-xs text-red-500 font-medium">Rejected — reseller must resubmit</p>
                                            <p x-show="d.review_notes" class="text-xs text-red-400 mt-0.5" x-text="d.review_notes"></p>
                                        </div>
                                    </template>
                                    <template x-if="!d.submission_status || d.submission_status === 'not_submitted'">
                                        <p class="text-xs mt-1" :class="d.is_required ? 'text-gray-500' : 'text-gray-400'"
                                           x-text="d.is_required ? 'Not yet submitted — required' : 'Not yet submitted (optional)'"></p>
                                    </template>

                                    <a x-show="d.file_url" :href="d.file_url" target="_blank"
                                       class="text-xs text-purple-600 hover:text-purple-700 inline-flex items-center gap-1 mt-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        View File
                                    </a>
                                </div>
                                {{-- Actions --}}
                                <div class="shrink-0 flex flex-col gap-1.5 items-end">
                                    <template x-if="!d.submission_status || d.submission_status === 'not_submitted'">
                                        <button @click="markSubmitted(d)" :disabled="docSaving === d.id"
                                                class="btn-secondary text-xs py-1.5 px-2.5"
                                                x-text="docSaving === d.id ? '…' : 'Mark Submitted'"></button>
                                    </template>
                                    <template x-if="d.submission_status === 'submitted'">
                                        <div class="flex gap-1.5">
                                            <button @click="approveDoc(d)" :disabled="docSaving === d.id"
                                                    class="btn-primary text-xs py-1.5 px-2.5"
                                                    x-text="docSaving === d.id ? '…' : 'Approve'"></button>
                                            <button @click="rejectDoc(d)" :disabled="docSaving === d.id"
                                                    class="text-xs py-1.5 px-2.5 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition-colors"
                                                    x-text="docSaving === d.id ? '…' : 'Reject'"></button>
                                        </div>
                                    </template>
                                    <template x-if="d.submission_status === 'approved' || d.submission_status === 'rejected'">
                                        <button @click="resetDocSubmission(d)" :disabled="docSaving === d.id"
                                                class="text-xs text-gray-400 hover:text-red-500 underline transition-colors"
                                                x-text="docSaving === d.id ? '…' : 'Reset'"></button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                    <div x-show="activeResellerDocs.length === 0" class="text-center py-6 text-gray-400 text-sm">
                        No required documents configured for this tenant.
                    </div>
                </div>
                <div class="flex justify-end mt-5 pt-4 border-t border-gray-100">
                    <button @click="showDocuments = false" class="btn-secondary">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Agreement Management Modal --}}
    <div x-show="showAgreements" x-cloak
         class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4"
         @keydown.escape.window="showAgreements = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div>
                    <h3 class="font-semibold text-[#1E1B4B]">Agreement Status</h3>
                    <p class="text-xs text-gray-400 mt-0.5" x-text="activeReseller?.name"></p>
                </div>
                <button @click="showAgreements = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="p-6">
                {{-- Loading agreements --}}
                <div x-show="loadingAgreements" class="flex items-center justify-center py-8 gap-2 text-gray-400">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <span class="text-sm">Loading…</span>
                </div>

                <div x-show="!loadingAgreements" class="space-y-3">
                    <template x-for="a in activeResellerAgreements" :key="a.id">
                        <div class="flex items-start gap-4 p-4 rounded-xl border transition-colors"
                             :class="a.agreed_at ? 'border-emerald-100 bg-emerald-50/40' : (a.is_required ? 'border-orange-100 bg-orange-50/40' : 'border-gray-100')">
                            <div class="shrink-0 mt-0.5">
                                <template x-if="a.agreed_at">
                                    <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center">
                                        <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                </template>
                                <template x-if="!a.agreed_at">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                         :class="a.is_required ? 'bg-orange-100' : 'bg-gray-100'">
                                        <svg class="w-4 h-4" :class="a.is_required ? 'text-orange-500' : 'text-gray-400'"
                                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>
                                    </div>
                                </template>
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <p class="text-sm font-semibold text-[#1E1B4B]" x-text="a.label"></p>
                                    <span x-show="a.is_required" class="badge badge-red text-xs py-0.5 px-1.5">Required</span>
                                    <span x-show="!a.is_required" class="badge badge-gray text-xs py-0.5 px-1.5">Optional</span>
                                </div>
                                <p x-show="a.description" class="text-xs text-gray-400 mt-0.5" x-text="a.description"></p>

                                <template x-if="a.agreed_at">
                                    <div class="mt-1.5">
                                        <p class="text-xs text-emerald-600 font-medium">
                                            Signed
                                            <span x-text="new Date(a.agreed_at).toLocaleDateString('en', {month:'short',day:'numeric',year:'numeric'})"></span>
                                        </p>
                                        <p x-show="a.agreed_by_name" class="text-xs text-gray-400" x-text="'by ' + a.agreed_by_name"></p>
                                        <p x-show="a.version" class="text-xs text-gray-400" x-text="'Version ' + a.version"></p>
                                    </div>
                                </template>

                                <template x-if="!a.agreed_at">
                                    <p class="text-xs mt-1" :class="a.is_required ? 'text-orange-500' : 'text-gray-400'"
                                       x-text="a.is_required ? 'Not yet signed — required before referring deals' : 'Not yet signed (optional)'"></p>
                                </template>

                                <a x-show="a.file_url" :href="a.file_url" target="_blank"
                                   class="text-xs text-purple-600 hover:text-purple-700 inline-flex items-center gap-1 mt-1.5 transition-colors">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                    View Document
                                </a>
                            </div>

                            <div class="shrink-0 self-center">
                                <button x-show="!a.agreed_at" @click="markAcknowledged(a)"
                                        :disabled="ackSaving === a.id"
                                        class="btn-primary text-xs py-1.5 px-3"
                                        x-text="ackSaving === a.id ? 'Saving…' : 'Mark Signed'">
                                </button>
                                <button x-show="a.agreed_at" @click="revokeAcknowledged(a)"
                                        :disabled="ackSaving === a.id"
                                        class="text-xs text-gray-400 hover:text-red-500 transition-colors underline"
                                        x-text="ackSaving === a.id ? '…' : 'Revoke'">
                                </button>
                            </div>
                        </div>
                    </template>

                    <div x-show="activeResellerAgreements.length === 0" class="text-center py-6 text-gray-400 text-sm">
                        No agreements configured for this tenant yet.
                    </div>
                </div>

                <div class="flex justify-end mt-5 pt-4 border-t border-gray-100">
                    <button @click="showAgreements = false" class="btn-secondary">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Invite Modal: pure vanilla JS, zero Alpine dependency --}}

</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.store('referrersInvite', { show: false });

    Alpine.store('anonConfirm', {
        open:      false,
        enabling:  true,
        name:      '',
        reseller:  null,
        saving:    false,
        step:      1,        // 1 = review, 2 = type-to-confirm
        typeInput: '',

        get expectedWord() {
            if (this.enabling) {
                // Must type the referrer's first name (case-insensitive)
                return (this.name || '').split(' ')[0].toUpperCase();
            }
            return 'REMOVE';
        },

        get canConfirm() {
            return this.typeInput.trim().toUpperCase() === this.expectedWord;
        },

        show(reseller) {
            this.reseller  = reseller;
            this.enabling  = !reseller.is_anonymous;
            this.name      = reseller.name;
            this.saving    = false;
            this.step      = 1;
            this.typeInput = '';
            this.open      = true;
        },

        next() { this.step = 2; this.$nextTick?.(() => document.getElementById('anonTypeInput')?.focus()); },

        cancel() {
            this.open      = false;
            this.reseller  = null;
            this.step      = 1;
            this.typeInput = '';
        },

        async confirm() {
            if (!this.reseller || !this.canConfirm) return;
            this.saving = true;
            try {
                await fetch(`/api/resellers/${this.reseller.id}`, {
                    method:  'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body:    JSON.stringify({ is_anonymous: this.enabling }),
                });
                this.reseller.is_anonymous = this.enabling;
            } finally {
                this.saving    = false;
                this.open      = false;
                this.reseller  = null;
                this.step      = 1;
                this.typeInput = '';
            }
        },
    });
});

function referrersModule(tenantId) {
    return {
        referrers: [], filtered: [], loading: true,
        search: '', filterStatus: '', filterAgreement: '', filterDoc: '',
        saving: false, formError: '',
        form: { name: '', email: '', phone: '', territory: '' },
        summary: { total: 0, active: 0, invited: 0, no_email: 0, no_password: 0 },

        // Agreement data
        totalRequiredAgreements: 0,
        resellerCompliance: {},
        showAgreements: false,
        activeReseller: null,
        activeResellerAgreements: [],
        loadingAgreements: false,
        ackSaving: null,

        // Document data
        totalRequiredDocs: 0,
        resellerDocCompliance: {},
        showDocuments: false,
        activeDocReseller: null,
        activeResellerDocs: [],
        loadingDocs: false,
        docSaving: null,

        async init() {
            // Listen for successful invite from the pure-JS modal
            window.addEventListener('referrer-invited', (e) => {
                this.referrers.unshift(e.detail);
                this.applyFilters();
            });

            try {
                const res = await fetch(`/api/resellers?tenant_id=${tenantId}`);
                const data = await res.json();
                this.referrers = Array.isArray(data) ? data : (data.data || []);
            } catch(e) { this.referrers = []; }

            // Fetch summary counts (status breakdown from server)
            try {
                const sumRes = await fetch(`/api/resellers/summary?tenant_id=${tenantId}`);
                if (sumRes.ok) { this.summary = await sumRes.json(); }
            } catch(e) {}

            // Load compliance data in parallel
            await Promise.all([
                this.refreshCompliance(),
                this.refreshDocCompliance(),
            ]);

            this.applyFilters();
            this.loading = false;
        },

        applyFilters() {
            const q = this.search.toLowerCase();
            this.filtered = this.referrers.filter(r => {
                const matchQ  = !q || (r.name||'').toLowerCase().includes(q) || (r.email||'').toLowerCase().includes(q) || (r.territory||'').toLowerCase().includes(q);
                const matchSt = !this.filterStatus || r.status === this.filterStatus;
                const matchAg = !this.filterAgreement
                    || (this.filterAgreement === 'compliant' && this.isCompliant(r.id))
                    || (this.filterAgreement === 'missing'   && !this.isCompliant(r.id));
                const matchDc = !this.filterDoc
                    || (this.filterDoc === 'compliant' && this.isDocCompliant(r.id))
                    || (this.filterDoc === 'missing'   && !this.isDocCompliant(r.id));
                return matchQ && matchSt && matchAg && matchDc;
            });
        },

        // ── Anonymity ─────────────────────────────────────────────────────
        showAnonConfirm: false,
        anonConfirmReseller: null,
        anonSaving: false,

        toggleAnonymous(reseller) {
            Alpine.store('anonConfirm').show(reseller);
        },

        // ── Agreement helpers ─────────────────────────────────────────────

        isCompliant(resellerId) {
            if (this.totalRequiredAgreements === 0) return true;
            const c = this.resellerCompliance[resellerId];
            return c ? c.fully_compliant : false;
        },

        agreementSummary(resellerId) {
            const c = this.resellerCompliance[resellerId];
            if (!c) return `0/${this.totalRequiredAgreements}`;
            return `${c.acknowledged}/${c.required_total}`;
        },

        agreementTooltip(resellerId) {
            if (this.isCompliant(resellerId)) return 'All required agreements signed';
            const c = this.resellerCompliance[resellerId];
            const missing = c ? (c.required_total - c.acknowledged) : this.totalRequiredAgreements;
            return `${missing} required agreement${missing === 1 ? '' : 's'} not yet signed`;
        },

        nonCompliantCount() {
            if (this.totalRequiredAgreements === 0) return 0;
            return this.referrers.filter(r => !this.isCompliant(r.id)).length;
        },

        // ── Agreement modal ───────────────────────────────────────────────

        async openAgreements(reseller) {
            this.activeReseller           = reseller;
            this.activeResellerAgreements = [];
            this.showAgreements           = true;
            this.loadingAgreements        = true;

            try {
                const res  = await fetch(`/api/agreements/reseller-status?tenant_id=${tenantId}&reseller_id=${reseller.id}`);
                const data = await res.json();
                this.activeResellerAgreements = Array.isArray(data) ? data : [];
            } catch(e) {
                this.activeResellerAgreements = [];
            }
            this.loadingAgreements = false;
        },

        async markAcknowledged(agreement) {
            this.ackSaving = agreement.id;
            try {
                const res  = await fetch(`/api/agreements/${agreement.id}/acknowledge`, {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({
                        reseller_id:    this.activeReseller.id,
                        agreed_by_name: this.activeReseller.name + ' (recorded by admin)',
                    }),
                });
                const data = await res.json();
                if (data.acknowledged) {
                    const a = this.activeResellerAgreements.find(a => a.id === agreement.id);
                    if (a) { a.agreed_at = data.agreed_at; a.agreed_by_name = this.activeReseller.name + ' (recorded by admin)'; }
                    await this.refreshCompliance();
                    this.$dispatch('show-toast', { type: 'success', message: `${agreement.label} marked as signed.` });
                }
            } catch(e) {
                this.$dispatch('show-toast', { type: 'error', message: 'Failed to record agreement.' });
            }
            this.ackSaving = null;
        },

        async revokeAcknowledged(agreement) {
            if (!confirm(`Revoke the signed status for "${agreement.label}"? This will mark it as unsigned again.`)) return;
            this.ackSaving = agreement.id;
            try {
                await fetch(`/api/agreements/${agreement.id}/acknowledge?reseller_id=${this.activeReseller.id}`, {
                    method:  'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                });
                const a = this.activeResellerAgreements.find(a => a.id === agreement.id);
                if (a) { a.agreed_at = null; a.agreed_by_name = null; }
                await this.refreshCompliance();
                this.$dispatch('show-toast', { type: 'success', message: `${agreement.label} signature revoked.` });
            } catch(e) {
                this.$dispatch('show-toast', { type: 'error', message: 'Failed to revoke agreement.' });
            }
            this.ackSaving = null;
        },

        async refreshCompliance() {
            try {
                const cr  = await fetch(`/api/agreements/compliance?tenant_id=${tenantId}`);
                const cd  = await cr.json();
                this.totalRequiredAgreements = cd.required_agreements || 0;
                const map = {};
                (cd.resellers || []).forEach(r => { map[r.reseller_id] = r; });
                this.resellerCompliance = map;
                this.applyFilters();
            } catch(e) {}
        },

        // ── Document helpers ─────────────────────────────────────────────

        async refreshDocCompliance() {
            try {
                const res = await fetch(`/api/required-documents/compliance?tenant_id=${tenantId}`);
                const data = await res.json();
                this.totalRequiredDocs = data.required_documents || 0;
                const map = {};
                (data.resellers || []).forEach(r => { map[r.reseller_id] = r; });
                this.resellerDocCompliance = map;
                this.applyFilters();
            } catch(e) {}
        },

        isDocCompliant(resellerId) {
            if (this.totalRequiredDocs === 0) return true;
            const c = this.resellerDocCompliance[resellerId];
            return c ? c.fully_compliant : false;
        },

        docSummary(resellerId) {
            const c = this.resellerDocCompliance[resellerId];
            if (!c) return `0/${this.totalRequiredDocs}`;
            return `${c.approved}/${c.required_total}`;
        },

        docTooltip(resellerId) {
            if (this.isDocCompliant(resellerId)) return 'All required documents approved';
            const c = this.resellerDocCompliance[resellerId];
            const missing = c ? (c.required_total - c.approved) : this.totalRequiredDocs;
            return `${missing} required document${missing === 1 ? '' : 's'} pending`;
        },

        docNonCompliantCount() {
            if (this.totalRequiredDocs === 0) return 0;
            return this.referrers.filter(r => !this.isDocCompliant(r.id)).length;
        },

        async openDocuments(reseller) {
            this.activeDocReseller  = reseller;
            this.activeResellerDocs = [];
            this.showDocuments      = true;
            this.loadingDocs        = true;
            try {
                const res  = await fetch(`/api/required-documents/reseller-status?tenant_id=${tenantId}&reseller_id=${reseller.id}`);
                this.activeResellerDocs = await res.json();
            } catch(e) { this.activeResellerDocs = []; }
            this.loadingDocs = false;
        },

        async markSubmitted(doc) {
            const fileUrl = prompt('Paste file URL (optional — leave blank if submitting manually):') ?? '';
            this.docSaving = doc.id;
            try {
                const res = await fetch(`/api/required-documents/${doc.id}/submit`, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ reseller_id: this.activeDocReseller.id, file_url: fileUrl || null }),
                });
                if ((await res.json()).submitted) {
                    doc.submission_status = 'submitted';
                    doc.file_url = fileUrl || null;
                    doc.submitted_at = new Date().toISOString();
                    await this.refreshDocCompliance();
                    this.$dispatch('show-toast', { type: 'success', message: `${doc.label} marked as submitted.` });
                }
            } catch(e) { this.$dispatch('show-toast', { type: 'error', message: 'Failed to record submission.' }); }
            this.docSaving = null;
        },

        async approveDoc(doc) {
            if (!doc.submission_id) return;
            this.docSaving = doc.id;
            try {
                const res = await fetch(`/api/document-submissions/${doc.submission_id}/review`, {
                    method:  'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ status: 'approved', reviewed_by: 'Admin' }),
                });
                if ((await res.json()).reviewed) {
                    doc.submission_status = 'approved';
                    doc.reviewed_at = new Date().toISOString();
                    await this.refreshDocCompliance();
                    this.$dispatch('show-toast', { type: 'success', message: `${doc.label} approved.` });
                }
            } catch(e) { this.$dispatch('show-toast', { type: 'error', message: 'Failed to approve.' }); }
            this.docSaving = null;
        },

        async rejectDoc(doc) {
            const notes = prompt('Reason for rejection (shown to reseller):') ?? '';
            if (!doc.submission_id) return;
            this.docSaving = doc.id;
            try {
                const res = await fetch(`/api/document-submissions/${doc.submission_id}/review`, {
                    method:  'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ status: 'rejected', reviewed_by: 'Admin', review_notes: notes || null }),
                });
                if ((await res.json()).reviewed) {
                    doc.submission_status = 'rejected';
                    doc.review_notes = notes;
                    await this.refreshDocCompliance();
                    this.$dispatch('show-toast', { type: 'success', message: `${doc.label} rejected.` });
                }
            } catch(e) { this.$dispatch('show-toast', { type: 'error', message: 'Failed to reject.' }); }
            this.docSaving = null;
        },

        async resetDocSubmission(doc) {
            if (!confirm(`Reset submission for "${doc.label}"? The reseller will need to resubmit.`)) return;
            if (!doc.submission_id) return;
            this.docSaving = doc.id;
            try {
                await fetch(`/api/document-submissions/${doc.submission_id}`, {
                    method:  'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                });
                doc.submission_status = 'not_submitted';
                doc.submission_id     = null;
                doc.file_url          = null;
                doc.reviewed_at       = null;
                doc.review_notes      = null;
                await this.refreshDocCompliance();
                this.$dispatch('show-toast', { type: 'success', message: `${doc.label} submission reset.` });
            } catch(e) { this.$dispatch('show-toast', { type: 'error', message: 'Failed to reset.' }); }
            this.docSaving = null;
        },

        // ── KPIs ──────────────────────────────────────────────────────────

        totalClosed() {
            const t = this.referrers.reduce((s, r) => s + (Number(r.closed_value) || 0), 0);
            if (t >= 1000000) return (t/1000000).toFixed(1) + 'M';
            if (t >= 1000)    return Math.round(t/1000) + 'K';
            return t.toLocaleString();
        },

        formatValue(v) {
            const n = Math.round(Number(v) || 0);
            if (n >= 1000000) return '&#8369;' + (n/1000000).toFixed(1) + 'M';
            if (n >= 1000)    return '&#8369;' + Math.round(n/1000) + 'K';
            return n > 0 ? '&#8369;' + n.toLocaleString('en') : '—';
        },

        // ── Status update ─────────────────────────────────────────────────

        async updateStatus(id, status) {
            try {
                await fetch(`/api/resellers/${id}`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ status }),
                });
                const r = this.referrers.find(r => r.id === id);
                if (r) { r.status = status; this.applyFilters(); }
            } catch(e) {}
        },

        resetForm() { this.form = { name:'', email:'', phone:'', territory:'' }; this.formError = ''; },

        _setInviteError(msg) {
            this.formError = msg;
            const el = document.getElementById('referrer-invite-error');
            if (el) { el.textContent = msg; el.style.display = msg ? 'block' : 'none'; }
        },

        async invite() {
            this._setInviteError('');
            if (!this.form.name)  { this._setInviteError('Name is required.'); return; }
            if (!this.form.email) { this._setInviteError('Email is required.'); return; }
            this.saving = true;
            try {
                const res = await fetch('/api/resellers', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ ...this.form, tenant_id: tenantId, status: 'invited' }),
                });
                const reseller = await res.json();
                if (reseller.id) {
                    this.referrers.unshift(reseller);
                    this.applyFilters();
                    Alpine.store('referrersInvite').show = false;
                    this.resetForm();
                    document.getElementById('referrer-invite-form')?.reset();
                    window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'success', message: 'Referrer invited successfully.' }}));
                } else {
                    const msg = reseller.message || reseller.errors?.email?.[0] || 'Failed to invite referrer.';
                    this._setInviteError(msg);
                    window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'error', message: msg }}));
                }
            } catch(e) {
                this._setInviteError('Network error. Please try again.');
                window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'error', message: 'Network error. Please try again.' }}));
            }
            finally { this.saving = false; }
        },
    };
}
</script>

{{-- ── ANONYMITY CONFIRMATION MODAL (two-step) ─────────── --}}
<div x-data x-show="$store.anonConfirm.open" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     style="background:rgba(0,0,0,0.65);backdrop-filter:blur(4px);"
     @keydown.escape.window="$store.anonConfirm.cancel()">

    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden" @click.stop
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100">

        {{-- Coloured accent bar at top --}}
        <div class="h-1.5 w-full"
             :class="$store.anonConfirm.enabling ? 'bg-gray-800' : 'bg-amber-400'"></div>

        {{-- Step indicator --}}
        <div class="flex items-center justify-between px-6 pt-4 pb-0">
            <div class="flex items-center gap-2">
                <span class="w-5 h-5 rounded-full text-[10px] font-bold flex items-center justify-center"
                      :class="$store.anonConfirm.step === 1 ? 'bg-[#7B61FF] text-white' : 'bg-gray-200 text-gray-500'">1</span>
                <div class="w-8 h-px bg-gray-200"></div>
                <span class="w-5 h-5 rounded-full text-[10px] font-bold flex items-center justify-center"
                      :class="$store.anonConfirm.step === 2 ? 'bg-[#7B61FF] text-white' : 'bg-gray-200 text-gray-500'">2</span>
            </div>
            <span class="text-[10px] text-gray-400 font-medium"
                  x-text="'Step ' + $store.anonConfirm.step + ' of 2'"></span>
        </div>

        {{-- ── STEP 1: Review ─────────────────────────────── --}}
        <div x-show="$store.anonConfirm.step === 1">

            {{-- Header --}}
            <div class="px-6 pt-4 pb-4">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0"
                         :class="$store.anonConfirm.enabling ? 'bg-gray-100' : 'bg-amber-100'">
                        <svg class="w-5 h-5" :class="$store.anonConfirm.enabling ? 'text-gray-700' : 'text-amber-600'"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <template x-if="$store.anonConfirm.enabling">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/>
                            </template>
                            <template x-if="!$store.anonConfirm.enabling">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </template>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-[#1E1B4B] text-base"
                            x-text="$store.anonConfirm.enabling ? 'Enable Anonymous Mode' : 'Remove Anonymous Mode'"></h3>
                        <p class="text-sm text-gray-500"
                           x-text="'For: ' + $store.anonConfirm.name"></p>
                    </div>
                </div>
            </div>

            <div class="px-6 pb-5 space-y-4">

                {{-- Enable block --}}
                <template x-if="$store.anonConfirm.enabling">
                    <div class="space-y-3">
                        <p class="text-sm text-gray-700 leading-relaxed">
                            Enabling anonymous mode will <strong>immediately hide this referrer's identity</strong> from other referrers across the platform.
                        </p>
                        <div class="rounded-xl border border-gray-200 divide-y divide-gray-100 overflow-hidden text-xs">
                            <div class="flex items-start gap-3 px-4 py-3 bg-gray-50">
                                <svg class="w-4 h-4 text-gray-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7"/></svg>
                                <p class="text-gray-600">Name shown as <strong class="text-gray-800">"Anonymous Referrer"</strong> to other referrers and partners</p>
                            </div>
                            <div class="flex items-start gap-3 px-4 py-3 bg-gray-50">
                                <svg class="w-4 h-4 text-gray-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                <p class="text-gray-600">Email, phone, and photo hidden from all unauthorised viewers</p>
                            </div>
                            <div class="flex items-start gap-3 px-4 py-3 bg-emerald-50">
                                <svg class="w-4 h-4 text-emerald-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                <p class="text-emerald-800"><strong>You always see full details</strong> — this only affects other referrers</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 px-3 py-2.5 rounded-xl bg-gray-900 text-white text-xs">
                            <svg class="w-4 h-4 shrink-0 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <p>You'll need to type this referrer's first name on the next screen to confirm.</p>
                        </div>
                    </div>
                </template>

                {{-- Remove block --}}
                <template x-if="!$store.anonConfirm.enabling">
                    <div class="space-y-3">
                        <p class="text-sm text-gray-700 leading-relaxed">
                            Removing anonymous mode will <strong>immediately reveal this referrer's identity</strong> to other referrers on leaderboards, rankings, and shared screens.
                        </p>
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 space-y-2.5 text-xs">
                            <div class="flex items-start gap-2.5">
                                <svg class="w-4 h-4 text-amber-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                <p class="text-amber-800">Real name, photo, and profile will become visible to all other referrers immediately</p>
                            </div>
                            <div class="flex items-start gap-2.5">
                                <svg class="w-4 h-4 text-amber-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                <p class="text-amber-800">This referrer has chosen to remain anonymous — confirm they want their identity revealed before proceeding</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 px-3 py-2.5 rounded-xl bg-amber-600 text-white text-xs">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <p>You'll need to type <strong>REMOVE</strong> on the next screen to confirm.</p>
                        </div>
                    </div>
                </template>

                {{-- Step 1 Actions --}}
                <div class="flex gap-3 pt-1">
                    <button @click="$store.anonConfirm.cancel()"
                            class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button @click="$store.anonConfirm.next()"
                            :class="$store.anonConfirm.enabling
                                ? 'bg-gray-800 hover:bg-gray-900 text-white'
                                : 'bg-amber-500 hover:bg-amber-600 text-white'"
                            class="flex-1 py-2.5 rounded-xl text-sm font-semibold transition-colors flex items-center justify-center gap-2">
                        <span x-text="$store.anonConfirm.enabling ? 'I understand — Continue' : 'I understand — Continue'"></span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- ── STEP 2: Type to confirm ────────────────────── --}}
        <div x-show="$store.anonConfirm.step === 2">

            <div class="px-6 pt-5 pb-6 space-y-4">

                {{-- What's happening reminder --}}
                <div class="flex items-center gap-3 p-3.5 rounded-xl border"
                     :class="$store.anonConfirm.enabling ? 'bg-gray-50 border-gray-200' : 'bg-amber-50 border-amber-200'">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0"
                         :class="$store.anonConfirm.enabling ? 'bg-gray-200' : 'bg-amber-200'">
                        <svg class="w-4 h-4" :class="$store.anonConfirm.enabling ? 'text-gray-700' : 'text-amber-700'"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <template x-if="$store.anonConfirm.enabling">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/>
                            </template>
                            <template x-if="!$store.anonConfirm.enabling">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </template>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold"
                           :class="$store.anonConfirm.enabling ? 'text-gray-800' : 'text-amber-800'"
                           x-text="$store.anonConfirm.enabling ? 'Enabling anonymity for:' : 'Removing anonymity for:'"></p>
                        <p class="text-sm font-bold text-[#1E1B4B] truncate" x-text="$store.anonConfirm.name"></p>
                    </div>
                </div>

                {{-- Type to confirm --}}
                <div>
                    <label class="block text-sm font-semibold text-[#1E1B4B] mb-1">
                        <template x-if="$store.anonConfirm.enabling">
                            <span>Type <span class="font-mono bg-gray-100 px-1.5 py-0.5 rounded text-sm" x-text="$store.anonConfirm.expectedWord"></span> to confirm</span>
                        </template>
                        <template x-if="!$store.anonConfirm.enabling">
                            <span>Type <span class="font-mono bg-amber-100 text-amber-800 px-1.5 py-0.5 rounded text-sm">REMOVE</span> to confirm</span>
                        </template>
                    </label>
                    <p class="text-xs text-gray-400 mb-2"
                       x-text="$store.anonConfirm.enabling
                           ? 'Enter the referrer\'s first name exactly as shown above.'
                           : 'This confirms you want to remove anonymity protection.'"></p>
                    <input id="anonTypeInput"
                           x-model="$store.anonConfirm.typeInput"
                           type="text"
                           autocomplete="off"
                           spellcheck="false"
                           @keydown.enter="$store.anonConfirm.canConfirm && !$store.anonConfirm.saving && $store.anonConfirm.confirm()"
                           :placeholder="$store.anonConfirm.expectedWord"
                           :class="$store.anonConfirm.typeInput
                               ? ($store.anonConfirm.canConfirm ? 'border-emerald-400 ring-2 ring-emerald-100 bg-emerald-50' : 'border-red-300 ring-2 ring-red-100')
                               : 'border-gray-200'"
                           class="w-full px-4 py-3 rounded-xl border text-sm font-mono font-semibold tracking-widest uppercase outline-none transition-all">
                    {{-- Match indicator --}}
                    <div class="flex items-center gap-1.5 mt-2 min-h-[1.25rem]">
                        <template x-if="$store.anonConfirm.typeInput && $store.anonConfirm.canConfirm">
                            <span class="flex items-center gap-1 text-emerald-600 text-xs font-medium">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                Confirmed — you may proceed
                            </span>
                        </template>
                        <template x-if="$store.anonConfirm.typeInput && !$store.anonConfirm.canConfirm">
                            <span class="flex items-center gap-1 text-red-500 text-xs">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                <span x-text="'Doesn\'t match — expected \'' + $store.anonConfirm.expectedWord + '\''"></span>
                            </span>
                        </template>
                    </div>
                </div>

                {{-- Step 2 Actions --}}
                <div class="flex gap-3 pt-1">
                    <button @click="$store.anonConfirm.step = 1; $store.anonConfirm.typeInput = ''"
                            class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors flex items-center justify-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        Back
                    </button>
                    <button @click="$store.anonConfirm.confirm()"
                            :disabled="!$store.anonConfirm.canConfirm || $store.anonConfirm.saving"
                            :class="$store.anonConfirm.enabling
                                ? 'bg-gray-800 hover:bg-gray-900 text-white disabled:bg-gray-300 disabled:cursor-not-allowed'
                                : 'bg-amber-500 hover:bg-amber-600 text-white disabled:bg-amber-200 disabled:cursor-not-allowed'"
                            class="flex-1 py-2.5 rounded-xl text-sm font-semibold transition-colors">
                        <span x-text="$store.anonConfirm.saving ? 'Saving…'
                            : $store.anonConfirm.enabling ? 'Enable Anonymous Mode'
                            : 'Remove Anonymous Mode'"></span>
                    </button>
                </div>

                <p class="text-center text-xs text-gray-400">
                    Changed your mind?
                    <button @click="$store.anonConfirm.cancel()" class="text-[#7B61FF] hover:underline">Cancel entirely</button>
                </p>
            </div>
        </div>

    </div>
</div>

{{-- ── Pure-JS Invite Referrer Modal ─────────────────────────────────────── --}}
<div id="rb-invite-overlay"
     style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;z-index:9000;background:rgba(0,0,0,0.65);align-items:center;justify-content:center;padding:1rem"
     onclick="if(event.target===this)rbInviteClose()">
    <div style="background:#fff;border-radius:1rem;width:100%;max-width:440px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.3)"
         onclick="event.stopPropagation()">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:20px 24px;border-bottom:1px solid #f3f4f6">
            <h3 style="margin:0;font-size:15px;font-weight:600;color:#1E1B4B">Invite Referrer</h3>
            <button onclick="rbInviteClose()" style="background:none;border:none;cursor:pointer;padding:4px;color:#9ca3af;line-height:0">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form id="rb-invite-form" onsubmit="rbInviteSubmit(event)" style="padding:24px">
            <input type="hidden" id="rb-invite-tenant" value="{{ $tenant->id ?? '' }}">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
                <div>
                    <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:4px">Full Name *</label>
                    <input type="text" id="rb-inv-name" class="form-input" placeholder="Juan dela Cruz">
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:4px">Email *</label>
                    <input type="email" id="rb-inv-email" class="form-input" placeholder="juan@email.com">
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:4px">Phone</label>
                    <input type="text" id="rb-inv-phone" class="form-input" placeholder="+63 9XX XXX XXXX">
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:4px">Territory</label>
                    <input type="text" id="rb-inv-territory" class="form-input" placeholder="e.g. Metro Manila">
                </div>
            </div>
            <p id="rb-invite-error" style="display:none;color:#dc2626;font-size:12px;margin:0 0 12px 0"></p>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" onclick="rbInviteClose()"
                        style="padding:8px 18px;background:#fff;color:#374151;border:1px solid #e5e7eb;border-radius:10px;font-size:14px;font-weight:500;cursor:pointer;font-family:inherit">
                    Cancel
                </button>
                <button type="submit" id="rb-invite-btn"
                        style="padding:8px 18px;background:#FF5733;color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit">
                    Send Invitation
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function rbInviteOpen() {
    document.getElementById('rb-invite-form').reset();
    document.getElementById('rb-invite-error').style.display = 'none';
    document.getElementById('rb-invite-btn').textContent = 'Send Invitation';
    document.getElementById('rb-invite-btn').disabled = false;
    var o = document.getElementById('rb-invite-overlay');
    o.style.display = 'flex';
    setTimeout(function(){ document.getElementById('rb-inv-name').focus(); }, 50);
}

function rbInviteClose() {
    document.getElementById('rb-invite-overlay').style.display = 'none';
    document.getElementById('rb-invite-form').reset();
    document.getElementById('rb-invite-error').style.display = 'none';
}

async function rbInviteSubmit(e) {
    e.preventDefault();
    var name      = document.getElementById('rb-inv-name').value.trim();
    var email     = document.getElementById('rb-inv-email').value.trim();
    var phone     = document.getElementById('rb-inv-phone').value.trim();
    var territory = document.getElementById('rb-inv-territory').value.trim();
    var tenantId  = document.getElementById('rb-invite-tenant').value;
    var errEl     = document.getElementById('rb-invite-error');
    var btn       = document.getElementById('rb-invite-btn');

    errEl.style.display = 'none';
    if (!name)  { errEl.textContent = 'Full name is required.';  errEl.style.display = 'block'; return; }
    if (!email) { errEl.textContent = 'Email address is required.'; errEl.style.display = 'block'; return; }

    btn.textContent = 'Inviting…';
    btn.disabled = true;

    try {
        var csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
        var res  = await fetch('/api/resellers', {
            method:      'POST',
            credentials: 'same-origin',
            headers:     { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body:        JSON.stringify({ name: name, email: email, phone: phone, territory: territory, tenant_id: tenantId, status: 'invited' })
        });
        var data = await res.json();
        if (data.id) {
            rbInviteClose();
            window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'success', message: 'Referrer invited successfully.' }}));
            window.dispatchEvent(new CustomEvent('referrer-invited', { detail: data }));
        } else {
            var msg = (data.errors && data.errors.email && data.errors.email[0]) || data.message || 'Failed to invite referrer.';
            errEl.textContent = msg;
            errEl.style.display = 'block';
            btn.textContent = 'Send Invitation';
            btn.disabled = false;
        }
    } catch(err) {
        errEl.textContent = 'Network error. Please try again.';
        errEl.style.display = 'block';
        btn.textContent = 'Send Invitation';
        btn.disabled = false;
    }
}
</script>
@endpush
@endsection
