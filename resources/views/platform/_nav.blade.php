@php
    $links = [
        ['route' => 'platform.dashboard', 'label' => 'Dashboard',  'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        ['route' => 'platform.tenants',   'label' => 'Tenants',    'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5'],
        ['route' => 'platform.messaging', 'label' => 'Messaging',  'icon' => 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z'],
        ['route' => 'platform.templates', 'label' => 'Templates',  'icon' => 'M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z'],
        ['route' => 'platform.billing',   'label' => 'Billing',    'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
        ['route' => 'platform.import',    'label' => 'Import Center','icon'=> 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12'],
    ];
    $tenants = \App\Models\Tenant::orderBy('name')->limit(5)->get();
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
    @foreach($tenants as $t)
        <a href="{{ route('tenant.dashboard', $t->id) }}" class="sidebar-link text-xs">
            <span class="w-5 h-5 rounded-lg flex items-center justify-center text-white text-xs font-bold shrink-0"
                  style="background-color: {{ $t->accent_color ?? '#7B61FF' }}">
                {{ strtoupper(substr($t->name, 0, 1)) }}
            </span>
            {{ Str::limit($t->name, 18) }}
        </a>
    @endforeach
</div>
