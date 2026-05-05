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
    As a referrer, you'll be able to submit deals, track your pipeline, and earn commissions.
</p>

@if($dealName)
<div class="highlight-box highlight-purple">
    <div class="highlight-title" style="color:#7B61FF">Deal Already Assigned to You</div>
    <div class="highlight-text"><strong>{{ $dealName }}</strong> is waiting for you in your dashboard.</div>
</div>
@endif

<div class="highlight-box highlight-teal">
    <div class="highlight-title" style="color:#0D9488">What you get access to</div>
    <div class="highlight-text">
        ✓ Browse and claim available municipalities<br>
        ✓ Track your deals through the pipeline<br>
        ✓ Monitor your commission status<br>
        ✓ Get notified when action is needed
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
