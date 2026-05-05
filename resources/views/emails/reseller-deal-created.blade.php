@extends('emails.layouts.base', ['headerLabel' => 'Deal Created'])
@section('content')
<p class="greeting">Deal created! 💪</p>
<p class="text">Hi <strong>{{ $resellerName }}</strong>, you've successfully created a new deal on <strong>{{ $tenantName }}</strong>.</p>
<div class="highlight-box highlight-teal">
    <div class="highlight-title" style="color:#0D9488">Deal Details</div>
    <div class="highlight-text">
        <strong>Organization:</strong> {{ $dealName }}<br>
        <strong>Stage:</strong> {{ ucfirst(str_replace('_', ' ', $stage)) }}<br>
        <strong>Deadline:</strong> {{ $daysLeft }} days to advance to the next stage
        @if($dealValue > 0)<br><strong>Value:</strong> ₱{{ number_format($dealValue) }}@endif
    </div>
</div>
<p class="text">Keep this deal moving. You have <strong>{{ $daysLeft }} day{{ $daysLeft !== 1 ? 's' : '' }}</strong> to advance to the next stage before it becomes re-claimable.</p>
<div class="cta-wrap">
    <a href="{{ $dashboardUrl }}" class="cta cta-teal">View My Deals →</a>
</div>
@endsection
