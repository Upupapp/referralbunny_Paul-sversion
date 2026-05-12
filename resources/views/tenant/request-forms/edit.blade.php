@extends('layouts.app')
@section('title', 'Edit — ' . $form->title)
@section('nav') @include('tenant._nav') @endsection

@section('content')
<style>
@media (max-width:1023px) { .rf-edit-grid { grid-template-columns:1fr !important; } .rf-edit-sidebar { order:2; } }
</style>

{{-- ── Form-created success modal (shown once on first edit after creation) ── --}}
@if(session('form_created'))
<div x-data="{ open: true }" x-show="open" x-cloak
     style="position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:9999;display:flex;align-items:center;justify-content:center;padding:16px">
    <div style="background:white;border-radius:20px;padding:32px;max-width:420px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.15);text-align:center">
        <div style="width:56px;height:56px;border-radius:16px;background:#D1FAE5;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
            <svg style="width:28px;height:28px;color:#10B981" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
        </div>
        <p style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#10B981;margin-bottom:6px">Form Created</p>
        <h2 style="font-size:18px;font-weight:700;color:#1E1B4B;margin-bottom:6px">{{ $form->title }}</h2>
        <p style="font-size:13px;color:#9ca3af;margin-bottom:24px">Saved as draft. Publish it when you're ready so people can start submitting requests.</p>
        <div style="display:flex;flex-direction:column;gap:10px">
            <button @click="open=false; publishForm()"
                    id="created-publish-btn"
                    style="padding:11px 24px;border-radius:12px;background:linear-gradient(135deg,#7B61FF,#5b4cdb);color:white;border:none;font-size:13px;font-weight:600;cursor:pointer;box-shadow:0 4px 14px rgba(123,97,255,0.25)">
                Publish Now
            </button>
            <button @click="open=false"
                    style="padding:11px 24px;border-radius:12px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:13px;font-weight:600;cursor:pointer">
                Keep as Draft
            </button>
        </div>
    </div>
</div>
@endif

@if(session('success'))
<div style="padding:12px 16px;background:#dcfce7;border:1px solid #86efac;border-radius:12px;color:#15803d;font-size:13px;font-weight:600;margin-bottom:16px">
    {{ session('success') }}
</div>
@endif

{{-- ── Page header ──────────────────────────────────────────────────────────── --}}
<div style="margin-bottom:24px">
    <nav style="display:flex;align-items:center;gap:5px;font-size:12px;color:#9ca3af;margin-bottom:10px;flex-wrap:wrap">
        <a href="{{ route('tenant.request-forms', $tenant->id) }}" style="color:#9ca3af;text-decoration:none" onmouseover="this.style.color='#7B61FF'" onmouseout="this.style.color='#9ca3af'">Request Forms</a>
        <svg style="width:10px;height:10px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span style="color:#1E1B4B;font-weight:600;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:inline-block;vertical-align:middle">{{ Str::limit($form->title, 28) }}</span>
        <svg style="width:10px;height:10px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span style="color:#1E1B4B;font-weight:600">Settings</span>
    </nav>
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <h1 style="font-size:20px;font-weight:700;color:#1E1B4B;margin:0">Edit Form</h1>
                @php
                $sBg  = match($form->status) { 'published'=>'#dcfce7', 'draft'=>'#f3f4f6', 'unpublished'=>'#fef3c7', default=>'#f3f4f6' };
                $sClr = match($form->status) { 'published'=>'#15803d', 'draft'=>'#6b7280',  'unpublished'=>'#d97706', default=>'#6b7280' };
                @endphp
                <span style="font-size:11px;font-weight:700;padding:3px 10px;border-radius:9999px;background:{{ $sBg }};color:{{ $sClr }}">{{ ucfirst($form->status) }}</span>
            </div>
            <p style="font-size:13px;color:#9ca3af;margin-top:3px">{{ Str::limit($form->title, 50) }}</p>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <a href="{{ route('tenant.request-forms.submissions', [$tenant->id, $form->id]) }}"
               style="display:inline-flex;align-items:center;gap:5px;padding:7px 14px;border-radius:9px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:12px;font-weight:600;text-decoration:none;white-space:nowrap">
                <svg style="width:12px;height:12px;color:#6b7280" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Responses
                @if(isset($form->submissions_count) && $form->submissions_count)
                <span style="font-size:10px;font-weight:700;background:#ede9fe;color:#7B61FF;padding:1px 6px;border-radius:9999px">{{ $form->submissions_count }}</span>
                @endif
            </a>
            @if($form->isPublished())
            <a href="{{ $form->publicUrl() }}" target="_blank" rel="noopener"
               style="display:inline-flex;align-items:center;gap:5px;padding:7px 14px;border-radius:9px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:12px;font-weight:600;text-decoration:none;white-space:nowrap">
                <svg style="width:12px;height:12px;color:#6b7280" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                Preview
            </a>
            @endif
        </div>
    </div>
</div>

{{-- ── Two-column grid ──────────────────────────────────────────────────────── --}}
<div class="rf-edit-grid" style="display:grid;grid-template-columns:1fr 280px;gap:22px;align-items:start">

    {{-- ══ LEFT: MAIN CONTENT ══════════════════════════════════════════════════ --}}
    <div style="display:flex;flex-direction:column;gap:18px;min-width:0">

        {{-- Form Details ──────────────────────────────────────────────────────── --}}
        <div class="card">
            <form method="POST" action="{{ route('tenant.request-forms.update', [$tenant->id, $form->id]) }}" id="rf-settings-form">
                @csrf
                @method('PATCH')

                <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px">
                    <div style="width:30px;height:30px;border-radius:8px;background:#ede9fe;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <svg style="width:14px;height:14px;color:#7B61FF" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </div>
                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:#1E1B4B;margin:0">Form Details</h3>
                        <p style="font-size:11px;color:#9ca3af;margin:0">Title, description, and success message</p>
                    </div>
                </div>

                <div style="display:flex;flex-direction:column;gap:14px">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px">Form Title <span style="color:#ef4444">*</span></label>
                        <input type="text" name="title" required maxlength="120"
                               style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;color:#1E1B4B;background:white;outline:none;box-sizing:border-box;transition:border-color .15s"
                               value="{{ old('title', $form->title) }}"
                               onfocus="this.style.borderColor='#7B61FF'" onblur="this.style.borderColor='#e5e7eb'">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px">Description</label>
                        <textarea name="description" maxlength="500" rows="3"
                                  style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;color:#1E1B4B;background:white;outline:none;resize:vertical;box-sizing:border-box;transition:border-color .15s;line-height:1.6"
                                  placeholder="Briefly explain what this form is for…"
                                  onfocus="this.style.borderColor='#7B61FF'" onblur="this.style.borderColor='#e5e7eb'">{{ old('description', $form->description) }}</textarea>
                        <p style="font-size:11px;color:#9ca3af;margin-top:4px">Shown at the top of the public form.</p>
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px">Success Message</label>
                        <input type="text" name="success_message" maxlength="300"
                               style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;color:#1E1B4B;background:white;outline:none;box-sizing:border-box;transition:border-color .15s"
                               value="{{ old('success_message', $form->success_message) }}"
                               placeholder="Thank you. Your request has been submitted."
                               onfocus="this.style.borderColor='#7B61FF'" onblur="this.style.borderColor='#e5e7eb'">
                        <p style="font-size:11px;color:#9ca3af;margin-top:4px">Shown to the requester after submission.</p>
                    </div>
                </div>

                {{-- Save buttons inside form --}}
                <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid #f3f4f6">
                    <a href="{{ route('tenant.request-forms', $tenant->id) }}"
                       style="padding:9px 20px;border-radius:10px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:13px;font-weight:600;text-decoration:none">Cancel</a>
                    <button type="submit"
                            style="display:flex;align-items:center;gap:6px;padding:9px 22px;border-radius:10px;background:linear-gradient(135deg,#7B61FF,#5b4cdb);color:white;border:none;font-size:13px;font-weight:600;cursor:pointer;box-shadow:0 3px 10px rgba(123,97,255,0.25)"
                            onclick="this.textContent='Saving…';this.disabled=true;this.closest('form').submit()">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>

        {{-- Form Fields (read-only) ────────────────────────────────────────────── --}}
        <div class="card">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
                <div style="width:30px;height:30px;border-radius:8px;background:#dbeafe;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <svg style="width:14px;height:14px;color:#2563eb" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                </div>
                <div>
                    <h3 style="font-size:14px;font-weight:700;color:#1E1B4B;margin:0">Form Fields</h3>
                    <p style="font-size:11px;color:#9ca3af;margin:0">{{ $form->fields->count() }} {{ Str::plural('field', $form->fields->count()) }} configured</p>
                </div>
            </div>

            {{-- Lock notice --}}
            <div style="display:flex;align-items:flex-start;gap:10px;padding:10px 14px;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;margin-bottom:14px">
                <svg style="width:14px;height:14px;color:#d97706;flex-shrink:0;margin-top:1px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                <p style="font-size:12px;color:#92400e;margin:0;line-height:1.5">Field types are locked after creation to protect existing responses. To change field types, <a href="{{ route('tenant.request-forms.duplicate', [$tenant->id, $form->id]) }}" style="color:#d97706;font-weight:700;text-decoration:none" onclick="return confirm('Duplicate this form?')">duplicate this form</a>.</p>
            </div>

            {{-- Field cards --}}
            <div style="display:flex;flex-direction:column;gap:8px">
                @forelse($form->fields as $field)
                @php
                $typeBg  = match($field->field_type) { 'text'=>'#ede9fe','email'=>'#dbeafe','textarea'=>'#dcfce7','select'=>'#fff7ed','multi_select'=>'#fef3c7','radio'=>'#f3f4f6','number'=>'#ede9fe','date'=>'#f0fdf4', default=>'#f3f4f6' };
                $typeClr = match($field->field_type) { 'text'=>'#7B61FF','email'=>'#2563eb','textarea'=>'#15803d','select'=>'#ea580c','multi_select'=>'#d97706','radio'=>'#6b7280','number'=>'#7B61FF','date'=>'#15803d', default=>'#6b7280' };
                $typeLabel = match($field->field_type) { 'multi_select'=>'Multi-select', default=>ucfirst(str_replace('_',' ',$field->field_type)) };
                @endphp
                <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:#f9fafb;border-radius:10px;border:1px solid #f3f4f6">
                    <div style="width:26px;height:26px;border-radius:7px;background:{{ $typeBg }};color:{{ $typeClr }};font-size:9px;font-weight:700;text-transform:uppercase;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        {{ strtoupper(substr($typeLabel, 0, 3)) }}
                    </div>
                    <div style="flex:1;min-width:0">
                        <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap">
                            <span style="font-size:13px;font-weight:600;color:#1E1B4B">{{ $field->label }}</span>
                            <span style="font-size:10px;font-weight:600;color:{{ $typeClr }};background:{{ $typeBg }};padding:1px 7px;border-radius:9999px">{{ $typeLabel }}</span>
                            @if($field->is_required)
                            <span style="font-size:10px;font-weight:700;color:#ef4444;background:#fef2f2;padding:1px 7px;border-radius:9999px">Required</span>
                            @endif
                        </div>
                        @if($field->helper_text)
                        <p style="font-size:11px;color:#9ca3af;margin:2px 0 0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $field->helper_text }}</p>
                        @endif
                        @if($field->options && is_array($field->options) && count($field->options))
                        <p style="font-size:10px;color:#9ca3af;margin:2px 0 0">Options: {{ implode(', ', array_slice($field->options, 0, 4)) }}{{ count($field->options) > 4 ? '…' : '' }}</p>
                        @endif
                    </div>
                    <svg style="width:13px;height:13px;color:#d1d5db;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                </div>
                @empty
                <p style="font-size:13px;color:#9ca3af;font-style:italic;text-align:center;padding:16px 0">No fields configured.</p>
                @endforelse
            </div>
        </div>

        {{-- Recipients (editable) ──────────────────────────────────────────────── --}}
        <div class="card" x-data="{ selectedRecipients: {{ json_encode($form->recipientOptions->pluck('recipient_id')->filter()->values()) }} }">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
                <div style="width:30px;height:30px;border-radius:8px;background:#dcfce7;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <svg style="width:14px;height:14px;color:#15803d" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div>
                    <h3 style="font-size:14px;font-weight:700;color:#1E1B4B;margin:0">Recipients & Task Routing</h3>
                    <p style="font-size:11px;color:#9ca3af;margin:0">Editable — changes save with the form</p>
                </div>
            </div>

            <form method="POST" action="{{ route('tenant.request-forms.update', [$tenant->id, $form->id]) }}">
                @csrf
                @method('PATCH')
                {{-- Keep title/description/success_message so the PATCH doesn't blank them --}}
                <input type="hidden" name="title" value="{{ $form->title }}">
                <input type="hidden" name="description" value="{{ $form->description }}">
                <input type="hidden" name="success_message" value="{{ $form->success_message }}">

                @forelse($teamMembers as $member)
                @php
                $parts    = preg_split('/\s+/', trim($member->name ?? ''), 2);
                $initials = strtoupper(substr($parts[0] ?? '?', 0, 1)) . strtoupper(substr($parts[1] ?? '', 0, 1));
                @endphp
                <label style="display:flex;align-items:center;gap:12px;padding:11px 14px;background:#f9fafb;border-radius:12px;cursor:pointer;border:1.5px solid transparent;transition:all .12s;margin-bottom:8px"
                       :style="selectedRecipients.includes('{{ $member->id }}') ? 'border-color:#c4b5fd;background:#f5f3ff' : ''">
                    <input type="checkbox" value="{{ $member->id }}"
                           x-model="selectedRecipients"
                           style="width:15px;height:15px;accent-color:#7B61FF;cursor:pointer;flex-shrink:0">
                    <div style="width:34px;height:34px;border-radius:9999px;background:linear-gradient(135deg,#7B61FF,#5b4cdb);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:white;flex-shrink:0">{{ $initials ?: '?' }}</div>
                    <div style="flex:1;min-width:0">
                        <p style="font-size:13px;font-weight:600;color:#1E1B4B;margin:0">{{ $member->name }}</p>
                        <p style="font-size:11px;color:#9ca3af;margin:0">{{ $member->email }} · {{ ucfirst($member->role) }}</p>
                    </div>
                    <template x-if="selectedRecipients.includes('{{ $member->id }}')">
                        <span>
                            <input type="hidden" name="recipient_data[{{ $member->id }}][recipient_id]"   value="{{ $member->id }}">
                            <input type="hidden" name="recipient_data[{{ $member->id }}][display_name]"  value="{{ $member->name }}">
                            <input type="hidden" name="recipient_data[{{ $member->id }}][email]"         value="{{ $member->email }}">
                            <input type="hidden" name="recipient_data[{{ $member->id }}][role_snapshot]" value="{{ $member->role }}">
                        </span>
                    </template>
                </label>
                @empty
                <p style="font-size:13px;color:#d97706">No active Admins or Managers found.</p>
                @endforelse

                <div style="display:flex;justify-content:flex-end;margin-top:14px;padding-top:12px;border-top:1px solid #f3f4f6">
                    <button type="submit"
                            style="display:flex;align-items:center;gap:6px;padding:8px 20px;border-radius:10px;background:#7B61FF;color:white;border:none;font-size:13px;font-weight:600;cursor:pointer">
                        Save Recipients
                    </button>
                </div>
            </form>
        </div>

    </div>{{-- END LEFT --}}

    {{-- ══ RIGHT: SIDEBAR ══════════════════════════════════════════════════════ --}}
    <div class="rf-edit-sidebar" style="display:flex;flex-direction:column;gap:14px">

        {{-- Form Status ────────────────────────────────────────────────────────── --}}
        <div class="card" style="padding:18px">
            <h3 style="font-size:12px;font-weight:700;color:#1E1B4B;text-transform:uppercase;letter-spacing:.05em;margin:0 0 14px">Form Status</h3>
            <div style="display:flex;flex-direction:column;gap:12px">
                {{-- Status row --}}
                <div style="display:flex;align-items:center;justify-content:space-between">
                    <span style="font-size:12px;color:#9ca3af">Status</span>
                    <span style="font-size:11px;font-weight:700;padding:2px 9px;border-radius:9999px;background:{{ $sBg }};color:{{ $sClr }}">{{ ucfirst($form->status) }}</span>
                </div>
                {{-- Publish / Unpublish toggle --}}
                @if($form->status !== 'published')
                <form method="POST" action="{{ route('tenant.request-forms.publish', [$tenant->id, $form->id]) }}">
                    @csrf
                    <button type="submit"
                            style="display:flex;align-items:center;justify-content:center;gap:6px;width:100%;padding:9px;border-radius:10px;background:#15803d;color:white;border:none;font-size:12px;font-weight:600;cursor:pointer">
                        <svg style="width:12px;height:12px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Publish Form
                    </button>
                </form>
                @else
                <form method="POST" action="{{ route('tenant.request-forms.unpublish', [$tenant->id, $form->id]) }}">
                    @csrf
                    <button type="submit"
                            style="display:flex;align-items:center;justify-content:center;gap:6px;width:100%;padding:9px;border-radius:10px;background:white;border:1.5px solid #fde68a;color:#d97706;font-size:12px;font-weight:600;cursor:pointer">
                        <svg style="width:12px;height:12px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                        Unpublish
                    </button>
                </form>
                @endif
            </div>
        </div>

        {{-- Public Link ─────────────────────────────────────────────────────────── --}}
        @if($form->isPublished())
        <div class="card" style="padding:18px">
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:12px">
                <div style="width:8px;height:8px;border-radius:9999px;background:#22c55e;flex-shrink:0"></div>
                <h3 style="font-size:12px;font-weight:700;color:#1E1B4B;text-transform:uppercase;letter-spacing:.05em;margin:0">Public Link Active</h3>
            </div>
            <div style="display:flex;align-items:center;gap:6px;padding:8px 10px;background:#f9fafb;border-radius:8px;border:1px solid #f3f4f6;margin-bottom:10px" x-data="{ copied: false }">
                <a href="{{ $form->publicUrl() }}" target="_blank"
                   style="flex:1;font-size:11px;color:#7B61FF;text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0">{{ $form->publicUrl() }}</a>
                <button type="button"
                        @click="navigator.clipboard.writeText('{{ $form->publicUrl() }}').then(()=>{ copied=true; setTimeout(()=>copied=false,2000); })"
                        style="font-size:10px;font-weight:700;padding:3px 9px;border-radius:6px;border:none;cursor:pointer;flex-shrink:0;transition:all .2s"
                        :style="copied ? 'background:#dcfce7;color:#15803d' : 'background:#ede9fe;color:#7B61FF'"
                        x-text="copied ? '✓ Copied' : 'Copy'"></button>
            </div>
            <a href="{{ $form->publicUrl() }}" target="_blank" rel="noopener"
               style="display:flex;align-items:center;justify-content:center;gap:5px;width:100%;padding:8px;border-radius:9px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:12px;font-weight:600;text-decoration:none;box-sizing:border-box">
                <svg style="width:12px;height:12px;color:#6b7280" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                Preview Public Form
            </a>
        </div>
        @else
        <div class="card" style="padding:18px;background:#fffbeb">
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px">
                <div style="width:8px;height:8px;border-radius:9999px;background:#fbbf24;flex-shrink:0"></div>
                <h3 style="font-size:12px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.05em;margin:0">Form Not Published</h3>
            </div>
            <p style="font-size:12px;color:#d97706;margin:0 0 10px;line-height:1.5">Publish this form to make the public link active and start accepting submissions.</p>
        </div>
        @endif

        {{-- Quick Actions ─────────────────────────────────────────────────────── --}}
        <div class="card" style="padding:18px">
            <h3 style="font-size:12px;font-weight:700;color:#1E1B4B;text-transform:uppercase;letter-spacing:.05em;margin:0 0 12px">Quick Actions</h3>
            <div style="display:flex;flex-direction:column;gap:7px">
                <a href="{{ route('tenant.request-forms.submissions', [$tenant->id, $form->id]) }}"
                   style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:9px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:12px;font-weight:600;text-decoration:none"
                   onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='white'">
                    <svg style="width:12px;height:12px;color:#6b7280;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    View Responses
                </a>
                <form method="POST" action="{{ route('tenant.request-forms.duplicate', [$tenant->id, $form->id]) }}" style="margin:0">
                    @csrf
                    <button type="submit"
                            style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:9px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:12px;font-weight:600;cursor:pointer;width:100%;text-align:left"
                            onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='white'">
                        <svg style="width:12px;height:12px;color:#6b7280;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        Duplicate Form
                    </button>
                </form>
                <a href="{{ route('tenant.request-forms', $tenant->id) }}"
                   style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:9px;border:1.5px solid #e5e7eb;background:white;color:#6b7280;font-size:12px;font-weight:600;text-decoration:none"
                   onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='white'">
                    <svg style="width:12px;height:12px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    All Forms
                </a>
            </div>
        </div>

        {{-- Danger Zone ────────────────────────────────────────────────────────── --}}
        <div class="card" style="padding:18px;border:1.5px solid #fee2e2;background:#fff5f5">
            <h3 style="font-size:12px;font-weight:700;color:#dc2626;text-transform:uppercase;letter-spacing:.05em;margin:0 0 8px">Danger Zone</h3>
            <p style="font-size:12px;color:#9ca3af;margin:0 0 12px;line-height:1.5">Permanently deletes this form, all fields, and all submitted responses. This cannot be undone.</p>
            <button type="button"
                    onclick="rbConfirmDeleteForm('{{ $form->id }}','{{ addslashes($form->title) }}','{{ route('tenant.request-forms.destroy', [$tenant->id, $form->id]) }}')"
                    style="display:flex;align-items:center;gap:6px;padding:8px 14px;border-radius:9px;border:1.5px solid #fca5a5;background:white;color:#dc2626;font-size:12px;font-weight:600;cursor:pointer">
                <svg style="width:12px;height:12px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Delete Form
            </button>
        </div>

    </div>{{-- END SIDEBAR --}}

</div>{{-- END GRID --}}

{{-- Delete modal (reused pattern) --}}
<div id="rb-delete-form-modal"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:16px">
    <div style="background:white;border-radius:20px;padding:32px;max-width:400px;width:100%;text-align:center;box-shadow:0 24px 64px rgba(0,0,0,0.18)">
        <div style="width:52px;height:52px;border-radius:16px;background:#fee2e2;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
            <svg style="width:26px;height:26px;color:#dc2626" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        </div>
        <p style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#dc2626;margin-bottom:6px">Delete Form</p>
        <h3 id="rb-delete-form-title" style="font-size:17px;font-weight:700;color:#1E1B4B;margin-bottom:8px"></h3>
        <p style="font-size:13px;color:#9ca3af;margin-bottom:24px;line-height:1.6">This will permanently delete the form, all its fields, and all submitted responses. This cannot be undone.</p>
        <div style="display:flex;gap:10px">
            <button onclick="document.getElementById('rb-delete-form-modal').style.display='none'"
                    style="flex:1;padding:11px;border-radius:12px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:13px;font-weight:600;cursor:pointer">Cancel</button>
            <button id="rb-delete-form-btn"
                    style="flex:1;padding:11px;border-radius:12px;background:#dc2626;color:white;border:none;font-size:13px;font-weight:600;cursor:pointer">Delete Form</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
function rbConfirmDeleteForm(formId, title, actionUrl) {
    document.getElementById('rb-delete-form-title').textContent = title;
    document.getElementById('rb-delete-form-modal').style.display = 'flex';
    document.getElementById('rb-delete-form-btn').onclick = function() {
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

async function publishForm() {
    const btn = document.getElementById('created-publish-btn');
    if (btn) { btn.disabled = true; btn.textContent = 'Publishing…'; }
    try {
        const csrf = document.querySelector('meta[name=csrf-token]').content;
        const res  = await fetch('{{ route('tenant.request-forms.publish', [$tenant->id, $form->id]) }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        });
        const data = await res.json();
        if (data.status === 'published') window.location.reload();
    } catch (e) {
        if (btn) { btn.disabled = false; btn.textContent = 'Publish Now'; }
    }
}
</script>
@endpush
@endsection
