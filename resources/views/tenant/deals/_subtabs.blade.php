@php
$subtabs = [
    [
        'route'  => 'tenant.deals',
        'params' => [$tenant->id],
        'label'  => 'Active Deals',
        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>',
        'match'  => ['tenant.deals'],
        'color'  => 'purple',
    ],
    [
        'route'  => 'tenant.deals.expired',
        'params' => [$tenant->id],
        'label'  => 'Expired',
        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'match'  => ['tenant.deals.expired'],
        'color'  => 'red',
        'badge'  => $subtabCounts['expired'] ?? null,
    ],
    [
        'route'  => 'tenant.deals.archive-requests',
        'params' => [$tenant->id],
        'label'  => 'Archive Requests',
        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 4H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-2m-4-1v8m0 0l-3-3m3 3l3-3"/>',
        'match'  => ['tenant.deals.archive-requests', 'tenant.deals.archive-requests.show'],
        'color'  => 'orange',
        'badge'  => $subtabCounts['pending_archive'] ?? null,
    ],
    [
        'route'  => 'tenant.deals.deleted-archived',
        'params' => [$tenant->id],
        'label'  => 'Deleted / Archived',
        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1 12a2 2 0 002 2h8a2 2 0 002-2l1-12"/>',
        'match'  => ['tenant.deals.deleted-archived'],
        'color'  => 'gray',
        'badge'  => $subtabCounts['deleted_archived'] ?? null,
    ],
];
@endphp

<div class="flex items-center gap-1.5 flex-wrap">
    @foreach($subtabs as $tab)
        @php
            $isActive = request()->routeIs($tab['match']);
            $colorMap = [
                'purple' => ['active' => 'bg-[#7B61FF] text-white shadow-sm', 'inactive' => 'bg-white text-gray-500 hover:bg-gray-50 border border-gray-200'],
                'red'    => ['active' => 'bg-red-500 text-white shadow-sm',    'inactive' => 'bg-white text-gray-500 hover:bg-gray-50 border border-gray-200'],
                'orange' => ['active' => 'bg-orange-500 text-white shadow-sm', 'inactive' => 'bg-white text-gray-500 hover:bg-gray-50 border border-gray-200'],
                'gray'   => ['active' => 'bg-gray-600 text-white shadow-sm',   'inactive' => 'bg-white text-gray-500 hover:bg-gray-50 border border-gray-200'],
            ];
            $cls = $colorMap[$tab['color']] ?? $colorMap['purple'];
        @endphp
        <a href="{{ route($tab['route'], $tab['params']) }}"
           class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-sm font-semibold transition-all {{ $isActive ? $cls['active'] : $cls['inactive'] }}">
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $tab['icon'] !!}</svg>
            {{ $tab['label'] }}
            @if(!empty($tab['badge']) && $tab['badge'] > 0)
                <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold rounded-full
                             {{ $isActive ? 'bg-white/25 text-white' : 'bg-red-100 text-red-600' }}">
                    {{ $tab['badge'] }}
                </span>
            @endif
        </a>
    @endforeach
</div>
