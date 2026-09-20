@php
    $programMessageUnread = config('programs.enabled') ? \Illuminate\Support\Facades\DB::table('program_messages')
        ->where('tenant_id', $tenant->id)->where('reseller_id', auth('reseller')->id())
        ->where('sender_type','admin')->whereNull('read_at')->count() : 0;
    $referrerTabs = [
        ['Dashboard', 'reseller.dashboard', 'M3 12l9-9 9 9M5 10v10h14V10M9 20v-7h6v7'],
        ['Referral Programs', 'reseller.programs.index', 'M4 7h16v14H4zM3 7h18M8 7V3h8v4'],
        ['My Referrals', 'reseller.deals', 'M8 4h8v4H8zM6 6H4v15h16V6h-2M8 12h8M8 16h5'],
        ['Rewards', 'reseller.commission', 'M12 3v18M17 7H9a3 3 0 000 6h6a3 3 0 010 6H6'],
        ['Messages', 'reseller.messages', 'M4 4h16v13H9l-5 4zM8 8h8M8 12h5'],
        ['Activity', 'reseller.activity', 'M3 12h4l3-8 4 16 3-8h4'],
        ['Profile', 'reseller.profile', 'M16 7a4 4 0 11-8 0 4 4 0 018 0M4 21a8 8 0 0116 0'],
    ];
@endphp
@foreach($referrerTabs as [$label, $tabRoute, $icon])
    @continue($tabRoute === 'reseller.programs.index' && !config('programs.enabled'))
    @php $tabActive = request()->routeIs($tabRoute, $tabRoute === 'reseller.programs.index' ? 'reseller.programs.*' : $tabRoute.'.*'); @endphp
    <a href="{{ route($tabRoute, $tenant->id) }}" class="rs-sidebar-link {{ $tabActive ? 'active' : '' }}" @if($tabActive) aria-current="page" @endif>
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="{{ $icon }}"/></svg>
        {{ $label }}
        @if($tabRoute === 'reseller.messages' && $programMessageUnread)
            <span style="margin-left:auto;background:#8b19df;color:white;border-radius:12px;padding:2px 7px;font-size:11px" aria-label="{{ $programMessageUnread }} unread messages">{{ min($programMessageUnread,99) }}</span>
        @endif
    </a>
@endforeach
