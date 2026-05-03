@extends('layouts.app')
@section('title', 'New Tenant')
@section('nav') @include('platform._nav') @endsection

@section('content')
<div class="max-w-2xl mx-auto space-y-6">

    <a href="{{ route('platform.tenants') }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Back to Tenants
    </a>

    @if($errors->any())
        <div class="p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm space-y-1">
            @foreach($errors->all() as $error)<p>• {{ $error }}</p>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('platform.tenants.store') }}" class="space-y-6">
        @csrf

        {{-- Basic Info --}}
        <div class="card space-y-4">
            <h3 class="font-semibold text-[#1E1B4B]">Basic Information</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Tenant Name *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="form-input" placeholder="e.g. LGU IDS">
                </div>
                <div>
                    <label class="form-label">Program Name *</label>
                    <input type="text" name="program_name" value="{{ old('program_name') }}" required class="form-input" placeholder="e.g. LGU ID Referral Program">
                </div>
                <div>
                    <label class="form-label">Business Name</label>
                    <input type="text" name="business_name" value="{{ old('business_name') }}" class="form-input" placeholder="Legal business name">
                </div>
                <div>
                    <label class="form-label">Industry *</label>
                    <select name="industry" required class="form-input">
                        <option value="">Select industry</option>
                        @foreach([
                            'Real Estate','Insurance','Healthcare','Financial Services','Legal Services',
                            'Automotive','Technology / SaaS','Education','E-commerce / Retail',
                            'Travel & Hospitality','Food & Beverage','Beauty & Wellness',
                            'Events & Entertainment','Logistics & Transport','Construction & Engineering',
                            'Non-Profit & NGO','Government / LGU','Media & Advertising',
                            'Manufacturing','Agriculture','Telecommunications','Pet Industry',
                        ] as $ind)
                            <option value="{{ $ind }}" {{ old('industry') === $ind ? 'selected' : '' }}>{{ $ind }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label">Description</label>
                    <textarea name="description" rows="2" class="form-input" placeholder="Brief description of this tenant's referral program">{{ old('description') }}</textarea>
                </div>
            </div>
        </div>

        {{-- Contact --}}
        <div class="card space-y-4">
            <h3 class="font-semibold text-[#1E1B4B]">Contact Information</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Contact Person</label>
                    <input type="text" name="contact_person" value="{{ old('contact_person') }}" class="form-input" placeholder="Full name">
                </div>
                <div>
                    <label class="form-label">Contact Email</label>
                    <input type="email" name="contact_email" value="{{ old('contact_email') }}" class="form-input" placeholder="contact@company.com">
                </div>
                <div>
                    <label class="form-label">Contact Phone</label>
                    <input type="text" name="contact_phone" value="{{ old('contact_phone') }}" class="form-input" placeholder="+63 9XX XXX XXXX">
                </div>
            </div>
        </div>

        {{-- Admin Account --}}
        <div class="card space-y-4">
            <h3 class="font-semibold text-[#1E1B4B]">Admin Account</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Admin Name *</label>
                    <input type="text" name="admin_name" value="{{ old('admin_name') }}" required class="form-input" placeholder="Admin full name">
                </div>
                <div>
                    <label class="form-label">Admin Email *</label>
                    <input type="email" name="admin_email" value="{{ old('admin_email') }}" required class="form-input" placeholder="admin@company.com">
                </div>
            </div>
        </div>

        {{-- Branding --}}
        <div class="card space-y-4">
            <h3 class="font-semibold text-[#1E1B4B]">Branding</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Accent Color</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="accent_color" value="{{ old('accent_color', '#7B61FF') }}"
                               class="w-12 h-10 rounded-xl border border-gray-200 cursor-pointer p-1">
                        <input type="text" id="accent_hex" value="{{ old('accent_color', '#7B61FF') }}"
                               class="form-input flex-1" placeholder="#7B61FF"
                               oninput="document.querySelector('[name=accent_color]').value=this.value">
                    </div>
                </div>
                <div>
                    <label class="form-label">Currency</label>
                    <select name="preferred_currency" class="form-input">
                        <option value="PHP" {{ old('preferred_currency','PHP')==='PHP'?'selected':'' }}>PHP — Philippine Peso</option>
                        <option value="USD" {{ old('preferred_currency')==='USD'?'selected':'' }}>USD — US Dollar</option>
                        <option value="SGD" {{ old('preferred_currency')==='SGD'?'selected':'' }}>SGD — Singapore Dollar</option>
                        <option value="EUR" {{ old('preferred_currency')==='EUR'?'selected':'' }}>EUR — Euro</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3 justify-end">
            <a href="{{ route('platform.tenants') }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">Create Tenant</button>
        </div>
    </form>
</div>

<script>
document.querySelector('[name=accent_color]').addEventListener('input', function() {
    document.getElementById('accent_hex').value = this.value;
});
</script>
@endsection
