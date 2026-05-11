@extends('emails.layouts.base', ['headerLabel' => 'Platform Daily Summary'])
@section('content')
<p class="greeting">ReferralBunny.ai Daily Summary</p>
<p class="text">Platform overview for <strong>{{ now()->setTimezone('Asia/Manila')->format('l, F j, Y') }}</strong>.</p>

<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px">
    <tr>
        <td style="padding:10px;text-align:center;background:#EDE9FE;border-radius:12px;width:32%">
            <div style="font-size:24px;font-weight:700;color:#7B61FF">{{ $activeTenants }}</div>
            <div style="font-size:11px;color:#6B7280;margin-top:2px">Active Tenants</div>
        </td>
        <td style="width:6px"></td>
        <td style="padding:10px;text-align:center;background:#D1FAE5;border-radius:12px;width:32%">
            <div style="font-size:24px;font-weight:700;color:#059669">{{ $newDealsToday }}</div>
            <div style="font-size:11px;color:#6B7280;margin-top:2px">New Deals Today</div>
        </td>
        <td style="width:6px"></td>
        <td style="padding:10px;text-align:center;background:{{ $expiringDeals > 0 ? '#FFFBEB' : '#F3F4F6' }};border-radius:12px;width:32%">
            <div style="font-size:24px;font-weight:700;color:{{ $expiringDeals > 0 ? '#D97706' : '#6B7280' }}">{{ $expiringDeals }}</div>
            <div style="font-size:11px;color:#6B7280;margin-top:2px">Expiring Deals</div>
        </td>
    </tr>
</table>

<table width="100%" cellpadding="12" cellspacing="0" style="border:1px solid #F3F4F6;border-radius:12px;margin-bottom:24px">
    <tr style="background:#F9FAFB">
        <td style="font-size:12px;font-weight:600;color:#6B7280;text-transform:uppercase;letter-spacing:.06em">Metric</td>
        <td style="font-size:12px;font-weight:600;color:#6B7280;text-transform:uppercase;letter-spacing:.06em;text-align:right">Value</td>
    </tr>
    <tr><td class="text" style="margin:0">Total Deals (all time)</td><td style="font-weight:700;color:#1E1B4B;text-align:right">{{ $totalDeals }}</td></tr>
    <tr style="background:#F9FAFB"><td class="text" style="margin:0">New Referrers Today</td><td style="font-weight:700;color:#1E1B4B;text-align:right">{{ $newResellers }}</td></tr>
</table>

<div class="cta-wrap">
    <a href="{{ $dashboardUrl }}" class="cta">Open Super Admin Dashboard →</a>
</div>
@endsection
