@extends('layouts.app')
@section('title', 'Referrers')
@section('nav') @include('tenant._nav') @endsection

@section('topbar-actions')
    <button x-data @click="$dispatch('open-invite-referrer')" class="btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
        <span class="hidden sm:inline">Invite Referrer</span>
    </button>
@endsection

@section('content')
<div class="space-y-5"
     x-data="referrersModule('{{ $tenant->id }}')"
     x-init="init()"
     @open-invite-referrer.window="showInvite = true">

    {{-- Summary KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
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
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Missing Agreements</span>
                <p class="text-2xl font-bold mt-1.5"
                   :class="nonCompliantCount() > 0 ? 'text-orange-500' : 'text-[#1E1B4B]'"
                   x-text="totalRequiredAgreements > 0 ? nonCompliantCount() : '—'"></p>
            </div>
            <div class="kpi-icon bg-orange-100 ml-3 shrink-0"><svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></div>
        </div>
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide flex items-center gap-1">Total Closed @if($showLocation ?? false)<x-tax-tip />@endif</span>
                <p class="text-2xl font-bold text-[#1E1B4B] mt-1.5" x-text="'&#8369;' + totalClosed()"></p>
            </div>
            <div class="kpi-icon bg-blue-100 ml-3 shrink-0"><svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
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
            <button x-show="filterStatus || filterAgreement || search"
                    @click="filterStatus=''; filterAgreement=''; search=''; applyFilters()"
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
                        <th>Territory</th>
                        <th>Deals</th>
                        <th class="flex items-center gap-1">Closed Value @if($showLocation ?? false)<x-tax-tip />@endif</th>
                        <th>Performance</th>
                        <th>Status</th>
                        <th x-show="totalRequiredAgreements > 0">Agreements</th>
                        <th>Joined</th>
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
                                <p class="text-gray-400 text-sm" x-text="referrers.length === 0 ? 'No referrers yet. Invite your first referrer.' : 'No referrers match the filters.'"></p>
                                <button x-show="referrers.length === 0" @click="showInvite = true" class="btn-primary mt-3 text-sm">Invite First Referrer</button>
                            </td>
                        </tr>
                    </template>
                    <template x-for="r in filtered" :key="r.id">
                        <tr class="table-row">
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-purple-700 text-xs font-bold shrink-0"
                                         x-text="(r.name||'?').slice(0,2).toUpperCase()"></div>
                                    <div class="min-w-0">
                                        <p class="font-medium text-[#1E1B4B] truncate" x-text="r.name"></p>
                                        <p class="text-xs text-gray-400 truncate" x-text="r.email"></p>
                                    </div>
                                </div>
                            </td>
                            <td class="text-gray-500 text-sm" x-text="r.territory || '—'"></td>
                            <td class="text-sm tabular-nums">
                                <span class="font-semibold text-[#1E1B4B]" x-text="r.assigned_leads || 0"></span>
                            </td>
                            <td class="font-semibold text-[#1E1B4B] tabular-nums" x-text="formatValue(r.closed_value)"></td>
                            <td>
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
                            <td x-show="totalRequiredAgreements > 0">
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

                            <td class="text-gray-400 text-sm tabular-nums"
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
                                    <button x-show="totalRequiredAgreements > 0" @click="openAgreements(r)"
                                            class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 hover:text-[#7B61FF] transition-colors"
                                            title="Manage agreements">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
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

    {{-- Invite Modal --}}
    <div x-show="showInvite" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-[#1E1B4B]">Invite Referrer</h3>
                <button @click="showInvite = false; resetForm()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Full Name *</label>
                        <input type="text" x-model="form.name" class="form-input" placeholder="Juan dela Cruz">
                    </div>
                    <div>
                        <label class="form-label">Email *</label>
                        <input type="email" x-model="form.email" class="form-input" placeholder="juan@email.com">
                    </div>
                    <div>
                        <label class="form-label">Phone</label>
                        <input type="text" x-model="form.phone" class="form-input" placeholder="+63 9XX XXX XXXX">
                    </div>
                    <div>
                        <label class="form-label">Territory</label>
                        <input type="text" x-model="form.territory" class="form-input" placeholder="e.g. Metro Manila">
                    </div>
                </div>
                <p x-show="formError" class="text-xs text-red-600" x-text="formError"></p>
                <div class="flex justify-end gap-3">
                    <button @click="showInvite = false; resetForm()" class="btn-secondary">Cancel</button>
                    <button @click="invite()" :disabled="saving" class="btn-primary" x-text="saving ? 'Inviting…' : 'Send Invitation'"></button>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function referrersModule(tenantId) {
    return {
        referrers: [], filtered: [], loading: true,
        search: '', filterStatus: '', filterAgreement: '',
        showInvite: false, saving: false, formError: '',
        form: { name: '', email: '', phone: '', territory: '' },

        // Agreement data
        totalRequiredAgreements: 0,
        resellerCompliance: {},   // { [reseller_id]: { acknowledged, required_total, fully_compliant } }

        // Agreement modal
        showAgreements: false,
        activeReseller: null,
        activeResellerAgreements: [],
        loadingAgreements: false,
        ackSaving: null,

        async init() {
            try {
                const res = await fetch(`/api/resellers?tenant_id=${tenantId}`);
                const data = await res.json();
                this.referrers = Array.isArray(data) ? data : (data.data || []);
            } catch(e) { this.referrers = []; }

            // Load compliance data in parallel
            try {
                const cr  = await fetch(`/api/agreements/compliance?tenant_id=${tenantId}`);
                const cd  = await cr.json();
                this.totalRequiredAgreements = cd.required_agreements || 0;
                const map = {};
                (cd.resellers || []).forEach(r => { map[r.reseller_id] = r; });
                this.resellerCompliance = map;
            } catch(e) {}

            this.applyFilters();
            this.loading = false;
        },

        applyFilters() {
            const q = this.search.toLowerCase();
            this.filtered = this.referrers.filter(r => {
                const matchQ  = !q || (r.name||'').toLowerCase().includes(q) || (r.email||'').toLowerCase().includes(q) || (r.territory||'').toLowerCase().includes(q);
                const matchSt = !this.filterStatus || r.status === this.filterStatus;
                const matchAg = !this.filterAgreement
                    || (this.filterAgreement === 'compliant'  && this.isCompliant(r.id))
                    || (this.filterAgreement === 'missing'    && !this.isCompliant(r.id));
                return matchQ && matchSt && matchAg;
            });
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

        async invite() {
            if (!this.form.name)  { this.formError = 'Name is required.'; return; }
            if (!this.form.email) { this.formError = 'Email is required.'; return; }
            this.saving = true; this.formError = '';
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
                    this.showInvite = false;
                    this.resetForm();
                    this.$dispatch('show-toast', { type: 'success', message: 'Referrer invited successfully.' });
                } else {
                    this.formError = reseller.message || 'Failed to invite referrer.';
                    this.$dispatch('show-toast', { type: 'error', message: reseller.message || 'Failed to invite referrer.' });
                }
            } catch(e) { this.formError = 'Network error. Please try again.'; this.$dispatch('show-toast', { type: 'error', message: 'Network error. Please try again.' }); }
            finally { this.saving = false; }
        },
    };
}
</script>
@endsection
