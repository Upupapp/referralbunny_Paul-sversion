@extends('emails.layouts.base', ['headerLabel' => 'Invitation Accepted', 'subject' => "{$acceptedByName} accepted your invitation"])

@section('content')
<div style="text-align:center;margin-bottom:24px">
    <img src="https://referralbunny.ai/images/mascots/r-bunny-celebration.webp"
         alt="" width="120" height="120"
         style="width:120px;height:120px;object-fit:contain;display:inline-block">
</div>

<p class="greeting">Great news! 🎉</p>

<p class="text">
    <strong>{{ $acceptedByName }}</strong> (<a href="mailto:{{ $acceptedByEmail }}" style="color:#7B61FF">{{ $acceptedByEmail }}</a>)
    has accepted your invitation to join <strong>{{ $tenantName }}</strong> as a <strong>{{ $roleLabel }}</strong>.
</p>

<div class="highlight-box highlight-teal">
    <div class="highlight-title" style="color:#0D9488">They're now part of your team</div>
    <div class="highlight-text">
        <strong>{{ $acceptedByName }}</strong> has access to <strong>{{ $tenantName }}</strong>
        and can start collaborating right away.
    </div>
</div>

<div class="cta-wrap">
    <a href="{{ $usersUrl }}" class="cta cta-teal">View Your Team →</a>
</div>

<hr class="divider">
<p class="text" style="font-size:13px;color:#9CA3AF">
    You can manage team roles and permissions from your Users &amp; Roles page.
</p>
@endsection
