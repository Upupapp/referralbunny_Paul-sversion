@extends('emails.layouts.base', ['headerLabel' => 'Referrer Invitation', 'subject' => "You've been invited as a referrer"])

@section('content')
<div style="text-align:center;margin-bottom:24px">
    <img src="https://referralbunny.ai/images/mascots/r-bunny-portal.webp"
         alt="" width="120" height="120"
         style="width:120px;height:120px;object-fit:contain;display:inline-block;background:transparent">
</div>
<p class="greeting">Hi {{ $resellerName }}! 👋</p>

<p class="text">
    You've been invited to join <strong>{{ $tenantName }}</strong>'s referral program on ReferralBunny.ai.
    As a Referrer, you'll be able to submit deals, track your pipeline, and earn commissions.
</p>

@php $totalDeals = $dealCount ?? 0; $names = $dealNames ?? []; $singleName = $dealName ?? null; @endphp

@if($totalDeals > 1)
{{-- Summarized multi-deal invitation --}}
<div class="highlight-box highlight-purple">
    <div class="highlight-title" style="color:#7B61FF">{{ $totalDeals }} Deal(s) Already Assigned to You</div>
    <div class="highlight-text">
        After activating your account, you can view all assigned deals in your Referrer dashboard.
        @if(count($names) > 0)
        <br><br>
        @foreach($names as $i => $name)
        <strong>{{ $name }}</strong>@if(!$loop->last)<br>@endif
        @endforeach
        @if($totalDeals > count($names))
        <br>…and {{ $totalDeals - count($names) }} more
        @endif
        @endif
    </div>
</div>
@elseif($singleName)
{{-- Single deal assignment --}}
<div class="highlight-box highlight-purple">
    <div class="highlight-title" style="color:#7B61FF">Deal Already Assigned to You</div>
    <div class="highlight-text"><strong>{{ $singleName }}</strong> is waiting for you in your dashboard.</div>
</div>
@endif

<div class="highlight-box highlight-teal">
    <div class="highlight-title" style="color:#0D9488">What you get access to</div>
    <div class="highlight-text">
        ✓ Submit and track deals through the pipeline<br>
        ✓ Monitor your commission and earnings status<br>
        ✓ Get notified when action is needed<br>
        ✓ Access your referrer dashboard anytime
    </div>
</div>

<p class="text">Click the button below to set up your password and activate your account:</p>

<div class="cta-wrap">
    <a href="{{ $setupUrl }}" class="cta cta-teal">Activate My Account →</a>
</div>

<p class="text" style="font-size:13px;color:#9CA3AF;text-align:center">
    Or copy this link into your browser:
</p>
<div class="link-box"><a href="{{ $setupUrl }}">{{ $setupUrl }}</a></div>

<hr class="divider">
<p class="text" style="font-size:13px;color:#9CA3AF">
    This invitation was sent by <strong>{{ $tenantName }}</strong>. If you weren't expecting this, you can safely ignore this email.
</p>
@endsection
