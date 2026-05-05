@extends('emails.layouts.base', ['headerLabel' => 'Daily Summary'])
@section('content')
<p class="greeting">Good morning, {{ $resellerName }}! ☀️</p>
<p class="text">Here's your referral summary for <strong>{{ now()->setTimezone('Asia/Manila')->format('l, F j') }}</strong> on <strong>{{ $tenantName }}</strong>.</p>

<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px">
    <tr>
        <td style="padding:8px;text-align:center;background:#F0FDFA;border-radius:12px;width:25%">
            <div style="font-size:24px;font-weight:700;color:#0D9488">{{ $totalDeals }}</div>
            <div style="font-size:11px;color:#6B7280">Total Deals</div>
        </td>
        <td style="width:8px"></td>
        <td style="padding:8px;text-align:center;background:#EDE9FE;border-radius:12px;width:25%">
            <div style="font-size:24px;font-weight:700;color:#7B61FF">{{ $activeDeals }}</div>
            <div style="font-size:11px;color:#6B7280">Active</div>
        </td>
        <td style="width:8px"></td>
        <td style="padding:8px;text-align:center;background:{{ $expiringDeals > 0 ? '#FFFBEB' : '#F0FDFA' }};border-radius:12px;width:25%">
            <div style="font-size:24px;font-weight:700;color:{{ $expiringDeals > 0 ? '#D97706' : '#0D9488' }}">{{ $expiringDeals }}</div>
            <div style="font-size:11px;color:#6B7280">Expiring</div>
        </td>
        <td style="width:8px"></td>
        <td style="padding:8px;text-align:center;background:#F5F3FF;border-radius:12px;width:25%">
            <div style="font-size:16px;font-weight:700;color:#7B61FF">₱{{ number_format($pendingCommission) }}</div>
            <div style="font-size:11px;color:#6B7280">Pending Comm.</div>
        </td>
    </tr>
</table>

@if(count($expiringList) > 0)
<div class="highlight-box highlight-orange">
    <div class="highlight-title" style="color:#D97706">Deals Needing Attention</div>
    @foreach($expiringList as $deal)
    <div class="highlight-text" style="margin-bottom:6px">
        <strong>{{ $deal['name'] }}</strong> — {{ $deal['days_left'] }}d left in {{ ucfirst(str_replace('_', ' ', $deal['stage'])) }}
    </div>
    @endforeach
</div>
@endif

<div class="cta-wrap">
    <a href="{{ $dashboardUrl }}" class="cta cta-teal">Open My Dashboard →</a>
</div>
@endsection
