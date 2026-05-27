@extends('emails.layouts.base', ['headerLabel' => 'Archive Approved'])
@section('content')
@php
$lead      = $archiveRequest->lead;
$dealName  = $lead?->name ?? 'your deal';
$stage     = $lead ? ucwords(str_replace('_', ' ', $lead->stage)) : '—';
$dealValue = $lead ? '₱' . number_format((float)$lead->deal_value, 0) : '—';
@endphp
<p class="greeting">Archive Request Approved</p>
<p class="text">Hi <strong>{{ $reseller->name }}</strong>, your archive request for <strong>{{ $dealName }}</strong> has been approved.</p>
<div class="highlight-box highlight-green">
    <div class="highlight-title" style="color:#059669">Deal Archived</div>
    <div class="highlight-text">
        <strong>Deal:</strong> {{ $dealName }}<br>
        <strong>Stage:</strong> {{ $stage }}<br>
        <strong>Value:</strong> {{ $dealValue }}<br>
        @if($archiveRequest->reviewer_note)
        <strong>Note from Admin:</strong> {{ $archiveRequest->reviewer_note }}
        @endif
    </div>
</div>
<p class="text">The deal has been archived. If you have any questions, please contact your program administrator.</p>
<hr class="divider">
<p class="text" style="font-size:13px;color:#9CA3AF">{{ $tenant->name }}</p>
@endsection
