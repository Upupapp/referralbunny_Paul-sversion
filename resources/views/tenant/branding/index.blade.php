@extends('layouts.app')

@section('title', 'Brand Studio')
@section('stitch_page', 'tenant-brand-studio')

@section('nav')
    @include('tenant._nav', ['tenant' => $tenant])
@endsection

@section('content')
<div class="p-6 max-w-4xl mx-auto space-y-6"
     x-data="brandStudio(@js([
        'accentColor'          => $profile?->accent_color  ?? $tenant->accent_color  ?? '#FF5733',
        'sidebarColor'         => $profile?->sidebar_color ?? '#2D2B6E',
        'logoUrl'              => $profile?->logo_url       ?? $tenant->logo_url      ?? null,
        'status'               => $profile?->status         ?? 'draft',
        'publishedAt'          => $profile?->published_at?->toIso8601String() ?? null,
        'accentPasses'         => $accentPasses,
        'canEdit'              => $canEdit,
        'saveDraftUrl'         => route('tenant.settings.branding.save-draft', $tenant->id),
        'publishUrl'           => route('tenant.settings.branding.publish', $tenant->id),
        'uploadLogoUrl'        => route('tenant.settings.branding.upload-logo', $tenant->id),
        'deleteLogoUrl'        => route('tenant.settings.branding.delete-logo', $tenant->id),
        'revertUrl'            => route('tenant.settings.branding.revert', $tenant->id),
        'versionsUrl'          => route('tenant.settings.branding.versions', $tenant->id),
        'restoreVersionBaseUrl'=> route('tenant.settings.branding.restore-version', [$tenant->id, '__ID__']),
        'csrfToken'            => csrf_token(),
     ]))">

    {{-- ── Header ─────────────────────────────────────────────────────────── --}}
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-heading">Brand Studio</h1>
            <p class="text-sm text-gray-500 mt-1">Customize how your program looks across all portals.</p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            @if($canEdit)
            <span x-show="isDirty" x-cloak
                  class="text-xs text-amber-600 font-medium px-2 py-1 bg-amber-50 rounded-full border border-amber-200">
                Unsaved changes
            </span>
            <button type="button" @click="saveDraft()"
                    :disabled="saving || !isDirty"
                    x-show="canEdit"
                    class="btn-secondary text-sm disabled:opacity-40 disabled:cursor-not-allowed">
                <span x-show="!saving">Save Draft</span>
                <span x-show="saving" x-cloak>Saving…</span>
            </button>
            <button type="button" @click="showPublishModal = true"
                    x-show="canEdit"
                    class="btn-primary text-sm">
                Publish
            </button>
            @endif
        </div>
    </div>

    {{-- ── Toast ──────────────────────────────────────────────────────────── --}}
    <div x-show="toast.show" x-cloak x-transition
         class="fixed bottom-5 right-5 z-50 flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg text-sm font-medium"
         :class="toast.type === 'error' ? 'bg-red-600 text-white' : 'bg-green-600 text-white'">
        <span x-text="toast.message"></span>
    </div>

    {{-- ── Health Score ────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-heading text-base">Brand Health</h2>
                <p class="text-xs text-gray-400 mt-0.5">Complete your brand to score higher.</p>
            </div>
            <div class="text-right">
                <span class="text-3xl font-bold"
                      :class="{
                          'text-red-500':    {{ $healthScore }} < 40,
                          'text-amber-500':  {{ $healthScore }} >= 40 && {{ $healthScore }} < 70,
                          'text-green-500':  {{ $healthScore }} >= 70
                      }">{{ $healthScore }}</span>
                <span class="text-gray-400 text-sm">/100</span>
            </div>
        </div>

        <div class="mt-3 h-2 bg-gray-100 rounded-full overflow-hidden">
            <div class="h-full rounded-full transition-all duration-500"
                 style="width: {{ $healthScore }}%"
                 :class="{
                     'bg-red-400':    {{ $healthScore }} < 40,
                     'bg-amber-400':  {{ $healthScore }} >= 40 && {{ $healthScore }} < 70,
                     'bg-green-500':  {{ $healthScore }} >= 70
                 }"></div>
        </div>

        <ul class="mt-4 space-y-1.5 text-xs text-gray-500">
            <li class="flex items-center gap-2">
                <span :class="logoUrl ? 'text-green-500' : 'text-gray-300'">
                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </span>
                Logo uploaded (+30 pts)
            </li>
            <li class="flex items-center gap-2">
                <span class="{{ ($profile?->accent_color && $profile->accent_color !== '#FF5733') ? 'text-green-500' : 'text-gray-300' }}">
                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </span>
                Accent color customized (+25 pts)
            </li>
            <li class="flex items-center gap-2">
                <span class="{{ ($profile?->sidebar_color && $profile->sidebar_color !== '#2D2B6E') ? 'text-green-500' : 'text-gray-300' }}">
                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </span>
                Sidebar color customized (+20 pts)
            </li>
            <li class="flex items-center gap-2">
                <span class="{{ $tenant->program_name ? 'text-green-500' : 'text-gray-300' }}">
                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </span>
                Program name set (+15 pts)
            </li>
            <li class="flex items-center gap-2">
                <span class="{{ $tenant->business_name ? 'text-green-500' : 'text-gray-300' }}">
                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </span>
                Business name set (+10 pts)
            </li>
        </ul>
    </div>

    {{-- ── Logo ────────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="font-semibold text-heading text-base mb-4">Logo</h2>

        <div class="flex items-start gap-6">
            {{-- Preview --}}
            <div class="shrink-0 w-24 h-24 rounded-xl border-2 border-dashed border-gray-200 flex items-center justify-center bg-gray-50 overflow-hidden">
                <template x-if="logoUrl">
                    <img :src="logoUrl" alt="Brand logo" class="max-w-full max-h-full object-contain p-1">
                </template>
                <template x-if="!logoUrl">
                    <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </template>
            </div>

            <div class="flex-1 space-y-3">
                <p class="text-sm text-gray-500">PNG, JPG, or WebP. Max 2 MB. Recommended: 400×400 px or larger square.</p>

                @if($canEdit)
                <div class="flex items-center gap-2">
                    <label class="btn-secondary text-sm cursor-pointer">
                        <span x-show="!uploadingLogo">Choose File</span>
                        <span x-show="uploadingLogo" x-cloak>Uploading…</span>
                        <input type="file" accept=".jpg,.jpeg,.png,.webp" class="sr-only"
                               :disabled="uploadingLogo"
                               @change="uploadLogo($event)">
                    </label>
                    <button type="button" x-show="logoUrl" x-cloak
                            @click="deleteLogo()"
                            :disabled="deletingLogo"
                            class="text-sm text-red-500 hover:text-red-700 disabled:opacity-40">
                        Remove
                    </button>
                </div>
                <p x-show="logoError" x-cloak class="text-xs text-red-500" x-text="logoError"></p>
                @else
                <p class="text-xs text-gray-400 italic">Contact an owner or admin to update the logo.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Colors ──────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="font-semibold text-heading text-base mb-4">Colors</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">

            {{-- Accent Color --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Accent Color</label>
                <p class="text-xs text-gray-400 mb-3">Used for buttons, links, and active states.</p>
                <div class="flex items-center gap-3">
                    <input type="color"
                           x-model="accentColor"
                           @change="markDirty()"
                           @if(!$canEdit) disabled @endif
                           class="h-10 w-16 rounded-lg border border-gray-200 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed p-0.5">
                    <input type="text" x-model="accentColor"
                           @input="markDirty()"
                           @if(!$canEdit) disabled @endif
                           maxlength="7"
                           placeholder="#FF5733"
                           class="w-28 text-sm border border-gray-200 rounded-lg px-3 py-2 font-mono focus:ring-2 focus:ring-brand/30 focus:border-brand disabled:bg-gray-50 disabled:opacity-60">
                </div>
                {{-- WCAG warning --}}
                <p x-show="!accentPassesWcag()" x-cloak
                   class="mt-2 text-xs text-amber-600 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.485 3.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 3.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                    </svg>
                    Low contrast on white — text may be hard to read (WCAG AA).
                </p>
            </div>

            {{-- Sidebar Color --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sidebar / Nav Color</label>
                <p class="text-xs text-gray-400 mb-3">Background color of the left navigation panel.</p>
                <div class="flex items-center gap-3">
                    <input type="color"
                           x-model="sidebarColor"
                           @change="markDirty()"
                           @if(!$canEdit) disabled @endif
                           class="h-10 w-16 rounded-lg border border-gray-200 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed p-0.5">
                    <input type="text" x-model="sidebarColor"
                           @input="markDirty()"
                           @if(!$canEdit) disabled @endif
                           maxlength="7"
                           placeholder="#2D2B6E"
                           class="w-28 text-sm border border-gray-200 rounded-lg px-3 py-2 font-mono focus:ring-2 focus:ring-brand/30 focus:border-brand disabled:bg-gray-50 disabled:opacity-60">
                </div>
            </div>
        </div>

        {{-- Color Preview Strip --}}
        <div class="mt-5 rounded-lg overflow-hidden border border-gray-100 flex h-10">
            <div class="flex-1 flex items-center justify-center text-white text-xs font-medium transition-colors duration-200"
                 :style="'background-color:' + sidebarColor">Sidebar</div>
            <div class="flex-1 flex items-center justify-center text-white text-xs font-medium transition-colors duration-200"
                 :style="'background-color:' + accentColor">Accent</div>
            <div class="flex-1 flex items-center justify-center text-xs text-gray-400 bg-[#F0EFFA]">
                Page bg
            </div>
        </div>
    </div>

    {{-- ── Live Preview ────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="font-semibold text-heading text-base mb-4">Live Preview</h2>
        <div class="rounded-xl overflow-hidden border border-gray-100 flex h-40 shadow-sm">
            {{-- Mini sidebar --}}
            <div class="w-36 flex flex-col shrink-0 transition-colors duration-200"
                 :style="'background:' + sidebarColor">
                <div class="flex items-center gap-2 px-3 py-3 border-b border-white/10">
                    <template x-if="logoUrl">
                        <img :src="logoUrl" alt="Logo" class="w-6 h-6 rounded object-contain bg-white/10 p-0.5">
                    </template>
                    <template x-if="!logoUrl">
                        <div class="w-6 h-6 rounded bg-white/20 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 text-white/70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01"/>
                            </svg>
                        </div>
                    </template>
                    <span class="text-white text-[10px] font-semibold leading-none truncate opacity-90">Your Portal</span>
                </div>
                <div class="px-2 py-2 space-y-1">
                    <div class="h-1.5 rounded-full bg-white/30 w-3/4"></div>
                    <div class="h-1.5 rounded-full bg-white/20 w-1/2"></div>
                    <div class="h-1.5 rounded-full bg-white/20 w-2/3"></div>
                </div>
            </div>
            {{-- Mini content area --}}
            <div class="flex-1 bg-[#F0EFFA] p-4 flex flex-col gap-3">
                <div class="flex items-center gap-2">
                    <div class="h-3 bg-gray-300 rounded w-24"></div>
                    <div class="ml-auto h-6 rounded-lg px-3 flex items-center text-[9px] font-semibold text-white transition-colors duration-200"
                         :style="'background:' + accentColor">Button</div>
                </div>
                <div class="space-y-1.5">
                    <div class="h-2 bg-white rounded-md w-full"></div>
                    <div class="h-2 bg-white rounded-md w-5/6"></div>
                    <div class="h-2 bg-white rounded-md w-3/4"></div>
                </div>
                <div class="flex items-center gap-1.5 mt-auto">
                    <div class="h-4 rounded px-2 flex items-center text-[8px] font-medium text-white transition-colors duration-200"
                         :style="'background:' + accentColor">Link</div>
                    <div class="h-4 bg-gray-200 rounded px-2 flex items-center text-[8px] text-gray-500">Cancel</div>
                </div>
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-2">Preview updates as you change colors and upload a logo.</p>
    </div>

    {{-- ── Version History ─────────────────────────────────────────────────── --}}
    @if($canEdit)
    <div class="bg-white rounded-xl border border-gray-200 p-5"
         x-init="loadVersions()">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-heading text-base">Version History</h2>
            <button type="button" @click="loadVersions()" :disabled="loadingVersions"
                    class="text-xs text-gray-400 hover:text-gray-600 disabled:opacity-40">
                <svg class="w-4 h-4 inline" :class="loadingVersions ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Refresh
            </button>
        </div>

        <template x-if="!loadingVersions && brandVersions.length === 0">
            <p class="text-sm text-gray-400 italic">No versions yet — publish your brand to create a snapshot.</p>
        </template>

        <template x-if="loadingVersions">
            <div class="space-y-2">
                <div class="h-10 bg-gray-50 rounded-lg animate-pulse"></div>
                <div class="h-10 bg-gray-50 rounded-lg animate-pulse"></div>
            </div>
        </template>

        <template x-if="!loadingVersions && brandVersions.length > 0">
            <div class="divide-y divide-gray-100">
                <template x-for="v in brandVersions" :key="v.id">
                    <div class="flex items-center gap-3 py-3">
                        {{-- Color swatches --}}
                        <div class="flex items-center gap-1 shrink-0">
                            <template x-if="v.sidebar_color">
                                <span class="w-4 h-4 rounded-full ring-1 ring-black/10 inline-block"
                                      :style="'background:' + v.sidebar_color"
                                      :title="'Sidebar: ' + v.sidebar_color"></span>
                            </template>
                            <template x-if="v.accent_color">
                                <span class="w-4 h-4 rounded-full ring-1 ring-black/10 inline-block"
                                      :style="'background:' + v.accent_color"
                                      :title="'Accent: ' + v.accent_color"></span>
                            </template>
                            <template x-if="v.logo_url">
                                <img :src="v.logo_url" alt="Logo" class="w-4 h-4 rounded object-contain ring-1 ring-black/10">
                            </template>
                        </div>
                        {{-- Score --}}
                        <span class="text-xs font-bold w-9 shrink-0 text-right"
                              :style="'color:' + (v.health_score >= 70 ? '#22c55e' : v.health_score >= 40 ? '#f59e0b' : '#ef4444')"
                              x-text="v.health_score + '%'"></span>
                        {{-- Date --}}
                        <span class="text-xs text-gray-400 flex-1 truncate"
                              x-text="new Date(v.created_at).toLocaleString()"></span>
                        {{-- Restore --}}
                        <button type="button"
                                @click="restoreVersion(v.id)"
                                :disabled="restoringVersion === v.id"
                                class="text-xs text-brand hover:underline disabled:opacity-40 shrink-0">
                            <span x-show="restoringVersion !== v.id">Restore</span>
                            <span x-show="restoringVersion === v.id" x-cloak>Restoring…</span>
                        </button>
                    </div>
                </template>
            </div>
        </template>
    </div>
    @endif

    {{-- ── Status Strip ────────────────────────────────────────────────────── --}}
    <div class="text-xs text-gray-400 flex items-center justify-between">
        <span>
            Status:
            <span class="font-medium"
                  :class="status === 'published' ? 'text-green-600' : 'text-amber-600'"
                  x-text="status === 'published' ? 'Published' : 'Draft'"></span>
            <template x-if="publishedAt">
                <span x-text="' — last published ' + new Date(publishedAt).toLocaleDateString()"></span>
            </template>
        </span>
        @if($canEdit && $profile?->status === 'published')
        <button type="button"
                @click="showRevertModal = true"
                class="text-xs text-gray-400 hover:text-red-500 underline">
            Revert draft to published
        </button>
        @endif
    </div>

    {{-- ── Publish Confirmation Modal ───────────────────────────────────────── --}}
    <div x-show="showPublishModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div @click.away="showPublishModal = false"
             class="bg-white rounded-xl shadow-xl max-w-sm w-full p-6 space-y-4">
            <h3 class="font-semibold text-heading text-lg">Publish Brand?</h3>
            <p class="text-sm text-gray-500">
                This will make your brand changes live across all portals immediately.
            </p>
            <div class="flex gap-3 justify-end">
                <button type="button" @click="showPublishModal = false"
                        class="btn-secondary text-sm">Cancel</button>
                <button type="button" @click="publishBrand()"
                        :disabled="publishing"
                        class="btn-primary text-sm disabled:opacity-40">
                    <span x-show="!publishing">Publish Now</span>
                    <span x-show="publishing" x-cloak>Publishing…</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ── Revert Confirmation Modal ────────────────────────────────────────── --}}
    <div x-show="showRevertModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div @click.away="showRevertModal = false"
             class="bg-white rounded-xl shadow-xl max-w-sm w-full p-6 space-y-4">
            <h3 class="font-semibold text-heading text-lg">Revert Draft?</h3>
            <p class="text-sm text-gray-500">
                This will discard any unsaved draft changes and reset to your last published brand.
            </p>
            <div class="flex gap-3 justify-end">
                <button type="button" @click="showRevertModal = false"
                        class="btn-secondary text-sm">Cancel</button>
                <button type="button" @click="revertDraft()"
                        class="bg-red-600 hover:bg-red-700 text-white text-sm px-4 py-2 rounded-lg font-medium">
                    Revert
                </button>
            </div>
        </div>
    </div>

</div>

<script>
function brandStudio(config) {
    return {
        // State
        accentColor:      config.accentColor,
        sidebarColor:     config.sidebarColor,
        logoUrl:          config.logoUrl,
        status:           config.status,
        publishedAt:      config.publishedAt,
        canEdit:          config.canEdit,
        isDirty:          false,

        // UI state
        saving:           false,
        publishing:       false,
        uploadingLogo:    false,
        deletingLogo:     false,
        showPublishModal: false,
        showRevertModal:  false,
        logoError:        null,
        toast:            { show: false, message: '', type: 'success' },

        // Version history
        brandVersions:    [],
        loadingVersions:  false,
        restoringVersion: null,

        // Helpers
        markDirty() { this.isDirty = true; },

        accentPassesWcag() {
            const hex = this.accentColor.replace('#', '');
            if (hex.length !== 6) return true;
            const r = parseInt(hex.slice(0,2), 16) / 255;
            const g = parseInt(hex.slice(2,4), 16) / 255;
            const b = parseInt(hex.slice(4,6), 16) / 255;
            const lin = c => c <= 0.04045 ? c/12.92 : Math.pow((c+0.055)/1.055, 2.4);
            const L = 0.2126*lin(r) + 0.7152*lin(g) + 0.0722*lin(b);
            return 1.05 / (L + 0.05) >= 4.5;
        },

        showToast(message, type = 'success') {
            this.toast = { show: true, message, type };
            setTimeout(() => { this.toast.show = false; }, 3500);
        },

        async post(url, body) {
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken,
                },
                body: JSON.stringify(body),
            });
            return res;
        },

        // Actions
        async saveDraft() {
            if (!this.isDirty || this.saving) return;
            this.saving = true;
            try {
                const res = await this.post(config.saveDraftUrl, {
                    accent_color:  this.accentColor,
                    sidebar_color: this.sidebarColor,
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.error ?? 'Save failed.');
                this.isDirty = false;
                this.status  = data.status;
                this.showToast('Draft saved.');
            } catch (e) {
                this.showToast(e.message, 'error');
            } finally {
                this.saving = false;
            }
        },

        async publishBrand() {
            this.publishing = true;
            try {
                // Auto-save draft first if dirty
                if (this.isDirty) {
                    const dr = await this.post(config.saveDraftUrl, {
                        accent_color:  this.accentColor,
                        sidebar_color: this.sidebarColor,
                    });
                    if (!dr.ok) throw new Error('Could not save draft before publishing.');
                    this.isDirty = false;
                }

                const res  = await this.post(config.publishUrl, {});
                const data = await res.json();
                if (!res.ok) throw new Error(data.error ?? 'Publish failed.');

                this.status      = 'published';
                this.publishedAt = data.published_at;
                this.showPublishModal = false;
                this.showToast('Brand published successfully!');

                // Reload so CSS vars and sidebar logo update
                setTimeout(() => window.location.reload(), 800);
            } catch (e) {
                this.showToast(e.message, 'error');
            } finally {
                this.publishing = false;
            }
        },

        async uploadLogo(event) {
            const file = event.target.files?.[0];
            if (!file) return;
            this.logoError     = null;
            this.uploadingLogo = true;

            const formData = new FormData();
            formData.append('logo', file);
            formData.append('_token', config.csrfToken);

            try {
                const res  = await fetch(config.uploadLogoUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' },
                    body: formData,
                });
                const data = await res.json();
                if (!res.ok) {
                    this.logoError = data.error ?? 'Upload failed.';
                    return;
                }
                this.logoUrl = data.logo_url;
                this.showToast('Logo uploaded. Publish to make it live.');
            } catch (e) {
                this.logoError = 'Upload failed — please try again.';
            } finally {
                this.uploadingLogo = false;
                event.target.value = '';
            }
        },

        async deleteLogo() {
            if (!confirm('Remove logo from draft?')) return;
            this.deletingLogo = true;
            try {
                const res  = await fetch(config.deleteLogoUrl, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.error ?? 'Delete failed.');
                this.logoUrl = null;
                this.showToast('Logo removed from draft.');
            } catch (e) {
                this.showToast(e.message, 'error');
            } finally {
                this.deletingLogo = false;
            }
        },

        async loadVersions() {
            this.loadingVersions = true;
            try {
                const res = await fetch(config.versionsUrl, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                this.brandVersions = await res.json();
            } catch {}
            finally { this.loadingVersions = false; }
        },

        async restoreVersion(id) {
            if (!confirm('Restore this version as a draft?')) return;
            this.restoringVersion = id;
            try {
                const url = config.restoreVersionBaseUrl.replace('__ID__', id);
                const res = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.error ?? 'Restore failed.');
                this.showToast('Version restored as draft.');
                setTimeout(() => window.location.reload(), 800);
            } catch (e) {
                this.showToast(e.message, 'error');
            } finally { this.restoringVersion = null; }
        },

        async revertDraft() {
            try {
                const res  = await this.post(config.revertUrl, {});
                const data = await res.json();
                if (!res.ok) throw new Error(data.error ?? 'Revert failed.');
                this.showRevertModal = false;
                this.showToast('Draft reverted to published brand.');
                setTimeout(() => window.location.reload(), 800);
            } catch (e) {
                this.showToast(e.message, 'error');
            }
        },
    };
}
</script>
@endsection
