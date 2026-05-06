@extends('layouts.app')
@section('title', 'Critical Actions')
@section('nav') @include('tenant._nav') @endsection

@section('content')
@php
    $severityConfig = [
        'urgent' => ['bg' => 'bg-red-100',    'text' => 'text-red-700',    'dot' => 'bg-red-500',    'label' => 'Urgent'],
        'high'   => ['bg' => 'bg-orange-100', 'text' => 'text-orange-700', 'dot' => 'bg-orange-500', 'label' => 'High'],
        'medium' => ['bg' => 'bg-amber-100',  'text' => 'text-amber-700',  'dot' => 'bg-amber-400',  'label' => 'Medium'],
        'low'    => ['bg' => 'bg-blue-100',   'text' => 'text-blue-700',   'dot' => 'bg-blue-400',   'label' => 'Low'],
        'info'   => ['bg' => 'bg-gray-100',   'text' => 'text-gray-600',   'dot' => 'bg-gray-400',   'label' => 'Info'],
    ];
    $categoryIcons = [
        'deal'      => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2',
        'import'    => 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12',
        'messaging' => 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z',
        'user'      => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
        'activity'  => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
    ];
@endphp
<div class="space-y-5">

    {{-- Page header --}}
    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-[#EDE9FE] flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-[#7B61FF]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-[#1E1B4B] font-bold text-lg">Critical Actions</h2>
                    <p class="text-gray-400 text-xs mt-0.5">Important activity, warnings, and follow-up items for this workspace</p>
                </div>
            </div>
            <div class="text-sm text-gray-400">
                <span class="font-semibold text-[#1E1B4B]">{{ $result['total'] }}</span> item{{ $result['total'] !== 1 ? 's' : '' }}
                @if($result['total'] > 0)
                    <span class="mx-1">·</span> Page {{ $result['page'] }} of {{ $result['total_pages'] }}
                @endif
            </div>
        </div>
    </div>

    {{-- Search + Filters --}}
    <form method="GET" action="{{ route('tenant.critical-actions', $tenant->id) }}" class="card space-y-3">
        <div class="flex flex-col sm:flex-row gap-3">
            {{-- Search --}}
            <div class="search-group flex-1">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                       placeholder="Search by action, actor, or related record…"
                       autocomplete="off">
                @if($filters['search'])
                    <a href="{{ route('tenant.critical-actions', $tenant->id) }}" class="text-gray-400 hover:text-gray-600 shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </a>
                @endif
            </div>
            <button type="submit" class="btn-primary shrink-0">Search</button>
        </div>

        <div class="filter-bar">
            <label class="filter-pill" :class="''" >
                <svg class="w-3.5 h-3.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/></svg>
                <select name="severity" onchange="this.form.submit()">
                    @foreach($severities as $val => $label)
                        <option value="{{ $val }}" {{ ($filters['severity'] ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>

            <label class="filter-pill">
                <svg class="w-3.5 h-3.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/></svg>
                <select name="category" onchange="this.form.submit()">
                    @foreach($categories as $val => $label)
                        <option value="{{ $val }}" {{ ($filters['category'] ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>

            <label class="filter-pill">
                <svg class="w-3.5 h-3.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <input type="date" name="since" value="{{ $filters['since'] ? (is_string($filters['since']) ? $filters['since'] : $filters['since']->format('Y-m-d')) : '' }}"
                       onchange="this.form.submit()" placeholder="From date">
            </label>

            @if(array_filter([$filters['search'], $filters['severity'], $filters['category'], $filters['since']]))
                <a href="{{ route('tenant.critical-actions', $tenant->id) }}"
                   class="filter-pill !border-red-200 !text-red-500 hover:!bg-red-50 no-underline">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Clear filters
                </a>
            @endif
        </div>
    </form>

    {{-- Results --}}
    <div class="card p-0 overflow-hidden">

        {{-- Empty state --}}
        @if(count($result['items']) === 0)
            <div class="flex flex-col items-center justify-center py-16 text-center px-4">
                <img src="/images/mascots/r-bunny-sleeping.webp" alt="" class="w-14 h-14 object-contain mb-3 opacity-50">
                <p class="text-sm font-semibold text-gray-500">Nothing critical right now</p>
                <p class="text-xs text-gray-400 mt-1">R Bunny says your workspace is calm. Check back later.</p>
            </div>

        @else
            {{-- Desktop table --}}
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="table-head">
                            <th style="width:90px">Severity</th>
                            <th>Action</th>
                            <th>Actor</th>
                            <th style="width:140px">When</th>
                            <th style="width:80px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($result['items'] as $action)
                            @php
                                $sev = $severityConfig[$action['severity']] ?? $severityConfig['info'];
                                $icon = $categoryIcons[$action['category']] ?? $categoryIcons['activity'];
                            @endphp
                            <tr class="table-row">
                                <td>
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold {{ $sev['bg'] }} {{ $sev['text'] }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $sev['dot'] }}"></span>
                                        {{ $sev['label'] }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex items-start gap-2.5">
                                        <div class="w-7 h-7 rounded-lg bg-[#EDE9FE] flex items-center justify-center shrink-0 mt-0.5">
                                            <svg class="w-3.5 h-3.5 text-[#7B61FF]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"/>
                                            </svg>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-[#1E1B4B] leading-snug">{{ $action['summary'] }}</p>
                                            @if($action['related_label'])
                                                <p class="text-xs text-gray-400 mt-0.5 truncate">{{ ucfirst($action['related_type']) }}: {{ $action['related_label'] }}</p>
                                            @endif
                                            @if($action['action_needed'])
                                                <span class="inline-flex items-center gap-1 text-[9px] font-bold text-amber-600 uppercase tracking-wide mt-0.5">
                                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01"/></svg>
                                                    Action needed
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="min-w-0">
                                        <p class="text-sm text-[#1E1B4B] font-medium truncate">{{ $action['actor_name'] }}</p>
                                        <p class="text-xs text-gray-400">{{ $action['actor_role'] }}</p>
                                    </div>
                                </td>
                                <td>
                                    <p class="text-xs text-gray-600">{{ $action['occurred_ago'] }}</p>
                                    <p class="text-[10px] text-gray-400 mt-0.5">{{ $action['occurred_fmt'] ?? '' }}</p>
                                </td>
                                <td @click.stop>
                                    @if($action['action_url'])
                                        <a href="{{ $action['action_url'] }}"
                                           class="px-2.5 py-1.5 rounded-lg text-xs font-medium bg-purple-50 text-purple-700 hover:bg-purple-100 transition-colors whitespace-nowrap">
                                            Open →
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile cards --}}
            <div class="md:hidden divide-y divide-gray-50">
                @foreach($result['items'] as $action)
                    @php $sev = $severityConfig[$action['severity']] ?? $severityConfig['info']; @endphp
                    <div class="p-4 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold {{ $sev['bg'] }} {{ $sev['text'] }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $sev['dot'] }}"></span>
                                {{ $sev['label'] }}
                            </span>
                            <span class="text-[10px] text-gray-400">{{ $action['occurred_ago'] }}</span>
                        </div>
                        <p class="text-sm font-medium text-[#1E1B4B]">{{ $action['summary'] }}</p>
                        <div class="flex items-center justify-between">
                            <p class="text-xs text-gray-500">{{ $action['actor_name'] }} · {{ $action['actor_role'] }}</p>
                            @if($action['action_url'])
                                <a href="{{ $action['action_url'] }}"
                                   class="text-xs font-semibold text-[#7B61FF] hover:text-purple-800">Open →</a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Pagination --}}
    @if($result['total_pages'] > 1)
    <div class="flex items-center justify-between">
        <p class="text-xs text-gray-400">
            Showing {{ (($result['page'] - 1) * $result['per_page']) + 1 }}–{{ min($result['page'] * $result['per_page'], $result['total']) }} of {{ $result['total'] }} items
        </p>
        <div class="flex items-center gap-1.5">
            @if($result['page'] > 1)
                <a href="{{ route('tenant.critical-actions', $tenant->id) }}?{{ http_build_query(array_merge(request()->query(), ['page' => $result['page'] - 1])) }}"
                   class="px-3 py-1.5 rounded-xl border border-gray-200 text-xs font-medium text-gray-600 hover:bg-gray-50 transition-colors">← Prev</a>
            @endif
            @if($result['page'] < $result['total_pages'])
                <a href="{{ route('tenant.critical-actions', $tenant->id) }}?{{ http_build_query(array_merge(request()->query(), ['page' => $result['page'] + 1])) }}"
                   class="px-3 py-1.5 rounded-xl border border-gray-200 text-xs font-medium text-gray-600 hover:bg-gray-50 transition-colors">Next →</a>
            @endif
        </div>
    </div>
    @endif

</div>
@endsection
