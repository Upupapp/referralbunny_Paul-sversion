@extends('emails.layouts.base', ['headerLabel' => 'Deal Status Update'])
@section('content')
<p class="greeting">A deal update requires your attention.</p>
<p class="text">Hi <strong>{{ $resellerName }}</strong>, we want to let you know that one of your deals on <strong>{{ $tenantName }}</strong> has been marked as declined.</p>

<div class="highlight-box highlight-red">
    <div class="highlight-title" style="color:#EF4444">Deal Declined</div>
    <div class="highlight-text">
        <strong>Deal:</strong> {{ $dealName }}<br>
        <strong>Stage at time of decline:</strong> {{ ucfirst(str_replace('_', ' ', $stage)) }}<br>
        <strong>Updated by:</strong> {{ $declinedByName }}
        @if($dealValue > 0)<br><strong>Deal Value:</strong> ₱{{ number_format($dealValue) }}@endif
    </div>
</div>

<p class="text">
    If you believe this was done in error or have additional information that may change the outcome,
    please contact your admin directly. You can still view the deal history and your commission record.
</p>

<div class="cta-wrap">
    <a href="{{ $dashboardUrl }}" class="cta cta-blue">View My Deals →</a>
</div>

<hr class="divider">
<p class="text" style="font-size:13px;color:#6B7280;">
    You are receiving this email because you are assigned to this deal on {{ $tenantName }}.
    Log in to <strong>ReferralBunny.ai</strong> to manage your deals and commissions.
</p>
@endsection
