@extends('layouts.app')
@section('title', 'Dashboard')
@section('platformLabel', 'Super Admin')

@section('nav')
    @php
        $links = [
            ['route' => 'platform.dashboard',  'label' => 'Dashboard',  'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
            ['route' => 'platform.tenants',    'label' => 'Tenants',    'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
            ['route' => 'platform.messaging',  'label' => 'Messaging',  'icon' => 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z'],
            ['route' => 'platform.templates',  'label' => 'Templates',  'icon' => 'M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z'],
            ['route' => 'platform.billing',    'label' => 'Billing',    'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
        ];
    @endphp

    @foreach($links as $link)
        <a href="{{ route($link['route']) }}"
           class="sidebar-link {{ request()->routeIs($link['route']) ? 'active' : '' }}">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $link['icon'] }}"/>
            </svg>
            {{ $link['label'] }}
        </a>
    @endforeach

    <div class="pt-4 mt-4 border-t border-white/10">
        <p class="px-4 text-white/30 text-xs uppercase tracking-wider mb-2">Tenant Access</p>
        @foreach($tenants->take(5) as $t)
            <a href="{{ route('tenant.dashboard', $t->id) }}" class="sidebar-link text-xs">
                <span class="w-5 h-5 rounded-lg flex items-center justify-center text-white text-xs font-bold shrink-0"
                      style="background-color: {{ $t->accent_color ?? '#7B61FF' }}">
                    {{ strtoupper(substr($t->name, 0, 1)) }}
                </span>
                {{ Str::limit($t->name, 18) }}
            </a>
        @endforeach
    </div>
@endsection

@section('topbar-actions')
    <a href="{{ route('platform.tenants.create') }}" class="btn-primary hidden sm:inline-flex">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        New Tenant
    </a>
@endsection

@section('content')
<div class="space-y-5">

    {{-- Approval queue alert --}}
    @if($approvalCount > 0)
    <a href="{{ route('platform.billing') }}"
       class="flex items-center gap-3 p-4 bg-orange-50 border border-orange-200 rounded-2xl text-orange-800 hover:bg-orange-100 transition-colors">
        <svg class="w-5 h-5 text-orange-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <span class="text-sm font-medium">
            {{ $approvalCount }} pending approval{{ $approvalCount > 1 ? 's' : '' }} require your attention — pricing or promo changes awaiting sign-off.
        </span>
        <span class="ml-auto text-xs font-semibold text-orange-600">Review →</span>
    </a>
    @endif

    {{-- Platform banner --}}
    <div class="gradient-banner rounded-2xl p-6 text-white relative overflow-hidden">
        <div class="relative z-10">
            <div>
                <p class="text-white/50 text-xs font-semibold uppercase tracking-widest">Platform Overview</p>
                <h2 class="text-2xl font-bold mt-1.5 leading-tight">{{ now()->format('l, d F Y') }}</h2>
                <p class="text-white/70 text-sm mt-1">
                    {{ $stats['total_tenants'] }} {{ Str::plural('tenant', $stats['total_tenants']) }}
                    &middot; {{ $billing['paying_tenants'] }} paying
                    &middot; {{ $stats['trial_tenants'] }} on trial
                </p>
            </div>
            <div class="flex flex-wrap gap-6 mt-5 pt-4 border-t border-white/10">
                @php $metrics = [
                    ['label' => 'MRR',            'value' => '₱' . number_format($billing['mrr']),   'alert' => false],
                    ['label' => 'ARR',            'value' => '₱' . number_format($billing['arr']),   'alert' => false],
                    ['label' => 'At Risk',        'value' => $stats['at_risk_tenants'],               'alert' => $stats['at_risk_tenants'] > 0],
                    ['label' => 'Critical Alerts','value' => $stats['critical_alerts'],               'alert' => $stats['critical_alerts'] > 0],
                ]; @endphp
                @foreach($metrics as $m)
                <div>
                    <p class="text-white/50 text-xs">{{ $m['label'] }}</p>
                    <p class="text-xl font-bold mt-0.5 {{ $m['alert'] ? 'text-red-200' : '' }}">{{ $m['value'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
        <div class="absolute -right-12 -top-12 w-56 h-56 bg-white/10 rounded-full pointer-events-none"></div>
        <div class="absolute right-8 bottom-0 w-20 h-20 bg-white/10 rounded-full pointer-events-none"></div>
    </div>

    {{-- Quick Actions --}}
    <div class="card">
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">Quick Actions</p>
        @php
        $quickActions = [
            ['label'=>'New Tenant',  'href'=>route('platform.tenants.create'), 'bg'=>'bg-purple-100', 'color'=>'text-purple-600', 'icon'=>'M12 4v16m8-8H4',                                                                                                                                                                        'badge'=>null],
            ['label'=>'Import Data', 'href'=>route('platform.import'),          'bg'=>'bg-blue-100',   'color'=>'text-blue-600',   'icon'=>'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12',                                                                                                                      'badge'=>($importStats['active_jobs'] + $importStats['pending_approval']) ?: null],
            ['label'=>'Billing',     'href'=>route('platform.billing'),         'bg'=>'bg-emerald-100','color'=>'text-emerald-600','icon'=>'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',                                                                                            'badge'=>$approvalCount ?: null],
            ['label'=>'Search',      'href'=>route('platform.search'),          'bg'=>'bg-gray-100',   'color'=>'text-gray-600',   'icon'=>'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',                                                                                                                                          'badge'=>null],
            ['label'=>'Messaging',   'href'=>route('platform.messaging'),       'bg'=>'bg-orange-100', 'color'=>'text-orange-600', 'icon'=>'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z',                                                                         'badge'=>null],
            ['label'=>'Templates',   'href'=>route('platform.templates'),       'bg'=>'bg-pink-100',   'color'=>'text-pink-600',   'icon'=>'M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z', 'badge'=>null],
        ];
        @endphp
        <div class="grid grid-cols-3 sm:grid-cols-6 gap-1">
            @foreach($quickActions as $qa)
            <a href="{{ $qa['href'] }}"
               class="flex flex-col items-center gap-1.5 p-3 rounded-xl hover:bg-[#F0EFFA] transition-all group">
                <div class="relative">
                    <div class="w-10 h-10 rounded-xl {{ $qa['bg'] }} flex items-center justify-center group-hover:scale-110 transition-transform">
                        <svg class="w-5 h-5 {{ $qa['color'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $qa['icon'] }}"/>
                        </svg>
                    </div>
                    @if($qa['badge'])
                    <span class="absolute -top-1 -right-1 min-w-[1.1rem] h-[1.1rem] px-0.5 bg-[#FF5733] rounded-full text-white text-[10px] font-bold flex items-center justify-center leading-none">
                        {{ $qa['badge'] }}
                    </span>
                    @endif
                </div>
                <span class="text-xs font-medium text-gray-600 text-center leading-tight">{{ $qa['label'] }}</span>
            </a>
            @endforeach
        </div>
    </div>

    {{-- Tenant KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @php
        $kpis = [
            ['label'=>'Total Tenants', 'tip'=>'All organizations registered on the platform, regardless of subscription status.',                         'value'=>$stats['total_tenants'],   'sub'=>$stats['active_tenants'].' active',                    'bg'=>'bg-purple-100','color'=>'text-purple-600','icon'=>'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5', 'href'=>route('platform.tenants')],
            ['label'=>'On Trial',      'tip'=>'Tenants in their free 15-day trial. They get Pro-level access and convert or expire automatically.',        'value'=>$stats['trial_tenants'],   'sub'=>'Trial period',                                        'bg'=>'bg-blue-100',  'color'=>'text-blue-600',  'icon'=>'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',                        'href'=>route('platform.tenants', ['status'=>'trial'])],
            ['label'=>'At Risk',       'tip'=>'Tenants with a health score below 50. These accounts show low engagement, stale leads, or payment issues.', 'value'=>$stats['at_risk_tenants'], 'sub'=>($stats['needs_attention'] ?? 0).' need attention',    'bg'=>'bg-red-100',   'color'=>'text-red-600',   'icon'=>'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',          'href'=>route('platform.tenants', ['health_level'=>'at_risk'])],
            ['label'=>'Total Leads',   'tip'=>'Combined lead count across all tenant pipelines. Includes every stage and status.',                          'value'=>$stats['total_leads'],    'sub'=>'₱'.number_format($stats['total_pipeline']/1000000,1).'M pipeline', 'bg'=>'bg-emerald-100','color'=>'text-emerald-600','icon'=>'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0', 'href'=>route('platform.search', ['type'=>'lead'])],
        ];
        @endphp
        @foreach($kpis as $kpi)
        <div class="kpi-card hover:shadow-md transition-shadow">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5 mb-1.5">
                    <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">{{ $kpi['label'] }}</span>
                    <x-info-tip :text="$kpi['tip']" :position="$loop->last ? 'left' : 'top'" />
                </div>
                <a href="{{ $kpi['href'] }}"
                   class="text-2xl font-bold text-[#1E1B4B] block hover:text-[#7B61FF] transition-colors leading-tight">
                    {{ $kpi['value'] }}
                </a>
                <p class="text-xs text-gray-400 mt-0.5">{{ $kpi['sub'] }}</p>
            </div>
            <a href="{{ $kpi['href'] }}"
               class="kpi-icon {{ $kpi['bg'] }} hover:opacity-75 transition-opacity shrink-0 ml-3">
                <svg class="w-5 h-5 {{ $kpi['color'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $kpi['icon'] }}"/>
                </svg>
            </a>
        </div>
        @endforeach
    </div>

    {{-- Billing KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @php
        $bkpis = [
            ['label'=>'MRR',            'tip'=>'Monthly Recurring Revenue — predictable monthly income from all active paid subscriptions.',              'value'=>'₱'.number_format($billing['mrr']),           'sub'=>'Monthly recurring',   'bg'=>'bg-purple-100', 'color'=>'text-purple-600', 'icon'=>'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',                     'href'=>route('platform.billing')],
            ['label'=>'ARR',            'tip'=>'Annual Recurring Revenue — MRR × 12. Represents the annualised value of current subscriptions if nothing changes.', 'value'=>'₱'.number_format($billing['arr']),   'sub'=>'Annual run rate',     'bg'=>'bg-indigo-100', 'color'=>'text-indigo-600', 'icon'=>'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10', 'href'=>route('platform.billing')],
            ['label'=>'Paying Tenants', 'tip'=>'Tenants with an active subscription and at least one successful payment. Excludes trials and free plans.',  'value'=>$billing['paying_tenants'],                   'sub'=>'Active subscriptions','bg'=>'bg-emerald-100','color'=>'text-emerald-600','icon'=>'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',                                                               'href'=>route('platform.tenants', ['status'=>'active'])],
            ['label'=>'Failed (30d)',   'tip'=>'Payment attempts that failed in the last 30 days. High numbers indicate expired cards or billing issues requiring follow-up.', 'value'=>$billing['failed_payments_30d'], 'sub'=>'Payment failures', 'bg'=>'bg-red-100', 'color'=>'text-red-600', 'icon'=>'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',                                       'href'=>route('platform.search', ['type'=>'payment', 'status'=>'failed'])],
        ];
        @endphp
        @foreach($bkpis as $kpi)
        <div class="kpi-card hover:shadow-md transition-shadow">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5 mb-1.5">
                    <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">{{ $kpi['label'] }}</span>
                    <x-info-tip :text="$kpi['tip']" :position="$loop->last ? 'left' : 'top'" />
                </div>
                <a href="{{ $kpi['href'] }}"
                   class="text-2xl font-bold text-[#1E1B4B] block hover:text-[#7B61FF] transition-colors leading-tight">
                    {{ $kpi['value'] }}
                </a>
                <p class="text-xs text-gray-400 mt-0.5">{{ $kpi['sub'] }}</p>
            </div>
            <a href="{{ $kpi['href'] }}"
               class="kpi-icon {{ $kpi['bg'] }} hover:opacity-75 transition-opacity shrink-0 ml-3">
                <svg class="w-5 h-5 {{ $kpi['color'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $kpi['icon'] }}"/>
                </svg>
            </a>
        </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Recent Tenants --}}
        <div class="lg:col-span-2 card">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-[#1E1B4B]">Recent Tenants</h3>
                <a href="{{ route('platform.tenants') }}" class="text-sm text-purple-600 hover:text-purple-700">View all</a>
            </div>
            <div class="space-y-1">
                @php
                    $tenantMetrics = $healthMetrics ?? collect();
                    $metricsByTenant = \App\Models\TenantMetric::whereIn('tenant_id', $tenants->take(6)->pluck('id'))->get()->keyBy('tenant_id');
                @endphp
                @forelse($tenants->take(6) as $tenant)
                    @php $tm = $metricsByTenant[$tenant->id] ?? null; @endphp
                    <a href="{{ route('platform.tenants.show', $tenant->id) }}"
                       class="flex items-center gap-3 p-3 rounded-xl hover:bg-[#F0EFFA] transition-colors">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white text-sm font-bold shrink-0"
                             style="background-color: {{ $tenant->accent_color ?? '#7B61FF' }}">
                            {{ strtoupper(substr($tenant->name, 0, 2)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-[#1E1B4B] truncate">{{ $tenant->name }}</p>
                            <p class="text-xs text-gray-400 truncate">{{ $tenant->industry ?? 'No industry' }}</p>
                        </div>
                        @if($tm)
                        <div class="text-right shrink-0 mr-2">
                            <p class="text-xs font-semibold {{ $tm->health_score >= 80 ? 'text-emerald-600' : ($tm->health_score >= 50 ? 'text-orange-500' : 'text-red-500') }}">
                                {{ $tm->health_score }}
                            </p>
                            <p class="text-xs text-gray-400">health</p>
                        </div>
                        @endif
                        <span @class(['badge','badge-green'=>$tenant->status==='active','badge-blue'=>$tenant->status==='trial','badge-gray'=>$tenant->status==='inactive'])>
                            {{ ucfirst($tenant->status) }}
                        </span>
                    </a>
                @empty
                    <p class="text-gray-400 text-sm text-center py-6">No tenants yet</p>
                @endforelse
            </div>
        </div>

        {{-- Right panel --}}
        <div class="space-y-4">

            {{-- Health breakdown --}}
            <div class="card">
                <div class="flex items-center gap-1 mb-3">
                <h3 class="font-semibold text-[#1E1B4B]">Tenant Health</h3>
                <x-info-tip text="Health score distribution across all tenants. Healthy = 80+, Needs Attention = 50–79, At Risk = below 50. Click any bar to filter the tenant list." />
            </div>
                @php
                    $healthRows = [
                        ['key'=>'healthy',          'label'=>'Healthy',          'color'=>'bg-emerald-500', 'text'=>'text-emerald-700', 'bg'=>'bg-emerald-50'],
                        ['key'=>'needs_attention',  'label'=>'Needs Attention',  'color'=>'bg-orange-400',  'text'=>'text-orange-700',  'bg'=>'bg-orange-50'],
                        ['key'=>'at_risk',          'label'=>'At Risk',          'color'=>'bg-red-500',     'text'=>'text-red-700',     'bg'=>'bg-red-50'],
                    ];
                    $totalWithMetrics = ($healthMetrics->sum('count') ?: 1);
                @endphp
                <div class="space-y-2.5">
                    @foreach($healthRows as $row)
                    @php $count = $healthMetrics[$row['key']]->count ?? 0; @endphp
                    <a href="{{ route('platform.tenants', ['health_level' => $row['key']]) }}"
                       @class([
                           'block p-2 rounded-xl transition-colors -mx-2',
                           'hover:bg-emerald-50' => $row['key'] === 'healthy',
                           'hover:bg-orange-50'  => $row['key'] === 'needs_attention',
                           'hover:bg-red-50'     => $row['key'] === 'at_risk',
                       ])>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-gray-600">{{ $row['label'] }}</span>
                            <span class="font-semibold {{ $row['text'] }}">{{ $count }} →</span>
                        </div>
                        <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="{{ $row['color'] }} h-full rounded-full transition-all"
                                 style="width: {{ $totalWithMetrics > 0 ? round(($count / $totalWithMetrics) * 100) : 0 }}%"></div>
                        </div>
                    </a>
                    @endforeach
                </div>

                {{-- Avg scores --}}
                <div class="mt-3 pt-3 border-t border-gray-100 grid grid-cols-3 gap-2">
                    @foreach($healthRows as $row)
                    @php $avg = round($healthMetrics[$row['key']]->avg_score ?? 0); @endphp
                    <div class="text-center">
                        <p class="text-lg font-bold {{ $row['text'] }}">{{ $avg }}</p>
                        <p class="text-xs text-gray-400">avg {{ Str::before($row['label'],' ') }}</p>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Industry breakdown --}}
            <div class="card">
                <div class="flex items-center gap-1 mb-3">
                    <h3 class="font-semibold text-[#1E1B4B]">By Industry</h3>
                    <x-info-tip text="Tenant count by industry vertical. Useful for identifying which sectors are growing fastest on the platform." position="left" />
                </div>
                <div class="space-y-2.5">
                    @forelse($industryData->take(5) as $item)
                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-gray-600 truncate flex-1 mr-2">{{ $item->industry }}</span>
                                <span class="font-semibold text-[#1E1B4B] shrink-0">{{ $item->count }}</span>
                            </div>
                            <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full rounded-full"
                                     style="width: {{ $totalTenants > 0 ? round(($item->count / $totalTenants) * 100) : 0 }}%; background: linear-gradient(90deg, #7B61FF, #FF6CAB)"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-gray-400 text-sm">No data</p>
                    @endforelse
                </div>
            </div>

            {{-- Alerts --}}
            <div class="card">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-1">
                        <h3 class="font-semibold text-[#1E1B4B]">Alerts</h3>
                        <x-info-tip text="Unread platform notifications. Critical alerts indicate payment failures, at-risk tenants, or items requiring immediate action." position="right" />
                    </div>
                    <span class="badge badge-red">{{ $stats['critical_alerts'] }} critical</span>
                </div>
                <div class="space-y-2">
                    @forelse($recentNotifications as $notif)
                        <a href="{{ $notif->action_url ?? route('platform.dashboard') }}"
                           class="flex items-start gap-2 p-2.5 rounded-xl bg-[#F0EFFA] hover:bg-purple-100 transition-colors">
                            <span @class(['w-2 h-2 rounded-full mt-1.5 shrink-0','bg-red-500'=>$notif->priority==='critical','bg-orange-500'=>$notif->priority==='high','bg-blue-500'=>$notif->priority==='medium','bg-gray-400'=>true])></span>
                            <p class="text-xs text-gray-600 flex-1">{{ Str::limit($notif->message, 65) }}</p>
                        </a>
                    @empty
                        <p class="text-gray-400 text-sm text-center py-2">No alerts</p>
                    @endforelse
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
