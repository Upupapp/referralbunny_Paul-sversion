@extends('layouts.app')
@section('title', 'Billing & Usage')
@section('nav') @include('tenant._nav') @endsection

@section('content')
<div class="space-y-5" x-data="billingUsage('{{ $tenant->id }}')" x-init="init()">

    {{-- Sub-tab nav --}}
    <div class="card p-1.5">
        <div class="flex gap-1 overflow-x-auto">
            @foreach(['My Plan','Usage','Health Score','Invoices'] as $ti => $tl)
            <button @click="tab = {{ $ti }}"
                    :class="tab === {{ $ti }} ? 'tab-active' : 'text-gray-600 hover:bg-gray-100'"
                    class="px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap transition-colors flex items-center gap-1.5">
                {{ $tl }}
                @if($ti === 2)
                <span x-show="metric.health_level === 'at_risk'"
                      class="w-2 h-2 rounded-full bg-red-500"></span>
                @endif
            </button>
            @endforeach
        </div>
    </div>

    {{-- ══════════════════════════════════════════
         TAB 0 — MY PLAN
         ══════════════════════════════════════════ --}}
    <div x-show="tab === 0" class="space-y-5">

        {{-- Plan card --}}
        <div class="card" x-show="subscription">
            <div class="flex flex-col sm:flex-row sm:items-start gap-5">

                {{-- Plan badge & name --}}
                <div class="flex-1 space-y-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0"
                             style="background:linear-gradient(135deg,#7B61FF,#FF6CAB)">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-[#1E1B4B]" x-text="subscription?.plan?.name || 'No plan'"></h2>
                            <p class="text-sm text-gray-500" x-text="subscription?.billing_cycle ? ucFirst(subscription.billing_cycle) + ' billing' : 'Trial'"></p>
                        </div>
                        <span :class="{
                            'badge badge-green':  subscription?.status === 'active',
                            'badge badge-blue':   subscription?.status === 'trial',
                            'badge badge-orange': subscription?.status === 'past_due',
                            'badge badge-red':    ['suspended','canceled'].includes(subscription?.status||''),
                            'badge badge-gray':   true,
                        }" class="ml-auto sm:ml-0" x-text="subscription?.status ? ucFirst(subscription.status) : '—'"></span>
                    </div>

                    {{-- Key dates & pricing --}}
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                        @foreach([
                            ['Trial Ends',     "subscription?.trial_end_date ? new Date(subscription.trial_end_date).toLocaleDateString('en',{month:'short',day:'numeric',year:'numeric'}) : '—'"],
                            ['Next Billing',   "subscription?.next_billing_date ? new Date(subscription.next_billing_date).toLocaleDateString('en',{month:'short',day:'numeric',year:'numeric'}) : '—'"],
                            ['Monthly Price',  "subscription?.plan?.price_monthly > 0 ? '₱' + Number(subscription.plan.price_monthly).toLocaleString() : 'Free'"],
                        ] as [$label, $expr])
                        <div class="p-3 bg-gray-50 rounded-xl">
                            <p class="text-xs text-gray-400 mb-0.5">{{ $label }}</p>
                            <p class="text-sm font-semibold text-[#1E1B4B]" x-text="{{ $expr }}"></p>
                        </div>
                        @endforeach
                    </div>

                    {{-- Trial countdown bar --}}
                    <div x-show="subscription?.status === 'trial' && trialDaysLeft() > 0"
                         class="p-4 rounded-xl bg-blue-50 border border-blue-100 space-y-2">
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-semibold text-blue-800">Trial Period</span>
                            <span class="text-blue-600 font-bold" x-text="trialDaysLeft() + ' days remaining'"></span>
                        </div>
                        <div class="h-2 bg-blue-200 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-500 rounded-full transition-all"
                                 :style="'width:' + Math.min(100, (trialDaysLeft() / 15) * 100) + '%'"></div>
                        </div>
                        <p class="text-xs text-blue-600">
                            Your trial includes Pro-level access. Expires on
                            <span class="font-medium" x-text="subscription?.trial_end_date ? new Date(subscription.trial_end_date).toLocaleDateString('en',{month:'long',day:'numeric',year:'numeric'}) : '—'"></span>.
                        </p>
                    </div>

                    {{-- Past due warning --}}
                    <div x-show="subscription?.status === 'past_due'"
                         class="p-4 rounded-xl bg-orange-50 border border-orange-200 flex items-start gap-3">
                        <svg class="w-5 h-5 text-orange-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <div>
                            <p class="font-semibold text-orange-800 text-sm">Payment Past Due</p>
                            <p class="text-xs text-orange-700 mt-0.5">Update your payment method to restore full access.</p>
                        </div>
                        <button class="btn-primary ml-auto text-xs shrink-0">Add Payment Method</button>
                    </div>
                </div>

                {{-- Upgrade CTA --}}
                <div x-show="subscription?.status === 'trial' || ['Free','Starter'].includes(subscription?.plan?.name)"
                     class="shrink-0 w-full sm:w-48">
                    <div class="p-4 rounded-2xl text-center space-y-3" style="background:linear-gradient(135deg,#F5F3FF,#FDF2F8);border:1px solid #EDE9FE">
                        <p class="text-xs font-semibold text-purple-700 uppercase tracking-wide">Upgrade Plan</p>
                        <p class="text-xs text-gray-500">Unlock more deals, referrers, and messaging</p>
                        <button @click="document.getElementById('plans-comparison')?.scrollIntoView({behavior:'smooth'})" class="btn-primary w-full text-xs">
                            View Plans
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Plan limits comparison --}}
        <div id="plans-comparison" class="card p-0 overflow-hidden" x-show="plans.length > 0">
            <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-[#1E1B4B] text-sm">All Plans — Pricing, Resources & Features</h3>
                <span class="text-xs text-gray-400" x-text="plans.length + ' plans available'"></span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="table-head">
                            <th class="text-left w-36">Feature</th>
                            <template x-for="plan in plans" :key="plan.id">
                                <th class="text-center">
                                    <span x-text="plan.name"></span>
                                    <span x-show="subscription?.plan?.id === plan.id" class="ml-1 text-[10px] text-purple-600 font-bold">✓ current</span>
                                </th>
                            </template>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Pricing --}}
                        <tr class="bg-gray-50">
                            <td colspan="10" class="px-4 py-2 text-xs font-bold text-gray-500 uppercase tracking-wider">Pricing</td>
                        </tr>
                        <tr class="table-row">
                            <td class="text-gray-600 text-sm font-medium">Monthly</td>
                            <template x-for="plan in plans" :key="plan.id">
                                <td class="text-center">
                                    <span :class="subscription?.plan?.id === plan.id ? 'font-bold text-[#1E1B4B]' : 'text-gray-600'"
                                          class="text-sm"
                                          x-text="Number(plan.price_monthly) === 0 ? (plan.name === 'Enterprise' ? 'Custom' : 'Free') : '₱' + Math.round(Number(plan.price_monthly)).toLocaleString()"></span>
                                </td>
                            </template>
                        </tr>
                        <tr class="table-row">
                            <td class="text-gray-600 text-sm font-medium">Yearly</td>
                            <template x-for="plan in plans" :key="plan.id">
                                <td class="text-center">
                                    <span :class="subscription?.plan?.id === plan.id ? 'font-bold text-[#1E1B4B]' : 'text-gray-600'"
                                          class="text-sm"
                                          x-text="Number(plan.price_yearly) > 0 ? '₱' + Math.round(Number(plan.price_yearly)).toLocaleString() + '/yr' : (plan.name === 'Enterprise' ? 'Custom' : '—')"></span>
                                </td>
                            </template>
                        </tr>
                        {{-- Resources --}}
                        <tr class="bg-gray-50">
                            <td colspan="10" class="px-4 py-2 text-xs font-bold text-gray-500 uppercase tracking-wider">Resources</td>
                        </tr>
                        @foreach([
                            ['Leads / mo',    'max_leads_per_month'],
                            ['Referrers',     'max_resellers'],
                            ['Users',         'max_users'],
                            ['Messages / mo', 'max_messages_per_month'],
                            ['Storage',       'max_storage_mb'],
                        ] as [$label, $key])
                        <tr class="table-row">
                            <td class="text-gray-500 text-xs font-medium">{{ $label }}</td>
                            <template x-for="plan in plans" :key="plan.id">
                                <td class="text-center">
                                    <span :class="subscription?.plan?.id === plan.id ? 'font-bold text-[#1E1B4B]' : 'text-gray-500'"
                                          class="text-sm"
                                          x-text="fmtLimit(plan.plan_limits_json?.['{{ $key }}'])"></span>
                                </td>
                            </template>
                        </tr>
                        @endforeach
                                                {{-- Features --}}
                        <tr class="bg-gray-50">
                            <td colspan="10" class="px-4 py-2 text-xs font-bold text-gray-500 uppercase tracking-wider">Features</td>
                        </tr>
                        @foreach(['messaging','advanced_analytics','api_access','sms'] as $feat)
                        <tr class="table-row">
                            <td class="text-gray-500 text-xs font-medium capitalize">{{ str_replace('_',' ',$feat) }}</td>
                            <template x-for="plan in plans" :key="plan.id">
                                <td class="text-center text-sm" :class="subscription?.plan?.id === plan.id ? 'font-bold' : ''">
                                    <span :class="{
                                        'text-emerald-600': plan.plan_features_json?.['{{ $feat }}'] === true || plan.plan_features_json?.['{{ $feat }}'] === 'full',
                                        'text-red-400':     plan.plan_features_json?.['{{ $feat }}'] === false,
                                        'text-orange-500':  plan.plan_features_json?.['{{ $feat }}'] && plan.plan_features_json?.['{{ $feat }}'] !== true && plan.plan_features_json?.['{{ $feat }}'] !== false,
                                    }" x-text="fmtFeat(plan.plan_features_json?.['{{ $feat }}'])"></span>
                                </td>
                            </template>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════
         TAB 1 — USAGE
         ══════════════════════════════════════════ --}}
    <div x-show="tab === 1" class="space-y-5">

        {{-- Period header --}}
        <div class="flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-[#1E1B4B]">Usage This Month</h3>
                <p class="text-xs text-gray-400 mt-0.5" x-text="usage.period_start ? 'Billing period: ' + new Date(usage.period_start).toLocaleDateString('en',{month:'long',year:'numeric'}) : ''"></p>
            </div>
            <span class="text-xs text-gray-400" x-show="usage.period_start">
                Resets <span x-text="usage.period_end ? new Date(usage.period_end).toLocaleDateString('en',{month:'short',day:'numeric'}) : ''"></span>
            </span>
        </div>

        {{-- Loading --}}
        <div x-show="loadingUsage" class="card flex items-center justify-center py-10 gap-3 text-gray-400">
            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            <span class="text-sm">Loading usage data…</span>
        </div>

        {{-- Usage meters --}}
        <div x-show="!loadingUsage && usage.resources" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <template x-for="[key, res] in Object.entries(usage.resources || {})" :key="key">
                <div class="card space-y-3">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-[#1E1B4B] capitalize" x-text="key.replace(/_/g,' ')"></p>
                        <span :class="{
                            'badge badge-red':    !res.unlimited && res.percent_used >= 100,
                            'badge badge-orange': !res.unlimited && res.percent_used >= 80 && res.percent_used < 100,
                            'badge badge-green':  res.unlimited || res.percent_used < 80,
                        }" x-text="res.unlimited ? 'Unlimited' : (res.percent_used >= 100 ? 'Limit reached' : res.percent_used >= 80 ? 'Near limit' : 'OK')"></span>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-end justify-between">
                            <span class="text-2xl font-bold text-[#1E1B4B] tabular-nums" x-text="res.unlimited ? '∞' : (res.current || 0)"></span>
                            <span class="text-sm text-gray-400 mb-1" x-text="res.unlimited ? 'Unlimited' : '/ ' + (res.max || 0)"></span>
                        </div>
                        <div class="h-2 bg-gray-100 rounded-full overflow-hidden" x-show="!res.unlimited">
                            <div class="h-full rounded-full transition-all"
                                 :class="res.percent_used >= 100 ? 'bg-red-500' : res.percent_used >= 80 ? 'bg-orange-400' : 'bg-[#7B61FF]'"
                                 :style="'width:' + Math.min(res.percent_used ?? 0, 100) + '%'"></div>
                        </div>
                        <div class="h-2 bg-emerald-400 rounded-full" x-show="res.unlimited"></div>
                        <p class="text-xs text-gray-400"
                           x-text="res.unlimited ? 'No limit on this plan' : (res.percent_used ?? 0) + '% used · ' + Math.max(0, (res.max||0)-(res.current||0)) + ' remaining'"></p>
                    </div>
                </div>
            </template>
        </div>

        {{-- Near-limit alert --}}
        <div x-show="!loadingUsage && hasNearLimit()"
             class="card bg-orange-50 border border-orange-200 flex items-start gap-3">
            <svg class="w-5 h-5 text-orange-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div class="flex-1">
                <p class="font-semibold text-orange-800 text-sm">Approaching Plan Limit</p>
                <p class="text-xs text-orange-700 mt-0.5">One or more resources are at 80%+ usage. Consider upgrading to avoid interruptions.</p>
            </div>
            <button @click="tab = 0" class="btn-primary text-xs shrink-0">Upgrade Plan</button>
        </div>

        {{-- No data state --}}
        <div x-show="!loadingUsage && !usage.resources" class="card text-center py-10 text-gray-400">
            <p class="text-sm">Usage data unavailable. Connect to plan first.</p>
        </div>
    </div>

    {{-- ══════════════════════════════════════════
         TAB 2 — HEALTH SCORE
         ══════════════════════════════════════════ --}}
    <div x-show="tab === 2" class="space-y-5">

        {{-- Score hero --}}
        <div class="card">
            <div class="flex flex-col sm:flex-row items-center gap-6">
                <div class="relative w-28 h-28 shrink-0">
                    <svg class="w-28 h-28 -rotate-90" viewBox="0 0 110 110">
                        <circle cx="55" cy="55" r="46" fill="none" stroke="#EDE9FE" stroke-width="10"/>
                        <circle cx="55" cy="55" r="46" fill="none"
                                :stroke="metric.health_score >= 80 ? '#10B981' : metric.health_score >= 50 ? '#F59E0B' : '#EF4444'"
                                stroke-width="10"
                                :stroke-dasharray="(metric.health_score ?? 0) * 2.89 + ' 289'"
                                stroke-linecap="round"/>
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-2xl font-bold text-[#1E1B4B]" x-text="metric.health_score ?? '—'"></span>
                        <span class="text-[10px] text-gray-400">/100</span>
                    </div>
                </div>
                <div class="flex-1 text-center sm:text-left">
                    <div class="flex items-center gap-2 justify-center sm:justify-start mb-2">
                        <h3 class="text-lg font-bold text-[#1E1B4B]">Platform Health Score</h3>
                        <span :class="{
                            'badge badge-green':  metric.health_level === 'healthy',
                            'badge badge-orange': metric.health_level === 'needs_attention',
                            'badge badge-red':    metric.health_level === 'at_risk',
                            'badge badge-gray':   !metric.health_level,
                        }" x-text="(metric.health_level||'—').replace('_',' ')"></span>
                    </div>
                    <p class="text-sm text-gray-500"
                       x-text="metric.health_score >= 80 ? 'Your account is in great shape. Keep up the activity!' : metric.health_score >= 50 ? 'A few areas need attention to improve your score.' : 'Action required — your score is below the healthy threshold.'"></p>
                    <p class="text-xs text-gray-400 mt-2"
                       x-text="metric.last_activity_at ? 'Last activity: ' + new Date(metric.last_activity_at).toLocaleDateString('en',{month:'short',day:'numeric',year:'numeric',hour:'2-digit',minute:'2-digit'}) : 'No recent activity'"></p>
                </div>
            </div>
        </div>

        {{-- Score breakdown --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach([
                ['Setup Completion', 'setup_completion_percentage', 'Complete your profile, fields, and pipeline stages to improve this.', '%'],
                ['Lead Activity',    'leads_count',                 'Records created and active in your pipeline.',                       ' records'],
                ['Subscription',     'subscription_status',         'Your current payment and subscription standing.',                    ''],
            ] as [$title, $key, $desc, $suffix])
            <div class="card space-y-2">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ $title }}</p>
                <p class="text-2xl font-bold text-[#1E1B4B]"
                   x-text="metric.{{ $key }} !== undefined ? metric.{{ $key }} + '{{ $suffix }}' : '—'"></p>
                <p class="text-xs text-gray-400">{{ $desc }}</p>
            </div>
            @endforeach
        </div>

        {{-- Score factors --}}
        <div class="card space-y-4">
            <h3 class="font-semibold text-[#1E1B4B] text-sm">Score Factors</h3>
            @foreach([
                ['Deal activity',          "leads.length > 0 ? '✓ Deals in pipeline' : '✗ No deals yet'",         "leads.length > 0"],
                ['Referrer network',           "resellers.length > 0 ? '✓ Referrers onboarded' : '✗ No referrers yet'",    "resellers.length > 0"],
                ['Subscription standing',      "subscription?.status === 'active' || subscription?.status === 'trial' ? '✓ Subscription active' : '✗ Subscription issue'", "subscription?.status === 'active' || subscription?.status === 'trial'"],
                ['Setup completion ≥ 50%',     "(metric.setup_completion_percentage ?? 0) >= 50 ? '✓ Setup progress good' : '✗ Complete your setup'", "(metric.setup_completion_percentage ?? 0) >= 50"],
            ] as [$label, $valExpr, $passExpr])
            <div class="flex items-center gap-3">
                <div :class="{{ $passExpr }} ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-500'"
                     class="w-8 h-8 rounded-full flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <template x-if="{{ $passExpr }}"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></template>
                        <template x-if="!( {{ $passExpr }} )"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></template>
                    </svg>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium text-[#1E1B4B]">{{ $label }}</p>
                    <p class="text-xs text-gray-400" x-text="{{ $valExpr }}"></p>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- ══════════════════════════════════════════
         TAB 3 — INVOICES
         ══════════════════════════════════════════ --}}
    <div x-show="tab === 3" class="space-y-5">

        <div class="card p-0 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
                <h3 class="font-semibold text-[#1E1B4B]">Invoice History</h3>
                <span class="text-xs text-gray-400" x-text="invoices.length + ' invoices'"></span>
            </div>

            <div x-show="loadingInvoices" class="flex items-center justify-center py-10 gap-3 text-gray-400">
                <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span class="text-sm">Loading invoices…</span>
            </div>

            <div x-show="!loadingInvoices && invoices.length === 0" class="py-16 text-center">
                <svg class="w-10 h-10 text-gray-200 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 14H5a2 2 0 00-2 2v4a2 2 0 002 2h4m6-6h4a2 2 0 012 2v4a2 2 0 01-2 2h-4m-6 0h6m-3-6V4m0 0L9 7m3-3l3 3"/></svg>
                <p class="text-gray-400 text-sm">No invoices yet. Invoices appear once you have an active paid subscription.</p>
            </div>

            <div x-show="!loadingInvoices && invoices.length > 0" class="overflow-x-auto">
                <table class="w-full">
                    <thead><tr class="table-head"><th>Invoice</th><th>Period</th><th>Amount</th><th>Status</th><th>Due Date</th><th></th></tr></thead>
                    <tbody>
                        <template x-for="inv in invoices" :key="inv.id">
                            <tr class="table-row">
                                <td class="font-mono text-xs text-gray-500" x-text="inv.invoice_number || inv.id?.slice(0,8)+'...'"></td>
                                <td class="text-sm text-gray-500" x-text="inv.created_at ? new Date(inv.created_at).toLocaleDateString('en',{month:'short',year:'numeric'}) : '—'"></td>
                                <td class="font-semibold text-[#1E1B4B] tabular-nums" x-text="inv.final_amount ? '₱' + Number(inv.final_amount).toLocaleString('en',{minimumFractionDigits:2}) : '—'"></td>
                                <td>
                                    <span :class="{
                                        'badge badge-green':  inv.status === 'paid',
                                        'badge badge-orange': inv.status === 'open' || inv.status === 'past_due',
                                        'badge badge-gray':   inv.status === 'void' || inv.status === 'waived',
                                    }" x-text="inv.status ? inv.status.charAt(0).toUpperCase()+inv.status.slice(1).replace('_',' ') : '—'"></span>
                                </td>
                                <td class="text-gray-400 text-xs tabular-nums" x-text="inv.due_date ? new Date(inv.due_date).toLocaleDateString('en',{month:'short',day:'numeric',year:'numeric'}) : '—'"></td>
                                <td class="text-right">
                                    <span x-show="inv.status === 'paid'" class="text-xs text-emerald-600 font-medium">Paid ✓</span>
                                    <button x-show="inv.status === 'open' || inv.status === 'past_due'" class="btn-primary text-xs px-3 py-1.5">Pay Now</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script>
function billingUsage(tenantId) {
    return {
        tab: 0,
        subscription: null, usage: {}, metric: {}, plans: [], invoices: [],
        leads: [], resellers: [],
        loadingUsage: true, loadingInvoices: true,

        async init() {
            const [subRes, plansRes, metricsRes, leadsRes, resRes] = await Promise.all([
                fetch(`/api/billing/tenants/${tenantId}/subscription`),
                fetch('/api/pricing/plans'),
                fetch(`/api/metrics/${tenantId}`),
                fetch(`/api/leads?tenant_id=${tenantId}&per_page=500`),
                fetch(`/api/resellers?tenant_id=${tenantId}`),
            ]);

            this.subscription = await subRes.json();
            const rawPlans = await plansRes.json();
            // Deduplicate by name — keep one entry per plan tier
            const seen = new Set();
            this.plans = (Array.isArray(rawPlans) ? rawPlans : []).filter(p => {
                const key = (p.name || '').toLowerCase();
                if (seen.has(key)) return false;
                seen.add(key);
                return true;
            });
            const md           = await metricsRes.json();
            this.metric        = md.metric ?? {};

            const ld = await leadsRes.json();
            this.leads = Array.isArray(ld) ? ld : (ld.data || []);

            const rd = await resRes.json();
            this.resellers = Array.isArray(rd) ? rd : (rd.data || []);

            // Load usage
            try {
                const uRes  = await fetch(`/api/feature-access/usage?tenant_id=${tenantId}`);
                this.usage  = await uRes.json();
            } catch(e) { this.usage = {}; }
            this.loadingUsage = false;

            // Load invoices
            try {
                const iRes    = await fetch(`/api/billing/invoices?tenant_id=${tenantId}`);
                const iData   = await iRes.json();
                this.invoices = Array.isArray(iData) ? iData : (iData.data || []);
            } catch(e) { this.invoices = []; }
            this.loadingInvoices = false;
        },

        trialDaysLeft() {
            if (!this.subscription?.trial_end_date) return 0;
            return Math.max(0, Math.ceil((new Date(this.subscription.trial_end_date) - new Date()) / 86400000));
        },

        hasNearLimit() {
            return Object.values(this.usage.resources || {}).some(r => !r.unlimited && r.percent_used >= 80);
        },

        ucFirst(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; },

        fmtLimit(v) {
            if (v === undefined || v === null) return '—';
            if (v === -1) return '∞';
            if (v >= 1000) return (v/1000) + 'k';
            return String(v);
        },

        fmtFeat(v) {
            if (v === true)           return '✓';
            if (v === false)          return '✗';
            if (v === 'full')         return 'Full';
            if (v === 'limited')      return 'Limited';
            if (v === 'critical_only')return 'Critical only';
            return v ?? '—';
        },
    }
}
</script>
@endsection


