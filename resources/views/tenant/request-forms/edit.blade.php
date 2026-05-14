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

        {{-- Form Fields (editable) ─────────────────────────────────────────────── --}}
        <div class="card" x-data="rfFieldManager()">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px;flex-wrap:wrap">
                <div style="display:flex;align-items:center;gap:10px">
                    <div style="width:30px;height:30px;border-radius:8px;background:#dbeafe;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <svg style="width:14px;height:14px;color:#2563eb" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                    </div>
                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:#1E1B4B;margin:0">Form Fields</h3>
                        <p style="font-size:11px;color:#9ca3af;margin:0"><span x-text="fields.length"></span> fields · drag to reorder</p>
                    </div>
                </div>
                <button type="button" @click="showAdd = true"
                        style="display:inline-flex;align-items:center;gap:5px;padding:7px 14px;border-radius:9px;background:linear-gradient(135deg,#2563EB,#1D4ED8);color:white;border:none;font-size:12px;font-weight:600;cursor:pointer">
                    <svg style="width:12px;height:12px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                    Add Field
                </button>
            </div>

            {{-- Field type lock notice --}}
            <div style="display:flex;align-items:flex-start;gap:8px;padding:9px 12px;background:#fffbeb;border:1px solid #fde68a;border-radius:9px;margin-bottom:12px;font-size:12px;color:#92400e;line-height:1.5">
                <svg style="width:13px;height:13px;color:#d97706;flex-shrink:0;margin-top:1px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Field <strong>type</strong> is locked after creation — edit label, placeholder, options, and required. To change a type, delete and re-add.
            </div>

            {{-- Add Field panel --}}
            <div x-show="showAdd" style="padding:16px;background:#f0f9ff;border:1.5px solid #bae6fd;border-radius:12px;margin-bottom:14px">
                <p style="font-size:13px;font-weight:700;color:#0369a1;margin:0 0 12px">Add New Field</p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
                    <div>
                        <label style="display:block;font-size:11px;font-weight:600;color:#374151;margin-bottom:4px">Label *</label>
                        <input x-model="newField.label" type="text" placeholder="e.g. Full Name"
                               style="width:100%;padding:7px 10px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:13px;outline:none;box-sizing:border-box">
                    </div>
                    <div>
                        <label style="display:block;font-size:11px;font-weight:600;color:#374151;margin-bottom:4px">Field Type *</label>
                        <select x-model="newField.field_type" style="width:100%;padding:7px 10px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:13px;outline:none;background:white;box-sizing:border-box">
                            <option value="text">Text</option>
                            <option value="email">Email</option>
                            <option value="textarea">Long Text</option>
                            <option value="select">Dropdown</option>
                            <option value="radio">Radio</option>
                            <option value="checkbox">Checkbox</option>
                            <option value="number">Number</option>
                            <option value="date">Date</option>
                        </select>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
                    <div>
                        <label style="display:block;font-size:11px;font-weight:600;color:#374151;margin-bottom:4px">Placeholder</label>
                        <input x-model="newField.placeholder" type="text" placeholder="e.g. Enter your name"
                               style="width:100%;padding:7px 10px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:13px;outline:none;box-sizing:border-box">
                    </div>
                    <div>
                        <label style="display:block;font-size:11px;font-weight:600;color:#374151;margin-bottom:4px">Helper Text</label>
                        <input x-model="newField.helper_text" type="text" placeholder="Short description"
                               style="width:100%;padding:7px 10px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:13px;outline:none;box-sizing:border-box">
                    </div>
                </div>
                <template x-if="['select','radio','checkbox','multi_select'].includes(newField.field_type)">
                    <div style="margin-bottom:10px">
                        <label style="display:block;font-size:11px;font-weight:600;color:#374151;margin-bottom:4px">Options (one per line)</label>
                        <textarea x-model="newField.options" rows="3" placeholder="Option A&#10;Option B&#10;Option C"
                                  style="width:100%;padding:7px 10px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:13px;outline:none;resize:vertical;box-sizing:border-box"></textarea>
                    </div>
                </template>
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
                    <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:#374151;cursor:pointer">
                        <input type="checkbox" x-model="newField.is_required" class="rounded">
                        Required field
                    </label>
                    <div style="display:flex;gap:8px">
                        <button type="button" @click="showAdd = false; resetNew()"
                                style="padding:7px 16px;border-radius:8px;border:1.5px solid #e5e7eb;background:white;font-size:12px;font-weight:600;cursor:pointer;color:#374151">
                            Cancel
                        </button>
                        <button type="button" @click="addField()"
                                :disabled="!newField.label.trim() || addingSaving"
                                style="padding:7px 16px;border-radius:8px;background:linear-gradient(135deg,#2563EB,#1D4ED8);color:white;border:none;font-size:12px;font-weight:600;cursor:pointer;disabled:opacity:50"
                                x-text="addingSaving ? 'Adding…' : 'Add Field'">Add Field</button>
                    </div>
                </div>
                <div x-show="addError" style="margin-top:8px;padding:6px 10px;background:#fef2f2;border-radius:7px;font-size:12px;color:#dc2626" x-text="addError"></div>
            </div>

            {{-- Field cards (editable) --}}
            <div style="display:flex;flex-direction:column;gap:8px">
                <template x-if="fields.length === 0">
                    <p style="font-size:13px;color:#9ca3af;font-style:italic;text-align:center;padding:20px 0">No fields yet. Add your first field above.</p>
                </template>
                <template x-for="(field, idx) in fields" :key="field.id">
                    <div style="background:#f9fafb;border-radius:10px;border:1px solid #f3f4f6;overflow:hidden">
                        {{-- Field header row --}}
                        <div style="display:flex;align-items:center;gap:10px;padding:10px 14px">
                            <div style="width:26px;height:26px;border-radius:7px;background:#ede9fe;color:#7B61FF;font-size:9px;font-weight:700;text-transform:uppercase;display:flex;align-items:center;justify-content:center;flex-shrink:0"
                                 x-text="field.type_label.substring(0,3).toUpperCase()"></div>
                            <div style="flex:1;min-width:0">
                                <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap">
                                    <span style="font-size:13px;font-weight:600;color:#1E1B4B" x-text="field.label"></span>
                                    <span style="font-size:10px;font-weight:600;color:#7B61FF;background:#ede9fe;padding:1px 7px;border-radius:9999px" x-text="field.type_label"></span>
                                    <span x-show="field.is_required" style="font-size:10px;font-weight:700;color:#ef4444;background:#fef2f2;padding:1px 7px;border-radius:9999px">Required</span>
                                    <span x-show="field.is_system_field" style="font-size:10px;font-weight:700;color:#0d9488;background:#f0fdfa;border:1px solid #99f6e4;padding:1px 7px;border-radius:9999px">🔒 System</span>
                                </div>
                                <p x-show="field.helper_text" style="font-size:11px;color:#9ca3af;margin:2px 0 0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" x-text="field.helper_text"></p>
                            </div>
                            <div style="display:flex;gap:4px;flex-shrink:0">
                                <button type="button" @click="field._editing = !field._editing"
                                        style="padding:4px 10px;border-radius:7px;border:1px solid #e5e7eb;background:white;font-size:11px;font-weight:600;color:#374151;cursor:pointer"
                                        x-text="field._editing ? 'Close' : 'Edit'">Edit</button>
                                <button x-show="!field.is_system_field" type="button" @click="confirmDelete(field)"
                                        style="padding:4px 8px;border-radius:7px;border:1px solid #fee2e2;background:white;font-size:11px;font-weight:600;color:#dc2626;cursor:pointer">✕</button>
                            </div>
                        </div>

                        {{-- Inline edit panel --}}
                        <div x-show="field._editing" style="padding:12px 14px;border-top:1px solid #f3f4f6;background:white">
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">
                                <div>
                                    <label style="display:block;font-size:11px;font-weight:600;color:#374151;margin-bottom:3px">Label</label>
                                    <input x-model="field.label" type="text"
                                           style="width:100%;padding:6px 9px;border:1.5px solid #e5e7eb;border-radius:7px;font-size:12px;outline:none;box-sizing:border-box">
                                </div>
                                <div>
                                    <label style="display:block;font-size:11px;font-weight:600;color:#374151;margin-bottom:3px">Placeholder</label>
                                    <input x-model="field.placeholder" type="text"
                                           style="width:100%;padding:6px 9px;border:1.5px solid #e5e7eb;border-radius:7px;font-size:12px;outline:none;box-sizing:border-box">
                                </div>
                            </div>
                            <div style="margin-bottom:8px">
                                <label style="display:block;font-size:11px;font-weight:600;color:#374151;margin-bottom:3px">Helper Text</label>
                                <input x-model="field.helper_text" type="text"
                                       style="width:100%;padding:6px 9px;border:1.5px solid #e5e7eb;border-radius:7px;font-size:12px;outline:none;box-sizing:border-box">
                            </div>
                            <template x-if="['select','radio','checkbox','multi_select'].includes(field.field_type)">
                                <div style="margin-bottom:8px">
                                    <label style="display:block;font-size:11px;font-weight:600;color:#374151;margin-bottom:3px">Options (one per line)</label>
                                    <textarea
                                              :value="Array.isArray(field.options) ? field.options.join('\n') : (field.options || '')"
                                              @input="field.options = $event.target.value.split('\n').map(s => s.trim()).filter(s => s)"
                                              rows="3"
                                              style="width:100%;padding:6px 9px;border:1.5px solid #e5e7eb;border-radius:7px;font-size:12px;outline:none;resize:vertical;box-sizing:border-box"></textarea>
                                </div>
                            </template>
                            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
                                <label style="display:flex;align-items:center;gap:5px;font-size:12px;color:#374151;cursor:pointer">
                                    <input type="checkbox" :checked="field.is_required" @change="field.is_required = $event.target.checked" class="rounded">
                                    Required
                                </label>
                                <div style="display:flex;gap:6px">
                                    <button type="button" @click="field._editing = false; revertField(field)"
                                            style="padding:5px 12px;border-radius:7px;border:1.5px solid #e5e7eb;background:white;font-size:11px;font-weight:600;cursor:pointer;color:#374151">
                                        Cancel
                                    </button>
                                    <button type="button" @click="saveField(field)"
                                            :disabled="field._saving"
                                            style="padding:5px 12px;border-radius:7px;background:linear-gradient(135deg,#7B61FF,#5b4cdb);color:white;border:none;font-size:11px;font-weight:600;cursor:pointer"
                                            x-text="field._saving ? 'Saving…' : 'Save Changes'">Save</button>
                                </div>
                            </div>
                            <div x-show="field._error" style="margin-top:6px;font-size:11px;color:#dc2626" x-text="field._error"></div>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Delete confirmation modal --}}
            <div x-show="deletingField" x-cloak style="position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:9999;display:flex;align-items:center;justify-content:center;padding:16px">
                <div style="background:white;border-radius:16px;padding:28px;max-width:380px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.15);text-align:center">
                    <div style="width:44px;height:44px;border-radius:12px;background:#fef2f2;display:flex;align-items:center;justify-content:center;margin:0 auto 14px">
                        <svg style="width:22px;height:22px;color:#dc2626" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </div>
                    <h3 style="font-size:16px;font-weight:700;color:#1E1B4B;margin:0 0 6px">Remove Field?</h3>
                    <p style="font-size:13px;color:#9ca3af;margin:0 0 20px">
                        "<span x-text="deletingField?.label"></span>" will be removed from this form. Existing submissions are unaffected.
                    </p>
                    <div style="display:flex;gap:8px;justify-content:center">
                        <button type="button" @click="deletingField = null"
                                style="padding:9px 22px;border-radius:10px;border:1.5px solid #e5e7eb;background:white;font-size:13px;font-weight:600;cursor:pointer;color:#374151">
                            Cancel
                        </button>
                        <button type="button" @click="deleteField()"
                                :disabled="deleteLoading"
                                style="padding:9px 22px;border-radius:10px;background:#dc2626;color:white;border:none;font-size:13px;font-weight:600;cursor:pointer"
                                x-text="deleteLoading ? 'Removing…' : 'Remove Field'">Remove</button>
                    </div>
                </div>
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

@php
// Pre-compute field data outside @json() — multi-line closures inside @json() cause
// PHP 8.4 parse errors in Blade-compiled output due to semicolons in nested expressions.
$_rfFieldsData = $form->fields->sortBy('sort_order')->map(function ($f) {
    $typeLabel = match ($f->field_type) {
        'multi_select' => 'Multi-select',
        default        => ucfirst(str_replace('_', ' ', $f->field_type)),
    };
    return [
        'id'              => $f->id,
        'label'           => $f->label,
        'field_type'      => $f->field_type,
        'type_label'      => $typeLabel,
        'placeholder'     => $f->placeholder,
        'helper_text'     => $f->helper_text,
        'options'         => $f->options ?? [],
        'is_required'     => (bool) $f->is_required,
        'is_system_field' => (bool) $f->is_system_field,
    ];
})->values()->all();
@endphp
@push('scripts')
<script>
// Field manager globals — set before rfFieldManager() is called by Alpine
window.__rfFieldsData = @json($_rfFieldsData);
window.__rfAddUrl  = '{{ route('tenant.request-forms.fields.store', [$tenant->id, $form->id]) }}';
window.__rfBaseUrl = '{{ url('tenant/'.$tenant->id.'/request-forms/'.$form->id.'/fields') }}';
window.__rfCsrf    = '{{ csrf_token() }}';
</script>
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

function rfFieldManager() {
    const addUrl  = window.__rfAddUrl  ?? '';
    const baseUrl = window.__rfBaseUrl ?? '';
    const csrf    = window.__rfCsrf   ?? document.querySelector('meta[name=csrf-token]')?.content ?? '';
    const initialFields = window.__rfFieldsData ?? [];

    return {
        fields:       initialFields.map(f => ({ ...f, _editing: false, _saving: false, _error: '', _original: { ...f } })),
        showAdd:      false,
        addingSaving: false,
        addError:     '',
        deletingField:null,
        deleteLoading:false,
        newField:     { label:'', field_type:'text', placeholder:'', helper_text:'', options:'', is_required:false },

        resetNew() {
            this.newField = { label:'', field_type:'text', placeholder:'', helper_text:'', options:'', is_required:false };
            this.addError = '';
        },

        revertField(field) {
            Object.assign(field, field._original, { _editing:false, _saving:false, _error:'', _original:field._original });
        },

        async addField() {
            if (!this.newField.label.trim() || this.addingSaving) return;
            this.addingSaving = true; this.addError = '';
            try {
                const r = await fetch(addUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({
                        label:       this.newField.label,
                        field_type:  this.newField.field_type,
                        placeholder: this.newField.placeholder,
                        helper_text: this.newField.helper_text,
                        options:     this.newField.options,
                        is_required: this.newField.is_required,
                    }),
                });
                const d = await r.json();
                if (!r.ok) { this.addError = d.message || d.error || 'Failed to add field.'; return; }
                this.fields.push({ ...d.field, _editing: false, _saving: false, _error: '', _original: { ...d.field } });
                this.showAdd = false;
                this.resetNew();
            } catch(e) { this.addError = 'Network error. Please try again.'; }
            finally { this.addingSaving = false; }
        },

        async saveField(field) {
            field._saving = true; field._error = '';
            try {
                const optVal = Array.isArray(field.options) ? field.options.join('\n') : (field.options || '');
                const r = await fetch(baseUrl + '/' + field.id, {
                    method: 'PATCH',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({
                        label:       field.label,
                        placeholder: field.placeholder,
                        helper_text: field.helper_text,
                        options:     optVal,
                        is_required: field.is_required,
                    }),
                });
                const d = await r.json();
                if (!r.ok) { field._error = d.message || 'Failed to save.'; return; }
                Object.assign(field, { ...d.field, _editing: false, _saving: false, _error: '', _original: { ...d.field } });
            } catch(e) { field._error = 'Network error.'; }
            finally { field._saving = false; }
        },

        confirmDelete(field) { this.deletingField = field; },

        async deleteField() {
            if (!this.deletingField || this.deleteLoading) return;
            this.deleteLoading = true;
            try {
                const r = await fetch(baseUrl + '/' + this.deletingField.id, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                });
                if (r.ok) {
                    this.fields = this.fields.filter(f => f.id !== this.deletingField.id);
                    this.deletingField = null;
                }
            } catch(e) {}
            finally { this.deleteLoading = false; }
        },
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
