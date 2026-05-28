@extends('emails.layouts.base', ['headerLabel' => 'Archive Approved'])
@section('content')
<p class="greeting">Archive Request Approved</p>
<p class="text">Hi <strong>{{ $resellerName }}</strong>, your archive request for <strong>{{ $dealName }}</strong> has been approved.</p>
<div class="highlight-box highlight-green">
    <div class="highlight-title" style="color:#059669">Deal Archived</div>
    <div class="highlight-text">
        <strong>Deal:</strong> {{ $dealName }}<br>
        <strong>Stage:</strong> {{ $stage }}<br>
        <strong>Value:</strong> {{ $dealValue }}<br>
        @if($reviewerNote)
        <strong>Note from Admin:</strong> {{ $reviewerNote }}
        @endif
    </div>
</div>
<p class="text">The deal has been archived. If you have any questions, please contact your program administrator.</p>
<hr class="divider">
<p class="text" style="font-size:13px;color:#9CA3AF">{{ $tenantName }}</p>
@endsection
