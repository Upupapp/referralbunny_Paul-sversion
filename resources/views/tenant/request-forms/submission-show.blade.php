@extends('layouts.app')
@section('title', 'Response Detail')
@section('nav') @include('tenant._nav') @endsection

@section('content')
<div class="max-w-3xl space-y-5">

    {{-- Breadcrumb --}}
    <div>
        <div style="display:flex;align-items:center;gap:6px;font-size:13px;color:#9ca3af">
            <a href="{{ route('tenant.request-forms', $tenant->id) }}" style="color:#9ca3af;text-decoration:none">Request Forms</a>
            <svg style="width:12px;height:12px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <a href="{{ route('tenant.request-forms.submissions', [$tenant->id, $form->id]) }}" style="color:#9ca3af;text-decoration:none">{{ Str::limit($form->title, 40) }}</a>
            <svg style="width:12px;height:12px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span>Response</span>
        </div>
        <h1 style="font-size:20px;font-weight:700;color:#1E1B4B;margin-top:8px">Request Response</h1>
    </div>

    {{-- Submission Summary --}}
    <div class="card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:16px">
            <h3 style="font-size:14px;font-weight:700;color:#1E1B4B">Submission Summary</h3>
            @php $subStatus = match($submission->status) {
                'tasks_created' => 'background:#dcfce7;color:#15803d',
                'failed'        => 'background:#fee2e2;color:#dc2626',
                default         => 'background:#f3f4f6;color:#6b7280',
            }; @endphp
            <span style="font-size:11px;font-weight:700;padding:3px 10px;border-radius:9999px;{{ $subStatus }}">
                {{ str_replace('_', ' ', ucfirst($submission->status)) }}
            </span>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;font-size:13px;gap:16px">
                <span style="color:#9ca3af;flex-shrink:0">Submitted by</span>
                <span style="font-weight:600;color:#1E1B4B;text-align:right">
                    {{ $submission->submitter_name ?: '—' }}
                    @if($submission->submitter_email)
                    <span style="color:#7B61FF;font-weight:400"> &lt;{{ $submission->submitter_email }}&gt;</span>
                    @endif
                </span>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;font-size:13px">
                <span style="color:#9ca3af">Submitted at</span>
                <span style="font-weight:600;color:#1E1B4B">{{ $submission->submitted_at ? $submission->submitted_at->format('M j, Y g:i A') : '—' }}</span>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;font-size:13px">
                <span style="color:#9ca3af">Form</span>
                <a href="{{ route('tenant.request-forms.edit', [$tenant->id, $form->id]) }}"
                   style="font-weight:600;color:#7B61FF;text-decoration:none">{{ $form->title }}</a>
            </div>
            @if($submission->request_for)
            <div style="display:flex;align-items:center;justify-content:space-between;font-size:13px">
                <span style="color:#9ca3af">Request For</span>
                <span style="font-weight:600;color:#1E1B4B">{{ $submission->request_for }}</span>
            </div>
            @endif
        </div>
    </div>

    {{-- Submitted Answers --}}
    <div class="card">
        <h3 style="font-size:14px;font-weight:700;color:#1E1B4B;margin-bottom:16px">Submitted Answers</h3>

        @php
        $payload = $submission->payload ?? [];
        $fields  = $form->fields;
        @endphp

        @if($fields->isNotEmpty())
        <div style="display:flex;flex-direction:column;gap:14px">
            @foreach($fields as $field)
            @php
            $answer = $payload[$field->field_key] ?? $payload[$field->label] ?? null;
            // Legacy fallbacks
            if ($answer === null) {
                $answer = match($field->field_key) {
                    'name'        => $submission->submitter_name,
                    'email'       => $submission->submitter_email,
                    'request_for' => $submission->request_for,
                    'notes'       => $submission->notes,
                    default       => null,
                };
            }
            @endphp
            <div>
                <p style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px">
                    {{ $field->label }}
                    @if($field->is_required)<span style="color:#ef4444">*</span>@endif
                </p>
                @if($answer !== null && $answer !== '')
                    @if($field->field_type === 'textarea')
                    <p style="font-size:13px;color:#374151;background:#f9fafb;border-radius:10px;padding:12px;line-height:1.6;border-left:3px solid #7B61FF;margin:0;white-space:pre-line">{{ $answer }}</p>
                    @elseif(is_array($answer))
                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                        @foreach($answer as $v)
                        <span style="font-size:12px;font-weight:600;background:#ede9fe;color:#7B61FF;padding:3px 10px;border-radius:9999px">{{ $v }}</span>
                        @endforeach
                    </div>
                    @else
                    <p style="font-size:13px;color:#374151;font-weight:500;margin:0">{{ $answer }}</p>
                    @endif
                @else
                <p style="font-size:13px;color:#d1d5db;margin:0">—</p>
                @endif
            </div>
            @endforeach
        </div>
        @elseif($payload)
        {{-- No field schema but payload exists — show raw key/value --}}
        <div style="display:flex;flex-direction:column;gap:10px">
            @foreach($payload as $key => $val)
            <div>
                <p style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px">{{ str_replace('_',' ', $key) }}</p>
                @if(is_array($val))
                <p style="font-size:13px;color:#374151">{{ implode(', ', $val) }}</p>
                @else
                <p style="font-size:13px;color:#374151;white-space:pre-line">{{ $val }}</p>
                @endif
            </div>
            @endforeach
        </div>
        @else
        {{-- Legacy fields from columns --}}
        <div style="display:flex;flex-direction:column;gap:10px">
            @if($submission->submitter_name)
            <div>
                <p style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px">Name</p>
                <p style="font-size:13px;color:#374151">{{ $submission->submitter_name }}</p>
            </div>
            @endif
            @if($submission->submitter_email)
            <div>
                <p style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px">Email</p>
                <p style="font-size:13px;color:#374151">{{ $submission->submitter_email }}</p>
            </div>
            @endif
            @if($submission->request_for)
            <div>
                <p style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px">Request For</p>
                <p style="font-size:13px;color:#374151">{{ $submission->request_for }}</p>
            </div>
            @endif
            @if($submission->notes)
            <div>
                <p style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px">Notes</p>
                <p style="font-size:13px;color:#374151;background:#f9fafb;border-radius:10px;padding:12px;line-height:1.6;border-left:3px solid #7B61FF;white-space:pre-line">{{ $submission->notes }}</p>
            </div>
            @endif
        </div>
        @endif
    </div>

    {{-- Recipients & Generated Tasks --}}
    <div class="card">
        <h3 style="font-size:14px;font-weight:700;color:#1E1B4B;margin-bottom:16px">Recipients & Generated Tasks</h3>

        @if($submission->submissionRecipients->isEmpty())
        <p style="font-size:13px;color:#9ca3af">No recipients were selected for this submission.</p>
        @else
        <div style="display:flex;flex-direction:column;gap:12px">
            @foreach($submission->submissionRecipients as $recip)
            @php
            $task = $submission->tasks->firstWhere('assigned_to_id', $recip->recipient_id)
                 ?? $submission->tasks->first();
            $taskStatus = $task ? match($task->status) {
                'completed'   => ['background:#dcfce7;color:#15803d', 'Completed'],
                'in_progress' => ['background:#dbeafe;color:#2563eb', 'In Progress'],
                'cancelled'   => ['background:#fee2e2;color:#dc2626', 'Cancelled'],
                default       => ['background:#ede9fe;color:#7B61FF', 'Open'],
            } : null;
            $taskPrio = $task ? match($task->priority ?? 'medium') {
                'urgent' => 'background:#fef2f2;color:#dc2626',
                'high'   => 'background:#fff7ed;color:#ea580c',
                'medium' => 'background:#fffbeb;color:#d97706',
                default  => 'background:#f3f4f6;color:#6b7280',
            } : null;
            @endphp
            <div style="padding:14px 16px;background:#f9fafb;border-radius:12px;border:1.5px solid #f3f4f6">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap">
                    <div style="display:flex;align-items:center;gap:10px">
                        <div style="width:32px;height:32px;border-radius:9999px;background:#ede9fe;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#7B61FF;flex-shrink:0">
                            {{ strtoupper(substr($recip->recipient_name ?? '?', 0, 2)) }}
                        </div>
                        <div>
                            <p style="font-size:13px;font-weight:600;color:#1E1B4B">{{ $recip->recipient_name }}</p>
                            <p style="font-size:11px;color:#9ca3af">{{ $recip->recipient_email }}</p>
                        </div>
                    </div>
                    @if($task)
                    <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:9999px;{{ $taskStatus[0] }}">{{ $taskStatus[1] }}</span>
                    @else
                    <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:9999px;background:#fee2e2;color:#dc2626">No Task</span>
                    @endif
                </div>

                @if($task)
                <div style="margin-top:10px;padding-top:10px;border-top:1px solid #f3f4f6">
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
                        <div>
                            <p style="font-size:12px;color:#9ca3af;margin-bottom:2px">Task</p>
                            <p style="font-size:13px;font-weight:600;color:#374151">{{ $task->title }}</p>
                            <div style="display:flex;align-items:center;gap:8px;margin-top:4px;flex-wrap:wrap">
                                <span style="font-size:10px;font-weight:700;padding:1px 7px;border-radius:9999px;{{ $taskPrio }}">{{ ucfirst($task->priority ?? 'medium') }}</span>
                                @if($task->due_at)
                                <span style="font-size:11px;color:{{ $task->isOverdue() ? '#dc2626' : '#9ca3af' }};font-weight:{{ $task->isOverdue() ? '700' : '400' }}">
                                    Due {{ $task->due_at->format('M j, Y') }}{{ $task->isOverdue() ? ' (overdue)' : '' }}
                                </span>
                                @endif
                                @if($task->completed_at)
                                <span style="font-size:11px;color:#9ca3af">Completed {{ $task->completed_at->format('M j, Y') }}</span>
                                @endif
                            </div>
                        </div>
                        <a href="{{ route('tenant.tasks.show', [$tenant->id, $task->id]) }}"
                           style="display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:8px;background:#7B61FF;color:white;font-size:12px;font-weight:600;text-decoration:none;flex-shrink:0">
                            <svg style="width:11px;height:11px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            View Task
                        </a>
                    </div>

                    {{-- Completion response email status --}}
                    @if($task->completionResponses && $task->completionResponses->isNotEmpty())
                    @php $lastResp = $task->completionResponses->first(); @endphp
                    <div style="margin-top:8px;display:flex;align-items:center;gap:6px;font-size:11px">
                        @if($lastResp->status === 'sent')
                        <span style="background:#dcfce7;color:#15803d;padding:1px 7px;border-radius:9999px;font-weight:700">Response Email Sent</span>
                        @elseif($lastResp->status === 'failed')
                        <span style="background:#fee2e2;color:#dc2626;padding:1px 7px;border-radius:9999px;font-weight:700">Response Email Failed</span>
                        @else
                        <span style="background:#f3f4f6;color:#6b7280;padding:1px 7px;border-radius:9999px;font-weight:700">Response Email Queued</span>
                        @endif
                    </div>
                    @endif
                </div>
                @endif
            </div>
            @endforeach
        </div>
        @endif

        {{-- Tasks not matched to a recipient (fallback) --}}
        @php
        $matchedTaskIds = $submission->submissionRecipients
            ->pluck('task_id')
            ->filter()
            ->toArray();
        $unmatchedTasks = $submission->tasks->filter(fn($t) => !in_array($t->id, $matchedTaskIds));
        @endphp
        @if($unmatchedTasks->isNotEmpty())
        <div style="margin-top:12px;padding:12px 14px;background:#f5f3ff;border-radius:10px">
            <p style="font-size:12px;font-weight:600;color:#7B61FF;margin-bottom:8px">Additional tasks</p>
            @foreach($unmatchedTasks as $task)
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
                <p style="font-size:13px;color:#374151">{{ $task->title }}</p>
                <a href="{{ route('tenant.tasks.show', [$tenant->id, $task->id]) }}"
                   style="font-size:12px;color:#7B61FF;text-decoration:none;font-weight:600;flex-shrink:0">View Task →</a>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Activity Timeline --}}
    @php
    $allActivities = $submission->tasks
        ->flatMap(fn($t) => $t->activities ?? collect())
        ->sortBy('created_at');
    @endphp
    @if($allActivities->isNotEmpty())
    <div class="card">
        <h3 style="font-size:14px;font-weight:700;color:#1E1B4B;margin-bottom:16px">Activity Timeline</h3>
        <div style="position:relative;padding-left:24px">
            {{-- Timeline line --}}
            <div style="position:absolute;left:7px;top:4px;bottom:4px;width:2px;background:#f3f4f6;border-radius:9999px"></div>

            @foreach($allActivities as $act)
            @php
            $actLabel = match($act->action_type ?? '') {
                'task_created'                 => ['Task created', '#7B61FF', '#ede9fe'],
                'task_completed'               => ['Task completed', '#15803d', '#dcfce7'],
                'completion_response_queued'   => ['Response email queued', '#2563eb', '#dbeafe'],
                'completion_response_sent'     => ['Response email sent', '#15803d', '#dcfce7'],
                'completion_response_failed'   => ['Response email failed', '#dc2626', '#fee2e2'],
                default                        => [ucfirst(str_replace('_', ' ', $act->action_type ?? '')), '#6b7280', '#f3f4f6'],
            };
            @endphp
            <div style="position:relative;margin-bottom:14px;display:flex;align-items:flex-start;gap:10px">
                <div style="width:14px;height:14px;border-radius:9999px;background:{{ $actLabel[2] }};border:2px solid {{ $actLabel[1] }};flex-shrink:0;margin-top:1px;position:absolute;left:-21px"></div>
                <div style="flex:1;min-width:0">
                    <p style="font-size:13px;font-weight:600;color:#1E1B4B;margin:0">{{ $actLabel[0] }}</p>
                    @if($act->actor_name)
                    <p style="font-size:11px;color:#9ca3af;margin-top:1px">by {{ $act->actor_name }}</p>
                    @endif
                    <p style="font-size:11px;color:#d1d5db;margin-top:1px">{{ \Carbon\Carbon::parse($act->created_at)->format('M j, Y g:i A') }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Bottom nav --}}
    <div style="display:flex;gap:10px;justify-content:flex-start;padding-bottom:20px">
        <a href="{{ route('tenant.request-forms.submissions', [$tenant->id, $form->id]) }}"
           style="padding:9px 20px;border-radius:10px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:13px;font-weight:600;text-decoration:none">
            ← All Responses
        </a>
    </div>

</div>
@endsection
