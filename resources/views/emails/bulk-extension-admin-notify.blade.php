@extends('emails.layouts.base', ['headerLabel' => 'Bulk Extension Request'])
@section('content')
<p class="greeting">Action needed: bulk extension request submitted</p>
<p class="text">Hi <strong>{{ $adminName }}</strong>, a Referrer has submitted a bulk extension request that needs your review.</p>

<div class="highlight-box highlight-amber">
    <div class="highlight-title" style="color:#D97706">Request Summary</div>
    <div class="highlight-text">
        <strong>Reference:</strong> {{ $batchReference }}<br>
        <strong>Referrer:</strong> {{ $referrerName }}<br>
        <strong>Deals:</strong> {{ $dealCount }} deal{{ $dealCount !== 1 ? 's' : '' }}<br>
        <strong>Requested Extension:</strong> {{ $requestedDays }} day{{ $requestedDays !== 1 ? 's' : '' }}<br>
        @if($earliestExpiry)
        <strong>Earliest Expiry:</strong> {{ $earliestExpiry }}<br>
        @endif
        @if($expiredCount > 0)
        <strong style="color:#DC2626">⚠ Expired deals included:</strong> {{ $expiredCount }}<br>
        @endif
        @if($expiringCount > 0)
        <strong style="color:#D97706">⏰ Expiring soon:</strong> {{ $expiringCount }} deal{{ $expiringCount !== 1 ? 's' : '' }}<br>
        @endif
        <strong>Reason:</strong> {{ $reasonPreview }}
    </div>
</div>

<p class="text">
    Review each deal in the batch, approve the eligible ones, and reject or skip any that don't qualify.
    @if($expiredCount > 0)
    Some deals have already expired — please check them carefully before approving.
    @endif
</p>

<div class="cta-wrap">
    <a href="{{ $reviewUrl }}" class="cta cta-violet">Review Extension Request →</a>
</div>

<hr class="divider">
<p class="text" style="font-size:13px;color:#6B7280;">
    You are receiving this email because you are an admin or manager of <strong>{{ $tenantName }}</strong> on ReferralBunny.ai.
</p>
@endsection
