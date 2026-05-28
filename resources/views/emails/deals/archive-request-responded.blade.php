@extends('emails.layouts.base', ['headerLabel' => 'Referrer Responded'])
@section('content')
<p class="greeting">Referrer responded to clarification</p>
<p class="text">Hi <strong>{{ $adminName }}</strong>, <strong>{{ $resellerName }}</strong> has responded to your clarification request for the archive request on <strong>{{ $dealName }}</strong>.</p>

<div class="highlight-box" style="background:#F0FDF4;border-left:4px solid #22C55E;padding:16px;border-radius:8px;margin:20px 0">
    <div class="highlight-title" style="color:#15803D;font-weight:600;margin-bottom:8px">Referrer's Response</div>
    <div class="highlight-text" style="color:#166534">{!! nl2br(e($visibleResponse)) !!}</div>
</div>

<p class="text">Please review the response and approve, reject, or ask for further clarification.</p>

<div class="cta-wrap">
    <a href="{{ $dealUrl }}" class="cta cta-teal">Review & Decide →</a>
</div>

<hr class="divider">
<p class="text" style="font-size:13px;color:#9CA3AF">{{ $tenantName }}</p>
@endsection
