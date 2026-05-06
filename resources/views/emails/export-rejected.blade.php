@extends('emails.layouts.base', ['headerLabel' => 'Export Request Update', 'subject' => 'Your export request was not approved'])

@section('content')
<div style="text-align:center;margin-bottom:24px">
    <img src="https://referralbunny.ai/images/mascots/r-bunny-portal.webp"
         alt="" width="120" height="120"
         style="width:120px;height:120px;object-fit:contain;display:inline-block;background:transparent">
</div>

<p class="greeting">Hi {{ $requesterName }}! 👋</p>

<p class="text">
    Your export request for <strong>{{ ucfirst(str_replace('_', ' ', $exportType)) }}</strong> data
    at <strong>{{ $tenantName }}</strong> was not approved at this time.
</p>

@if($rejectionReason)
<div class="highlight-box highlight-orange">
    <div class="highlight-title" style="color:#D97706">Reason from Admin</div>
    <div class="highlight-text">{{ $rejectionReason }}</div>
</div>
@endif

<p class="text">
    If your data needs have changed or if you believe this was in error, you can submit a new export request
    with additional context. Contact your Tenant Admin for more information.
</p>

<hr class="divider">
<p class="text" style="font-size:13px;color:#9CA3AF">
    This notification was sent by <strong>{{ $tenantName }}</strong> via ReferralBunny.ai.
    If you have questions about this decision, please reach out to your Tenant Admin directly.
</p>
@endsection
