@extends('emails.layouts.base', ['headerLabel' => 'Deal Reassignment'])
@section('content')
<p class="greeting">You've been removed from a deal.</p>
<p class="text">Hi <strong>{{ $resellerName }}</strong>, we're letting you know that you have been removed from a deal on <strong>{{ $tenantName }}</strong>.</p>

<div class="highlight-box highlight-red">
    <div class="highlight-title" style="color:#EF4444">Deal Reassigned</div>
    <div class="highlight-text">
        <strong>Deal:</strong> {{ $dealName }}<br>
        <strong>Reassigned by:</strong> {{ $reassignedByName }}
        @if($newResellerName)<br><strong>New Referrer:</strong> {{ $newResellerName }}@endif
    </div>
</div>

<p class="text">
    You no longer have access to this deal. If you believe this was done in error,
    please contact your admin. Your commission history for this deal is preserved in your account.
</p>

<div class="cta-wrap">
    <a href="{{ url('/reseller/' . $tenantId . '/deals') }}" class="cta cta-blue">View My Deals →</a>
</div>

<hr class="divider">
<p class="text" style="font-size:13px;color:#6B7280;">
    You are receiving this email because you were previously assigned to a deal on {{ $tenantName }}.
    Log in to <strong>ReferralBunny.ai</strong> to manage your remaining deals and commissions.
</p>
@endsection
