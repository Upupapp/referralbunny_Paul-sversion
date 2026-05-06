@extends('emails.layouts.base', ['headerLabel' => 'Export Approved', 'subject' => 'Your export request was approved'])

@section('content')
<div style="text-align:center;margin-bottom:24px">
    <img src="https://referralbunny.ai/images/mascots/r-bunny-rocket.webp"
         alt="" width="120" height="120"
         style="width:120px;height:120px;object-fit:contain;display:inline-block;background:transparent">
</div>

<p class="greeting">Hi {{ $requesterName }}! 👋</p>

<p class="text">
    Great news — your export request for <strong>{{ ucfirst(str_replace('_', ' ', $exportType)) }}</strong> data
    at <strong>{{ $tenantName }}</strong> has been approved.
</p>

<div class="highlight-box highlight-teal">
    <div class="highlight-title" style="color:#0D9488">What happens now?</div>
    <div class="highlight-text">
        Your export file is now being generated. This usually takes just a moment.
        You'll receive another email as soon as your file is ready to download.
    </div>
</div>

<p class="text">
    You can check the progress of your export at any time from your Export Dashboard.
</p>

<div class="cta-wrap">
    <a href="{{ $dashboardUrl }}" class="cta cta-teal">Go to Export Dashboard →</a>
</div>

<hr class="divider">
<p class="text" style="font-size:13px;color:#9CA3AF">
    This notification was sent by <strong>{{ $tenantName }}</strong> via ReferralBunny.ai.
</p>
@endsection
