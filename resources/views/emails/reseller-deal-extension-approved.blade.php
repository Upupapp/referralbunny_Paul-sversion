@extends('emails.layouts.base', ['headerLabel' => 'Extension Approved'])
@section('content')
<p class="greeting">Your extension request was approved!</p>
<p class="text">Hi <strong>{{ $resellerName }}</strong>, great news — your deadline extension request has been approved by the admin.</p>

<div class="highlight-box highlight-teal">
    <div class="highlight-title" style="color:#0D9488">Extension Details</div>
    <div class="highlight-text">
        <strong>Deal:</strong> {{ $dealName }}<br>
        <strong>Days Added:</strong> {{ $approvedDays }} day{{ $approvedDays !== 1 ? 's' : '' }}<br>
        <strong>New Deadline:</strong> {{ $newDaysLeft }} day{{ $newDaysLeft !== 1 ? 's' : '' }} remaining
        @if($adminNote)<br><strong>Admin Note:</strong> {{ $adminNote }}@endif
    </div>
</div>

<p class="text">
    Use this extra time wisely. Make sure your next follow-up is scheduled and your deal notes are up to date.
    Keep pushing — every day counts!
</p>

<div class="cta-wrap">
    <a href="{{ $dealUrl }}" class="cta cta-teal">View Deal →</a>
</div>

<hr class="divider">
<p class="text" style="font-size:13px;color:#6B7280;">
    You are receiving this email because you requested an extension for this deal on {{ $tenantName }}.
    Log in to <strong>ReferralBunny.ai</strong> to manage your deals and commissions.
</p>
@endsection
