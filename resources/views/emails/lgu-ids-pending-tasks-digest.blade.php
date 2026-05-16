@extends('emails.layouts.base', ['headerLabel' => 'Daily Task Digest'])
@section('content')
<p class="greeting">Good morning, {{ $adminName }}!</p>
<p class="text">
    Here are your <strong>{{ $totalCount }} pending task{{ $totalCount !== 1 ? 's' : '' }}</strong> for <strong>{{ $tenantName }}</strong> as of <strong>{{ $date }}</strong>.
</p>

@php
$priorityOrder = ['urgent' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
$sorted = collect($tasks)->sortBy(fn($t) => $priorityOrder[$t['priority']] ?? 9)->values();
$priorityColors = [
    'urgent' => ['bg' => '#FEE2E2', 'color' => '#DC2626', 'label' => 'URGENT'],
    'high'   => ['bg' => '#FEF3C7', 'color' => '#D97706', 'label' => 'HIGH'],
    'medium' => ['bg' => '#EDE9FE', 'color' => '#7B61FF', 'label' => 'MEDIUM'],
    'low'    => ['bg' => '#F0FDF4', 'color' => '#16A34A', 'label' => 'LOW'],
];
@endphp

<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;border:1px solid #E5E7EB;border-radius:12px;overflow:hidden">
    <tr style="background:#F9FAFB">
        <td style="padding:10px 14px;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase;letter-spacing:.05em;width:45%">Task</td>
        <td style="padding:10px 8px;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase;letter-spacing:.05em;width:15%">Priority</td>
        <td style="padding:10px 8px;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase;letter-spacing:.05em;width:20%">Due</td>
        <td style="padding:10px 8px;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase;letter-spacing:.05em;width:20%">Assigned To</td>
    </tr>
    @foreach($sorted as $i => $task)
    @php $pc = $priorityColors[$task['priority']] ?? $priorityColors['medium']; @endphp
    <tr style="background:{{ $i % 2 === 0 ? '#FFFFFF' : '#F9FAFB' }};border-top:1px solid #F3F4F6">
        <td style="padding:12px 14px">
            <a href="{{ $task['url'] }}" style="font-size:13px;font-weight:600;color:#1E1B4B;text-decoration:none">{{ $task['title'] }}</a>
            @if(!empty($task['description']))
            <div style="font-size:12px;color:#9CA3AF;margin-top:2px">{{ Str::limit($task['description'], 80) }}</div>
            @endif
        </td>
        <td style="padding:12px 8px">
            <span style="display:inline-block;padding:2px 8px;border-radius:99px;font-size:10px;font-weight:700;background:{{ $pc['bg'] }};color:{{ $pc['color'] }}">{{ $pc['label'] }}</span>
        </td>
        <td style="padding:12px 8px;font-size:12px;color:{{ $task['overdue'] ? '#DC2626' : '#374151' }};font-weight:{{ $task['overdue'] ? '700' : '400' }}">
            {{ $task['due_label'] }}
            @if($task['overdue'])<div style="font-size:10px;color:#DC2626">OVERDUE</div>@endif
        </td>
        <td style="padding:12px 8px;font-size:12px;color:#6B7280">{{ $task['assigned_to'] ?? '—' }}</td>
    </tr>
    @endforeach
</table>

<div class="cta-wrap">
    <a href="{{ $tasksUrl }}" class="cta">View All Tasks →</a>
</div>

<hr class="divider">
<p class="text" style="font-size:12px;color:#9CA3AF;text-align:center">
    This digest is sent every morning to LGU IDS administrators. Complete tasks are excluded.
</p>
@endsection
