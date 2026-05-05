@extends('layouts.app')
@section('title', 'My Profile')

@section('nav')
    @include('tenant._nav')
@endsection

@section('content')
@php
    $completion = \App\Services\UserDisplayNameService::completionPercent($user);
    $photoUrl   = $user->profile_photo_url;
    $initials   = $user->initials;
    $tenantId   = $tenant->id;
@endphp

<div class="max-w-3xl mx-auto space-y-5">

    {{-- Header --}}
    <div>
        <h1 class="text-xl font-bold text-[#1E1B4B]">My Profile</h1>
        <p class="text-gray-400 text-sm mt-0.5">Manage your personal information and preferences.</p>
    </div>

    {{-- Profile completion --}}
    @if($completion < 100)
    <div class="card flex items-center gap-4">
        <div class="flex-1">
            <div class="flex items-center justify-between mb-1.5">
                <p class="text-sm font-semibold text-[#1E1B4B]">Profile {{ $completion }}% complete</p>
                <span class="text-xs text-gray-400">Fill in more details</span>
            </div>
            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-2 bg-[#7B61FF] rounded-full transition-all" style="width:{{ $completion }}%"></div>
            </div>
        </div>
    </div>
    @endif

    {{-- Section 1: Photo --}}
    <div class="card">
        <h2 class="text-sm font-bold text-[#1E1B4B] mb-4">Profile Photo</h2>
        <div class="flex items-center gap-5">
            {{-- Avatar --}}
            <div class="relative shrink-0">
                @if($photoUrl)
                    <img src="{{ $photoUrl }}" alt="Profile photo"
                         class="w-20 h-20 rounded-full object-cover ring-2 ring-[#7B61FF]/20">
                @else
                    <div class="w-20 h-20 rounded-full bg-[#EDE9FE] flex items-center justify-center text-[#7B61FF] font-bold text-xl">
                        {{ $initials }}
                    </div>
                @endif
            </div>
            {{-- Actions --}}
            <div class="flex-1">
                <p class="text-xs text-gray-500 mb-3">Use a clear photo so your team can recognize you. JPG, PNG or WebP · Max 2 MB.</p>
                <div class="flex gap-2 flex-wrap">
                    <form method="POST" action="{{ route('tenant.profile.photo', $tenantId) }}" enctype="multipart/form-data">
                        @csrf
                        <label class="btn-primary cursor-pointer text-sm py-2 px-3 inline-flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                            </svg>
                            Upload Photo
                            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp"
                                   class="sr-only" onchange="this.form.submit()">
                        </label>
                    </form>
                    @if($photoUrl)
                    <form method="POST" action="{{ route('tenant.profile.photo.destroy', $tenantId) }}">
                        @csrf @method('DELETE')
                        <button class="btn-secondary text-sm py-2 px-3 text-red-500 border-red-200 hover:bg-red-50">Remove</button>
                    </form>
                    @endif
                </div>
                @error('photo') <p class="text-red-500 text-xs mt-2">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- Section 2: Basic Info --}}
    <form method="POST" action="{{ route('tenant.profile.update', $tenantId) }}">
        @csrf
        <div class="card space-y-5">
            <h2 class="text-sm font-bold text-[#1E1B4B]">Basic Information</h2>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">First name</label>
                    <input name="first_name" type="text" value="{{ old('first_name', $user->first_name) }}" class="form-input" placeholder="Jane">
                    @error('first_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Last name</label>
                    <input name="last_name" type="text" value="{{ old('last_name', $user->last_name) }}" class="form-input" placeholder="Dela Cruz">
                    @error('last_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="form-label">Nickname</label>
                <input name="nickname" type="text" value="{{ old('nickname', $user->nickname) }}" class="form-input" placeholder="e.g. JD or Janie">
                <p class="text-xs text-gray-400 mt-1">Used as your friendly name across ReferralBunny.ai.</p>
                @error('nickname') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Job title</label>
                    <input name="job_title" type="text" value="{{ old('job_title', $user->job_title) }}" class="form-input" placeholder="Sales Manager">
                </div>
                <div>
                    <label class="form-label">Department / Team</label>
                    <input name="department" type="text" value="{{ old('department', $user->department) }}" class="form-input" placeholder="Sales">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Organization</label>
                    <input name="organization" type="text" value="{{ old('organization', $user->organization) }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">Location</label>
                    <input name="location" type="text" value="{{ old('location', $user->location) }}" class="form-input" placeholder="Manila, Philippines">
                </div>
            </div>

            <div>
                <label class="form-label">Short bio</label>
                <textarea name="bio" rows="3" class="form-input resize-none" placeholder="A short intro about yourself..." maxlength="500">{{ old('bio', $user->bio) }}</textarea>
                <p class="text-xs text-gray-400 mt-1">Max 500 characters.</p>
            </div>

            {{-- Contact & Localization --}}
            <div class="pt-3 border-t border-gray-100">
                <h2 class="text-sm font-bold text-[#1E1B4B] mb-4">Contact & Preferences</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Phone number</label>
                        <input name="phone_number" type="tel" value="{{ old('phone_number', $user->phone_number) }}" class="form-input" placeholder="+63 9XX XXX XXXX">
                    </div>
                    <div>
                        <label class="form-label">Timezone</label>
                        <select name="timezone" class="form-input">
                            @foreach(\App\Services\UserDisplayNameService::timezones() as $tz)
                                <option value="{{ $tz }}" @selected(old('timezone', $user->timezone ?? 'Asia/Manila') === $tz)>{{ $tz }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('tenant.dashboard', $tenantId) }}" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </div>
    </form>

    {{-- Section 3: Security Links --}}
    <div class="card">
        <h2 class="text-sm font-bold text-[#1E1B4B] mb-3">Security</h2>
        <div class="space-y-2">
            <a href="{{ route('tenant.settings', $tenantId) }}" class="flex items-center justify-between p-3 rounded-xl hover:bg-gray-50 transition-colors group">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-[#1E1B4B]">Change Password</p>
                        <p class="text-xs text-gray-400">Update your account password</p>
                    </div>
                </div>
                <svg class="w-4 h-4 text-gray-400 group-hover:text-[#7B61FF]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="{{ route('tenant.notifications', $tenantId) }}" class="flex items-center justify-between p-3 rounded-xl hover:bg-gray-50 transition-colors group">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-[#1E1B4B]">Notification Preferences</p>
                        <p class="text-xs text-gray-400">Manage how you receive alerts</p>
                    </div>
                </div>
                <svg class="w-4 h-4 text-gray-400 group-hover:text-[#7B61FF]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>

</div>
@endsection
