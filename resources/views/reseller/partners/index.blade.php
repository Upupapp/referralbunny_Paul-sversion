@extends('layouts.reseller')
@section('title', 'My Partners')
@section('nav') @include('reseller._nav') @endsection

@section('content')
<div
    x-data="partnersPage()"
    x-init="init()"
    class="space-y-5 max-w-5xl mx-auto"
>

    {{-- ── Header ─────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[#1E1B4B]">My Partners</h1>
            <p class="text-sm text-gray-400 mt-0.5">Partners connected to your assigned deals.</p>
        </div>
        @if($myDeals->isNotEmpty())
        <button @click="openAddModal()"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white transition-all hover:shadow-md shrink-0"
                style="background:linear-gradient(135deg,#0D9488,#14B8A6)">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add Partner
        </button>
        @endif
    </div>

    {{-- ── KPI Row ─────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">

        {{-- Total Partners --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Total Partners</p>
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" @click.outside="open = false" @keydown.escape.window="open = false"
                            class="w-5 h-5 rounded-full flex items-center justify-center text-gray-300 hover:text-gray-500 transition-colors" aria-label="Info">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </button>
                    <div x-show="open" x-cloak x-transition class="absolute right-0 top-6 z-30 w-56 bg-white border border-gray-100 rounded-xl shadow-lg p-3 text-xs text-gray-500">
                        Partners connected to your assigned deals.
                    </div>
                </div>
            </div>
            <p class="text-2xl font-bold text-[#1E1B4B]">{{ $totalPartners }}</p>
        </div>

        {{-- Active Partners --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Active</p>
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" @click.outside="open = false" @keydown.escape.window="open = false"
                            class="w-5 h-5 rounded-full flex items-center justify-center text-gray-300 hover:text-gray-500 transition-colors" aria-label="Info">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </button>
                    <div x-show="open" x-cloak x-transition class="absolute right-0 top-6 z-30 w-56 bg-white border border-gray-100 rounded-xl shadow-lg p-3 text-xs text-gray-500">
                        Partners who have accepted their invite or are active in the system.
                    </div>
                </div>
            </div>
            <p class="text-2xl font-bold text-green-600">{{ $activePartners }}</p>
        </div>

        {{-- Pending Invites --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Pending Invites</p>
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" @click.outside="open = false" @keydown.escape.window="open = false"
                            class="w-5 h-5 rounded-full flex items-center justify-center text-gray-300 hover:text-gray-500 transition-colors" aria-label="Info">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </button>
                    <div x-show="open" x-cloak x-transition class="absolute right-0 top-6 z-30 w-56 bg-white border border-gray-100 rounded-xl shadow-lg p-3 text-xs text-gray-500">
                        Partners who were invited but have not activated their account yet.
                    </div>
                </div>
            </div>
            <p class="text-2xl font-bold text-amber-500">{{ $pendingInvites }}</p>
        </div>

        {{-- Partners Commission --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Partners Commission</p>
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" @click.outside="open = false" @keydown.escape.window="open = false"
                            class="w-5 h-5 rounded-full flex items-center justify-center text-gray-300 hover:text-gray-500 transition-colors" aria-label="Info">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </button>
                    <div x-show="open" x-cloak x-transition class="absolute right-0 top-6 z-30 w-56 bg-white border border-gray-100 rounded-xl shadow-lg p-3 text-xs text-gray-500">
                        The total estimated commission allocated to Partners across your assigned deals.
                    </div>
                </div>
            </div>
            <p class="text-2xl font-bold text-[#0D9488]">₱{{ number_format($totalCommission, 0) }}</p>
        </div>

    </div>

    {{-- ── Search + Filter ────────────────────────────────────── --}}
    @if($partners->isNotEmpty())
    <div class="flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1">
            <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input x-model="search" type="text" placeholder="Search by name or email…"
                   class="w-full text-sm py-2.5 pl-10 pr-9 bg-white border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-teal-400/20 focus:border-teal-400 transition-all">
            <button x-show="search" @click="search = ''" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-500">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="flex gap-2 flex-wrap">
            <button @click="setFilter('all')"
                    :class="filter==='all' ? 'bg-teal-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-50'"
                    class="px-3 py-2 rounded-xl text-xs font-semibold border border-gray-200 transition-all">All</button>
            <button @click="setFilter('active')"
                    :class="filter==='active' ? 'bg-green-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-50'"
                    class="px-3 py-2 rounded-xl text-xs font-semibold border border-gray-200 transition-all">Active</button>
            <button @click="setFilter('pending_invite')"
                    :class="filter==='pending_invite' ? 'bg-amber-500 text-white' : 'bg-white text-gray-500 hover:bg-gray-50'"
                    class="px-3 py-2 rounded-xl text-xs font-semibold border border-gray-200 transition-all">Pending Invite</button>
        </div>
    </div>
    @endif

    {{-- ── Partners List ───────────────────────────────────────── --}}
    @php
        $partnersJson = $partners->map(fn($p) => [
            'email'            => (string) $p['email'],
            'name'             => (string) $p['name'],
            'status'           => (string) $p['status'],
            'deal_count'       => (int) $p['deal_count'],
            'total_commission' => (float) $p['total_commission'],
            'latest_deal'      => (string) ($p['latest_deal'] ?? ''),
            'added_at'         => $p['added_at'] ? $p['added_at']->diffForHumans() : '',
            'slug'             => base64_encode($p['email']),
        ])->values()->all();
    @endphp
    <div x-show="!loading" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

        @if($partners->isEmpty())
        {{-- Empty state --}}
        <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center mb-4" style="background:#CCFBF1">
                <svg class="w-7 h-7 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <p class="text-sm font-semibold text-[#1E1B4B]">No Partners yet</p>
            <p class="text-xs text-gray-400 mt-1 max-w-sm leading-relaxed">
                @if($myDeals->isEmpty())
                    You don't have any assigned deals yet. Partners will appear here once you've been assigned a deal and added a Partner to it.
                @else
                    Partners you add to your assigned deals will appear here. Partners can help move a deal forward and may receive a split allocation.
                @endif
            </p>
            @if($myDeals->isNotEmpty())
            <button @click="openAddModal()"
                    class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white"
                    style="background:linear-gradient(135deg,#0D9488,#14B8A6)">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Partner
            </button>
            @endif
        </div>

        @else
        {{-- Desktop table --}}
        <div class="hidden lg:block">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100">
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Partner</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Status</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Deals</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Commission</th>
                        <th class="text-right px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <template x-for="p in filtered" :key="p.email">
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold text-teal-700 shrink-0" style="background:#CCFBF1"
                                         x-text="(p.name || '?').charAt(0).toUpperCase()"></div>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-[#1E1B4B] truncate" x-text="p.name"></p>
                                        <p class="text-xs text-gray-400 truncate" x-text="p.email"></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <span :class="{
                                    'bg-green-100 text-green-700':  p.status === 'active',
                                    'bg-amber-100 text-amber-700':  p.status === 'pending_invite',
                                    'bg-gray-100  text-gray-500':   p.status === 'provisional',
                                }" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold">
                                    <span x-text="{active:'Active', pending_invite:'Pending Invite', provisional:'Not Invited'}[p.status] || p.status"></span>
                                </span>
                            </td>
                            <td class="px-4 py-4 text-gray-600" x-text="p.deal_count + ' deal' + (p.deal_count !== 1 ? 's' : '')"></td>
                            <td class="px-4 py-4 text-right font-semibold text-[#0D9488]"
                                x-text="'₱' + Number(p.total_commission).toLocaleString('en-PH', {minimumFractionDigits:0, maximumFractionDigits:0})"></td>
                            <td class="px-5 py-4 text-right">
                                <a :href="'{{ url('reseller/' . $tenantId . '/partners') }}/' + p.slug"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-teal-700 bg-teal-50 hover:bg-teal-100 transition-colors">
                                    View
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            </td>
                        </tr>
                    </template>
                    <template x-if="filtered.length === 0">
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-sm text-gray-400">
                                No Partners match your search or filter.
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Mobile cards --}}
        <div class="lg:hidden divide-y divide-gray-50">
            <template x-for="p in filtered" :key="p.email">
                <div class="px-4 py-4 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold text-teal-700 shrink-0" style="background:#CCFBF1"
                         x-text="(p.name || '?').charAt(0).toUpperCase()"></div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <p class="font-semibold text-[#1E1B4B] text-sm truncate" x-text="p.name"></p>
                            <span :class="{
                                'bg-green-100 text-green-700':  p.status === 'active',
                                'bg-amber-100 text-amber-700':  p.status === 'pending_invite',
                                'bg-gray-100  text-gray-500':   p.status === 'provisional',
                            }" class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold">
                                <span x-text="{active:'Active', pending_invite:'Pending', provisional:'Not Invited'}[p.status] || p.status"></span>
                            </span>
                        </div>
                        <p class="text-xs text-gray-400 mt-0.5 truncate" x-text="p.deal_count + ' deal' + (p.deal_count !== 1 ? 's' : '') + ' · ₱' + Number(p.total_commission).toLocaleString('en-PH', {maximumFractionDigits:0})"></p>
                    </div>
                    <a :href="'{{ url('reseller/' . $tenantId . '/partners') }}/' + p.slug"
                       class="shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-teal-600 bg-teal-50 hover:bg-teal-100 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </template>
            <template x-if="filtered.length === 0">
                <div class="px-5 py-10 text-center text-sm text-gray-400">No Partners match your search or filter.</div>
            </template>
        </div>
        @endif
    </div>

    {{-- ── Add Partner Modal ───────────────────────────────────── --}}
    <div x-show="modal" x-cloak
         class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4"
         @keydown.escape.window="modal = false">
        <div @click="modal = false" class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg" @click.stop
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">

            {{-- Modal Header --}}
            <div class="flex items-center justify-between px-6 pt-5 pb-4 border-b border-gray-100">
                <div>
                    <h3 class="text-[#1E1B4B] font-bold text-base">Add Partner</h3>
                    <p class="text-gray-400 text-xs mt-0.5">Associate a Partner with one of your assigned deals.</p>
                </div>
                <button @click="modal = false" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Modal Form --}}
            <form @submit.prevent="submitAdd()" class="px-6 py-5 space-y-4">

                {{-- Deal selector --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Deal <span class="text-red-400">*</span></label>
                    <select x-model="form.deal_id" class="w-full text-sm bg-white border border-gray-200 rounded-xl px-3 py-2.5 outline-none focus:ring-2 focus:ring-teal-400/20 focus:border-teal-400 transition-all" required>
                        <option value="">Select a deal…</option>
                        @foreach($myDeals as $deal)
                        <option value="{{ $deal->id }}">{{ $deal->name }}
                            @if($deal->commission_status === 'paid') (Paid)
                            @elseif($deal->commission_status === 'locked') (Locked)
                            @endif
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Partner Name --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Partner Name <span class="text-red-400">*</span></label>
                    <input x-model="form.partner_name" type="text" placeholder="Full name"
                           class="w-full text-sm bg-white border border-gray-200 rounded-xl px-3 py-2.5 outline-none focus:ring-2 focus:ring-teal-400/20 focus:border-teal-400 transition-all" required>
                </div>

                {{-- Partner Email --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Partner Email <span class="text-red-400">*</span></label>
                    <input x-model="form.partner_email" type="email" placeholder="partner@email.com"
                           class="w-full text-sm bg-white border border-gray-200 rounded-xl px-3 py-2.5 outline-none focus:ring-2 focus:ring-teal-400/20 focus:border-teal-400 transition-all" required>
                </div>

                {{-- Split --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Split Value <span class="text-red-400">*</span></label>
                        <input x-model="form.split_share_value" type="number" step="0.01" min="0.01"
                               :placeholder="form.split_share_type === 'percentage' ? 'e.g. 10' : 'e.g. 50000'"
                               class="w-full text-sm bg-white border border-gray-200 rounded-xl px-3 py-2.5 outline-none focus:ring-2 focus:ring-teal-400/20 focus:border-teal-400 transition-all" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Type</label>
                        <select x-model="form.split_share_type" class="w-full text-sm bg-white border border-gray-200 rounded-xl px-3 py-2.5 outline-none focus:ring-2 focus:ring-teal-400/20 focus:border-teal-400 transition-all">
                            <option value="percentage">Percentage (%)</option>
                            <option value="fixed_amount">Fixed Amount (₱)</option>
                        </select>
                    </div>
                </div>

                {{-- Error --}}
                <div x-show="formError" class="text-xs text-red-600 bg-red-50 px-3 py-2.5 rounded-xl border border-red-200" x-text="formError"></div>

                {{-- Actions --}}
                <div class="flex gap-3 justify-end pt-1">
                    <button type="button" @click="modal = false"
                            class="px-4 py-2 rounded-xl text-sm font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200 transition-colors">
                        Cancel
                    </button>
                    <button type="submit"
                            :disabled="submitting || !form.deal_id || !form.partner_name || !form.partner_email || !form.split_share_value"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                            style="background:linear-gradient(135deg,#0D9488,#14B8A6)">
                        <svg x-show="submitting" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4z"/>
                        </svg>
                        <span x-text="submitting ? 'Adding Partner…' : 'Add Partner'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Toast ───────────────────────────────────────────────── --}}
    <div x-show="toast" x-cloak x-transition
         class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 flex items-center gap-3 bg-[#1E1B4B] text-white text-sm font-medium px-4 py-3 rounded-2xl shadow-xl">
        <svg class="w-4 h-4 text-teal-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <span x-text="toast"></span>
    </div>

</div>
@endsection

@push('scripts')
<script>
function partnersPage() {
    const CSRF    = document.querySelector('meta[name=csrf-token]')?.content ?? '';
    const STORE   = '{{ route('reseller.partners.store', $tenantId) }}';
    const allData = {!! json_encode($partnersJson, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};

    return {
        search:     '',
        filter:     'all',
        partners:   allData,
        modal:      false,
        loading:    false,
        submitting: false,
        toast:      null,
        formError:  null,
        form: {
            deal_id:           '',
            partner_name:      '',
            partner_email:     '',
            split_share_value: '',
            split_share_type:  'percentage',
        },

        init() {},

        get filtered() {
            let list = this.partners;
            if (this.filter !== 'all') list = list.filter(p => p.status === this.filter);
            if (this.search) {
                const q = this.search.toLowerCase().trim();
                list = list.filter(p =>
                    (p.name  ?? '').toLowerCase().includes(q) ||
                    (p.email ?? '').toLowerCase().includes(q) ||
                    (p.latest_deal ?? '').toLowerCase().includes(q)
                );
            }
            return list;
        },

        setFilter(f) { this.filter = f; },

        openAddModal() {
            this.form      = { deal_id: '', partner_name: '', partner_email: '', split_share_value: '', split_share_type: 'percentage' };
            this.formError = null;
            this.modal     = true;
        },

        async submitAdd() {
            if (this.submitting) return;
            this.submitting = true;
            this.formError  = null;
            try {
                const r = await fetch(STORE, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify(this.form),
                });
                const d = await r.json();
                if (!r.ok) { this.formError = d.error || d.message || 'Could not add Partner.'; return; }
                this.modal = false;
                this.showToast(d.message || 'Partner added. Admins have been notified.');
                setTimeout(() => { window.location.reload(); }, 1200);
            } catch(e) {
                this.formError = 'Network error. Please try again.';
            } finally {
                this.submitting = false;
            }
        },

        showToast(msg) {
            this.toast = msg;
            setTimeout(() => { this.toast = null; }, 4000);
        },
    };
}
</script>
@endpush
