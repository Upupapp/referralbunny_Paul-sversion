@extends('layouts.app')
@section('title', 'New Request Form')
@section('nav') @include('tenant._nav') @endsection

@section('content')
<div class="max-w-3xl space-y-5" x-data="formBuilder()">

    <div>
        <a href="{{ route('tenant.request-forms', $tenant->id) }}" style="font-size:13px;color:#9ca3af;text-decoration:none">
            &larr; Back to Request Forms
        </a>
        <h1 style="font-size:20px;font-weight:700;color:#1E1B4B;margin-top:8px">Create Request Form</h1>
    </div>

    <form method="POST" action="{{ route('tenant.request-forms.store', $tenant->id) }}" @submit.prevent="submitForm($event)">
        @csrf

        {{-- Form basics --}}
        <div class="card space-y-4 mb-5">
            <h3 style="font-size:14px;font-weight:700;color:#1E1B4B">Form Details</h3>

            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:4px">Form Title *</label>
                <input type="text" name="title" required maxlength="120"
                       style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:14px;color:#1E1B4B;background:white;outline:none;box-sizing:border-box"
                       placeholder="e.g. LGU IDS Request Form" value="{{ old('title') }}">
            </div>

            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:4px">Description</label>
                <textarea name="description" maxlength="500" rows="2"
                          style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;color:#1E1B4B;background:white;outline:none;resize:none;box-sizing:border-box"
                          placeholder="Brief description shown on the public form">{{ old('description') }}</textarea>
            </div>

            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:4px">Success Message</label>
                <input type="text" name="success_message" maxlength="300"
                       style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;color:#1E1B4B;background:white;outline:none;box-sizing:border-box"
                       placeholder="Thank you. Your request has been submitted."
                       value="{{ old('success_message', 'Your request has been submitted successfully.') }}">
            </div>
        </div>

        {{-- Fields --}}
        <div class="card space-y-4 mb-5">
            <div style="display:flex;align-items:center;justify-content:space-between">
                <h3 style="font-size:14px;font-weight:700;color:#1E1B4B">Form Fields</h3>
                <button type="button" @click="addField()"
                        style="font-size:12px;font-weight:600;color:#7B61FF;background:#ede9fe;border:none;padding:6px 14px;border-radius:8px;cursor:pointer">
                    + Add Field
                </button>
            </div>

            {{-- LGU IDS Template --}}
            <div style="padding:10px 14px;background:#f5f3ff;border:1px dashed #c4b5fd;border-radius:10px;font-size:12px;color:#7B61FF">
                <strong>Quick-fill:</strong>
                <button type="button" @click="loadLguIdsTemplate()"
                        style="font-size:12px;font-weight:700;color:#7B61FF;background:none;border:none;cursor:pointer;text-decoration:underline;padding:0 4px">
                    Load LGU IDS Request Form template
                </button>
            </div>

            <div class="space-y-3">
                <template x-for="(field, i) in fields" :key="i">
                    <div style="padding:14px;background:#f9fafb;border-radius:12px;border:1px solid #f3f4f6">
                        <input type="hidden" :name="'fields[' + i + '][field_key]'" :value="field.key">
                        <input type="hidden" :name="'fields[' + i + '][field_type]'" :value="field.type">
                        <input type="hidden" :name="'fields[' + i + '][is_required]'" :value="field.required ? 1 : 0">

                        <div style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap">
                            <div style="flex:1;min-width:0">
                                <label style="display:block;font-size:10px;font-weight:700;color:#9ca3af;letter-spacing:.05em;text-transform:uppercase;margin-bottom:3px">Label</label>
                                <input type="text" :name="'fields[' + i + '][label]'" x-model="field.label"
                                       style="width:100%;padding:7px 10px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;box-sizing:border-box">
                            </div>
                            <div style="width:140px">
                                <label style="display:block;font-size:10px;font-weight:700;color:#9ca3af;letter-spacing:.05em;text-transform:uppercase;margin-bottom:3px">Type</label>
                                <select :name="'fields[' + i + '][field_type]'" x-model="field.type"
                                        style="width:100%;padding:7px 8px;border:1px solid #e5e7eb;border-radius:8px;font-size:12px;background:white">
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
                            <div style="display:flex;align-items:center;gap:6px;padding-top:20px">
                                <label style="font-size:12px;color:#374151;display:flex;align-items:center;gap:4px;cursor:pointer">
                                    <input type="checkbox" :name="'fields[' + i + '][is_required]'" :value="1" x-model="field.required">
                                    Required
                                </label>
                                <button type="button" @click="fields.splice(i, 1)"
                                        style="font-size:11px;color:#ef4444;background:none;border:none;cursor:pointer;padding:4px">Remove</button>
                            </div>
                        </div>

                        {{-- Options for select/radio --}}
                        <template x-if="['select','multi_select','radio'].includes(field.type)">
                            <div style="margin-top:10px">
                                <label style="font-size:10px;font-weight:700;color:#9ca3af;letter-spacing:.05em;text-transform:uppercase;display:block;margin-bottom:4px">Options (one per line)</label>
                                <textarea :name="'fields[' + i + '][options]'" x-model="field.optionsText" rows="3"
                                          style="width:100%;padding:7px 10px;border:1px solid #e5e7eb;border-radius:8px;font-size:12px;resize:none;box-sizing:border-box"
                                          placeholder="Option 1&#10;Option 2&#10;Option 3"></textarea>
                            </div>
                        </template>

                        <div style="margin-top:8px">
                            <input type="text" :name="'fields[' + i + '][helper_text]'" x-model="field.helperText"
                                   style="width:100%;padding:6px 10px;border:1px solid #e5e7eb;border-radius:8px;font-size:12px;color:#6b7280;box-sizing:border-box"
                                   placeholder="Helper text (optional)">
                        </div>
                    </div>
                </template>

                <template x-if="fields.length === 0">
                    <p style="text-align:center;color:#9ca3af;font-size:13px;padding:20px">No fields yet. Add fields or load a template.</p>
                </template>
            </div>
        </div>

        {{-- Recipients --}}
        <div class="card space-y-4 mb-5">
            <h3 style="font-size:14px;font-weight:700;color:#1E1B4B">Request Recipients</h3>
            <p style="font-size:12px;color:#9ca3af">Select team members who can be chosen as "Request To" on the public form. Tasks will be created for the selected recipient(s) on each submission.</p>

            <div class="space-y-2">
                @forelse($teamMembers as $member)
                <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:#f9fafb;border-radius:10px;cursor:pointer;border:1.5px solid transparent"
                       :style="selectedRecipients.includes('{{ $member->id }}') ? 'border-color:#c4b5fd;background:#f5f3ff' : ''">
                    <input type="checkbox" value="{{ $member->id }}"
                           x-model="selectedRecipients"
                           style="width:16px;height:16px;cursor:pointer">
                    <div>
                        <p style="font-size:13px;font-weight:600;color:#1E1B4B">{{ $member->name }}</p>
                        <p style="font-size:11px;color:#9ca3af">{{ $member->email }} &middot; {{ ucfirst($member->role) }}</p>
                    </div>
                    {{-- Hidden fields for selected recipients --}}
                    <template x-if="selectedRecipients.includes('{{ $member->id }}')">
                        <span>
                            <input type="hidden" name="recipients[]" value="{{ $member->id }}">
                            <input type="hidden" :name="'recipient_data[{{ $member->id }}][recipient_id]'" value="{{ $member->id }}">
                            <input type="hidden" :name="'recipient_data[{{ $member->id }}][display_name]'" value="{{ $member->name }}">
                            <input type="hidden" :name="'recipient_data[{{ $member->id }}][email]'" value="{{ $member->email }}">
                            <input type="hidden" :name="'recipient_data[{{ $member->id }}][role_snapshot]'" value="{{ $member->role }}">
                        </span>
                    </template>
                </label>
                @empty
                <p style="font-size:13px;color:#d97706;padding:10px;background:#fffbeb;border-radius:8px">
                    No active Admins or Managers found. Add team members first.
                </p>
                @endforelse
            </div>
        </div>

        {{-- Submit --}}
        <div style="display:flex;gap:10px;justify-content:flex-end">
            <a href="{{ route('tenant.request-forms', $tenant->id) }}"
               style="padding:10px 24px;border-radius:12px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:13px;font-weight:600;text-decoration:none">
                Cancel
            </a>
            <button type="submit"
                    style="padding:10px 28px;border-radius:12px;background:linear-gradient(135deg,#7B61FF,#5b4cdb);color:white;border:none;font-size:13px;font-weight:600;cursor:pointer;box-shadow:0 4px 14px rgba(123,97,255,0.3)">
                Save Form
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function formBuilder() {
    return {
        fields: [],
        selectedRecipients: [],

        addField() {
            this.fields.push({ label: '', key: 'field_' + Date.now(), type: 'text', required: false, helperText: '', optionsText: '' });
        },

        loadLguIdsTemplate() {
            this.fields = [
                { label: 'Name', key: 'name', type: 'text', required: true, helperText: '', optionsText: '' },
                { label: 'Email', key: 'email', type: 'email', required: true, helperText: '', optionsText: '' },
                { label: 'Request For', key: 'request_for', type: 'select', required: true, helperText: '', optionsText: "Letter to the LGU\nBusiness Proposals\nOthers" },
                { label: 'Notes', key: 'notes', type: 'textarea', required: false, helperText: 'You may include the deal amount, mayor\'s name, LGU name, province, and any other important details.', optionsText: '' },
            ];
            document.querySelector('[name=title]').value = 'LGU IDS Request Form';
            document.querySelector('[name=success_message]').value = 'Thank you. Your request has been submitted and assigned to the selected recipient.';
        },

        submitForm(e) {
            e.target.submit();
        },
    };
}
</script>
@endpush
@endsection
