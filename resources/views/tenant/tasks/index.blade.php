@extends('layouts.app')
@section('title', 'Tasks')
@section('nav') @include('tenant._nav') @endsection

@section('content')
<div class="space-y-5">

    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div>
            <h1 style="font-size:20px;font-weight:700;color:#1E1B4B">Tasks</h1>
            <p style="font-size:13px;color:#9ca3af;margin-top:2px">Manage requests, assignments, and action items.</p>
        </div>
    </div>

    {{-- Tab filters --}}
    <div style="display:flex;gap:6px;flex-wrap:wrap">
        @foreach([['mine','My Tasks'],['all','All Tasks'],['overdue','Overdue'],['completed','Completed']] as [$key,$label])
        <a href="{{ request()->fullUrlWithQuery(['tab' => $key]) }}"
           style="padding:7px 16px;font-size:13px;font-weight:600;border-radius:9999px;text-decoration:none;transition:all .15s;
                  {{ $tab === $key ? 'background:#7B61FF;color:white;box-shadow:0 4px 12px rgba(123,97,255,0.3)' : 'background:white;color:#9ca3af;border:1.5px solid #e5e7eb' }}">
            {{ $label }}
        </a>
        @endforeach
    </div>

    {{-- Task list --}}
    <div class="card" style="padding:0;overflow:hidden">
        @if($tasks->isEmpty())
        <div style="text-align:center;padding:48px 24px">
            <svg style="width:40px;height:40px;color:#d1d5db;margin:0 auto 12px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p style="font-size:14px;font-weight:600;color:#1E1B4B;margin-bottom:4px">No tasks here</p>
            <p style="font-size:13px;color:#9ca3af">
                @if($tab === 'mine') Tasks assigned to you will appear here.
                @elseif($tab === 'overdue') No overdue tasks. Great work!
                @else No tasks found.
                @endif
            </p>
        </div>
        @else
        @foreach($tasks as $task)
        @php
        $priorityStyle = match($task->priority) {
            'urgent' => 'background:#fef2f2;color:#dc2626',
            'high'   => 'background:#fff7ed;color:#ea580c',
            'medium' => 'background:#fffbeb;color:#d97706',
            default  => 'background:#f3f4f6;color:#6b7280',
        };
        $statusStyle = match($task->status) {
            'completed'  => 'background:#dcfce7;color:#15803d',
            'in_progress'=> 'background:#dbeafe;color:#2563eb',
            'cancelled'  => 'background:#fee2e2;color:#dc2626',
            default      => 'background:#ede9fe;color:#7B61FF',
        };
        @endphp
        <a href="{{ route('tenant.tasks.show', [$tenant->id, $task->id]) }}"
           style="display:flex;align-items:flex-start;gap:14px;padding:14px 16px;border-bottom:1px solid #f9fafb;text-decoration:none;transition:background .1s;background:white"
           onmouseover="this.style.background='#fafafa'" onmouseout="this.style.background='white'">
            <div style="width:8px;height:8px;border-radius:9999px;flex-shrink:0;margin-top:6px;
                        {{ $task->priority === 'urgent' ? 'background:#dc2626' : ($task->priority === 'high' ? 'background:#ea580c' : ($task->priority === 'medium' ? 'background:#d97706' : 'background:#9ca3af')) }}">
            </div>
            <div style="flex:1;min-width:0">
                <div style="display:flex;align-items:flex-start;gap:8px;flex-wrap:wrap;margin-bottom:4px">
                    <p style="font-size:14px;font-weight:600;color:#1E1B4B">{{ $task->title }}</p>
                    <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:9999px;flex-shrink:0;{{ $statusStyle }}">
                        {{ str_replace('_', ' ', ucfirst($task->status)) }}
                    </span>
                    @if($task->category === 'request_form')
                    <span style="font-size:10px;font-weight:600;padding:2px 8px;border-radius:9999px;background:#f0fdf4;color:#16a34a;flex-shrink:0">Request Form</span>
                    @endif
                </div>
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;font-size:12px;color:#9ca3af">
                    @if($task->requestor_name) <span>From: {{ $task->requestor_name }}</span> @endif
                    @if($task->due_at)
                    <span style="{{ $task->isOverdue() ? 'color:#dc2626;font-weight:600' : '' }}">
                        Due {{ $task->due_at->format('M j, Y') }}{{ $task->isOverdue() ? ' (overdue)' : '' }}
                    </span>
                    @endif
                    <span>{{ $task->created_at->diffForHumans() }}</span>
                </div>
            </div>
            <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:9999px;flex-shrink:0;{{ $priorityStyle }}">{{ ucfirst($task->priority) }}</span>
        </a>
        @endforeach
        <div style="padding:12px 16px">{{ $tasks->links() }}</div>
        @endif
    </div>
</div>
@endsection
