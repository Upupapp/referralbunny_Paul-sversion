@extends('layouts.app')
@section('title', 'Edit Form — ' . $form->title)
@section('nav') @include('tenant._nav') @endsection

@section('content')
<div class="max-w-3xl space-y-5">

    <div>
        <a href="{{ route('tenant.request-forms', $tenant->id) }}" style="font-size:13px;color:#9ca3af;text-decoration:none">&larr; Request Forms</a>
        <h1 style="font-size:20px;font-weight:700;color:#1E1B4B;margin-top:8px">Edit: {{ $form->title }}</h1>
    </div>

    @if(session('form_created'))
    {{-- Success modal shown only on first arrival after creation --}}
    <div x-data="{ open: true }" x-show="open" x-cloak
         style="position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:9999;display:flex;align-items:center;justify-content:center;padding:16px"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div style="background:white;border-radius:20px;padding:32px;max-width:420px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.15);text-align:center"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">
            <div style="width:56px;height:56px;border-radius:16px;background:#D1FAE5;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
                <svg style="width:28px;height:28px;color:#10B981" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
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
    @elseif(session('success'))
    <div style="padding:12px 16px;background:#dcfce7;border:1px solid #86efac;border-radius:12px;color:#15803d;font-size:13px;font-weight:600">
        {{ session('success') }}
    </div>
    @endif

    {{-- Public link --}}
    @if($form->isPublished())
    <div style="padding:12px 16px;background:#f5f3ff;border:1px solid #c4b5fd;border-radius:12px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <svg style="width:14px;height:14px;color:#7B61FF;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
        <code style="font-size:12px;color:#7B61FF;flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $form->publicUrl() }}</code>
        <button onclick="navigator.clipboard.writeText('{{ $form->publicUrl() }}').then(()=>alert('Copied!'))"
                style="font-size:11px;font-weight:700;color:#7B61FF;background:#ede9fe;border:none;padding:4px 12px;border-radius:6px;cursor:pointer;flex-shrink:0">Copy</button>
        <a href="{{ $form->publicUrl() }}" target="_blank"
           style="font-size:11px;font-weight:700;color:#2563eb;background:#dbeafe;padding:4px 12px;border-radius:6px;text-decoration:none;flex-shrink:0">Preview</a>
    </div>
    @endif

    <form method="POST" action="{{ route('tenant.request-forms.update', [$tenant->id, $form->id]) }}">
        @csrf
        @method('PATCH')

        <div class="card space-y-4 mb-5">
            <h3 style="font-size:14px;font-weight:700;color:#1E1B4B">Form Details</h3>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:4px">Form Title *</label>
                <input type="text" name="title" required maxlength="120"
                       style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:14px;color:#1E1B4B;background:white;outline:none;box-sizing:border-box"
                       value="{{ old('title', $form->title) }}">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:4px">Description</label>
                <textarea name="description" maxlength="500" rows="2"
                          style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;color:#1E1B4B;background:white;outline:none;resize:none;box-sizing:border-box">{{ old('description', $form->description) }}</textarea>
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:4px">Success Message</label>
                <input type="text" name="success_message" maxlength="300"
                       style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;color:#1E1B4B;background:white;outline:none;box-sizing:border-box"
                       value="{{ old('success_message', $form->success_message) }}">
            </div>
        </div>

        {{-- Fields (read-only summary) --}}
        <div class="card space-y-3 mb-5">
            <h3 style="font-size:14px;font-weight:700;color:#1E1B4B">Form Fields ({{ $form->fields->count() }})</h3>
            @foreach($form->fields as $field)
            <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:#f9fafb;border-radius:10px">
                <span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:9999px;background:#ede9fe;color:#7B61FF;flex-shrink:0">{{ ucfirst(str_replace('_',' ',$field->field_type)) }}</span>
                <span style="font-size:13px;font-weight:600;color:#1E1B4B">{{ $field->label }}</span>
                @if($field->is_required)<span style="font-size:10px;color:#ef4444;font-weight:700">Required</span>@endif
            </div>
            @endforeach
            <p style="font-size:11px;color:#9ca3af">Field configuration is read-only after creation. Create a new form to change field types.</p>
        </div>

        {{-- Recipients --}}
        <div class="card space-y-4 mb-5" x-data="{ selectedRecipients: {{ json_encode($form->recipientOptions->pluck('recipient_id')->filter()->values()) }} }">
            <h3 style="font-size:14px;font-weight:700;color:#1E1B4B">Recipients</h3>
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
            </div>
        </div>

        <div style="display:flex;gap:10px;justify-content:flex-end">
            <a href="{{ route('tenant.request-forms', $tenant->id) }}"
               style="padding:10px 24px;border-radius:12px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:13px;font-weight:600;text-decoration:none">Cancel</a>
            <button type="submit"
                    style="padding:10px 28px;border-radius:12px;background:linear-gradient(135deg,#7B61FF,#5b4cdb);color:white;border:none;font-size:13px;font-weight:600;cursor:pointer;box-shadow:0 4px 14px rgba(123,97,255,0.3)">
                Save Changes
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
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
        if (data.status === 'published') {
            window.location.reload();
        }
    } catch (e) {
        if (btn) { btn.disabled = false; btn.textContent = 'Publish Now'; }
    }
}
</script>
@endpush
@endsection
