@extends('layouts.app')
@section('title', 'Tenants')

@section('nav')
    @include('platform._nav')
@endsection

@section('topbar-actions')
    <a href="{{ route('platform.tenants.create') }}" class="btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        <span class="hidden sm:inline">New Tenant</span>
    </a>
@endsection

@section('content')
<div class="space-y-5">

    {{-- Active filter banner --}}
    @if(request('health_level'))
    @php
        $healthLabels = ['healthy' => 'Healthy', 'needs_attention' => 'Needs Attention', 'at_risk' => 'At Risk'];
        $healthColors = ['healthy' => 'bg-emerald-50 border-emerald-200 text-emerald-800', 'needs_attention' => 'bg-orange-50 border-orange-200 text-orange-800', 'at_risk' => 'bg-red-50 border-red-200 text-red-800'];
    @endphp
    <div class="flex items-center gap-3 px-4 py-3 {{ $healthColors[request('health_level')] ?? 'bg-gray-50 border-gray-200 text-gray-700' }} border rounded-2xl text-sm">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/></svg>
        Filtered by health: <strong>{{ $healthLabels[request('health_level')] ?? request('health_level') }}</strong>
        <a href="{{ route('platform.tenants') }}" class="ml-auto text-xs underline opacity-70 hover:opacity-100">Clear filter</a>
    </div>
    @endif

    {{-- Filters --}}
    <div class="card space-y-3">
        <form method="GET" class="space-y-3">
            <div class="flex items-center gap-3">
                <div class="search-group flex-1">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search tenants…">
                    @if(request('search'))
                    <a href="{{ route('platform.tenants', array_merge(request()->except('search', 'page'))) }}"
                       class="text-gray-400 hover:text-gray-600 transition-colors shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </a>
                    @endif
                </div>
                <button type="submit" class="btn-primary shrink-0">Search</button>
            </div>
            <div class="filter-bar">
                <label class="filter-pill {{ request('status') ? 'active' : '' }}">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <select name="status" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="active"   {{ request('status') === 'active'   ? 'selected' : '' }}>Active</option>
                        <option value="trial"    {{ request('status') === 'trial'    ? 'selected' : '' }}>Trial</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </label>
                <label class="filter-pill {{ request('health_level') ? 'active' : '' }}">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                    <select name="health_level" onchange="this.form.submit()">
                        <option value="">All Health</option>
                        <option value="healthy"         {{ request('health_level') === 'healthy'         ? 'selected' : '' }}>Healthy (80+)</option>
                        <option value="needs_attention" {{ request('health_level') === 'needs_attention' ? 'selected' : '' }}>Needs Attention</option>
                        <option value="at_risk"         {{ request('health_level') === 'at_risk'         ? 'selected' : '' }}>At Risk</option>
                    </select>
                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </label>
                <label class="filter-pill {{ request('industry') ? 'active' : '' }}">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    <select name="industry" onchange="this.form.submit()">
                        <option value="">All Industries</option>
                        @foreach($industries as $ind)
                            <option value="{{ $ind }}" {{ request('industry') === $ind ? 'selected' : '' }}>{{ $ind }}</option>
                        @endforeach
                    </select>
                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </label>
                @if(request()->hasAny(['search','status','industry','health_level']))
                <a href="{{ route('platform.tenants') }}"
                   class="filter-pill !border-red-200 !text-red-500 hover:!bg-red-50 no-underline">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Clear
                </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Table --}}
    <div class="card p-0 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
            <p class="text-sm font-semibold text-[#1E1B4B]">
                {{ number_format($tenants->total()) }} {{ \Illuminate\Support\Str::plural('tenant', $tenants->total()) }}
                @if(request()->hasAny(['search','status','industry','health_level']))
                    <span class="text-gray-400 font-normal text-xs ml-1">— filtered</span>
                @endif
            </p>
            @if($tenants->total() > 0)
                <p class="text-xs text-gray-400">
                    {{ $tenants->firstItem() }}–{{ $tenants->lastItem() }} of {{ number_format($tenants->total()) }}
                </p>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead><tr class="table-head">
                    <th>Tenant</th>
                    <th>Industry</th>
                    <th>Admin</th>
                    <th>
                        <span class="inline-flex items-center gap-1">
                            Health
                            <x-info-tip text="Platform health score (0–100). Healthy = 80+, Needs Attention = 50–79, At Risk = below 50." position="bottom" />
                        </span>
                    </th>
                    <th>Status</th>
                    <th>Created</th>
                    <th></th>
                </tr></thead>
                <tbody>
                @forelse($tenants as $tenant)
                    @php $metric = $tenant->metric; @endphp
                    <tr class="table-row">
                        <td>
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white text-sm font-bold shrink-0"
                                     style="background-color: {{ $tenant->accent_color ?? '#7B61FF' }}">
                                    {{ strtoupper(substr($tenant->name, 0, 2)) }}
                                </div>
                                <div>
                                    <p class="font-medium text-[#1E1B4B]">{{ $tenant->name }}</p>
                                    <p class="text-xs text-gray-400">{{ $tenant->program_name }}</p>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($tenant->industry)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-lg bg-gray-100 text-gray-600 text-xs font-medium">{{ $tenant->industry }}</span>
                            @else
                                <span class="text-gray-300 text-sm">—</span>
                            @endif
                        </td>
                        <td>
                            <p class="text-sm text-gray-700">{{ $tenant->admin_name ?: '—' }}</p>
                            <p class="text-xs text-gray-400">{{ $tenant->admin_email }}</p>
                        </td>
                        <td>
                            @if($metric)
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-bold tabular-nums {{ $metric->health_score >= 80 ? 'text-emerald-600' : ($metric->health_score >= 50 ? 'text-orange-500' : 'text-red-500') }}">
                                        {{ $metric->health_score }}
                                    </span>
                                    <div class="w-16 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full {{ $metric->health_score >= 80 ? 'bg-emerald-500' : ($metric->health_score >= 50 ? 'bg-orange-400' : 'bg-red-500') }}"
                                             style="width: {{ $metric->health_score }}%"></div>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-400 mt-0.5 capitalize">{{ str_replace('_', ' ', $metric->health_level) }}</p>
                            @else
                                <span class="text-xs text-gray-300">—</span>
                            @endif
                        </td>
                        <td>
                            <span @class([
                                'badge',
                                'badge-green'  => $tenant->status === 'active',
                                'badge-blue'   => $tenant->status === 'trial',
                                'badge-gray'   => $tenant->status === 'inactive',
                            ])>{{ ucfirst($tenant->status) }}</span>
                        </td>
                        <td class="text-gray-400 text-sm tabular-nums">{{ $tenant->created_at->format('M d, Y') }}</td>
                        <td>
                            <div class="flex items-center gap-1.5 justify-end">
                                <a href="{{ route('platform.tenants.show', $tenant->id) }}"
                                   class="px-2.5 py-1.5 rounded-lg text-xs font-medium text-gray-600 hover:bg-gray-100 transition-colors">Details</a>
                                <a href="{{ route('tenant.dashboard', $tenant->id) }}"
                                   class="px-2.5 py-1.5 rounded-lg text-xs font-medium bg-purple-50 text-purple-700 hover:bg-purple-100 transition-colors">Access →</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-16 text-center">
                            <div class="text-gray-300 mb-2">
                                <svg class="w-10 h-10 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            </div>
                            <p class="text-gray-400 text-sm">No tenants found</p>
                            @if(request()->hasAny(['search','status','industry','health_level']))
                                <a href="{{ route('platform.tenants') }}" class="mt-2 inline-block text-xs text-purple-600 hover:text-purple-700">Clear filters</a>
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($tenants->hasPages())
            <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between gap-4">
                <p class="text-xs text-gray-400">
                    Page {{ $tenants->currentPage() }} of {{ $tenants->lastPage() }}
                </p>
                <div class="flex items-center gap-1">
                    @if($tenants->onFirstPage())
                        <span class="px-3 py-1.5 text-xs text-gray-300 rounded-lg">← Prev</span>
                    @else
                        <a href="{{ $tenants->previousPageUrl() }}" class="px-3 py-1.5 text-xs text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">← Prev</a>
                    @endif
                    @foreach($tenants->getUrlRange(max(1,$tenants->currentPage()-2), min($tenants->lastPage(),$tenants->currentPage()+2)) as $page => $url)
                        @if($page == $tenants->currentPage())
                            <span class="px-3 py-1.5 text-xs font-semibold bg-[#7B61FF] text-white rounded-lg">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="px-3 py-1.5 text-xs text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">{{ $page }}</a>
                        @endif
                    @endforeach
                    @if($tenants->hasMorePages())
                        <a href="{{ $tenants->nextPageUrl() }}" class="px-3 py-1.5 text-xs text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">Next →</a>
                    @else
                        <span class="px-3 py-1.5 text-xs text-gray-300 rounded-lg">Next →</span>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
