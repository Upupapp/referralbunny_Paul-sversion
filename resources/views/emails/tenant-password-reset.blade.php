@extends('emails.layouts.base', ['headerLabel' => 'Password Reset'])
@section('content')
<p class="greeting">Hi {{ $userName }},</p>
<p class="text">We received a request to reset your ReferralBunny.ai admin portal password. Click the button below to set a new password. This link expires in <strong>1 hour</strong>.</p>
<div class="cta-wrap">
    <a href="{{ $resetUrl }}" class="cta">Reset My Password →</a>
</div>
<p class="text" style="font-size:13px;color:#9CA3AF;text-align:center">If you did not request a password reset, you can safely ignore this email. Your password will not be changed.</p>
<hr class="divider">
<p class="text" style="font-size:12px;color:#9CA3AF">If the button above doesn't work, copy and paste this link into your browser:<br><a href="{{ $resetUrl }}" style="color:#7B61FF;word-break:break-all">{{ $resetUrl }}</a></p>
@endsection
