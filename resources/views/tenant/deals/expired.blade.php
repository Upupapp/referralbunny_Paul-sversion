@extends('layouts.app')
@section('title', 'Expired Deals')
@section('nav') @include('tenant._nav') @endsection

@section('content')
@php
$_badgeCounts = \Illuminate\Support\Facades\Cache::remember("subtab_badge_counts:{$tenant->id}", 60, fn() => [
    'expired'         => \App\Models\Lead::where('tenant_id', $tenant->id)->where('status','expired')->whereNull('deleted_at')->count(),
    'pending_archive' => \App\Models\DealApprovalRequest::where('tenant_id', $tenant->id)->where('type','deal_archive')->where('status','pending')->count(),
    'deleted_archived'=> \App\Models\Lead::withTrashed()->where('tenant_id', $tenant->id)->where(fn($q) => $q->where('status','archived')->orWhereNotNull('deleted_at'))->count(),
]);
$subtabCounts = array_merge($_badgeCounts, ['expired' => $metrics['total']]);
@endphp

<div class="space-y-5">

    {{-- Subtab navigation --}}
    @include('tenant.deals._subtabs')

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700 flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
    @endif

    {{-- Metrics row --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
        <div class="card p-4">
            <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total Expired</p>
            <p class="text-2xl font-bold text-red-600 mt-1">{{ number_format($metrics['total']) }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Expired This Week</p>
            <p class="text-2xl font-bold text-orange-500 mt-1">{{ number_format($metrics['this_week']) }}</p>
        </div>
        <div class="card p-4 col-span-2 sm:col-span-1">
            <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total Deal Value</p>
            <p class="text-2xl font-bold text-gray-700 mt-1">₱{{ number_format($metrics['total_value'], 0) }}</p>
        </div>
    </div>

    {{-- Filter bar --}}
    <div class="card space-y-3">
        <form method="GET" action="{{ route('tenant.deals.expired', $tenant->id) }}" class="flex flex-wrap gap-2 items-center">
            <div class="search-group flex-1 min-w-[180px]">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="q" value="{{ $search }}" placeholder="Search expired deals…" class="w-full">
            </div>
            <select name="stage" class="pill-select">
                <option value="">All Stages</option>
                @foreach(['introduction','presentation','contract_sent','signed','paid'] as $s)
                    <option value="{{ $s }}" @selected($stage === $s)>{{ ucwords(str_replace('_',' ',$s)) }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn-primary">Filter</button>
            @if($search || $stage)
                <a href="{{ route('tenant.deals.expired', $tenant->id) }}" class="btn-secondary">Clear</a>
            @endif
        </form>
    </div>

    {{-- Table --}}
    <div class="card overflow-hidden">
        @if($deals->isEmpty())
            <div class="flex flex-col items-center justify-center py-16 text-center px-4">
                <div class="w-16 h-16 rounded-2xl bg-red-50 flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <p class="text-gray-500 font-medium">No expired deals found</p>
                @if($search || $stage)
                    <p class="text-gray-400 text-sm mt-1">Try adjusting your filters.</p>
                @else
                    <p class="text-gray-400 text-sm mt-1">Deals that miss their stage deadline will appear here.</p>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/50">
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Deal</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden sm:table-cell">Stage</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden md:table-cell">Referrer</th>
                            <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden lg:table-cell">Deal Value</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden md:table-cell">Expired</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($deals as $deal)
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-4 py-3">
                                <a href="{{ route('tenant.deals.show', [$tenant->id, $deal->id]) }}"
                                   class="font-semibold text-[#1E1B4B] hover:text-[#7B61FF] transition-colors">
                                    {{ $deal->name }}
                                </a>
                                <p class="text-xs text-gray-400 mt-0.5 sm:hidden">
                                    {{ ucwords(str_replace('_',' ',$deal->stage)) }}
                                    @if($deal->reseller_name) · {{ $deal->reseller_name }} @endif
                                </p>
                            </td>
                            <td class="px-4 py-3 hidden sm:table-cell">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-medium bg-gray-100 text-gray-600">
                                    {{ ucwords(str_replace('_',' ',$deal->stage)) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 hidden md:table-cell">
                                {{ $deal->reseller_name ?: '—' }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-gray-700 hidden lg:table-cell">
                                ₱{{ number_format((float)$deal->deal_value, 0) }}
                            </td>
                            <td class="px-4 py-3 text-gray-400 text-xs hidden md:table-cell">
                                {{ $deal->updated_at?->diffForHumans() }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('tenant.deals.show', [$tenant->id, $deal->id]) }}"
                                   class="text-xs text-[#7B61FF] hover:underline font-medium">View</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($deals->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">
                    {{ $deals->links() }}
                </div>
            @endif
        @endif
    </div>

</div>
@endsection
