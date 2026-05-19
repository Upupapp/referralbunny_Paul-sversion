@extends('emails.layouts.base', ['headerLabel' => 'Deal Stage Update'])
@section('content')
<p class="greeting">Your deal has moved to a new stage.</p>
<p class="text">Hi <strong>{{ $resellerName }}</strong>, an admin on <strong>{{ $tenantName }}</strong> has updated the stage of one of your deals.</p>

<div class="highlight-box highlight-purple">
    <div class="highlight-title" style="color:#7B61FF">Stage Change</div>
    <div class="highlight-text">
        <strong>Deal:</strong> {{ $dealName }}<br>
        <strong>From:</strong> {{ ucfirst(str_replace('_', ' ', $fromStage)) }}<br>
        <strong>To:</strong> {{ ucfirst(str_replace('_', ' ', $toStage)) }}<br>
        <strong>Updated by:</strong> {{ $movedByName }}
        @if($dealValue > 0)<br><strong>Deal Value:</strong> ₱{{ number_format($dealValue) }}@endif
    </div>
</div>

<p class="text">
    Log in to review the latest deal details, update your progress notes, and make sure your next action is scheduled.
</p>

<div class="cta-wrap">
    <a href="{{ $dealUrl }}" class="cta">View Deal →</a>
</div>

<hr class="divider">
<p class="text" style="font-size:13px;color:#6B7280;">
    You are receiving this email because you are assigned to this deal on {{ $tenantName }}.
    Log in to <strong>ReferralBunny.ai</strong> to manage your deals and commissions.
</p>
@endsection
