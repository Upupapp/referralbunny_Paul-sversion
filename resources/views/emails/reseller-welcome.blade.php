@extends('emails.layouts.base', ['headerLabel' => 'Welcome to the Team'])
@section('content')
<p class="greeting">Welcome, {{ $resellerName }}! 🎉</p>
<p class="text">Your referrer account on <strong>{{ $tenantName }}</strong> is now active. You're ready to start claiming deals, tracking your pipeline, and earning commissions.</p>
<div class="highlight-box highlight-teal">
    <div class="highlight-title" style="color:#0D9488">What you can do now</div>
    <div class="highlight-text">
        ✓ Browse available municipalities and claim deals<br>
        ✓ Track your deals through every stage<br>
        ✓ Monitor your commission status in real time<br>
        ✓ Get notified when your deals need attention
    </div>
</div>
<div class="cta-wrap">
    <a href="{{ $dashboardUrl }}" class="cta cta-teal">Go to My Dashboard →</a>
</div>
<hr class="divider">
<p class="text" style="font-size:13px;color:#9CA3AF">Questions? Contact your program administrator at <strong>{{ $tenantName }}</strong>.</p>
@endsection
