@extends('layouts.partner')
@section('title', 'My Profile')
@section('nav') @include('partner._nav') @endsection

@section('content')
@php
    use App\Services\UserDisplayNameService;
    $photoUrl   = $partner->profile_photo_path ? asset('storage/' . $partner->profile_photo_path) : null;
    $firstName  = $partner->first_name ?? '';
    $lastName   = $partner->last_name ?? '';
    $initials   = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: 'P';
    $completion = isset($partner) ? UserDisplayNameService::completionPercent($partner) : 0;
@endphp

<div class="max-w-2xl mx-auto space-y-5">

    <div>
        <h1 class="text-xl font-bold text-[#1E1B4B]">My Profile</h1>
        <p class="text-gray-400 text-sm mt-0.5">Manage your personal information and contact details.</p>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
    <div class="flex items-center gap-3 px-4 py-3 rounded-2xl bg-green-50 border border-green-100">
        <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <p class="text-sm text-green-700">{{ session('success') }}</p>
    </div>
    @endif

    {{-- Completion bar --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center gap-4">
        <div class="flex-1">
            <div class="flex items-center justify-between mb-1.5">
                <p class="text-sm font-semibold text-[#1E1B4B]">Profile {{ $completion }}% complete</p>
                @if($completion < 100)
                <span class="text-xs text-blue-600">Fill in missing fields below</span>
                @else
                <span class="text-xs text-green-600 font-medium">Complete!</span>
                @endif
            </div>
            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-2 rounded-full transition-all"
                     style="width:{{ $completion }}%;background:linear-gradient(90deg,#2563EB,#3B82F6)"></div>
            </div>
        </div>
    </div>

    {{-- Photo --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <h2 class="text-sm font-bold text-[#1E1B4B] mb-4">Profile Photo</h2>
        <div class="flex items-center gap-5">
            <div class="shrink-0">
                @if($photoUrl)
                    <img src="{{ $photoUrl }}" alt="Photo" class="w-20 h-20 rounded-full object-cover ring-2 ring-blue-200">
                @else
                    <div class="w-20 h-20 rounded-full flex items-center justify-center text-white font-bold text-xl"
                         style="background:linear-gradient(135deg,#3B82F6,#2563EB)">
                        {{ $initials }}
                    </div>
                @endif
            </div>
            <div class="flex-1">
                <p class="text-xs text-gray-500 mb-3">JPG, PNG or WebP · Max 2 MB.</p>
                <div class="flex gap-2 flex-wrap">
                    <form method="POST" action="{{ route('partner.profile.photo') }}" enctype="multipart/form-data">
                        @csrf
                        <label class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-sm font-semibold text-white transition-all"
                               style="background:linear-gradient(135deg,#2563EB,#3B82F6)">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                            </svg>
                            Upload Photo
                            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="sr-only" onchange="this.form.submit()">
                        </label>
                    </form>
                    @if($photoUrl)
                    <form method="POST" action="{{ route('partner.profile.photo.destroy') }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="px-3 py-2 rounded-xl text-sm font-medium border border-red-200 text-red-500 hover:bg-red-50 transition-colors">
                            Remove
                        </button>
                    </form>
                    @endif
                </div>
                @error('photo')
                <p class="text-red-500 text-xs mt-2">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    {{-- Edit form --}}
    <form method="POST" action="{{ route('partner.profile.update') }}">
        @csrf

        {{-- My Information --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 space-y-5">
            <h2 class="text-sm font-bold text-[#1E1B4B]">My Information</h2>

            {{-- Email (read-only) --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5">Email Address</label>
                <div class="w-full border border-gray-200 bg-gray-100 rounded-xl px-3.5 py-2.5 text-sm text-gray-500 select-all">
                    {{ $partner->email }}
                </div>
                <p class="text-xs text-gray-400 mt-1">Your email cannot be changed. Contact your referrer to update it.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">First Name *</label>
                    <input name="first_name" type="text" value="{{ old('first_name', $partner->first_name) }}" required
                           class="w-full border border-gray-200 bg-gray-50 rounded-xl px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-400/20 focus:border-blue-400 transition-all"
                           placeholder="First name">
                    @error('first_name')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Last Name *</label>
                    <input name="last_name" type="text" value="{{ old('last_name', $partner->last_name) }}" required
                           class="w-full border border-gray-200 bg-gray-50 rounded-xl px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-400/20 focus:border-blue-400 transition-all"
                           placeholder="Last name">
                    @error('last_name')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Nickname</label>
                    <input name="nickname" type="text" value="{{ old('nickname', $partner->nickname) }}"
                           class="w-full border border-gray-200 bg-gray-50 rounded-xl px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-400/20 focus:border-blue-400 transition-all"
                           placeholder="e.g. JD">
                    <p class="text-xs text-gray-400 mt-1">How R Bunny greets you.</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Phone Number</label>
                    <input name="phone_number" type="tel" value="{{ old('phone_number', $partner->phone_number) }}"
                           class="w-full border border-gray-200 bg-gray-50 rounded-xl px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-400/20 focus:border-blue-400 transition-all"
                           placeholder="+63 9XX XXX XXXX">
                    @error('phone_number')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Organization</label>
                    <input name="organization" type="text" value="{{ old('organization', $partner->organization) }}"
                           class="w-full border border-gray-200 bg-gray-50 rounded-xl px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-400/20 focus:border-blue-400 transition-all"
                           placeholder="Company or organization">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Location</label>
                    <input name="location" type="text" value="{{ old('location', $partner->location) }}"
                           class="w-full border border-gray-200 bg-gray-50 rounded-xl px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-400/20 focus:border-blue-400 transition-all"
                           placeholder="City, Country">
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5">Short bio</label>
                <textarea name="bio" rows="3" maxlength="500"
                          class="w-full border border-gray-200 bg-gray-50 rounded-xl px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-400/20 focus:border-blue-400 transition-all resize-none"
                          placeholder="A short intro about yourself...">{{ old('bio', $partner->bio) }}</textarea>
            </div>
        </div>

        {{-- Contact & Preferences --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 space-y-5 mt-4">
            <h2 class="text-sm font-bold text-[#1E1B4B]">Contact & Preferences</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Timezone</label>
                    <select name="timezone"
                            class="w-full border border-gray-200 bg-gray-50 rounded-xl px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-400/20 focus:border-blue-400 transition-all">
                        @foreach(UserDisplayNameService::timezones() as $tz)
                            <option value="{{ $tz }}" @selected(old('timezone', $partner->timezone ?? 'Asia/Manila') === $tz)>{{ $tz }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Language</label>
                    <select name="language"
                            class="w-full border border-gray-200 bg-gray-50 rounded-xl px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-400/20 focus:border-blue-400 transition-all">
                        <option value="en" @selected(($partner->language ?? 'en') === 'en')>English</option>
                        <option value="fil" @selected(($partner->language ?? '') === 'fil')>Filipino</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Save --}}
        <div class="flex justify-end gap-3 mt-4">
            <a href="{{ route('partner.dashboard') }}"
               class="px-4 py-2 rounded-xl text-sm font-medium border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors">
                Cancel
            </a>
            <button type="submit"
                    class="px-4 py-2 rounded-xl text-sm font-semibold text-white transition-all hover:shadow-md"
                    style="background:#2563EB">
                Save Changes
            </button>
        </div>
    </form>

    {{-- Security / Change Password --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5" x-data="{ pwOpen: {{ $errors->has('current_password') || $errors->has('new_password') ? 'true' : 'false' }} }">
        <button type="button" @click="pwOpen = !pwOpen"
                class="flex items-center justify-between w-full text-left">
            <h2 class="text-sm font-bold text-[#1E1B4B]">Change Password</h2>
            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="pwOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>

        <div x-show="pwOpen" x-cloak class="mt-4 space-y-3">
            @if(session('password_success'))
            <div class="flex items-center gap-2 p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-700 font-medium">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                {{ session('password_success') }}
            </div>
            @endif
            <form method="POST" action="{{ route('partner.profile.password') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Current Password</label>
                    <input type="password" name="current_password"
                           class="w-full px-3 py-2 rounded-xl border text-sm focus:outline-none focus:border-purple-300 @error('current_password') border-red-400 @else border-gray-200 @enderror">
                    @error('current_password')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">New Password <span class="text-gray-400 font-normal">(min 8 chars)</span></label>
                    <input type="password" name="new_password"
                           class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-purple-300 @error('new_password') border-red-400 @enderror">
                    @error('new_password')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Confirm New Password</label>
                    <input type="password" name="new_password_confirmation"
                           class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-purple-300">
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-2 rounded-xl text-xs font-semibold text-white transition-all"
                            style="background:linear-gradient(135deg,#7B61FF,#5b4cdb)">
                        Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
