@extends('emails.layouts.base', ['headerLabel' => 'Password Reset', 'subject' => 'Reset your Partner Portal password'])

@section('content')
<p class="greeting">Reset your password</p>

<p class="text">
    Hi <strong>{{ $partnerName }}</strong>, we received a request to reset your password for
    the <strong>{{ $tenantName }}</strong> partner portal on ReferralBunny.ai.
</p>

<div class="highlight-box highlight-orange">
    <div class="highlight-title" style="color:#D97706">Security Notice</div>
    <div class="highlight-text">This link expires after use. If you didn't request a password reset, please ignore this email — your account is safe.</div>
</div>

<div class="cta-wrap">
    <a href="{{ $resetUrl }}" class="cta">Reset My Password →</a>
</div>

<p class="text" style="font-size:13px;color:#9CA3AF;text-align:center">
    Or copy this link into your browser:
</p>
<div class="link-box"><a href="{{ $resetUrl }}">{{ $resetUrl }}</a></div>

<hr class="divider">
<p class="text" style="font-size:13px;color:#9CA3AF">
    Didn't request this? No action needed. Your password won't change unless you click the link above.
</p>
@endsection
