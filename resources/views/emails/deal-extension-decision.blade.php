@extends('emails.layouts.base', ['headerLabel' => match($decision) { 'approved' => 'Extension Approved', 'rejected' => 'Extension Rejected', default => 'Clarification Needed' }])
@section('content')
@php
$dealUrl = url('/reseller/' . $dealTenantId . '/deals/' . $dealId);
@endphp

@if($decision === 'approved')
<p class="greeting">Your extension request was approved!</p>
<p class="text">Hi <strong>{{ $resellerName }}</strong>, great news — your deadline extension request for <strong>{{ $dealName }}</strong> has been approved.</p>
<div class="highlight-box highlight-teal">
    <div class="highlight-title" style="color:#0D9488">Extension Details</div>
    <div class="highlight-text">
        <strong>Deal:</strong> {{ $dealName }}<br>
        <strong>Days Added:</strong> {{ $extensionRequest->approved_days }} day{{ $extensionRequest->approved_days !== 1 ? 's' : '' }}
        @if($extensionRequest->admin_note)<br><strong>Admin Note:</strong> {{ $extensionRequest->admin_note }}@endif
    </div>
</div>
<p class="text">Use this extra time wisely — keep your deal notes updated and your next follow-up scheduled.</p>

@elseif($decision === 'rejected')
<p class="greeting">Extension request update</p>
<p class="text">Hi <strong>{{ $resellerName }}</strong>, unfortunately your extension request for <strong>{{ $dealName }}</strong> has been rejected.</p>
@if($extensionRequest->admin_note)
<div class="highlight-box" style="background:#FEF2F2;border-left:4px solid #EF4444;padding:16px;border-radius:8px;margin:20px 0">
    <div class="highlight-title" style="color:#DC2626;font-weight:600;margin-bottom:8px">Admin's Note</div>
    <div class="highlight-text" style="color:#991B1B">{!! nl2br(e($extensionRequest->admin_note)) !!}</div>
</div>
@endif
<p class="text">Please visit the deal page if you have further questions or need to take action.</p>

@else
<p class="greeting">Clarification needed on your extension request</p>
<p class="text">Hi <strong>{{ $resellerName }}</strong>, the admin has requested clarification on your extension request for <strong>{{ $dealName }}</strong>.</p>
@if($extensionRequest->admin_note)
<div class="highlight-box" style="background:#EFF6FF;border-left:4px solid #3B82F6;padding:16px;border-radius:8px;margin:20px 0">
    <div class="highlight-title" style="color:#1D4ED8;font-weight:600;margin-bottom:8px">Admin's Message</div>
    <div class="highlight-text" style="color:#1E40AF">{!! nl2br(e($extensionRequest->admin_note)) !!}</div>
</div>
@endif
<p class="text">Please visit the deal page to respond to the clarification request.</p>
@endif

<div class="cta-wrap">
    <a href="{{ $dealUrl }}" class="cta cta-teal">View Deal →</a>
</div>

<hr class="divider">
<p class="text" style="font-size:13px;color:#6B7280;">
    You are receiving this email because you submitted an extension request on {{ $tenantName }}.
</p>
@endsection
