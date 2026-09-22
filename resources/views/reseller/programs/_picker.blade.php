@php
    $referrerContext = app(\App\Services\Programs\ReferrerProgramContext::class)->resolve($tenant->id, request());
    $switchRoute = request()->routeIs('reseller.deals','reseller.commission','reseller.messages','reseller.activity') ? request()->route()->getName() : 'reseller.dashboard';
@endphp
<form method="GET" action="{{ route($switchRoute, $tenant->id) }}">
    <label for="referrer-program-picker" class="sr-only">Selected referral program</label>
    @if(isset($subscriptionDashboard) && $subscriptionDashboard)
    <input type="hidden" name="days" value="{{ $subscriptionDashboard['days'] }}">
    @endif
    <select id="referrer-program-picker" name="program_id" onchange="this.form.submit()" @unless((isset($subscriptionDashboard) && $subscriptionDashboard) || isset($referralAccounts) || isset($rewardsPage) || isset($messagingPage) || ($compactProfile ?? false)) style="max-width:210px;border:1px solid #d9d1ed;border-radius:10px;background:#f8f3ff;color:#7522ac;font-size:12px;padding:8px 28px 8px 10px" @endunless>
        @forelse($referrerContext['programs'] as $choice)
        <option value="{{ $choice->id }}" @selected($referrerContext['program']?->id === $choice->id)>{{ $choice->name }}</option>
        @empty<option value="">No enrolled programs</option>@endforelse
    </select>
    <noscript><button type="submit">Switch program</button></noscript>
</form>
