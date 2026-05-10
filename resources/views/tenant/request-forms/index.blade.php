@extends('layouts.app')
@section('title', 'Request Forms')
@section('nav') @include('tenant._nav') @endsection

@section('content')
<div class="space-y-5">

    {{-- Header --}}
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div>
            <h1 style="font-size:20px;font-weight:700;color:#1E1B4B">Request Forms</h1>
            <p style="font-size:13px;color:#9ca3af;margin-top:2px">Create, manage, and review public request forms for your tenant.</p>
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

    {{-- Search + Filters --}}
    <form method="GET" action="{{ route('tenant.request-forms', $tenant->id) }}"
          style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        {{-- Search --}}
        <div style="position:relative;flex:1;min-width:200px">
            <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);width:14px;height:14px;color:#9ca3af" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" name="q" value="{{ $search }}"
                   placeholder="Search forms…"
                   style="width:100%;padding:8px 12px 8px 32px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;color:#1E1B4B;background:white;outline:none;box-sizing:border-box">
        </div>
        {{-- Status filter --}}
        <div style="display:flex;gap:4px;background:white;border:1.5px solid #e5e7eb;border-radius:10px;padding:3px">
            @foreach([['all','All'],['draft','Draft'],['published','Published'],['unpublished','Unpublished'],['archived','Archived']] as [$val,$lbl])
            <a href="{{ request()->fullUrlWithQuery(['status'=>$val,'q'=>$search,'sort'=>$sort]) }}"
               style="padding:5px 12px;border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;transition:all .12s;
                      {{ $status === $val ? 'background:#7B61FF;color:white' : 'color:#9ca3af' }}">{{ $lbl }}</a>
            @endforeach
        </div>
        {{-- Sort --}}
        <select name="sort" onchange="this.form.submit()"
                style="padding:8px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;color:#374151;background:white;outline:none">
            <option value="updated"  {{ $sort==='updated'  ? 'selected' : '' }}>Recently Updated</option>
            <option value="created"  {{ $sort==='created'  ? 'selected' : '' }}>Recently Created</option>
            <option value="title"    {{ $sort==='title'    ? 'selected' : '' }}>A → Z</option>
            <option value="responses"{{ $sort==='responses' ? 'selected' : '' }}>Most Responses</option>
            <option value="last"     {{ $sort==='last'     ? 'selected' : '' }}>Last Response</option>
        </select>
        @if($search || ($status && $status !== 'all'))
        <a href="{{ route('tenant.request-forms', $tenant->id) }}"
           style="font-size:12px;color:#9ca3af;text-decoration:none;padding:8px 12px;border:1.5px solid #e5e7eb;border-radius:10px;background:white">Clear</a>
        @endif
    </form>

    {{-- Forms List --}}
    <div class="card" style="padding:0;overflow:hidden">
        @if($forms->isEmpty())
        <div style="text-align:center;padding:56px 24px">
            <svg style="width:44px;height:44px;color:#d1d5db;margin:0 auto 14px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <p style="font-size:15px;font-weight:700;color:#1E1B4B;margin-bottom:6px">No request forms yet</p>
            <p style="font-size:13px;color:#9ca3af;margin-bottom:20px">Create a public request form to collect requests and automatically assign them as tasks.</p>
            <a href="{{ route('tenant.request-forms.create', $tenant->id) }}"
               style="display:inline-flex;align-items:center;gap:6px;padding:10px 22px;border-radius:12px;background:#7B61FF;color:white;font-size:13px;font-weight:600;text-decoration:none">
                <svg style="width:14px;height:14px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create Your First Form
            </a>
        </div>
        @else
        {{-- Desktop table --}}
        <div style="display:none" class="hidden md:block" id="rf-table">
        </div>
        <table style="width:100%;border-collapse:collapse">
            <thead>
                <tr style="background:#f9fafb;border-bottom:1px solid #f3f4f6">
                    <th style="text-align:left;padding:10px 16px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em">Form</th>
                    <th style="text-align:left;padding:10px 16px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em">Status</th>
                    <th style="text-align:center;padding:10px 16px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em">Responses</th>
                    <th style="text-align:left;padding:10px 16px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em">Last Response</th>
                    <th style="text-align:right;padding:10px 16px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($forms as $form)
                @php
                $statusStyle = match($form->status) {
                    'published'   => 'background:#dcfce7;color:#15803d',
                    'draft'       => 'background:#f3f4f6;color:#6b7280',
                    'unpublished' => 'background:#fef3c7;color:#d97706',
                    'archived'    => 'background:#fee2e2;color:#dc2626',
                    default       => 'background:#f3f4f6;color:#6b7280',
                };
                $lastResp = $form->submissions_max_submitted_at
                    ? \Carbon\Carbon::parse($form->submissions_max_submitted_at)
                    : null;
                @endphp
                <tr style="border-bottom:1px solid #f9fafb" x-data="{ actionsOpen: false }" @click.outside="actionsOpen = false">
                    <td style="padding:14px 16px;max-width:280px">
                        <div style="display:flex;align-items:flex-start;gap:10px">
                            <div style="width:32px;height:32px;border-radius:10px;background:#ede9fe;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                <svg style="width:14px;height:14px;color:#7B61FF" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/></svg>
                            </div>
                            <div style="min-width:0">
                                <p style="font-size:14px;font-weight:600;color:#1E1B4B;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $form->title }}</p>
                                @if($form->description)
                                <p style="font-size:11px;color:#9ca3af;margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ Str::limit($form->description, 55) }}</p>
                                @endif
                                @if($form->status === 'published')
                                <div style="display:flex;align-items:center;gap:5px;margin-top:5px">
                                    <code style="font-size:10px;color:#7B61FF;background:#ede9fe;padding:2px 7px;border-radius:5px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:inline-block">{{ $form->publicUrl() }}</code>
                                    <button type="button"
                                            onclick="navigator.clipboard.writeText('{{ $form->publicUrl() }}').then(()=>window.dispatchEvent(new CustomEvent('show-toast',{detail:{type:'success',message:'Public link copied to clipboard'}}))).catch(()=>alert('{{ $form->publicUrl() }}'))"
                                            style="font-size:10px;color:#7B61FF;background:#ede9fe;border:none;cursor:pointer;padding:2px 8px;border-radius:5px;flex-shrink:0;font-weight:600">Copy</button>
                                </div>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td style="padding:14px 16px">
                        <span style="font-size:11px;font-weight:700;padding:3px 10px;border-radius:9999px;white-space:nowrap;{{ $statusStyle }}">{{ ucfirst($form->status) }}</span>
                    </td>
                    <td style="padding:14px 16px;text-align:center">
                        <a href="{{ route('tenant.request-forms.submissions', [$tenant->id, $form->id]) }}"
                           style="font-size:14px;font-weight:700;color:#7B61FF;text-decoration:none">
                            {{ $form->submissions_count ?? 0 }}
                        </a>
                    </td>
                    <td style="padding:14px 16px;text-align:center;font-size:14px;font-weight:600;color:#374151">
                        {{ $form->submissions_count ?? 0 }}
                    </td>
                    <td style="padding:14px 16px;font-size:12px;color:#9ca3af;white-space:nowrap">
                        {{ $lastResp ? $lastResp->format('M j, Y') : '—' }}
                    </td>
                    <td style="padding:14px 16px;text-align:right">
                        <div style="position:relative;display:inline-block">
                            <button @click.stop="actionsOpen = !actionsOpen"
                                    style="display:flex;align-items:center;gap:5px;padding:6px 12px;border-radius:8px;border:1.5px solid #e5e7eb;background:white;font-size:12px;font-weight:600;color:#374151;cursor:pointer">
                                Actions
                                <svg style="width:12px;height:12px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>

                            {{-- Dropdown --}}
                            <div x-show="actionsOpen"
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 style="display:none;position:absolute;right:0;top:calc(100% + 4px);z-index:200;background:white;border:1.5px solid #f3f4f6;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,0.12);min-width:170px;padding:6px">

                                <a href="{{ route('tenant.request-forms.edit', [$tenant->id, $form->id]) }}"
                                   style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;font-size:13px;color:#374151;text-decoration:none;transition:background .1s"
                                   onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='transparent'">
                                    <svg style="width:13px;height:13px;color:#6b7280" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    Edit Form
                                </a>

                                <a href="{{ route('tenant.request-forms.submissions', [$tenant->id, $form->id]) }}"
                                   style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;font-size:13px;color:#374151;text-decoration:none;transition:background .1s"
                                   onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='transparent'">
                                    <svg style="width:13px;height:13px;color:#6b7280" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    View Responses
                                    @if($form->submissions_count)
                                    <span style="margin-left:auto;font-size:10px;font-weight:700;background:#ede9fe;color:#7B61FF;padding:1px 6px;border-radius:9999px">{{ $form->submissions_count }}</span>
                                    @endif
                                </a>

                                @if($form->status === 'published')
                                <button type="button"
                                        onclick="navigator.clipboard.writeText('{{ $form->publicUrl() }}').then(()=>{window.dispatchEvent(new CustomEvent('show-toast',{detail:{type:'success',message:'Public link copied to clipboard'}}));}).catch(()=>alert('{{ $form->publicUrl() }}'))"
                                        style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;font-size:13px;color:#374151;background:none;border:none;cursor:pointer;width:100%;transition:background .1s;text-align:left"
                                        onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='transparent'">
                                    <svg style="width:13px;height:13px;color:#6b7280" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                                    Copy Public Link
                                </button>
                                @endif

                                <a href="{{ $form->publicUrl() }}" target="_blank"
                                   style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;font-size:13px;color:#374151;text-decoration:none;transition:background .1s"
                                   onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='transparent'">
                                    <svg style="width:13px;height:13px;color:#6b7280" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    Preview Form
                                </a>

                                <div style="height:1px;background:#f3f4f6;margin:4px 0"></div>

                                {{-- Publish / Unpublish --}}
                                @if($form->status !== 'published')
                                <form method="POST" action="{{ route('tenant.request-forms.publish', [$tenant->id, $form->id]) }}" style="margin:0">
                                    @csrf
                                    <button type="submit"
                                            style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;font-size:13px;color:#15803d;background:none;border:none;cursor:pointer;width:100%;transition:background .1s;text-align:left"
                                            onmouseover="this.style.background='#f0fdf4'" onmouseout="this.style.background='transparent'">
                                        <svg style="width:13px;height:13px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Publish
                                    </button>
                                </form>
                                @else
                                <form method="POST" action="{{ route('tenant.request-forms.unpublish', [$tenant->id, $form->id]) }}" style="margin:0">
                                    @csrf
                                    <button type="submit"
                                            style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;font-size:13px;color:#d97706;background:none;border:none;cursor:pointer;width:100%;transition:background .1s;text-align:left"
                                            onmouseover="this.style.background='#fffbeb'" onmouseout="this.style.background='transparent'">
                                        <svg style="width:13px;height:13px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                        Unpublish
                                    </button>
                                </form>
                                @endif

                                {{-- Duplicate --}}
                                <form method="POST" action="{{ route('tenant.request-forms.duplicate', [$tenant->id, $form->id]) }}" style="margin:0">
                                    @csrf
                                    <button type="submit"
                                            style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;font-size:13px;color:#374151;background:none;border:none;cursor:pointer;width:100%;transition:background .1s;text-align:left"
                                            onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='transparent'">
                                        <svg style="width:13px;height:13px;color:#6b7280" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                        Duplicate
                                    </button>
                                </form>

                                <div style="height:1px;background:#f3f4f6;margin:4px 0"></div>

                                {{-- Archive --}}
                                <form method="POST" action="{{ route('tenant.request-forms.destroy', [$tenant->id, $form->id]) }}" style="margin:0"
                                      onsubmit="return confirm('Archive this form? It will no longer accept submissions.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;font-size:13px;color:#dc2626;background:none;border:none;cursor:pointer;width:100%;transition:background .1s;text-align:left"
                                            onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background='transparent'">
                                        <svg style="width:13px;height:13px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1 12a2 2 0 002 2h8a2 2 0 002-2L19 8m-9 4v6m4-6v6"/></svg>
                                        Archive
                                    </button>
                                </form>

                            </div>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Mobile cards --}}
        {{-- (Same data shown as cards on small screens via the table's responsive behavior) --}}

        <div style="padding:12px 16px">{{ $forms->links() }}</div>
        @endif
    </div>

</div>
@endsection
