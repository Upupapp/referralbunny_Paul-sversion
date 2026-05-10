@extends('layouts.app')
@section('title', 'Submissions — ' . $form->title)
@section('nav') @include('tenant._nav') @endsection

@section('content')
<div class="space-y-5">
    <div>
        <a href="{{ route('tenant.request-forms', $tenant->id) }}" style="font-size:13px;color:#9ca3af;text-decoration:none">&larr; Request Forms</a>
        <h1 style="font-size:18px;font-weight:700;color:#1E1B4B;margin-top:8px">Submissions: {{ $form->title }}</h1>
    </div>

    <div class="card" style="padding:0;overflow:hidden">
        @if($submissions->isEmpty())
        <div style="text-align:center;padding:40px">
            <p style="font-size:14px;color:#9ca3af">No submissions yet. Share the form link to start receiving requests.</p>
            @if($form->status === 'published')
            <div style="display:flex;align-items:center;gap:8px;justify-content:center;margin-top:16px">
                <code style="font-size:12px;color:#7B61FF;background:#ede9fe;padding:6px 14px;border-radius:8px">{{ $form->publicUrl() }}</code>
                <button onclick="navigator.clipboard.writeText('{{ $form->publicUrl() }}').then(()=>alert('Copied!'))"
                        style="font-size:12px;font-weight:600;color:#7B61FF;background:#ede9fe;border:none;padding:6px 14px;border-radius:8px;cursor:pointer">Copy Link</button>
            </div>
            @endif
        </div>
        @else
        <table style="width:100%;border-collapse:collapse">
            <thead>
                <tr style="background:#f9fafb;border-bottom:1px solid #f3f4f6">
                    <th style="text-align:left;padding:10px 16px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase">Submitter</th>
                    <th style="text-align:left;padding:10px 16px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase">Request For</th>
                    <th style="text-align:left;padding:10px 16px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase">Recipients</th>
                    <th style="text-align:left;padding:10px 16px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase">Status</th>
                    <th style="text-align:left;padding:10px 16px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase">Submitted</th>
                </tr>
            </thead>
            <tbody>
                @foreach($submissions as $sub)
                <tr style="border-bottom:1px solid #f9fafb">
                    <td style="padding:12px 16px">
                        <p style="font-size:13px;font-weight:600;color:#1E1B4B">{{ $sub->submitter_name }}</p>
                        <p style="font-size:11px;color:#9ca3af">{{ $sub->submitter_email }}</p>
                    </td>
                    <td style="padding:12px 16px;font-size:13px;color:#374151">{{ $sub->request_for ?: '—' }}</td>
                    <td style="padding:12px 16px">
                        @foreach($sub->submissionRecipients as $rec)
                        <p style="font-size:12px;color:#374151">{{ $rec->recipient_name }}</p>
                        @endforeach
                    </td>
                    <td style="padding:12px 16px">
                        <span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:9999px;background:#dcfce7;color:#15803d">
                            {{ str_replace('_', ' ', ucfirst($sub->status)) }}
                        </span>
                    </td>
                    <td style="padding:12px 16px;font-size:12px;color:#9ca3af">{{ $sub->submitted_at->format('M j, Y g:i A') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div style="padding:12px 16px">{{ $submissions->links() }}</div>
        @endif
    </div>
</div>
@endsection
