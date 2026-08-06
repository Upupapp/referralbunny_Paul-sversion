@extends('layouts.app')
@section('title', 'Organizations')
@section('nav') @include('tenant._nav') @endsection

@section('topbar-actions')
    <button type="button" x-data @click="$dispatch('open-add-org')" class="btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        <span class="hidden sm:inline">Add Organization</span>
    </button>
@endsection

@section('content')
<div class="space-y-5"
     x-data="orgsModule('{{ $tenant->id }}')"
     x-init="init()"
     @open-add-org.window="openAdd()">

    {{-- KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Total Organizations</span>
                <p class="text-2xl font-bold text-[#1E1B4B] mt-1.5" x-text="meta.total.toLocaleString()"></p>
            </div>
            <div class="kpi-icon bg-purple-100 ml-3 shrink-0">
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
        </div>
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Total Contacts</span>
                <p class="text-2xl font-bold text-[#1E1B4B] mt-1.5" x-text="orgs.reduce((s,o) => s + Number(o.contact_count||0), 0)"></p>
            </div>
            <div class="kpi-icon bg-blue-100 ml-3 shrink-0">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </div>
        </div>
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">With Deals</span>
                <p class="text-2xl font-bold text-[#1E1B4B] mt-1.5" x-text="orgs.filter(o => o.deal_count > 0).length"></p>
            </div>
            <div class="kpi-icon bg-emerald-100 ml-3 shrink-0">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
            </div>
        </div>
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Total Deal Value</span>
                <p class="text-2xl font-bold text-[#1E1B4B] mt-1.5" x-text="fmtValue(orgs.reduce((s,o) => s + Number(o.deal_value||0), 0))"></p>
            </div>
            <div class="kpi-icon bg-orange-100 ml-3 shrink-0">
                <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
    </div>

    {{-- Filter bar --}}
    <div class="card space-y-3">
        {{-- Search --}}
        <div class="search-group">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" x-model="search" @input.debounce.400ms="resetAndFetch()"
                   placeholder="Search city or municipality…">
            <button type="button" x-show="search.length > 0" @click="search=''; resetAndFetch()"
                    class="text-gray-400 hover:text-gray-600 transition-colors shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- LGU-specific filters --}}
        <div class="filter-bar">

            {{-- Island Group (Luzon / Visayas / Mindanao) --}}
            <label class="filter-pill" :class="filterIsland ? 'active' : ''">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"/></svg>
                <select x-effect="$el.value = filterIsland" @change="filterIsland = $event.target.value; filterRegion=''; filterProvince=''; resetAndFetch()">
                    <option value="">All Islands</option>
                    <option value="Luzon">Luzon</option>
                    <option value="Visayas">Visayas</option>
                    <option value="Mindanao">Mindanao</option>
                </select>
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>

            {{-- Region (cascades from island group) --}}
            <label class="filter-pill" :class="filterRegion ? 'active' : ''">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                <select x-effect="$el.value = filterRegion" @change="filterRegion = $event.target.value; filterProvince=''; resetAndFetch()">
                    <option value="">All Regions</option>
                    <template x-for="r in filteredRegionList" :key="r">
                        <option :value="r" x-text="r.split(' – ')[0]"></option>
                    </template>
                </select>
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>

            {{-- Province (cascades from region) --}}
            <label class="filter-pill" :class="filterProvince ? 'active' : ''"
                   x-show="filterRegion || filterProvince">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                <select x-effect="$el.value = filterProvince" @change="filterProvince = $event.target.value; resetAndFetch()">
                    <option value="">All Provinces</option>
                    <template x-for="p in provinceOptions" :key="p">
                        <option :value="p" x-text="p"></option>
                    </template>
                </select>
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>

            {{-- LGU Type --}}
            <label class="filter-pill" :class="filterType ? 'active' : ''">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <select x-effect="$el.value = filterType" @change="filterType = $event.target.value; resetAndFetch()">
                    <option value="">Cities &amp; Municipalities</option>
                    <option value="City">Cities only</option>
                    <option value="Municipality">Municipalities only</option>
                </select>
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>

            {{-- Deal Status --}}
            <label class="filter-pill" :class="filterHasDeal !== '' ? 'active' : ''">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <select x-effect="$el.value = filterHasDeal" @change="filterHasDeal = $event.target.value; resetAndFetch()">
                    <option value="">All Deal Status</option>
                    <option value="1">With Active Deals</option>
                    <option value="0">No Deals Yet</option>
                </select>
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>

            <button type="button" x-show="filterIsland || filterRegion || filterProvince || filterType || filterHasDeal !== '' || search"
                    @click="filterIsland=''; filterRegion=''; filterProvince=''; filterType=''; filterHasDeal=''; search=''; resetAndFetch()"
                    class="filter-pill !border-red-200 !text-red-500 hover:!bg-red-50">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                Clear all
            </button>
        </div>
    </div>

    {{-- Table --}}
    <div class="card p-0 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
            <p class="text-sm font-semibold text-[#1E1B4B]">
                <span x-text="meta.total.toLocaleString()"></span> organizations
                <span x-show="filterRegion||filterProvince||filterType||search" class="text-gray-400 font-normal text-xs ml-1">— filtered</span>
            </p>
            <p class="text-xs text-gray-400" x-show="meta.last_page > 1"
               x-text="'Page ' + meta.page + ' of ' + meta.last_page"></p>
        </div>

        <div x-show="loading" class="flex items-center justify-center py-10 gap-3 text-gray-400">
            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            <span class="text-sm">Loading organizations…</span>
        </div>

        <div x-show="!loading" class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="table-head">
                        <th>Organization</th>
                        <th class="hidden sm:table-cell">Province</th>
                        <th class="hidden md:table-cell">Region</th>
                        <th class="hidden md:table-cell">Type</th>
                        <th class="hidden lg:table-cell">Contacts</th>
                        <th>Deals</th>
                        <th>Deal Value</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="orgs.length === 0 && !loading">
                        <tr>
                            <td colspan="8" class="py-16 text-center">
                                <div class="flex justify-center text-gray-300 mb-3">
                                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                </div>
                                <p class="text-gray-400 text-sm">No organizations match the current filters.</p>
                            </td>
                        </tr>
                    </template>
                    <template x-for="o in orgs" :key="o.id">
                        <tr class="table-row">
                            {{-- Name --}}
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-xs font-bold shrink-0"
                                         :style="`background:${o._d.lgu_type==='City' ? '#7B61FF' : '#3B82F6'}`"
                                         x-text="(o.name||'?').slice(0,2).toUpperCase()"></div>
                                    <div class="min-w-0">
                                        <p class="font-medium text-[#1E1B4B] truncate" x-text="o.name"></p>
                                        <p class="text-xs text-gray-400 truncate" x-text="o.city"></p>
                                    </div>
                                </div>
                            </td>
                            {{-- Province --}}
                            <td class="hidden sm:table-cell text-sm text-gray-600" x-text="o.address || '—'"></td>
                            {{-- Region --}}
                            <td class="hidden md:table-cell text-xs text-gray-400"
                                x-text="o._d.region ? o._d.region.split(' – ')[0] : '—'"></td>
                            {{-- Type --}}
                            <td class="hidden md:table-cell">
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                                      :class="o._d.lgu_type==='City' ? 'bg-violet-100 text-violet-700' : 'bg-blue-100 text-blue-700'"
                                      x-text="o._d.lgu_type || '—'"></span>
                            </td>
                            {{-- Contacts --}}
                            <td class="hidden lg:table-cell">
                                <span x-show="o.contact_count > 0"
                                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-purple-50 text-purple-700 text-xs font-semibold"
                                      x-text="o.contact_count"></span>
                                <span x-show="!o.contact_count" class="text-gray-300 text-sm">—</span>
                            </td>
                            {{-- Deals --}}
                            <td class="text-sm font-semibold text-[#1E1B4B] tabular-nums"
                                x-text="o.deal_count > 0 ? o.deal_count : '—'"></td>
                            {{-- Deal value --}}
                            <td class="text-sm font-semibold text-[#1E1B4B] tabular-nums"
                                x-text="o.deal_value > 0 ? fmtValue(o.deal_value) : '—'"></td>
                            {{-- Actions --}}
                            <td>
                                <div class="flex items-center gap-1.5 justify-end">
                                    <button type="button" @click="openEdit(o)"
                                            class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-700 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button type="button" @click="deleteOrg(o.id)"
                                            class="p-1.5 rounded-lg hover:bg-red-50 text-gray-400 hover:text-red-500 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div x-show="meta.last_page > 1"
             class="flex items-center justify-between px-5 py-3 border-t border-gray-100 bg-gray-50/50">
            <p class="text-xs text-gray-400"
               x-text="`Showing ${((meta.page-1)*meta.per_page)+1}–${Math.min(meta.page*meta.per_page, meta.total)} of ${meta.total.toLocaleString()} organizations`"></p>
            <div class="flex items-center gap-1.5">
                <button type="button" @click="goPage(meta.page - 1)" :disabled="meta.page <= 1"
                        class="px-3 py-1.5 rounded-lg text-xs font-medium border border-gray-200 text-gray-600 hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                    ← Prev
                </button>
                <template x-for="p in pageNumbers()" :key="p">
                    <button type="button" @click="goPage(p)"
                            :class="p === meta.page ? 'bg-[#7B61FF] text-white border-[#7B61FF]' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'"
                            class="w-8 h-8 rounded-lg text-xs font-semibold border transition-colors"
                            x-text="p"></button>
                </template>
                <button type="button" @click="goPage(meta.page + 1)" :disabled="meta.page >= meta.last_page"
                        class="px-3 py-1.5 rounded-lg text-xs font-medium border border-gray-200 text-gray-600 hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                    Next →
                </button>
            </div>
        </div>
    </div>

    {{-- Add / Edit Modal --}}
    <div x-show="showModal" x-cloak
         class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4"
         @keydown.escape.window="showModal = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-[#1E1B4B]" x-text="editId ? 'Edit Organization' : 'Add Organization'"></h3>
                <button type="button" @click="showModal = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="form-label">Organization Name *</label>
                    <input type="text" x-model="form.name" class="form-input" placeholder="e.g. Municipality of Tagaytay">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Industry</label>
                        <input type="text" x-model="form.industry" class="form-input" placeholder="e.g. Government, Healthcare">
                    </div>
                    <div>
                        <label class="form-label">City / Municipality</label>
                        <input type="text" x-model="form.city" class="form-input" placeholder="City name">
                    </div>
                    <div>
                        <label class="form-label">Website</label>
                        <input type="text" x-model="form.website" class="form-input" placeholder="https://…">
                    </div>
                    <div>
                        <label class="form-label">Country</label>
                        <input type="text" x-model="form.country" class="form-input" placeholder="Philippines">
                    </div>
                </div>
                <div>
                    <label class="form-label">Address</label>
                    <textarea x-model="form.address" class="form-input" rows="2" placeholder="Full address"></textarea>
                </div>
                <div>
                    <label class="form-label">Notes</label>
                    <textarea x-model="form.notes" class="form-input" rows="2" placeholder="Any notes about this organization…"></textarea>
                </div>
                <p x-show="formError" class="text-xs text-red-600 font-medium" x-text="formError"></p>
                <div class="flex justify-end gap-3 pt-1">
                    <button type="button" @click="showModal = false" class="btn-secondary">Cancel</button>
                    <button type="button" @click="saveOrg()" :disabled="saving" class="btn-primary"
                            x-text="saving ? 'Saving…' : (editId ? 'Save Changes' : 'Add Organization')"></button>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
(function() {
    const ISLAND_REGIONS = {
        'Luzon':    ['NCR', 'CAR', 'Region I', 'Region II', 'Region III', 'Region IV-A', 'Region IV-B', 'Region V'],
        'Visayas':  ['Region VI', 'Region VII', 'Region VIII'],
        'Mindanao': ['Region IX', 'Region X', 'Region XI', 'Region XII', 'Region XIII', 'BARMM'],
    };

    const PH_REGIONS = {
        'NCR – National Capital Region':              ['Metro Manila'],
        'CAR – Cordillera Administrative Region':     ['Abra','Apayao','Benguet','Ifugao','Kalinga','Mountain Province'],
        'Region I – Ilocos Region':                   ['Ilocos Norte','Ilocos Sur','La Union','Pangasinan'],
        'Region II – Cagayan Valley':                 ['Batanes','Cagayan','Isabela','Nueva Vizcaya','Quirino'],
        'Region III – Central Luzon':                 ['Aurora','Bataan','Bulacan','Nueva Ecija','Pampanga','Tarlac','Zambales'],
        'Region IV-A – CALABARZON':                   ['Batangas','Cavite','Laguna','Quezon','Rizal'],
        'Region IV-B – MIMAROPA':                     ['Marinduque','Occidental Mindoro','Oriental Mindoro','Palawan','Romblon'],
        'Region V – Bicol Region':                    ['Albay','Camarines Norte','Camarines Sur','Catanduanes','Masbate','Sorsogon'],
        'Region VI – Western Visayas':                ['Aklan','Antique','Capiz','Guimaras','Iloilo','Negros Occidental'],
        'Region VII – Central Visayas':               ['Bohol','Cebu','Negros Oriental','Siquijor'],
        'Region VIII – Eastern Visayas':              ['Biliran','Eastern Samar','Leyte','Northern Samar','Samar','Southern Leyte'],
        'Region IX – Zamboanga Peninsula':            ['Zamboanga del Norte','Zamboanga del Sur','Zamboanga Sibugay'],
        'Region X – Northern Mindanao':               ['Bukidnon','Camiguin','Lanao del Norte','Misamis Occidental','Misamis Oriental'],
        'Region XI – Davao Region':                   ['Davao de Oro','Davao del Norte','Davao del Sur','Davao Occidental','Davao Oriental'],
        'Region XII – SOCCSKSARGEN':                  ['Cotabato','Sarangani','South Cotabato','Sultan Kudarat'],
        'Region XIII – Caraga':                       ['Agusan del Norte','Agusan del Sur','Dinagat Islands','Surigao del Norte','Surigao del Sur'],
        'BARMM – Bangsamoro Autonomous Region':       ['Basilan','Lanao del Sur','Maguindanao del Norte','Maguindanao del Sur','Sulu','Tawi-Tawi'],
    };

    window.orgsModule = function(tenantId) {
        return {
            orgs: [],
            loading: true,
            _fetchSeq: 0,
            search: '',
            filterIsland: '',
            filterRegion: '',
            filterProvince: '',
            filterType: '',
            filterHasDeal: '',
            meta: { total: 0, page: 1, per_page: 25, last_page: 1 },
            showModal: false,
            saving: false,
            formError: '',
            editId: null,
            form: { name: '', industry: '', website: '', address: '', city: '', country: 'Philippines', notes: '' },

            get regionList() {
                return Object.keys(PH_REGIONS).sort();
            },

            // Regions filtered by selected island group
            get filteredRegionList() {
                if (!this.filterIsland) return this.regionList;
                const prefixes = ISLAND_REGIONS[this.filterIsland] || [];
                return this.regionList.filter(r => prefixes.some(p => r.startsWith(p)));
            },

            get provinceOptions() {
                if (!this.filterRegion) return [];
                return (PH_REGIONS[this.filterRegion] || []).slice().sort();
            },

            async init() {
                await this.fetch();
            },

            async fetch() {
                // Every filter change fires a request; responses can land out of order
                // (a slow region-wide query resolving after a narrower province one would
                // repaint the list with the wrong province's municipalities). Only the
                // newest request is allowed to write state.
                const seq = ++this._fetchSeq;
                this.loading = true;
                try {
                    const p = new URLSearchParams({ tenant_id: tenantId, page: this.meta.page, per_page: this.meta.per_page });
                    if (this.search)              p.set('search',       this.search);
                    if (this.filterIsland)        p.set('island_group', this.filterIsland);
                    if (this.filterRegion)        p.set('region',       this.filterRegion);
                    if (this.filterProvince)      p.set('province',     this.filterProvince);
                    if (this.filterType)          p.set('lgu_type',     this.filterType);
                    if (this.filterHasDeal !== '') p.set('has_deal',    this.filterHasDeal);
                    const res  = await fetch(`/api/organizations?${p}`, {
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const json = await res.json();
                    if (seq !== this._fetchSeq) return;   // superseded — drop this response
                    this.orgs  = (json.data || []).map(o => {
                        let _d = {};
                        try { _d = typeof o.data === 'string' ? JSON.parse(o.data || '{}') : (o.data || {}); } catch(e) {}
                        return { ...o, _d };
                    });
                    this.meta = {
                        total:     json.total     ?? 0,
                        page:      json.page      ?? 1,
                        per_page:  json.per_page  ?? 25,
                        last_page: json.last_page ?? 1,
                    };
                } catch(e) {
                    if (seq !== this._fetchSeq) return;
                    this.orgs = [];
                }
                if (seq !== this._fetchSeq) return;       // keep the spinner up for the live request
                this.loading = false;
            },

            resetAndFetch() {
                this.meta.page = 1;
                return this.fetch();
            },

            goPage(n) {
                if (n < 1 || n > this.meta.last_page) return;
                this.meta.page = n;
                return this.fetch();
            },

            pageNumbers() {
                const total = this.meta.last_page;
                const cur   = this.meta.page;
                if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
                const set = new Set([1, total, cur]);
                for (let i = cur - 2; i <= cur + 2; i++) if (i > 0 && i <= total) set.add(i);
                return [...set].sort((a, b) => a - b);
            },

            fmtValue(v) {
                const n = Math.round(Number(v) || 0);
                if (n >= 1000000) return '₱' + (n / 1000000).toFixed(1) + 'M';
                if (n >= 1000)    return '₱' + Math.round(n / 1000) + 'K';
                return n > 0 ? '₱' + n.toLocaleString('en') : '—';
            },

            openAdd() {
                this.editId = null;
                this.form = { name: '', industry: '', website: '', address: '', city: '', country: 'Philippines', notes: '' };
                this.formError = '';
                this.showModal = true;
            },

            openEdit(o) {
                this.editId = o.id;
                this.form = {
                    name:     o.name     || '',
                    industry: o.industry || '',
                    website:  o.website  || '',
                    address:  o.address  || '',
                    city:     o.city     || '',
                    country:  o.country  || 'Philippines',
                    notes:    o.notes    || '',
                };
                this.formError = '';
                this.showModal = true;
            },

            async saveOrg() {
                if (!this.form.name.trim()) { this.formError = 'Organization name is required.'; return; }
                this.saving = true; this.formError = '';
                try {
                    const url    = this.editId ? `/api/organizations/${this.editId}` : '/api/organizations';
                    const method = this.editId ? 'PUT' : 'POST';
                    const body   = this.editId ? { ...this.form } : { ...this.form, tenant_id: tenantId };
                    const res    = await fetch(url, {
                        method,
                        credentials: 'same-origin',
                        headers:     { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'X-Requested-With': 'XMLHttpRequest' },
                        body:        JSON.stringify(body),
                    });
                    const data = await res.json();
                    if (data.id) {
                        this.showModal = false;
                        this.$dispatch('show-toast', { type: 'success', message: this.editId ? 'Organization updated.' : 'Organization added.' });
                        await this.fetch();
                    } else {
                        this.formError = data.message || 'Failed to save organization.';
                    }
                } catch(e) { this.formError = 'Network error. Please try again.'; }
                finally { this.saving = false; }
            },

            async deleteOrg(id) {
                if (!confirm('Delete this organization? Contacts linked to it will become unaffiliated.')) return;
                try {
                    await fetch(`/api/organizations/${id}`, {
                        method:      'DELETE',
                        credentials: 'same-origin',
                        headers:     { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    this.$dispatch('show-toast', { type: 'success', message: 'Organization deleted.' });
                    await this.fetch();
                } catch(e) {
                    this.$dispatch('show-toast', { type: 'error', message: 'Failed to delete organization.' });
                }
            },
        };
    };
})();
</script>
@endsection
