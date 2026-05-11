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

    {{-- Anonymity notice --}}
    @if($reseller->is_anonymous)
    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 text-sm text-amber-700 flex gap-2 items-start">
        <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <p>Anonymity is enabled for your account. Your name, photo, and contact details are hidden from other referrers and partners. Authorized workspace admins can still view your identity.</p>
    </div>
    @endif

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
