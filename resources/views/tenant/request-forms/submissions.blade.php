@extends('layouts.app')
@section('title', 'Responses — ' . $form->title)
@section('nav') @include('tenant._nav') @endsection

@section('content')
<div class="space-y-5">

    {{-- Header --}}
    <div>
        <a href="{{ route('tenant.request-forms', $tenant->id) }}"
           style="font-size:13px;color:#9ca3af;text-decoration:none;display:inline-flex;align-items:center;gap:4px">
            <svg style="width:12px;height:12px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Request Forms
        </a>
        <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-top:8px">
            <div>
                <h1 style="font-size:20px;font-weight:700;color:#1E1B4B">Responses: {{ $form->title }}</h1>
                <div style="display:flex;align-items:center;gap:10px;margin-top:4px;flex-wrap:wrap">
                    @php $statusStyle = match($form->status) {
                        'published'   => 'background:#dcfce7;color:#15803d',
                        'draft'       => 'background:#f3f4f6;color:#6b7280',
                        'unpublished' => 'background:#fef3c7;color:#d97706',
                        'archived'    => 'background:#fee2e2;color:#dc2626',
                        default       => 'background:#f3f4f6;color:#6b7280',
                    }; @endphp
                    <span style="font-size:11px;font-weight:700;padding:2px 10px;border-radius:9999px;{{ $statusStyle }}">{{ ucfirst($form->status) }}</span>
                    <span style="font-size:13px;color:#9ca3af">{{ $submissions->total() }} response{{ $submissions->total() === 1 ? '' : 's' }}</span>
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                @if($form->status === 'published')
                <div x-data="{ copied: false }" style="display:inline-flex">
                    <button type="button"
                            @click="navigator.clipboard.writeText('{{ $form->publicUrl() }}').then(()=>{ copied=true; setTimeout(()=>copied=false,2000); }).catch(()=>alert('{{ $form->publicUrl() }}'))"
                            style="display:flex;align-items:center;gap:6px;padding:8px 14px;border-radius:10px;border:none;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s"
                            :style="copied ? 'background:#dcfce7;color:#15803d' : 'background:#ede9fe;color:#7B61FF'">
                        <template x-if="!copied">
                            <svg style="width:13px;height:13px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </template>
                        <template x-if="copied">
                            <svg style="width:13px;height:13px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </template>
                        <span x-text="copied ? 'Copied!' : 'Copy Link'"></span>
                    </button>
                </div>
                @endif
                <a href="{{ route('tenant.request-forms.edit', [$tenant->id, $form->id]) }}"
                   style="display:flex;align-items:center;gap:6px;padding:8px 14px;border-radius:10px;background:white;border:1.5px solid #e5e7eb;color:#374151;font-size:13px;font-weight:600;text-decoration:none">
                    <svg style="width:13px;height:13px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Edit Form
                </a>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div style="padding:12px 16px;background:#dcfce7;border:1px solid #86efac;border-radius:12px;color:#15803d;font-size:13px;font-weight:600">
        {{ session('success') }}
    </div>
    @endif

    {{-- Error banner (shown when query failed) --}}
    @if(!empty($submissionsError))
    <div style="display:flex;align-items:flex-start;gap:10px;padding:12px 16px;background:#fef2f2;border:1.5px solid #fecaca;border-radius:12px">
        <svg style="width:16px;height:16px;color:#dc2626;flex-shrink:0;margin-top:1px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div>
            <p style="font-size:13px;font-weight:600;color:#dc2626">Could not load responses</p>
            <p style="font-size:12px;color:#b91c1c;margin-top:2px">{{ $submissionsError }}</p>
        </div>
    </div>
    @endif

    {{-- Search + Filters --}}
    <form method="GET" action="{{ route('tenant.request-forms.submissions', [$tenant->id, $form->id]) }}"
          style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <div style="position:relative;flex:1;min-width:200px">
            <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);width:14px;height:14px;color:#9ca3af" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" name="q" value="{{ $search }}"
                   placeholder="Search by name, email, request type…"
                   style="width:100%;padding:8px 12px 8px 32px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;color:#1E1B4B;background:white;outline:none;box-sizing:border-box">
        </div>
        <input type="date" name="date_from" value="{{ $dateFrom }}"
               style="padding:8px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;color:#374151;background:white;outline:none"
               placeholder="From">
        <input type="date" name="date_to" value="{{ $dateTo }}"
               style="padding:8px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;color:#374151;background:white;outline:none"
               placeholder="To">
        <button type="submit" style="padding:8px 16px;border-radius:10px;background:#7B61FF;color:white;border:none;font-size:13px;font-weight:600;cursor:pointer">Filter</button>
        @if($search || $dateFrom || $dateTo)
        <a href="{{ route('tenant.request-forms.submissions', [$tenant->id, $form->id]) }}"
           style="font-size:12px;color:#9ca3af;text-decoration:none;padding:8px 12px;border:1.5px solid #e5e7eb;border-radius:10px;background:white">Clear</a>
        @endif
    </form>

    {{-- Submissions list --}}
    <div class="card" style="padding:0;overflow:hidden">
        @if($submissions->isEmpty())
        <div style="text-align:center;padding:48px 24px">
            <svg style="width:40px;height:40px;color:#d1d5db;margin:0 auto 12px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            <p style="font-size:14px;font-weight:600;color:#1E1B4B;margin-bottom:4px">No responses yet</p>
            <p style="font-size:13px;color:#9ca3af;margin-bottom:16px">
                Once people submit this public form, their responses will appear here.
            </p>
            @if($form->status === 'published')
            <div style="display:flex;align-items:center;gap:8px;justify-content:center;flex-wrap:wrap" x-data="{ copied: false }">
                <code style="font-size:12px;color:#7B61FF;background:#ede9fe;padding:6px 14px;border-radius:8px">{{ $form->publicUrl() }}</code>
                <button type="button"
                        @click="navigator.clipboard.writeText('{{ $form->publicUrl() }}').then(()=>{ copied=true; setTimeout(()=>copied=false,2000); }).catch(()=>alert('{{ $form->publicUrl() }}'))"
                        style="font-size:12px;font-weight:600;border:none;padding:6px 14px;border-radius:8px;cursor:pointer;transition:all .2s"
                        :style="copied ? 'background:#dcfce7;color:#15803d' : 'background:#ede9fe;color:#7B61FF'"
                        x-text="copied ? 'Copied ✓' : 'Copy Link'"></button>
            </div>
            @else
            <a href="{{ route('tenant.request-forms.edit', [$tenant->id, $form->id]) }}"
               style="display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:10px;background:#7B61FF;color:white;font-size:13px;font-weight:600;text-decoration:none">
                Publish Form to Start Receiving Responses
            </a>
            @endif
        </div>
        @else

        {{-- Desktop table --}}
        <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;min-width:700px">
            <thead>
                <tr style="background:#f9fafb;border-bottom:1px solid #f3f4f6">
                    <th style="text-align:left;padding:10px 16px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase">Submitter</th>
                    <th style="text-align:left;padding:10px 16px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase">Request For</th>
                    <th style="text-align:left;padding:10px 16px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase">Recipients</th>
                    <th style="text-align:center;padding:10px 16px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase">Tasks</th>
                    <th style="text-align:left;padding:10px 16px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase">Status</th>
                    <th style="text-align:left;padding:10px 16px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase">Submitted</th>
                    <th style="text-align:right;padding:10px 16px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($submissions as $sub)
                @php
                $tasksTotal     = (int) ($sub->tasks_count ?? 0);
                $tasksDone      = (int) ($sub->completed_tasks_count ?? 0);
                $latestStatus   = $sub->latest_task_status ?? null;

                // Derive a meaningful status combining submission status + task completion state
                if ($tasksTotal > 0 && $tasksDone >= $tasksTotal) {
                    // All tasks completed
                    $subStatus = ['background:#dcfce7;color:#15803d', 'Completed'];
                } elseif ($tasksTotal > 0 && $tasksDone > 0) {
                    // Partially completed
                    $subStatus = ['background:#d1fae5;color:#065f46', 'Partially Done'];
                } elseif ($latestStatus === 'in_progress') {
                    $subStatus = ['background:#dbeafe;color:#1d4ed8', 'In Progress'];
                } elseif ($latestStatus === 'cancelled') {
                    $subStatus = ['background:#fef3c7;color:#92400e', 'Cancelled'];
                } elseif ($latestStatus === 'archived') {
                    $subStatus = ['background:#f3f4f6;color:#6b7280', 'Archived'];
                } elseif ($sub->status === 'tasks_created') {
                    // Tasks exist but none started or completed
                    $subStatus = ['background:#ede9fe;color:#6d28d9', 'Tasks Created'];
                } elseif ($sub->status === 'failed') {
                    $subStatus = ['background:#fee2e2;color:#dc2626', 'Failed'];
                } else {
                    $subStatus = ['background:#f3f4f6;color:#6b7280', ucfirst(str_replace('_', ' ', $sub->status ?? 'pending'))];
                }
                @endphp
                <tr style="border-bottom:1px solid #f9fafb;transition:background .1s"
                    onmouseover="this.style.background='#fafafa'" onmouseout="this.style.background='white'">
                    <td style="padding:12px 16px">
                        <p style="font-size:13px;font-weight:600;color:#1E1B4B">{{ $sub->submitter_name ?: '—' }}</p>
                        <p style="font-size:11px;color:#9ca3af">{{ $sub->submitter_email ?: '—' }}</p>
                    </td>
                    <td style="padding:12px 16px;font-size:13px;color:#374151;max-width:160px">
                        <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block">{{ $sub->request_for ?: '—' }}</span>
                    </td>
                    <td style="padding:12px 16px">
                        @foreach($sub->submissionRecipients as $rec)
                        <p style="font-size:12px;color:#374151;white-space:nowrap">{{ $rec->recipient_name }}</p>
                        @endforeach
                        @if($sub->submissionRecipients->isEmpty())
                        <span style="font-size:12px;color:#d1d5db">—</span>
                        @endif
                    </td>
                    <td style="padding:12px 16px;text-align:center">
                        @if($tasksTotal > 0)
                            @if($tasksDone >= $tasksTotal)
                            {{-- All done — green --}}
                            <span style="font-size:11px;font-weight:700;background:#dcfce7;color:#15803d;padding:2px 8px;border-radius:9999px;display:inline-flex;align-items:center;gap:3px">
                                <svg style="width:10px;height:10px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                {{ $tasksDone }}/{{ $tasksTotal }}
                            </span>
                            @elseif($tasksDone > 0)
                            {{-- Partial -- amber --}}
                            <span style="font-size:11px;font-weight:700;background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:9999px">{{ $tasksDone }}/{{ $tasksTotal }}</span>
                            @else
                            {{-- None done yet -- purple --}}
                            <span style="font-size:11px;font-weight:700;background:#ede9fe;color:#7B61FF;padding:2px 8px;border-radius:9999px">{{ $tasksTotal }}</span>
                            @endif
                        @else
                        <span style="font-size:12px;color:#d1d5db">—</span>
                        @endif
                    </td>
                    <td style="padding:12px 16px">
                        <span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:9999px;{{ $subStatus[0] }}">{{ $subStatus[1] }}</span>
                    </td>
                    <td style="padding:12px 16px;font-size:12px;color:#9ca3af;white-space:nowrap">
                        {{ $sub->submitted_at ? $sub->submitted_at->format('M j, Y g:i A') : '—' }}
                    </td>
                    <td style="padding:12px 16px;text-align:right">
                        <div style="display:inline-flex;align-items:center;gap:6px">
                            <a href="{{ route('tenant.request-forms.submissions.show', [$tenant->id, $form->id, $sub->id]) }}"
                               style="display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:600;color:#7B61FF;background:#ede9fe;padding:5px 12px;border-radius:8px;text-decoration:none">
                                <svg style="width:11px;height:11px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                View
                            </a>
                            <button type="button"
                                    onclick="rbConfirmDeleteSubmission('{{ $sub->id }}',{{ json_encode($sub->submitter_name ?: $sub->submitter_email ?: 'this response', JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) }},'{{ route('tenant.request-forms.submissions.destroy', [$tenant->id, $form->id, $sub->id]) }}')"
                                    style="display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:600;color:#dc2626;background:#fee2e2;padding:5px 10px;border-radius:8px;border:none;cursor:pointer"
                                    title="Delete this response">
                                <svg style="width:11px;height:11px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>

        {{-- Mobile cards --}}
        <div style="display:none" id="rf-mobile-submissions">
            {{-- Progressive enhancement: shown on mobile via CSS --}}
        </div>

        <div style="padding:12px 16px">{{ $submissions->links() }}</div>
        @endif
    </div>
</div>

{{-- Delete Response Confirmation Modal --}}
<div id="rb-del-sub-modal"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:16px">
    <div style="background:white;border-radius:20px;padding:32px;max-width:400px;width:100%;text-align:center;box-shadow:0 24px 64px rgba(0,0,0,0.18)">
        <div style="width:48px;height:48px;border-radius:14px;background:#fee2e2;display:flex;align-items:center;justify-content:center;margin:0 auto 14px">
            <svg style="width:22px;height:22px;color:#dc2626" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
        </div>
        <p style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#dc2626;margin-bottom:6px">Delete Response</p>
        <h3 id="rb-del-sub-name" style="font-size:16px;font-weight:700;color:#1E1B4B;margin-bottom:8px"></h3>
        <p style="font-size:13px;color:#9ca3af;margin-bottom:24px;line-height:1.6">This will permanently remove this submission. This cannot be undone.</p>
        <div style="display:flex;gap:10px">
            <button onclick="document.getElementById('rb-del-sub-modal').style.display='none'"
                    style="flex:1;padding:10px;border-radius:12px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:13px;font-weight:600;cursor:pointer">
                Cancel
            </button>
            <button id="rb-del-sub-btn"
                    style="flex:1;padding:10px;border-radius:12px;background:#dc2626;color:white;border:none;font-size:13px;font-weight:600;cursor:pointer">
                Delete
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
function rbConfirmDeleteSubmission(subId, name, actionUrl) {
    document.getElementById('rb-del-sub-name').textContent = name;
    document.getElementById('rb-del-sub-modal').style.display = 'flex';
    document.getElementById('rb-del-sub-btn').onclick = function() {
        this.textContent = 'Deleting…';
        this.disabled = true;
        const f = document.createElement('form');
        f.method = 'POST';
        f.action = actionUrl;
        f.innerHTML = '<input name="_token" value="{{ csrf_token() }}"><input name="_method" value="DELETE">';
        document.body.appendChild(f);
        f.submit();
    };
}
</script>
@endpush
@endsection
