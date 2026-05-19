@extends('layouts.app')
@section('title', 'Deal Import Settings')

@section('nav')
    @include('tenant._nav')
@endsection

@section('topbar-actions')
    <a href="{{ route('tenant.imports', $tenant->id) }}" class="btn-secondary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        <span class="hidden sm:inline">Back to Imports</span>
    </a>
@endsection

@section('content')
<form action="{{ route('tenant.imports.deals.settings.update', $tenant->id) }}" method="POST" class="space-y-5">
    @csrf

    {{-- ── Header card ─────────────────────────────────────── --}}
    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background: #EDE9FE;">
                        <svg class="w-4 h-4" style="color: #7B61FF;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full" style="background: #EDE9FE; color: #7B61FF;">Settings</span>
                </div>
                <h1 class="text-[#1E1B4B] font-bold text-xl">Deal Import Settings</h1>
                <p class="text-gray-400 text-sm mt-0.5">Configure how deals are imported for your tenant.</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('tenant.imports.deals', $tenant->id) }}" class="btn-secondary">
                    Discard Changes
                </a>
                <button type="submit" class="btn-primary">
                    Save Settings
                </button>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="flex items-center gap-3 p-4 rounded-2xl" style="background: #ECFDF5; border: 1px solid #A7F3D0;">
        <svg class="w-5 h-5 shrink-0" style="color: #10B981;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
        </svg>
        <p class="text-sm font-medium" style="color: #065F46;">{{ session('success') }}</p>
    </div>
    @endif

    @if($errors->any())
    <div class="flex items-start gap-3 p-4 rounded-2xl bg-red-50 border border-red-100">
        <svg class="w-5 h-5 shrink-0 mt-0.5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
        <ul class="text-sm text-red-700 space-y-0.5">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- ── Section 1: Industry Template ────────────────────── --}}
    <div class="card">
        <div class="mb-4">
            <h2 class="text-[#1E1B4B] font-semibold text-base">Industry Template</h2>
            <p class="text-gray-400 text-sm mt-0.5">Choose the template that matches your industry. This controls which columns are expected in your import file.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3" x-data="{ selected: '{{ $settings->industry_template_key ?? 'default' }}' }">
            @foreach($templateKeys as $key => $label)
            <label class="relative flex flex-col gap-1.5 p-4 rounded-2xl border-2 cursor-pointer transition-all"
                   :class="selected === '{{ $key }}'
                       ? 'border-[#7B61FF] bg-[#F5F3FF]'
                       : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'"
                   @click="selected = '{{ $key }}'">
                <input type="radio"
                       name="industry_template_key"
                       value="{{ $key }}"
                       class="sr-only"
                       {{ ($settings->industry_template_key ?? 'default') === $key ? 'checked' : '' }}
                       x-ref="radio_{{ $key }}"
                       @click="selected = '{{ $key }}'">
                {{-- Checkmark badge --}}
                <span x-show="selected === '{{ $key }}'"
                      class="absolute top-3 right-3 w-5 h-5 rounded-full flex items-center justify-center"
                      style="background: #7B61FF;">
                    <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                </span>
                <span class="text-sm font-semibold text-[#1E1B4B] pr-6">{{ $label }}</span>
                <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-gray-100 text-gray-500 w-fit">{{ $key }}</span>
            </label>
            @endforeach
        </div>
    </div>

    {{-- ── Section 2: Required / Optional Fields ───────────── --}}
    @php
        $templateConfig = config("referralbunny_import_templates.{$settings->industry_template_key}", config('referralbunny_import_templates.default', []));
        $requiredFields = $templateConfig['required_fields'] ?? [];
        $optionalFields = $templateConfig['optional_fields'] ?? [];
        $extraRequired  = $settings->extra_required_fields ?? [];
    @endphp
    <div class="card">
        <div class="mb-4">
            <h2 class="text-[#1E1B4B] font-semibold text-base">Required & Optional Fields</h2>
            <p class="text-gray-400 text-sm mt-0.5">Core required fields are always enforced. You can mark additional optional fields as required for your workflow.</p>
        </div>

        {{-- Always required --}}
        <div class="mb-4">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Always Required (Template Core)</p>
            <div class="flex flex-wrap gap-2">
                @foreach($requiredFields as $field)
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold" style="background: #EDE9FE; color: #7B61FF;">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    {{ str_replace('_', ' ', $field) }}
                </span>
                @endforeach
            </div>
        </div>

        {{-- Make optional fields required --}}
        @if(count($optionalFields) > 0)
        <div>
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Optional Fields — Toggle Required</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                @foreach($optionalFields as $field)
                <label class="flex items-center gap-2.5 p-2.5 rounded-xl border border-gray-100 hover:bg-gray-50 cursor-pointer transition-colors">
                    <input type="checkbox"
                           name="extra_required_fields[]"
                           value="{{ $field }}"
                           {{ in_array($field, $extraRequired) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-[#7B61FF]">
                    <span class="text-sm text-gray-700">{{ str_replace('_', ' ', $field) }}</span>
                </label>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- ── Section 3: Duplicate Handling ───────────────────── --}}
    <div class="card">
        <div class="mb-4">
            <h2 class="text-[#1E1B4B] font-semibold text-base">Duplicate Handling</h2>
            <p class="text-gray-400 text-sm mt-0.5">Choose what happens when an imported deal matches an existing one.</p>
        </div>

        <div class="space-y-2.5">
            @php
                $dupOptions = [
                    'allow'              => ['label' => 'Allow',                     'desc' => 'Create a new deal even if a duplicate exists.'],
                    'block'              => ['label' => 'Block',                      'desc' => 'Reject any row that matches an existing deal.'],
                    'require_review'     => ['label' => 'Require Review',             'desc' => 'Flag duplicates for manual review before importing.'],
                    'merge_approved'     => ['label' => 'Allow Merge (Approved)',     'desc' => 'Merge missing fields into the existing deal after review.'],
                    'overwrite_approved' => ['label' => 'Allow Overwrite (Approved)', 'desc' => 'Overwrite all fields in the existing deal after review.'],
                ];
                $currentDup = $settings->duplicate_handling ?? 'require_review';
            @endphp
            @foreach($dupOptions as $value => $opt)
            <label class="flex items-start gap-3 p-3 rounded-xl border border-gray-100 hover:bg-gray-50 cursor-pointer transition-colors">
                <input type="radio"
                       name="duplicate_handling"
                       value="{{ $value }}"
                       {{ $currentDup === $value ? 'checked' : '' }}
                       class="mt-0.5 text-[#7B61FF] border-gray-300">
                <div>
                    <p class="text-sm font-medium text-[#1E1B4B]">{{ $opt['label'] }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ $opt['desc'] }}</p>
                </div>
            </label>
            @endforeach
        </div>
    </div>

    {{-- ── Section 4: Unknown Organization ─────────────────── --}}
    <div class="card">
        <div class="mb-4">
            <h2 class="text-[#1E1B4B] font-semibold text-base">Unknown Organization</h2>
            <p class="text-gray-400 text-sm mt-0.5">What to do when an organization in the import file is not found in the system.</p>
        </div>

        <div class="space-y-2.5">
            @php
                $orgOptions = [
                    'auto_create'    => ['label' => 'Auto-Create',        'desc' => 'Automatically create the organization if it does not exist.'],
                    'flag_review'    => ['label' => 'Flag for Review',     'desc' => 'Mark the row for review — do not import until resolved.'],
                    'reject'         => ['label' => 'Reject Row',          'desc' => 'Reject any row whose organization is not found.'],
                ];
                $currentOrg = $settings->unknown_org_behavior ?? 'auto_create';
            @endphp
            @foreach($orgOptions as $value => $opt)
            <label class="flex items-start gap-3 p-3 rounded-xl border border-gray-100 hover:bg-gray-50 cursor-pointer transition-colors">
                <input type="radio"
                       name="unknown_org_behavior"
                       value="{{ $value }}"
                       {{ $currentOrg === $value ? 'checked' : '' }}
                       class="mt-0.5 text-[#7B61FF] border-gray-300">
                <div>
                    <p class="text-sm font-medium text-[#1E1B4B]">{{ $opt['label'] }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ $opt['desc'] }}</p>
                </div>
            </label>
            @endforeach
        </div>
    </div>

    {{-- ── Section 5: Referrer Permissions ─────────────────── --}}
    <div class="card">
        <div class="mb-4">
            <h2 class="text-[#1E1B4B] font-semibold text-base">Referrer Permissions</h2>
            <p class="text-gray-400 text-sm mt-0.5">Control what referrers can do during and after an import.</p>
        </div>

        <div class="space-y-2.5">
            @php
                $perms = [
                    'allow_referrer_import'      => 'Allow Referrer imports (referrers can upload their own deal files)',
                    'allow_referrer_new_deals'   => 'Allow Referrer to add new deals manually',
                    'allow_referrer_partner_add' => 'Allow Referrer to add Partner emails on their deals',
                ];
            @endphp
            @foreach($perms as $field => $permLabel)
            <label class="flex items-start gap-3 p-3 rounded-xl border border-gray-100 hover:bg-gray-50 cursor-pointer transition-colors">
                <input type="checkbox"
                       name="{{ $field }}"
                       value="1"
                       {{ ($settings->{$field} ?? false) ? 'checked' : '' }}
                       class="mt-0.5 rounded text-[#7B61FF] border-gray-300">
                <span class="text-sm text-[#1E1B4B]">{{ $permLabel }}</span>
            </label>
            @endforeach
        </div>
    </div>

    {{-- ── Footer save strip ────────────────────────────────── --}}
    <div class="card flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <p class="text-sm text-gray-400">Changes apply to all future imports for this tenant.</p>
        <div class="flex items-center gap-2">
            <a href="{{ route('tenant.imports.deals', $tenant->id) }}" class="btn-secondary">
                Discard
            </a>
            <button type="submit" class="btn-primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Save Settings
            </button>
        </div>
    </div>

</form>
@endsection
