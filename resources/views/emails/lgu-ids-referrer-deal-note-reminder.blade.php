@extends('emails.layouts.base', ['headerLabel' => 'Weekly Deal Reminder'])
@section('content')
@php
$count = count($deals);
$stageLabels = [
    'introduction'  => 'Introduction',
    'presentation'  => 'Presentation',
    'contract_sent' => 'Contract Sent',
    'signed'        => 'Signed',
];
$stageColors = [
    'introduction'  => '#9CA3AF',
    'presentation'  => '#3B82F6',
    'contract_sent' => '#F59E0B',
    'signed'        => '#8B5CF6',
];
@endphp

<p class="greeting">Hi {{ $referrerName }},</p>
<p class="text">
    You have <strong>{{ $count }} active deal{{ $count !== 1 ? 's' : '' }}</strong> in <strong>{{ $tenantName }}</strong>
    with no notes yet. Notes help your team track progress — and deals with regular updates are more likely to get approved quickly.
</p>

<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;border:1px solid #E5E7EB;border-radius:12px;overflow:hidden">
    <tr style="background:#F9FAFB">
        <td style="padding:10px 14px;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase;letter-spacing:.05em;width:45%">Deal</td>
        <td style="padding:10px 8px;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase;letter-spacing:.05em;width:20%">Stage</td>
        <td style="padding:10px 8px;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase;letter-spacing:.05em;width:15%">Status</td>
        <td style="padding:10px 8px;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase;letter-spacing:.05em;width:20%">Days Left</td>
    </tr>
    @foreach($deals as $i => $deal)
    @php
        $stageLabel  = $stageLabels[$deal['stage']]  ?? ucfirst(str_replace('_', ' ', $deal['stage']));
        $stageColor  = $stageColors[$deal['stage']]  ?? '#9CA3AF';
        $isExpiring  = $deal['status'] === 'expiring';
        $daysLeft    = $deal['days_left'];
        $daysColor   = ($daysLeft !== null && $daysLeft <= 3) ? '#DC2626' : (($daysLeft !== null && $daysLeft <= 7) ? '#D97706' : '#374151');
    @endphp
    <tr style="background:{{ $i % 2 === 0 ? '#FFFFFF' : '#F9FAFB' }};border-top:1px solid #F3F4F6">
        <td style="padding:12px 14px">
            <a href="{{ $deal['url'] }}" style="font-size:13px;font-weight:600;color:#1E1B4B;text-decoration:none">{{ $deal['name'] }}</a>
            <div style="margin-top:4px">
                <a href="{{ $deal['url'] }}" style="font-size:11px;color:#7B61FF;text-decoration:none;font-weight:600">+ Add a note →</a>
            </div>
        </td>
        <td style="padding:12px 8px">
            <span style="display:inline-block;padding:2px 8px;border-radius:99px;font-size:10px;font-weight:700;color:#FFFFFF;background:{{ $stageColor }}">{{ $stageLabel }}</span>
        </td>
        <td style="padding:12px 8px">
            @if($isExpiring)
            <span style="display:inline-block;padding:2px 8px;border-radius:99px;font-size:10px;font-weight:700;background:#FEF3C7;color:#D97706">Expiring</span>
            @else
            <span style="display:inline-block;padding:2px 8px;border-radius:99px;font-size:10px;font-weight:700;background:#D1FAE5;color:#065F46">Active</span>
            @endif
        </td>
        <td style="padding:12px 8px;font-size:12px;color:{{ $daysColor }};font-weight:{{ ($daysLeft !== null && $daysLeft <= 3) ? '700' : '400' }}">
            {{ $daysLeft !== null ? $daysLeft . 'd' : '—' }}
        </td>
    </tr>
    @endforeach
</table>

<div class="highlight-box" style="background:#F3F0FF;border:1px solid #DDD6FE;border-radius:12px;padding:16px 20px;margin-bottom:24px">
    <p style="margin:0;font-size:13px;font-weight:600;color:#5B21B6">💡 What makes a good note?</p>
    <p style="margin:8px 0 0;font-size:12px;color:#6D28D9;line-height:1.6">
        A quick update on your last contact, the decision-maker's response, any concerns raised, or the next scheduled meeting.
        Even one line is enough to keep the deal moving.
    </p>
</div>

<div class="cta-wrap">
    <a href="{{ $dealsUrl }}" class="cta">View Deals Without Notes →</a>
</div>

<hr class="divider">
<p class="text" style="font-size:12px;color:#9CA3AF;text-align:center">
    This reminder is sent every Wednesday to LGU IDS referrers with active deals that have no notes.
    Week of {{ $weekLabel }}.
</p>
@endsection
