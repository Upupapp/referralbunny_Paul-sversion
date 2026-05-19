@extends('emails.layouts.base', ['headerLabel' => 'Deal Amount Update'])
@section('content')
<p class="greeting">Your deal amount has been updated.</p>
<p class="text">Hi <strong>{{ $resellerName }}</strong>, an admin on <strong>{{ $tenantName }}</strong> has updated the contract amount for one of your deals. Your commission estimate will reflect this change.</p>

<div class="highlight-box highlight-teal">
    <div class="highlight-title" style="color:#0D9488">Amount Change</div>
    <div class="highlight-text">
        <strong>Deal:</strong> {{ $dealName }}<br>
        <strong>Previous Amount:</strong> ₱{{ number_format($oldAmount) }}<br>
        <strong>New Amount:</strong> ₱{{ number_format($newAmount) }}<br>
        <strong>Updated by:</strong> {{ $updatedByName }}
    </div>
</div>

<p class="text">
    Your commission is calculated as a percentage of the commission pool, which is based on the updated deal amount.
    Log in to view your updated commission estimate.
</p>

<div class="cta-wrap">
    <a href="{{ $dealUrl }}" class="cta cta-teal">View Deal →</a>
</div>

<hr class="divider">
<p class="text" style="font-size:13px;color:#6B7280;">
    You are receiving this email because you are assigned to this deal on {{ $tenantName }}.
    Log in to <strong>ReferralBunny.ai</strong> to manage your deals and commissions.
</p>
@endsection
