@extends('emails.layouts.base', ['headerLabel' => 'Extension Request Update'])
@section('content')
@php
    $allApproved  = $approvedCount > 0 && $declinedCount === 0;
    $allDeclined  = $declinedCount > 0 && $approvedCount === 0;
    $partial      = $approvedCount > 0 && $declinedCount > 0;
    $highlightClass = $allApproved ? 'highlight-teal' : ($allDeclined ? 'highlight-red' : 'highlight-blue');
    $titleColor     = $allApproved ? '#0D9488' : ($allDeclined ? '#DC2626' : '#2563EB');
@endphp
<p class="greeting">
    @if($allApproved) Your extension request was approved!
    @elseif($allDeclined) Your extension request was declined.
    @else Your extension request has been reviewed.
    @endif
</p>
<p class="text">Hi <strong>{{ $referrerName }}</strong>, here is a summary of the review for batch <strong>{{ $batchReference }}</strong>.</p>

<div class="highlight-box {{ $highlightClass }}">
    <div class="highlight-title" style="color:{{ $titleColor }}">Review Summary</div>
    <div class="highlight-text">
        <strong>Total Deals:</strong> {{ $totalCount }}<br>
        @if($approvedCount > 0)
        <strong style="color:#059669">✓ Approved:</strong> {{ $approvedCount }} deal{{ $approvedCount !== 1 ? 's' : '' }}<br>
        @endif
        @if($declinedCount > 0)
        <strong style="color:#DC2626">✗ Declined:</strong> {{ $declinedCount }} deal{{ $declinedCount !== 1 ? 's' : '' }}<br>
        @endif
        @if($skippedCount > 0)
        <strong style="color:#6B7280">— Pending further review:</strong> {{ $skippedCount }}<br>
        @endif
        @if($adminNote)
        <strong>Admin Note:</strong> {{ $adminNote }}
        @endif
    </div>
</div>

@if(count($dealSummaries) > 0)
<p class="text" style="font-weight:600;margin-bottom:8px;">Deal breakdown:</p>
<table style="width:100%;border-collapse:collapse;font-size:13px;margin-bottom:16px;">
    <thead>
        <tr style="background:#F9FAFB;">
            <th style="text-align:left;padding:6px 8px;color:#374151;font-weight:600;border-bottom:1px solid #E5E7EB;">Deal</th>
            <th style="text-align:center;padding:6px 8px;color:#374151;font-weight:600;border-bottom:1px solid #E5E7EB;">Result</th>
            <th style="text-align:center;padding:6px 8px;color:#374151;font-weight:600;border-bottom:1px solid #E5E7EB;">Days Added</th>
        </tr>
    </thead>
    <tbody>
        @foreach($dealSummaries as $deal)
        <tr style="border-bottom:1px solid #F3F4F6;">
            <td style="padding:6px 8px;color:#1F2937;">{{ $deal['name'] }}</td>
            <td style="padding:6px 8px;text-align:center;">
                @if($deal['status'] === 'approved')
                <span style="color:#059669;font-weight:600;">Approved</span>
                @elseif($deal['status'] === 'rejected')
                <span style="color:#DC2626;font-weight:600;">Declined</span>
                @else
                <span style="color:#6B7280;">{{ ucfirst($deal['status']) }}</span>
                @endif
            </td>
            <td style="padding:6px 8px;text-align:center;color:#059669;font-weight:600;">
                {{ isset($deal['approved_days']) && $deal['approved_days'] ? '+' . $deal['approved_days'] . 'd' : '—' }}
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@if($totalCount > count($dealSummaries))
<p class="text" style="font-size:12px;color:#6B7280;">…and {{ $totalCount - count($dealSummaries) }} more deal{{ ($totalCount - count($dealSummaries)) !== 1 ? 's' : '' }}. View the full request for details.</p>
@endif
@endif

@if($allApproved)
<p class="text">Use this extra time wisely — update your deal notes, schedule your next follow-up, and keep the momentum going!</p>
@elseif($partial)
<p class="text">The approved deals have already been updated. Log in to see which deals received extensions and plan your next steps.</p>
@endif

<div class="cta-wrap">
    <a href="{{ $requestUrl }}" class="cta {{ $allApproved ? 'cta-teal' : ($allDeclined ? 'cta-red' : 'cta-violet') }}">View Full Request →</a>
</div>

<hr class="divider">
<p class="text" style="font-size:13px;color:#6B7280;">
    You are receiving this email because you submitted a bulk extension request on <strong>{{ $tenantName }}</strong> via ReferralBunny.ai.
</p>
@endsection
