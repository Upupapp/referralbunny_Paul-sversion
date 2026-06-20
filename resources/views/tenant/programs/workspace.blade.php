@extends('layouts.app')

@section('title', $program->name . ' — Program Workspace')
@section('stitch_page', 'tenant-program-workspace')

@section('nav')
    @include('tenant._nav', ['tenant' => $tenant])
@endsection

@section('content')
@php
    $tabs = [
        'overview'     => ['label' => 'Overview',      'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        'offers'       => ['label' => 'Offers',         'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        'members'      => ['label' => 'Members',        'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'],
        'contracts'    => ['label' => 'Contracts',      'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        'analytics'    => ['label' => 'Analytics',      'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
        'action-items' => ['label' => 'Action Items',   'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
        'settings'     => ['label' => 'Settings',       'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
        'intake'       => ['label' => 'Intake',         'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 17h.01'],
        'public-page'  => ['label' => 'Public Page',    'icon' => 'M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14'],
        'notifications'=> ['label' => 'Notifications',  'icon' => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9'],
        'access'       => ['label' => 'Access',         'icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'],
    ];
@endphp
<div class="flex flex-col h-full" x-data="{ activeTab: @js($activeTab) }">

    {{-- ── Workspace Header ────────────────────────────────────────────────── --}}
    <div class="bg-white border-b border-gray-200 px-6 pt-5 pb-0">
        <div class="flex items-start justify-between gap-4 mb-4">
            <div>
                <a href="{{ route('tenant.programs.index', $tenant->id) }}"
                   class="text-xs text-gray-400 hover:text-gray-600 flex items-center gap-1 mb-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Programs
                </a>
                <h1 class="text-xl font-bold text-heading">{{ $program->name }}</h1>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                {{-- Status badge --}}
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium
                    {{ $program->status === 'active'    ? 'bg-green-100 text-green-700'   : '' }}
                    {{ $program->status === 'draft'     ? 'bg-gray-100 text-gray-600'     : '' }}
                    {{ $program->status === 'paused'    ? 'bg-amber-100 text-amber-700'   : '' }}
                    {{ $program->status === 'scheduled' ? 'bg-blue-100 text-blue-700'     : '' }}
                    {{ $program->status === 'ended'     ? 'bg-red-100 text-red-700'       : '' }}
                    {{ $program->status === 'archived'  ? 'bg-gray-100 text-gray-400'     : '' }}
                ">{{ ucfirst($program->status) }}</span>

                {{-- Lifecycle actions --}}
                @can('launch', $program)
                    @if(in_array($program->status, ['draft', 'scheduled', 'paused']))
                    <form method="POST" action="{{ route('tenant.programs.launch', [$tenant->id, $program->id]) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm">
                            {{ $program->status === 'paused' ? 'Resume' : 'Launch' }}
                        </button>
                    </form>
                    @endif
                @endcan

                @can('pause', $program)
                    @if($program->status === 'active')
                    <form method="POST" action="{{ route('tenant.programs.pause', [$tenant->id, $program->id]) }}">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm">Pause</button>
                    </form>
                    @endif
                @endcan
            </div>
        </div>

        {{-- ── Tab bar ──────────────────────────────────────────────────────── --}}
        <nav class="flex gap-0.5 -mb-px overflow-x-auto" aria-label="Program workspace tabs">
            @foreach($tabs as $key => $tab)
            <a href="{{ route('tenant.programs.workspace', [$tenant->id, $program->id]) }}?tab={{ $key }}"
               @click.prevent="activeTab = '{{ $key }}'; history.replaceState(null, '', '?tab={{ $key }}')"
               :aria-current="activeTab === '{{ $key }}' ? 'page' : 'false'"
               :class="activeTab === '{{ $key }}' ? 'tab-active' : 'tab'"
               class="tab whitespace-nowrap">
                {{ $tab['label'] }}
            </a>
            @endforeach
        </nav>
    </div>

    {{-- ── Tab content panels ───────────────────────────────────────────────── --}}
    <div class="flex-1 overflow-auto">

        {{-- ── Overview tab ──────────────────────────────────────────────────── --}}
        <div x-show="activeTab === 'overview'" x-cloak class="p-6 max-w-4xl mx-auto space-y-6">

            @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                {{ session('success') }}
            </div>
            @endif

            <form method="POST"
                  action="{{ route('tenant.programs.update', [$tenant->id, $program->id]) }}"
                  x-data="{ submitting: false }" @submit="submitting = true"
                  class="rounded-xl border border-gray-200 bg-white shadow-sm divide-y divide-gray-100">
                @csrf @method('PATCH')

                <div class="p-6 space-y-5">
                    <h2 class="text-base font-semibold text-heading">Program details</h2>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input type="text" name="name" value="{{ old('name', $program->name) }}"
                                   class="input w-full" maxlength="120" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                            <select name="program_type" class="input w-full">
                                @foreach(\App\Models\Program::allTypes() as $type)
                                <option value="{{ $type }}" {{ $program->program_type === $type ? 'selected' : '' }}>
                                    {{ ucfirst(str_replace('_', ' ', $type)) }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Visibility</label>
                            <select name="public_visibility" class="input w-full">
                                <option value="private"  {{ $program->public_visibility === 'private'  ? 'selected' : '' }}>Private</option>
                                <option value="unlisted" {{ $program->public_visibility === 'unlisted' ? 'selected' : '' }}>Unlisted</option>
                                <option value="public"   {{ $program->public_visibility === 'public'   ? 'selected' : '' }}>Public</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Short description</label>
                            <textarea name="short_description" rows="2"
                                      class="input w-full resize-none" maxlength="500">{{ old('short_description', $program->short_description) }}</textarea>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Full description</label>
                            <textarea name="full_description" rows="4"
                                      class="input w-full resize-none" maxlength="5000">{{ old('full_description', $program->full_description) }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 bg-gray-50 rounded-b-xl flex items-center justify-between gap-3">
                    <div class="text-xs text-gray-400">
                        Last updated {{ $program->updated_at->diffForHumans() }}
                    </div>
                    @can('update', $program)
                    <button type="submit" class="btn btn-primary" :disabled="submitting" x-text="submitting ? 'Saving…' : 'Save changes'"></button>
                    @endcan
                </div>
            </form>

            {{-- Danger zone (delete draft) --}}
            @can('delete', $program)
            <div class="rounded-xl border border-red-200 bg-red-50 p-5">
                <h3 class="text-sm font-semibold text-red-800">Danger zone</h3>
                <p class="text-xs text-red-700 mt-1">Deleting a draft program is permanent. Only draft programs with no operational history can be deleted.</p>
                <form method="POST"
                      action="{{ route('tenant.programs.destroy', [$tenant->id, $program->id]) }}"
                      class="mt-3"
                      onsubmit="return confirm('Delete program \'{{ addslashes($program->name) }}\'? This cannot be undone.')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm">Delete program</button>
                </form>
            </div>
            @endcan
        </div>

        {{-- ── Members tab ───────────────────────────────────────────────────── --}}
        <div x-show="activeTab === 'members'" x-cloak class="p-6 max-w-5xl mx-auto space-y-6" x-data="{ memberType: 'referrers' }">

            @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
            @endif
            @if($errors->any())
            <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
            @endif

            @php
                $memberTransitions = [
                    'invited'   => ['approved', 'removed'],
                    'applied'   => ['approved', 'removed'],
                    'approved'  => ['active', 'suspended', 'removed'],
                    'active'    => ['paused', 'suspended', 'removed'],
                    'paused'    => ['active', 'suspended', 'removed'],
                    'suspended' => ['active', 'removed'],
                ];
                $statusBadge = fn (string $status) => match ($status) {
                    'active'    => 'bg-green-100 text-green-700',
                    'approved'  => 'bg-blue-100 text-blue-700',
                    'paused'    => 'bg-amber-100 text-amber-700',
                    'suspended', 'removed', 'expired' => 'bg-red-100 text-red-700',
                    default     => 'bg-gray-100 text-gray-600',
                };
            @endphp

            <div class="flex gap-2">
                <button type="button" @click="memberType = 'referrers'"
                        :class="memberType === 'referrers' ? 'btn btn-primary btn-sm' : 'btn btn-ghost btn-sm'">Referrers</button>
                <button type="button" @click="memberType = 'partners'"
                        :class="memberType === 'partners' ? 'btn btn-primary btn-sm' : 'btn btn-ghost btn-sm'">Partners</button>
            </div>

            {{-- Referrers --}}
            <div x-show="memberType === 'referrers'" x-cloak class="space-y-4">
                @can('managePeople', $program)
                <form method="POST" action="{{ route('tenant.programs.members.referrers.attach', [$tenant->id, $program->id]) }}"
                      class="rounded-xl border border-gray-200 bg-white shadow-sm p-4 flex items-end gap-3">
                    @csrf
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Add referrer</label>
                        <select name="reseller_id" class="input w-full" required>
                            <option value="">Select a referrer…</option>
                            @foreach($tenantResellers ?? [] as $reseller)
                            <option value="{{ $reseller->id }}">{{ $reseller->name }} ({{ $reseller->email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Add</button>
                </form>
                @endcan

                <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                            <tr>
                                <th class="px-4 py-2">Referrer</th>
                                <th class="px-4 py-2">Status</th>
                                <th class="px-4 py-2">Source</th>
                                <th class="px-4 py-2">Joined</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($referrerMemberships ?? [] as $membership)
                            <tr>
                                <td class="px-4 py-3">{{ $membership->reseller?->name ?? '—' }}<div class="text-xs text-gray-400">{{ $membership->reseller?->email }}</div></td>
                                <td class="px-4 py-3"><span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusBadge($membership->status) }}">{{ ucfirst($membership->status) }}</span></td>
                                <td class="px-4 py-3 text-gray-500">{{ ucfirst($membership->source) }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $membership->joined_at?->format('M j, Y') ?? '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    @can('managePeople', $program)
                                    @foreach($memberTransitions[$membership->status] ?? [] as $next)
                                    <form method="POST" action="{{ route('tenant.programs.members.referrers.status', [$tenant->id, $program->id, $membership->id]) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="status" value="{{ $next }}">
                                        <button type="submit" class="btn btn-ghost btn-xs">{{ ucfirst($next) }}</button>
                                    </form>
                                    @endforeach
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">No referrers yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $referrerMemberships?->links() }}
            </div>

            {{-- Partners --}}
            <div x-show="memberType === 'partners'" x-cloak class="space-y-4">
                @can('managePeople', $program)
                <form method="POST" action="{{ route('tenant.programs.members.partners.attach', [$tenant->id, $program->id]) }}"
                      class="rounded-xl border border-gray-200 bg-white shadow-sm p-4 flex items-end gap-3">
                    @csrf
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Add partner</label>
                        <select name="partner_id" class="input w-full" required>
                            <option value="">Select a partner…</option>
                            @foreach($tenantPartners ?? [] as $partner)
                            <option value="{{ $partner->id }}">{{ trim($partner->first_name . ' ' . $partner->last_name) }} ({{ $partner->email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Add</button>
                </form>
                @endcan

                <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                            <tr>
                                <th class="px-4 py-2">Partner</th>
                                <th class="px-4 py-2">Status</th>
                                <th class="px-4 py-2">Source</th>
                                <th class="px-4 py-2">Joined</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($partnerMemberships ?? [] as $membership)
                            <tr>
                                <td class="px-4 py-3">{{ trim(($membership->partner?->first_name ?? '') . ' ' . ($membership->partner?->last_name ?? '')) ?: '—' }}<div class="text-xs text-gray-400">{{ $membership->partner?->email }}</div></td>
                                <td class="px-4 py-3"><span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusBadge($membership->status) }}">{{ ucfirst($membership->status) }}</span></td>
                                <td class="px-4 py-3 text-gray-500">{{ ucfirst($membership->source) }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $membership->joined_at?->format('M j, Y') ?? '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    @can('managePeople', $program)
                                    @foreach($memberTransitions[$membership->status] ?? [] as $next)
                                    <form method="POST" action="{{ route('tenant.programs.members.partners.status', [$tenant->id, $program->id, $membership->id]) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="status" value="{{ $next }}">
                                        <button type="submit" class="btn btn-ghost btn-xs">{{ ucfirst($next) }}</button>
                                    </form>
                                    @endforeach
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">No partners yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $partnerMemberships?->links() }}
            </div>
        </div>

        {{-- ── Settings tab ──────────────────────────────────────────────────── --}}
        <div x-show="activeTab === 'settings'" x-cloak class="p-6 max-w-4xl mx-auto space-y-6">

            @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
            @endif

            <form method="POST"
                  action="{{ route('tenant.programs.update', [$tenant->id, $program->id]) }}"
                  x-data="{ submitting: false }" @submit="submitting = true"
                  class="rounded-xl border border-gray-200 bg-white shadow-sm divide-y divide-gray-100">
                @csrf @method('PATCH')

                <div class="p-6 space-y-5">
                    <h2 class="text-base font-semibold text-heading">Attribution &amp; eligibility</h2>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Attribution model</label>
                            <select name="attribution_model" class="input w-full">
                                @foreach(['first_touch','last_touch','manual','code','link','deal_registration'] as $model)
                                <option value="{{ $model }}" {{ $program->attribution_model === $model ? 'selected' : '' }}>{{ ucfirst(str_replace('_',' ',$model)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Attribution window (days)</label>
                            <input type="number" name="attribution_window_days" min="1" max="365"
                                   value="{{ old('attribution_window_days', $program->attribution_window_days) }}" class="input w-full">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Referral expiry (days)</label>
                            <input type="number" name="referral_expiry_days" min="1" max="730"
                                   value="{{ old('referral_expiry_days', $program->referral_expiry_days) }}" class="input w-full">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Duplicate referral policy</label>
                            <select name="duplicate_referral_policy" class="input w-full">
                                @foreach(['reject','allow','flag'] as $policy)
                                <option value="{{ $policy }}" {{ $program->duplicate_referral_policy === $policy ? 'selected' : '' }}>{{ ucfirst($policy) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Organization uniqueness</label>
                            <select name="organization_uniqueness_policy" class="input w-full">
                                <option value="one_per_org"    {{ $program->organization_uniqueness_policy === 'one_per_org'    ? 'selected' : '' }}>One referral per organization</option>
                                <option value="allow_multiple" {{ $program->organization_uniqueness_policy === 'allow_multiple' ? 'selected' : '' }}>Allow multiple</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Existing customer policy</label>
                            <select name="existing_customer_policy" class="input w-full">
                                @foreach(['reject','allow','flag'] as $policy)
                                <option value="{{ $policy }}" {{ $program->existing_customer_policy === $policy ? 'selected' : '' }}>{{ ucfirst($policy) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Self-referral policy</label>
                            <select name="self_referral_policy" class="input w-full">
                                @foreach(['reject','allow'] as $policy)
                                <option value="{{ $policy }}" {{ $program->self_referral_policy === $policy ? 'selected' : '' }}>{{ ucfirst($policy) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="p-6 space-y-5">
                    <h2 class="text-base font-semibold text-heading">Locale</h2>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Currency</label>
                            <select name="default_currency" class="input w-full">
                                @foreach(['PHP'=>'Philippine Peso','USD'=>'US Dollar','EUR'=>'Euro','GBP'=>'British Pound','SGD'=>'Singapore Dollar','AUD'=>'Australian Dollar','CAD'=>'Canadian Dollar','JPY'=>'Japanese Yen'] as $code => $label)
                                <option value="{{ $code }}" {{ $program->default_currency === $code ? 'selected' : '' }}>{{ $code }} — {{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Timezone</label>
                            <select name="timezone" class="input w-full">
                                @foreach(['Asia/Manila','Asia/Singapore','Asia/Hong_Kong','Asia/Tokyo','Australia/Sydney','Europe/London','America/New_York','America/Los_Angeles','UTC'] as $tz)
                                <option value="{{ $tz }}" {{ $program->timezone === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="p-6 space-y-5">
                    <h2 class="text-base font-semibold text-heading">Enrollment &amp; referral windows</h2>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Enrollment opens</label>
                            <input type="datetime-local" name="enrollment_opens_at"
                                   value="{{ old('enrollment_opens_at', $program->enrollment_opens_at?->format('Y-m-d\TH:i')) }}" class="input w-full">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Enrollment closes</label>
                            <input type="datetime-local" name="enrollment_closes_at"
                                   value="{{ old('enrollment_closes_at', $program->enrollment_closes_at?->format('Y-m-d\TH:i')) }}" class="input w-full">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Referral period opens</label>
                            <input type="datetime-local" name="referral_period_opens_at"
                                   value="{{ old('referral_period_opens_at', $program->referral_period_opens_at?->format('Y-m-d\TH:i')) }}" class="input w-full">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Referral period closes</label>
                            <input type="datetime-local" name="referral_period_closes_at"
                                   value="{{ old('referral_period_closes_at', $program->referral_period_closes_at?->format('Y-m-d\TH:i')) }}" class="input w-full">
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 bg-gray-50 rounded-b-xl flex items-center justify-end gap-3">
                    @can('update', $program)
                    <button type="submit" class="btn btn-primary" :disabled="submitting" x-text="submitting ? 'Saving…' : 'Save changes'"></button>
                    @endcan
                </div>
            </form>
        </div>

        {{-- ── Offers tab ────────────────────────────────────────────────────── --}}
        <div x-show="activeTab === 'offers'" x-cloak class="p-6 max-w-4xl mx-auto space-y-6">

            @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
            @endif
            @if($errors->any())
            <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
            @endif

            @can('update', $program)
            <form method="POST" action="{{ route('tenant.programs.offers.store', [$tenant->id, $program->id]) }}"
                  x-data="{ submitting: false, rewardModel: 'fixed' }" @submit="submitting = true"
                  class="rounded-xl border border-gray-200 bg-white shadow-sm divide-y divide-gray-100">
                @csrf
                <div class="p-6 space-y-5">
                    <h2 class="text-base font-semibold text-heading">New offer</h2>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input type="text" name="name" class="input w-full" maxlength="120" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Code (optional)</label>
                            <input type="text" name="code" class="input w-full" maxlength="60">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Visibility</label>
                            <select name="visibility" class="input w-full">
                                <option value="public">Public</option>
                                <option value="group_only">Group only</option>
                                <option value="hidden">Hidden</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Reward model</label>
                            <select name="reward_model" class="input w-full" x-model="rewardModel">
                                <option value="fixed">Fixed amount</option>
                                <option value="percentage">Percentage</option>
                            </select>
                        </div>
                        <div x-show="rewardModel === 'fixed'">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fixed amount</label>
                            <input type="number" name="fixed_amount" min="0" step="0.01" class="input w-full">
                        </div>
                        <div x-show="rewardModel === 'percentage'" x-cloak>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Percentage rate</label>
                            <input type="number" name="percentage_rate" min="0" max="100" step="0.01" class="input w-full">
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 rounded-b-xl flex items-center justify-end gap-3">
                    <button type="submit" class="btn btn-primary" :disabled="submitting" x-text="submitting ? 'Creating…' : 'Create offer'"></button>
                </div>
            </form>
            @endcan

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                        <tr>
                            <th class="px-4 py-2">Name</th>
                            <th class="px-4 py-2">Code</th>
                            <th class="px-4 py-2">Reward</th>
                            <th class="px-4 py-2">Visibility</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($offers ?? [] as $offer)
                        <tr>
                            <td class="px-4 py-3">{{ $offer->name }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $offer->code ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-500">
                                @if($offer->currentVersion)
                                    {{ $offer->currentVersion->reward_model === 'fixed'
                                        ? $offer->currentVersion->currency . ' ' . $offer->currentVersion->fixed_amount
                                        : $offer->currentVersion->percentage_rate . '%' }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ str_replace('_', ' ', ucfirst($offer->visibility)) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium
                                    {{ $offer->status === 'active'   ? 'bg-green-100 text-green-700' : '' }}
                                    {{ $offer->status === 'inactive' ? 'bg-gray-100 text-gray-600'   : '' }}
                                    {{ $offer->status === 'archived' ? 'bg-red-100 text-red-700'     : '' }}">
                                    {{ ucfirst($offer->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                @can('update', $program)
                                <form method="POST" action="{{ route('tenant.programs.offers.update', [$tenant->id, $program->id, $offer->id]) }}" class="inline">
                                    @csrf @method('PATCH')
                                    <select name="status" onchange="this.form.submit()" class="input input-sm">
                                        <option value="active"   {{ $offer->status === 'active'   ? 'selected' : '' }}>Active</option>
                                        <option value="inactive" {{ $offer->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                        <option value="archived" {{ $offer->status === 'archived' ? 'selected' : '' }}>Archived</option>
                                    </select>
                                </form>
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">No offers yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── Contracts & Action Items shared helpers ─────────────────────────── --}}
        @php
            $resolveMemberName = function ($row) use ($referrerMembershipsById, $partnerMembershipsById) {
                if ($row->membership_type === 'referrer') {
                    return (($referrerMembershipsById ?? [])[$row->membership_id] ?? null)?->reseller?->name ?? '—';
                }
                $partner = (($partnerMembershipsById ?? [])[$row->membership_id] ?? null)?->partner;
                return $partner ? (trim($partner->first_name . ' ' . $partner->last_name) ?: '—') : '—';
            };
            $contractStatusBadge = fn (string $status) => match ($status) {
                'active'              => 'bg-green-100 text-green-700',
                'proposed'            => 'bg-blue-100 text-blue-700',
                'declined', 'ended', 'expired' => 'bg-red-100 text-red-700',
                default               => 'bg-gray-100 text-gray-600',
            };
            $contractTransitions = [
                'proposed' => ['active', 'declined'],
                'active'   => ['ended', 'superseded'],
            ];
        @endphp

        {{-- ── Contracts tab ─────────────────────────────────────────────────── --}}
        <div x-show="activeTab === 'contracts'" x-cloak class="p-6 max-w-5xl mx-auto space-y-6" x-data="{ memberType: 'referrer' }">

            @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
            @endif
            @if($errors->any())
            <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
            @endif

            @can('managePeople', $program)
            <form method="POST" action="{{ route('tenant.programs.contracts.propose', [$tenant->id, $program->id]) }}"
                  x-data="{ submitting: false }" @submit="submitting = true"
                  class="rounded-xl border border-gray-200 bg-white shadow-sm divide-y divide-gray-100">
                @csrf
                <div class="p-6 space-y-5">
                    <h2 class="text-base font-semibold text-heading">Propose contract</h2>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Member type</label>
                            <select name="membership_type" class="input w-full" x-model="memberType">
                                <option value="referrer">Referrer</option>
                                <option value="partner">Partner</option>
                            </select>
                        </div>
                        <div x-show="memberType === 'referrer'">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Referrer</label>
                            <select name="membership_id" class="input w-full">
                                <option value="">Select a referrer…</option>
                                @foreach($programReferrerMemberships ?? [] as $membership)
                                <option value="{{ $membership->id }}">{{ $membership->reseller?->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div x-show="memberType === 'partner'" x-cloak>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Partner</label>
                            <select name="membership_id" class="input w-full">
                                <option value="">Select a partner…</option>
                                @foreach($programPartnerMemberships ?? [] as $membership)
                                <option value="{{ $membership->id }}">{{ trim(($membership->partner?->first_name ?? '') . ' ' . ($membership->partner?->last_name ?? '')) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Offer (optional)</label>
                            <select name="offer_version_id" class="input w-full">
                                <option value="">No offer</option>
                                @foreach($offers ?? [] as $offer)
                                    @if($offer->currentVersion)
                                    <option value="{{ $offer->currentVersion->id }}">{{ $offer->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 rounded-b-xl flex items-center justify-end gap-3">
                    <button type="submit" class="btn btn-primary" :disabled="submitting" x-text="submitting ? 'Proposing…' : 'Propose contract'"></button>
                </div>
            </form>
            @endcan

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                        <tr>
                            <th class="px-4 py-2">Member</th>
                            <th class="px-4 py-2">Type</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2">Offer</th>
                            <th class="px-4 py-2">Proposed</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($contracts ?? [] as $contract)
                        <tr>
                            <td class="px-4 py-3">{{ $resolveMemberName($contract) }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ ucfirst($contract->membership_type) }}</td>
                            <td class="px-4 py-3"><span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $contractStatusBadge($contract->status) }}">{{ ucfirst($contract->status) }}</span></td>
                            <td class="px-4 py-3 text-gray-500">{{ $contract->offerVersion?->offer?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $contract->proposed_at?->format('M j, Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                @can('managePeople', $program)
                                @foreach($contractTransitions[$contract->status] ?? [] as $next)
                                <form method="POST" action="{{ route('tenant.programs.contracts.status', [$tenant->id, $program->id, $contract->id]) }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="status" value="{{ $next }}">
                                    <button type="submit" class="btn btn-ghost btn-xs">{{ ucfirst($next) }}</button>
                                </form>
                                @endforeach
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">No contracts yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $contracts?->links() }}
        </div>

        {{-- ── Action Items tab ──────────────────────────────────────────────── --}}
        <div x-show="activeTab === 'action-items'" x-cloak class="p-6 max-w-5xl mx-auto space-y-6" x-data="{ memberType: 'referrer' }">

            @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
            @endif
            @if($errors->any())
            <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
            @endif

            @can('managePeople', $program)
            <form method="POST" action="{{ route('tenant.programs.action-items.store', [$tenant->id, $program->id]) }}"
                  x-data="{ submitting: false }" @submit="submitting = true"
                  class="rounded-xl border border-gray-200 bg-white shadow-sm divide-y divide-gray-100">
                @csrf
                <div class="p-6 space-y-5">
                    <h2 class="text-base font-semibold text-heading">New action item</h2>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Member type</label>
                            <select name="membership_type" class="input w-full" x-model="memberType">
                                <option value="referrer">Referrer</option>
                                <option value="partner">Partner</option>
                            </select>
                        </div>
                        <div x-show="memberType === 'referrer'">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Referrer</label>
                            <select name="membership_id" class="input w-full">
                                <option value="">Select a referrer…</option>
                                @foreach($programReferrerMemberships ?? [] as $membership)
                                <option value="{{ $membership->id }}">{{ $membership->reseller?->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div x-show="memberType === 'partner'" x-cloak>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Partner</label>
                            <select name="membership_id" class="input w-full">
                                <option value="">Select a partner…</option>
                                @foreach($programPartnerMemberships ?? [] as $membership)
                                <option value="{{ $membership->id }}">{{ trim(($membership->partner?->first_name ?? '') . ' ' . ($membership->partner?->last_name ?? '')) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Action type</label>
                            <select name="action_type" class="input w-full">
                                @foreach(['accept_terms','review_contract','upload_document','complete_profile','resolve_application','acknowledge_program_ending'] as $type)
                                <option value="{{ $type }}">{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Due (optional)</label>
                            <input type="datetime-local" name="due_at" class="input w-full">
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 rounded-b-xl flex items-center justify-end gap-3">
                    <button type="submit" class="btn btn-primary" :disabled="submitting" x-text="submitting ? 'Creating…' : 'Create action item'"></button>
                </div>
            </form>
            @endcan

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                        <tr>
                            <th class="px-4 py-2">Member</th>
                            <th class="px-4 py-2">Action</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2">Due</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($actionItems ?? [] as $item)
                        <tr>
                            <td class="px-4 py-3">{{ $resolveMemberName($item) }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ ucfirst(str_replace('_', ' ', $item->action_type)) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium
                                    {{ $item->status === 'pending'   ? 'bg-blue-100 text-blue-700'  : '' }}
                                    {{ $item->status === 'completed' ? 'bg-green-100 text-green-700' : '' }}
                                    {{ $item->status === 'dismissed' ? 'bg-gray-100 text-gray-600'  : '' }}
                                    {{ $item->status === 'expired'   ? 'bg-red-100 text-red-700'    : '' }}">
                                    {{ ucfirst($item->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 {{ $item->isOverdue() ? 'text-red-600 font-medium' : 'text-gray-500' }}">
                                {{ $item->due_at?->format('M j, Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                @can('managePeople', $program)
                                @if($item->status === 'pending')
                                <form method="POST" action="{{ route('tenant.programs.action-items.status', [$tenant->id, $program->id, $item->id]) }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="status" value="completed">
                                    <button type="submit" class="btn btn-ghost btn-xs">Complete</button>
                                </form>
                                <form method="POST" action="{{ route('tenant.programs.action-items.status', [$tenant->id, $program->id, $item->id]) }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="status" value="dismissed">
                                    <button type="submit" class="btn btn-ghost btn-xs">Dismiss</button>
                                </form>
                                @endif
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">No action items yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $actionItems?->links() }}
        </div>

        {{-- ── Analytics tab ─────────────────────────────────────────────────── --}}
        <div x-show="activeTab === 'analytics'" x-cloak class="p-6 max-w-5xl mx-auto space-y-6">
            @php $a = $analytics ?? []; @endphp

            <div>
                <h2 class="text-base font-semibold text-heading mb-3">Deals</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-5">
                        <p class="text-xs text-gray-500">Total deals</p>
                        <p class="text-2xl font-bold text-heading mt-1">{{ $a['total_deals'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-5">
                        <p class="text-xs text-gray-500">Active deals</p>
                        <p class="text-2xl font-bold text-heading mt-1">{{ $a['active_deals'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-5">
                        <p class="text-xs text-gray-500">Closed won</p>
                        <p class="text-2xl font-bold text-heading mt-1">{{ $a['closed_won_deals'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-5">
                        <p class="text-xs text-gray-500">Conversion rate</p>
                        <p class="text-2xl font-bold text-heading mt-1">{{ isset($a['conversion_rate']) ? $a['conversion_rate'] . '%' : '—' }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-5">
                        <p class="text-xs text-gray-500">Expiring deals</p>
                        <p class="text-2xl font-bold text-heading mt-1">{{ $a['expiring_deals'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-5">
                        <p class="text-xs text-gray-500">Total deal value</p>
                        <p class="text-2xl font-bold text-heading mt-1">{{ number_format($a['total_deal_value'] ?? 0, 2) }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-5">
                        <p class="text-xs text-gray-500">Average deal value</p>
                        <p class="text-2xl font-bold text-heading mt-1">{{ number_format($a['average_deal_value'] ?? 0, 2) }}</p>
                    </div>
                </div>
            </div>

            <div>
                <h2 class="text-base font-semibold text-heading mb-3">Commission</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-5">
                        <p class="text-xs text-gray-500">Pending</p>
                        <p class="text-2xl font-bold text-heading mt-1">{{ number_format($a['pending_commission'] ?? 0, 2) }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-5">
                        <p class="text-xs text-gray-500">Locked</p>
                        <p class="text-2xl font-bold text-heading mt-1">{{ number_format($a['locked_commission'] ?? 0, 2) }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-5">
                        <p class="text-xs text-gray-500">Paid</p>
                        <p class="text-2xl font-bold text-heading mt-1">{{ number_format($a['paid_commission'] ?? 0, 2) }}</p>
                    </div>
                </div>
            </div>

            <div>
                <h2 class="text-base font-semibold text-heading mb-3">Members</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-5">
                        <p class="text-xs text-gray-500">Active referrers</p>
                        <p class="text-2xl font-bold text-heading mt-1">{{ $a['referrer_active'] ?? 0 }}<span class="text-sm text-gray-400 font-normal"> / {{ $a['referrer_total'] ?? 0 }}</span></p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-5">
                        <p class="text-xs text-gray-500">Active partners</p>
                        <p class="text-2xl font-bold text-heading mt-1">{{ $a['partner_active'] ?? 0 }}<span class="text-sm text-gray-400 font-normal"> / {{ $a['partner_total'] ?? 0 }}</span></p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Access tab ────────────────────────────────────────────────────── --}}
        <div x-show="activeTab === 'access'" x-cloak class="p-6 max-w-4xl mx-auto space-y-4">
            <div class="rounded-lg bg-blue-50 border border-blue-200 px-4 py-3 text-sm text-blue-800">
                Permissions are managed tenant-wide, not per program. This shows what your current team can do with <strong>any</strong> program based on their role.
                <a href="{{ route('tenant.users', $tenant->id) }}" class="underline font-medium">Manage team &amp; roles</a>.
            </div>

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                        <tr>
                            <th class="px-4 py-2">Member</th>
                            <th class="px-4 py-2">Role</th>
                            <th class="px-4 py-2">Can view programs</th>
                            <th class="px-4 py-2">Can manage programs</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($accessRows ?? [] as $row)
                        <tr>
                            <td class="px-4 py-3">
                                {{ $row['membership']->tenantUser?->display_name ?? '—' }}
                                <div class="text-xs text-gray-400">{{ $row['membership']->tenantUser?->email }}</div>
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $roleBadge = [
                                        'owner'   => 'bg-amber-100 text-amber-800',
                                        'admin'   => 'bg-violet-100 text-violet-700',
                                        'manager' => 'bg-sky-100 text-sky-700',
                                        'member'  => 'bg-green-100 text-green-700',
                                    ][$row['membership']->role] ?? 'bg-gray-100 text-gray-600';
                                @endphp
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $roleBadge }}">{{ ucfirst($row['membership']->role) }}</span>
                            </td>
                            <td class="px-4 py-3">
                                @if($row['can_view'])
                                <span class="text-green-600">&check; Yes</span>
                                @else
                                <span class="text-gray-400">&cross; No</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($row['can_manage'])
                                <span class="text-green-600">&check; Yes</span>
                                @else
                                <span class="text-gray-400">&cross; No</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">No active team members.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── Notifications tab ─────────────────────────────────────────────── --}}
        <div x-show="activeTab === 'notifications'" x-cloak class="p-6 max-w-4xl mx-auto space-y-4">
            <div class="rounded-lg bg-blue-50 border border-blue-200 px-4 py-3 text-sm text-blue-800">
                A read-only history of notifications sent to your team about this program. Use the bell icon to mark notifications as read.
            </div>

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                        <tr>
                            <th class="px-4 py-2">Notification</th>
                            <th class="px-4 py-2">Recipient</th>
                            <th class="px-4 py-2">Priority</th>
                            <th class="px-4 py-2">Sent</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($programNotifications ?? [] as $notification)
                        <tr>
                            <td class="px-4 py-3">
                                {{ $notification->display_title }}
                                <div class="text-xs text-gray-400">{{ $notification->message }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-500">
                                @if($notification->notifiable_type === 'tenant_admin')
                                    {{ ($notificationRecipientsById ?? [])[$notification->notifiable_id]->display_name ?? '—' }}
                                @else
                                    {{ ucfirst($notification->notifiable_type ?? '') }}
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium
                                    {{ $notification->priority_color === 'red'    ? 'bg-red-100 text-red-700'     : '' }}
                                    {{ $notification->priority_color === 'orange' ? 'bg-amber-100 text-amber-700' : '' }}
                                    {{ $notification->priority_color === 'blue'   ? 'bg-blue-100 text-blue-700'   : '' }}
                                    {{ $notification->priority_color === 'gray'   ? 'bg-gray-100 text-gray-600'   : '' }}">
                                    {{ ucfirst($notification->priority) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $notification->sent_at?->diffForHumans() ?? '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">No notifications yet for this program.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $programNotifications?->links() }}
        </div>

        {{-- ── Public Page tab ───────────────────────────────────────────────── --}}
        <div x-show="activeTab === 'public-page'" x-cloak class="p-6 max-w-3xl mx-auto space-y-6">

            @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
            @endif

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-6 space-y-4">
                <div>
                    <h2 class="text-base font-semibold text-heading">Live public page</h2>
                    @if($tenant->slug && $program->slug)
                    <div class="mt-2 flex items-center gap-2">
                        <input type="text" readonly
                               value="{{ route('public.programs.show', [$tenant->slug, $program->slug]) }}"
                               class="input w-full font-mono text-xs" onclick="this.select()">
                        <a href="{{ route('public.programs.show', [$tenant->slug, $program->slug]) }}" target="_blank" rel="noopener noreferrer"
                           class="btn btn-ghost btn-sm whitespace-nowrap">Open ↗</a>
                    </div>
                    @else
                    <p class="text-sm text-gray-400 mt-2">A live URL isn't available yet — both the tenant and program need a slug.</p>
                    @endif
                </div>

                <div class="pt-2 border-t border-gray-100">
                    <p class="text-xs text-gray-500">
                        Visibility: <span class="font-medium text-gray-700">{{ ucfirst($program->public_visibility) }}</span>
                        — change this on the
                        <a href="{{ route('tenant.programs.workspace', [$tenant->id, $program->id]) }}?tab=overview" class="underline">Overview tab</a>.
                    </p>
                </div>
            </div>

            <form method="POST"
                  action="{{ route('tenant.programs.update', [$tenant->id, $program->id]) }}"
                  x-data="{ submitting: false }" @submit="submitting = true"
                  class="rounded-xl border border-gray-200 bg-white shadow-sm divide-y divide-gray-100">
                @csrf @method('PATCH')

                <div class="p-6 space-y-3">
                    <h2 class="text-base font-semibold text-heading">Call-to-action text</h2>
                    <label for="public_cta_text" class="block text-xs text-gray-500">Shown on the public page instead of the default "Interested in joining? Contact {{ $tenant->name }} to learn more."</label>
                    <textarea id="public_cta_text" name="public_cta_text" rows="2" maxlength="160"
                              class="input w-full resize-none"
                              placeholder="e.g. Apply now — spots are limited!">{{ old('public_cta_text', $program->public_cta_text) }}</textarea>
                </div>

                <div class="px-6 py-4 bg-gray-50 rounded-b-xl flex items-center justify-end gap-3">
                    @can('update', $program)
                    <button type="submit" class="btn btn-primary" :disabled="submitting" x-text="submitting ? 'Saving…' : 'Save changes'"></button>
                    @endcan
                </div>
            </form>
        </div>

        {{-- ── Intake tab ────────────────────────────────────────────────────── --}}
        <div x-show="activeTab === 'intake'" x-cloak class="p-6 max-w-4xl mx-auto space-y-4">

            @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
            @endif

            @can('managePeople', $program)
            <div class="flex justify-end">
                <a href="{{ route('tenant.request-forms.create', $tenant->id) }}?program_id={{ $program->id }}"
                   class="btn btn-primary btn-sm">New intake form</a>
            </div>
            @endcan

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                        <tr>
                            <th class="px-4 py-2">Form</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2">Responses</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($intakeForms ?? [] as $form)
                        <tr>
                            <td class="px-4 py-3">{{ $form->title }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium
                                    {{ $form->status === 'published'   ? 'bg-green-100 text-green-700' : '' }}
                                    {{ $form->status === 'draft'       ? 'bg-gray-100 text-gray-600'   : '' }}
                                    {{ $form->status === 'unpublished' ? 'bg-amber-100 text-amber-700' : '' }}
                                    {{ $form->status === 'archived'    ? 'bg-red-100 text-red-700'     : '' }}">
                                    {{ ucfirst($form->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $form->submissions_count }}</td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <a href="{{ route('tenant.request-forms.submissions', [$tenant->id, $form->id]) }}" class="text-sm text-blue-600 hover:underline">View responses</a>
                                @can('managePeople', $program)
                                <a href="{{ route('tenant.request-forms.edit', [$tenant->id, $form->id]) }}" class="text-sm text-blue-600 hover:underline">Edit</a>
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">No intake forms yet for this program.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── Placeholder panels for remaining tabs ──────────────────────────── --}}
        @foreach(array_keys($tabs) as $tabKey)
            @if(!in_array($tabKey, ['overview', 'members', 'settings', 'offers', 'contracts', 'action-items', 'analytics', 'access', 'notifications', 'public-page', 'intake'], true))
            <div x-show="activeTab === '{{ $tabKey }}'" x-cloak class="p-6">
                <div class="max-w-2xl mx-auto rounded-xl border border-dashed border-gray-300 bg-gray-50 py-16 text-center">
                    <p class="text-sm text-gray-500">{{ $tabs[$tabKey]['label'] }} — coming in next phase</p>
                </div>
            </div>
            @endif
        @endforeach

    </div>
</div>
@endsection
