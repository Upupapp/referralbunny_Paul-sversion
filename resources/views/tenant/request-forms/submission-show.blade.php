@extends('layouts.app')
@section('title', 'Response — ' . $form->title)
@section('nav') @include('tenant._nav') @endsection

@section('content')
@php
// ── Derived response-level status ─────────────────────────────────────────
$taskStatuses = $submission->tasks->pluck('status');
[$statusLabel, $statusColor, $statusBg] = match(true) {
    $submission->status === 'failed'
        => ['Needs Review', '#dc2626', '#fee2e2'],
    $submission->tasks->isEmpty() && $submission->status !== 'tasks_created'
        => ['Pending', '#6b7280', '#f3f4f6'],
    $taskStatuses->isNotEmpty() && $taskStatuses->every(fn($s) => $s === 'completed')
        => ['Completed', '#15803d', '#dcfce7'],
    $taskStatuses->contains('in_progress')
        => ['In Progress', '#2563eb', '#dbeafe'],
    $submission->status === 'tasks_created'
        => ['Tasks Created', '#15803d', '#dcfce7'],
    default
        => [ucwords(str_replace('_', ' ', $submission->status)), '#6b7280', '#f3f4f6'],
};

$taskCount       = $submission->tasks->count();
$submitterLabel  = trim(($submission->submitter_name ?? '')) ?: 'Anonymous';

// ── Synthesise submission event for timeline ──────────────────────────────
$timelineSubmitEntry = (object)[
    'action_type' => 'response_submitted',
    'actor_name'  => $submitterLabel . ($submission->submitter_email ? ' <' . $submission->submitter_email . '>' : ''),
    'created_at'  => $submission->submitted_at ?? now(),
    'new_values'  => null,
    'metadata'    => null,
];
$allActivities = collect([$timelineSubmitEntry])
    ->concat($submission->tasks->flatMap(fn($t) => $t->activities ?? collect()))
    ->sortBy('created_at');
@endphp

<style>
@media (max-width: 1023px) {
    .rf-resp-grid { grid-template-columns: 1fr !important; }
    .rf-resp-sidebar { order: -1; }
}
@media (max-width: 639px) {
    .rf-answers-grid { grid-template-columns: 1fr !important; }
    .rf-header-actions { flex-direction: column !important; align-items: stretch !important; }
    .rf-header-actions a { justify-content: center; }
}
</style>

{{-- ════════════════════════════════════════════════════════════════════════ --}}
{{-- PAGE HEADER                                                              --}}
{{-- ════════════════════════════════════════════════════════════════════════ --}}
<div style="margin-bottom:24px">

    {{-- Breadcrumb --}}
    <nav aria-label="Breadcrumb" style="display:flex;align-items:center;gap:5px;font-size:12px;color:#9ca3af;margin-bottom:10px;flex-wrap:wrap">
        <a href="{{ route('tenant.request-forms', $tenant->id) }}"
           style="color:#9ca3af;text-decoration:none;transition:color .12s"
           onmouseover="this.style.color='#7B61FF'" onmouseout="this.style.color='#9ca3af'">Request Forms</a>
        <svg style="width:11px;height:11px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <a href="{{ route('tenant.request-forms.submissions', [$tenant->id, $form->id]) }}"
           style="color:#9ca3af;text-decoration:none;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:inline-block;vertical-align:middle;transition:color .12s"
           onmouseover="this.style.color='#7B61FF'" onmouseout="this.style.color='#9ca3af'"
           title="{{ $form->title }}">{{ Str::limit($form->title, 30) }}</a>
        <svg style="width:11px;height:11px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span style="color:#1E1B4B;font-weight:600">Response</span>
    </nav>

    {{-- Title + actions --}}
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <h1 style="font-size:20px;font-weight:700;color:#1E1B4B;margin:0">Request Response</h1>
                <span style="font-size:11px;font-weight:700;padding:3px 11px;border-radius:9999px;background:{{ $statusBg }};color:{{ $statusColor }};flex-shrink:0;white-space:nowrap">
                    {{ $statusLabel }}
                </span>
            </div>
            <p style="font-size:13px;color:#9ca3af;margin-top:3px">
                Submitted via <span style="color:#7B61FF;font-weight:600">{{ $form->title }}</span>
                @if($submission->submitted_at)
                · <span>{{ $submission->submitted_at->format('M j, Y') }}</span>
                @endif
            </p>
        </div>
        <div class="rf-header-actions" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <a href="{{ route('tenant.request-forms.edit', [$tenant->id, $form->id]) }}"
               style="display:inline-flex;align-items:center;gap:5px;padding:7px 14px;border-radius:9px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:12px;font-weight:600;text-decoration:none;white-space:nowrap"
               onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='white'">
                <svg style="width:12px;height:12px;color:#6b7280;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                View Form
            </a>
            <a href="{{ route('tenant.request-forms.submissions', [$tenant->id, $form->id]) }}"
               style="display:inline-flex;align-items:center;gap:5px;padding:7px 14px;border-radius:9px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:12px;font-weight:600;text-decoration:none;white-space:nowrap"
               onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='white'">
                <svg style="width:12px;height:12px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                All Responses
            </a>
        </div>
    </div>
</div>

{{-- ════════════════════════════════════════════════════════════════════════ --}}
{{-- TWO-COLUMN GRID                                                          --}}
{{-- ════════════════════════════════════════════════════════════════════════ --}}
<div class="rf-resp-grid" style="display:grid;grid-template-columns:1fr 320px;gap:24px;align-items:start">

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- LEFT: MAIN CONTENT                                                    --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div style="display:flex;flex-direction:column;gap:20px;min-width:0">

        {{-- ────────────────────────────────────────────────────────────────── --}}
        {{-- SUBMITTED ANSWERS                                                  --}}
        {{-- ────────────────────────────────────────────────────────────────── --}}
        <div class="card">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
                <div style="width:32px;height:32px;border-radius:9px;background:#ede9fe;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <svg style="width:15px;height:15px;color:#7B61FF" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
                <div>
                    <h3 style="font-size:14px;font-weight:700;color:#1E1B4B;margin:0">Submitted Answers</h3>
                    <p style="font-size:11px;color:#9ca3af;margin:0">Read-only response from the public form</p>
                </div>
            </div>

            @php
            $payload = $submission->payload ?? [];
            $fields  = $form->fields;
            @endphp

            @if($fields->isNotEmpty())
            {{-- Schema-driven answers --}}
            <div class="rf-answers-grid" style="display:grid;grid-template-columns:repeat(2,1fr);gap:14px">
                @foreach($fields as $field)
                @php
                $answer = $payload[$field->field_key] ?? $payload[$field->label] ?? null;
                if ($answer === null) {
                    $answer = match($field->field_key) {
                        'name'        => $submission->submitter_name,
                        'email'       => $submission->submitter_email,
                        'request_for' => $submission->request_for,
                        'notes'       => $submission->notes,
                        default       => null,
                    };
                }
                $isFullWidth = $field->field_type === 'textarea'
                    || in_array($field->field_type, ['multi_select', 'checkbox'])
                    || (is_array($answer) && count((array)$answer) > 2)
                    || (!is_array($answer) && mb_strlen((string)$answer) > 52);
                @endphp
                <div style="{{ $isFullWidth ? 'grid-column:1/-1' : '' }}">
                    <p style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.07em;margin:0 0 5px">
                        {{ $field->label }}@if($field->is_required)<span style="color:#ef4444;margin-left:2px" aria-hidden="true">*</span>@endif
                    </p>
                    @if($answer !== null && $answer !== '')
                        @if($field->field_type === 'email' && is_string($answer) && filter_var($answer, FILTER_VALIDATE_EMAIL))
                        <a href="mailto:{{ $answer }}"
                           style="font-size:13px;color:#7B61FF;font-weight:500;text-decoration:none;word-break:break-all"
                           aria-label="Send email to {{ $answer }}">{{ $answer }}</a>
                        @elseif($field->field_type === 'textarea' || (is_string($answer) && str_contains($answer, "\n")))
                        <div style="font-size:13px;color:#374151;line-height:1.75;background:#fafafa;border:1px solid #f3f4f6;border-radius:10px;padding:12px 14px;white-space:pre-line;word-break:break-word">{{ e($answer) }}</div>
                        @elseif(is_array($answer))
                        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:2px">
                            @foreach($answer as $v)
                            <span style="font-size:11px;font-weight:600;background:#ede9fe;color:#7B61FF;padding:3px 10px;border-radius:9999px">{{ $v }}</span>
                            @endforeach
                        </div>
                        @else
                        <p style="font-size:13px;color:#1E1B4B;font-weight:500;margin:0;word-break:break-word">{{ $answer }}</p>
                        @endif
                    @else
                    <p style="font-size:13px;color:#d1d5db;margin:0;font-style:italic">Not provided</p>
                    @endif
                </div>
                @endforeach
            </div>

            @elseif(!empty($payload))
            {{-- Raw payload (no field schema) --}}
            <div class="rf-answers-grid" style="display:grid;grid-template-columns:repeat(2,1fr);gap:14px">
                @foreach($payload as $key => $val)
                @php
                $isFullWidth = (is_string($val) && mb_strlen($val) > 52) || is_array($val);
                @endphp
                <div style="{{ $isFullWidth ? 'grid-column:1/-1' : '' }}">
                    <p style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.07em;margin:0 0 5px">{{ Str::title(str_replace('_', ' ', $key)) }}</p>
                    @if(is_array($val))
                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                        @foreach($val as $v)
                        <span style="font-size:11px;font-weight:600;background:#ede9fe;color:#7B61FF;padding:3px 10px;border-radius:9999px">{{ $v }}</span>
                        @endforeach
                    </div>
                    @else
                    <p style="font-size:13px;color:#1E1B4B;font-weight:500;margin:0;white-space:pre-line;word-break:break-word">{{ e($val) }}</p>
                    @endif
                </div>
                @endforeach
            </div>

            @else
            {{-- Legacy column-based fallback --}}
            <div class="rf-answers-grid" style="display:grid;grid-template-columns:repeat(2,1fr);gap:14px">
                @if($submission->submitter_name)
                <div>
                    <p style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.07em;margin:0 0 5px">Name</p>
                    <p style="font-size:13px;color:#1E1B4B;font-weight:500;margin:0">{{ $submission->submitter_name }}</p>
                </div>
                @endif
                @if($submission->submitter_email)
                <div>
                    <p style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.07em;margin:0 0 5px">Email</p>
                    <a href="mailto:{{ $submission->submitter_email }}"
                       style="font-size:13px;color:#7B61FF;font-weight:500;text-decoration:none;word-break:break-all"
                       aria-label="Send email to {{ $submission->submitter_email }}">{{ $submission->submitter_email }}</a>
                </div>
                @endif
                @if($submission->request_for)
                <div>
                    <p style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.07em;margin:0 0 5px">Request For</p>
                    <p style="font-size:13px;color:#1E1B4B;font-weight:500;margin:0">{{ $submission->request_for }}</p>
                </div>
                @endif
                @if($submission->notes)
                <div style="grid-column:1/-1">
                    <p style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.07em;margin:0 0 5px">Notes</p>
                    <div style="font-size:13px;color:#374151;line-height:1.75;background:#fafafa;border:1px solid #f3f4f6;border-radius:10px;padding:12px 14px;white-space:pre-line;word-break:break-word">{{ e($submission->notes) }}</div>
                </div>
                @endif
                @if(!$submission->submitter_name && !$submission->submitter_email && !$submission->request_for && !$submission->notes)
                <div style="grid-column:1/-1">
                    <p style="font-size:13px;color:#d1d5db;font-style:italic">No answer data available.</p>
                </div>
                @endif
            </div>
            @endif
        </div>

        {{-- ────────────────────────────────────────────────────────────────── --}}
        {{-- RECIPIENTS & GENERATED TASKS                                       --}}
        {{-- ────────────────────────────────────────────────────────────────── --}}
        <div class="card">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:20px">
                <div style="display:flex;align-items:center;gap:10px">
                    <div style="width:32px;height:32px;border-radius:9px;background:#dcfce7;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <svg style="width:15px;height:15px;color:#15803d" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                    </div>
                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:#1E1B4B;margin:0">Recipients & Generated Tasks</h3>
                        <p style="font-size:11px;color:#9ca3af;margin:0">Tasks automatically created from this response</p>
                    </div>
                </div>
                @if($taskCount > 0)
                <span style="font-size:11px;font-weight:700;background:#ede9fe;color:#7B61FF;padding:3px 10px;border-radius:9999px;white-space:nowrap;flex-shrink:0">
                    {{ $taskCount }} {{ Str::plural('task', $taskCount) }}
                </span>
                @endif
            </div>

            @if($submission->submissionRecipients->isEmpty())
            <div style="text-align:center;padding:24px 16px">
                <div style="width:44px;height:44px;border-radius:14px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;margin:0 auto 10px">
                    <svg style="width:20px;height:20px;color:#d1d5db" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
                <p style="font-size:13px;font-weight:600;color:#6b7280;margin:0 0 4px">No recipients selected</p>
                <p style="font-size:12px;color:#9ca3af;margin:0">This submission did not select any recipients.</p>
            </div>
            @else
            <div style="display:flex;flex-direction:column;gap:12px">
                @foreach($submission->submissionRecipients as $recip)
                @php
                // Match task via task_id on recipient first, then by assigned_to_id
                $task = $recip->task_id
                    ? $submission->tasks->firstWhere('id', $recip->task_id)
                    : $submission->tasks->firstWhere('assigned_to_id', $recip->recipient_id);

                [$taskStatusCss, $taskStatusLabel] = $task ? match($task->status) {
                    'completed'   => ['background:#dcfce7;color:#15803d',  'Completed'],
                    'in_progress' => ['background:#dbeafe;color:#2563eb',  'In Progress'],
                    'cancelled'   => ['background:#f3f4f6;color:#6b7280',  'Cancelled'],
                    'waiting'     => ['background:#fff7ed;color:#ea580c',  'Waiting'],
                    default       => ['background:#ede9fe;color:#7B61FF',  'Open'],
                } : ['background:#fef3c7;color:#d97706', 'No Task'];

                [$taskPrioCss, $taskPrioLabel] = $task ? match($task->priority ?? 'medium') {
                    'urgent' => ['background:#fee2e2;color:#dc2626', 'Urgent'],
                    'high'   => ['background:#fff7ed;color:#ea580c', 'High'],
                    'low'    => ['background:#f3f4f6;color:#6b7280',  'Low'],
                    default  => ['background:#fffbeb;color:#d97706', 'Medium'],
                } : ['', ''];

                // Two-letter initials from full name
                $nameParts = preg_split('/\s+/', trim($recip->recipient_name ?? '?'), 2);
                $initials  = strtoupper(substr($nameParts[0] ?? '?', 0, 1))
                           . strtoupper(substr($nameParts[1] ?? '', 0, 1));
                $initials  = $initials ?: '??';
                @endphp

                <div style="border:1.5px solid #f3f4f6;border-radius:14px;overflow:hidden">

                    {{-- Recipient header row --}}
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:#f9fafb;gap:10px;flex-wrap:wrap">
                        <div style="display:flex;align-items:center;gap:10px;min-width:0">
                            <div style="width:38px;height:38px;border-radius:9999px;background:linear-gradient(135deg,#7B61FF,#5b4cdb);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:white;flex-shrink:0;letter-spacing:.03em"
                                 aria-hidden="true">{{ $initials }}</div>
                            <div style="min-width:0">
                                <p style="font-size:13px;font-weight:700;color:#1E1B4B;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $recip->recipient_name }}</p>
                                <p style="font-size:11px;color:#9ca3af;margin:0;word-break:break-all">{{ $recip->recipient_email }}</p>
                            </div>
                        </div>
                        <span style="font-size:10px;font-weight:700;padding:3px 10px;border-radius:9999px;flex-shrink:0;white-space:nowrap;{{ $taskStatusCss }}">{{ $taskStatusLabel }}</span>
                    </div>

                    {{-- Task body --}}
                    @if($task)
                    <div style="padding:14px 16px">
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:14px;flex-wrap:wrap">
                            <div style="flex:1;min-width:0">
                                <p style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.07em;margin:0 0 4px">Task</p>
                                <p style="font-size:13px;font-weight:600;color:#1E1B4B;margin:0;word-break:break-word;line-height:1.4">{{ $task->title }}</p>

                                <div style="display:flex;align-items:center;gap:8px;margin-top:8px;flex-wrap:wrap">
                                    {{-- Priority badge --}}
                                    @if($taskPrioLabel)
                                    <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:9999px;{{ $taskPrioCss }}">{{ $taskPrioLabel }}</span>
                                    @endif

                                    {{-- Due date --}}
                                    @if($task->due_at)
                                    <span style="font-size:11px;display:flex;align-items:center;gap:3px;font-weight:{{ $task->isOverdue() ? '700' : '400' }};color:{{ $task->isOverdue() ? '#dc2626' : '#9ca3af' }}">
                                        <svg style="width:10px;height:10px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        Due {{ $task->due_at->format('M j, Y') }}{{ $task->isOverdue() ? ' · Overdue' : '' }}
                                    </span>
                                    @endif

                                    {{-- Completed date --}}
                                    @if($task->completed_at)
                                    <span style="font-size:11px;color:#15803d;font-weight:600;display:flex;align-items:center;gap:3px">
                                        <svg style="width:10px;height:10px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        Done {{ $task->completed_at->format('M j, Y') }}
                                    </span>
                                    @endif

                                    {{-- Created --}}
                                    <span style="font-size:11px;color:#d1d5db">by System · {{ $task->created_at->format('M j') }}</span>
                                </div>
                            </div>

                            {{-- View Task CTA --}}
                            <a href="{{ route('tenant.tasks.show', [$tenant->id, $task->id]) }}"
                               style="display:inline-flex;align-items:center;gap:5px;padding:8px 16px;border-radius:9px;background:linear-gradient(135deg,#7B61FF,#5b4cdb);color:white;font-size:12px;font-weight:600;text-decoration:none;flex-shrink:0;white-space:nowrap;box-shadow:0 2px 8px rgba(123,97,255,0.22);transition:opacity .15s"
                               onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
                                <svg style="width:11px;height:11px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                View Task
                            </a>
                        </div>

                        {{-- Completion email status --}}
                        @if($task->completionResponses && $task->completionResponses->isNotEmpty())
                        @php $lastResp = $task->completionResponses->first(); @endphp
                        <div style="margin-top:10px;padding-top:10px;border-top:1px solid #f3f4f6;display:flex;align-items:center;gap:7px;flex-wrap:wrap">
                            <svg style="width:12px;height:12px;color:#9ca3af;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            @if($lastResp->status === 'sent')
                            <span style="font-size:11px;font-weight:700;background:#dcfce7;color:#15803d;padding:1px 8px;border-radius:9999px">Completion Email Sent</span>
                            @elseif($lastResp->status === 'failed')
                            <span style="font-size:11px;font-weight:700;background:#fee2e2;color:#dc2626;padding:1px 8px;border-radius:9999px">Completion Email Failed</span>
                            @else
                            <span style="font-size:11px;font-weight:700;background:#f3f4f6;color:#6b7280;padding:1px 8px;border-radius:9999px">Completion Email Queued</span>
                            @endif
                            <span style="font-size:11px;color:#d1d5db">→ {{ $lastResp->recipient_email ?: ($submission->submitter_email ?: 'requester') }}</span>
                        </div>
                        @endif
                    </div>
                    @else
                    <div style="padding:12px 16px">
                        <p style="font-size:12px;color:#9ca3af;margin:0;font-style:italic">No task was generated for this recipient.</p>
                    </div>
                    @endif

                </div>
                @endforeach
            </div>
            @endif

            {{-- Unmatched/additional tasks --}}
            @php
            $matchedIds    = $submission->submissionRecipients->pluck('task_id')->filter()->values();
            $unmatchedTasks = $submission->tasks->filter(fn($t) => !$matchedIds->contains($t->id));
            @endphp
            @if($unmatchedTasks->isNotEmpty())
            <div style="margin-top:16px;padding:14px 16px;background:#f5f3ff;border-radius:12px;border:1px solid #e9d5ff">
                <p style="font-size:12px;font-weight:700;color:#7B61FF;margin:0 0 10px">Additional Tasks</p>
                <div style="display:flex;flex-direction:column;gap:8px">
                    @foreach($unmatchedTasks as $uTask)
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
                        <p style="font-size:13px;color:#374151;margin:0;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $uTask->title }}</p>
                        <a href="{{ route('tenant.tasks.show', [$tenant->id, $uTask->id]) }}"
                           style="font-size:12px;color:#7B61FF;text-decoration:none;font-weight:700;flex-shrink:0;white-space:nowrap">
                            View Task →
                        </a>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- ────────────────────────────────────────────────────────────────── --}}
        {{-- ACTIVITY TIMELINE                                                  --}}
        {{-- ────────────────────────────────────────────────────────────────── --}}
        <div class="card">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
                <div style="width:32px;height:32px;border-radius:9px;background:#dbeafe;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <svg style="width:15px;height:15px;color:#2563eb" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 style="font-size:14px;font-weight:700;color:#1E1B4B;margin:0">Activity Timeline</h3>
                    <p style="font-size:11px;color:#9ca3af;margin:0">Events from submission to completion</p>
                </div>
            </div>

            @if($allActivities->isEmpty())
            <div style="text-align:center;padding:20px 0">
                <p style="font-size:13px;color:#d1d5db;font-style:italic">No activity recorded yet.</p>
            </div>
            @else
            <div style="position:relative;padding-left:24px">
                {{-- Vertical timeline line --}}
                <div style="position:absolute;left:15px;top:16px;bottom:10px;width:2px;background:linear-gradient(to bottom,#e9d5ff 0%,#f3f4f6 100%);border-radius:9999px"
                     aria-hidden="true"></div>

                @foreach($allActivities as $act)
                @php
                [$actTitle, $actColor, $actBg, $actIcon] = match($act->action_type ?? '') {
                    'response_submitted'          => ['Response Submitted',        '#7B61FF', '#ede9fe', '✓'],
                    'task_created'                => ['Task Created',              '#2563eb', '#dbeafe', '+'],
                    'task_completed'              => ['Task Completed',            '#15803d', '#dcfce7', '✓'],
                    'task_updated'                => ['Task Updated',              '#6b7280', '#f3f4f6', '↻'],
                    'task_assigned'               => ['Task Assigned',             '#d97706', '#fffbeb', '→'],
                    'task_reassigned'             => ['Task Reassigned',           '#ea580c', '#fff7ed', '↻'],
                    'completion_response_queued'  => ['Completion Email Queued',   '#d97706', '#fffbeb', '✉'],
                    'completion_response_sent'    => ['Completion Email Sent',     '#15803d', '#dcfce7', '✉'],
                    'completion_response_failed'  => ['Completion Email Failed',   '#dc2626', '#fee2e2', '✕'],
                    default                       => [Str::title(str_replace('_', ' ', $act->action_type ?? 'Event')), '#6b7280', '#f3f4f6', '·'],
                };
                @endphp
                <div style="display:flex;align-items:flex-start;gap:14px;margin-bottom:20px;position:relative">
                    {{-- Timeline dot --}}
                    <div style="width:30px;height:30px;border-radius:9999px;background:{{ $actBg }};border:2px solid {{ $actColor }};display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:11px;font-weight:700;color:{{ $actColor }};position:absolute;left:-39px;top:0;z-index:1"
                         aria-hidden="true">{{ $actIcon }}</div>

                    {{-- Event detail --}}
                    <div style="flex:1;min-width:0;padding-top:4px">
                        <p style="font-size:13px;font-weight:700;color:#1E1B4B;margin:0">{{ $actTitle }}</p>
                        @if(!empty($act->actor_name))
                        <p style="font-size:12px;color:#6b7280;margin:2px 0 0">by {{ $act->actor_name }}</p>
                        @endif
                        @if(!empty($act->new_values) && isset($act->new_values['assigned_to_name']))
                        <p style="font-size:12px;color:#9ca3af;margin:2px 0 0">Assigned to {{ $act->new_values['assigned_to_name'] }}</p>
                        @endif
                        <p style="font-size:11px;color:#d1d5db;margin:3px 0 0">{{ \Carbon\Carbon::parse($act->created_at)->format('M j, Y \a\t g:i A') }}</p>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

    </div>{{-- END LEFT COLUMN --}}

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- RIGHT: SIDEBAR                                                        --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div class="rf-resp-sidebar" style="display:flex;flex-direction:column;gap:16px">

        {{-- ────────────────────────────────────────────────────────────────── --}}
        {{-- SUBMISSION SUMMARY                                                 --}}
        {{-- ────────────────────────────────────────────────────────────────── --}}
        <div class="card" style="padding:20px">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:16px">
                <h3 style="font-size:13px;font-weight:700;color:#1E1B4B;margin:0">Submission Summary</h3>
                <span style="font-size:10px;font-weight:700;padding:2px 9px;border-radius:9999px;white-space:nowrap;background:{{ $statusBg }};color:{{ $statusColor }}">{{ $statusLabel }}</span>
            </div>

            <div style="display:flex;flex-direction:column;gap:13px">

                {{-- Submitted by --}}
                <div>
                    <p style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.07em;margin:0 0 3px">Submitted by</p>
                    <p style="font-size:13px;font-weight:600;color:#1E1B4B;margin:0;word-break:break-word">{{ $submitterLabel }}</p>
                    @if($submission->submitter_email)
                    <a href="mailto:{{ $submission->submitter_email }}"
                       style="font-size:11px;color:#7B61FF;text-decoration:none;word-break:break-all;line-height:1.4;display:block;margin-top:1px"
                       aria-label="Email {{ $submission->submitter_email }}"
                       title="{{ $submission->submitter_email }}">{{ $submission->submitter_email }}</a>
                    @endif
                </div>

                {{-- Submitted at --}}
                <div>
                    <p style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.07em;margin:0 0 3px">Submitted at</p>
                    <p style="font-size:13px;font-weight:600;color:#1E1B4B;margin:0">{{ $submission->submitted_at ? $submission->submitted_at->format('M j, Y') : '—' }}</p>
                    @if($submission->submitted_at)
                    <p style="font-size:11px;color:#9ca3af;margin:0">{{ $submission->submitted_at->format('g:i A') }}</p>
                    @endif
                </div>

                {{-- Form --}}
                <div>
                    <p style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.07em;margin:0 0 3px">Form</p>
                    <a href="{{ route('tenant.request-forms.edit', [$tenant->id, $form->id]) }}"
                       style="font-size:13px;font-weight:600;color:#7B61FF;text-decoration:none;line-height:1.4;display:block;word-break:break-word">{{ $form->title }}</a>
                </div>

                {{-- Request For --}}
                @if($submission->request_for)
                <div>
                    <p style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.07em;margin:0 0 3px">Request For</p>
                    <p style="font-size:13px;font-weight:600;color:#1E1B4B;margin:0;word-break:break-word">{{ $submission->request_for }}</p>
                </div>
                @endif

                {{-- Generated tasks --}}
                <div>
                    <p style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.07em;margin:0 0 3px">Generated Tasks</p>
                    <p style="font-size:13px;font-weight:700;color:{{ $taskCount > 0 ? '#7B61FF' : '#9ca3af' }};margin:0">
                        {{ $taskCount > 0 ? $taskCount . ' ' . Str::plural('task', $taskCount) : 'None' }}
                    </p>
                </div>

                {{-- Divider --}}
                <div style="height:1px;background:#f3f4f6"></div>

                {{-- Response ID (truncated) --}}
                <div>
                    <p style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.07em;margin:0 0 3px">Response ID</p>
                    <p style="font-size:10px;color:#d1d5db;margin:0;font-family:monospace;word-break:break-all" title="{{ $submission->id }}">{{ substr($submission->id, 0, 18) }}…</p>
                </div>

            </div>
        </div>

        {{-- ────────────────────────────────────────────────────────────────── --}}
        {{-- QUICK ACTIONS                                                       --}}
        {{-- ────────────────────────────────────────────────────────────────── --}}
        <div class="card" style="padding:20px">
            <h3 style="font-size:13px;font-weight:700;color:#1E1B4B;margin:0 0 14px">Quick Actions</h3>
            <div style="display:flex;flex-direction:column;gap:8px">

                {{-- View Task(s) — primary CTA --}}
                @if($submission->tasks->isNotEmpty())
                @php $firstTask = $submission->tasks->first(); @endphp
                <a href="{{ route('tenant.tasks.show', [$tenant->id, $firstTask->id]) }}"
                   style="display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:10px;background:linear-gradient(135deg,#7B61FF,#5b4cdb);color:white;font-size:12px;font-weight:600;text-decoration:none;box-shadow:0 2px 8px rgba(123,97,255,0.22);transition:opacity .15s"
                   onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
                    <svg style="width:13px;height:13px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    View Task{{ $taskCount > 1 ? 's (' . $taskCount . ')' : '' }}
                </a>
                @endif

                {{-- Email Requester --}}
                @if($submission->submitter_email)
                <a href="mailto:{{ $submission->submitter_email }}"
                   style="display:flex;align-items:center;gap:8px;padding:9px 14px;border-radius:10px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:12px;font-weight:600;text-decoration:none;transition:background .12s"
                   onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='white'"
                   aria-label="Email requester {{ $submission->submitter_email }}">
                    <svg style="width:13px;height:13px;color:#6b7280;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    Email Requester
                </a>
                @endif

                {{-- Form Settings --}}
                <a href="{{ route('tenant.request-forms.edit', [$tenant->id, $form->id]) }}"
                   style="display:flex;align-items:center;gap:8px;padding:9px 14px;border-radius:10px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:12px;font-weight:600;text-decoration:none;transition:background .12s"
                   onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='white'">
                    <svg style="width:13px;height:13px;color:#6b7280;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Form Settings
                </a>

                {{-- Preview public form --}}
                @if($form->isPublished())
                <a href="{{ $form->publicUrl() }}" target="_blank" rel="noopener noreferrer"
                   style="display:flex;align-items:center;gap:8px;padding:9px 14px;border-radius:10px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:12px;font-weight:600;text-decoration:none;transition:background .12s"
                   onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='white'">
                    <svg style="width:13px;height:13px;color:#6b7280;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    View Public Form
                </a>
                @endif

                <div style="height:1px;background:#f3f4f6;margin:2px 0" aria-hidden="true"></div>

                {{-- All Responses --}}
                <a href="{{ route('tenant.request-forms.submissions', [$tenant->id, $form->id]) }}"
                   style="display:flex;align-items:center;gap:8px;padding:9px 14px;border-radius:10px;border:1.5px solid #e5e7eb;background:white;color:#6b7280;font-size:12px;font-weight:600;text-decoration:none;transition:background .12s"
                   onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='white'">
                    <svg style="width:13px;height:13px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    All Responses
                </a>

            </div>
        </div>

    </div>{{-- END SIDEBAR --}}

</div>{{-- END GRID --}}
@endsection
