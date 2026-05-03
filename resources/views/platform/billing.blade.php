@extends('layouts.app')
@section('title', 'Billing & Pricing')
@section('nav') @include('platform._nav') @endsection

@section('content')
<div x-data="billingPanel()" x-init="init()" class="space-y-5">

    {{-- Tab nav --}}
    <div class="card p-1.5">
        <div class="flex gap-1 overflow-x-auto">
            @foreach(['Overview','Plans & Pricing','Promo Codes','Promotions','Pricing History','Performance','Approvals'] as $i => $tab)
            <button @click="activeTab = {{ $i }}"
                    :class="activeTab === {{ $i }} ? 'tab-active' : 'text-gray-600 hover:bg-gray-100'"
                    class="px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap transition-colors flex items-center gap-1.5">
                {{ $tab }}
                @if($i === 6)
                    <span x-show="approvalCount > 0" x-text="approvalCount"
                          class="w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center"></span>
                @endif
            </button>
            @endforeach
        </div>
    </div>

    {{-- ── Tab 0: Overview ── --}}
    <div x-show="activeTab === 0" class="space-y-5">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="kpi-card">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5 mb-1.5">
                        <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">MRR</span>
                        <x-info-tip text="Monthly Recurring Revenue from all active paid subscriptions." />
                    </div>
                    <p class="text-2xl font-bold text-[#1E1B4B]" x-text="overview.mrr ? '₱' + Number(overview.mrr).toLocaleString() : '—'"></p>
                </div>
                <div class="kpi-icon bg-purple-100 ml-3 shrink-0"><svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg></div>
            </div>
            <div class="kpi-card">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5 mb-1.5">
                        <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Active Subs</span>
                        <x-info-tip text="Subscriptions with active status and a confirmed payment method. Excludes trials." />
                    </div>
                    <p class="text-2xl font-bold text-[#1E1B4B]" x-text="overview.paying_tenants ?? '—'"></p>
                </div>
                <div class="kpi-icon bg-emerald-100 ml-3 shrink-0"><svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
            </div>
            <div class="kpi-card">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5 mb-1.5">
                        <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Trials</span>
                        <x-info-tip text="Subscriptions in the trial period. They get Pro-level access and automatically convert or expire." />
                    </div>
                    <p class="text-2xl font-bold text-[#1E1B4B]" x-text="overview.trial_tenants ?? '—'"></p>
                </div>
                <div class="kpi-icon bg-blue-100 ml-3 shrink-0"><svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
            </div>
            <div class="kpi-card">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5 mb-1.5">
                        <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Past Due</span>
                        <x-info-tip text="Subscriptions with an overdue payment. Tenants may lose access if not resolved." position="left" />
                    </div>
                    <p class="text-2xl font-bold text-[#1E1B4B]" x-text="overview.past_due_tenants ?? '—'"></p>
                </div>
                <div class="kpi-icon bg-red-100 ml-3 shrink-0"><svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg></div>
            </div>
        </div>

        {{-- Recent invoices --}}
        <div class="card p-0 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 font-semibold text-[#1E1B4B]">Recent Invoices</div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead><tr class="table-head"><th>Invoice</th><th>Tenant</th><th>Amount</th><th>Status</th><th>Due</th></tr></thead>
                    <tbody>
                        @forelse($invoices as $inv)
                        <tr class="table-row">
                            <td class="font-mono text-xs text-gray-500">{{ $inv->invoice_number }}</td>
                            <td class="font-medium text-[#1E1B4B]">{{ $inv->tenant?->name ?? '—' }}</td>
                            <td class="font-semibold">₱{{ number_format($inv->final_amount, 2) }}</td>
                            <td><span @class(['badge','badge-green'=>$inv->status==='paid','badge-orange'=>$inv->status==='open','badge-red'=>$inv->status==='past_due','badge-gray'=>true])>{{ ucfirst($inv->status) }}</span></td>
                            <td class="text-gray-400 text-xs">{{ $inv->due_date?->format('M d, Y') ?? '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="py-8 text-center text-gray-400 text-sm">No invoices yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── Tab 1: Plans & Pricing ── --}}
    <div x-show="activeTab === 1" class="space-y-4">

        {{-- Plan price cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <template x-for="(plan, pi) in plans" :key="plan.id">
                <div class="card p-0 overflow-hidden flex flex-col">
                    {{-- Tier color band --}}
                    <div class="h-1.5 w-full shrink-0"
                         :style="'background:' + [
                             'linear-gradient(90deg,#6366F1,#8B5CF6)',
                             'linear-gradient(90deg,#8B5CF6,#7B61FF)',
                             'linear-gradient(90deg,#7B61FF,#EC4899)',
                             'linear-gradient(90deg,#EC4899,#F43F5E)'
                         ][pi % 4]"></div>

                    <div class="p-4 flex flex-col flex-1 gap-3">
                        {{-- Name + status --}}
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="font-bold text-[#1E1B4B]" x-text="plan.name"></h3>
                                <p class="text-xs text-gray-400 mt-0.5 line-clamp-2" x-text="plan.description || '—'"></p>
                            </div>
                            <span :class="plan.is_active ? 'badge badge-green' : 'badge badge-gray'"
                                  x-text="plan.is_active ? 'Active' : 'Off'" class="shrink-0 text-xs mt-0.5"></span>
                        </div>

                        {{-- Price --}}
                        <div class="py-3 border-y border-gray-100">
                            <div class="flex items-baseline gap-1">
                                <span class="text-3xl font-bold text-[#1E1B4B]"
                                      x-text="Number(plan.price_monthly) === 0 ? (plan.name?.toLowerCase() === 'enterprise' ? 'Custom' : 'Free') : '₱' + Math.round(Number(plan.price_monthly)).toLocaleString()"></span>
                                <span class="text-xs text-gray-400" x-show="Number(plan.price_monthly) > 0">/mo</span>
                            </div>
                            <p class="text-xs text-gray-400 mt-0.5"
                               x-text="Number(plan.price_yearly) > 0 ? '₱' + Number(plan.price_yearly).toLocaleString() + ' /yr' : 'No yearly plan'"></p>
                        </div>

                        {{-- Key limits --}}
                        <div class="space-y-1.5 text-xs">
                            <div class="flex items-center justify-between text-gray-500">
                                <span class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                    Leads / mo
                                </span>
                                <span class="font-semibold text-[#1E1B4B]" x-text="formatLimit(plan.plan_limits_json?.max_leads_per_month)"></span>
                            </div>
                            <div class="flex items-center justify-between text-gray-500">
                                <span class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/></svg>
                                    Resellers
                                </span>
                                <span class="font-semibold text-[#1E1B4B]" x-text="formatLimit(plan.plan_limits_json?.max_resellers)"></span>
                            </div>
                            <div class="flex items-center justify-between text-gray-500">
                                <span class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                                    Messages / mo
                                </span>
                                <span class="font-semibold text-[#1E1B4B]" x-text="formatLimit(plan.plan_limits_json?.max_messages_per_month)"></span>
                            </div>
                        </div>

                        {{-- Edit button --}}
                        <button @click="editPlan = {...plan, update_rule: 'new_subscriptions_only', change_reason: ''}; showPlanModal = true"
                                class="mt-auto w-full py-2 rounded-xl text-xs font-semibold bg-[#F0EFFA] text-purple-700 hover:bg-purple-100 transition-colors flex items-center justify-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            Edit Pricing
                        </button>
                    </div>
                </div>
            </template>
            <template x-if="plans.length === 0">
                <div class="col-span-4 card text-center py-10 text-gray-400 text-sm">Loading plans…</div>
            </template>
        </div>

        {{-- Unified plan comparison table (Limits + Features merged) --}}
        <div class="card p-0 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-[#1E1B4B] text-sm">Plan Comparison — Resources & Features</h3>
                <span class="text-xs text-gray-400" x-text="plans.length + ' plans'"></span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="table-head">
                            <th class="text-left w-40">Feature</th>
                            <template x-for="plan in plans" :key="plan.id">
                                <th class="text-center" x-text="plan.name"></th>
                            </template>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Resources section --}}
                        <tr class="bg-gray-50">
                            <td colspan="10" class="px-4 py-2 text-xs font-bold text-gray-500 uppercase tracking-wider">Resources</td>
                        </tr>
                        <template x-for="row in limitRows" :key="row.key">
                            <tr class="table-row">
                                <td class="text-gray-500 text-xs font-medium" x-text="row.label"></td>
                                <template x-for="plan in plans" :key="plan.id">
                                    <td class="text-center">
                                        <span class="text-sm font-bold text-[#1E1B4B]"
                                              x-text="formatLimit(plan.plan_limits_json?.[row.key])"></span>
                                    </td>
                                </template>
                            </tr>
                        </template>
                        {{-- Features section --}}
                        <tr class="bg-gray-50">
                            <td colspan="10" class="px-4 py-2 text-xs font-bold text-gray-500 uppercase tracking-wider">Features</td>
                        </tr>
                        <template x-for="feat in featureRows" :key="feat">
                            <tr class="table-row">
                                <td class="text-gray-500 text-xs font-medium capitalize" x-text="feat.replace(/_/g,' ')"></td>
                                <template x-for="plan in plans" :key="plan.id">
                                    <td class="text-center">
                                        <span :class="{
                                            'text-emerald-500 font-bold': plan.plan_features_json?.[feat] === true || plan.plan_features_json?.[feat] === 'full',
                                            'text-red-400':               plan.plan_features_json?.[feat] === false,
                                            'text-orange-500 font-medium':plan.plan_features_json?.[feat] && plan.plan_features_json?.[feat] !== true && plan.plan_features_json?.[feat] !== false,
                                            'text-gray-300':              plan.plan_features_json?.[feat] == null,
                                        }" class="text-sm" x-text="formatFeature(plan.plan_features_json?.[feat])"></span>
                                    </td>
                                </template>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    {{-- ── Tab 2: Promo Codes ── --}}
    <div x-show="activeTab === 2" class="space-y-4">
        <div class="flex justify-end">
            <button @click="showPromoModal = true" class="btn-primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Promo Code
            </button>
        </div>
        <div class="card p-0 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead><tr class="table-head"><th>Code</th><th>Discount</th><th>Redemptions</th><th>Valid Until</th><th>Status</th></tr></thead>
                    <tbody>
                        <template x-if="promoCodes.length === 0">
                            <tr><td colspan="5" class="py-8 text-center text-gray-400 text-sm">No promo codes yet</td></tr>
                        </template>
                        <template x-for="code in promoCodes" :key="code.id">
                            <tr class="table-row">
                                <td><span class="font-mono font-bold text-[#1E1B4B]" x-text="code.code"></span><br><span class="text-xs text-gray-400" x-text="code.name"></span></td>
                                <td x-text="code.discount_type === 'percentage' ? code.discount_value + '%' : '₱' + Number(code.discount_value).toLocaleString()"></td>
                                <td x-text="code.redemptions_count + (code.max_redemptions ? ' / ' + code.max_redemptions : ' / ∞')"></td>
                                <td class="text-gray-400 text-xs" x-text="code.valid_until ? new Date(code.valid_until).toLocaleDateString() : 'No expiry'"></td>
                                <td><span :class="{'badge':true,'badge-green':code.status==='active','badge-orange':code.status==='pending_approval','badge-gray':code.status==='inactive'||code.status==='expired'}" x-text="code.status.replace('_',' ')"></span></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── Tab 3: Promotions ── --}}
    <div x-show="activeTab === 3" class="space-y-4">
        <div class="flex justify-end">
            <button @click="showPromotionModal = true" class="btn-primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Promotion
            </button>
        </div>
        <div class="card p-0 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead><tr class="table-head"><th>Name</th><th>Type</th><th>Discount</th><th>Scope</th><th>Valid Until</th><th>Status</th></tr></thead>
                    <tbody>
                        <template x-if="promotions.length === 0">
                            <tr><td colspan="6" class="py-8 text-center text-gray-400 text-sm">No promotions yet</td></tr>
                        </template>
                        <template x-for="promo in promotions" :key="promo.id">
                            <tr class="table-row">
                                <td class="font-medium text-[#1E1B4B]" x-text="promo.name"></td>
                                <td><span class="badge badge-purple capitalize" x-text="promo.promotion_type.replace('_',' ')"></span></td>
                                <td x-text="promo.discount_type === 'percentage' ? promo.discount_value + '%' : '₱' + Number(promo.discount_value).toLocaleString()"></td>
                                <td class="capitalize text-gray-500 text-xs" x-text="promo.target_scope.replace('_',' ')"></td>
                                <td class="text-gray-400 text-xs" x-text="promo.valid_until ? new Date(promo.valid_until).toLocaleDateString() : 'No expiry'"></td>
                                <td><span :class="{'badge':true,'badge-green':promo.status==='active','badge-blue':promo.status==='draft','badge-gray':promo.status==='ended','badge-orange':promo.status==='pending_approval'}" x-text="promo.status.replace('_',' ')"></span></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── Tab 4: Pricing History ── --}}
    <div x-show="activeTab === 4" class="space-y-3">
        <template x-if="pricingHistory.length === 0">
            <div class="card text-center py-10 text-gray-400">No pricing changes recorded yet.</div>
        </template>
        <template x-for="h in pricingHistory" :key="h.id">
            <div class="card flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="flex-1">
                    <p class="font-semibold text-[#1E1B4B]" x-text="h.plan?.name + ' — Price Updated'"></p>
                    <p class="text-sm text-gray-500 mt-0.5" x-text="'Monthly: ₱' + h.old_price_monthly + ' → ₱' + h.new_price_monthly + '  ·  Yearly: ₱' + h.old_price_yearly + ' → ₱' + h.new_price_yearly"></p>
                    <p class="text-xs text-gray-400 mt-1" x-text="h.change_reason || 'No reason provided'"></p>
                </div>
                <div class="text-right shrink-0">
                    <span class="badge badge-blue capitalize" x-text="h.update_rule?.replace(/_/g,' ')"></span>
                    <p class="text-xs text-gray-400 mt-1" x-text="new Date(h.created_at).toLocaleDateString()"></p>
                </div>
            </div>
        </template>
    </div>

    {{-- ── Tab 5: Performance ── --}}
    <div x-show="activeTab === 5" class="space-y-5">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="kpi-card"><div><p class="text-gray-500 text-sm">Active Codes</p><p class="text-2xl font-bold text-[#1E1B4B] mt-1" x-text="performance.activeCodes ?? '—'"></p></div><div class="kpi-icon bg-purple-100"><svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg></div></div>
            <div class="kpi-card"><div><p class="text-gray-500 text-sm">Total Redemptions</p><p class="text-2xl font-bold text-[#1E1B4B] mt-1" x-text="performance.totalRedemptions ?? '—'"></p></div><div class="kpi-icon bg-emerald-100"><svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div></div>
            <div class="kpi-card"><div><p class="text-gray-500 text-sm">Total Discount Given</p><p class="text-2xl font-bold text-[#1E1B4B] mt-1" x-text="performance.totalDiscount ? '₱' + Number(performance.totalDiscount).toLocaleString() : '₱0'"></p></div><div class="kpi-icon bg-red-100"><svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div></div>
            <div class="kpi-card"><div><p class="text-gray-500 text-sm">Top Code</p><p class="text-lg font-bold text-[#1E1B4B] mt-1 font-mono" x-text="performance.topCode?.code ?? '—'"></p></div><div class="kpi-icon bg-orange-100"><svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg></div></div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="card">
                <h3 class="font-semibold text-[#1E1B4B] mb-4">Redemptions by Promo</h3>
                <div class="space-y-3">
                    <template x-for="item in (performance.byPromo || []).slice(0,5)" :key="item.promo_code_id">
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-mono font-medium text-[#1E1B4B]" x-text="item.promo_code?.code || '—'"></span>
                            <div class="flex items-center gap-3">
                                <span class="text-gray-500" x-text="item.redemptions + ' uses'"></span>
                                <span class="font-semibold text-[#1E1B4B]" x-text="'₱' + Number(item.total_discount).toLocaleString()"></span>
                            </div>
                        </div>
                    </template>
                    <template x-if="!performance.byPromo || performance.byPromo.length === 0">
                        <p class="text-gray-400 text-sm text-center py-4">No redemptions yet</p>
                    </template>
                </div>
            </div>
            <div class="card">
                <h3 class="font-semibold text-[#1E1B4B] mb-4">Failed Attempts by Reason</h3>
                <div class="space-y-3">
                    <template x-for="item in (performance.failedAttempts || []).slice(0,5)" :key="item.failure_reason">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-600 capitalize" x-text="item.failure_reason.replace(/_/g,' ')"></span>
                            <span class="badge badge-red" x-text="item.count"></span>
                        </div>
                    </template>
                    <template x-if="!performance.failedAttempts || performance.failedAttempts.length === 0">
                        <p class="text-gray-400 text-sm text-center py-4">No failed attempts</p>
                    </template>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Tab 6: Approvals ── --}}
    <div x-show="activeTab === 6" class="space-y-4">
        <div class="flex gap-2">
            <button @click="approvalTab = 'pending'" :class="approvalTab==='pending' ? 'btn-primary' : 'btn-secondary'" class="text-sm">Pending (<span x-text="approvalCount"></span>)</button>
            <button @click="approvalTab = 'history'; loadApprovalHistory()" :class="approvalTab==='history' ? 'btn-primary' : 'btn-secondary'" class="text-sm">History</button>
        </div>

        <div x-show="approvalTab === 'pending'" class="space-y-3">
            <template x-if="approvals.length === 0">
                <div class="card text-center py-10 text-gray-400">No pending approvals.</div>
            </template>
            <template x-for="req in approvals" :key="req.id">
                <div class="card flex flex-col sm:flex-row sm:items-center gap-4">
                    <div class="flex-1">
                        <p class="font-semibold text-[#1E1B4B] capitalize" x-text="req.request_type.replace(/_/g,' ')"></p>
                        <p class="text-sm text-gray-500 mt-0.5" x-text="'Ref: ' + (req.reference_type || '—') + ' #' + (req.reference_id?.slice(0,8) || '—')"></p>
                        <p class="text-xs text-gray-400 mt-0.5" x-text="req.notes || 'No notes'"></p>
                        <p class="text-xs text-gray-300 mt-0.5" x-text="new Date(req.created_at).toLocaleString()"></p>
                    </div>
                    <div class="flex gap-2 shrink-0">
                        <button @click="approve(req.id)" class="btn-primary text-xs px-3 py-1.5">Approve</button>
                        <button @click="reject(req.id)" class="btn-danger text-xs px-3 py-1.5">Reject</button>
                    </div>
                </div>
            </template>
        </div>

        <div x-show="approvalTab === 'history'" class="space-y-3">
            <template x-if="approvalHistory.length === 0">
                <div class="card text-center py-10 text-gray-400">No approval history.</div>
            </template>
            <template x-for="req in approvalHistory" :key="req.id">
                <div class="card flex flex-col sm:flex-row sm:items-center gap-4">
                    <div class="flex-1">
                        <p class="font-semibold text-[#1E1B4B] capitalize" x-text="req.request_type.replace(/_/g,' ')"></p>
                        <p class="text-sm text-gray-500 mt-0.5" x-text="req.reviewer_notes || 'No notes'"></p>
                        <p class="text-xs text-gray-300 mt-1" x-text="req.approved_at ? new Date(req.approved_at).toLocaleString() : ''"></p>
                    </div>
                    <span :class="{'badge':true,'badge-green':req.status==='approved','badge-red':req.status==='rejected'}" x-text="req.status"></span>
                </div>
            </template>
        </div>
    </div>

    {{-- ── Plan Edit Modal ── --}}
    <div x-show="showPlanModal" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-[#1E1B4B]" x-text="'Edit ' + editPlan.name + ' Pricing'"></h3>
                <button @click="showPlanModal = false" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="form-label">Monthly Price (PHP)</label><input type="number" x-model="editPlan.price_monthly" class="form-input" min="0" step="1"></div>
                    <div><label class="form-label">Yearly Price (PHP)</label><input type="number" x-model="editPlan.price_yearly" class="form-input" min="0" step="1"></div>
                </div>
                <div>
                    <label class="form-label">Update Rule</label>
                    <select x-model="editPlan.update_rule" class="form-input">
                        <option value="new_subscriptions_only">New subscriptions only</option>
                        <option value="next_billing_cycle">Existing — next billing cycle</option>
                        <option value="immediate_proration">Immediate with proration</option>
                        <option value="grandfather">Grandfather existing</option>
                    </select>
                </div>
                <div><label class="form-label">Reason for change</label><input type="text" x-model="editPlan.change_reason" class="form-input" placeholder="e.g. Annual pricing review"></div>
                <div class="flex justify-end gap-3">
                    <button @click="showPlanModal = false" class="btn-secondary">Cancel</button>
                    <button @click="savePlan()" :disabled="saving" class="btn-primary" x-text="saving ? 'Saving...' : 'Save Changes'"></button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── New Promo Code Modal ── --}}
    <div x-show="showPromoModal" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 sticky top-0 bg-white">
                <h3 class="font-semibold text-[#1E1B4B]">New Promo Code</h3>
                <button @click="showPromoModal = false" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="form-label">Code (leave blank to auto-generate)</label><input type="text" x-model="newPromo.code" class="form-input uppercase" placeholder="e.g. LAUNCH20"></div>
                    <div><label class="form-label">Name *</label><input type="text" x-model="newPromo.name" class="form-input" placeholder="Launch Promo"></div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Discount Type *</label>
                        <select x-model="newPromo.discount_type" class="form-input">
                            <option value="percentage">Percentage</option>
                            <option value="fixed_amount">Fixed Amount</option>
                            <option value="free_months">Free Months</option>
                            <option value="trial_extension">Trial Extension</option>
                        </select>
                    </div>
                    <div><label class="form-label">Value *</label><input type="number" x-model="newPromo.discount_value" class="form-input" placeholder="e.g. 20" min="0"></div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="form-label">Max Redemptions (blank = unlimited)</label><input type="number" x-model="newPromo.max_redemptions" class="form-input" placeholder="100" min="1"></div>
                    <div>
                        <label class="form-label">Billing Cycle</label>
                        <select x-model="newPromo.applies_to_billing_cycle" class="form-input">
                            <option value="both">Both</option>
                            <option value="monthly">Monthly only</option>
                            <option value="yearly">Yearly only</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="form-label">Valid From</label><input type="date" x-model="newPromo.valid_from" class="form-input"></div>
                    <div><label class="form-label">Valid Until (blank = no expiry)</label><input type="date" x-model="newPromo.valid_until" class="form-input"></div>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" x-model="newPromo.allow_stacking" class="rounded accent-purple-600">
                    <label class="text-sm text-gray-600">Allow stacking with other promos</label>
                </div>
                <div x-show="newPromo.discount_type === 'percentage' && newPromo.discount_value > 30" class="p-3 bg-orange-50 border border-orange-200 rounded-xl text-orange-700 text-sm">
                    Discounts above 30% require approval before activation.
                </div>
                <div class="flex justify-end gap-3">
                    <button @click="showPromoModal = false" class="btn-secondary">Cancel</button>
                    <button @click="savePromo()" :disabled="saving" class="btn-primary" x-text="saving ? 'Saving...' : 'Create Code'"></button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── New Promotion Modal ── --}}
    <div x-show="showPromotionModal" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 sticky top-0 bg-white">
                <h3 class="font-semibold text-[#1E1B4B]">New Promotion</h3>
                <button @click="showPromotionModal = false" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <div class="p-6 space-y-4">
                <div><label class="form-label">Promotion Name *</label><input type="text" x-model="newPromotion.name" class="form-input" placeholder="e.g. Beauty Industry Launch"></div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Type</label>
                        <select x-model="newPromotion.promotion_type" class="form-input">
                            <option value="campaign">Campaign</option>
                            <option value="auto_apply">Auto-Apply</option>
                            <option value="launch">Launch</option>
                            <option value="pilot">Pilot</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Target Scope</label>
                        <select x-model="newPromotion.target_scope" class="form-input">
                            <option value="specific_tenants">Specific Tenants</option>
                            <option value="new_tenants">New Tenants Only</option>
                            <option value="existing_tenants">Existing Tenants</option>
                            <option value="industry">By Industry</option>
                            <option value="all">All Tenants</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Discount Type</label>
                        <select x-model="newPromotion.discount_type" class="form-input">
                            <option value="percentage">Percentage</option>
                            <option value="fixed_amount">Fixed Amount</option>
                            <option value="free_months">Free Months</option>
                        </select>
                    </div>
                    <div><label class="form-label">Value</label><input type="number" x-model="newPromotion.discount_value" class="form-input" min="0"></div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="form-label">Valid From</label><input type="date" x-model="newPromotion.valid_from" class="form-input"></div>
                    <div><label class="form-label">Valid Until</label><input type="date" x-model="newPromotion.valid_until" class="form-input"></div>
                </div>
                <div>
                    <label class="form-label">Applies to Duration</label>
                    <select x-model="newPromotion.affects_duration" class="form-input">
                        <option value="first_invoice">First invoice only</option>
                        <option value="first_x_months">First X months</option>
                        <option value="entire_subscription">Entire subscription</option>
                        <option value="next_billing_cycle">Next billing cycle</option>
                    </select>
                </div>
                <div class="flex items-center gap-4">
                    <label class="flex items-center gap-2 text-sm text-gray-600"><input type="checkbox" x-model="newPromotion.auto_apply" class="rounded accent-purple-600"> Auto-apply</label>
                    <label class="flex items-center gap-2 text-sm text-gray-600"><input type="checkbox" x-model="newPromotion.allow_stacking" class="rounded accent-purple-600"> Allow stacking</label>
                </div>
                <div class="flex justify-end gap-3">
                    <button @click="showPromotionModal = false" class="btn-secondary">Cancel</button>
                    <button @click="savePromotion()" :disabled="saving" class="btn-primary" x-text="saving ? 'Saving...' : 'Create Promotion'"></button>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function billingPanel() {
    return {
        activeTab: 0, approvalTab: 'pending',
        overview: {}, plans: [], promoCodes: [], promotions: [],
        pricingHistory: [], performance: {}, approvals: [], approvalHistory: [],
        approvalCount: 0,
        showPlanModal: false, showPromoModal: false, showPromotionModal: false,
        editPlan: {}, saving: false,
        newPromo: { code:'', name:'', discount_type:'percentage', discount_value:'', max_redemptions:'', valid_from:'', valid_until:'', applies_to_billing_cycle:'both', allow_stacking:false },
        newPromotion: { name:'', promotion_type:'campaign', discount_type:'percentage', discount_value:'', target_scope:'specific_tenants', valid_from:'', valid_until:'', affects_duration:'first_invoice', auto_apply:false, allow_stacking:false },

        limitRows: [
            { key: 'max_leads_per_month',    label: 'Leads / mo' },
            { key: 'max_messages_per_month', label: 'Messages / mo' },
            { key: 'max_resellers',          label: 'Resellers' },
            { key: 'max_users',              label: 'Users' },
            { key: 'max_storage_mb',         label: 'Storage' },
            { key: 'grace_period_days',      label: 'Grace Period' },
        ],
        featureRows: ['messaging','advanced_analytics','template_customization','api_access','sms'],

        formatLimit(val) {
            if (val === undefined || val === null) return '—';
            if (val === -1) return 'Unlimited';
            if (typeof val === 'number' && val >= 1000 && val % 1000 === 0) return (val/1000) + 'k';
            return String(val);
        },
        formatFeature(val) {
            if (val === true)    return '✓';
            if (val === false)   return '✗';
            if (val === 'full')  return 'Full';
            if (val === 'limited') return 'Limited';
            if (val === 'critical_only') return 'Critical only';
            return val ?? '—';
        },

        async init() {
            await Promise.all([
                this.loadOverview(),
                this.loadPlans(),
                this.loadPromoCodes(),
                this.loadPromotions(),
                this.loadPricingHistory(),
                this.loadPerformance(),
                this.loadApprovals(),
            ]);
        },

        async loadOverview() {
            const res = await fetch('/api/billing/dashboard');
            const data = await res.json();
            this.overview = data;
        },

        async loadPlans() {
            const res = await fetch('/api/pricing/plans');
            this.plans = await res.json();
        },

        async loadPromoCodes() {
            const res = await fetch('/api/promo-codes?per_page=50');
            const data = await res.json();
            this.promoCodes = data.data || data;
        },

        async loadPromotions() {
            const res = await fetch('/api/promotions?per_page=50');
            const data = await res.json();
            this.promotions = data.data || data;
        },

        async loadPricingHistory() {
            const res = await fetch('/api/pricing/history');
            this.pricingHistory = await res.json();
        },

        async loadPerformance() {
            const res = await fetch('/api/promo-codes/performance');
            this.performance = await res.json();
        },

        async loadApprovals() {
            const res = await fetch('/api/approvals');
            this.approvals = await res.json();
            this.approvalCount = this.approvals.length;
        },

        async loadApprovalHistory() {
            const res = await fetch('/api/approvals/history');
            this.approvalHistory = await res.json();
        },

        async savePlan() {
            this.saving = true;
            try {
                await fetch(`/api/pricing/plans/${this.editPlan.id}/price`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ price_monthly: this.editPlan.price_monthly, price_yearly: this.editPlan.price_yearly, update_rule: this.editPlan.update_rule || 'new_subscriptions_only', change_reason: this.editPlan.change_reason }),
                });
                this.showPlanModal = false;
                await this.loadPlans();
                await this.loadPricingHistory();
            } finally { this.saving = false; }
        },

        async savePromo() {
            if (!this.newPromo.name || !this.newPromo.discount_value) return;
            this.saving = true;
            try {
                await fetch('/api/promo-codes', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify(this.newPromo),
                });
                this.showPromoModal = false;
                this.newPromo = { code:'', name:'', discount_type:'percentage', discount_value:'', max_redemptions:'', valid_from:'', valid_until:'', applies_to_billing_cycle:'both', allow_stacking:false };
                await this.loadPromoCodes();
            } finally { this.saving = false; }
        },

        async savePromotion() {
            if (!this.newPromotion.name || !this.newPromotion.discount_value) return;
            this.saving = true;
            try {
                await fetch('/api/promotions', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify(this.newPromotion),
                });
                this.showPromotionModal = false;
                await this.loadPromotions();
            } finally { this.saving = false; }
        },

        async approve(id) {
            await fetch(`/api/approvals/${id}/approve`, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } });
            await this.loadApprovals();
        },

        async reject(id) {
            const notes = prompt('Reason for rejection (optional):');
            await fetch(`/api/approvals/${id}/reject`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify({ notes }),
            });
            await this.loadApprovals();
        },
    }
}
</script>
@endsection
