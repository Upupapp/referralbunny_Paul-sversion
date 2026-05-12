@extends('layouts.app')
@section('title', 'Create Request Form')
@section('nav') @include('tenant._nav') @endsection

@section('content')
<style>
@keyframes rb-ping { 75%,100%{ transform:scale(2); opacity:0; } }
@media (max-width:1023px) { .rf-create-grid { grid-template-columns:1fr !important; } }
@media (max-width:639px)  { .rf-field-row { flex-direction:column !important; } }
</style>

{{-- Loading overlay --}}
<div id="rb-form-saving" style="display:none;position:fixed;inset:0;background:rgba(15,15,35,0.58);z-index:9999;align-items:center;justify-content:center;padding:16px">
    <div style="background:white;border-radius:20px;padding:36px 32px;max-width:360px;width:100%;text-align:center;box-shadow:0 24px 64px rgba(0,0,0,0.2)">
        <div style="width:56px;height:56px;border-radius:16px;background:#ede9fe;display:flex;align-items:center;justify-content:center;margin:0 auto 18px">
            <svg style="width:26px;height:26px;color:#7B61FF" class="animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
        </div>
        <p style="font-size:16px;font-weight:700;color:#1E1B4B;margin-bottom:6px">Creating your form…</p>
        <p style="font-size:13px;color:#9ca3af">Please wait a moment.</p>
    </div>
</div>

{{-- Success modal --}}
<div id="rb-form-success" style="display:none;position:fixed;inset:0;background:rgba(15,15,35,0.58);z-index:9999;align-items:center;justify-content:center;padding:16px">
    <div style="background:white;border-radius:24px;padding:40px 32px;max-width:440px;width:100%;text-align:center;box-shadow:0 24px 64px rgba(0,0,0,0.2)">
        <div style="position:relative;width:64px;height:64px;margin:0 auto 20px">
            <div style="position:absolute;inset:0;border-radius:50%;background:#D1FAE5;animation:rb-ping 1.5s cubic-bezier(0,0,.2,1) infinite"></div>
            <div style="position:relative;width:64px;height:64px;border-radius:50%;background:#10B981;display:flex;align-items:center;justify-content:center">
                <svg style="width:30px;height:30px;color:white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            </div>
        </div>
        <p style="font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#10B981;margin-bottom:6px">Form Created</p>
        <h2 id="rb-form-title" style="font-size:20px;font-weight:700;color:#1E1B4B;margin-bottom:6px"></h2>
        <p style="font-size:13px;color:#9ca3af;margin-bottom:20px;line-height:1.6">Your form has been saved. Publish it and share the link below to start receiving requests.</p>
        <div style="display:flex;align-items:center;gap:8px;background:#f9fafb;border:1.5px solid #e5e7eb;border-radius:12px;padding:10px 14px;margin-bottom:20px">
            <a id="rb-public-url" href="#" target="_blank"
               style="flex:1;font-size:12px;color:#7B61FF;font-weight:600;text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-align:left"></a>
            <button onclick="rbCopyUrl()"
                    style="flex-shrink:0;padding:5px 12px;border-radius:8px;background:#7B61FF;color:white;border:none;font-size:11px;font-weight:700;cursor:pointer">Copy</button>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px">
            <a id="rb-edit-btn" href="#"
               style="display:block;padding:12px 24px;border-radius:12px;background:linear-gradient(135deg,#7B61FF,#5b4cdb);color:white;font-size:13px;font-weight:600;text-decoration:none">Manage Form</a>
            <a id="rb-list-btn" href="#"
               style="display:block;padding:12px 24px;border-radius:12px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:13px;font-weight:600;text-decoration:none">View All Forms</a>
        </div>
    </div>
</div>

<div x-data="formBuilder()">

    {{-- ── Page header ──────────────────────────────────────────────────────── --}}
    <div style="margin-bottom:24px">
        <nav style="display:flex;align-items:center;gap:5px;font-size:12px;color:#9ca3af;margin-bottom:10px">
            <a href="{{ route('tenant.request-forms', $tenant->id) }}" style="color:#9ca3af;text-decoration:none" onmouseover="this.style.color='#7B61FF'" onmouseout="this.style.color='#9ca3af'">Request Forms</a>
            <svg style="width:10px;height:10px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span style="color:#1E1B4B;font-weight:600">Create Form</span>
        </nav>
        <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
            <div>
                <h1 style="font-size:20px;font-weight:700;color:#1E1B4B;margin:0">Create Request Form</h1>
                <p style="font-size:13px;color:#9ca3af;margin-top:3px">Build a public form that creates tasks for selected team members when submitted.</p>
            </div>
            <a href="{{ route('tenant.request-forms', $tenant->id) }}"
               style="display:inline-flex;align-items:center;gap:5px;padding:7px 14px;border-radius:9px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:12px;font-weight:600;text-decoration:none">
                <svg style="width:12px;height:12px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                All Forms
            </a>
        </div>
    </div>

    <form method="POST" action="{{ route('tenant.request-forms.store', $tenant->id) }}" @submit.prevent="submitForm($event)">
        @csrf

        {{-- ── Two-column grid ──────────────────────────────────────────────── --}}
        <div class="rf-create-grid" style="display:grid;grid-template-columns:1fr 280px;gap:22px;align-items:start">

            {{-- ══ LEFT: MAIN CONTENT ══════════════════════════════════════════ --}}
            <div style="display:flex;flex-direction:column;gap:18px;min-width:0">

                {{-- Form Details ──────────────────────────────────────────────── --}}
                <div class="card">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px">
                        <div style="width:30px;height:30px;border-radius:8px;background:#ede9fe;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <svg style="width:14px;height:14px;color:#7B61FF" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </div>
                        <div>
                            <h3 style="font-size:14px;font-weight:700;color:#1E1B4B;margin:0">Form Details</h3>
                            <p style="font-size:11px;color:#9ca3af;margin:0">Basic info shown on the public form</p>
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:14px">
                        <div>
                            <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px">Form Title <span style="color:#ef4444">*</span></label>
                            <input type="text" name="title" required maxlength="120"
                                   style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;color:#1E1B4B;background:white;outline:none;box-sizing:border-box;transition:border-color .15s"
                                   placeholder="e.g. LGU IDS Request Form"
                                   value="{{ old('title') }}"
                                   onfocus="this.style.borderColor='#7B61FF'" onblur="this.style.borderColor='#e5e7eb'">
                            <p style="font-size:11px;color:#9ca3af;margin-top:4px">Use a clear title people will recognize when opening the request link.</p>
                        </div>
                        <div>
                            <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px">Description</label>
                            <textarea name="description" maxlength="500" rows="3"
                                      style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;color:#1E1B4B;background:white;outline:none;resize:vertical;box-sizing:border-box;transition:border-color .15s;line-height:1.6"
                                      placeholder="Briefly explain what this form is for and what information the requester should provide."
                                      onfocus="this.style.borderColor='#7B61FF'" onblur="this.style.borderColor='#e5e7eb'">{{ old('description') }}</textarea>
                            <p style="font-size:11px;color:#9ca3af;margin-top:4px">Shown at the top of the public form. Optional but recommended.</p>
                        </div>
                        <div>
                            <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px">Success Message</label>
                            <input type="text" name="success_message" maxlength="300"
                                   style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;color:#1E1B4B;background:white;outline:none;box-sizing:border-box;transition:border-color .15s"
                                   placeholder="Thank you. Your request has been submitted."
                                   value="{{ old('success_message', 'Thank you. Your request has been submitted and assigned to the selected recipient.') }}"
                                   onfocus="this.style.borderColor='#7B61FF'" onblur="this.style.borderColor='#e5e7eb'">
                            <p style="font-size:11px;color:#9ca3af;margin-top:4px">Shown to the requester after they submit the form.</p>
                        </div>
                    </div>
                </div>

                {{-- Form Fields ────────────────────────────────────────────────── --}}
                <div class="card">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:16px;flex-wrap:wrap">
                        <div style="display:flex;align-items:center;gap:10px">
                            <div style="width:30px;height:30px;border-radius:8px;background:#dbeafe;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                <svg style="width:14px;height:14px;color:#2563eb" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                            </div>
                            <div>
                                <h3 style="font-size:14px;font-weight:700;color:#1E1B4B;margin:0">Form Fields</h3>
                                <p style="font-size:11px;color:#9ca3af;margin:0">Fields requesters will fill out on the public form</p>
                            </div>
                        </div>
                        <button type="button" @click="addField()"
                                style="display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600;color:#7B61FF;background:#ede9fe;border:none;padding:7px 14px;border-radius:8px;cursor:pointer;flex-shrink:0">
                            <svg style="width:12px;height:12px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Add Field
                        </button>
                    </div>

                    {{-- Template quick-fill --}}
                    <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:#f5f3ff;border:1px dashed #c4b5fd;border-radius:10px;margin-bottom:16px;flex-wrap:wrap">
                        <svg style="width:14px;height:14px;color:#7B61FF;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        <span style="font-size:12px;color:#7B61FF;font-weight:600">Quick-fill template:</span>
                        <button type="button" @click="loadLguIdsTemplate()"
                                style="font-size:12px;font-weight:700;color:#5b4cdb;background:none;border:none;cursor:pointer;text-decoration:underline;padding:0">
                            Load LGU IDS Request Form
                        </button>
                    </div>

                    {{-- Fields list --}}
                    <div style="display:flex;flex-direction:column;gap:10px">
                        <template x-for="(field, i) in fields" :key="i">
                            <div style="border:1.5px solid #f3f4f6;border-radius:12px;overflow:hidden">
                                {{-- Field header --}}
                                <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:#f9fafb;border-bottom:1px solid #f3f4f6">
                                    <div style="width:22px;height:22px;border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:9px;font-weight:700;text-transform:uppercase"
                                         :style="{'background': {'text':'#ede9fe','email':'#dbeafe','textarea':'#dcfce7','select':'#fff7ed','multi_select':'#fef3c7','radio':'#f3f4f6','number':'#ede9fe','date':'#f0fdf4'}[field.type] || '#f3f4f6', 'color': {'text':'#7B61FF','email':'#2563eb','textarea':'#15803d','select':'#ea580c','multi_select':'#d97706','radio':'#6b7280','number':'#7B61FF','date':'#15803d'}[field.type] || '#6b7280'}"
                                         x-text="{'text':'Txt','email':'@','textarea':'¶','select':'▾','multi_select':'□','radio':'◉','number':'#','date':'📅'}[field.type] || 'Fld'">
                                    </div>
                                    <span style="font-size:12px;font-weight:600;color:#1E1B4B;flex:1" x-text="field.label || 'Untitled Field'"></span>
                                    <span x-show="field.required" style="font-size:10px;font-weight:700;color:#ef4444;background:#fef2f2;padding:1px 7px;border-radius:9999px;flex-shrink:0">Required</span>
                                    <button type="button" @click="fields.splice(i,1)"
                                            style="font-size:11px;color:#ef4444;background:none;border:none;cursor:pointer;padding:2px 6px;flex-shrink:0;border-radius:6px"
                                            onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='transparent'">Remove</button>
                                </div>

                                {{-- Field body --}}
                                <div style="padding:12px 14px">
                                    <input type="hidden" :name="'fields[' + i + '][field_key]'" :value="field.key">
                                    <input type="hidden" :name="'fields[' + i + '][field_type]'" :value="field.type">
                                    <input type="hidden" :name="'fields[' + i + '][is_required]'" :value="field.required ? 1 : 0">

                                    <div class="rf-field-row" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
                                        <div style="flex:1;min-width:160px">
                                            <label style="display:block;font-size:10px;font-weight:700;color:#9ca3af;letter-spacing:.06em;text-transform:uppercase;margin-bottom:4px">Label</label>
                                            <input type="text" :name="'fields[' + i + '][label]'" x-model="field.label"
                                                   style="width:100%;padding:7px 10px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:13px;box-sizing:border-box;outline:none"
                                                   onfocus="this.style.borderColor='#7B61FF'" onblur="this.style.borderColor='#e5e7eb'"
                                                   placeholder="Field label">
                                        </div>
                                        <div style="width:130px;flex-shrink:0">
                                            <label style="display:block;font-size:10px;font-weight:700;color:#9ca3af;letter-spacing:.06em;text-transform:uppercase;margin-bottom:4px">Type</label>
                                            <select :name="'fields[' + i + '][field_type]'" x-model="field.type"
                                                    style="width:100%;padding:7px 8px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:12px;background:white;outline:none">
                                                <option value="text">Text</option>
                                                <option value="email">Email</option>
                                                <option value="textarea">Textarea</option>
                                                <option value="select">Dropdown</option>
                                                <option value="multi_select">Multi-select</option>
                                                <option value="radio">Radio</option>
                                                <option value="number">Number</option>
                                                <option value="date">Date</option>
                                            </select>
                                        </div>
                                        <label style="display:flex;align-items:center;gap:5px;font-size:12px;color:#374151;cursor:pointer;padding-bottom:2px;flex-shrink:0;white-space:nowrap">
                                            <input type="checkbox" :name="'fields[' + i + '][is_required]'" :value="1" x-model="field.required"
                                                   style="width:14px;height:14px;accent-color:#7B61FF;cursor:pointer">
                                            Required
                                        </label>
                                    </div>

                                    {{-- Options (select/radio/multi_select) --}}
                                    <template x-if="['select','multi_select','radio'].includes(field.type)">
                                        <div style="margin-top:10px">
                                            <label style="font-size:10px;font-weight:700;color:#9ca3af;letter-spacing:.06em;text-transform:uppercase;display:block;margin-bottom:4px">Options <span style="font-weight:400;text-transform:none">(one per line)</span></label>
                                            <textarea :name="'fields[' + i + '][options]'" x-model="field.optionsText" rows="3"
                                                      style="width:100%;padding:7px 10px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:12px;resize:none;box-sizing:border-box;outline:none"
                                                      placeholder="Option 1&#10;Option 2&#10;Option 3"
                                                      onfocus="this.style.borderColor='#7B61FF'" onblur="this.style.borderColor='#e5e7eb'"></textarea>
                                        </div>
                                    </template>

                                    {{-- Helper text --}}
                                    <div style="margin-top:8px">
                                        <input type="text" :name="'fields[' + i + '][helper_text]'" x-model="field.helperText"
                                               style="width:100%;padding:6px 10px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:12px;color:#6b7280;box-sizing:border-box;outline:none"
                                               placeholder="Helper text shown below this field (optional)"
                                               onfocus="this.style.borderColor='#c4b5fd'" onblur="this.style.borderColor='#e5e7eb'">
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template x-if="fields.length === 0">
                            <div style="text-align:center;padding:28px 16px;border:2px dashed #f3f4f6;border-radius:12px">
                                <svg style="width:28px;height:28px;color:#d1d5db;margin:0 auto 10px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                                <p style="font-size:13px;color:#9ca3af;margin:0">No fields yet. Add fields above or load a template.</p>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Recipients ─────────────────────────────────────────────────── --}}
                <div class="card">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
                        <div style="width:30px;height:30px;border-radius:8px;background:#dcfce7;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <svg style="width:14px;height:14px;color:#15803d" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <div>
                            <h3 style="font-size:14px;font-weight:700;color:#1E1B4B;margin:0">Recipients & Task Routing</h3>
                            <p style="font-size:11px;color:#9ca3af;margin:0">Selected recipients receive a generated task on each submission</p>
                        </div>
                    </div>

                    @if($teamMembers->isEmpty())
                    <div style="padding:14px;background:#fffbeb;border-radius:10px;border:1px solid #fde68a;font-size:13px;color:#d97706">
                        No active Admins or Managers found. Add team members first before creating a request form.
                    </div>
                    @else
                    <div style="display:flex;flex-direction:column;gap:8px">
                        @foreach($teamMembers as $member)
                        @php
                        $parts    = preg_split('/\s+/', trim($member->name ?? ''), 2);
                        $initials = strtoupper(substr($parts[0] ?? '?', 0, 1)) . strtoupper(substr($parts[1] ?? '', 0, 1));
                        @endphp
                        <label style="display:flex;align-items:center;gap:12px;padding:11px 14px;background:#f9fafb;border-radius:12px;cursor:pointer;border:1.5px solid transparent;transition:all .12s"
                               :style="selectedRecipients.includes('{{ $member->id }}') ? 'border-color:#c4b5fd;background:#f5f3ff' : 'border-color:transparent;background:#f9fafb'">
                            <input type="checkbox" value="{{ $member->id }}"
                                   x-model="selectedRecipients"
                                   style="width:15px;height:15px;accent-color:#7B61FF;cursor:pointer;flex-shrink:0">
                            <div style="width:34px;height:34px;border-radius:9999px;background:linear-gradient(135deg,#7B61FF,#5b4cdb);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:white;flex-shrink:0">{{ $initials ?: '?' }}</div>
                            <div style="flex:1;min-width:0">
                                <p style="font-size:13px;font-weight:600;color:#1E1B4B;margin:0">{{ $member->name }}</p>
                                <p style="font-size:11px;color:#9ca3af;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $member->email }} · {{ ucfirst($member->role) }}</p>
                            </div>
                            <template x-if="selectedRecipients.includes('{{ $member->id }}')">
                                <span>
                                    <input type="hidden" name="recipient_data[{{ $member->id }}][recipient_id]"   value="{{ $member->id }}">
                                    <input type="hidden" name="recipient_data[{{ $member->id }}][display_name]"  value="{{ $member->name }}">
                                    <input type="hidden" name="recipient_data[{{ $member->id }}][email]"         value="{{ $member->email }}">
                                    <input type="hidden" name="recipient_data[{{ $member->id }}][role_snapshot]" value="{{ $member->role }}">
                                    <svg style="width:15px;height:15px;color:#7B61FF;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                </span>
                            </template>
                        </label>
                        @endforeach
                    </div>
                    <p style="font-size:11px;color:#9ca3af;margin-top:10px">
                        Selected recipients will receive a generated task automatically each time someone submits this form.
                    </p>
                    @endif
                </div>

            </div>{{-- END LEFT --}}

            {{-- ══ RIGHT: SIDEBAR ══════════════════════════════════════════════ --}}
            <div style="display:flex;flex-direction:column;gap:14px">

                {{-- Save actions ──────────────────────────────────────────────── --}}
                <div class="card" style="padding:18px">
                    <h3 style="font-size:12px;font-weight:700;color:#1E1B4B;text-transform:uppercase;letter-spacing:.05em;margin:0 0 14px">Save Form</h3>
                    <div style="display:flex;flex-direction:column;gap:8px">
                        <button type="submit"
                                style="display:flex;align-items:center;justify-content:center;gap:6px;padding:10px 20px;border-radius:10px;background:linear-gradient(135deg,#7B61FF,#5b4cdb);color:white;border:none;font-size:13px;font-weight:600;cursor:pointer;box-shadow:0 3px 12px rgba(123,97,255,0.28)">
                            <svg style="width:13px;height:13px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Create Form
                        </button>
                        <a href="{{ route('tenant.request-forms', $tenant->id) }}"
                           style="display:flex;align-items:center;justify-content:center;padding:9px 20px;border-radius:10px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:13px;font-weight:600;text-decoration:none">
                            Cancel
                        </a>
                    </div>
                    <p style="font-size:11px;color:#9ca3af;margin-top:10px;line-height:1.5">Form is saved as a draft. You can publish it after creation.</p>
                </div>

                {{-- How it works ──────────────────────────────────────────────── --}}
                <div class="card" style="padding:18px;background:#fafafa">
                    <h3 style="font-size:12px;font-weight:700;color:#1E1B4B;text-transform:uppercase;letter-spacing:.05em;margin:0 0 12px">How it works</h3>
                    <div style="display:flex;flex-direction:column;gap:10px">
                        @foreach([['1','Create this form and add fields','#7B61FF','#ede9fe'],['2','Publish and share the public link','#2563eb','#dbeafe'],['3','Requester fills the form','#15803d','#dcfce7'],['4','Task is auto-created for selected recipient','#d97706','#fffbeb']] as [$n,$txt,$color,$bg])
                        <div style="display:flex;align-items:flex-start;gap:10px">
                            <div style="width:22px;height:22px;border-radius:6px;background:{{ $bg }};color:{{ $color }};font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0">{{ $n }}</div>
                            <p style="font-size:12px;color:#374151;margin:0;line-height:1.5">{{ $txt }}</p>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Field count summary ────────────────────────────────────────── --}}
                <div class="card" style="padding:16px">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                        <h3 style="font-size:12px;font-weight:700;color:#1E1B4B;text-transform:uppercase;letter-spacing:.05em;margin:0">Form Summary</h3>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:8px">
                        <div style="display:flex;justify-content:space-between;font-size:12px">
                            <span style="color:#9ca3af">Fields</span>
                            <span style="font-weight:700;color:#1E1B4B" x-text="fields.length + ' field' + (fields.length === 1 ? '' : 's')"></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:12px">
                            <span style="color:#9ca3af">Required</span>
                            <span style="font-weight:700;color:#1E1B4B" x-text="fields.filter(f=>f.required).length"></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:12px">
                            <span style="color:#9ca3af">Recipients</span>
                            <span style="font-weight:700;color:#1E1B4B" x-text="selectedRecipients.length + ' selected'"></span>
                        </div>
                    </div>
                </div>

            </div>{{-- END SIDEBAR --}}

        </div>{{-- END GRID --}}
    </form>
</div>

@push('scripts')
<script>
function rbCopyUrl() {
    const url = window._rbPublicUrl;
    if (!url) return;
    navigator.clipboard.writeText(url).then(() => {
        const btn = event.target;
        btn.textContent = 'Copied!';
        btn.style.background = '#10B981';
        setTimeout(() => { btn.textContent = 'Copy'; btn.style.background = '#7B61FF'; }, 2000);
    });
}

function formBuilder() {
    return {
        fields: [],
        selectedRecipients: [],

        addField() {
            this.fields.push({ label: '', key: 'field_' + Date.now(), type: 'text', required: false, helperText: '', optionsText: '' });
        },

        loadLguIdsTemplate() {
            this.fields = [
                { label: 'Name',         key: 'name',         type: 'text',     required: true,  helperText: '', optionsText: '' },
                { label: 'Email',        key: 'email',        type: 'email',    required: true,  helperText: '', optionsText: '' },
                { label: 'Request For',  key: 'request_for',  type: 'select',   required: true,  helperText: '', optionsText: "Letter to the LGU\nBusiness Proposals\nDocument Request\nFollow-up\nOthers" },
                { label: 'Notes',        key: 'notes',        type: 'textarea', required: false, helperText: "Include deal amount, mayor's name, LGU name, province, or any important details.", optionsText: '' },
            ];
            const titleEl = document.querySelector('[name=title]');
            if (titleEl && !titleEl.value) {
                titleEl.value = 'LGU IDS Request Form';
            }
            const smEl = document.querySelector('[name=success_message]');
            if (smEl) smEl.value = 'Thank you. Your request has been submitted and assigned to the selected recipient.';
        },

        async submitForm(e) {
            e.preventDefault();
            const form    = e.target;
            const saving  = document.getElementById('rb-form-saving');
            const success = document.getElementById('rb-form-success');

            saving.style.display = 'flex';

            try {
                const res  = await fetch(form.action, {
                    method:      'POST',
                    headers:     { 'Accept': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name=_token]').value },
                    body:        new FormData(form),
                    credentials: 'same-origin',
                });
                const data = await res.json();

                if (res.status === 201) {
                    saving.style.display = 'none';
                    document.getElementById('rb-form-title').textContent = data.title;
                    document.getElementById('rb-edit-btn').href  = data.edit_url;
                    document.getElementById('rb-list-btn').href  = data.list_url;
                    const urlEl = document.getElementById('rb-public-url');
                    urlEl.textContent = data.public_url;
                    urlEl.href        = data.public_url;
                    window._rbPublicUrl = data.public_url;
                    success.style.display = 'flex';
                } else {
                    saving.style.display = 'none';
                    const msgs = data.errors
                        ? Object.values(data.errors).flat().join('\n')
                        : (data.message || 'An error occurred.');
                    alert(msgs);
                }
            } catch (err) {
                saving.style.display = 'none';
                alert('Network error. Please try again.');
            }
        },
    };
}
</script>
@endpush
@endsection
