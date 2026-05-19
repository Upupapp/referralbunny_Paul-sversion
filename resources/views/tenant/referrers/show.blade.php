@extends('layouts.app')
@section('title', ($reseller->name ?? 'Referrer') . ' — Referrer Detail')
@section('nav') @include('tenant._nav') @endsection

@section('topbar-actions')
    <a href="{{ route('tenant.referrers', $tenant->id) }}" class="btn-secondary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        <span class="hidden sm:inline">All Referrers</span>
    </a>
    {{-- Resend Invite — only for referrers who haven't set up yet --}}
    @if(in_array($reseller->status, ['invited']) || (!$reseller->password && $reseller->status !== 'deactivated'))
    <form method="POST" action="{{ route('tenant.referrers.resend-invite', [$tenant->id, $reseller->id]) }}" class="inline">
        @csrf
        <button type="submit"
                class="px-3 py-2 rounded-xl text-xs font-semibold bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors border border-blue-100"
                onclick="return confirm('Resend invite to {{ addslashes($reseller->email) }}?')">
            <span class="hidden sm:inline">Resend Invite</span>
            <span class="sm:hidden">Invite</span>
        </button>
    </form>
    @endif
    @if($reseller->status !== 'deactivated')
    <button onclick="document.getElementById('deactivate-section').scrollIntoView({behavior:'smooth'})"
            class="px-3 py-2 rounded-xl text-xs font-semibold bg-red-50 text-red-600 hover:bg-red-100 transition-colors border border-red-100">
        Deactivate
    </button>
    @endif
@endsection

@section('content')
<div class="space-y-5">

    @if(session('success'))
    <div class="flex items-center gap-3 px-4 py-3.5 rounded-xl bg-emerald-50 border border-emerald-200">
        <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <p class="text-sm font-medium text-emerald-700">{{ session('success') }}</p>
    </div>
    @endif
    @if($errors->has('invite'))
    <div class="flex items-center gap-3 px-4 py-3.5 rounded-xl bg-red-50 border border-red-200">
        <svg class="w-5 h-5 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <p class="text-sm font-medium text-red-700">{{ $errors->first('invite') }}</p>
    </div>
    @endif

    {{-- ── Agreement Compliance Alert (top of page if non-compliant) ────── --}}
    @if($requiredTotal > 0 && !$agreementCompliant)
    <div class="flex items-start gap-3 px-4 py-3.5 rounded-xl bg-orange-50 border border-orange-200">
        <svg class="w-5 h-5 text-orange-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <div>
            <p class="text-sm font-semibold text-orange-700">Missing Agreements</p>
            <p class="text-xs text-orange-600 mt-0.5">
                {{ $reseller->name }} has signed {{ $signedRequired }} of {{ $requiredTotal }} required agreement{{ $requiredTotal !== 1 ? 's' : '' }}.
                Review and mark agreements below.
            </p>
        </div>
    </div>
    @elseif($requiredTotal > 0 && $agreementCompliant)
    <div class="flex items-start gap-3 px-4 py-3.5 rounded-xl bg-emerald-50 border border-emerald-200">
        <svg class="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
        </svg>
        <p class="text-sm font-semibold text-emerald-700">All required agreements signed — Compliant</p>
    </div>
    @endif

    {{-- ── Header ─────────────────────────────────────────────────────────── --}}
    <div class="card flex flex-col sm:flex-row items-start sm:items-center gap-4">
        {{-- Avatar --}}
        <div class="w-14 h-14 rounded-2xl bg-purple-100 flex items-center justify-center text-purple-700 text-xl font-bold shrink-0">
            @php
                $initials = collect(explode(' ', $reseller->name ?? '?'))->map(fn($w) => strtoupper($w[0] ?? ''))->take(2)->join('');
            @endphp
            @if($reseller->profile_photo_path)
                <img src="{{ Storage::url($reseller->profile_photo_path) }}" class="w-14 h-14 rounded-2xl object-cover" alt="{{ $reseller->name }}">
            @else
                {{ $initials ?: '?' }}
            @endif
        </div>

        {{-- Identity --}}
        <div class="flex-1 min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold text-[#1E1B4B] truncate">{{ $reseller->name ?? 'Unnamed Referrer' }}</h1>
                {{-- Status badge --}}
                @php
                    $statusColor = match($reseller->status) {
                        'active', 'nda_signed' => 'bg-emerald-100 text-emerald-700',
                        'invited'              => 'bg-orange-100 text-orange-600',
                        'deactivated'          => 'bg-red-100 text-red-600',
                        default                => 'bg-gray-100 text-gray-500',
                    };
                    $statusLabel = match($reseller->status) {
                        'active'      => 'Active',
                        'nda_signed'  => 'NDA Signed',
                        'invited'     => 'Pending Invite',
                        'deactivated' => 'Deactivated',
                        default       => ucfirst($reseller->status ?? 'Unknown'),
                    };
                @endphp
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $statusColor }}">{{ $statusLabel }}</span>

                {{-- Completeness badge --}}
                @if(!$reseller->email)
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-600">Needs Email</span>
                @elseif(!$reseller->password && $reseller->status === 'invited')
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-600">Pending Setup</span>
                @elseif($completeness['status'] === 'complete')
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">Complete</span>
                @endif

                {{-- Agreement compliance badge --}}
                @if($requiredTotal > 0)
                    @if($agreementCompliant)
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700"
                              title="All required agreements signed">
                            Agreements ✓
                        </span>
                    @else
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-orange-100 text-orange-600"
                              title="{{ $signedRequired }}/{{ $requiredTotal }} required agreements signed">
                            {{ $signedRequired }}/{{ $requiredTotal }} Agreements
                        </span>
                    @endif
                @endif

                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-purple-100 text-purple-600">Referrer</span>
            </div>
            <p class="text-sm text-gray-400 mt-0.5">{{ $reseller->email ?? '—' }}{{ $reseller->phone ? ' · ' . $reseller->phone : '' }}</p>
        </div>

        {{-- Quick stats --}}
        <div class="flex gap-4 shrink-0 text-center">
            <div>
                <p class="text-xl font-bold text-[#1E1B4B]">{{ $performance['total_deals'] }}</p>
                <p class="text-xs text-gray-400">Total Deals</p>
            </div>
            <div>
                <p class="text-xl font-bold text-emerald-600">{{ $performance['active_deals'] }}</p>
                <p class="text-xs text-gray-400">Active</p>
            </div>
            <div>
                <p class="text-xl font-bold text-[#1E1B4B]">{{ $performance['closed_won_deals'] }}</p>
                <p class="text-xs text-gray-400">Closed</p>
            </div>
        </div>
    </div>

    {{-- ── KPI Cards ────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Total Deal Value</span>
                <p class="text-2xl font-bold text-[#1E1B4B] mt-1.5">
                    &#8369;{{ number_format($performance['total_deal_value'], 0) }}
                </p>
            </div>
            <div class="kpi-icon bg-blue-100 ml-3 shrink-0">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Conversion Rate</span>
                <p class="text-2xl font-bold text-[#1E1B4B] mt-1.5">
                    {{ $performance['conversion_rate'] !== null ? $performance['conversion_rate'] . '%' : '—' }}
                </p>
            </div>
            <div class="kpi-icon bg-purple-100 ml-3 shrink-0">
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
            </div>
        </div>
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide flex items-center gap-1">
                    Pending Commission
                </span>
                <p class="text-2xl font-bold text-orange-500 mt-1.5">
                    &#8369;{{ number_format($performance['pending_commission'], 0) }}
                </p>
            </div>
            <div class="kpi-icon bg-orange-100 ml-3 shrink-0">
                <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </div>
        </div>
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Expiring Deals</span>
                <p class="text-2xl font-bold mt-1.5 {{ $performance['expiring_deals'] > 0 ? 'text-red-500' : 'text-[#1E1B4B]' }}">
                    {{ $performance['expiring_deals'] }}
                </p>
            </div>
            <div class="kpi-icon {{ $performance['expiring_deals'] > 0 ? 'bg-red-100' : 'bg-gray-100' }} ml-3 shrink-0">
                <svg class="w-5 h-5 {{ $performance['expiring_deals'] > 0 ? 'text-red-500' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- ── Left: Profile + Completeness ──────────────────────────────── --}}
        <div class="space-y-5">

            {{-- Profile --}}
            <div class="card space-y-4">
                <h2 class="font-semibold text-[#1E1B4B] text-sm">Profile Details</h2>
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-400 shrink-0">Full Name</dt>
                        <dd class="font-medium text-[#1E1B4B] text-right">{{ $reseller->name ?? '—' }}</dd>
                    </div>
                    @if($reseller->nickname)
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-400 shrink-0">Nickname</dt>
                        <dd class="font-medium text-[#1E1B4B] text-right">{{ $reseller->nickname }}</dd>
                    </div>
                    @endif
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-400 shrink-0">Email</dt>
                        <dd class="font-medium text-[#1E1B4B] text-right truncate">
                            {{ $reseller->email ?? '' }}
                            @if(!$reseller->email)<span class="badge badge-red text-[10px]">Missing</span>@endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-400 shrink-0">Phone</dt>
                        <dd class="font-medium text-[#1E1B4B] text-right">
                            {{ $reseller->phone ?? '' }}
                            @if(!$reseller->phone)<span class="text-gray-300">—</span>@endif
                        </dd>
                    </div>
                    @if($reseller->job_title)
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-400 shrink-0">Job Title</dt>
                        <dd class="font-medium text-[#1E1B4B] text-right">{{ $reseller->job_title }}</dd>
                    </div>
                    @endif
                    @if($reseller->organization)
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-400 shrink-0">Organization</dt>
                        <dd class="font-medium text-[#1E1B4B] text-right">{{ $reseller->organization }}</dd>
                    </div>
                    @endif
                    @if($reseller->territory)
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-400 shrink-0">Territory</dt>
                        <dd class="font-medium text-[#1E1B4B] text-right">{{ $reseller->territory }}</dd>
                    </div>
                    @endif
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-400 shrink-0">Status</dt>
                        <dd><span class="badge {{ in_array($reseller->status, ['active','nda_signed']) ? 'badge-green' : ($reseller->status === 'invited' ? 'badge-orange' : 'badge-gray') }}">{{ $statusLabel }}</span></dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-400 shrink-0">Joined</dt>
                        <dd class="font-medium text-[#1E1B4B] text-right">
                            {{ $reseller->joined_date ? \Carbon\Carbon::parse($reseller->joined_date)->format('M j, Y') : '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-400 shrink-0">Added</dt>
                        <dd class="font-medium text-[#1E1B4B] text-right">
                            {{ $reseller->created_at ? \Carbon\Carbon::parse($reseller->created_at)->format('M j, Y') : '—' }}
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Details Completeness --}}
            <div class="card space-y-3">
                <h2 class="font-semibold text-[#1E1B4B] text-sm">Profile Completeness</h2>
                @php
                    $checks = [
                        ['label' => 'Name',          'ok' => !empty($reseller->name)],
                        ['label' => 'Email',         'ok' => !empty($reseller->email)],
                        ['label' => 'Phone',         'ok' => !empty($reseller->phone)],
                        ['label' => 'Account Setup', 'ok' => !empty($reseller->password) || in_array($reseller->status, ['active','nda_signed'])],
                        ['label' => 'Profile Photo', 'ok' => !empty($reseller->profile_photo_path), 'optional' => true],
                        ['label' => 'Organization',  'ok' => !empty($reseller->organization), 'optional' => true],
                    ];
                    $required = array_filter($checks, fn($c) => empty($c['optional']));
                    $doneCount = count(array_filter($checks, fn($c) => $c['ok']));
                    $totalCount = count($checks);
                @endphp

                <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden">
                    <div class="h-full rounded-full bg-[#7B61FF] transition-all"
                         style="width: {{ round($doneCount / $totalCount * 100) }}%"></div>
                </div>
                <p class="text-xs text-gray-400">{{ $doneCount }}/{{ $totalCount }} fields complete</p>

                <div class="space-y-1.5">
                    @foreach($checks as $check)
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-gray-600">
                            {{ $check['label'] }}
                            @if(!empty($check['optional']))<span class="text-gray-300 ml-1">(optional)</span>@endif
                        </span>
                        @if($check['ok'])
                            <span class="text-emerald-500 font-medium flex items-center gap-0.5">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                Done
                            </span>
                        @else
                            <span class="text-orange-500 font-medium">Missing</span>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Commission Summary --}}
            @if($performance['total_deal_value'] > 0)
            <div class="card space-y-3">
                <h2 class="font-semibold text-[#1E1B4B] text-sm flex items-center gap-1.5">
                    Commission Summary
                    <span class="text-[10px] text-gray-400 font-normal">(existing rules applied)</span>
                </h2>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Pending</span>
                        <span class="font-semibold text-orange-500">&#8369;{{ number_format($performance['pending_commission'], 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Locked</span>
                        <span class="font-semibold text-blue-600">&#8369;{{ number_format($performance['locked_commission'], 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Paid</span>
                        <span class="font-semibold text-emerald-600">&#8369;{{ number_format($performance['paid_commission'], 0) }}</span>
                    </div>
                </div>
            </div>
            @endif

            {{-- Agreements & Compliance --}}
            <div class="card space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold text-[#1E1B4B] text-sm">Agreements & Compliance</h2>
                    @if($requiredTotal > 0)
                        @if($agreementCompliant)
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                Compliant
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-orange-100 text-orange-600">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01"/></svg>
                                {{ $signedRequired }}/{{ $requiredTotal }} Signed
                            </span>
                        @endif
                    @else
                        <span class="text-xs text-gray-400">No agreements configured</span>
                    @endif
                </div>

                @if($agreements->isEmpty())
                    <p class="text-xs text-gray-400">No agreements have been configured for this tenant yet.</p>
                @else
                    <div class="space-y-3">
                        @foreach($agreements as $ag)
                        @php $signed = !empty($ag->agreed_at); @endphp
                        <div class="flex items-start gap-3 p-3 rounded-xl border transition-colors
                            {{ $signed ? 'border-emerald-100 bg-emerald-50/40' : ($ag->is_required ? 'border-orange-100 bg-orange-50/40' : 'border-gray-100') }}">

                            {{-- Status icon --}}
                            <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 mt-0.5
                                {{ $signed ? 'bg-emerald-100' : ($ag->is_required ? 'bg-orange-100' : 'bg-gray-100') }}">
                                @if($signed)
                                    <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                @else
                                    <svg class="w-4 h-4 {{ $ag->is_required ? 'text-orange-500' : 'text-gray-400' }}"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                @endif
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5 mb-0.5">
                                    <p class="text-sm font-semibold text-[#1E1B4B]">{{ $ag->label }}</p>
                                    @if($ag->is_required)
                                        <span class="px-1.5 py-0 rounded text-[10px] font-bold bg-red-100 text-red-600">Required</span>
                                    @else
                                        <span class="px-1.5 py-0 rounded text-[10px] font-bold bg-gray-100 text-gray-500">Optional</span>
                                    @endif
                                    @if($ag->version)
                                        <span class="text-[10px] text-gray-400">v{{ $ag->version }}</span>
                                    @endif
                                </div>

                                @if($ag->description)
                                    <p class="text-xs text-gray-400">{{ $ag->description }}</p>
                                @endif

                                @if($signed)
                                    <p class="text-xs text-emerald-600 font-medium mt-1">
                                        Signed {{ \Carbon\Carbon::parse($ag->agreed_at)->format('M j, Y') }}
                                        @if($ag->agreed_by_name)
                                            &nbsp;·&nbsp; by {{ $ag->agreed_by_name }}
                                        @endif
                                    </p>
                                @else
                                    <p class="text-xs {{ $ag->is_required ? 'text-orange-500 font-medium' : 'text-gray-400' }} mt-1">
                                        {{ $ag->is_required ? 'Not yet signed — required' : 'Not yet signed (optional)' }}
                                    </p>
                                @endif

                                @if($ag->file_url)
                                    <a href="{{ $ag->file_url }}" target="_blank"
                                       class="inline-flex items-center gap-1 text-xs text-[#7B61FF] hover:underline mt-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        View Document
                                    </a>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>

                    {{-- Summary footer --}}
                    @if($requiredTotal > 0)
                    <div class="pt-2 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                        <span>{{ $signedRequired }}/{{ $requiredTotal }} required agreements signed</span>
                        <a href="{{ route('tenant.referrers', $tenant->id) }}"
                           class="text-[#7B61FF] hover:underline font-medium">
                            Manage from Referrers list →
                        </a>
                    </div>
                    @endif
                @endif
            </div>

        </div>

        {{-- ── Right: Deals + Activity ──────────────────────────────────── --}}
        <div class="lg:col-span-2 space-y-5">

            {{-- Associated Deals --}}
            <div class="card p-0 overflow-hidden">
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
                    <p class="text-sm font-semibold text-[#1E1B4B]">Associated Deals ({{ $recentDeals->count() }})</p>
                    @if($performance['total_deals'] > 10)
                        <a href="{{ route('tenant.deals', $tenant->id) }}" class="text-xs text-[#7B61FF] hover:underline">View all</a>
                    @endif
                </div>

                @if($recentDeals->isEmpty())
                    <div class="py-10 text-center text-gray-400 text-sm">No deals assigned yet.</div>
                @else
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="table-head">
                                <th>Deal / Org</th>
                                <th>Stage</th>
                                <th class="hidden sm:table-cell">Value</th>
                                <th class="hidden md:table-cell">Commission</th>
                                <th>Status</th>
                                <th class="hidden lg:table-cell">Last Update</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentDeals as $deal)
                            <tr class="table-row">
                                <td>
                                    <a href="{{ route('tenant.deals.show', [$tenant->id, $deal->id]) }}"
                                       class="font-medium text-[#1E1B4B] hover:text-[#7B61FF] transition-colors text-sm truncate block max-w-[160px]">
                                        {{ $deal->name ?? 'Unnamed Deal' }}
                                    </a>
                                </td>
                                <td>
                                    <span class="text-xs text-gray-500 capitalize">{{ $deal->stage ?? '—' }}</span>
                                </td>
                                <td class="hidden sm:table-cell tabular-nums text-sm font-semibold text-[#1E1B4B]">
                                    &#8369;{{ number_format($deal->deal_value ?? 0, 0) }}
                                </td>
                                <td class="hidden md:table-cell">
                                    @php $commStatus = $deal->commission_status ?? null; @endphp
                                    @if($commStatus)
                                        <span class="badge {{ $commStatus === 'paid' ? 'badge-green' : ($commStatus === 'locked' ? 'badge-blue' : 'badge-orange') }} text-xs">
                                            {{ ucfirst($commStatus) }}
                                        </span>
                                    @else
                                        <span class="text-gray-300 text-xs">—</span>
                                    @endif
                                </td>
                                <td>
                                    @php $st = $deal->status ?? 'active'; @endphp
                                    <span class="badge {{ $st === 'active' ? 'badge-green' : ($st === 'expiring' ? 'badge-orange' : 'badge-gray') }} text-xs">
                                        {{ ucfirst($st) }}
                                    </span>
                                </td>
                                <td class="hidden lg:table-cell text-gray-400 text-xs tabular-nums">
                                    {{ $deal->updated_at ? \Carbon\Carbon::parse($deal->updated_at)->diffForHumans() : '—' }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>

            {{-- Recent Activity --}}
            @if(!empty($recentActivity))
            <div class="card space-y-4">
                <h2 class="font-semibold text-[#1E1B4B] text-sm">Recent Activity</h2>
                <div class="space-y-3">
                    @foreach($recentActivity as $item)
                    <div class="flex items-start gap-3">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0 mt-0.5
                            {{ ($item['severity'] ?? 'normal') === 'urgent' ? 'bg-red-100 text-red-500' : (($item['severity'] ?? 'normal') === 'high' ? 'bg-orange-100 text-orange-500' : 'bg-gray-100 text-gray-500') }}">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-[#1E1B4B]">{{ $item['title'] ?? '' }}</p>
                            @if(!empty($item['summary']))
                                <p class="text-xs text-gray-400 mt-0.5">{{ $item['summary'] }}</p>
                            @endif
                            <p class="text-[10px] text-gray-300 mt-0.5">
                                {{ $item['occurred_ago'] ?? '' }}
                            </p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Deactivate Section --}}
            @if($reseller->status !== 'deactivated')
            <div id="deactivate-section"
                 class="card border border-red-100 bg-red-50/30"
                 x-data="{ open: false }">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-red-700">Deactivate Referrer</p>
                        <p class="text-xs text-gray-500 mt-0.5">Remove portal access. Historical deals, commissions, and audit logs are preserved.</p>
                    </div>
                    <button @click="open = !open"
                            class="px-3 py-2 rounded-xl text-xs font-semibold bg-red-500 text-white hover:bg-red-600 transition-colors shrink-0">
                        Deactivate
                    </button>
                </div>
                <p x-show="open" class="text-xs text-red-600 mt-3">
                    Use the <strong>Deactivate</strong> button from the Referrers list to perform this action with full double-authentication.
                    <a href="{{ route('tenant.referrers', $tenant->id) }}" class="underline ml-1">Go to Referrers list →</a>
                </p>
            </div>
            @endif

        </div>
    </div>

</div>
@endsection
