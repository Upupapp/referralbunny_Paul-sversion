@extends('emails.layouts.base', ['headerLabel' => 'Task Due Today'])
@section('content')
<p class="greeting">⏰ Task Due Today</p>
<p class="text">Hi <strong>{{ $adminName }}</strong>, the following task is due <strong>today</strong> and still pending.</p>

@php
$priorityColors = [
    'urgent' => ['bg' => '#FEE2E2', 'border' => '#FECACA', 'color' => '#DC2626'],
    'high'   => ['bg' => '#FEF3C7', 'border' => '#FDE68A', 'color' => '#D97706'],
    'medium' => ['bg' => '#EDE9FE', 'border' => '#DDD6FE', 'color' => '#7B61FF'],
    'low'    => ['bg' => '#F0FDF4', 'border' => '#BBF7D0', 'color' => '#16A34A'],
];
$pc = $priorityColors[$taskPriority] ?? $priorityColors['medium'];
@endphp

<div style="background:{{ $pc['bg'] }};border:1.5px solid {{ $pc['border'] }};border-radius:14px;padding:20px 24px;margin-bottom:24px">
    <div style="font-size:10px;font-weight:700;color:{{ $pc['color'] }};letter-spacing:.1em;text-transform:uppercase;margin-bottom:6px">
        {{ strtoupper($taskPriority) }} Priority · Due {{ $dueLabel }}
    </div>
    <div style="font-size:18px;font-weight:700;color:#1E1B4B;margin-bottom:8px">{{ $taskTitle }}</div>
    @if($taskDescription)
    <div style="font-size:14px;color:#6B7280;line-height:1.6">{{ $taskDescription }}</div>
    @endif
    @if($assignedTo)
    <div style="margin-top:10px;font-size:12px;color:#9CA3AF">Assigned to: <strong style="color:#374151">{{ $assignedTo }}</strong></div>
    @endif
</div>

<div class="cta-wrap">
    <a href="{{ $taskUrl }}" class="cta">Open Task →</a>
</div>

<hr class="divider">
<p class="text" style="font-size:12px;color:#9CA3AF;text-align:center">
    You receive this reminder because this task is due today and has not been completed.
    Each due task is sent as a separate reminder email.
</p>
@endsection
