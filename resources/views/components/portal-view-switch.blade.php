@props(['current' => 'admin', 'tenantId' => null, 'tab' => false])
<form method="POST" action="{{ route('portal.switch-view') }}">
    @csrf
    <input type="hidden" name="view" value="{{ $current === 'admin' ? 'referrer' : 'admin' }}">
    @if($tenantId)<input type="hidden" name="tenant_id" value="{{ $tenantId }}">@endif
    <button type="submit" class="{{ $tab ? 'w-full text-left px-3 py-2 rounded-xl text-sm font-medium text-white hover:bg-white/10' : 'px-3 py-2 rounded-xl border border-purple-200 text-xs font-semibold text-purple-700 bg-purple-50 hover:bg-purple-100' }}"
            aria-label="Switch to {{ $current === 'admin' ? 'referrer' : 'company admin' }} view">
        @if($tab)
            {{ $current === 'admin' ? 'My Referrals' : 'My Programs' }}
        @else
            {{ $current === 'admin' ? 'Company Admin' : 'Referrer' }} <span aria-hidden="true">⇄</span>
        @endif
    </button>
</form>
