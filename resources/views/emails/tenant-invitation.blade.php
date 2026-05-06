@extends('emails.layouts.base', ['headerLabel' => 'Team Invitation', 'subject' => "You've been invited to join {$tenantName}"])

@section('content')
<div style="text-align:center;margin-bottom:24px">
    <img src="https://referralbunny.ai/images/mascots/r-bunny-waving.webp"
         alt="" width="120" height="120"
         style="width:120px;height:120px;object-fit:contain;display:inline-block">
</div>

<p class="greeting">Hi there! 👋</p>

<p class="text">
    <strong>{{ $inviterName }}</strong> has invited you to join <strong>{{ $tenantName }}</strong> as a
    <strong>{{ $roleLabel }}</strong> on ReferralBunny.ai.
</p>

<div class="highlight-box highlight-purple">
    <div class="highlight-title" style="color:#7B61FF">Your Role: {{ $roleLabel }}</div>
    <div class="highlight-text">
        @if($roleLabel === 'Admin')
            Full access to the workspace — manage deals, contacts, users, and settings.
        @elseif($roleLabel === 'Manager')
            Manage day-to-day operations including deals, contacts, and team tasks.
        @elseif($roleLabel === 'Member')
            Collaborate on deals and contacts assigned to you.
        @else
            Read-only access to the workspace to stay informed on progress.
        @endif
    </div>
</div>

<p class="text">Click the button below to accept your invitation and get started:</p>

<div class="cta-wrap">
    <a href="{{ $acceptUrl }}" class="cta">Accept Invitation →</a>
</div>

<p class="text" style="font-size:13px;color:#9CA3AF;text-align:center">
    Or copy this link into your browser:
</p>
<div class="link-box"><a href="{{ $acceptUrl }}">{{ $acceptUrl }}</a></div>

<hr class="divider">
<p class="text" style="font-size:13px;color:#9CA3AF">
    This invitation expires on <strong>{{ $expiresAt }}</strong>.
    If you weren't expecting this email, you can safely ignore it.
</p>
@endsection
