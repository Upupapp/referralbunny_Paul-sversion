@extends('emails.layouts.base', ['headerLabel' => 'Invitation Reminder', 'subject' => "Reminder: Your invitation to join {$tenantName}"])

@section('content')
<div style="text-align:center;margin-bottom:24px">
    @if($isLastReminder)
        <img src="https://referralbunny.ai/images/mascots/r-bunny-warning-error.webp"
             alt="" width="120" height="120"
             style="width:120px;height:120px;object-fit:contain;display:inline-block">
    @else
        <img src="https://referralbunny.ai/images/mascots/r-bunny-helper-question.webp"
             alt="" width="120" height="120"
             style="width:120px;height:120px;object-fit:contain;display:inline-block">
    @endif
</div>

<p class="greeting">
    @if($isLastReminder)
        ⚠️ Last chance!
    @else
        Just a friendly reminder 👋
    @endif
</p>

<p class="text">
    You still have a pending invitation from <strong>{{ $inviterName }}</strong> to join
    <strong>{{ $tenantName }}</strong> as a <strong>{{ $roleLabel }}</strong> on ReferralBunny.ai.
</p>

@if($isLastReminder)
<div class="highlight-box highlight-orange">
    <div class="highlight-title" style="color:#D97706">Invitation Expires on {{ $expiresAt }}</div>
    <div class="highlight-text">
        This is your final reminder. After <strong>{{ $expiresAt }}</strong>, this link will no longer work
        and you'll need to ask <strong>{{ $inviterName }}</strong> to send a new invitation.
    </div>
</div>
@else
<div class="highlight-box highlight-purple">
    <div class="highlight-title" style="color:#7B61FF">Your invitation expires on {{ $expiresAt }}</div>
    <div class="highlight-text">
        Accept before the deadline to join <strong>{{ $tenantName }}</strong> as a <strong>{{ $roleLabel }}</strong>.
    </div>
</div>
@endif

<div class="cta-wrap">
    <a href="{{ $acceptUrl }}" class="cta {{ $isLastReminder ? '' : '' }}">
        Accept Invitation →
    </a>
</div>

<div class="link-box"><a href="{{ $acceptUrl }}">{{ $acceptUrl }}</a></div>

<hr class="divider">
<p class="text" style="font-size:13px;color:#9CA3AF">
    If you no longer want to join {{ $tenantName }}, simply ignore this email.
    No action is needed.
</p>
@endsection
