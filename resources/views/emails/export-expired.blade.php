@extends('emails.layouts.base', ['headerLabel' => 'Export Expired', 'subject' => 'Your export file has expired'])

@section('content')
<div style="text-align:center;margin-bottom:24px">
    <img src="https://referralbunny.ai/images/mascots/r-bunny-portal.webp"
         alt="" width="120" height="120"
         style="width:120px;height:120px;object-fit:contain;display:inline-block;background:transparent">
</div>

<p class="greeting">Hi {{ $requesterName }}! 👋</p>

<p class="text">
    Your export file for <strong>{{ ucfirst(str_replace('_', ' ', $exportType)) }}</strong> data
    at <strong>{{ $tenantName }}</strong> is no longer available.
    The download window has closed and the file has been removed from our servers.
</p>

<div class="highlight-box highlight-purple">
    <div class="highlight-title" style="color:#7B61FF">Need this data again?</div>
    <div class="highlight-text">
        You can submit a new export request at any time from your Export Dashboard.
        A Tenant Admin will review and approve it so a fresh file can be generated for you.
    </div>
</div>

<p class="text">
    If you have questions about export access or data retention policies, please contact your Tenant Admin.
</p>

<hr class="divider">
<p class="text" style="font-size:13px;color:#9CA3AF">
    This notification was sent by <strong>{{ $tenantName }}</strong> via ReferralBunny.ai.
</p>
@endsection
