@extends('layouts.app')
@section('title', 'Export Requests')
@section('nav') @include('tenant._nav') @endsection

@section('topbar-actions')
    <button type="button" @click="$store.exportModal.open()" class="btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        <span class="hidden sm:inline">New Export Request</span>
    </button>
@endsection

@section('content')
<div class="space-y-5"
     x-data="exportsModule('{{ $tenant->id }}')"
     x-init="init()">

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Total Requests</span>
                <p class="text-2xl font-bold text-[#1E1B4B] mt-1.5" x-text="meta.total ?? '—'"></p>
            </div>
            <div class="kpi-icon bg-purple-100 ml-3 shrink-0">
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
            </div>
        </div>
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Pending Approval</span>
                <p class="text-2xl font-bold mt-1.5"
                   :class="(meta.pending_count ?? 0) > 0 ? 'text-orange-500' : 'text-[#1E1B4B]'"
                   x-text="meta.pending_count ?? '—'"></p>
            </div>
            <div class="kpi-icon bg-orange-100 ml-3 shrink-0">
                <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Ready to Download</span>
                <p class="text-2xl font-bold mt-1.5"
                   :class="(meta.ready_count ?? 0) > 0 ? 'text-emerald-500' : 'text-[#1E1B4B]'"
                   x-text="meta.ready_count ?? '—'"></p>
            </div>
            <div class="kpi-icon bg-emerald-100 ml-3 shrink-0">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Approved This Month</span>
                <p class="text-2xl font-bold text-[#1E1B4B] mt-1.5" x-text="meta.approved_this_month ?? '—'"></p>
            </div>
            <div class="kpi-icon bg-blue-100 ml-3 shrink-0">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
        </div>
    </div>

    {{-- Page header + search + filters --}}
    <div class="card space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-[#EDE9FE] flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-[#7B61FF]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-[#1E1B4B] font-bold text-lg">Export Requests</h2>
                    <p class="text-gray-400 text-xs mt-0.5">Manage data exports and approvals for this workspace</p>
                </div>
            </div>
            <div class="text-sm text-gray-400">
                <span class="font-semibold text-[#1E1B4B]" x-text="meta.total ?? 0"></span>
                <span x-text="(meta.total ?? 0) === 1 ? ' request' : ' requests'"></span>
                <template x-if="meta.last_page > 1">
                    <span> &middot; Page <span x-text="currentPage"></span> of <span x-text="meta.last_page"></span></span>
                </template>
            </div>
        </div>

        {{-- Search --}}
        <div class="search-group">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" x-model="search"
                   @input.debounce.300ms="currentPage = 1; fetchData()"
                   placeholder="Search by requester, export type…"
                   autocomplete="off">
            <button type="button" x-show="search.length > 0"
                    @click="search = ''; currentPage = 1; fetchData()"
                    class="text-gray-400 hover:text-gray-600 transition-colors shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Filter bar --}}
        <div class="filter-bar">
            <label class="filter-pill" :class="filterStatus ? 'active' : ''">
                <svg class="w-3.5 h-3.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <select x-model="filterStatus" @change="currentPage = 1; fetchData()">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="processing">Processing</option>
                    <option value="ready">Ready</option>
                    <option value="direct_ready">Ready (Direct)</option>
                    <option value="rejected">Rejected</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="expired">Expired</option>
                    <option value="failed">Failed</option>
                </select>
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </label>

            <label class="filter-pill" :class="filterType ? 'active' : ''">
                <svg class="w-3.5 h-3.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
                </svg>
                <select x-model="filterType" @change="currentPage = 1; fetchData()">
                    <option value="">All Types</option>
                    <option value="deals">Deals</option>
                    <option value="contacts">Contacts</option>
                    <option value="organizations">Organizations</option>
                    <option value="referrers">Referrers</option>
                    <option value="commissions">Commissions</option>
                    <option value="users">Users</option>
                    <option value="audit_logs">Audit Logs</option>
                    <option value="reports">Reports</option>
                    <option value="messages">Messages</option>
                    <option value="import_summary">Import Summary</option>
                </select>
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </label>

            <label class="filter-pill" :class="filterRole ? 'active' : ''">
                <svg class="w-3.5 h-3.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <select x-model="filterRole" @change="currentPage = 1; fetchData()">
                    <option value="">All Roles</option>
                    <option value="owner">Owner</option>
                    <option value="admin">Admin</option>
                    <option value="manager">Manager</option>
                    <option value="referrer">Referrer</option>
                    <option value="super_admin">Super Admin</option>
                </select>
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </label>

            <label class="filter-pill" :class="filterFrom ? 'active' : ''">
                <svg class="w-3.5 h-3.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <input type="date" x-model="filterFrom" @change="currentPage = 1; fetchData()" placeholder="From">
            </label>

            <label class="filter-pill" :class="filterTo ? 'active' : ''">
                <svg class="w-3.5 h-3.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <input type="date" x-model="filterTo" @change="currentPage = 1; fetchData()" placeholder="To">
            </label>

            <button type="button" x-show="filterStatus || filterType || filterRole || filterFrom || filterTo || search"
                    @click="filterStatus = ''; filterType = ''; filterRole = ''; filterFrom = ''; filterTo = ''; search = ''; currentPage = 1; fetchData()"
                    class="filter-pill !border-red-200 !text-red-500 hover:!bg-red-50">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                Clear filters
            </button>
        </div>
    </div>

    {{-- Data table --}}
    <div class="card p-0 overflow-hidden">

        {{-- Loading state --}}
        <div x-show="loading" class="flex items-center justify-center py-12 gap-3 text-gray-400">
            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            <span class="text-sm">Loading export requests…</span>
        </div>

        {{-- Error state --}}
        <div x-show="!loading && error" class="flex flex-col items-center justify-center py-12 text-center px-4">
            <svg class="w-8 h-8 text-red-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <p class="text-sm font-semibold text-gray-600">Failed to load export requests</p>
            <button type="button" @click="fetchData()" class="mt-2 text-xs text-[#7B61FF] hover:underline">Try again</button>
        </div>

        {{-- Empty state --}}
        <div x-show="!loading && !error && requests.length === 0" class="flex flex-col items-center justify-center py-16 text-center px-4">
            <img src="/images/mascots/r-bunny-sleeping.webp" alt="" class="w-14 h-14 object-contain mb-3 opacity-50">
            <p class="text-sm font-semibold text-gray-500">No export requests found</p>
            <p class="text-xs text-gray-400 mt-1">R Bunny says no data exports have been requested yet.</p>
            <button type="button" @click="$store.exportModal.open()" class="mt-4 btn-primary text-xs py-1.5 px-3">Request an Export</button>
        </div>

        {{-- Desktop table --}}
        <div x-show="!loading && !error && requests.length > 0" class="hidden md:block overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="table-head">
                        <th style="width:130px">Date</th>
                        <th>Requested By</th>
                        <th style="width:90px">Role</th>
                        <th style="width:120px">Export Type</th>
                        <th style="width:70px">Format</th>
                        <th style="width:120px">Status</th>
                        <th style="width:80px">Records</th>
                        <th style="width:80px"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="req in requests" :key="req.id">
                        <tr class="table-row cursor-pointer"
                            @click="window.location.href = '/tenant/{{ $tenant->id }}/exports/' + req.id">
                            <td>
                                <p class="text-xs text-gray-600" x-text="formatDate(req.created_at)"></p>
                            </td>
                            <td>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-[#1E1B4B] truncate" x-text="req.requester_id"></p>
                                    <p class="text-xs text-gray-400" x-text="req.requester_type === 'reseller' ? 'Referrer' : 'Tenant User'"></p>
                                </div>
                            </td>
                            <td>
                                <span class="text-xs text-gray-600 capitalize" x-text="req.requester_role"></span>
                            </td>
                            <td>
                                <div class="flex items-center gap-1.5">
                                    <template x-if="req.is_sensitive">
                                        <svg class="w-3 h-3 text-orange-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>
                                    </template>
                                    <span class="text-xs text-gray-700 capitalize" x-text="req.export_type.replace('_', ' ')"></span>
                                </div>
                            </td>
                            <td>
                                <span class="text-xs font-mono uppercase text-gray-500" x-text="req.export_format"></span>
                            </td>
                            <td @click.stop>
                                <span :class="statusBadgeClass(req.status)"
                                      x-text="statusLabel(req.status)"></span>
                            </td>
                            <td>
                                <span class="text-xs text-gray-500" x-text="req.records_estimate > 0 ? req.records_estimate.toLocaleString() : '—'"></span>
                            </td>
                            <td @click.stop>
                                <a :href="'/tenant/{{ $tenant->id }}/exports/' + req.id"
                                   class="px-2.5 py-1.5 rounded-lg text-xs font-medium bg-purple-50 text-purple-700 hover:bg-purple-100 transition-colors whitespace-nowrap">
                                    View →
                                </a>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Mobile cards --}}
        <div x-show="!loading && !error && requests.length > 0" class="md:hidden divide-y divide-gray-50">
            <template x-for="req in requests" :key="req.id">
                <div class="p-4 space-y-2.5"
                     @click="window.location.href = '/tenant/{{ $tenant->id }}/exports/' + req.id">
                    <div class="flex items-center justify-between">
                        <span :class="statusBadgeClass(req.status)" x-text="statusLabel(req.status)"></span>
                        <span class="text-[10px] text-gray-400" x-text="formatDate(req.created_at)"></span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <template x-if="req.is_sensitive">
                            <svg class="w-3 h-3 text-orange-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </template>
                        <p class="text-sm font-medium text-[#1E1B4B] capitalize" x-text="req.export_type.replace('_', ' ') + ' (' + (req.export_format || '').toUpperCase() + ')'"></p>
                    </div>
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-gray-500 capitalize" x-text="req.requester_role + ' · ' + (req.requester_type === 'reseller' ? 'Referrer' : 'Tenant')"></p>
                        <span class="text-xs text-[#7B61FF] font-semibold">View →</span>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- Pagination --}}
    <div x-show="meta.last_page > 1" class="flex items-center justify-between">
        <p class="text-xs text-gray-400">
            Showing
            <span x-text="((currentPage - 1) * 25) + 1"></span>–<span x-text="Math.min(currentPage * 25, meta.total ?? 0)"></span>
            of <span x-text="meta.total ?? 0"></span> requests
        </p>
        <div class="flex items-center gap-1.5">
            <button type="button" x-show="currentPage > 1"
                    @click="currentPage--; fetchData()"
                    class="px-3 py-1.5 rounded-xl border border-gray-200 text-xs font-medium text-gray-600 hover:bg-gray-50 transition-colors">← Prev</button>
            <button type="button" x-show="currentPage < (meta.last_page ?? 1)"
                    @click="currentPage++; fetchData()"
                    class="px-3 py-1.5 rounded-xl border border-gray-200 text-xs font-medium text-gray-600 hover:bg-gray-50 transition-colors">Next →</button>
        </div>
    </div>

</div>

{{-- ── New Export Request Modal ──────────────────────────────────── --}}
<div x-data x-show="$store.exportModal.visible" x-cloak
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
     @click.self="$store.exportModal.close()">

    <div x-data="exportRequestForm('{{ $tenant->id }}')"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">

        {{-- Modal header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-[#EDE9FE] flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-[#7B61FF]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-[#1E1B4B] font-bold text-base leading-tight">New Export Request</h3>
                    <p class="text-gray-400 text-xs">Request a data export from your workspace</p>
                </div>
            </div>
            <button type="button" @click="$store.exportModal.close()"
                    class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Modal body --}}
        <div class="px-6 py-5 space-y-4">

            {{-- Success feedback --}}
            <div x-show="success"
                 class="flex items-start gap-3 p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">
                <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span x-text="success"></span>
            </div>

            {{-- Error feedback --}}
            <div x-show="formError"
                 class="flex items-start gap-3 p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm">
                <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span x-text="formError"></span>
            </div>

            <template x-if="!success">
                <div class="space-y-4">
                    {{-- Export Type --}}
                    <div>
                        <label class="form-label">Export Type <span class="text-red-400">*</span></label>
                        <select x-model="form.export_type" class="form-input mt-1 w-full">
                            <option value="">Select export type…</option>
                            <option value="deals">Deals</option>
                            <option value="contacts">Contacts</option>
                            <option value="organizations">Organizations</option>
                            <option value="referrers">Referrers</option>
                            <option value="commissions">Commissions</option>
                            <option value="users">Users</option>
                            <option value="audit_logs">Audit Logs</option>
                            <option value="reports">Reports</option>
                            <option value="messages">Messages</option>
                            <option value="import_summary">Import Summary</option>
                        </select>
                    </div>

                    {{-- Format --}}
                    <div>
                        <label class="form-label">File Format <span class="text-red-400">*</span></label>
                        <div class="flex items-center gap-4 mt-1.5">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" x-model="form.format" value="csv"
                                       class="w-4 h-4 text-[#7B61FF] border-gray-300 focus:ring-[#7B61FF]">
                                <span class="text-sm text-gray-700 font-medium">CSV</span>
                                <span class="text-xs text-gray-400">— plain text, universal</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" x-model="form.format" value="xlsx"
                                       class="w-4 h-4 text-[#7B61FF] border-gray-300 focus:ring-[#7B61FF]">
                                <span class="text-sm text-gray-700 font-medium">XLSX</span>
                                <span class="text-xs text-gray-400">— Excel spreadsheet</span>
                            </label>
                        </div>
                    </div>

                    {{-- Date range --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">From Date</label>
                            <input type="date" x-model="form.scope_from" class="form-input mt-1 w-full">
                        </div>
                        <div>
                            <label class="form-label">To Date</label>
                            <input type="date" x-model="form.scope_to" class="form-input mt-1 w-full">
                        </div>
                    </div>

                    {{-- Reason --}}
                    <div>
                        <label class="form-label">
                            Reason
                            <span class="text-gray-400 font-normal text-xs ml-1">(may be required)</span>
                        </label>
                        <textarea x-model="form.reason"
                                  rows="3"
                                  placeholder="Briefly describe why this export is needed…"
                                  class="form-input mt-1 w-full resize-none"></textarea>
                    </div>
                </div>
            </template>
        </div>

        {{-- Modal footer --}}
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/50">
            <button type="button" @click="$store.exportModal.close()" class="btn-secondary" :disabled="submitting">Cancel</button>
            <template x-if="!success">
                <button type="button" @click="submit()" class="btn-primary" :disabled="submitting || !form.export_type || !form.format">
                    <span x-show="!submitting">Request Export</span>
                    <span x-show="submitting" class="flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Submitting…
                    </span>
                </button>
            </template>
            <template x-if="success">
                <button type="button" @click="$store.exportModal.close()" class="btn-primary">Done</button>
            </template>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// ── Alpine store for modal state ──────────────────────────────────
document.addEventListener('alpine:init', () => {
    Alpine.store('exportModal', {
        visible: false,
        open()  { this.visible = true; },
        close() { this.visible = false; },
    });
});

// ── Exports list module ───────────────────────────────────────────
function exportsModule(tenantId) {
    return {
        tenantId,
        requests:    [],
        meta:        {},
        loading:     true,
        error:       false,
        search:      '',
        filterStatus:'',
        filterType:  '',
        filterRole:  '',
        filterFrom:  '',
        filterTo:    '',
        currentPage: 1,

        init() {
            this.fetchData();
        },

        buildUrl() {
            const params = new URLSearchParams({ tenant_id: this.tenantId, page: this.currentPage });
            if (this.search)       params.set('search',  this.search);
            if (this.filterStatus) params.set('status',  this.filterStatus);
            if (this.filterType)   params.set('type',    this.filterType);
            if (this.filterRole)   params.set('role',    this.filterRole);
            if (this.filterFrom)   params.set('from',    this.filterFrom);
            if (this.filterTo)     params.set('to',      this.filterTo);
            return '/api/exports?' + params.toString();
        },

        async fetchData() {
            this.loading = true;
            this.error   = false;
            try {
                const res  = await fetch(this.buildUrl(), {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const json    = await res.json();
                this.requests = json.data  ?? [];
                this.meta     = json.meta  ?? {};
            } catch (e) {
                this.error = true;
            } finally {
                this.loading = false;
            }
        },

        statusLabel(status) {
            const map = {
                pending:       'Pending Approval',
                approved:      'Approved',
                processing:    'Generating',
                ready:         'Ready',
                direct_ready:  'Ready',
                rejected:      'Rejected',
                cancelled:     'Cancelled',
                expired:       'Expired',
                failed:        'Failed',
                direct_pending:'Processing',
            };
            return map[status] ?? status;
        },

        statusBadgeClass(status) {
            const map = {
                pending:       'badge badge-orange',
                approved:      'badge badge-blue',
                processing:    'badge badge-purple',
                direct_pending:'badge badge-purple',
                ready:         'badge badge-green',
                direct_ready:  'badge badge-green',
                rejected:      'badge badge-red',
                cancelled:     'badge badge-gray',
                expired:       'badge badge-gray',
                failed:        'badge badge-red',
            };
            return map[status] ?? 'badge badge-gray';
        },

        formatDate(dt) {
            if (!dt) return '—';
            return new Date(dt).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
        },
    };
}

// ── Export request form module ────────────────────────────────────
function exportRequestForm(tenantId) {
    return {
        tenantId,
        form: {
            export_type: '',
            format:      'csv',
            scope_from:  '',
            scope_to:    '',
            reason:      '',
        },
        submitting: false,
        success:    '',
        formError:  '',

        async submit() {
            this.submitting = true;
            this.formError  = '';
            this.success    = '';

            const scope = {};
            if (this.form.scope_from) scope.from = this.form.scope_from;
            if (this.form.scope_to)   scope.to   = this.form.scope_to;

            try {
                const res = await fetch('/api/exports?tenant_id=' + this.tenantId, {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept':       'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        export_type: this.form.export_type,
                        format:      this.form.format,
                        scope:       Object.keys(scope).length ? scope : null,
                        reason:      this.form.reason || null,
                    }),
                });

                const json = await res.json();

                if (!res.ok) {
                    this.formError = json.message ?? 'Failed to submit export request.';
                    return;
                }

                this.success = 'Export request submitted successfully. '
                    + (json.status === 'pending' ? 'It is pending admin approval.' : 'It is now being generated.');

                // Refresh the list behind the modal
                const listEl = document.querySelector('[x-data*="exportsModule"]');
                if (listEl) listEl._x_dataStack[0].fetchData();

            } catch (e) {
                this.formError = 'A network error occurred. Please try again.';
            } finally {
                this.submitting = false;
            }
        },
    };
}
</script>
@endpush
