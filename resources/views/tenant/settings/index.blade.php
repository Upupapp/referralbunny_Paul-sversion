@extends('layouts.app')
@section('title', 'Settings')

@section('nav')
    @include('tenant._nav')
@endsection

@section('content')
<div class="space-y-5">

    <div class="card">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white text-lg font-bold shrink-0"
                 style="background-color: {{ $tenant->accent_color ?? '#7B61FF' }}">
                {{ strtoupper(substr($tenant->name, 0, 2)) }}
            </div>
            <div>
                <h2 class="text-[#1E1B4B] font-bold text-lg">{{ $tenant->name }}</h2>
                <p class="text-gray-400 text-sm">{{ $tenant->program_name }}</p>
            </div>
        </div>
    </div>

    {{-- R Bunny help card --}}
    <div class="flex gap-3 p-4 rounded-xl bg-[#F0EFFA] border border-purple-100">
        <x-r-bunny variant="helper" size="sm" :decorative="true" class="shrink-0 mt-0.5" />
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-[#1E1B4B] mb-0.5">R Bunny's Settings Tips</p>
            <p class="text-xs text-gray-500 leading-relaxed mb-2">These settings apply to your entire referral program. Changes take effect immediately for all referrers and deals.</p>
            <ul class="space-y-1">
                @foreach([
                    'Agreement files must be signed before referrers can submit deals.',
                    'Commission rules affect all new and pending deals.',
                    'Pipeline stages define how deals move from intro to paid.',
                ] as $tip)
                <li class="flex items-start gap-1.5 text-xs text-gray-500">
                    <svg class="w-3.5 h-3.5 text-purple-400 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    {{ $tip }}
                </li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <div class="lg:col-span-2 space-y-4">

            <form method="POST" action="{{ route('settings.update', $tenant->id) }}"
                  class="card" id="general-settings"
                  x-data="{ saving: false }"
                  @submit="if (!$el.checkValidity()) return; saving = true">
                @csrf
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-[#1E1B4B]">General Settings</h3>
                    <button type="submit" class="btn-primary text-sm py-1.5 px-4" :disabled="saving">
                        <svg x-show="saving" class="w-3.5 h-3.5 animate-spin inline mr-1" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-text="saving ? 'Saving…' : 'Save'"></span>
                    </button>
                </div>

                @if(session('settings_saved'))
                <div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-xl bg-green-50 border border-green-200 text-sm text-green-700">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ session('settings_saved') }}
                </div>
                @endif

                @if($errors->any())
                <div class="mb-4 flex items-start gap-2 px-3 py-2 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700">
                    <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
                @endif

                <div class="space-y-4">
                    <div>
                        <label class="form-label">Program Name</label>
                        <input type="text" name="program_name" class="form-input"
                               value="{{ old('program_name', $tenant->program_name) }}"
                               placeholder="Your program name">
                    </div>
                    <div>
                        <label class="form-label">Business Name</label>
                        <input type="text" name="business_name" class="form-input"
                               value="{{ old('business_name', $tenant->business_name) }}"
                               placeholder="Business or company name">
                    </div>
                    <div>
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-input" rows="3"
                                  placeholder="Describe your referral program">{{ old('description', $tenant->description) }}</textarea>
                    </div>
                    <div>
                        <label class="form-label">Accent Color</label>
                        <div class="flex items-center gap-3">
                            <input type="color" name="accent_color"
                                   class="h-10 w-16 rounded-lg border border-gray-200 p-1 cursor-pointer"
                                   value="{{ old('accent_color', $tenant->accent_color ?? '#7B61FF') }}">
                            <span class="text-sm text-gray-400">Used for avatars and highlights</span>
                        </div>
                    </div>
                </div>
            </form>

            <div class="card" id="contact-info">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-[#1E1B4B]">Contact Information</h3>
                    <a href="{{ route('tenant.profile', $tenant->id) }}" class="btn-secondary text-sm py-1.5 px-4">Edit Profile</a>
                </div>
                <div class="space-y-3">
                    <div class="flex items-center gap-3 text-sm">
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        <span class="text-gray-500 w-24 shrink-0">Admin</span>
                        <span class="text-[#1E1B4B] font-medium">{{ $tenant->admin_name ?? '—' }}</span>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        <span class="text-gray-500 w-24 shrink-0">Email</span>
                        <span class="text-[#1E1B4B] font-medium">{{ $tenant->admin_email ?? '—' }}</span>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                        <span class="text-gray-500 w-24 shrink-0">Phone</span>
                        <span class="text-[#1E1B4B] font-medium">{{ $tenant->contact_phone ?? '—' }}</span>
                    </div>
                </div>
            </div>

            {{-- Agreement Files --}}
            <div class="card" id="agreement-files"
                 x-data="agreementManager('{{ $tenant->id }}')">
                <div class="flex items-start justify-between mb-1">
                    <div>
                        <h3 class="font-semibold text-[#1E1B4B]">Agreement Files</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Referrers must acknowledge required agreements before they can refer deals.</p>
                    </div>
                    <button @click="openAdd()" class="btn-primary text-sm py-1.5 px-3 shrink-0 ml-4">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        <span class="hidden sm:inline ml-1">Add Agreement</span>
                    </button>
                </div>

                <div class="mt-4">
                    {{-- Loading --}}
                    <div x-show="loading" class="flex items-center justify-center py-8 gap-2 text-gray-400">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <span class="text-sm">Loading agreements…</span>
                    </div>

                    {{-- Empty state --}}
                    <div x-show="!loading && agreements.length === 0" class="text-center py-10 border-2 border-dashed border-gray-100 rounded-xl">
                        <div class="flex justify-center mb-3">
                            <div class="w-12 h-12 rounded-xl bg-purple-50 flex items-center justify-center">
                                <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                        </div>
                        <p class="text-sm font-medium text-gray-600">No agreement files yet</p>
                        <p class="text-xs text-gray-400 mt-1">Add an NDA or Non-Compete to require referrer sign-off.</p>
                        <button @click="openAdd()" class="btn-primary text-sm mt-4 py-1.5 px-4">Add First Agreement</button>
                    </div>

                    {{-- Agreement list --}}
                    <div x-show="!loading && agreements.length > 0" class="space-y-2">
                        <template x-for="a in agreements" :key="a.id">
                            <div class="flex items-start gap-3 p-3.5 rounded-xl border border-gray-100 hover:border-purple-200 transition-colors group">
                                <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 mt-0.5"
                                     :class="a.is_active ? 'bg-purple-50' : 'bg-gray-100'">
                                    <svg class="w-4 h-4" :class="a.is_active ? 'text-purple-600' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <p class="text-sm font-semibold text-[#1E1B4B]" x-text="a.label"></p>
                                        <span x-show="a.is_required" class="badge badge-red text-xs py-0.5 px-2">Required</span>
                                        <span x-show="!a.is_required" class="badge badge-gray text-xs py-0.5 px-2">Optional</span>
                                        <span x-show="!a.is_active" class="badge badge-gray text-xs py-0.5 px-2">Inactive</span>
                                    </div>
                                    <p x-show="a.description" class="text-xs text-gray-400 mt-0.5 line-clamp-1" x-text="a.description"></p>
                                    <div class="flex items-center gap-3 mt-1.5 flex-wrap">
                                        <span x-show="a.version" class="text-xs text-gray-400" x-text="'v' + a.version"></span>
                                        <span x-show="a.effective_date" class="text-xs text-gray-400"
                                              x-text="a.effective_date ? 'Effective ' + new Date(a.effective_date).toLocaleDateString('en', {month:'short',day:'numeric',year:'numeric'}) : ''"></span>
                                        <a x-show="a.file_url" :href="a.file_url" target="_blank"
                                           class="text-xs text-purple-600 hover:text-purple-700 flex items-center gap-1 transition-colors">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13l-3 3m0 0l-3-3m3 3V8m0 13a9 9 0 110-18 9 9 0 010 18z"/>
                                            </svg>
                                            View File
                                        </a>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity shrink-0 self-center">
                                    <button @click="openEdit(a)"
                                            class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-700 transition-colors"
                                            title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    <button @click="deleteAgreement(a.id)"
                                            class="p-1.5 rounded-lg hover:bg-red-50 text-gray-400 hover:text-red-500 transition-colors"
                                            title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Add / Edit Modal --}}
                <div x-show="showModal" x-cloak
                     class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4"
                     @keydown.escape.window="closeModal()">
                    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg" @click.stop>
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                            <h3 class="font-semibold text-[#1E1B4B]" x-text="editId ? 'Edit Agreement' : 'Add Agreement File'"></h3>
                            <button @click="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <label class="form-label">Agreement Label *</label>
                                <input type="text" x-model="form.label" class="form-input"
                                       placeholder="e.g. Non-Disclosure Agreement (NDA)">
                                <p class="text-xs text-gray-400 mt-1">Shown to referrers when they are asked to sign.</p>
                            </div>
                            <div>
                                <label class="form-label">Description</label>
                                <textarea x-model="form.description" class="form-input" rows="2"
                                          placeholder="Brief description of what this agreement covers"></textarea>
                            </div>
                            <div>
                                <label class="form-label">File URL</label>
                                <input type="text" x-model="form.file_url" class="form-input"
                                       placeholder="https://… link to the full document">
                                <p class="text-xs text-gray-400 mt-1">Link to Google Docs, Supabase Storage, or any hosted document.</p>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="form-label">Version</label>
                                    <input type="text" x-model="form.version" class="form-input" placeholder="1.0">
                                </div>
                                <div>
                                    <label class="form-label">Effective Date</label>
                                    <input type="date" x-model="form.effective_date" class="form-input">
                                </div>
                            </div>

                            {{-- Required toggle --}}
                            <div class="flex items-center justify-between p-3 rounded-xl bg-gray-50">
                                <div>
                                    <p class="text-sm font-medium text-[#1E1B4B]">Required for referrers</p>
                                    <p class="text-xs text-gray-400 mt-0.5">Referrers cannot refer deals without signing this.</p>
                                </div>
                                <button type="button" @click="form.is_required = !form.is_required"
                                        :class="form.is_required ? 'bg-purple-600' : 'bg-gray-300'"
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none shrink-0 ml-4">
                                    <span :class="form.is_required ? 'translate-x-6' : 'translate-x-1'"
                                          class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow"></span>
                                </button>
                            </div>

                            <p x-show="formError" class="text-xs text-red-600 font-medium" x-text="formError"></p>

                            <div class="flex justify-end gap-3 pt-1">
                                <button @click="closeModal()" class="btn-secondary">Cancel</button>
                                <button @click="saveAgreement()" :disabled="saving" class="btn-primary"
                                        x-text="saving ? 'Saving…' : (editId ? 'Save Changes' : 'Add Agreement')"></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Required Documents --}}
            <div class="card" id="required-documents"
                 x-data="requiredDocManager('{{ $tenant->id }}')">
                <div class="flex items-start justify-between mb-1">
                    <div>
                        <h3 class="font-semibold text-[#1E1B4B]">Required Documents</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Documents referrers must submit before they can refer deals. Admin reviews and approves each submission.</p>
                    </div>
                    <button @click="openAdd()" class="btn-primary text-sm py-1.5 px-3 shrink-0 ml-4">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        <span class="hidden sm:inline ml-1">Add Document</span>
                    </button>
                </div>

                <div class="mt-4">
                    <div x-show="loading" class="flex items-center justify-center py-8 gap-2 text-gray-400">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <span class="text-sm">Loading documents…</span>
                    </div>

                    <div x-show="!loading && docs.length === 0" class="text-center py-10 border-2 border-dashed border-gray-100 rounded-xl">
                        <div class="flex justify-center mb-3">
                            <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center">
                                <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2"/>
                                </svg>
                            </div>
                        </div>
                        <p class="text-sm font-medium text-gray-600">No required documents yet</p>
                        <p class="text-xs text-gray-400 mt-1">Add a Valid ID or other document to require from referrers.</p>
                        <button @click="openAdd()" class="btn-primary text-sm mt-4 py-1.5 px-4">Add First Document</button>
                    </div>

                    <div x-show="!loading && docs.length > 0" class="space-y-2">
                        <template x-for="d in docs" :key="d.id">
                            <div class="flex items-start gap-3 p-3.5 rounded-xl border border-gray-100 hover:border-blue-200 transition-colors group">
                                <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 mt-0.5"
                                     :class="d.is_active ? 'bg-blue-50' : 'bg-gray-100'">
                                    <svg class="w-4 h-4" :class="d.is_active ? 'text-blue-600' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2"/>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <p class="text-sm font-semibold text-[#1E1B4B]" x-text="d.label"></p>
                                        <span x-show="d.is_required" class="badge badge-red text-xs py-0.5 px-2">Required</span>
                                        <span x-show="!d.is_required" class="badge badge-gray text-xs py-0.5 px-2">Optional</span>
                                        <span x-show="!d.is_active" class="badge badge-gray text-xs py-0.5 px-2">Inactive</span>
                                    </div>
                                    <p x-show="d.description" class="text-xs text-gray-400 mt-0.5 line-clamp-1" x-text="d.description"></p>
                                    <div class="flex items-center gap-3 mt-1 flex-wrap">
                                        <span class="text-xs text-gray-400" x-text="docTypeLabel(d.document_type)"></span>
                                        <span x-show="d.accepted_formats" class="text-xs text-gray-400" x-text="'Accepts: ' + d.accepted_formats"></span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity shrink-0 self-center">
                                    <button @click="openEdit(d)" class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-700 transition-colors" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button @click="deleteDoc(d.id)" class="p-1.5 rounded-lg hover:bg-red-50 text-gray-400 hover:text-red-500 transition-colors" title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Add / Edit Modal --}}
                <div x-show="showModal" x-cloak
                     class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4"
                     @keydown.escape.window="closeModal()">
                    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg" @click.stop>
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                            <h3 class="font-semibold text-[#1E1B4B]" x-text="editId ? 'Edit Document' : 'Add Required Document'"></h3>
                            <button @click="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <label class="form-label">Document Label *</label>
                                <input type="text" x-model="form.label" class="form-input" placeholder="e.g. Valid Government ID">
                            </div>
                            <div>
                                <label class="form-label">Document Type *</label>
                                <select x-model="form.document_type" class="form-input">
                                    <option value="valid_id">Valid Government ID</option>
                                    <option value="business_permit">Business Permit</option>
                                    <option value="bir_registration">BIR / Tax Registration</option>
                                    <option value="dti_sec_registration">DTI / SEC Registration</option>
                                    <option value="bank_details">Bank Account Details</option>
                                    <option value="selfie_with_id">Selfie with ID</option>
                                    <option value="proof_of_address">Proof of Address</option>
                                    <option value="other">Custom / Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Description</label>
                                <textarea x-model="form.description" class="form-input" rows="2" placeholder="Instructions shown to referrers when submitting"></textarea>
                            </div>
                            <div>
                                <label class="form-label">Accepted Formats</label>
                                <input type="text" x-model="form.accepted_formats" class="form-input" placeholder="PDF, JPG, PNG">
                            </div>
                            <div class="flex items-center justify-between p-3 rounded-xl bg-gray-50">
                                <div>
                                    <p class="text-sm font-medium text-[#1E1B4B]">Required for referrers</p>
                                    <p class="text-xs text-gray-400 mt-0.5">Referrers cannot refer deals without this document being approved.</p>
                                </div>
                                <button type="button" @click="form.is_required = !form.is_required"
                                        :class="form.is_required ? 'bg-[#7B61FF]' : 'bg-gray-300'"
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none shrink-0 ml-4">
                                    <span :class="form.is_required ? 'translate-x-6' : 'translate-x-1'"
                                          class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow"></span>
                                </button>
                            </div>
                            <p x-show="formError" class="text-xs text-red-600 font-medium" x-text="formError"></p>
                            <div class="flex justify-end gap-3 pt-1">
                                <button @click="closeModal()" class="btn-secondary">Cancel</button>
                                <button @click="saveDoc()" :disabled="saving" class="btn-primary"
                                        x-text="saving ? 'Saving…' : (editId ? 'Save Changes' : 'Add Document')"></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Legal Agreements by Role --}}
            <div class="card" id="legal-agreements"
                 x-data="legalAgreementManager('{{ $tenant->id }}')">
                <div class="flex items-start justify-between mb-1">
                    <div>
                        <h3 class="font-semibold text-[#1E1B4B]">Legal Agreements by Role</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Paste NDA, Non-Compete, or other legal text. Users must scroll through and accept before accessing the workspace.</p>
                    </div>
                    <button @click="openAdd()" class="btn-primary text-sm py-1.5 px-3 shrink-0 ml-4">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span class="hidden sm:inline ml-1">Add Agreement</span>
                    </button>
                </div>

                <div class="mt-4">
                    <div x-show="loading" class="flex items-center justify-center py-8 gap-2 text-gray-400">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span class="text-sm">Loading…</span>
                    </div>

                    <div x-show="!loading && agreements.length === 0" class="text-center py-10 border-2 border-dashed border-gray-100 rounded-xl">
                        <div class="flex justify-center mb-3">
                            <div class="w-12 h-12 rounded-xl bg-indigo-50 flex items-center justify-center">
                                <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            </div>
                        </div>
                        <p class="text-sm font-medium text-gray-600">No legal agreements configured</p>
                        <p class="text-xs text-gray-400 mt-1">Add an NDA or Non-Compete that new members must accept when joining.</p>
                        <button @click="openAdd()" class="btn-primary text-sm mt-4 py-1.5 px-4">Add First Agreement</button>
                    </div>

                    <div x-show="!loading && agreements.length > 0" class="space-y-2">
                        <template x-for="a in agreements" :key="a.id">
                            <div class="flex items-start gap-3 p-3.5 rounded-xl border border-gray-100 hover:border-indigo-200 transition-colors group">
                                <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 mt-0.5"
                                     :class="a.is_active ? 'bg-indigo-50' : 'bg-gray-100'">
                                    <svg class="w-4 h-4" :class="a.is_active ? 'text-indigo-600' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-1.5 mb-1">
                                        <p class="text-sm font-semibold text-[#1E1B4B]" x-text="a.title"></p>
                                        <span class="badge text-xs py-0.5 px-2"
                                              :class="{
                                                  'bg-purple-100 text-purple-700': a.type === 'nda',
                                                  'bg-red-100 text-red-700':       a.type === 'non_compete',
                                                  'bg-blue-100 text-blue-700':     a.type === 'confidentiality',
                                                  'bg-gray-100 text-gray-600':     a.type === 'custom',
                                              }"
                                              x-text="{ nda: 'NDA', non_compete: 'Non-Compete', confidentiality: 'Confidentiality', custom: 'Custom' }[a.type] || a.type"></span>
                                        <span x-show="a.is_required" class="badge badge-red text-xs py-0.5 px-2">Required</span>
                                        <span x-show="!a.is_required" class="badge badge-gray text-xs py-0.5 px-2">Optional</span>
                                        <span x-show="!a.is_active" class="badge badge-gray text-xs py-0.5 px-2">Inactive</span>
                                    </div>
                                    {{-- Applicable roles --}}
                                    <div class="flex flex-wrap gap-1 mb-1.5">
                                        <template x-if="!a.applicable_roles || a.applicable_roles.length === 0">
                                            <span class="text-xs text-gray-400">All roles</span>
                                        </template>
                                        <template x-for="r in (a.applicable_roles || [])" :key="r">
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-600 capitalize" x-text="r"></span>
                                        </template>
                                    </div>
                                    <div class="flex items-center gap-3 text-xs text-gray-400 flex-wrap">
                                        <span x-show="a.version" x-text="'v' + a.version"></span>
                                        <span x-show="a.effective_date" x-text="a.effective_date ? 'Effective ' + new Date(a.effective_date).toLocaleDateString('en',{month:'short',day:'numeric',year:'numeric'}) : ''"></span>
                                        <span x-show="a.acceptances_count > 0" x-text="a.acceptances_count + ' acceptance' + (a.acceptances_count === 1 ? '' : 's')"></span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity shrink-0 self-center">
                                    <button @click="openEdit(a)" class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-700 transition-colors" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button @click="deleteLegalAgreement(a.id)" class="p-1.5 rounded-lg hover:bg-red-50 text-gray-400 hover:text-red-500 transition-colors" title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Add / Edit Modal --}}
                <div x-show="showModal" x-cloak
                     class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4"
                     @keydown.escape.window="closeModal()">
                    <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col" @click.stop>
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 shrink-0">
                            <h3 class="font-semibold text-[#1E1B4B]" x-text="editId ? 'Edit Legal Agreement' : 'Add Legal Agreement'"></h3>
                            <button @click="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <div class="p-6 space-y-4 overflow-y-auto flex-1">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="col-span-2">
                                    <label class="form-label">Agreement Title <span class="text-red-500">*</span></label>
                                    <input type="text" x-model="form.title" class="form-input" placeholder="e.g. Non-Disclosure Agreement">
                                </div>
                                <div>
                                    <label class="form-label">Type <span class="text-red-500">*</span></label>
                                    <select x-model="form.type" class="form-input">
                                        <option value="nda">NDA — Non-Disclosure Agreement</option>
                                        <option value="non_compete">Non-Compete Agreement</option>
                                        <option value="confidentiality">Confidentiality Agreement</option>
                                        <option value="custom">Custom Agreement</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Version</label>
                                    <input type="text" x-model="form.version" class="form-input" placeholder="1.0">
                                </div>
                            </div>

                            <div>
                                <label class="form-label">Applies to Roles</label>
                                <p class="text-xs text-gray-400 mb-2">Leave all unchecked to apply to every role.</p>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                    @foreach(['admin','manager','referrer','partner','member','viewer'] as $roleOpt)
                                    <label class="flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 hover:border-[#7B61FF] cursor-pointer transition-colors"
                                           :class="form.applicable_roles.includes('{{ $roleOpt }}') ? 'border-[#7B61FF] bg-purple-50' : ''">
                                        <input type="checkbox"
                                               value="{{ $roleOpt }}"
                                               :checked="form.applicable_roles.includes('{{ $roleOpt }}')"
                                               @change="if ($event.target.checked) { if (!form.applicable_roles.includes('{{ $roleOpt }}')) form.applicable_roles.push('{{ $roleOpt }}') } else { form.applicable_roles = form.applicable_roles.filter(r => r !== '{{ $roleOpt }}') }"
                                               class="w-3.5 h-3.5 rounded accent-[#7B61FF]">
                                        <span class="text-sm capitalize">{{ ucfirst($roleOpt) }}</span>
                                    </label>
                                    @endforeach
                                </div>
                            </div>

                            <div>
                                <label class="form-label">Full Legal Text <span class="text-red-500">*</span></label>
                                <p class="text-xs text-gray-400 mb-1.5">Paste the complete legal agreement text. Users will scroll through this before accepting.</p>
                                <textarea x-model="form.content" rows="14"
                                          class="form-input font-mono text-xs leading-relaxed resize-y"
                                          placeholder="Paste the full legal text of your NDA, Non-Compete, or agreement here...&#10;&#10;Example:&#10;NON-DISCLOSURE AGREEMENT&#10;&#10;This Non-Disclosure Agreement ('Agreement') is entered into as of the date of acceptance..."></textarea>
                            </div>

                            <div>
                                <label class="form-label">Effective Date</label>
                                <input type="date" x-model="form.effective_date" class="form-input">
                            </div>

                            <div class="flex items-center justify-between p-3 rounded-xl bg-gray-50">
                                <div>
                                    <p class="text-sm font-medium text-[#1E1B4B]">Required acceptance</p>
                                    <p class="text-xs text-gray-400 mt-0.5">Users must accept this before accessing the workspace.</p>
                                </div>
                                <button type="button" @click="form.is_required = !form.is_required"
                                        :class="form.is_required ? 'bg-[#7B61FF]' : 'bg-gray-300'"
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none shrink-0 ml-4">
                                    <span :class="form.is_required ? 'translate-x-6' : 'translate-x-1'"
                                          class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow"></span>
                                </button>
                            </div>

                            <p x-show="formError" class="text-xs text-red-600 font-medium p-2 bg-red-50 rounded-lg" x-text="formError"></p>
                        </div>
                        <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-100 shrink-0">
                            <button @click="closeModal()" class="btn-secondary">Cancel</button>
                            <button @click="saveLegalAgreement()" :disabled="saving" class="btn-primary"
                                    x-text="saving ? 'Saving…' : (editId ? 'Save Changes' : 'Add Agreement')"></button>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="space-y-4">

            <div class="card">
                <h3 class="font-semibold text-[#1E1B4B] text-sm mb-3">Settings Sections</h3>
                <div class="space-y-1">
                    @php $navItems = [
                        ['label' => 'General Settings',           'href' => '#general-settings',   'live' => true,  'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
                        ['label' => 'Contact Info',                'href' => '#contact-info',       'live' => true,  'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
                        ['label' => 'Agreement Files',             'href' => '#agreement-files',    'live' => true,  'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                        ['label' => 'Required Documents',          'href' => '#required-documents', 'live' => true,  'icon' => 'M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2'],
                        ['label' => 'Legal Agreements by Role',    'href' => '#legal-agreements',   'live' => true,  'icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'],
                        ['label' => 'Pipeline & Stages',           'href' => '#',                   'live' => false, 'icon' => 'M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2'],
                        ['label' => 'Commission Rules',            'href' => '#',                   'live' => false, 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                        ['label' => 'Notification Templates',      'href' => '#',                   'live' => false, 'icon' => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9'],
                        ['label' => 'API & Integrations',          'href' => route('tenant.integrations', $tenant->id), 'live' => true,  'icon' => 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4'],
                        ['label' => 'Data & Privacy',              'href' => '#',                   'live' => false, 'icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'],
                    ]; @endphp
                    @foreach($navItems as $section)
                    @if($section['live'])
                    <a href="{{ $section['href'] }}"
                       class="flex items-center gap-3 w-full px-3 py-2.5 rounded-xl hover:bg-[#F0EFFA] transition-colors text-left group">
                        <svg class="w-4 h-4 text-gray-400 shrink-0 group-hover:text-[#7B61FF] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $section['icon'] }}"/>
                        </svg>
                        <span class="text-sm text-gray-700 group-hover:text-[#1E1B4B] transition-colors flex-1">{{ $section['label'] }}</span>
                        <svg class="w-3.5 h-3.5 text-gray-300 group-hover:text-gray-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                    @else
                    <div class="flex items-center gap-3 w-full px-3 py-2.5 rounded-xl text-left opacity-50 cursor-not-allowed">
                        <svg class="w-4 h-4 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $section['icon'] }}"/>
                        </svg>
                        <span class="text-sm text-gray-400 flex-1">{{ $section['label'] }}</span>
                        <span class="text-[10px] bg-gray-100 text-gray-400 px-1.5 py-0.5 rounded font-medium">Soon</span>
                    </div>
                    @endif
                    @endforeach
                </div>
            </div>

            <div class="card border border-red-100">
                <h3 class="font-semibold text-red-600 text-sm mb-2">Danger Zone</h3>
                <p class="text-xs text-gray-400 mb-3">Irreversible actions that affect this tenant's data.</p>
                <button class="btn-secondary text-sm text-red-600 border-red-200 hover:bg-red-50 w-full">
                    Archive Tenant
                </button>
            </div>

        </div>

    </div>

</div>

<script>
function agreementManager(tenantId) {
    return {
        agreements: [],
        loading: true,
        showModal: false,
        saving: false,
        formError: '',
        editId: null,
        form: {
            label: '',
            description: '',
            file_url: '',
            is_required: true,
            version: '1.0',
            effective_date: '',
        },

        async init() {
            try {
                const res = await fetch(`/api/agreements?tenant_id=${tenantId}`, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error('Failed to load');
                const data = await res.json();
                this.agreements = Array.isArray(data) ? data : [];
            } catch(e) {
                this.agreements = [];
                this.$dispatch('show-toast', { type: 'error', message: 'Unable to load agreements. Please refresh.' });
            }
            this.loading = false;
        },

        openAdd() {
            this.editId    = null;
            this.form      = { label: '', description: '', file_url: '', is_required: true, version: '1.0', effective_date: '' };
            this.formError = '';
            this.showModal = true;
        },

        openEdit(a) {
            this.editId = a.id;
            this.form   = {
                label:          a.label,
                description:    a.description || '',
                file_url:       a.file_url || '',
                is_required:    !!a.is_required,
                version:        a.version || '1.0',
                effective_date: a.effective_date ? String(a.effective_date).substring(0, 10) : '',
            };
            this.formError = '';
            this.showModal = true;
        },

        closeModal() { this.showModal = false; this.formError = ''; },

        async saveAgreement() {
            if (!this.form.label.trim()) { this.formError = 'Agreement label is required.'; return; }
            this.saving = true; this.formError = '';
            try {
                const url    = this.editId ? `/api/agreements/${this.editId}` : '/api/agreements';
                const method = this.editId ? 'PUT' : 'POST';
                const body   = this.editId
                    ? { ...this.form }
                    : { ...this.form, tenant_id: tenantId };

                const res  = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                    },
                    body: JSON.stringify(body),
                });
                const data = await res.json();

                if (!res.ok) {
                    this.formError = data.message || (res.status === 403 ? 'You do not have permission.' : 'Failed to save agreement.');
                } else if (data.id) {
                    if (this.editId) {
                        const i = this.agreements.findIndex(a => a.id === this.editId);
                        if (i !== -1) this.agreements.splice(i, 1, data);
                    } else {
                        this.agreements.push(data);
                    }
                    this.showModal = false;
                    this.$dispatch('show-toast', {
                        type:    'success',
                        message: this.editId ? 'Agreement updated.' : 'Agreement added.',
                    });
                } else {
                    this.formError = data.message || 'Failed to save agreement.';
                }
            } catch(e) {
                this.formError = 'Network error. Please try again.';
            } finally {
                this.saving = false;
            }
        },

        async deleteAgreement(id) {
            if (!confirm('Delete this agreement? This will also remove all acknowledgment records for this agreement.')) return;
            try {
                await fetch(`/api/agreements/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                });
                this.agreements = this.agreements.filter(a => a.id !== id);
                this.$dispatch('show-toast', { type: 'success', message: 'Agreement deleted.' });
            } catch(e) {
                this.$dispatch('show-toast', { type: 'error', message: 'Failed to delete agreement.' });
            }
        },
    };
}

function requiredDocManager(tenantId) {
    const docTypeLabels = {
        valid_id:           'Valid Government ID',
        business_permit:    'Business Permit',
        bir_registration:   'BIR / Tax Registration',
        dti_sec_registration:'DTI / SEC Registration',
        bank_details:       'Bank Account Details',
        selfie_with_id:     'Selfie with ID',
        proof_of_address:   'Proof of Address',
        other:              'Custom / Other',
    };

    return {
        docs: [], loading: true,
        showModal: false, saving: false, formError: '',
        editId: null,
        form: { label: '', document_type: 'valid_id', description: '', accepted_formats: 'PDF, JPG, PNG', is_required: true },

        docTypeLabel(t) { return docTypeLabels[t] || t; },

        async init() {
            try {
                const res = await fetch(`/api/required-documents`);
                if (!res.ok) throw new Error('Failed to load');
                const data = await res.json();
                this.docs = Array.isArray(data) ? data : [];
            } catch(e) {
                this.docs = [];
                this.$dispatch('show-toast', { type: 'error', message: 'Unable to load document requirements. Please refresh.' });
            }
            this.loading = false;
        },

        openAdd() {
            this.editId = null;
            this.form = { label: '', document_type: 'valid_id', description: '', accepted_formats: 'PDF, JPG, PNG', is_required: true };
            this.formError = '';
            this.showModal = true;
        },

        openEdit(d) {
            this.editId = d.id;
            this.form = {
                label:            d.label || '',
                document_type:    d.document_type || 'other',
                description:      d.description || '',
                accepted_formats: d.accepted_formats || 'PDF, JPG, PNG',
                is_required:      !!d.is_required,
            };
            this.formError = '';
            this.showModal = true;
        },

        closeModal() { this.showModal = false; this.formError = ''; },

        async saveDoc() {
            if (!this.form.label.trim()) { this.formError = 'Document label is required.'; return; }
            this.saving = true; this.formError = '';
            try {
                const url    = this.editId ? `/api/required-documents/${this.editId}` : '/api/required-documents';
                const method = this.editId ? 'PUT' : 'POST';
                const body   = this.editId ? { ...this.form } : { ...this.form, tenant_id: tenantId };
                const res    = await fetch(url, {
                    method,
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' },
                    body: JSON.stringify(body),
                });
                const data = await res.json();
                if (!res.ok) {
                    this.formError = data.message || (res.status === 403 ? 'You do not have permission.' : 'Failed to save document.');
                } else if (data.id) {
                    if (this.editId) {
                        const i = this.docs.findIndex(d => d.id === this.editId);
                        if (i !== -1) this.docs.splice(i, 1, data);
                    } else {
                        this.docs.push(data);
                    }
                    this.showModal = false;
                    this.$dispatch('show-toast', { type: 'success', message: this.editId ? 'Document updated.' : 'Document added.' });
                } else {
                    this.formError = data.message || 'Failed to save document.';
                }
            } catch(e) { this.formError = 'Network error. Please try again.'; }
            finally { this.saving = false; }
        },

        async deleteDoc(id) {
            if (!confirm('Delete this document requirement? This will also remove all submission records.')) return;
            try {
                await fetch(`/api/required-documents/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                });
                this.docs = this.docs.filter(d => d.id !== id);
                this.$dispatch('show-toast', { type: 'success', message: 'Document requirement deleted.' });
            } catch(e) {
                this.$dispatch('show-toast', { type: 'error', message: 'Failed to delete.' });
            }
        },
    };
}

function legalAgreementManager(tenantId) {
    return {
        agreements: [],
        loading: true,
        showModal: false,
        saving: false,
        formError: '',
        editId: null,
        form: {
            title: '', type: 'nda', content: '',
            applicable_roles: [], is_required: true, is_active: true,
            version: '1.0', effective_date: '',
        },

        async init() {
            try {
                const res  = await fetch(`/api/legal-agreements?tenant_id=${tenantId}`, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                this.agreements = Array.isArray(data) ? data : [];
            } catch(e) {
                this.$dispatch('show-toast', { type: 'error', message: 'Unable to load legal agreements. Please refresh.' });
            }
            this.loading = false;
        },

        openAdd() {
            this.editId    = null;
            this.form      = { title: '', type: 'nda', content: '', applicable_roles: [], is_required: true, is_active: true, version: '1.0', effective_date: '' };
            this.formError = '';
            this.showModal = true;
        },

        openEdit(a) {
            this.editId    = a.id;
            this.form      = {
                title:            a.title,
                type:             a.type || 'nda',
                content:          a.content || '',
                applicable_roles: Array.isArray(a.applicable_roles) ? [...a.applicable_roles] : [],
                is_required:      !!a.is_required,
                is_active:        !!a.is_active,
                version:          a.version || '1.0',
                effective_date:   a.effective_date ? String(a.effective_date).substring(0, 10) : '',
            };
            this.formError = '';
            this.showModal = true;
        },

        closeModal() { this.showModal = false; this.formError = ''; },

        async saveLegalAgreement() {
            if (!this.form.title.trim())   { this.formError = 'Title is required.'; return; }
            if (!this.form.content.trim()) { this.formError = 'Legal text is required.'; return; }
            this.saving = true; this.formError = '';
            try {
                const url    = this.editId ? `/api/legal-agreements/${this.editId}` : '/api/legal-agreements';
                const method = this.editId ? 'PUT' : 'POST';
                const body   = this.editId ? { ...this.form } : { ...this.form, tenant_id: tenantId };

                const res  = await fetch(url, {
                    method,
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' },
                    body: JSON.stringify(body),
                });
                const data = await res.json();
                if (!res.ok) {
                    this.formError = data.message || (res.status === 403 ? 'Permission denied.' : 'Failed to save.');
                } else if (data.id) {
                    if (this.editId) {
                        const i = this.agreements.findIndex(a => a.id === this.editId);
                        if (i !== -1) this.agreements.splice(i, 1, data);
                    } else {
                        this.agreements.push(data);
                    }
                    this.showModal = false;
                    this.$dispatch('show-toast', { type: 'success', message: this.editId ? 'Agreement updated.' : 'Legal agreement added.' });
                } else {
                    this.formError = data.message || 'Failed to save.';
                }
            } catch(e) {
                this.formError = 'Network error. Please try again.';
            } finally {
                this.saving = false;
            }
        },

        async deleteLegalAgreement(id) {
            if (!confirm('Delete this legal agreement? All acceptance records will also be removed.')) return;
            try {
                await fetch(`/api/legal-agreements/${id}`, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                });
                this.agreements = this.agreements.filter(a => a.id !== id);
                this.$dispatch('show-toast', { type: 'success', message: 'Agreement deleted.' });
            } catch(e) {
                this.$dispatch('show-toast', { type: 'error', message: 'Failed to delete agreement.' });
            }
        },
    };
}
</script>
@endsection
