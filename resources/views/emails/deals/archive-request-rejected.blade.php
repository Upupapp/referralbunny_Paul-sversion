@extends('emails.layouts.base', ['headerLabel' => 'Archive Request'])
@section('content')
<p class="greeting">Archive Request Not Approved</p>
<p class="text">Hi <strong>{{ $resellerName }}</strong>, your archive request for <strong>{{ $dealName }}</strong> was not approved at this time.</p>
<div class="highlight-box highlight-red">
    <div class="highlight-title" style="color:#DC2626">Request Rejected</div>
    <div class="highlight-text">
        <strong>Deal:</strong> {{ $dealName }}<br>
        <strong>Stage:</strong> {{ $stage }}<br>
        @if($reviewerNote)
        <strong>Reason from Admin:</strong> {{ $reviewerNote }}
        @endif
    </div>
</div>
<p class="text">The deal remains active. If you have questions about this decision, please reach out to your program administrator.</p>
@if($dealUrl)
<div class="cta-wrap">
    <a href="{{ $dealUrl }}" class="cta cta-teal">View Deal →</a>
</div>
@endif
<hr class="divider">
<p class="text" style="font-size:13px;color:#9CA3AF">{{ $tenantName }}</p>
@endsection
