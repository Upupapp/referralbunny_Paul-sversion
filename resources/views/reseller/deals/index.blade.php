@extends('layouts.reseller')
@section('title', 'My Deals')
@section('nav') @include('reseller._nav') @endsection

@section('topbar-actions')
    <a href="{{ route('reseller.deals.imports', $tenant->id) }}"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
        </svg>
        <span class="hidden sm:inline">Import Deals</span>
    </a>
    <button onclick="window.dispatchEvent(new CustomEvent('open-claim-deal'))" class="rs-btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        <span class="hidden sm:inline">Create a Deal</span>
    </button>
@endsection

@section('content')
<div class="space-y-5"
     x-data="resellerDeals('{{ $tenant->id }}', '{{ addslashes($reseller->name) }}')"
     x-init="init()"
     @open-claim-deal.window="showClaim = true">

    {{-- Search + filters --}}
    <div class="card space-y-3">
        <div class="search-group">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" x-model="search" @input.debounce.300ms="applyFilters()" placeholder="Search my deals…">
            <button x-show="search" @click="search=''; applyFilters()" class="text-gray-400 hover:text-gray-600 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="filter-bar">
            {{-- Archived toggle — mutually exclusive with status/stage filters --}}
            <button @click="toggleArchived()"
                    :class="filterArchived ? 'active !border-gray-400 !text-gray-700' : ''"
                    class="filter-pill gap-1.5">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1 12a2 2 0 002 2h8a2 2 0 002-2L19 8"/></svg>
                <span>Archived</span>
                <span x-show="archivedLoaded && archivedLeads.length > 0"
                      class="inline-flex items-center justify-center w-4 h-4 text-[9px] font-bold rounded-full"
                      :class="filterArchived ? 'bg-gray-600 text-white' : 'bg-gray-200 text-gray-600'"
                      x-text="archivedLeads.length"></span>
            </button>
            <label class="filter-pill" :class="(filterStatus && !filterArchived) ? 'active' : ''" x-show="!filterArchived">
                <select x-effect="$el.value = filterStatus" @change="filterStatus = $event.target.value; applyFilters()">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="expiring">Expiring</option>
                    <option value="expired">Expired</option>
                </select>
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>
            <label class="filter-pill" :class="(filterStage && !filterArchived) ? 'active' : ''" x-show="!filterArchived">
                <select x-effect="$el.value = filterStage" @change="filterStage = $event.target.value; applyFilters()">
                    <option value="">All Stages</option>
                    <option value="introduction">Introduction</option>
                    <option value="presentation">Presentation</option>
                    <option value="contract_sent">Contract Sent</option>
                    <option value="signed">Signed</option>
                    <option value="paid">Paid</option>
                </select>
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>
            {{-- Partner filter — only shown when at least one deal has a partner and not in archived mode --}}
            <template x-if="uniquePartners.length > 0 && !filterArchived">
            <label class="filter-pill" :class="filterPartner ? 'active' : ''">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/></svg>
                <select x-effect="$el.value = filterPartner" @change="filterPartner = $event.target.value; applyFilters()">
                    <option value="">All Partners</option>
                    <template x-for="p in uniquePartners" :key="p">
                        <option :value="p.toLowerCase()" x-text="p"></option>
                    </template>
                </select>
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>
            </template>
            <template x-if="filterStatus || filterStage || filterPartner || search">
                <button @click="filterStatus=''; filterStage=''; filterPartner=''; search=''; applyFilters()"
                        class="filter-pill !border-red-200 !text-red-500 hover:!bg-red-50">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Clear
                </button>
            </template>

        </div>
    </div>

    {{-- Table --}}
    <div class="card p-0 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
            <p class="text-sm font-semibold" style="color:#1E1B4B">
                <span x-text="filtered.length"></span> deal<span x-show="filtered.length !== 1">s</span>
            </p>
        </div>

        <div x-show="loading" class="flex items-center justify-center py-10 gap-3 text-gray-400">
            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            <span class="text-sm">Loading…</span>
        </div>

        <div x-show="!loading" class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="table-head">
                        <th>Organization</th>
                        <th>Stage</th>
                        <th>Value</th>
                        <th class="hidden md:table-cell">Partner</th>
                        <th class="hidden sm:table-cell">
                            <div class="inline-flex items-center gap-1">
                                Commission
                                <div x-data="{ open: false }" class="relative">
                                    <button @click="open = !open" @click.outside="open = false" @keydown.escape.window="open = false"
                                            class="w-3.5 h-3.5 flex items-center justify-center text-gray-300 hover:text-gray-500 transition-colors" aria-label="About commission status">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </button>
                                    <div x-show="open" x-cloak x-transition
                                         class="absolute left-0 top-6 z-50 w-64 bg-white border border-gray-100 rounded-xl shadow-xl p-3 text-xs text-gray-500 leading-relaxed font-normal">
                                        <strong class="block mb-2 text-gray-700">Commission Status</strong>
                                        <p><strong class="text-gray-600">Pending</strong> — Estimate from an active deal. May change.</p>
                                        <p class="mt-1"><strong class="text-gray-600">Locked</strong> — Confirmed at the Signed stage. Awaiting payout.</p>
                                        <p class="mt-1"><strong class="text-gray-600">Paid</strong> — Released and finalized.</p>
                                    </div>
                                </div>
                            </div>
                        </th>
                        <th class="hidden sm:table-cell text-right">My Commission</th>
                        <th>Status</th>
                        <th>Days Left</th>
                        <th class="hidden lg:table-cell">Last Activity</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="filtered.length === 0 && !loading">
                        <tr><td colspan="8" class="py-14 text-center">
                            <img :src="filterArchived ? '/images/mascots/r-bunny-sleeping.webp' : '/images/mascots/r-bunny-sleeping.webp'" alt="" class="w-12 h-12 object-contain mx-auto mb-3 opacity-50">
                            <template x-if="filterArchived">
                                <div>
                                    <p class="text-gray-400 text-sm font-medium">No archived deals</p>
                                    <p class="text-xs text-gray-400 mt-1">Archived deals will appear here once approved by an admin.</p>
                                </div>
                            </template>
                            <template x-if="!filterArchived">
                                <div>
                                    <p class="text-gray-400 text-sm font-medium">No deals yet</p>
                                    <p class="text-xs text-gray-400 mt-1">Claim your first municipality to get started.</p>
                                    <button @click="showClaim = true" class="rs-btn-primary mt-4 text-xs">Create a Deal</button>
                                </div>
                            </template>
                        </td></tr>
                    </template>
                    <template x-for="d in filtered" :key="d.id">
                        <tr class="table-row">
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-xs font-bold shrink-0"
                                         :style="`background:${stageColor(d.stage)}`"
                                         x-text="(d.name||'?').slice(0,2).toUpperCase()"></div>
                                    <div class="min-w-0">
                                        <a :href="`/reseller/{{ $tenant->id }}/deals/${d.id}`"
                                           class="font-medium text-sm truncate block hover:underline" style="color:#1E1B4B" x-text="d.name"></a>
                                        <p class="text-xs text-gray-400 truncate" x-text="d.data?.province || ''"></p>
                                        {{-- Partner info visible on mobile (md and below hides the Partner column) --}}
                                        <div class="md:hidden mt-0.5" x-show="(d.partners||[]).length > 0">
                                            <span class="text-xs text-indigo-600 font-medium" x-text="partnerLabel(d)"></span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-sm text-gray-600 capitalize" x-text="(d.stage||'').replace('_',' ')"></td>
                            <td class="text-sm font-bold tabular-nums" style="color:#1E1B4B" x-text="d.deal_value ? '₱'+Number(d.deal_value).toLocaleString() : '₱0'"></td>
                            {{-- Partner column — desktop only --}}
                            <td class="hidden md:table-cell">
                                <template x-if="(d.partners||[]).length === 0">
                                    <span class="text-xs text-gray-400 italic">No partner added</span>
                                </template>
                                <template x-if="(d.partners||[]).length > 0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="text-xs font-medium text-gray-700 truncate max-w-[120px]"
                                              x-text="d.partners[0].display_name || d.partners[0].email || 'Partner'"></span>
                                        <template x-if="d.partners.length > 1">
                                            <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-indigo-50 text-indigo-600 font-semibold shrink-0"
                                                  x-text="'+' + (d.partners.length - 1) + ' more'"></span>
                                        </template>
                                    </div>
                                </template>
                            </td>
                            <td class="hidden sm:table-cell">
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium capitalize"
                                      :class="{'bg-violet-100 text-violet-700': d.commission_status==='pending','bg-amber-100 text-amber-700': d.commission_status==='locked','bg-emerald-100 text-emerald-700': d.commission_status==='paid'}"
                                      x-text="d.commission_status || 'pending'"></span>
                            </td>
                            {{-- My Commission (net of partner shares) --}}
                            <td class="hidden sm:table-cell text-right">
                                <template x-if="(d.referrer_pool_remaining ?? d.commission_pool ?? 0) > 0">
                                    <div>
                                        <p class="text-sm font-bold tabular-nums" style="color:#0D9488"
                                           x-text="'₱' + Math.round(d.referrer_pool_remaining ?? d.commission_pool ?? 0).toLocaleString()"></p>
                                        <template x-if="(d.partner_commission_total ?? 0) > 0">
                                            <p class="text-[10px] text-purple-400 tabular-nums"
                                               x-text="'₱' + Math.round(d.partner_commission_total).toLocaleString() + ' partners'"></p>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="!(d.referrer_pool_remaining ?? d.commission_pool ?? 0)">
                                    <span class="text-xs text-gray-300">—</span>
                                </template>
                            </td>
                            <td>
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium capitalize"
                                      :class="{'bg-emerald-100 text-emerald-700':d.status==='active','bg-amber-100 text-amber-700':d.status==='expiring','bg-red-100 text-red-600':d.status==='expired','bg-gray-200 text-gray-600':d.status==='archived','bg-gray-100 text-gray-500':!['active','expiring','expired','archived'].includes(d.status||'')}"
                                      x-text="d.status || 'active'"></span>
                            </td>
                            <td x-show="d.stage !== 'paid'">
                                <template x-if="d.days_left !== null && d.days_left !== undefined">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold"
                                          :class="{
                                              'bg-red-100 text-red-700':     d.days_left <= 3,
                                              'bg-amber-100 text-amber-700': d.days_left > 3 && d.days_left <= 7,
                                              'bg-blue-50 text-blue-600':    d.days_left > 7,
                                          }">
                                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <span x-text="d.days_left <= 0 ? 'Overdue' : (d.days_left + 'd')"></span>
                                    </span>
                                </template>
                                <template x-if="d.days_left === null || d.days_left === undefined">
                                    <span class="text-xs text-gray-400">—</span>
                                </template>
                            </td>
                            <td class="hidden lg:table-cell">
                                <span class="text-xs text-gray-500"
                                      :title="d.last_activity_at ? new Date(d.last_activity_at).toLocaleString('en-PH') : ''"
                                      x-text="timeAgo(d.last_activity_at || d.updated_at)"></span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── CLAIM DEAL MODAL (LGU IDS: browse by province) ──── --}}
    <div x-show="showClaim" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4"
         @keydown.escape.window="showClaim = false; claimStep = 1; dealMode = 'standard'; selectedOrg = null; customForm = { name: '', org_name: '', stage: 'introduction', deal_value: '' }; customError = ''">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] flex flex-col" @click.stop>

            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 shrink-0">
                <div>
                    <h3 class="font-semibold" style="color:#1E1B4B">
                        <span x-show="dealMode === 'standard' && claimStep === 1">Choose a Municipality</span>
                        <span x-show="dealMode === 'standard' && claimStep === 2">Confirm Your Claim</span>
                        <span x-show="dealMode === 'custom'">Custom Deal</span>
                    </h3>
                    <p class="text-xs text-gray-400 mt-0.5" x-show="dealMode === 'standard' && claimStep === 1">Select a province, then pick an available municipality.</p>
                </div>
                <button @click="showClaim = false; claimStep = 1; dealMode = 'standard'; selectedOrg = null; customError = ''" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Mode toggle --}}
            <div class="px-6 pt-3 pb-0 shrink-0" x-show="claimStep === 1">
                <div class="flex rounded-xl overflow-hidden border border-gray-200 text-xs font-semibold">
                    <button type="button"
                            @click="dealMode = 'standard'"
                            :class="dealMode === 'standard' ? 'bg-[#7B61FF] text-white' : 'bg-white text-gray-500 hover:bg-gray-50'"
                            class="flex-1 py-2 transition-colors">
                        Select Municipality
                    </button>
                    <button type="button"
                            @click="dealMode = 'custom'; customError = ''"
                            :class="dealMode === 'custom' ? 'bg-[#7B61FF] text-white' : 'bg-white text-gray-500 hover:bg-gray-50'"
                            class="flex-1 py-2 transition-colors border-l border-gray-200">
                        Custom Deal
                    </button>
                </div>
            </div>

            {{-- Step 1: Browse --}}
            <div x-show="claimStep === 1 && dealMode === 'standard'" class="flex flex-col flex-1 overflow-hidden">
                {{-- Province selector --}}
                <div class="px-6 pt-4 pb-3 shrink-0">
                    <label class="form-label">Province</label>
                    <select x-model="claimProvince" @change="loadAvailableOrgs()" class="form-input">
                        <option value="">Select a province…</option>
                        @foreach(['Abra','Agusan del Norte','Agusan del Sur','Aklan','Albay','Antique','Apayao','Aurora','Basilan','Bataan','Batanes','Batangas','Benguet','Biliran','Bohol','Bukidnon','Bulacan','Cagayan','Camarines Norte','Camarines Sur','Camiguin','Capiz','Catanduanes','Cavite','Cebu','Cotabato','Davao de Oro','Davao del Norte','Davao del Sur','Davao Occidental','Davao Oriental','Dinagat Islands','Eastern Samar','Guimaras','Ifugao','Ilocos Norte','Ilocos Sur','Iloilo','Isabela','Kalinga','La Union','Laguna','Lanao del Norte','Lanao del Sur','Leyte','Maguindanao del Norte','Maguindanao del Sur','Marinduque','Masbate','Metro Manila','Misamis Occidental','Misamis Oriental','Mountain Province','Negros Occidental','Negros Oriental','Northern Samar','Nueva Ecija','Nueva Vizcaya','Occidental Mindoro','Oriental Mindoro','Palawan','Pampanga','Pangasinan','Quezon','Quirino','Rizal','Romblon','Samar','Sarangani','Siquijor','Sorsogon','South Cotabato','Southern Leyte','Sultan Kudarat','Sulu','Surigao del Norte','Surigao del Sur','Tarlac','Tawi-Tawi','Zambales','Zamboanga del Norte','Zamboanga del Sur','Zamboanga Sibugay'] as $prov)
                        <option value="{{ $prov }}">{{ $prov }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Org list --}}
                <div class="flex-1 overflow-y-auto px-6 pb-4">
                    <div x-show="loadingOrgs" class="flex items-center justify-center py-8 text-gray-400 gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span class="text-sm">Loading municipalities…</span>
                    </div>

                    <div x-show="!loadingOrgs && claimProvince && availableOrgs.length === 0" class="text-center py-8">
                        <p class="text-sm text-gray-400">No municipalities found for this province.</p>
                        <p class="text-xs text-gray-400 mt-1">Try selecting a different province.</p>
                    </div>
                    <div x-show="!loadingOrgs && availableOrgs.length > 0 && availableOrgs.every(o => o.claimed)" class="px-3 py-3 rounded-xl text-center" style="background:#FFFBEB;border:1px solid #FDE68A">
                        <p class="text-sm font-medium text-amber-700">All municipalities in this province are currently claimed.</p>
                        <p class="text-xs text-amber-600 mt-0.5">Try another province or check back when a deal expires.</p>
                    </div>

                    <div x-show="!loadingOrgs && availableOrgs.length > 0" class="space-y-1.5 mt-1">
                        <template x-for="org in availableOrgs" :key="org.id">
                            <button type="button"
                                    @click="!org.claimed && selectOrgToClaim(org)"
                                    :class="org.claimed ? 'opacity-50 cursor-not-allowed bg-gray-50' : 'hover:bg-teal-50 hover:border-teal-200 cursor-pointer'"
                                    class="w-full flex items-center gap-3 p-3 rounded-xl border border-gray-100 text-left transition-all">
                                <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white text-xs font-bold shrink-0"
                                     :style="`background:${org.lgu_type === 'City' ? '#7B61FF' : '#3B82F6'}`"
                                     x-text="(org.name||'').slice(0,2).toUpperCase()"></div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold truncate" style="color:#1E1B4B" x-text="org.name"></p>
                                    <p class="text-xs text-gray-400 mt-0.5" x-text="org.lgu_type || 'LGU'"></p>
                                </div>
                                <div class="shrink-0">
                                    <span x-show="org.claimed" class="text-xs px-2 py-0.5 rounded-full font-medium" style="background:#F3F4F6;color:#9CA3AF">Claimed</span>
                                    <span x-show="!org.claimed" class="text-xs px-2.5 py-1 rounded-full font-semibold" style="background:#CCFBF1;color:#0D9488">Available</span>
                                </div>
                            </button>
                        </template>
                    </div>

                    <div x-show="!claimProvince && availableOrgs.length === 0" class="text-center py-8">
                        <p class="text-sm text-gray-400">Select a province to see available municipalities.</p>
                    </div>
                </div>
            </div>

            {{-- Custom deal form --}}
            <div x-show="dealMode === 'custom'" class="p-6 space-y-4 flex-1 overflow-y-auto">
                <div class="px-3 py-2.5 rounded-xl text-xs leading-relaxed" style="background:#EDE9FE;color:#5B21B6">
                    Create a deal with a custom name and organization — not linked to a specific municipality.
                </div>
                <div>
                    <label class="form-label">Organization Name *</label>
                    <input type="text" x-model="customForm.org_name" class="form-input" placeholder="e.g. Manila City Hall">
                    <p class="text-xs text-gray-400 mt-1">A new organization will be created with this name.</p>
                </div>
                <div>
                    <label class="form-label">Deal Name *</label>
                    <input type="text" x-model="customForm.name" class="form-input" placeholder="e.g. City of Manila — ID System">
                </div>
                <div>
                    <label class="form-label">Stage</label>
                    <select x-model="customForm.stage" class="form-input">
                        <option value="introduction">Introduction</option>
                        <option value="presentation">Presentation</option>
                        <option value="contract_sent">Contract Sent</option>
                        <option value="signed">Signed</option>
                        <option value="paid">Paid</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Deal Value (₱) <span class="text-gray-400 font-normal">optional</span></label>
                    <input type="number" x-model="customForm.deal_value" class="form-input" placeholder="0">
                </div>
                <p x-show="customError" class="text-xs text-red-600 font-medium" x-text="customError"></p>
                <button @click="confirmCustomDeal()" :disabled="savingCustom"
                        class="rs-btn-primary w-full justify-center">
                    <svg x-show="savingCustom" class="w-4 h-4 animate-spin shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="savingCustom ? 'Creating…' : 'Create Custom Deal'"></span>
                </button>
            </div>

            {{-- Step 2: Confirm claim --}}
            <div x-show="claimStep === 2 && dealMode === 'standard'" class="p-6 flex-1">
                <div x-show="selectedOrg" class="text-center mb-6">
                    <div class="w-16 h-16 rounded-2xl flex items-center justify-center text-white text-xl font-bold mx-auto mb-3"
                         :style="`background:${selectedOrg?.lgu_type === 'City' ? '#7B61FF' : '#0D9488'}`"
                         x-text="(selectedOrg?.name||'').slice(0,2).toUpperCase()"></div>
                    <h3 class="text-lg font-bold" style="color:#1E1B4B" x-text="selectedOrg?.name"></h3>
                    <p class="text-sm text-gray-400 mt-0.5" x-text="selectedOrg?.province"></p>
                </div>

                <div class="px-4 py-3 rounded-xl mb-4 text-center" style="background:#F0FDFA;border:1px solid #99F6E4">
                    <p class="text-sm font-semibold" style="color:#0D9488">Take this challenge on! 💪</p>
                    <p class="text-xs text-gray-500 mt-1" x-text="claimPrompt"></p>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="form-label">Stage</label>
                        <select x-model="claimForm.stage" class="form-input">
                            <option value="introduction">Introduction</option>
                            <option value="presentation">Presentation</option>
                            <option value="contract_sent">Contract Sent</option>
                            <option value="signed">Signed</option>
                            <option value="paid">Paid</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Deal Value (₱) <span class="text-gray-400 font-normal">optional</span></label>
                        <input type="number" x-model="claimForm.deal_value" class="form-input" placeholder="0">
                    </div>
                </div>

                <p x-show="claimError" class="text-xs text-red-600 font-medium mt-3" x-text="claimError"></p>

                <div class="flex gap-3 mt-5">
                    <button @click="claimStep = 1; selectedOrg = null" class="btn-secondary flex-1">Back</button>
                    <button @click="confirmClaim()" :disabled="saving" class="rs-btn-primary flex-1 justify-center"
                            x-text="saving ? 'Claiming…' : 'Claim This Deal'"></button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── SUCCESS PROMPT ──────────────────────────────────── --}}
    <div x-show="showSuccessPrompt" x-cloak
         class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm p-8 text-center" @click.stop>
            <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" style="background:#CCFBF1">
                <svg class="w-8 h-8" style="color:#0D9488" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </div>
            <h2 class="text-xl font-bold mb-2" style="color:#1E1B4B">Deal Claimed!</h2>
            <p class="text-gray-500 text-sm mb-1" x-text="successOrgName"></p>
            <p class="text-base font-semibold mb-6" style="color:#0D9488" x-text="successPrompt"></p>
            <button @click="showSuccessPrompt = false" class="rs-btn-primary w-full justify-center">
                Let's Go 🚀
            </button>
        </div>
    </div>

</div>

<script>
const CLAIM_PROMPTS = [
    "You're now the champion for this municipality. Show them what you've got!",
    "The clock starts now. Move this deal through the pipeline.",
    "Every big win starts with a single claim. This is yours.",
    "This municipality is counting on you. Don't let them down.",
    "Your territory, your responsibility. Make it happen.",
    "One step closer to your commission. Keep the momentum going!",
    "You've staked your claim. Now it's time to deliver.",
];

function resellerDeals(tenantId, resellerName) {
    return {
        leads: [], filtered: [], loading: true,
        search: '', filterStatus: '', filterStage: '', filterPartner: '',
        filterArchived: false, archivedLeads: [], archivedLoaded: false,
        showClaim: false, claimStep: 1, dealMode: 'standard',
        claimProvince: '', availableOrgs: [], loadingOrgs: false,
        selectedOrg: null, saving: false, claimError: '',
        claimForm: { stage: 'introduction', deal_value: '' },
        claimPrompt: '',
        customForm: { name: '', org_name: '', stage: 'introduction', deal_value: '' },
        savingCustom: false, customError: '',
        showSuccessPrompt: false, successPrompt: '', successOrgName: '',

        stageColors: { introduction:'#9CA3AF', presentation:'#3B82F6', contract_sent:'#F59E0B', signed:'#8B5CF6', paid:'#10B981' },
        stageColor(s) { return this.stageColors[s] || '#9CA3AF'; },

        async init() {
            const urlParams = new URLSearchParams(window.location.search);
            const preStatus = urlParams.get('status');
            if (['expiring','expired','active'].includes(preStatus)) this.filterStatus = preStatus;
            try {
                const res  = await fetch(`/api/leads?tenant_id=${tenantId}&reseller_name=${encodeURIComponent(resellerName)}&include_partners=1`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                this.leads = Array.isArray(data) ? data : (data.data || []);
            } catch(e) { this.leads = []; }
            this.applyFilters();
            this.loading = false;
        },

        timeAgo(iso) {
            if (!iso) return '—';
            const s = Math.floor((Date.now() - new Date(iso)) / 1000);
            if (s < 60)     return 'just now';
            if (s < 3600)   return Math.floor(s / 60) + 'm ago';
            if (s < 86400)  return Math.floor(s / 3600) + 'h ago';
            if (s < 604800) return Math.floor(s / 86400) + 'd ago';
            return new Date(iso).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
        },

        // Returns a compact partner label for mobile secondary line.
        partnerLabel(d) {
            const p = d.partners || [];
            if (p.length === 0) return '';
            const first = p[0].display_name || p[0].email || 'Partner';
            return p.length === 1 ? first : first + ' +' + (p.length - 1) + ' more';
        },

        get uniquePartners() {
            const names = new Set();
            this.leads.forEach(d => (d.partners || []).forEach(p => {
                const label = p.display_name || p.email;
                if (label) names.add(label);
            }));
            return [...names].sort();
        },

        applyFilters() {
            const q    = this.search.toLowerCase();
            const fp   = this.filterPartner.toLowerCase();
            const pool = this.filterArchived ? this.archivedLeads : this.leads;
            this.filtered = pool.filter(d => {
                const matchQ  = !q  || (d.name||'').toLowerCase().includes(q);
                const matchSt = this.filterArchived || !this.filterStatus || d.status === this.filterStatus;
                const matchSg = this.filterArchived || !this.filterStage  || d.stage  === this.filterStage;
                const matchPa = !fp || (d.partners || []).some(p => (p.display_name||p.email||'').toLowerCase() === fp);
                return matchQ && matchSt && matchSg && matchPa;
            });
        },

        async toggleArchived() {
            this.filterArchived = !this.filterArchived;
            this.filterStatus = ''; this.filterStage = ''; this.filterPartner = '';
            if (this.filterArchived && !this.archivedLoaded) {
                this.loading = true;
                try {
                    const res  = await fetch(`/api/leads?tenant_id=${tenantId}&reseller_name=${encodeURIComponent(resellerName)}&include_partners=1&status=archived`, {
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = await res.json();
                    this.archivedLeads = Array.isArray(data) ? data : (data.data || []);
                    this.archivedLoaded = true;
                } catch(e) { this.archivedLeads = []; }
                this.loading = false;
            }
            this.applyFilters();
        },

        async loadAvailableOrgs() {
            if (!this.claimProvince) { this.availableOrgs = []; return; }
            this.loadingOrgs = true;
            try {
                const res  = await fetch(`/api/organizations/available?tenant_id=${tenantId}&province=${encodeURIComponent(this.claimProvince)}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                this.availableOrgs = await res.json();
            } catch(e) { this.availableOrgs = []; }
            this.loadingOrgs = false;
        },

        selectOrgToClaim(org) {
            this.selectedOrg  = org;
            this.claimPrompt  = CLAIM_PROMPTS[Math.floor(Math.random() * CLAIM_PROMPTS.length)];
            this.claimError   = '';
            this.claimStep    = 2;
        },

        async confirmClaim() {
            if (!this.selectedOrg) return;
            this.claimError = '';
            this.saving = true;
            try {
                const res  = await fetch('/api/leads', {
                    method:      'POST',
                    credentials: 'same-origin',
                    headers:     { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({
                        tenant_id:       tenantId,
                        reseller_name:   resellerName,
                        organization_id: this.selectedOrg.id,
                        name:            this.selectedOrg.name,
                        stage:           this.claimForm.stage,
                        deal_value:      this.claimForm.deal_value || 0,
                        data: {
                            province:    this.selectedOrg.province,
                            municipality: (this.selectedOrg.name||'').replace(/^(?:Municipality|City) of\s+/i, ''),
                            lgu_type:    this.selectedOrg.lgu_type,
                        },
                    }),
                });
                const lead = await res.json();

                if (!res.ok) {
                    this.claimError = lead.message || 'Failed to claim deal.';
                    return;
                }

                this.leads.unshift(lead);
                this.applyFilters();
                this.showClaim    = false;
                this.claimStep    = 1;
                this.selectedOrg  = null;
                this.claimProvince = '';
                this.availableOrgs = [];
                this.claimForm    = { stage: 'introduction', deal_value: '' };

                this.successOrgName  = lead.name;
                this.successPrompt   = CLAIM_PROMPTS[Math.floor(Math.random() * CLAIM_PROMPTS.length)];
                this.showSuccessPrompt = true;
            } catch(e) {
                this.claimError = 'Network error. Please try again.';
            } finally {
                this.saving = false;
            }
        },

        async confirmCustomDeal() {
            this.customError = '';
            if (!this.customForm.org_name.trim()) { this.customError = 'Organization name is required.'; return; }
            if (!this.customForm.name.trim())     { this.customError = 'Deal name is required.'; return; }
            this.savingCustom = true;
            const csrf    = document.querySelector('meta[name=csrf-token]').content;
            const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' };
            try {
                // 1. Create the organization
                const orgRes = await fetch('/api/organizations', {
                    method: 'POST', credentials: 'same-origin', headers,
                    body: JSON.stringify({ tenant_id: tenantId, name: this.customForm.org_name.trim() }),
                });
                const org = await orgRes.json();
                if (!orgRes.ok) { this.customError = org.message || 'Failed to create organization.'; return; }

                // 2. Create the deal linked to the new org
                const res = await fetch('/api/leads', {
                    method: 'POST', credentials: 'same-origin', headers,
                    body: JSON.stringify({
                        tenant_id:       tenantId,
                        reseller_name:   resellerName,
                        organization_id: org.id,
                        name:            this.customForm.name.trim(),
                        stage:           this.customForm.stage,
                        deal_value:      this.customForm.deal_value || 0,
                        data:            { custom: true },
                    }),
                });
                const lead = await res.json();
                if (!res.ok) { this.customError = lead.message || 'Failed to create deal.'; return; }

                this.leads.unshift(lead);
                this.applyFilters();
                this.showClaim   = false;
                this.dealMode    = 'standard';
                this.customForm  = { name: '', org_name: '', stage: 'introduction', deal_value: '' };
                this.customError = '';
                this.successOrgName  = lead.name;
                this.successPrompt   = CLAIM_PROMPTS[Math.floor(Math.random() * CLAIM_PROMPTS.length)];
                this.showSuccessPrompt = true;
            } catch(e) {
                this.customError = 'Network error. Please try again.';
            } finally {
                this.savingCustom = false;
            }
        },
    };
}
</script>
@endsection
