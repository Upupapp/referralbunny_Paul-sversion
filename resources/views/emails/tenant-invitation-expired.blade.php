@extends('emails.layouts.base', ['headerLabel' => 'Invitation Expired', 'subject' => "Your invitation to {$tenantName} has expired"])

@section('content')
<div style="text-align:center;margin-bottom:24px">
    <img src="https://referralbunny.ai/images/mascots/r-bunny-sleeping.webp"
         alt="" width="120" height="120"
         style="width:120px;height:120px;object-fit:contain;display:inline-block">
</div>

<p class="greeting">Your invitation has expired</p>

<p class="text">
    Your invitation to join <strong>{{ $tenantName }}</strong> as a <strong>{{ $roleLabel }}</strong>
    on ReferralBunny.ai has expired.
</p>

<div class="highlight-box highlight-orange">
    <div class="highlight-title" style="color:#D97706">What happened?</div>
    <div class="highlight-text">
        Invitations are valid for 7 days. This one was not accepted before its expiry date.
        If you still want to join, reach out to <strong>{{ $inviterName }}</strong> and ask for a new invitation.
    </div>
</div>

<hr class="divider">
<p class="text" style="font-size:13px;color:#9CA3AF">
    If you weren't expecting this email or don't know who <strong>{{ $inviterName }}</strong> is,
    you can safely ignore it.
</p>
@endsection
