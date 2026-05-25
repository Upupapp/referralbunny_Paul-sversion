@extends('layouts.reseller')
@section('title', 'Request Extension')
@section('nav') @include('reseller._nav') @endsection

@section('content')
@php
    $tenantId      = $tenantId ?? request()->route('tenantId');
    $eligibleCount = $eligible->count();
    $totalCount    = $eligibilityRows->count();
@endphp

<div
    x-data="bulkExtensionWizard()"
    x-init="init()"
    class="max-w-4xl mx-auto space-y-6"
>
    {{-- ── Page header ─────────────────────────────────────────── --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold" style="color:#1E1B4B">Request Extension</h1>
            <p class="text-sm text-gray-500 mt-0.5">Select deals and submit your extension request for Admin review.</p>
        </div>
        <a href="{{ route('reseller.deals', $tenantId) }}"
           class="text-sm text-gray-500 hover:text-gray-800 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            My Deals
        </a>
    </div>

    {{-- ── Step progress ────────────────────────────────────────── --}}
    <div class="flex items-center gap-2 text-xs font-medium">
        <template x-for="(label, i) in steps" :key="i">
            <div class="flex items-center gap-2">
                <div class="flex items-center gap-1.5">
                    <div class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold transition-colors"
                         :class="step === i+1 ? 'text-white' : (step > i+1 ? 'text-white' : 'bg-gray-100 text-gray-400')"
                         :style="step === i+1 ? 'background:#7B61FF' : (step > i+1 ? 'background:#10B981' : '')">
                        <template x-if="step > i+1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        </template>
                        <template x-if="step <= i+1">
                            <span x-text="i+1"></span>
                        </template>
                    </div>
                    <span :class="step === i+1 ? 'font-semibold' : 'text-gray-400'" style="color:inherit" x-text="label"></span>
                </div>
                <div x-show="i < steps.length - 1" class="w-8 h-px bg-gray-200"></div>
            </div>
        </template>
    </div>

    {{-- ── STEP 1: Select Deals ─────────────────────────────────── --}}
    <div x-show="step === 1" x-transition>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            {{-- Header --}}
            <div class="p-5 border-b border-gray-100">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-gray-800">Select Deals</h2>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ $eligibleCount }} eligible · {{ $totalCount - $eligibleCount }} ineligible
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        {{-- Search --}}
                        <div class="relative">
                            <input type="text" x-model="search" placeholder="Search deals…"
                                   class="pl-8 pr-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-300 w-48">
                            <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/></svg>
                        </div>
                        {{-- Select all --}}
                        <button @click="selectAllEligible()" type="button"
                                class="text-xs font-medium px-3 py-2 rounded-xl border border-gray-200 hover:bg-gray-50 transition-colors whitespace-nowrap">
                            Select All Eligible
                        </button>
                    </div>
                </div>
            </div>

            {{-- Deal list --}}
            <div class="divide-y divide-gray-50">
                @if($eligibilityRows->isEmpty())
                    <div class="p-12 text-center">
                        <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <p class="font-medium text-gray-700">No deals are currently eligible for extension.</p>
                        <p class="text-xs text-gray-400 mt-1">You can request an extension once a deal is assigned to you and has an active deadline.</p>
                    </div>
                @endif

                @foreach($eligibilityRows as $row)
                @php
                    $deal      = $row['deal'];
                    $eligible  = $row['eligible'];
                    $reason    = $row['reason'];
                    $daysLeft  = $deal->days_left ?? 0;
                    $urgent    = $daysLeft <= 2;
                    $expiring  = $daysLeft <= 7;
                @endphp
                <div class="flex items-start gap-4 p-4 hover:bg-gray-50 transition-colors"
                     x-show="matchesSearch('{{ addslashes($deal->name) }}')"
                     :class="{{ $eligible ? 'true' : 'false' }} ? '' : 'opacity-60'">
                    {{-- Checkbox --}}
                    <div class="pt-0.5">
                        <input type="checkbox"
                               value="{{ $deal->id }}"
                               @if(!$eligible) disabled @endif
                               x-model="selectedDealIds"
                               class="w-4 h-4 accent-violet-600 cursor-pointer disabled:cursor-not-allowed rounded">
                    </div>
                    {{-- Deal info --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium text-gray-800 text-sm">{{ $deal->name }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                                  style="{{ $urgent ? 'background:#FEE2E2;color:#DC2626' : ($expiring ? 'background:#FEF3C7;color:#D97706' : 'background:#F3F4F6;color:#6B7280') }}">
                                @if($daysLeft < 0)
                                    Expired {{ abs($daysLeft) }}d ago
                                @elseif($daysLeft === 0)
                                    Expiring today
                                @else
                                    {{ $daysLeft }}d left
                                @endif
                            </span>
                            @if(!$eligible)
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium" style="background:#F3F4F6;color:#6B7280">
                                    Ineligible
                                </span>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-3 mt-1">
                            <span class="text-xs text-gray-400">Stage: {{ ucwords(str_replace('_', ' ', $deal->stage)) }}</span>
                            <span class="text-xs text-gray-400">Status: {{ ucfirst($deal->status) }}</span>
                        </div>
                        @if(!$eligible && $reason)
                            <p class="text-xs text-red-500 mt-1">{{ $reason }}</p>
                        @endif
                    </div>
                    {{-- Link --}}
                    <a href="{{ route('reseller.deals.show', [$tenantId, $deal->id]) }}"
                       class="text-xs text-gray-400 hover:text-violet-600 whitespace-nowrap"
                       target="_blank">View</a>
                </div>
                @endforeach
            </div>

            {{-- Footer --}}
            <div class="p-4 border-t border-gray-100 flex items-center justify-between bg-gray-50">
                <span class="text-sm text-gray-500">
                    <span x-text="selectedDealIds.length"></span> deal(s) selected
                </span>
                <button @click="goToStep(2)" type="button"
                        :disabled="selectedDealIds.length === 0"
                        class="px-5 py-2 rounded-xl text-sm font-semibold text-white transition-opacity disabled:opacity-40 disabled:cursor-not-allowed"
                        style="background:#7B61FF">
                    Continue
                </button>
            </div>
        </div>
    </div>

    {{-- ── STEP 2: Extension Details ─────────────────────────────── --}}
    <div x-show="step === 2" x-transition>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">
            <h2 class="font-semibold text-gray-800">Extension Details</h2>

            {{-- Requested extension days --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Requested Extension <span class="text-red-500">*</span>
                </label>
                <div class="flex items-center gap-3">
                    <input type="number" x-model.number="requestedDays" min="1" max="90"
                           class="w-24 px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-300">
                    <span class="text-sm text-gray-500">days</span>
                </div>
                <p class="text-xs text-gray-400 mt-1">Maximum 90 days per request.</p>
            </div>

            {{-- Shared reason --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Reason <span class="text-red-500">*</span>
                </label>
                <textarea x-model="sharedReason" rows="4" maxlength="2000"
                          placeholder="Explain why you need more time for these deals…"
                          class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-300 resize-none"></textarea>
                <p class="text-xs text-gray-400 mt-1">
                    <span x-text="sharedReason.length"></span>/2000 characters. Minimum 10.
                </p>
            </div>

            {{-- Per-deal notes (expandable) --}}
            <div>
                <button type="button" @click="showPerDealNotes = !showPerDealNotes"
                        class="text-sm text-violet-600 font-medium flex items-center gap-1">
                    <svg class="w-4 h-4" :class="showPerDealNotes ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    <span x-text="showPerDealNotes ? 'Hide per-deal notes' : 'Add per-deal notes (optional)'"></span>
                </button>
                <div x-show="showPerDealNotes" x-transition class="mt-3 space-y-3">
                    <template x-for="dealId in selectedDealIds" :key="dealId">
                        <div class="p-3 bg-gray-50 rounded-xl">
                            <label class="block text-xs font-medium text-gray-600 mb-1"
                                   x-text="'Note for ' + getDealName(dealId)"></label>
                            <textarea :x-model="`perDealNotes[${dealId}]`"
                                      @input="perDealNotes[dealId] = $event.target.value"
                                      rows="2" maxlength="2000"
                                      class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-violet-300 resize-none"
                                      placeholder="Optional per-deal note…"></textarea>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Helper text --}}
            <div class="p-3 bg-violet-50 rounded-xl text-xs text-violet-700">
                <strong>Note:</strong> Your request will be sent to the Tenant Admins and Managers for review.
                They may approve all, approve some, decline some, or skip some for later.
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-between pt-2">
                <button @click="step = 1" type="button" class="text-sm text-gray-500 hover:text-gray-800">← Back</button>
                <button @click="goToStep(3)" type="button"
                        :disabled="requestedDays < 1 || sharedReason.trim().length < 10"
                        class="px-5 py-2 rounded-xl text-sm font-semibold text-white transition-opacity disabled:opacity-40 disabled:cursor-not-allowed"
                        style="background:#7B61FF">
                    Review Request
                </button>
            </div>
        </div>
    </div>

    {{-- ── STEP 3: Review & Confirm ─────────────────────────────── --}}
    <div x-show="step === 3" x-transition>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">
            <h2 class="font-semibold text-gray-800">Review Extension Request</h2>

            {{-- Summary --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <div class="p-3 bg-gray-50 rounded-xl">
                    <p class="text-xs text-gray-500">Deals selected</p>
                    <p class="text-lg font-bold" style="color:#7B61FF" x-text="selectedDealIds.length"></p>
                </div>
                <div class="p-3 bg-gray-50 rounded-xl">
                    <p class="text-xs text-gray-500">Requested extension</p>
                    <p class="text-lg font-bold text-gray-800" x-text="requestedDays + ' days'"></p>
                </div>
            </div>

            {{-- Reason --}}
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Reason</p>
                <p class="text-sm text-gray-800 bg-gray-50 rounded-xl p-3" x-text="sharedReason"></p>
            </div>

            {{-- Selected deals list --}}
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Selected Deals</p>
                <div class="divide-y divide-gray-50 border border-gray-100 rounded-xl overflow-hidden">
                    <template x-for="dealId in selectedDealIds" :key="dealId">
                        <div class="px-4 py-2.5 flex items-center justify-between text-sm">
                            <span x-text="getDealName(dealId)" class="text-gray-700"></span>
                            <span class="text-xs text-gray-400" x-text="getDaysLeft(dealId) + 'd left'"></span>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Warning --}}
            <div class="p-3 bg-amber-50 border border-amber-100 rounded-xl text-xs text-amber-700">
                <strong>Important:</strong> This does not automatically extend the deals.
                The extension is applied only after Admin or Manager approval.
            </div>

            {{-- Error state --}}
            <div x-show="errorMessage" class="p-3 bg-red-50 border border-red-100 rounded-xl text-xs text-red-700" x-text="errorMessage"></div>

            {{-- Actions --}}
            <div class="flex items-center justify-between pt-2">
                <button @click="step = 2" type="button" :disabled="submitting" class="text-sm text-gray-500 hover:text-gray-800 disabled:opacity-40">← Back</button>
                <div class="flex items-center gap-3">
                    <a href="{{ route('reseller.deals', $tenantId) }}" class="text-sm text-gray-500 hover:text-gray-800">Cancel</a>
                    <button @click="submitRequest()" type="button"
                            :disabled="submitting"
                            class="px-5 py-2 rounded-xl text-sm font-semibold text-white transition-opacity disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-2"
                            style="background:#7B61FF">
                        <svg x-show="submitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-text="submitting ? 'Submitting…' : 'Submit Extension Request'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── STEP 4: Success ──────────────────────────────────────── --}}
    <div x-show="step === 4" x-transition>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center space-y-5">
            <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto" style="background:#ECFDF5">
                <svg class="w-8 h-8" style="color:#10B981" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <div>
                <h2 class="text-xl font-bold" style="color:#1E1B4B">Extension request submitted!</h2>
                <p class="text-sm text-gray-500 mt-1" x-text="'Your request for ' + successCount + ' deal' + (successCount > 1 ? 's' : '') + ' has been sent to Admins and Managers.'"></p>
            </div>
            <div x-show="ineligibleDeals.length > 0" class="p-3 bg-amber-50 rounded-xl text-xs text-amber-700 text-left">
                <strong><span x-text="ineligibleDeals.length"></span> deal(s) were not included</strong> because they were ineligible:
                <ul class="mt-1 list-disc list-inside space-y-0.5">
                    <template x-for="d in ineligibleDeals" :key="d.deal_id">
                        <li><span x-text="d.name + ': ' + d.reason"></span></li>
                    </template>
                </ul>
            </div>
            <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
                <a :href="viewBatchUrl" class="px-5 py-2 rounded-xl text-sm font-semibold text-white" style="background:#7B61FF">
                    View My Request
                </a>
                <a href="{{ route('reseller.extension-requests.index', $tenantId) }}" class="px-5 py-2 rounded-xl text-sm font-medium border border-gray-200 text-gray-700 hover:bg-gray-50">
                    My Extension Requests
                </a>
                <a href="{{ route('reseller.deals', $tenantId) }}" class="px-5 py-2 rounded-xl text-sm font-medium border border-gray-200 text-gray-700 hover:bg-gray-50">
                    Back to My Deals
                </a>
            </div>
        </div>
    </div>
</div>

{{-- Sticky bottom bar removed — selection count shown inline in the deal list footer --}}

@push('scripts')
<script>
function bulkExtensionWizard() {
    const deals = @json($eligibilityRows->map(fn($r) => [
        'id'         => $r['deal_id'],
        'name'       => $r['deal']->name,
        'days_left'  => $r['deal']->days_left,
        'eligible'   => $r['eligible'],
        'reason'     => $r['reason'],
    ])->values());

    return {
        step: 1,
        steps: ['Select Deals', 'Extension Details', 'Review', 'Success'],
        selectedDealIds: [],
        requestedDays: 30,
        sharedReason: '',
        perDealNotes: {},
        showPerDealNotes: false,
        submitting: false,
        errorMessage: '',
        successCount: 0,
        ineligibleDeals: [],
        viewBatchUrl: '#',
        search: '',

        init() {
            // Pre-select eligible deals that came via query string (e.g. from deal detail)
            const preselect = new URLSearchParams(window.location.search).get('deal_ids');
            if (preselect) {
                const ids = preselect.split(',');
                this.selectedDealIds = deals.filter(d => d.eligible && ids.includes(d.id)).map(d => d.id);
            }
        },

        matchesSearch(name) {
            if (!this.search.trim()) return true;
            return name.toLowerCase().includes(this.search.trim().toLowerCase());
        },

        selectAllEligible() {
            const eligible = deals.filter(d => d.eligible && this.matchesSearch(d.name)).map(d => d.id);
            const allSelected = eligible.every(id => this.selectedDealIds.includes(id));
            if (allSelected) {
                this.selectedDealIds = this.selectedDealIds.filter(id => !eligible.includes(id));
            } else {
                eligible.forEach(id => { if (!this.selectedDealIds.includes(id)) this.selectedDealIds.push(id); });
            }
        },

        getDealName(id) {
            return deals.find(d => d.id === id)?.name ?? id;
        },

        getDaysLeft(id) {
            return deals.find(d => d.id === id)?.days_left ?? '?';
        },

        goToStep(n) {
            this.errorMessage = '';
            this.step = n;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },

        async submitRequest() {
            this.submitting = true;
            this.errorMessage = '';

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                const tenantId = '{{ $tenantId }}';

                const payload = {
                    deal_ids: this.selectedDealIds,
                    requested_extension_days: this.requestedDays,
                    shared_reason: this.sharedReason,
                    per_deal_notes: this.perDealNotes,
                };

                const res = await fetch(`/api/extension-requests/bulk`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(payload),
                });

                const data = await res.json();

                if (!res.ok) {
                    this.errorMessage = data.error ?? 'Request failed. Please try again.';
                    return;
                }

                this.successCount     = data.eligible_count ?? this.selectedDealIds.length;
                this.ineligibleDeals  = data.ineligible ?? [];
                this.viewBatchUrl     = `/reseller/${tenantId}/extension-requests/${data.batch?.id}`;
                this.step = 4;
                window.scrollTo({ top: 0, behavior: 'smooth' });

            } catch (e) {
                this.errorMessage = 'An unexpected error occurred. Please try again.';
            } finally {
                this.submitting = false;
            }
        },
    };
}
</script>
@endpush
@endsection
