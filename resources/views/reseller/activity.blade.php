@extends('layouts.reseller')
@section('title', 'Activity Log')
@section('nav') @include('reseller._nav') @endsection

@section('content')
<div class="max-w-2xl mx-auto space-y-4">

    {{-- Header --}}
    <div class="flex items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold" style="color:#1E1B4B">Activity Log</h1>
            <p class="text-sm text-gray-400 mt-0.5">Everything happening across your deals, commissions &amp; account.</p>
        </div>
        <span class="text-xs text-gray-400 mt-1.5 shrink-0">{{ number_format($total) }} {{ Str::plural('event', $total) }}</span>
    </div>

    {{-- Filter pills --}}
    @php
    $filters = [
        'all'        => 'All Activity',
        'deals'      => 'Deals',
        'commission' => 'Commission',
        'system'     => 'System',
    ];
    @endphp
    <div class="flex gap-2 flex-wrap">
        @foreach($filters as $key => $label)
        <a href="{{ route('reseller.activity', $tenant->id) }}?filter={{ $key }}"
           class="px-3 py-1.5 rounded-full text-xs font-medium transition-all
                  {{ $filter === $key
                       ? 'text-white shadow-sm'
                       : 'bg-white text-gray-500 border border-gray-200 hover:border-teal-300' }}"
           @if($filter === $key) style="background:#0D9488" @endif>
            {{ $label }}
        </a>
        @endforeach
    </div>

    {{-- Activity list --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

        @if($paged->isEmpty())
        <div class="flex flex-col items-center justify-center py-16 text-center px-6">
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center mb-3" style="background:#F0FDFA">
                <svg class="w-7 h-7" style="color:#0D9488" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
            <p class="text-sm font-semibold" style="color:#1E1B4B">No activity yet</p>
            <p class="text-xs text-gray-400 mt-1 max-w-xs">
                @if($filter !== 'all')
                    No {{ $filters[$filter] ?? $filter }} events found.
                    <a href="{{ route('reseller.activity', $tenant->id) }}" class="underline" style="color:#0D9488">View all activity</a>
                @else
                    As you submit deals and move them through stages, all activity will appear here.
                @endif
            </p>
        </div>

        @else
        <div class="divide-y divide-gray-50">
            @foreach($paged as $item)
            @php
                $iconConfig = match($item['icon_type']) {
                    'stage'      => ['bg' => '#CCFBF1', 'color' => '#0D9488',
                                     'path' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'],
                    'comment'    => ['bg' => '#EDE9FE', 'color' => '#7B61FF',
                                     'path' => 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z'],
                    'commission' => ['bg' => '#D1FAE5', 'color' => '#10B981',
                                     'path' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                    'extension'  => ['bg' => '#FEF3C7', 'color' => '#D97706',
                                     'path' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                    'status'     => ['bg' => '#DBEAFE', 'color' => '#3B82F6',
                                     'path' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
                    'system'     => ['bg' => '#F3F4F6', 'color' => '#9CA3AF',
                                     'path' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
                    default      => ['bg' => '#F0FDFA', 'color' => '#0D9488',
                                     'path' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
                };
            @endphp
            <div class="flex items-start gap-3.5 px-5 py-4 hover:bg-gray-50 transition-colors">

                {{-- Icon --}}
                <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 mt-0.5"
                     style="background:{{ $iconConfig['bg'] }}">
                    <svg class="w-4 h-4" fill="none" stroke="{{ $iconConfig['color'] }}" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $iconConfig['path'] }}"/>
                    </svg>
                </div>

                {{-- Content --}}
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold leading-snug" style="color:#1E1B4B">{{ $item['title'] }}</p>

                    @if($item['detail'])
                    <p class="text-xs text-gray-500 mt-0.5 leading-relaxed">{{ $item['detail'] }}</p>
                    @endif

                    @if($item['deal_name'])
                    <p class="text-[10px] text-gray-400 mt-1 flex items-center gap-1">
                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/>
                        </svg>
                        {{ $item['deal_name'] }}
                    </p>
                    @endif

                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-[10px] text-gray-400">{{ $item['time_ago'] }}</span>
                        <span class="w-1 h-1 rounded-full bg-gray-300 shrink-0"></span>
                        <span class="text-[10px] text-gray-400">{{ $item['category'] }}</span>
                    </div>
                </div>

                {{-- Deal link --}}
                @if($item['lead_id'])
                <a href="{{ route('reseller.deals', $tenant->id) }}#{{ $item['lead_id'] }}"
                   class="text-[10px] font-semibold shrink-0 mt-1 transition-colors hover:opacity-70"
                   style="color:#0D9488" title="View deal">
                    View →
                </a>
                @endif
            </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($pages > 1)
        <div class="flex items-center justify-between px-5 py-3 border-t border-gray-100 bg-gray-50">
            @if($page > 1)
            <a href="{{ route('reseller.activity', $tenant->id) }}?filter={{ $filter }}&page={{ $page - 1 }}"
               class="text-xs text-gray-500 hover:text-teal-600">← Previous</a>
            @else
            <span class="text-xs text-gray-300">← Previous</span>
            @endif

            <span class="text-xs text-gray-400">Page {{ $page }} of {{ $pages }}</span>

            @if($page < $pages)
            <a href="{{ route('reseller.activity', $tenant->id) }}?filter={{ $filter }}&page={{ $page + 1 }}"
               class="text-xs text-gray-500 hover:text-teal-600">Next →</a>
            @else
            <span class="text-xs text-gray-300">Next →</span>
            @endif
        </div>
        @endif

        @endif
    </div>

</div>
@endsection
