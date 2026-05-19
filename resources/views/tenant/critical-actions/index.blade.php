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
        'deal'       => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2',
        'import'     => 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12',
        'task'       => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
        'messaging'  => 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z',
        'user'       => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
        'export'     => 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4',
        'billing'    => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
        'commission' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        'activity'   => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
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
            <div class="flex items-center gap-3">
                <div class="text-sm text-gray-400">
                    <span class="font-semibold text-[#1E1B4B]">{{ $result['total'] }}</span> item{{ $result['total'] !== 1 ? 's' : '' }}
                    @if($result['total'] > 0)
                        <span class="mx-1">·</span> Page {{ $result['page'] }} of {{ $result['total_pages'] }}
                    @endif
                </div>
                @if($result['total'] > 0)
                <button x-data="{ done: false, loading: false }"
                        @click="if(done||loading) return; loading=true;
                            fetch('{{ route('tenant.critical-actions.mark-all-read', $tenant->id) }}', {
                                method:'POST', credentials:'same-origin',
                                headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}
                            }).then(r => r.ok ? r : Promise.reject(r)).then(()=>{
                                done=true; loading=false;
                                window.dispatchEvent(new CustomEvent('critical-badge:cleared'));
                                // Hide all New badges on the page without a full reload
                                document.querySelectorAll('.ca-new-badge').forEach(el => el.remove());
                                document.querySelectorAll('.ca-new-row').forEach(el => {
                                    el.classList.remove('bg-amber-50/60','border-l-2','border-l-amber-400');
                                });
                            }).catch(()=>{
                                loading=false;
                                window.dispatchEvent(new CustomEvent('show-toast', {detail:{type:'error',message:'Could not mark as seen. Please try refreshing.'}}));
                            })"
                        class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-xl border border-gray-200 text-gray-500 hover:border-violet-300 hover:text-violet-700 transition-colors disabled:opacity-60"
                        :disabled="loading || done">
                    <svg x-show="!done && !loading" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <svg x-show="loading" x-cloak class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    <span x-show="!done" x-text="loading ? 'Marking…' : 'Mark all as seen'"></span>
                    <span x-show="done" x-cloak class="text-emerald-600">All seen ✓</span>
                </button>
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

        {{-- Severity pills --}}
        <div class="flex flex-wrap gap-1.5">
            @foreach($severities as $val => $label)
                @php
                    $isActive = ($filters['severity'] ?? '') === $val;
                    $sevColors = match($val) {
                        'urgent' => ['active' => 'bg-red-600 text-white border-red-600',    'inactive' => 'border-gray-200 text-gray-500 hover:border-red-300 hover:text-red-600'],
                        'high'   => ['active' => 'bg-orange-500 text-white border-orange-500', 'inactive' => 'border-gray-200 text-gray-500 hover:border-orange-300 hover:text-orange-600'],
                        'medium' => ['active' => 'bg-amber-500 text-white border-amber-500',   'inactive' => 'border-gray-200 text-gray-500 hover:border-amber-300 hover:text-amber-600'],
                        'low'    => ['active' => 'bg-blue-500 text-white border-blue-500',     'inactive' => 'border-gray-200 text-gray-500 hover:border-blue-300 hover:text-blue-600'],
                        'info'   => ['active' => 'bg-gray-500 text-white border-gray-500',     'inactive' => 'border-gray-200 text-gray-500 hover:border-gray-400'],
                        default  => ['active' => 'bg-[#7B61FF] text-white border-[#7B61FF]',   'inactive' => 'border-gray-200 text-gray-500 hover:border-purple-300 hover:text-[#7B61FF]'],
                    };
                    $url = request()->url() . '?' . http_build_query(array_merge(request()->except(['severity','page']), $val ? ['severity' => $val] : []));
                @endphp
                <a href="{{ $url }}"
                   class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium border transition-colors no-underline {{ $isActive ? $sevColors['active'] : $sevColors['inactive'] }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        {{-- Category pills --}}
        <div class="flex flex-wrap gap-1.5">
            @foreach($categories as $val => $label)
                @php
                    $isActive = ($filters['category'] ?? '') === $val;
                    $url = request()->url() . '?' . http_build_query(array_merge(request()->except(['category','page']), $val ? ['category' => $val] : []));
                @endphp
                <a href="{{ $url }}"
                   class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium border transition-colors no-underline {{ $isActive ? 'bg-[#7B61FF] text-white border-[#7B61FF]' : 'border-gray-200 text-gray-500 hover:border-purple-300 hover:text-[#7B61FF]' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        {{-- Date range + clear --}}
        <div class="flex flex-wrap gap-1.5 items-center">
            <label class="filter-pill" title="From date">
                <svg class="w-3.5 h-3.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span style="font-size:10px;color:#9ca3af;margin-right:2px">From</span>
                <input type="date" name="since" value="{{ $filters['since'] ? (is_string($filters['since']) ? $filters['since'] : $filters['since']->format('Y-m-d')) : '' }}"
                       onchange="this.form.submit()" placeholder="From date">
            </label>

            <label class="filter-pill" title="To date">
                <svg class="w-3.5 h-3.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span style="font-size:10px;color:#9ca3af;margin-right:2px">To</span>
                <input type="date" name="until" value="{{ isset($filters['until']) && $filters['until'] ? (is_string($filters['until']) ? $filters['until'] : $filters['until']->format('Y-m-d')) : '' }}"
                       onchange="this.form.submit()" placeholder="To date">
            </label>

            @if(array_filter([$filters['search'], $filters['severity'], $filters['category'], $filters['since'], $filters['until'] ?? null]))
                <a href="{{ route('tenant.critical-actions', $tenant->id) }}"
                   class="filter-pill !border-red-200 !text-red-500 hover:!bg-red-50 no-underline">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Clear all
                </a>
            @endif
        </div>
    </form>

    {{-- Results --}}
    <div class="card p-0 overflow-hidden">

        {{-- Empty state --}}
        @if(count($result['items']) === 0)
            <div class="flex flex-col items-center justify-center py-16 text-center px-4">
                <img src="/images/mascots/r-bunny-sleeping.webp" alt="R Bunny relaxing" class="w-20 h-20 object-contain mb-4">
                @if(array_filter([$filters['search'], $filters['severity'], $filters['category'], $filters['since'], $filters['until'] ?? null]))
                    <p class="text-sm font-semibold text-gray-600">No results match your filters</p>
                    <p class="text-xs text-gray-400 mt-1">Try adjusting your search or clearing the active filters.</p>
                @elseif(in_array($actingRole, ['owner', 'admin', 'manager', 'super_admin']))
                    <p class="text-sm font-semibold text-gray-600">All clear. No tenant-wide critical actions.</p>
                    <p class="text-xs text-gray-400 mt-1">R Bunny says your workspace is calm. Check back anytime.</p>
                @else
                    <p class="text-sm font-semibold text-gray-600">You're all caught up.</p>
                    <p class="text-xs text-gray-400 mt-1">Nothing requires your attention right now. Nice work!</p>
                @endif
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
                            <th style="width:140px">
                                @php
                                    $currentSort = $filters['sort'] ?? 'recency_desc';
                                    $nextSort    = $currentSort === 'recency_desc' ? 'recency_asc' : 'recency_desc';
                                @endphp
                                <a href="{{ request()->url() }}?{{ http_build_query(array_merge(request()->except(['sort','page']), ['sort' => $nextSort])) }}"
                                   class="inline-flex items-center gap-1 hover:text-[#7B61FF] transition-colors"
                                   title="{{ $currentSort === 'recency_desc' ? 'Showing newest first — click for oldest first' : 'Showing oldest first — click for newest first' }}">
                                    When
                                    @if($currentSort === 'recency_desc')
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                    @else
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
                                    @endif
                                </a>
                            </th>
                            <th style="width:80px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($result['items'] as $action)
                            @php
                                $sev    = $severityConfig[$action['severity']] ?? $severityConfig['info'];
                                $icon   = $categoryIcons[$action['category']] ?? $categoryIcons['activity'];
                                // An item is "new" only if the user HAS visited before AND it
                                // occurred after their last visit. Null lastSeenAt = never visited
                                // → nothing is highlighted as "new" (no baseline to compare).
                                $isNew  = $lastSeenAt && isset($action['occurred_at']) && \Carbon\Carbon::parse($action['occurred_at'])->isAfter($lastSeenAt);
                            @endphp
                            <tr class="table-row {{ $isNew ? 'bg-amber-50/60 border-l-2 border-l-amber-400 ca-new-row' : '' }}">
                                <td>
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold {{ $sev['bg'] }} {{ $sev['text'] }}">
                                            <span class="w-1.5 h-1.5 rounded-full {{ $sev['dot'] }}"></span>
                                            {{ $sev['label'] }}
                                        </span>
                                        @if($isNew)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-amber-400 text-white uppercase tracking-wide ca-new-badge">New</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="flex items-start gap-2.5">
                                        <div class="w-7 h-7 rounded-lg {{ $isNew ? 'bg-amber-100' : 'bg-[#EDE9FE]' }} flex items-center justify-center shrink-0 mt-0.5">
                                            <svg class="w-3.5 h-3.5 {{ $isNew ? 'text-amber-600' : 'text-[#7B61FF]' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                    <div class="flex items-center gap-2 justify-end">
                                        @if($action['action_url'])
                                            <a href="{{ $action['action_url'] }}"
                                               class="px-2.5 py-1.5 rounded-lg text-xs font-medium bg-purple-50 text-purple-700 hover:bg-purple-100 transition-colors whitespace-nowrap">
                                                {{ $action['action_label'] ?? 'Open' }} →
                                            </a>
                                        @endif
                                        @if($action['dismissible'] ?? false)
                                            <button x-data="{ dismissing: false, dismissed: false }"
                                                    x-show="!dismissed" x-cloak
                                                    @click.prevent="if(dismissing||dismissed) return; dismissing=true;
                                                        fetch('{{ route('tenant.critical-actions.dismiss', $tenant->id) }}', {
                                                            method:'POST', credentials:'same-origin',
                                                            headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,'Content-Type':'application/json','Accept':'application/json'},
                                                            body: JSON.stringify({fingerprint:'{{ $action['fingerprint'] ?? '' }}', action_type:'{{ $action['type'] ?? '' }}'})
                                                        }).then(r=>r.json()).then(d=>{
                                                            if(d.success){ dismissed=true; $el.closest('tr').style.opacity='0'; setTimeout(()=>$el.closest('tr').remove(),300); }
                                                            else { dismissing=false; }
                                                        }).catch(()=>{ dismissing=false; })"
                                                    :disabled="dismissing"
                                                    title="Dismiss this item"
                                                    class="w-6 h-6 rounded-md flex items-center justify-center text-gray-300 hover:text-gray-500 hover:bg-gray-100 transition-colors disabled:opacity-40">
                                                <svg x-show="!dismissing" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                <svg x-show="dismissing" x-cloak class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile cards --}}
            <div class="md:hidden divide-y divide-gray-50">
                @foreach($result['items'] as $action)
                    @php
                        $sev    = $severityConfig[$action['severity']] ?? $severityConfig['info'];
                        $isNew  = $lastSeenAt && isset($action['occurred_at']) && \Carbon\Carbon::parse($action['occurred_at'])->isAfter($lastSeenAt);
                    @endphp
                    <div class="p-4 space-y-2 ca-mobile-card {{ $isNew ? 'bg-amber-50/60 border-l-2 border-l-amber-400 ca-new-row' : '' }}">
                        <div class="flex items-center justify-between gap-2 flex-wrap">
                            <div class="flex items-center gap-1.5">
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold {{ $sev['bg'] }} {{ $sev['text'] }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $sev['dot'] }}"></span>
                                    {{ $sev['label'] }}
                                </span>
                                @if($isNew)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-amber-400 text-white uppercase tracking-wide ca-new-badge">New</span>
                                @endif
                            </div>
                            <span class="text-[10px] text-gray-400">{{ $action['occurred_ago'] }}</span>
                        </div>
                        <p class="text-sm font-medium text-[#1E1B4B]">{{ $action['summary'] }}</p>
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-xs text-gray-500">{{ $action['actor_name'] }} · {{ $action['actor_role'] }}</p>
                            <div class="flex items-center gap-2 shrink-0">
                                @if($action['action_url'])
                                    <a href="{{ $action['action_url'] }}"
                                       class="text-xs font-semibold text-[#7B61FF] hover:text-purple-800">{{ $action['action_label'] ?? 'Open' }} →</a>
                                @endif
                                @if($action['dismissible'] ?? false)
                                    <button x-data="{ dismissing: false }"
                                            @click.prevent="if(dismissing) return; dismissing=true;
                                                fetch('{{ route('tenant.critical-actions.dismiss', $tenant->id) }}', {
                                                    method:'POST', credentials:'same-origin',
                                                    headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,'Content-Type':'application/json','Accept':'application/json'},
                                                    body: JSON.stringify({fingerprint:'{{ $action['fingerprint'] ?? '' }}', action_type:'{{ $action['type'] ?? '' }}'})
                                                }).then(r=>r.json()).then(d=>{
                                                    if(d.success){ $el.closest('.ca-mobile-card').style.opacity='0'; setTimeout(()=>$el.closest('.ca-mobile-card').remove(),300); }
                                                    else { dismissing=false; }
                                                }).catch(()=>{ dismissing=false; })"
                                            :disabled="dismissing"
                                            title="Dismiss"
                                            class="w-6 h-6 rounded-md flex items-center justify-center text-gray-300 hover:text-gray-500 hover:bg-gray-100 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                @endif
                            </div>
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
