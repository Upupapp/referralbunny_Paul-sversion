@extends('emails.layouts.base', ['headerLabel' => 'Action Required', 'subject' => 'Your deal needs attention'])

@section('content')
<p class="greeting">Action Required ⚠️</p>

<p class="text">
    Hi <strong>{{ $resellerName }}</strong>, one of your deals needs immediate attention.
</p>

<div class="highlight-box {{ $daysLeft <= 1 ? 'highlight-red' : 'highlight-orange' }}">
    <div class="highlight-title" style="color:{{ $daysLeft <= 1 ? '#DC2626' : '#D97706' }}">
        {{ $daysLeft <= 1 ? 'EXPIRES TODAY' : $daysLeft . ' DAY' . ($daysLeft > 1 ? 'S' : '') . ' LEFT' }}
    </div>
    <div class="highlight-text">
        <strong>{{ $dealName }}</strong><br>
        Current stage: <strong>{{ ucfirst(str_replace('_', ' ', $stage)) }}</strong><br>
        {{ $daysLeft <= 1 ? 'This deal expires today and may be reassigned if not advanced.' : "You have $daysLeft day" . ($daysLeft > 1 ? 's' : '') . " to advance this deal to the next stage." }}
    </div>
</div>

<p class="text">
    If this deal is not advanced to the next stage before the deadline, it will become
    available for reassignment to another referrer.
</p>

<div class="cta-wrap">
    <a href="{{ $loginUrl }}" class="cta">View My Deals →</a>
</div>

<hr class="divider">
<p class="text" style="font-size:13px;color:#9CA3AF">
    This is an automated reminder from <strong>{{ $tenantName }}</strong>'s referral program.
    Log in at <a href="{{ $loginUrl }}" style="color:#7B61FF">referralbunny.ai</a> to take action.
</p>
@endsection
