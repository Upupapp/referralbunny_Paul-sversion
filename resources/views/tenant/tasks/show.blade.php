@extends('layouts.app')
@section('title', $task->title)
@section('nav') @include('tenant._nav') @endsection

@section('content')
<div class="max-w-3xl space-y-5"
     x-data="taskDetail('{{ $task->id }}', '{{ $tenant->id }}', {{ $canComplete ? 'true' : 'false' }}, {{ $completionEmailEnabled ? 'true' : 'false' }})">

    <div>
        <a href="{{ route('tenant.tasks', $tenant->id) }}" style="font-size:13px;color:#9ca3af;text-decoration:none">&larr; Back to Tasks</a>
    </div>

    {{-- Header card --}}
    <div class="card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap">
            <div style="flex:1;min-width:0">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px">
                    @php
                    $statusStyle = match($task->status) {
                        'completed'  => 'background:#dcfce7;color:#15803d',
                        'in_progress'=> 'background:#dbeafe;color:#2563eb',
                        'cancelled'  => 'background:#fee2e2;color:#dc2626',
                        default      => 'background:#ede9fe;color:#7B61FF',
                    };
                    @endphp
                    <span style="font-size:11px;font-weight:700;padding:3px 10px;border-radius:9999px;{{ $statusStyle }}">
                        {{ str_replace('_', ' ', ucfirst($task->status)) }}
                    </span>
                    @if($task->category)
                    <span style="font-size:11px;font-weight:600;padding:3px 10px;border-radius:9999px;background:#f3f4f6;color:#6b7280;text-transform:capitalize">
                        {{ str_replace('_', ' ', $task->category) }}
                    </span>
                    @endif
                    @php
                    $priorityStyle = match($task->priority) {
                        'urgent' => 'background:#fef2f2;color:#dc2626',
                        'high'   => 'background:#fff7ed;color:#ea580c',
                        'medium' => 'background:#fffbeb;color:#d97706',
                        default  => 'background:#f3f4f6;color:#6b7280',
                    };
                    @endphp
                    <span style="font-size:11px;font-weight:700;padding:3px 10px;border-radius:9999px;{{ $priorityStyle }}">
                        {{ ucfirst($task->priority) }} priority
                    </span>
                </div>
                <h1 style="font-size:18px;font-weight:700;color:#1E1B4B">{{ $task->title }}</h1>
                @if($task->due_at)
                <p style="font-size:12px;color:{{ $task->isOverdue() ? '#dc2626' : '#9ca3af' }};margin-top:4px">
                    Due: {{ $task->due_at->format('M j, Y') }} {{ $task->isOverdue() ? '(overdue)' : '' }}
                </p>
                @endif
            </div>

            @if($canComplete && $task->status !== 'completed')
            <div style="display:flex;gap:8px;flex-shrink:0;flex-wrap:wrap">
                @if($completionEmailEnabled && $task->requestor_email)
                <button @click="showCompleteModal = true"
                        style="padding:9px 20px;border-radius:12px;background:linear-gradient(135deg,#7B61FF,#5b4cdb);color:white;border:none;font-size:13px;font-weight:600;cursor:pointer;box-shadow:0 4px 14px rgba(123,97,255,0.3)">
                    Mark Done
                </button>
                @else
                <button @click="completeTask()"
                        style="padding:9px 20px;border-radius:12px;background:linear-gradient(135deg,#16a34a,#15803d);color:white;border:none;font-size:13px;font-weight:600;cursor:pointer"
                        :disabled="completing" x-text="completing ? 'Completing...' : 'Mark Complete'">
                </button>
                @endif
            </div>
            @endif
        </div>
    </div>

    {{-- Request Details --}}
    @if($source || $task->requestor_email)
    <div class="card">
        <h3 style="font-size:14px;font-weight:700;color:#1E1B4B;margin-bottom:14px">Request Details</h3>
        <div style="display:flex;flex-direction:column;gap:10px">
            @if($task->requestor_name || $task->requestor_email)
            <div style="display:flex;justify-content:space-between;font-size:13px">
                <span style="color:#9ca3af">From</span>
                <span style="font-weight:600;color:#1E1B4B">{{ $task->requestor_name ?: '—' }} <span style="color:#7B61FF">&lt;{{ $task->requestor_email }}&gt;</span></span>
            </div>
            @endif
            @if($source)
            @if($source->request_for)
            <div style="display:flex;justify-content:space-between;font-size:13px">
                <span style="color:#9ca3af">Request For</span>
                <span style="font-weight:600;color:#1E1B4B">{{ $source->request_for }}</span>
            </div>
            @endif
            @if($source->notes)
            <div style="font-size:13px">
                <span style="color:#9ca3af;display:block;margin-bottom:4px">Notes</span>
                <p style="background:#f9fafb;border-radius:10px;padding:12px;color:#374151;line-height:1.6;border-left:3px solid #7B61FF">{{ $source->notes }}</p>
            </div>
            @endif
            <div style="display:flex;justify-content:space-between;font-size:12px;color:#9ca3af">
                <span>Form</span>
                <span>{{ $source->form->title ?? 'Request Form' }}</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:12px;color:#9ca3af">
                <span>Submitted</span>
                <span>{{ $source->submitted_at->format('M j, Y g:i A') }}</span>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Task description --}}
    @if($task->description)
    <div class="card">
        <h3 style="font-size:14px;font-weight:700;color:#1E1B4B;margin-bottom:10px">Task Notes</h3>
        <p style="font-size:13px;color:#374151;line-height:1.7;white-space:pre-line">{{ $task->description }}</p>
    </div>
    @endif

    {{-- Completion responses --}}
    @if($task->completionResponses->count())
    <div class="card">
        <h3 style="font-size:14px;font-weight:700;color:#1E1B4B;margin-bottom:14px">Response Emails Sent</h3>
        <div class="space-y-3">
            @foreach($task->completionResponses as $resp)
            <div style="padding:12px;background:#f9fafb;border-radius:10px;border:1px solid #f3f4f6">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                    <p style="font-size:13px;font-weight:600;color:#1E1B4B">{{ $resp->subject }}</p>
                    <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:9999px;
                                 {{ $resp->status === 'sent' ? 'background:#dcfce7;color:#15803d' : ($resp->status === 'failed' ? 'background:#fee2e2;color:#dc2626' : 'background:#fef3c7;color:#d97706') }}">
                        {{ ucfirst($resp->status) }}
                    </span>
                </div>
                <p style="font-size:12px;color:#9ca3af">To: {{ $resp->recipient_email }} &middot; {{ $resp->created_at->diffForHumans() }}</p>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Activity --}}
    <div class="card">
        <h3 style="font-size:14px;font-weight:700;color:#1E1B4B;margin-bottom:14px">Activity</h3>
        <div class="space-y-3">
            @forelse($task->activities as $activity)
            <div style="display:flex;gap:10px">
                <div style="width:8px;height:8px;background:#7B61FF;border-radius:9999px;flex-shrink:0;margin-top:5px"></div>
                <div>
                    <p style="font-size:13px;color:#374151">
                        <strong>{{ $activity->actor_name ?: 'System' }}</strong>
                        — {{ str_replace('_', ' ', $activity->action_type) }}
                    </p>
                    <p style="font-size:11px;color:#9ca3af">{{ $activity->created_at->diffForHumans() }}</p>
                </div>
            </div>
            @empty
            <p style="font-size:13px;color:#9ca3af">No activity yet.</p>
            @endforelse
        </div>
    </div>

    {{-- Complete Modal --}}
    @if($completionEmailEnabled && $task->requestor_email)
    <div x-show="showCompleteModal" style="display:none"
         style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;display:flex;align-items:flex-end;justify-content:center;padding:16px"
         @keydown.escape.window="showCompleteModal = false">
        <div style="background:white;border-radius:20px;width:100%;max-width:500px;overflow:hidden;box-shadow:0 25px 60px rgba(0,0,0,.18)" @click.stop>

            <div style="padding:20px 24px 16px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between">
                <h3 style="font-size:16px;font-weight:700;color:#1E1B4B">Complete Task</h3>
                <button @click="showCompleteModal = false" style="background:none;border:none;cursor:pointer;color:#9ca3af;font-size:18px">&times;</button>
            </div>

            <div style="padding:20px 24px;display:flex;flex-direction:column;gap:14px">

                {{-- Choice --}}
                <div style="display:flex;flex-direction:column;gap:8px">
                    <label style="display:flex;align-items:flex-start;gap:10px;padding:12px;background:#f9fafb;border-radius:12px;cursor:pointer;border:1.5px solid"
                           :style="responseMode === 'no_email' ? 'border-color:#7B61FF;background:#f5f3ff' : 'border-color:#e5e7eb'">
                        <input type="radio" x-model="responseMode" value="no_email" style="margin-top:2px;accent-color:#7B61FF">
                        <div>
                            <p style="font-size:13px;font-weight:600;color:#1E1B4B">Complete task only</p>
                            <p style="font-size:11px;color:#9ca3af">Mark done without sending a reply email</p>
                        </div>
                    </label>
                    <label style="display:flex;align-items:flex-start;gap:10px;padding:12px;background:#f9fafb;border-radius:12px;cursor:pointer;border:1.5px solid"
                           :style="responseMode === 'with_email' ? 'border-color:#7B61FF;background:#f5f3ff' : 'border-color:#e5e7eb'">
                        <input type="radio" x-model="responseMode" value="with_email" style="margin-top:2px;accent-color:#7B61FF">
                        <div>
                            <p style="font-size:13px;font-weight:600;color:#1E1B4B">Complete and send response</p>
                            <p style="font-size:11px;color:#9ca3af">Send a reply to <strong>{{ $task->requestor_email }}</strong></p>
                        </div>
                    </label>
                </div>

                {{-- Response form --}}
                <div x-show="responseMode === 'with_email'" style="display:none;flex-direction:column;gap:12px">
                    <div>
                        <label style="display:block;font-size:11px;font-weight:700;color:#9ca3af;letter-spacing:.05em;text-transform:uppercase;margin-bottom:4px">To (fixed)</label>
                        <input type="text" value="{{ $task->requestor_email }}" readonly
                               style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;color:#9ca3af;background:#f9fafb;box-sizing:border-box">
                    </div>
                    <div>
                        <label style="display:block;font-size:11px;font-weight:700;color:#9ca3af;letter-spacing:.05em;text-transform:uppercase;margin-bottom:4px">Subject *</label>
                        <input type="text" x-model="responseSubject" maxlength="150"
                               style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;color:#1E1B4B;background:white;outline:none;box-sizing:border-box"
                               :placeholder="'Re: ' + '{{ addslashes($task->title) }}'">
                    </div>
                    <div>
                        <label style="display:block;font-size:11px;font-weight:700;color:#9ca3af;letter-spacing:.05em;text-transform:uppercase;margin-bottom:4px">Response *</label>
                        <textarea x-model="responseBody" rows="5" maxlength="10000"
                                  style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;color:#1E1B4B;background:white;outline:none;resize:vertical;box-sizing:border-box;font-family:inherit"
                                  placeholder="Describe the outcome, next steps, or any information the requestor needs..."></textarea>
                    </div>
                    <p x-show="responseError" x-text="responseError" style="font-size:12px;color:#dc2626;font-weight:600"></p>
                </div>

                {{-- Actions --}}
                <div style="display:flex;gap:8px;justify-content:flex-end;padding-top:4px">
                    <button @click="showCompleteModal = false"
                            style="padding:9px 20px;border-radius:10px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:13px;font-weight:600;cursor:pointer">
                        Cancel
                    </button>
                    <button @click="responseMode === 'with_email' ? sendResponse() : completeTask()"
                            :disabled="completing"
                            style="padding:9px 20px;border-radius:10px;background:linear-gradient(135deg,#7B61FF,#5b4cdb);color:white;border:none;font-size:13px;font-weight:600;cursor:pointer"
                            :style="completing ? 'opacity:0.5;cursor:not-allowed' : ''"
                            x-text="completing ? 'Processing...' : (responseMode === 'with_email' ? 'Send & Complete' : 'Complete Task')">
                    </button>
                </div>

                <p x-show="successMsg" x-text="successMsg" style="font-size:12px;color:#16a34a;font-weight:600;text-align:center"></p>
            </div>
        </div>
    </div>
    @endif

</div>

@push('scripts')
<script>
function taskDetail(taskId, tenantId, canComplete, completionEmailEnabled) {
    const csrf = document.querySelector('meta[name=csrf-token]')?.content ?? '';
    return {
        showCompleteModal: false,
        responseMode: 'no_email',
        responseSubject: '',
        responseBody: '',
        responseError: '',
        successMsg: '',
        completing: false,

        async completeTask() {
            if (!canComplete) return;
            this.completing = true;
            try {
                const res = await fetch(`/tenant/${tenantId}/tasks/${taskId}/complete`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                });
                const data = await res.json();
                if (res.ok) {
                    this.successMsg = data.message || 'Task completed.';
                    this.showCompleteModal = false;
                    setTimeout(() => location.reload(), 1200);
                } else {
                    this.$dispatch('show-toast', { type: 'error', message: data.error || 'Failed to complete task.' });
                }
            } finally { this.completing = false; }
        },

        async sendResponse() {
            this.responseError = '';
            if (!this.responseSubject.trim()) { this.responseError = 'Subject is required.'; return; }
            if (!this.responseBody.trim())    { this.responseError = 'Response body is required.'; return; }

            this.completing = true;
            try {
                const body = new FormData();
                body.append('_token', csrf);
                body.append('subject', this.responseSubject);
                body.append('body', this.responseBody);
                body.append('client_request_id', 'cr_' + Date.now());

                const res = await fetch(`/tenant/${tenantId}/tasks/${taskId}/complete-with-response`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body,
                });
                const data = await res.json();
                if (res.ok) {
                    this.successMsg = data.message;
                    setTimeout(() => location.reload(), 1500);
                } else {
                    this.responseError = data.error || 'Failed to send response.';
                }
            } finally { this.completing = false; }
        },
    };
}
</script>
@endpush
@endsection
