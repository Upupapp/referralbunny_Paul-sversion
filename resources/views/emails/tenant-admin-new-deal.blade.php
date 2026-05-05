@extends('emails.layouts.base', ['headerLabel' => 'New Deal Created'])
@section('content')
<p class="greeting">New deal created</p>
<p class="text">Hi <strong>{{ $adminName }}</strong>, a new deal has been submitted in <strong>{{ $tenantName }}</strong>.</p>
<div class="highlight-box highlight-purple">
    <div class="highlight-title" style="color:#7B61FF">Deal Details</div>
    <div class="highlight-text">
        <strong>Organization:</strong> {{ $dealName }}<br>
        <strong>Referrer:</strong> {{ $resellerName }}<br>
        <strong>Stage:</strong> {{ ucfirst(str_replace('_', ' ', $stage)) }}<br>
        <strong>Stage Deadline:</strong> {{ $daysLeft }} days
        @if($dealValue > 0)<br><strong>Deal Value:</strong> ₱{{ number_format($dealValue) }}@endif
    </div>
</div>
<div class="cta-wrap">
    <a href="{{ $dashboardUrl }}" class="cta">Review Deal →</a>
</div>
@endsection
