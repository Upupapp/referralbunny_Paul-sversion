@extends('emails.layouts.base', ['headerLabel' => 'Export Request', 'subject' => 'Your export request was submitted'])

@section('content')
<div style="text-align:center;margin-bottom:24px">
    <img src="https://referralbunny.ai/images/mascots/r-bunny-rocket.webp"
         alt="" width="120" height="120"
         style="width:120px;height:120px;object-fit:contain;display:inline-block;background:transparent">
</div>

<p class="greeting">Hi {{ $requesterName }}! 👋</p>

<p class="text">
    Your export request for <strong>{{ ucfirst(str_replace('_', ' ', $exportType)) }}</strong> data has been submitted successfully.
    A Tenant Admin at <strong>{{ $tenantName }}</strong> will review your request and you'll be notified once a decision has been made.
</p>

<div class="highlight-box highlight-teal">
    <div class="highlight-title" style="color:#0D9488">What happens next?</div>
    <div class="highlight-text">
        ✓ Your request is now in the approval queue<br>
        ✓ A Tenant Admin will review it shortly<br>
        ✓ You'll receive an email when it's approved or rejected<br>
        ✓ Once approved, your file will be generated automatically
    </div>
</div>

<p class="text">
    Need to check the status of your request? Head over to your Export Dashboard at any time.
</p>

<hr class="divider">
<p class="text" style="font-size:13px;color:#9CA3AF">
    This notification was sent by <strong>{{ $tenantName }}</strong> via ReferralBunny.ai.
    If you did not submit this request, please contact your Tenant Admin immediately.
</p>
@endsection
