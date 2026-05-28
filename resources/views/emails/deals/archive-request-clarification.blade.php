@extends('emails.layouts.base', ['headerLabel' => 'Clarification Needed'])
@section('content')
@php
$lead      = $archiveRequest->lead;
$dealName  = $lead?->name ?? 'your deal';
$dealUrl   = $lead ? url('/reseller/' . $tenant->id . '/deals/' . $lead->id) : null;
@endphp
<p class="greeting">Clarification Needed</p>
<p class="text">Hi <strong>{{ $reseller->name }}</strong>, your archive request for <strong>{{ $dealName }}</strong> needs some clarification before it can be processed.</p>
<div class="highlight-box" style="background:#EFF6FF;border-left:4px solid #3B82F6;padding:16px;border-radius:8px;margin:20px 0">
    <div class="highlight-title" style="color:#1D4ED8;font-weight:600;margin-bottom:8px">Admin's Message</div>
    <div class="highlight-text" style="color:#1E40AF">{!! nl2br(e($archiveRequest->clarification_message ?? 'No message provided.')) !!}</div>
    @if($archiveRequest->clarification_due_at)
    <div style="margin-top:10px;font-size:13px;color:#3B82F6">
        Please respond by: <strong>{{ $archiveRequest->clarification_due_at->format('F d, Y') }}</strong>
    </div>
    @endif
</div>
<p class="text">Please visit your deal page to provide the requested information using the response form.</p>
@if($dealUrl)
<div class="cta-wrap">
    <a href="{{ $dealUrl }}" class="cta cta-teal">Respond to Clarification →</a>
</div>
@endif
<hr class="divider">
<p class="text" style="font-size:13px;color:#9CA3AF">{{ $tenant->name }}</p>
@endsection
