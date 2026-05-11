@extends('emails.layouts.base', ['headerLabel' => $isReassignment ? 'Deal Reassignment' : 'New Deal Assignment'])
@section('content')
<p class="greeting">{{ $isReassignment ? 'You\'ve been reassigned to a deal.' : 'You\'ve been assigned to a new deal!' }}</p>
<p class="text">Hi <strong>{{ $resellerName }}</strong>, you have been {{ $isReassignment ? 'reassigned to' : 'assigned to' }} a deal on <strong>{{ $tenantName }}</strong>.</p>

<div class="highlight-box highlight-teal">
    <div class="highlight-title" style="color:#0D9488">Deal Details</div>
    <div class="highlight-text">
        <strong>Deal:</strong> {{ $dealName }}<br>
        <strong>Stage:</strong> {{ ucfirst(str_replace('_', ' ', $stage)) }}<br>
        <strong>Assigned by:</strong> {{ $assignedByName }}
        @if($dealValue > 0)<br><strong>Value:</strong> ₱{{ number_format($dealValue) }}@endif
    </div>
</div>

<p class="text">
    Please log in to review the deal details, add your first update, upload any supporting documents,
    and create a follow-up task to keep this deal moving forward.
</p>

<div class="cta-wrap">
    <a href="{{ $dealUrl }}" class="cta cta-teal">View My Deals →</a>
</div>

<hr class="divider">
<p class="text" style="font-size:13px;color:#6B7280;">
    You are receiving this email because you were assigned to a deal on {{ $tenantName }}.
    Log in to <strong>ReferralBunny.ai</strong> to manage your deals and commissions.
</p>
@endsection
