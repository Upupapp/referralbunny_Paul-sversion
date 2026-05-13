@extends('layouts.app')
@section('title', $task->title)
@section('nav') @include('tenant._nav') @endsection

@section('content')
@php
// ── Linkify helper: escape HTML then make URLs clickable ──────────────────
function rb_linkify(string $text): string {
    $e = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return (string) preg_replace(
        '/(https?:\/\/[^\s<>&"\'()\[\]{}]+)/i',
        '<a href="$1" target="_blank" rel="noopener noreferrer" style="color:#7B61FF;text-decoration:underline;word-break:break-all">$1</a>',
        $e
    );
}

// ── Derived display values ────────────────────────────────────────────────
$isRequestTask  = $task->source_type === 'request_form_submission';
$isTerminal     = in_array($task->status, ['completed', 'cancelled', 'archived']);

$statusMap = [
    'completed'   => ['background:#dcfce7;color:#15803d',  'Completed'],
    'in_progress' => ['background:#dbeafe;color:#2563eb',  'In Progress'],
    'cancelled'   => ['background:#f3f4f6;color:#6b7280',  'Cancelled'],
    'archived'    => ['background:#f3f4f6;color:#6b7280',  'Archived'],
    'open'        => ['background:#ede9fe;color:#7B61FF',  'Open'],
];
[$statusCss, $statusLabel] = $statusMap[$task->status] ?? ['background:#ede9fe;color:#7B61FF', ucfirst($task->status)];
if ($task->isOverdue() && !$isTerminal) { $statusCss = 'background:#fee2e2;color:#dc2626'; $statusLabel = 'Overdue'; }

$priorityMap = [
    'urgent' => ['background:#fee2e2;color:#dc2626',  'Urgent'],
    'high'   => ['background:#fff7ed;color:#ea580c',  'High'],
    'medium' => ['background:#fffbeb;color:#d97706',  'Medium'],
    'low'    => ['background:#f3f4f6;color:#6b7280',  'Low'],
];
[$priCss, $priLabel] = $priorityMap[$task->priority ?? 'medium'] ?? ['background:#f3f4f6;color:#6b7280', ucfirst($task->priority ?? 'medium')];

// ── Activity timeline config ──────────────────────────────────────────────
$actIcons = [
    'task_created'                => ['Task Created',                '#7B61FF', '#ede9fe', '+'],
    'task_completed'              => ['Task Completed',              '#15803d', '#dcfce7', '✓'],
    'task_assigned'               => ['Task Assigned',               '#2563eb', '#dbeafe', '→'],
    'task_updated'                => ['Task Updated',                '#6b7280', '#f3f4f6', '↻'],
    'completion_response_queued'  => ['Response Email Queued',       '#d97706', '#fffbeb', '✉'],
    'completion_response_sent'    => ['Response Email Sent',         '#15803d', '#dcfce7', '✉'],
    'completion_response_failed'  => ['Response Email Failed',       '#dc2626', '#fee2e2', '✕'],
];
@endphp

<style>
@keyframes rb-spin { to { transform:rotate(360deg); } }
@media (max-width:1023px) {
    .task-detail-grid { grid-template-columns:1fr !important; }
    .task-sidebar      { order:-1; }
}
@media (max-width:639px) {
    .task-hdr-actions { flex-direction:column !important; }
    .task-hdr-actions > * { width:100%; justify-content:center; }
}

/* ── Task completion modal overlay ────────────────────────────────────── */
.rb-complete-overlay {
    display: flex;
    align-items: center;
    justify-content: center;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15,15,35,.58);
    z-index: 9999;
    padding: 16px;
    box-sizing: border-box;
}

/* ── Modal footer action bar ───────────────────────────────────────────
   Desktop: Cancel | spacer | [Mark as Done Only] [Send Reply & Mark as Done]
   Mobile: stack buttons full-width, primary action on top                */
.rb-modal-footer {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 14px 24px 20px;
    border-top: 1px solid #f3f4f6;
    background: white;
    border-radius: 0 0 20px 20px;
    position: sticky;
    bottom: 0;
}
.rb-modal-footer-spacer { flex: 1; }
.rb-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    height: 42px;
    padding: 0 18px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    flex-shrink: 0;
    transition: opacity .15s, box-shadow .15s;
    font-family: inherit;
    line-height: 1;
}
.rb-btn:disabled { opacity: .45; cursor: not-allowed; }
.rb-btn-ghost {
    border: 1.5px solid #e5e7eb;
    background: white;
    color: #374151;
}
.rb-btn-ghost:not(:disabled):hover { background: #f9fafb; }
.rb-btn-secondary {
    border: 1.5px solid #7B61FF;
    background: white;
    color: #7B61FF;
}
.rb-btn-secondary:not(:disabled):hover { background: #f5f3ff; }
.rb-btn-primary {
    border: none;
    background: linear-gradient(135deg,#7B61FF,#5b4cdb);
    color: white;
    box-shadow: 0 4px 14px rgba(123,97,255,.28);
}
.rb-btn-primary:not(:disabled):hover { box-shadow: 0 6px 20px rgba(123,97,255,.4); }
@media (max-width: 540px) {
    .rb-modal-footer {
        flex-direction: column-reverse;
        align-items: stretch;
        gap: 8px;
        padding: 12px 20px 20px;
    }
    .rb-modal-footer-spacer { display: none; }
    .rb-btn { width: 100%; height: 46px; font-size: 14px; }
    .rb-btn-cancel-mobile { order: 10; background: none; border: none; color: #9ca3af; height: 36px; }
}
</style>

<div x-data="taskDetail('{{ $task->id }}', '{{ $tenant->id }}', {{ $canComplete ? 'true' : 'false' }}, {{ $completionEmailEnabled ? 'true' : 'false' }}, {{ $canAssignToSelf ? 'true' : 'false' }}, {{ $isCurrentAssignee ? 'true' : 'false' }})">

{{-- ══════════════════════════════════════════════════════════════════════ --}}
{{-- PAGE HEADER                                                            --}}
{{-- ══════════════════════════════════════════════════════════════════════ --}}
<div style="margin-bottom:20px">
    <nav style="display:flex;align-items:center;gap:5px;font-size:12px;color:#9ca3af;margin-bottom:10px">
        <a href="{{ route('tenant.tasks', $tenant->id) }}" style="color:#9ca3af;text-decoration:none" onmouseover="this.style.color='#7B61FF'" onmouseout="this.style.color='#9ca3af'">Tasks</a>
        <svg style="width:10px;height:10px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span style="color:#1E1B4B;font-weight:600;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:inline-block;vertical-align:middle">{{ Str::limit($task->title, 30) }}</span>
    </nav>

    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div>
            {{-- Badges --}}
            <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-bottom:8px">
                <span style="font-size:11px;font-weight:700;padding:3px 10px;border-radius:9999px;{{ $statusCss }}">{{ $statusLabel }}</span>
                @if($isRequestTask)
                <span style="font-size:11px;font-weight:600;padding:3px 10px;border-radius:9999px;background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0">Request Form</span>
                @elseif($task->category)
                <span style="font-size:11px;font-weight:600;padding:3px 10px;border-radius:9999px;background:#f3f4f6;color:#6b7280;text-transform:capitalize">{{ str_replace('_',' ',$task->category) }}</span>
                @endif
                <span style="font-size:11px;font-weight:700;padding:3px 10px;border-radius:9999px;{{ $priCss }}">{{ $priLabel }}</span>
                @if($task->isOverdue() && !$isTerminal)
                <span style="font-size:11px;font-weight:700;padding:3px 10px;border-radius:9999px;background:#fee2e2;color:#dc2626">Overdue</span>
                @endif
            </div>
            <h1 style="font-size:19px;font-weight:700;color:#1E1B4B;margin:0 0 4px;line-height:1.3">{{ $task->title }}</h1>
            <p style="font-size:12px;color:#9ca3af;margin:0">
                @if($isRequestTask && $source)
                From <span style="color:#7B61FF;font-weight:600">{{ $source->form->title ?? 'Request Form' }}</span>
                @if($source->submitted_at) · Submitted {{ $source->submitted_at->format('M j, Y g:i A') }}@endif
                @else
                Created {{ $task->created_at->format('M j, Y') }}
                @endif
                @if($task->due_at) · Due <span style="color:{{ $task->isOverdue() ? '#dc2626' : '#6b7280' }};font-weight:{{ $task->isOverdue() ? '700' : '400' }}">{{ $task->due_at->format('M j, Y') }}</span>@endif
            </p>
        </div>

        {{-- Primary actions --}}
        <div class="task-hdr-actions" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;flex-shrink:0">
            @if($canComplete && !$isTerminal)
            <button @click="showCompleteModal = true"
                    style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:10px;background:linear-gradient(135deg,#7B61FF,#5b4cdb);color:white;border:none;font-size:13px;font-weight:600;cursor:pointer;box-shadow:0 3px 12px rgba(123,97,255,.28)">
                <svg style="width:14px;height:14px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Mark as Done
            </button>
            @elseif($task->status === 'completed')
            <span style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:10px;background:#dcfce7;color:#15803d;font-size:13px;font-weight:600;border:1.5px solid #86efac">
                <svg style="width:14px;height:14px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Completed
            </span>
            @endif
            @if($canAssignToSelf && !$isCurrentAssignee && !$isTerminal)
            <button @click="assignToSelf()" :disabled="assigning"
                    style="display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:10px;background:#ede9fe;color:#7B61FF;border:1.5px solid #c4b5fd;font-size:13px;font-weight:600;cursor:pointer"
                    :style="assigning ? 'opacity:.6;cursor:not-allowed' : ''"
                    x-text="assigning ? 'Assigning…' : '{{ $task->assigned_to_id ? 'Reassign to me' : 'Claim task' }}'">
            </button>
            @endif
            <a href="{{ route('tenant.tasks', $tenant->id) }}"
               style="display:inline-flex;align-items:center;gap:5px;padding:9px 14px;border-radius:10px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:13px;font-weight:600;text-decoration:none">
                <svg style="width:12px;height:12px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                All Tasks
            </a>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════ --}}
{{-- TWO-COLUMN GRID                                                        --}}
{{-- ══════════════════════════════════════════════════════════════════════ --}}
<div class="task-detail-grid" style="display:grid;grid-template-columns:1fr 296px;gap:20px;align-items:start">

    {{-- ══ LEFT: MAIN CONTENT ════════════════════════════════════════════ --}}
    <div style="display:flex;flex-direction:column;gap:18px;min-width:0">

        {{-- ── Request Details (request-form tasks only) ────────────────── --}}
        @if($isRequestTask && ($source || $task->requestor_email || $task->requestor_name))
        <div class="card">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px">
                <div style="width:30px;height:30px;border-radius:8px;background:#f0fdf4;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:1px solid #bbf7d0">
                    <svg style="width:14px;height:14px;color:#15803d" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
                <div>
                    <h3 style="font-size:14px;font-weight:700;color:#1E1B4B;margin:0">Request Details</h3>
                    <p style="font-size:11px;color:#9ca3af;margin:0">Information submitted through the public request form</p>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:14px">

                {{-- From --}}
                @if($task->requestor_name || $task->requestor_email)
                <div style="grid-column:1/-1">
                    <p style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.07em;margin:0 0 4px">From</p>
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                        @if($task->requestor_name)
                        <span style="font-size:13px;font-weight:600;color:#1E1B4B">{{ $task->requestor_name }}</span>
                        @endif
                        @if($task->requestor_email)
                        <a href="mailto:{{ $task->requestor_email }}"
                           style="font-size:12px;color:#7B61FF;text-decoration:none;word-break:break-all"
                           aria-label="Email {{ $task->requestor_email }}">{{ $task->requestor_email }}</a>
                        @endif
                    </div>
                </div>
                @endif

                {{-- Request For --}}
                @if($source?->request_for)
                <div>
                    <p style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.07em;margin:0 0 4px">Request For</p>
                    <p style="font-size:13px;font-weight:600;color:#1E1B4B;margin:0">{{ $source->request_for }}</p>
                </div>
                @endif

                {{-- Submitted --}}
                @if($source?->submitted_at)
                <div>
                    <p style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.07em;margin:0 0 4px">Submitted</p>
                    <p style="font-size:13px;font-weight:500;color:#374151;margin:0">{{ $source->submitted_at->format('M j, Y g:i A') }}</p>
                </div>
                @endif

                {{-- Notes (full width) --}}
                @if($source?->notes)
                <div style="grid-column:1/-1">
                    <p style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.07em;margin:0 0 6px">Notes</p>
                    <div style="font-size:13px;color:#374151;line-height:1.75;background:#fafafa;border:1px solid #f3f4f6;border-radius:10px;padding:12px 14px;white-space:pre-line;word-break:break-word">{!! rb_linkify($source->notes) !!}</div>
                </div>
                @endif

                {{-- Submitted payload (fields from the public form) --}}
                @if($source && !empty($source->payload))
                @php $payload = is_array($source->payload) ? $source->payload : json_decode($source->payload, true); @endphp
                @if(is_array($payload))
                @foreach($payload as $fKey => $fVal)
                @php
                $skipKeys = ['name','email','notes','request_for'];
                if (in_array(strtolower($fKey), $skipKeys)) continue;
                $isLong = is_string($fVal) && mb_strlen($fVal) > 50;
                @endphp
                <div style="{{ $isLong ? 'grid-column:1/-1' : '' }}">
                    <p style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.07em;margin:0 0 4px">{{ Str::title(str_replace('_',' ',$fKey)) }}</p>
                    @if(is_array($fVal))
                    <div style="display:flex;gap:5px;flex-wrap:wrap">
                        @foreach($fVal as $v)<span style="font-size:11px;font-weight:600;background:#ede9fe;color:#7B61FF;padding:2px 8px;border-radius:9999px">{{ $v }}</span>@endforeach
                    </div>
                    @else
                    <p style="font-size:13px;color:#374151;font-weight:500;margin:0;white-space:pre-line;word-break:break-word">{!! rb_linkify((string) $fVal) !!}</p>
                    @endif
                </div>
                @endforeach
                @endif
                @endif

            </div>

            {{-- View Response link --}}
            @if($source && $source->form)
            <div style="margin-top:14px;padding-top:12px;border-top:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
                <span style="font-size:11px;color:#9ca3af">Form: <a href="{{ route('tenant.request-forms.edit', [$tenant->id, $source->form->id]) }}" style="color:#7B61FF;text-decoration:none;font-weight:600">{{ $source->form->title }}</a></span>
                <a href="{{ route('tenant.request-forms.submissions.show', [$tenant->id, $source->form->id, $source->id]) }}"
                   style="display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:8px;background:#ede9fe;color:#7B61FF;font-size:12px;font-weight:600;text-decoration:none">
                    <svg style="width:12px;height:12px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    View Full Response
                </a>
            </div>
            @endif
        </div>
        @endif

        {{-- ── Task Description / Notes ───────────────────────────────── --}}
        @if($task->description)
        <div class="card">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
                <div style="width:30px;height:30px;border-radius:8px;background:#ede9fe;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <svg style="width:14px;height:14px;color:#7B61FF" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 17h.01"/></svg>
                </div>
                <h3 style="font-size:14px;font-weight:700;color:#1E1B4B;margin:0">Task Description</h3>
            </div>
            <div style="font-size:13px;color:#374151;line-height:1.75;white-space:pre-line;word-break:break-word">{!! rb_linkify($task->description) !!}</div>
        </div>
        @endif

        {{-- ── Response Emails ────────────────────────────────────────── --}}
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:16px">
                <div style="display:flex;align-items:center;gap:10px">
                    <div style="width:30px;height:30px;border-radius:8px;background:#dbeafe;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <svg style="width:14px;height:14px;color:#2563eb" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </div>
                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:#1E1B4B;margin:0">Response Emails</h3>
                        <p style="font-size:11px;color:#9ca3af;margin:0">Replies sent to the original requester</p>
                    </div>
                </div>
                @if($canComplete && !$isTerminal && $task->requestor_email && $completionEmailEnabled)
                <button @click="showCompleteModal = true"
                        style="display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:8px;background:#ede9fe;color:#7B61FF;border:none;font-size:12px;font-weight:600;cursor:pointer">
                    <svg style="width:12px;height:12px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    Send Reply
                </button>
                @endif
            </div>

            @if($task->completionResponses->isEmpty())
            <div style="text-align:center;padding:20px 12px">
                <div style="width:40px;height:40px;border-radius:12px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;margin:0 auto 10px">
                    <svg style="width:18px;height:18px;color:#d1d5db" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <p style="font-size:13px;font-weight:600;color:#6b7280;margin:0 0 4px">No response email sent yet</p>
                @if($task->requestor_email)
                <p style="font-size:11px;color:#9ca3af;margin:0">Complete the task to send an optional reply to {{ $task->requestor_email }}.</p>
                @else
                <p style="font-size:11px;color:#9ca3af;margin:0">No requester email is linked to this task.</p>
                @endif
            </div>
            @else
            <div style="display:flex;flex-direction:column;gap:10px">
                @foreach($task->completionResponses as $resp)
                @php
                [$rBg, $rClr, $rLabel, $rHint] = match($resp->status) {
                    'sent'    => ['#dcfce7','#15803d','Sent', null],
                    'failed'  => ['#fee2e2','#dc2626','Failed', 'This email could not be sent.'],
                    'queued'  => ['#fffbeb','#d97706','Queued', 'Waiting in the email queue.'],
                    default   => ['#f3f4f6','#6b7280', ucfirst($resp->status), null],
                };
                @endphp
                <div style="padding:12px 14px;background:#f9fafb;border-radius:12px;border:1px solid #f3f4f6">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:6px">
                        <div style="flex:1;min-width:0">
                            <p style="font-size:13px;font-weight:600;color:#1E1B4B;margin:0;word-break:break-word">{{ $resp->subject }}</p>
                            <p style="font-size:11px;color:#9ca3af;margin:3px 0 0">
                                To: <span style="color:#374151">{{ $resp->recipient_email }}</span>
                                @if($resp->sender_name) · From <span style="color:#374151">{{ $resp->sender_name }}</span>@endif
                                · {{ $resp->created_at->format('M j, Y g:i A') }}
                            </p>
                        </div>
                        <span style="font-size:10px;font-weight:700;padding:2px 9px;border-radius:9999px;flex-shrink:0;white-space:nowrap;background:{{ $rBg }};color:{{ $rClr }}">{{ $rLabel }}</span>
                    </div>
                    @if($rHint)
                    <p style="font-size:11px;color:{{ $rClr }};margin:4px 0 0;padding:5px 8px;background:{{ $rBg }};border-radius:6px;font-weight:500">{{ $rHint }}</p>
                    @endif
                    @if(!empty($resp->attachment_paths))
                    <p style="font-size:11px;color:#9ca3af;margin:6px 0 0;display:flex;align-items:center;gap:4px">
                        <svg style="width:11px;height:11px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                        {{ count($resp->attachment_paths) }} attachment{{ count($resp->attachment_paths) !== 1 ? 's' : '' }}
                    </p>
                    @endif
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- ── Activity Timeline ──────────────────────────────────────── --}}
        <div class="card">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px">
                <div style="width:30px;height:30px;border-radius:8px;background:#dbeafe;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <svg style="width:14px;height:14px;color:#2563eb" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h3 style="font-size:14px;font-weight:700;color:#1E1B4B;margin:0">Activity Timeline</h3>
            </div>

            @if($task->activities->isEmpty())
            <div style="text-align:center;padding:24px 0">
                <div style="width:40px;height:40px;border-radius:12px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;margin:0 auto 10px">
                    <svg style="width:18px;height:18px;color:#d1d5db" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p style="font-size:13px;color:#9ca3af;font-weight:500;margin:0">No activity yet</p>
                <p style="font-size:11px;color:#d1d5db;margin:3px 0 0">Events will appear here as the task progresses.</p>
            </div>
            @else
            <div style="position:relative;padding-left:22px">
                <div style="position:absolute;left:13px;top:14px;bottom:8px;width:2px;background:linear-gradient(to bottom,#e9d5ff,#f3f4f6);border-radius:9999px" aria-hidden="true"></div>
                @foreach($task->activities as $activity)
                @php
                [$evtTitle, $evtColor, $evtBg, $evtIcon] = $actIcons[$activity->action_type ?? '']
                    ?? [Str::title(str_replace('_',' ',$activity->action_type ?? 'Event')), '#6b7280', '#f3f4f6', '·'];
                @endphp
                <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:18px;position:relative">
                    <div style="width:28px;height:28px;border-radius:9999px;background:{{ $evtBg }};border:2px solid {{ $evtColor }};display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:11px;font-weight:700;color:{{ $evtColor }};position:absolute;left:-36px;z-index:1" aria-hidden="true">{{ $evtIcon }}</div>
                    <div style="padding-top:3px;flex:1;min-width:0">
                        <p style="font-size:13px;font-weight:700;color:#1E1B4B;margin:0">{{ $evtTitle }}</p>
                        @if($activity->actor_name)
                        <p style="font-size:12px;color:#6b7280;margin:2px 0 0">by {{ $activity->actor_name }}</p>
                        @endif
                        @if(!empty($activity->new_values['assigned_to_name']))
                        <p style="font-size:11px;color:#9ca3af;margin:2px 0 0">Assigned to {{ $activity->new_values['assigned_to_name'] }}</p>
                        @endif
                        <p style="font-size:11px;color:#d1d5db;margin:3px 0 0">{{ \Carbon\Carbon::parse($activity->created_at)->format('M j, Y \a\t g:i A') }}</p>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

    </div>{{-- END LEFT --}}

    {{-- ══ RIGHT: SIDEBAR ════════════════════════════════════════════════ --}}
    <div class="task-sidebar" style="display:flex;flex-direction:column;gap:16px">

        {{-- ── Assigned To ─────────────────────────────────────────────── --}}
        <div class="card" style="padding:18px">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;gap:8px">
                <h3 style="font-size:13px;font-weight:700;color:#1E1B4B;margin:0">Assigned To</h3>
                @if($canAssignToSelf && !$isCurrentAssignee && !$isTerminal)
                <button @click="assignToSelf()" :disabled="assigning"
                        style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:7px;background:#ede9fe;color:#7B61FF;border:none;font-size:11px;font-weight:700;cursor:pointer"
                        :style="assigning ? 'opacity:.6;cursor:not-allowed' : ''"
                        x-text="assigning ? '…' : '{{ $task->assigned_to_id ? 'Reassign' : 'Claim' }}'"></button>
                @endif
            </div>

            @if($task->assigned_to_id)
            @php
            $nameParts  = preg_split('/\s+/', trim($assigneeName ?? 'U'), 2);
            $initials   = strtoupper(substr($nameParts[0] ?? 'U', 0, 1)) . strtoupper(substr($nameParts[1] ?? '', 0, 1));
            // $assigneeRole is pre-resolved by the controller — no extra DB query needed
            @endphp
            <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:#f9fafb;border-radius:10px">
                <div style="width:36px;height:36px;border-radius:9999px;background:{{ $isCurrentAssignee ? 'linear-gradient(135deg,#7B61FF,#5b4cdb)' : '#ede9fe' }};color:{{ $isCurrentAssignee ? 'white' : '#7B61FF' }};display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0">{{ $initials }}</div>
                <div style="flex:1;min-width:0">
                    <p style="font-size:13px;font-weight:600;color:#1E1B4B;margin:0">
                        {{ $assigneeName ?? 'Unknown' }}
                        @if($isCurrentAssignee)<span style="font-size:10px;font-weight:700;background:#ede9fe;color:#7B61FF;padding:1px 6px;border-radius:9999px;margin-left:5px">You</span>@endif
                    </p>
                    <p style="font-size:11px;color:#9ca3af;margin:1px 0 0">{{ ucfirst($assigneeRole ?? 'Team Member') }}</p>
                </div>
                @if($task->completed_at)
                <span style="font-size:10px;font-weight:700;background:#dcfce7;color:#15803d;padding:2px 7px;border-radius:9999px;flex-shrink:0">Done</span>
                @endif
            </div>
            @else
            <div style="padding:10px 12px;background:#fffbeb;border-radius:10px;border:1.5px dashed #fbbf24;display:flex;align-items:center;gap:8px">
                <svg style="width:15px;height:15px;color:#d97706;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <div style="flex:1">
                    <p style="font-size:12px;font-weight:600;color:#d97706;margin:0">Unassigned</p>
                    @if($canAssignToSelf && !$isTerminal)
                    <button @click="assignToSelf()" :disabled="assigning" style="font-size:11px;color:#7B61FF;background:none;border:none;cursor:pointer;padding:0;margin-top:3px;font-weight:600" x-text="assigning ? 'Assigning…' : 'Claim this task'"></button>
                    @endif
                </div>
            </div>
            @endif
        </div>

        {{-- ── Task Metadata ────────────────────────────────────────────── --}}
        <div class="card" style="padding:18px">
            <h3 style="font-size:13px;font-weight:700;color:#1E1B4B;margin:0 0 12px">Task Info</h3>
            <div style="display:flex;flex-direction:column;gap:10px">
                <div>
                    <p style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;margin:0 0 2px">Status</p>
                    <span style="font-size:12px;font-weight:700;padding:2px 9px;border-radius:9999px;{{ $statusCss }}">{{ $statusLabel }}</span>
                </div>
                <div>
                    <p style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;margin:0 0 2px">Priority</p>
                    <span style="font-size:12px;font-weight:700;padding:2px 9px;border-radius:9999px;{{ $priCss }}">{{ $priLabel }}</span>
                </div>
                <div>
                    <p style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;margin:0 0 2px">Created</p>
                    <p style="font-size:12px;color:#374151;font-weight:500;margin:0">{{ $task->created_at->format('M j, Y g:i A') }}</p>
                </div>
                @if($task->due_at)
                <div>
                    <p style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;margin:0 0 2px">Due Date</p>
                    <p style="font-size:12px;font-weight:{{ $task->isOverdue() ? '700' : '500' }};color:{{ $task->isOverdue() ? '#dc2626' : '#374151' }};margin:0">
                        {{ $task->due_at->format('M j, Y') }}{{ $task->isOverdue() && !$isTerminal ? ' · Overdue' : '' }}
                    </p>
                </div>
                @endif
                @if($task->completed_at)
                <div>
                    <p style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;margin:0 0 2px">Completed</p>
                    <p style="font-size:12px;color:#15803d;font-weight:600;margin:0">{{ $task->completed_at->format('M j, Y g:i A') }}</p>
                </div>
                @endif
                <div>
                    <p style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;margin:0 0 2px">Source</p>
                    <p style="font-size:12px;color:#374151;font-weight:500;margin:0">{{ $isRequestTask ? 'Request Form' : 'Manual Task' }}</p>
                </div>
            </div>
        </div>

        {{-- ── Quick Actions ────────────────────────────────────────────── --}}
        <div class="card" style="padding:18px">
            <h3 style="font-size:13px;font-weight:700;color:#1E1B4B;margin:0 0 12px">Quick Actions</h3>
            <div style="display:flex;flex-direction:column;gap:7px">

                @if($canComplete && !$isTerminal)
                <button @click="showCompleteModal = true"
                        style="display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:9px;background:linear-gradient(135deg,#7B61FF,#5b4cdb);color:white;border:none;font-size:12px;font-weight:600;cursor:pointer;box-shadow:0 2px 8px rgba(123,97,255,.22)">
                    <svg style="width:13px;height:13px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Mark as Done
                </button>
                @endif

                @if($task->requestor_email && $completionEmailEnabled && !$isTerminal && $canComplete)
                <button @click="showCompleteModal = true"
                        style="display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:9px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:12px;font-weight:600;cursor:pointer"
                        onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='white'">
                    <svg style="width:13px;height:13px;color:#6b7280;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    Send Reply to Requester
                </button>
                @elseif(!$task->requestor_email && $isRequestTask)
                <div style="padding:8px 12px;border-radius:9px;background:#f9fafb;border:1px solid #f3f4f6">
                    <p style="font-size:11px;color:#9ca3af;margin:0">No requester email — reply unavailable.</p>
                </div>
                @endif

                @if($isRequestTask && $source && $source->form)
                <a href="{{ route('tenant.request-forms.submissions.show', [$tenant->id, $source->form->id, $source->id]) }}"
                   style="display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:9px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:12px;font-weight:600;text-decoration:none"
                   onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='white'">
                    <svg style="width:13px;height:13px;color:#6b7280;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    View Full Response
                </a>
                <a href="{{ route('tenant.request-forms.edit', [$tenant->id, $source->form->id]) }}"
                   style="display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:9px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:12px;font-weight:600;text-decoration:none"
                   onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='white'">
                    <svg style="width:13px;height:13px;color:#6b7280;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    View Request Form
                </a>
                @endif

                <div style="height:1px;background:#f3f4f6;margin:2px 0" aria-hidden="true"></div>

                <a href="{{ route('tenant.tasks', $tenant->id) }}"
                   style="display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:9px;border:1.5px solid #e5e7eb;background:white;color:#6b7280;font-size:12px;font-weight:600;text-decoration:none"
                   onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='white'">
                    <svg style="width:13px;height:13px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    All Tasks
                </a>
            </div>
        </div>

        {{-- ── Requester Info (sidebar) ─────────────────────────────────── --}}
        @if($task->requestor_email || $task->requestor_name)
        <div class="card" style="padding:18px">
            <h3 style="font-size:13px;font-weight:700;color:#1E1B4B;margin:0 0 10px">Requester</h3>
            @if($task->requestor_name)
            <p style="font-size:13px;font-weight:600;color:#1E1B4B;margin:0 0 2px">{{ $task->requestor_name }}</p>
            @endif
            @if($task->requestor_email)
            <a href="mailto:{{ $task->requestor_email }}"
               style="font-size:12px;color:#7B61FF;text-decoration:none;word-break:break-all"
               aria-label="Email {{ $task->requestor_email }}">{{ $task->requestor_email }}</a>
            @endif
        </div>
        @endif

    </div>{{-- END SIDEBAR --}}

</div>{{-- END GRID --}}

{{-- ══════════════════════════════════════════════════════════════════════ --}}
{{-- MARK COMPLETE MODAL                                                     --}}
{{-- ══════════════════════════════════════════════════════════════════════ --}}
@if($canComplete && !$isTerminal)
@php
    $hasRequester = !empty($resolvedRequesterEmail);
    // Default email subject: use request form title if this task is from a form
    if ($isRequestTask && $source && isset($source->form->title)) {
        $defaultSubject = 'Re: ' . $source->form->title
            . ($source->request_for ? ' — ' . $source->request_for : '');
    } else {
        $defaultSubject = 'Re: ' . $task->title;
    }
@endphp
<script>window.__taskDefaultSubject = @json($defaultSubject);</script>
<div x-show="showCompleteModal" x-cloak
     class="rb-complete-overlay"
     @keydown.escape.window="if(!completing){ showCompleteModal = false; resetModal(); }"
     @click.self="if(!completing){ showCompleteModal = false; resetModal(); }"
     role="dialog" aria-modal="true" aria-labelledby="complete-modal-title">

    <div style="background:white;border-radius:20px;width:100%;max-width:600px;max-height:92vh;overflow-y:auto;box-shadow:0 25px 60px rgba(0,0,0,.22);display:flex;flex-direction:column" @click.stop>

        {{-- Sticky header --}}
        <div style="padding:20px 24px 16px;border-bottom:1px solid #f3f4f6;display:flex;align-items:flex-start;justify-content:space-between;gap:12px;position:sticky;top:0;background:white;z-index:1;border-radius:20px 20px 0 0">
            <div style="display:flex;align-items:center;gap:10px;min-width:0">
                <div style="width:36px;height:36px;border-radius:10px;background:#ede9fe;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <svg style="width:17px;height:17px;color:#7B61FF" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div style="min-width:0">
                    <h3 id="complete-modal-title" style="font-size:16px;font-weight:700;color:#1E1B4B;margin:0;line-height:1.3">Mark Task as Done</h3>
                    <p style="font-size:11px;color:#9ca3af;margin:2px 0 0">Add a completion note and optionally reply to the requester.</p>
                </div>
            </div>
            <button @click="if(!completing){ showCompleteModal = false; resetModal(); }"
                    aria-label="Close dialog"
                    style="width:32px;height:32px;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:none;border:1px solid #e5e7eb;cursor:pointer;color:#6b7280;border-radius:8px"
                    onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='none'">
                <svg style="width:15px;height:15px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- ── Success overlay (replaces form content after action) ── --}}
        <div x-show="successState" style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:48px 32px;text-align:center;gap:16px">
            <div style="width:60px;height:60px;border-radius:9999px;background:#dcfce7;display:flex;align-items:center;justify-content:center;margin-bottom:4px">
                <svg style="width:28px;height:28px;color:#15803d" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <p style="font-size:18px;font-weight:700;color:#1E1B4B;margin:0" x-text="successMessage"></p>
            <p style="font-size:13px;color:#9ca3af;margin:0">Refreshing page…</p>
        </div>

        {{-- ── Form content (hidden after success) ── --}}
        <div x-show="!successState" style="padding:20px 24px;display:flex;flex-direction:column;gap:16px;flex:1">

            {{-- Requester card (server-resolved, always read-only) --}}
            @if($hasRequester)
            <div style="display:flex;align-items:center;gap:10px;padding:10px 13px;background:#f0f9ff;border-radius:10px;border:1px solid #bae6fd">
                <div style="width:32px;height:32px;border-radius:8px;background:#bae6fd;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <svg style="width:14px;height:14px;color:#0284c7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <div style="flex:1;min-width:0">
                    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                        @if($resolvedRequesterName)
                        <span style="font-size:13px;font-weight:600;color:#0c4a6e">{{ $resolvedRequesterName }}</span>
                        <span style="font-size:10px;color:#9ca3af">·</span>
                        @endif
                        <span style="font-size:12px;color:#075985;word-break:break-all">{{ $resolvedRequesterEmail }}</span>
                    </div>
                    <p style="font-size:11px;color:#0284c7;margin:2px 0 0">Requester — address is read-only and cannot be changed.</p>
                </div>
                <span style="font-size:10px;font-weight:600;background:#e0f2fe;color:#0369a1;padding:2px 8px;border-radius:9999px;flex-shrink:0;white-space:nowrap">Read-only</span>
            </div>
            @else
            <div style="display:flex;align-items:center;gap:8px;padding:10px 13px;background:#fffbeb;border-radius:10px;border:1px solid #fde68a">
                <svg style="width:14px;height:14px;color:#d97706;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <p style="font-size:12px;color:#92400e;margin:0">No requester email found — you can still mark the task done, but a reply email cannot be sent.</p>
            </div>
            @endif

            {{-- Email Subject — always visible when there is a requester --}}
            @if($hasRequester)
            <div>
                <label for="task-resp-subject" style="display:block;font-size:11px;font-weight:700;color:#6b7280;letter-spacing:.06em;text-transform:uppercase;margin-bottom:6px">
                    Email Subject <span style="color:#9ca3af;font-weight:400">(required if sending reply)</span>
                </label>
                <input id="task-resp-subject" type="text" x-model="responseSubject" maxlength="150"
                       style="width:100%;padding:10px 13px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;color:#1E1B4B;background:white;outline:none;box-sizing:border-box;font-family:inherit"
                       @focus="this.style.borderColor='#7B61FF'" @blur="this.style.borderColor='#e5e7eb'">
            </div>
            @endif

            {{-- Message / Completion Notes --}}
            <div>
                <div style="display:flex;align-items:baseline;justify-content:space-between;gap:8px;margin-bottom:6px">
                    <label for="task-resp-body" style="font-size:11px;font-weight:700;color:#6b7280;letter-spacing:.06em;text-transform:uppercase">
                        @if($hasRequester)Message / Completion Notes <span style="font-weight:400;color:#9ca3af">(required if sending reply)</span>
                        @else
                        Completion Notes <span style="font-weight:400;color:#9ca3af">(optional)</span>
                        @endif
                    </label>
                    @if($hasRequester)
                    <span style="font-size:10px;color:#7B61FF;font-weight:500;white-space:nowrap;flex-shrink:0">Paste links — they'll be clickable</span>
                    @endif
                </div>
                <textarea id="task-resp-body" x-model="responseBody" rows="5" maxlength="20000"
                          placeholder="{{ $hasRequester ? 'Describe what was done. Any URLs you paste will be clickable in the email.' : 'Optional internal note about completion…' }}"
                          style="width:100%;padding:10px 13px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;color:#1E1B4B;background:white;outline:none;resize:vertical;box-sizing:border-box;font-family:inherit;line-height:1.6"
                          @focus="this.style.borderColor='#7B61FF'" @blur="this.style.borderColor='#e5e7eb'"></textarea>
            </div>

            {{-- Attachments --}}
            <div>
                <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;letter-spacing:.06em;text-transform:uppercase;margin-bottom:6px">
                    Attach Files <span style="font-weight:400;color:#9ca3af">(optional)</span>
                </label>
                <label style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;padding:16px;border:2px dashed #d1d5db;border-radius:12px;cursor:pointer;transition:border-color .2s,background .2s"
                       onmouseover="this.style.borderColor='#7B61FF';this.style.background='#f5f3ff'"
                       onmouseout="this.style.borderColor='#d1d5db';this.style.background='transparent'">
                    <svg style="width:22px;height:22px;color:#9ca3af" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                    <p style="font-size:12px;font-weight:600;color:#374151;margin:0">Click or drag files here</p>
                    <p style="font-size:11px;color:#9ca3af;margin:0">PDF, DOCX, XLSX, JPG, PNG · Max 50 MB · Max 5 files</p>
                    <input type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png,.webp,.txt"
                           style="display:none" @change="handleFiles($event)">
                </label>
                <template x-if="attachedFiles.length > 0">
                    <div style="margin-top:8px;display:flex;flex-direction:column;gap:5px">
                        <template x-for="(f, idx) in attachedFiles" :key="idx">
                            <div style="display:flex;align-items:center;gap:8px;padding:7px 11px;background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb">
                                <svg style="width:13px;height:13px;color:#7B61FF;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                <div style="flex:1;min-width:0">
                                    <p style="font-size:12px;font-weight:600;color:#1E1B4B;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="f.name"></p>
                                    <p style="font-size:10px;color:#9ca3af;margin:0" x-text="formatSize(f.size)"></p>
                                </div>
                                <button @click.prevent="removeFile(idx)" type="button" aria-label="Remove file"
                                        style="width:20px;height:20px;display:flex;align-items:center;justify-content:center;background:none;border:none;cursor:pointer;color:#9ca3af;border-radius:4px"
                                        onmouseover="this.style.color='#dc2626'" onmouseout="this.style.color='#9ca3af'">
                                    <svg style="width:12px;height:12px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </template>
                    </div>
                </template>
                <p x-show="fileError" x-text="fileError" style="font-size:11px;color:#dc2626;font-weight:600;margin-top:5px"></p>
            </div>

            {{-- Error --}}
            <div x-show="responseError" style="font-size:13px;color:#dc2626;font-weight:500;padding:10px 14px;background:#fef2f2;border-radius:10px;border:1px solid #fecaca;line-height:1.5" x-text="responseError" role="alert"></div>
        </div>

        {{-- ── Footer action bar ──────────────────────────────────────────── --}}
        {{-- NOTE: x-show is used on SVGs (not x-if/template) so icons stay      --}}
        {{-- inside the inline-flex button — template x-if breaks this layout.   --}}
        <div x-show="!successState" class="rb-modal-footer">

            {{-- Cancel — ghost, left on desktop / bottom on mobile --}}
            <button @click="if(!completing){ showCompleteModal = false; resetModal(); }"
                    :disabled="completing"
                    class="rb-btn rb-btn-ghost rb-btn-cancel-mobile"
                    aria-label="Cancel and close modal">
                Cancel
            </button>

            <span class="rb-modal-footer-spacer" aria-hidden="true"></span>

            {{-- Mark as Done Only — secondary purple, no email sent --}}
            <button @click="markDoneOnly()"
                    :disabled="completing"
                    class="rb-btn rb-btn-secondary"
                    :aria-busy="completing && !wantReply">
                {{-- Spinner: visible only while this button is processing --}}
                <svg x-show="completing && !wantReply"
                     style="width:15px;height:15px;flex-shrink:0;animation:rb-spin 1s linear infinite"
                     fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle style="opacity:.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path style="opacity:.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                {{-- Checkmark: visible when idle --}}
                <svg x-show="!completing || wantReply"
                     style="width:15px;height:15px;flex-shrink:0"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <span x-text="(completing && !wantReply) ? 'Marking Done…' : 'Mark as Done Only'"></span>
            </button>

            {{-- Send Reply & Mark as Done — primary purple, only when requester exists --}}
            @if($hasRequester)
            <button @click="sendReplyAndComplete()"
                    :disabled="completing"
                    class="rb-btn rb-btn-primary"
                    :aria-busy="completing && wantReply">
                {{-- Spinner: visible only while this button is processing --}}
                <svg x-show="completing && wantReply"
                     style="width:15px;height:15px;flex-shrink:0;animation:rb-spin 1s linear infinite"
                     fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle style="opacity:.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path style="opacity:.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                {{-- Envelope: visible when idle --}}
                <svg x-show="!completing || !wantReply"
                     style="width:15px;height:15px;flex-shrink:0"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                <span x-text="(completing && wantReply) ? 'Sending Reply…' : 'Send Reply & Mark as Done'"></span>
            </button>
            @endif
        </div>
    </div>
</div>
@endif

</div>{{-- END x-data --}}

@push('scripts')
<script>
function taskDetail(taskId, tenantId, canComplete, completionEmailEnabled, canAssignToSelf, isCurrentAssignee) {
    const csrf      = document.querySelector('meta[name=csrf-token]')?.content ?? '';
    const MAX_SIZE  = 50 * 1024 * 1024;
    const MAX_FILES = 5;

    const defaultSubject = window.__taskDefaultSubject || '';

    return {
        showCompleteModal: false,
        wantReply:      false,
        responseSubject: defaultSubject,
        responseBody:   '',
        responseError:  '',
        fileError:      '',
        successState:   false,
        successMessage: '',
        completing:     false,
        assigning:      false,
        attachedFiles:  [],

        resetModal() {
            this.wantReply       = false;
            this.responseSubject = defaultSubject;
            this.responseBody    = '';
            this.responseError   = '';
            this.fileError       = '';
            this.successState    = false;
            this.successMessage  = '';
            this.attachedFiles   = [];
        },

        showSuccess(msg) {
            this.successState   = true;
            this.successMessage = msg;
            setTimeout(() => window.location.reload(), 2000);
        },

        handleFiles(event) {
            this.fileError = '';
            const incoming = Array.from(event.target.files || []);
            const combined = [...this.attachedFiles, ...incoming];
            if (combined.length > MAX_FILES) { this.fileError = `Maximum ${MAX_FILES} files allowed.`; event.target.value = ''; return; }
            for (const f of incoming) {
                if (f.size > MAX_SIZE) { this.fileError = `"${f.name}" exceeds the 50 MB limit.`; event.target.value = ''; return; }
            }
            this.attachedFiles = combined;
            event.target.value = '';
        },

        removeFile(idx) { this.attachedFiles.splice(idx, 1); },

        formatSize(bytes) {
            return bytes >= 1048576 ? (bytes/1048576).toFixed(1)+' MB' : (bytes/1024).toFixed(0)+' KB';
        },

        async assignToSelf() {
            if (!canAssignToSelf || this.assigning) return;
            this.assigning = true;
            try {
                const res  = await fetch(`/tenant/${tenantId}/tasks/${taskId}/assign-to-me`, { method:'POST', credentials:'same-origin', headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'} });
                const data = await res.json();
                if (res.ok) {
                    window.dispatchEvent(new CustomEvent('show-toast', { detail:{ type:'success', message: data.message || 'Task assigned to you.' } }));
                    setTimeout(() => location.reload(), 900);
                } else {
                    window.dispatchEvent(new CustomEvent('show-toast', { detail:{ type:'error', message: data.error || 'Could not assign task.' } }));
                }
            } catch { window.dispatchEvent(new CustomEvent('show-toast', { detail:{ type:'error', message:'Network error. Please try again.' } })); }
            finally { this.assigning = false; }
        },

        // ── Mark as Done Only ────────────────────────────────────────────────
        async markDoneOnly() {
            if (this.completing) return;
            this.wantReply = false;
            this.responseError = '';
            this.completing = true;
            try {
                const res  = await fetch(`/tenant/${tenantId}/tasks/${taskId}/complete`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok) {
                    this.showSuccess('Task marked as done!');
                } else {
                    this.responseError = data.error || data.message || 'Could not complete task. Please try again.';
                    this.completing = false;
                }
            } catch {
                this.responseError = 'Network error. Please check your connection.';
                this.completing = false;
            }
        },

        // ── Send Reply & Mark as Done ────────────────────────────────────────
        async sendReplyAndComplete() {
            if (this.completing) return;
            this.wantReply = true;
            this.responseError = '';

            const subject = this.responseSubject.trim();
            const body    = this.responseBody.trim();

            if (!subject) {
                this.responseError = 'Please enter an email subject.';
                document.getElementById('task-resp-subject')?.focus();
                return;
            }
            if (!body) {
                this.responseError = 'Please enter a message to send to the requester.';
                document.getElementById('task-resp-body')?.focus();
                return;
            }
            if (this.fileError) return;

            this.completing = true;
            try {
                const fd = new FormData();
                fd.append('_token',           csrf);
                fd.append('subject',          subject);
                fd.append('body',             body);
                fd.append('send_email',       '1');
                fd.append('client_request_id','cr_' + Date.now() + '_' + Math.random().toString(36).slice(2));
                this.attachedFiles.forEach((f, i) => fd.append(`attachments[${i}]`, f, f.name));

                const res  = await fetch(`/tenant/${tenantId}/tasks/${taskId}/complete-with-response`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: fd,
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok) {
                    this.showSuccess(data.message || 'Task completed and reply sent!');
                } else {
                    // Extract from data.error, data.message, or Laravel validation errors
                    const errMsg = data.error || data.message
                        || (data.errors ? Object.values(data.errors).flat().join(' ') : null)
                        || 'Could not send reply. Please try again.';
                    this.responseError = errMsg;
                    this.completing = false;
                }
            } catch {
                this.responseError = 'Network error. Please check your connection and try again.';
                this.completing = false;
            }
        },
    };
}
</script>
@endpush
@endsection
