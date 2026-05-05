@extends('emails.layouts.base', ['headerLabel' => 'New Referrer Joined'])
@section('content')
<p class="greeting">New referrer joined! 🤝</p>
<p class="text">Hi <strong>{{ $adminName }}</strong>, a new referrer has activated their account on <strong>{{ $tenantName }}</strong>.</p>
<div class="highlight-box highlight-teal">
    <div class="highlight-title" style="color:#0D9488">Referrer Details</div>
    <div class="highlight-text">
        <strong>Name:</strong> {{ $resellerName }}<br>
        <strong>Email:</strong> {{ $resellerEmail }}<br>
        <strong>Joined:</strong> {{ $joinDate }}
    </div>
</div>
<div class="cta-wrap">
    <a href="{{ $dashboardUrl }}" class="cta">View Referrers →</a>
</div>
@endsection
