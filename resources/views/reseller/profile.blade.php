@extends('layouts.reseller')
@section('title', 'My Profile')
@section('nav') @include('reseller._nav') @endsection

@section('content')
@php
    use App\Services\UserDisplayNameService;
    $photoUrl   = UserDisplayNameService::photoUrl($reseller, false);
    $initials   = UserDisplayNameService::initials($reseller, false);
    $completion = UserDisplayNameService::completionPercent($reseller);
    $tenantId   = $tenant->id;
@endphp

<div class="max-w-2xl mx-auto space-y-5">

    <div>
        <h1 class="text-xl font-bold text-[#1E1B4B]">My Profile</h1>
        <p class="text-gray-400 text-sm mt-0.5">Manage your personal information and referrer details.</p>
    </div>

    {{-- Success flash --}}
    @if(session('success'))
    <div class="flex items-center gap-3 p-4 rounded-2xl bg-teal-50 border border-teal-200 text-teal-700 text-sm font-medium">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        {{ session('success') }}
    </div>
    @endif

    {{-- Completion --}}
    @if($completion < 100)
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
        <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-semibold text-[#1E1B4B]">Profile {{ $completion }}% complete</p>
            <span class="text-xs text-gray-400">Fill in the fields below to reach 100%</span>
        </div>
        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
            <div class="h-2 rounded-full transition-all" style="width:{{ $completion }}%;background:linear-gradient(90deg,#0D9488,#14B8A6)"></div>
        </div>
    </div>
    @endif

    {{-- Anonymous toggle --}}
    <div x-data="{
            isAnon: {{ $reseller->is_anonymous ? 'true' : 'false' }},
            showModal: false,
            pendingOn: false,
            open() { this.pendingOn = !this.isAnon; this.showModal = true; }
         }"
         class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">

        <div class="flex items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-bold text-[#1E1B4B]">Anonymous Mode</h2>
                    <span x-show="isAnon"
                          class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-100 text-amber-700">ON</span>
                </div>
                <p class="text-xs text-gray-400 mt-0.5">
                    <span x-show="!isAnon">Your name and photo are visible to other referrers on this platform.</span>
                    <span x-show="isAnon">Your name and photo are hidden from other referrers. Admins can still see your identity.</span>
                </p>
            </div>
            <button @click="open()"
                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors shrink-0 focus:outline-none"
                    :class="isAnon ? 'bg-amber-400' : 'bg-gray-200'"
                    type="button">
                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                      :class="isAnon ? 'translate-x-6' : 'translate-x-1'"></span>
            </button>
        </div>

        {{-- Confirmation modal --}}
        <div x-show="showModal" x-cloak
             class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
             @click.self="showModal = false">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">

                {{-- Icon --}}
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center mx-auto"
                     :class="pendingOn ? 'bg-amber-100' : 'bg-teal-100'">
                    <svg class="w-6 h-6" :class="pendingOn ? 'text-amber-600' : 'text-teal-600'"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>

                {{-- Title --}}
                <div class="text-center">
                    <h3 class="text-base font-bold text-[#1E1B4B]"
                        x-text="pendingOn ? 'Enable Anonymous Mode?' : 'Disable Anonymous Mode?'"></h3>
                </div>

                {{-- Explanation --}}
                <div x-show="pendingOn"
                     class="rounded-xl bg-amber-50 border border-amber-200 p-4 space-y-2 text-sm text-amber-800">
                    <p class="font-semibold">What happens when you go anonymous:</p>
                    <ul class="space-y-1 text-xs">
                        <li class="flex items-start gap-2"><span class="text-amber-500 mt-0.5">•</span> Your real name is replaced with an alias (e.g. "Referrer #12") when other referrers view deals or leaderboards</li>
                        <li class="flex items-start gap-2"><span class="text-amber-500 mt-0.5">•</span> Your profile photo is hidden from other referrers</li>
                        <li class="flex items-start gap-2"><span class="text-amber-500 mt-0.5">•</span> Platform admins can always see your real identity</li>
                        <li class="flex items-start gap-2"><span class="text-amber-500 mt-0.5">•</span> Your commissions and deals are not affected</li>
                    </ul>
                </div>

                <div x-show="!pendingOn"
                     class="rounded-xl bg-teal-50 border border-teal-200 p-4 space-y-2 text-sm text-teal-800">
                    <p class="font-semibold">What happens when you become visible:</p>
                    <ul class="space-y-1 text-xs">
                        <li class="flex items-start gap-2"><span class="text-teal-500 mt-0.5">•</span> Your real name and photo will be visible to other referrers on leaderboards and shared deal views</li>
                        <li class="flex items-start gap-2"><span class="text-teal-500 mt-0.5">•</span> Partners you work with on deals will see your profile</li>
                        <li class="flex items-start gap-2"><span class="text-teal-500 mt-0.5">•</span> Your commissions and deals are not affected</li>
                    </ul>
                </div>

                {{-- Actions --}}
                <form method="POST" action="{{ route('reseller.profile.anonymous', $tenantId) }}">
                    @csrf
                    <input type="hidden" name="is_anonymous" :value="pendingOn ? '1' : '0'">
                    <div class="flex gap-3">
                        <button type="button" @click="showModal = false"
                                class="flex-1 px-4 py-2.5 rounded-xl text-sm font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200 transition-colors">
                            Cancel
                        </button>
                        <button type="submit"
                                class="flex-1 px-4 py-2.5 rounded-xl text-sm font-semibold text-white transition-colors"
                                :class="pendingOn ? 'bg-amber-500 hover:bg-amber-600' : 'bg-teal-600 hover:bg-teal-700'">
                            <span x-text="pendingOn ? 'Yes, go anonymous' : 'Yes, become visible'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Photo --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <h2 class="text-sm font-bold text-[#1E1B4B] mb-4">Profile Photo</h2>
        <div class="flex items-center gap-5">
            <div class="shrink-0">
                @if($photoUrl)
                    <img src="{{ $photoUrl }}" alt="Photo" class="w-20 h-20 rounded-full object-cover ring-2 ring-teal-200">
                @else
                    <div class="w-20 h-20 rounded-full flex items-center justify-center text-white font-bold text-xl"
                         style="background:linear-gradient(135deg,#14B8A6,#0D9488)">
                        {{ $initials }}
                    </div>
                @endif
            </div>
            <div class="flex-1">
                <p class="text-xs text-gray-500 mb-3">JPG, PNG or WebP · Max 2 MB.</p>
                @if($reseller->is_anonymous)
                    <p class="text-xs text-amber-600 mb-2">Your photo may be hidden where anonymity is enabled.</p>
                @endif
                <div class="flex gap-2 flex-wrap">
                    <form method="POST" action="{{ route('reseller.profile.photo', $tenantId) }}" enctype="multipart/form-data">
                        @csrf
                        <label class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-sm font-semibold text-white transition-all"
                               style="background:linear-gradient(135deg,#0D9488,#14B8A6)">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            Upload Photo
                            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="sr-only" onchange="this.form.submit()">
                        </label>
                    </form>
                    @if($photoUrl)
                    <form method="POST" action="{{ route('reseller.profile.photo.destroy', $tenantId) }}">
                        @csrf @method('DELETE')
                        <button class="px-3 py-2 rounded-xl text-sm font-medium border border-red-200 text-red-500 hover:bg-red-50 transition-colors">Remove</button>
                    </form>
                    @endif
                </div>
                @error('photo') <p class="text-red-500 text-xs mt-2">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- Edit form --}}
    <form method="POST" action="{{ route('reseller.profile.update', $tenantId) }}">
        @csrf
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 space-y-5">
            <h2 class="text-sm font-bold text-[#1E1B4B]">My Information</h2>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Display name *</label>
                    <input name="name" type="text" value="{{ old('name', $reseller->name) }}" required
                           class="w-full border border-gray-200 bg-gray-50 rounded-xl px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-teal-400/20 focus:border-teal-400 transition-all">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Nickname</label>
                    <input name="nickname" type="text" value="{{ old('nickname', $reseller->nickname) }}"
                           class="w-full border border-gray-200 bg-gray-50 rounded-xl px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-teal-400/20 focus:border-teal-400 transition-all"
                           placeholder="e.g. JD">
                    <p class="text-xs text-gray-400 mt-1">How R Bunny greets you.</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Phone</label>
                    <input name="phone" type="tel" value="{{ old('phone', $reseller->phone) }}"
                           class="w-full border border-gray-200 bg-gray-50 rounded-xl px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-teal-400/20 focus:border-teal-400 transition-all"
                           placeholder="+63 9XX XXX XXXX">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Organization</label>
                    <input name="organization" type="text" value="{{ old('organization', $reseller->organization) }}"
                           class="w-full border border-gray-200 bg-gray-50 rounded-xl px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-teal-400/20 focus:border-teal-400 transition-all">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Territory / Service area</label>
                    <input name="territory" type="text" value="{{ old('territory', $reseller->territory) }}"
                           class="w-full border border-gray-200 bg-gray-50 rounded-xl px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-teal-400/20 focus:border-teal-400 transition-all">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Location</label>
                    <input name="location" type="text" value="{{ old('location', $reseller->location) }}"
                           class="w-full border border-gray-200 bg-gray-50 rounded-xl px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-teal-400/20 focus:border-teal-400 transition-all"
                           placeholder="City, Country">
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5">Short bio</label>
                <textarea name="bio" rows="3" maxlength="500"
                          class="w-full border border-gray-200 bg-gray-50 rounded-xl px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-teal-400/20 focus:border-teal-400 transition-all resize-none"
                          placeholder="A short intro about yourself...">{{ old('bio', $reseller->bio) }}</textarea>
            </div>

            <div class="pt-3 border-t border-gray-100 grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Timezone</label>
                    <select name="timezone" class="w-full border border-gray-200 bg-gray-50 rounded-xl px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-teal-400/20 focus:border-teal-400 transition-all">
                        @foreach(UserDisplayNameService::timezones() as $tz)
                            <option value="{{ $tz }}" @selected(old('timezone', $reseller->timezone ?? 'Asia/Manila') === $tz)>{{ $tz }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Language</label>
                    <select name="language" class="w-full border border-gray-200 bg-gray-50 rounded-xl px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-teal-400/20 focus:border-teal-400 transition-all">
                        <option value="en" @selected(($reseller->language ?? 'en') === 'en')>English</option>
                        <option value="fil" @selected(($reseller->language ?? '') === 'fil')>Filipino</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('reseller.dashboard', $tenantId) }}"
                   class="px-4 py-2 rounded-xl text-sm font-medium border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors">
                    Cancel
                </a>
                <button type="submit"
                        class="px-4 py-2 rounded-xl text-sm font-semibold text-white transition-all hover:shadow-md"
                        style="background:linear-gradient(135deg,#0D9488,#14B8A6)">
                    Save Changes
                </button>
            </div>
        </div>
    </form>

    {{-- Stats (read-only) --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <h2 class="text-sm font-bold text-[#1E1B4B] mb-3">Referrer Stats</h2>
        <div class="grid grid-cols-3 gap-3 text-center">
            <div class="rounded-xl p-3" style="background:#F0FDFA">
                <p class="text-xl font-bold" style="color:#0D9488">{{ $reseller->assigned_leads ?? 0 }}</p>
                <p class="text-xs text-gray-500 mt-0.5">Deals</p>
            </div>
            <div class="rounded-xl p-3" style="background:#F0FDFA">
                <p class="text-xl font-bold" style="color:#0D9488">{{ number_format($reseller->closed_value ?? 0) }}</p>
                <p class="text-xs text-gray-500 mt-0.5">Closed (₱)</p>
            </div>
            <div class="rounded-xl p-3" style="background:#F0FDFA">
                <p class="text-xl font-bold" style="color:#0D9488">{{ $reseller->performance_score ?? 0 }}%</p>
                <p class="text-xs text-gray-500 mt-0.5">Score</p>
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-3">Joined: {{ $reseller->joined_date?->format('M j, Y') ?? '—' }} · {{ $tenant->name }}</p>
    </div>

</div>
@endsection
