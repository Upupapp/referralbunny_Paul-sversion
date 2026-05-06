@extends('emails.layouts.base', ['headerLabel' => 'Export Ready', 'subject' => 'Your export file is ready to download'])

@section('content')
<div style="text-align:center;margin-bottom:24px">
    <img src="https://referralbunny.ai/images/mascots/r-bunny-rocket.webp"
         alt="" width="120" height="120"
         style="width:120px;height:120px;object-fit:contain;display:inline-block;background:transparent">
</div>

<p class="greeting">Hi {{ $requesterName }}! 👋</p>

<p class="text">
    Your export file for <strong>{{ ucfirst(str_replace('_', ' ', $exportType)) }}</strong> data
    at <strong>{{ $tenantName }}</strong> is ready to download.
</p>

<div class="highlight-box highlight-orange">
    <div class="highlight-title" style="color:#D97706">Download Before It Expires</div>
    <div class="highlight-text">
        This file will expire on <strong>{{ $expiresAt }}</strong>.
        Download it before then — expired files cannot be recovered.
    </div>
</div>

<div class="cta-wrap">
    <a href="{{ $downloadUrl }}" class="cta cta-teal">Download Export →</a>
</div>

<p class="text" style="font-size:13px;color:#9CA3AF;text-align:center">
    Or copy this link into your browser:
</p>
<div class="link-box"><a href="{{ $downloadUrl }}">{{ $downloadUrl }}</a></div>

<p class="text" style="font-size:13px;color:#9CA3AF">
    Please do not share this download link with others. It is intended for your use only.
    The link will become inactive after the expiry date above.
</p>

<hr class="divider">
<p class="text" style="font-size:13px;color:#9CA3AF">
    This notification was sent by <strong>{{ $tenantName }}</strong> via ReferralBunny.ai.
</p>
@endsection
