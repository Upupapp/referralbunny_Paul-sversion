@extends('emails.layouts.base', ['headerLabel' => 'Invitation Follow-Up', 'subject' => "Pending invitation: {$inviteeEmail} hasn't responded yet"])

@section('content')
<div style="text-align:center;margin-bottom:24px">
    <img src="https://referralbunny.ai/images/mascots/r-bunny-thinking.webp"
         alt="" width="120" height="120"
         style="width:120px;height:120px;object-fit:contain;display:inline-block">
</div>

<p class="greeting">Hi {{ $inviterName }},</p>

<p class="text">
    Just a heads up — <strong>{{ $inviteeEmail }}</strong> hasn't accepted your invitation to join
    <strong>{{ $tenantName }}</strong> as a <strong>{{ $roleLabel }}</strong> yet.
</p>

<div class="highlight-box highlight-orange">
    <div class="highlight-title" style="color:#D97706">Invitation Status</div>
    <div class="highlight-text">
        <strong>Invited:</strong> {{ $inviteeEmail }}<br>
        <strong>Role:</strong> {{ $roleLabel }}<br>
        <strong>Expires:</strong> {{ $expiresAt }}
    </div>
</div>

<p class="text">
    You can resend the invitation or revoke it from your Users &amp; Roles page.
    If the invitation expires, you can always send a new one.
</p>

<div class="cta-wrap">
    <a href="{{ $usersUrl }}" class="cta">View Users &amp; Roles →</a>
</div>

<hr class="divider">
<p class="text" style="font-size:13px;color:#9CA3AF">
    This is an automated reminder. You can manage all pending invitations from your workspace.
</p>
@endsection
