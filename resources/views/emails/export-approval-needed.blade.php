@extends('emails.layouts.base', ['headerLabel' => 'Action Required', 'subject' => 'Export request needs your approval'])

@section('content')
<div style="text-align:center;margin-bottom:24px">
    <img src="https://referralbunny.ai/images/mascots/r-bunny-portal.webp"
         alt="" width="120" height="120"
         style="width:120px;height:120px;object-fit:contain;display:inline-block;background:transparent">
</div>

<p class="greeting">Hi {{ $adminName }}! 👋</p>

<p class="text">
    A new export request is waiting for your approval at <strong>{{ $tenantName }}</strong>.
</p>

<div class="highlight-box highlight-purple">
    <div class="highlight-title" style="color:#7B61FF">Export Request Details</div>
    <div class="highlight-text">
        <strong>Requested by:</strong> {{ $requesterName }} ({{ ucfirst($requesterRole) }})<br>
        <strong>Export type:</strong> {{ ucfirst(str_replace('_', ' ', $exportType)) }} data
    </div>
</div>

@if($reason)
<div class="highlight-box highlight-teal">
    <div class="highlight-title" style="color:#0D9488">Reason provided</div>
    <div class="highlight-text">{{ $reason }}</div>
</div>
@endif

<p class="text">
    Please review this request and either approve or reject it from your admin panel.
    The requester will be notified of your decision automatically.
</p>

<div class="cta-wrap">
    <a href="{{ $reviewUrl }}" class="cta cta-teal">Review Export Request →</a>
</div>

<p class="text" style="font-size:13px;color:#9CA3AF;text-align:center">
    Or copy this link into your browser:
</p>
<div class="link-box"><a href="{{ $reviewUrl }}">{{ $reviewUrl }}</a></div>

<hr class="divider">
<p class="text" style="font-size:13px;color:#9CA3AF">
    You are receiving this email because you are an admin of <strong>{{ $tenantName }}</strong> on ReferralBunny.ai.
    Only you can approve or reject this request.
</p>
@endsection
