@extends('emails.layouts.base', ['headerLabel' => 'Invitation Cancelled', 'subject' => "Your invitation to {$tenantName} has been cancelled"])

@section('content')
<div style="text-align:center;margin-bottom:24px">
    <img src="https://referralbunny.ai/images/mascots/r-bunny-warning-error.webp"
         alt="" width="120" height="120"
         style="width:120px;height:120px;object-fit:contain;display:inline-block">
</div>

<p class="greeting">Invitation cancelled</p>

<p class="text">
    Your invitation to join <strong>{{ $tenantName }}</strong> as a <strong>{{ $roleLabel }}</strong>
    on ReferralBunny.ai has been cancelled by the workspace administrator.
</p>

<p class="text">
    If you believe this was a mistake, please reach out to the person who invited you
    to request a new invitation.
</p>

<hr class="divider">
<p class="text" style="font-size:13px;color:#9CA3AF">
    If you weren't expecting this email, you can safely ignore it.
</p>
@endsection
