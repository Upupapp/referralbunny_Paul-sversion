@extends('emails.layouts.base', ['headerLabel' => 'Account Activated', 'subject' => "{$acceptedUserName} has joined {$tenantName}"])

@section('content')
<div style="text-align:center;margin-bottom:24px">
    <img src="https://referralbunny.ai/images/mascots/r-bunny-celebration.webp"
         alt="" width="120" height="120"
         style="width:120px;height:120px;object-fit:contain;display:inline-block">
</div>

<p class="greeting">Great news, {{ $inviterName }}!</p>

<p class="text">
    <strong>{{ $acceptedUserName }}</strong>
    (<a href="mailto:{{ $acceptedUserEmail }}" style="color:#7B61FF">{{ $acceptedUserEmail }}</a>)
    has accepted your invitation and activated their <strong>{{ $roleLabel }}</strong> account
    on <strong>{{ $tenantName }}</strong>.
</p>

<div class="highlight-box highlight-teal">
    <div class="highlight-title" style="color:#0D9488">They're now active</div>
    <div class="highlight-text">
        <strong>{{ $acceptedUserName }}</strong> can now log in and start working
        with you on <strong>{{ $tenantName }}</strong>.
    </div>
</div>

<div class="cta-wrap">
    <a href="{{ $actionUrl }}" class="cta cta-purple">{{ $actionLabel }} →</a>
</div>

<hr class="divider">
<p class="text" style="font-size:13px;color:#9CA3AF">
    You're receiving this because you invited {{ $acceptedUserName }} to {{ $tenantName }}.
</p>
@endsection
