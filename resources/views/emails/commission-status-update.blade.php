@extends('emails.layouts.base', ['headerLabel' => 'Commission Update'])
@section('content')
@php
    $colors = ['pending'=>['#EDE9FE','#7B61FF'],'locked'=>['#FEF3C7','#D97706'],'paid'=>['#D1FAE5','#059669']];
    $labels = ['pending'=>'Commission Pending','locked'=>'Commission Locked','paid'=>'Commission Paid! 🎉'];
    [$bg, $color] = $colors[$status] ?? ['#F3F4F6','#6B7280'];
@endphp
<p class="greeting">{{ $labels[$status] ?? 'Commission Update' }}</p>
<p class="text">Hi <strong>{{ $resellerName }}</strong>, here's an update on your commission for a deal in <strong>{{ $tenantName }}</strong>.</p>
<div class="highlight-box" style="background:{{ $bg }};border-left:4px solid {{ $color }}">
    <div class="highlight-title" style="color:{{ $color }}">{{ strtoupper($labels[$status] ?? $status) }}</div>
    <div class="highlight-text">
        <strong>Deal:</strong> {{ $dealName }}<br>
        @if($dealValue > 0)<strong>Deal Value:</strong> ₱{{ number_format($dealValue) }}<br>@endif
        <strong>Status:</strong> <span style="color:{{ $color }};font-weight:600">{{ ucfirst($status) }}</span>
    </div>
</div>
@if($status === 'paid')
<p class="text">Congratulations! Your commission for this deal has been approved and marked as paid. Check your dashboard for the full details.</p>
@elseif($status === 'locked')
<p class="text">Your commission has been locked and is pending final approval. You'll receive another notification once it's paid.</p>
@else
<p class="text">Your commission is being tracked. Keep advancing your deals to move this commission forward.</p>
@endif
<div class="cta-wrap">
    <a href="{{ $dashboardUrl }}" class="cta cta-teal">View My Commission →</a>
</div>
@endsection
