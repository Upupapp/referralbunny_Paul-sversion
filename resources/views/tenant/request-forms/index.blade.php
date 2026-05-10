@extends('layouts.app')
@section('title', 'Request Forms')
@section('nav') @include('tenant._nav') @endsection

@section('content')
<div class="space-y-5">

    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div>
            <h1 style="font-size:20px;font-weight:700;color:#1E1B4B">Request Forms</h1>
            <p style="font-size:13px;color:#9ca3af;margin-top:2px">Create public forms that generate tasks when submitted.</p>
        </div>
        <a href="{{ route('tenant.request-forms.create', $tenant->id) }}"
           style="display:inline-flex;align-items:center;gap:6px;padding:9px 20px;border-radius:12px;background:linear-gradient(135deg,#7B61FF,#5b4cdb);color:white;font-size:13px;font-weight:600;text-decoration:none;box-shadow:0 4px 14px rgba(123,97,255,0.3)">
            <svg style="width:14px;height:14px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Request Form
        </a>
    </div>

    @if(session('success'))
    <div style="padding:12px 16px;background:#dcfce7;border:1px solid #86efac;border-radius:12px;color:#15803d;font-size:13px;font-weight:600">
        {{ session('success') }}
    </div>
    @endif

    <div class="card" style="padding:0;overflow:hidden">
        @if($forms->isEmpty())
        <div style="text-align:center;padding:48px 24px">
            <svg style="width:40px;height:40px;color:#d1d5db;margin:0 auto 12px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <p style="font-size:14px;font-weight:600;color:#1E1B4B;margin-bottom:4px">No request forms yet</p>
            <p style="font-size:13px;color:#9ca3af;margin-bottom:16px">Create your first public request form to start receiving requests.</p>
            <a href="{{ route('tenant.request-forms.create', $tenant->id) }}"
               style="display:inline-flex;align-items:center;gap:6px;padding:9px 20px;border-radius:12px;background:#7B61FF;color:white;font-size:13px;font-weight:600;text-decoration:none">
                Create Your First Form
            </a>
        </div>
        @else
        <table style="width:100%;border-collapse:collapse">
            <thead>
                <tr style="background:#f9fafb;border-bottom:1px solid #f3f4f6">
                    <th style="text-align:left;padding:10px 16px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em">Form</th>
                    <th style="text-align:left;padding:10px 16px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em">Status</th>
                    <th style="text-align:right;padding:10px 16px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em">Submissions</th>
                    <th style="text-align:right;padding:10px 16px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($forms as $form)
                <tr style="border-bottom:1px solid #f9fafb">
                    <td style="padding:14px 16px">
                        <p style="font-size:14px;font-weight:600;color:#1E1B4B">{{ $form->title }}</p>
                        @if($form->description)
                        <p style="font-size:12px;color:#9ca3af;margin-top:2px">{{ Str::limit($form->description, 60) }}</p>
                        @endif
                        @if($form->status === 'published')
                        <div style="display:flex;align-items:center;gap:6px;margin-top:6px">
                            <code style="font-size:10px;color:#7B61FF;background:#ede9fe;padding:2px 8px;border-radius:6px;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:inline-block">
                                {{ $form->publicUrl() }}
                            </code>
                            <button onclick="navigator.clipboard.writeText('{{ $form->publicUrl() }}').then(()=>alert('Link copied!'))"
                                    style="font-size:10px;color:#7B61FF;background:none;border:none;cursor:pointer;padding:2px 6px;border-radius:4px;background:#ede9fe">Copy</button>
                        </div>
                        @endif
                    </td>
                    <td style="padding:14px 16px">
                        @php
                        $statusStyles = match($form->status) {
                            'published'   => 'background:#dcfce7;color:#15803d',
                            'draft'       => 'background:#f3f4f6;color:#6b7280',
                            'unpublished' => 'background:#fef3c7;color:#d97706',
                            'archived'    => 'background:#fee2e2;color:#dc2626',
                            default       => 'background:#f3f4f6;color:#6b7280',
                        };
                        @endphp
                        <span style="font-size:11px;font-weight:700;padding:3px 10px;border-radius:9999px;{{ $statusStyles }}">
                            {{ ucfirst($form->status) }}
                        </span>
                    </td>
                    <td style="padding:14px 16px;text-align:right;font-size:13px;font-weight:600;color:#1E1B4B">
                        {{ $form->submissions_count ?? 0 }}
                    </td>
                    <td style="padding:14px 16px;text-align:right">
                        <div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap">
                            @if($form->status !== 'published')
                            <form method="POST" action="{{ route('tenant.request-forms.publish', [$tenant->id, $form->id]) }}">
                                @csrf
                                <button type="submit" style="font-size:12px;font-weight:600;color:#16a34a;background:#dcfce7;border:none;padding:5px 12px;border-radius:8px;cursor:pointer">Publish</button>
                            </form>
                            @else
                            <form method="POST" action="{{ route('tenant.request-forms.unpublish', [$tenant->id, $form->id]) }}">
                                @csrf
                                <button type="submit" style="font-size:12px;font-weight:600;color:#d97706;background:#fef3c7;border:none;padding:5px 12px;border-radius:8px;cursor:pointer">Unpublish</button>
                            </form>
                            @endif
                            <a href="{{ route('tenant.request-forms.submissions', [$tenant->id, $form->id]) }}"
                               style="font-size:12px;font-weight:600;color:#7B61FF;background:#ede9fe;padding:5px 12px;border-radius:8px;text-decoration:none">Submissions</a>
                            <a href="{{ $form->publicUrl() }}" target="_blank"
                               style="font-size:12px;font-weight:600;color:#2563eb;background:#dbeafe;padding:5px 12px;border-radius:8px;text-decoration:none">Preview</a>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div style="padding:12px 16px">{{ $forms->links() }}</div>
        @endif
    </div>
</div>
@endsection
