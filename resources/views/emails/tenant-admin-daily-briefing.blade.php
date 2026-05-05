@extends('emails.layouts.base', ['headerLabel' => 'Daily Briefing'])
@section('content')
<p class="greeting">Good morning, {{ $adminName }}! ☀️</p>
<p class="text">Here's your daily briefing for <strong>{{ $tenantName }}</strong> — {{ now()->setTimezone('Asia/Manila')->format('l, F j, Y') }}.</p>

<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px">
    <tr>
        <td style="padding:10px;text-align:center;background:#FEF3C7;border-radius:12px;width:32%">
            <div style="font-size:28px;font-weight:700;color:#D97706">{{ $expiringDeals }}</div>
            <div style="font-size:11px;color:#6B7280;margin-top:2px">Expiring Deals</div>
        </td>
        <td style="width:8px"></td>
        <td style="padding:10px;text-align:center;background:#EDE9FE;border-radius:12px;width:32%">
            <div style="font-size:28px;font-weight:700;color:#7B61FF">{{ $newDeals }}</div>
            <div style="font-size:11px;color:#6B7280;margin-top:2px">New Deals</div>
        </td>
        <td style="width:8px"></td>
        <td style="padding:10px;text-align:center;background:#D1FAE5;border-radius:12px;width:32%">
            <div style="font-size:28px;font-weight:700;color:#059669">{{ $activeDeals }}</div>
            <div style="font-size:11px;color:#6B7280;margin-top:2px">Active Deals</div>
        </td>
    </tr>
</table>

@if(count($expiringList) > 0)
<div class="highlight-box highlight-orange">
    <div class="highlight-title" style="color:#D97706">⚠️ Expiring Deals — Action Required</div>
    @foreach($expiringList as $deal)
    <div style="display:flex;justify-content:space-between;margin-bottom:8px;padding-bottom:8px;border-bottom:1px solid rgba(0,0,0,.06)">
        <div class="highlight-text">
            <strong>{{ $deal['name'] }}</strong><br>
            <span style="font-size:12px;color:#9CA3AF">{{ $deal['reseller'] ?? 'No referrer' }} · {{ ucfirst(str_replace('_', ' ', $deal['stage'])) }}</span>
        </div>
        <div style="font-size:13px;font-weight:700;color:{{ $deal['days_left'] <= 1 ? '#DC2626' : '#D97706' }};white-space:nowrap;margin-left:12px">
            {{ $deal['days_left'] }}d left
        </div>
    </div>
    @endforeach
</div>
@endif

@if(count($newDealsList) > 0)
<div class="highlight-box highlight-purple">
    <div class="highlight-title" style="color:#7B61FF">New Deals Since Yesterday</div>
    @foreach($newDealsList as $deal)
    <div class="highlight-text" style="margin-bottom:4px">
        <strong>{{ $deal['name'] }}</strong> — {{ $deal['reseller'] ?? 'No referrer' }}
    </div>
    @endforeach
</div>
@endif

<div class="cta-wrap">
    <a href="{{ $dashboardUrl }}" class="cta">Open Dashboard →</a>
</div>
@endsection
