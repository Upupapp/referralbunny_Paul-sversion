@extends('emails.layouts.base', ['headerLabel' => 'Archive Request'])
@section('content')
<p class="greeting">New Archive Request</p>
<p class="text">Hi <strong>{{ $adminName }}</strong>, a Referrer has submitted an archive request for the deal below.</p>
<div class="highlight-box highlight-orange">
    <div class="highlight-title" style="color:#D97706">Deal Details</div>
    <div class="highlight-text">
        <strong>Deal:</strong> {{ $dealName }}<br>
        <strong>Stage:</strong> {{ $stage }}<br>
        <strong>Value:</strong> {{ $dealValue }}<br>
        @if($reason)
        <strong>Reason:</strong> {{ $reason }}
        @endif
    </div>
</div>
<p class="text">This request is waiting for your review. You can approve, reject, or request clarification from the Referrer.</p>
<div class="cta-wrap">
    <a href="{{ $reviewUrl }}" class="cta cta-teal">Review Request →</a>
</div>
<hr class="divider">
<p class="text" style="font-size:13px;color:#9CA3AF">You received this because you are an admin or manager on {{ $tenantName }}.</p>
@endsection
