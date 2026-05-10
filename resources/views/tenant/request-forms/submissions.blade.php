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
                <button type="button"
                        onclick="navigator.clipboard.writeText('{{ $form->publicUrl() }}').then(()=>window.dispatchEvent(new CustomEvent('show-toast',{detail:{type:'success',message:'Public link copied'}}))).catch(()=>alert('{{ $form->publicUrl() }}'))"
                        style="display:flex;align-items:center;gap:6px;padding:8px 14px;border-radius:10px;background:#ede9fe;color:#7B61FF;border:none;font-size:13px;font-weight:600;cursor:pointer">
                    <svg style="width:13px;height:13px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Copy Link
                </button>
                @endif
                <a href="{{ route('tenant.request-forms.edit', [$tenant->id, $form->id]) }}"
                   style="display:flex;align-items:center;gap:6px;padding:8px 14px;border-radius:10px;background:white;border:1.5px solid #e5e7eb;color:#374151;font-size:13px;font-weight:600;text-decoration:none">
                    <svg style="width:13px;height:13px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Edit Form
                </a>
            </div>
        </div>
    </div>

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
            <div style="display:flex;align-items:center;gap:8px;justify-content:center;flex-wrap:wrap">
                <code style="font-size:12px;color:#7B61FF;background:#ede9fe;padding:6px 14px;border-radius:8px">{{ $form->publicUrl() }}</code>
                <button type="button"
                        onclick="navigator.clipboard.writeText('{{ $form->publicUrl() }}').then(()=>window.dispatchEvent(new CustomEvent('show-toast',{detail:{type:'success',message:'Link copied'}}))).catch(()=>alert('{{ $form->publicUrl() }}'))"
                        style="font-size:12px;font-weight:600;color:#7B61FF;background:#ede9fe;border:none;padding:6px 14px;border-radius:8px;cursor:pointer">Copy Link</button>
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
                    <th style="text-align:right;padding:10px 16px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase">View</th>
                </tr>
            </thead>
            <tbody>
                @foreach($submissions as $sub)
                @php
                $subStatus = match($sub->status) {
                    'tasks_created' => ['background:#dcfce7;color:#15803d', 'Tasks Created'],
                    'failed'        => ['background:#fee2e2;color:#dc2626', 'Failed'],
                    default         => ['background:#f3f4f6;color:#6b7280', ucfirst(str_replace('_',' ',$sub->status))],
                };
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
                        @if($sub->tasks_count)
                        <span style="font-size:12px;font-weight:700;background:#ede9fe;color:#7B61FF;padding:2px 8px;border-radius:9999px">{{ $sub->tasks_count }}</span>
                        @else
                        <span style="font-size:12px;color:#d1d5db">0</span>
                        @endif
                    </td>
                    <td style="padding:12px 16px">
                        <span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:9999px;{{ $subStatus[0] }}">{{ $subStatus[1] }}</span>
                    </td>
                    <td style="padding:12px 16px;font-size:12px;color:#9ca3af;white-space:nowrap">
                        {{ $sub->submitted_at ? $sub->submitted_at->format('M j, Y g:i A') : '—' }}
                    </td>
                    <td style="padding:12px 16px;text-align:right">
                        <a href="{{ route('tenant.request-forms.submissions.show', [$tenant->id, $form->id, $sub->id]) }}"
                           style="display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:600;color:#7B61FF;background:#ede9fe;padding:5px 12px;border-radius:8px;text-decoration:none">
                            <svg style="width:11px;height:11px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            View
                        </a>
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
@endsection
