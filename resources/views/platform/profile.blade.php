@extends('layouts.app')
@section('title', 'My Profile')
@section('nav') @include('platform._nav') @endsection

@section('content')
@php
    use App\Services\UserDisplayNameService;
    $photoUrl   = UserDisplayNameService::photoUrl($user);
    $initials   = UserDisplayNameService::initials($user);
    $completion = UserDisplayNameService::completionPercent($user);
@endphp

<div class="max-w-3xl mx-auto space-y-5">

    <div>
        <h1 class="text-xl font-bold text-[#1E1B4B]">My Profile</h1>
        <p class="text-gray-400 text-sm mt-0.5">Manage your Super Admin account details.</p>
    </div>

    {{-- Completion --}}
    @if($completion < 100)
    <div class="card flex items-center gap-4">
        <div class="flex-1">
            <div class="flex items-center justify-between mb-1.5">
                <p class="text-sm font-semibold text-[#1E1B4B]">Profile {{ $completion }}% complete</p>
            </div>
            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-2 bg-[#7B61FF] rounded-full transition-all" style="width:{{ $completion }}%"></div>
            </div>
        </div>
    </div>
    @endif

    {{-- Photo --}}
    <div class="card">
        <h2 class="text-sm font-bold text-[#1E1B4B] mb-4">Profile Photo</h2>
        <div class="flex items-center gap-5">
            <div class="shrink-0">
                @if($photoUrl)
                    <img src="{{ $photoUrl }}" alt="Photo" class="w-20 h-20 rounded-full object-cover ring-2 ring-purple-200">
                @else
                    <div class="w-20 h-20 rounded-full bg-[#EDE9FE] flex items-center justify-center text-[#7B61FF] font-bold text-xl">
                        {{ $initials }}
                    </div>
                @endif
            </div>
            <div class="flex-1">
                <p class="text-xs text-gray-500 mb-3">JPG, PNG or WebP · Max 2 MB.</p>
                <div class="flex gap-2 flex-wrap">
                    <form method="POST" action="{{ route('platform.profile.photo') }}" enctype="multipart/form-data">
                        @csrf
                        <label class="btn-primary cursor-pointer text-sm py-2 px-3 inline-flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            Upload Photo
                            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="sr-only" onchange="this.form.submit()">
                        </label>
                    </form>
                    @if($photoUrl)
                    <form method="POST" action="{{ route('platform.profile.photo.destroy') }}">
                        @csrf @method('DELETE')
                        <button class="btn-secondary text-sm py-2 px-3 text-red-500 border-red-200 hover:bg-red-50">Remove</button>
                    </form>
                    @endif
                </div>
                @error('photo') <p class="text-red-500 text-xs mt-2">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- Form --}}
    <form method="POST" action="{{ route('platform.profile.update') }}">
        @csrf
        <div class="card space-y-5">
            <h2 class="text-sm font-bold text-[#1E1B4B]">Account Information</h2>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Display name</label>
                    <input name="name" type="text" value="{{ old('name', $user->name) }}" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">Nickname</label>
                    <input name="nickname" type="text" value="{{ old('nickname', $user->nickname) }}" class="form-input" placeholder="e.g. SA or Admin">
                    <p class="text-xs text-gray-400 mt-1">Used in greetings and activity logs.</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Job title</label>
                    <input name="job_title" type="text" value="{{ old('job_title', $user->job_title) }}" class="form-input" placeholder="Super Administrator">
                </div>
                <div>
                    <label class="form-label">Department</label>
                    <input name="department" type="text" value="{{ old('department', $user->department) }}" class="form-input" placeholder="Platform Operations">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Phone number</label>
                    <input name="phone_number" type="tel" value="{{ old('phone_number', $user->phone_number) }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">Location</label>
                    <input name="location" type="text" value="{{ old('location', $user->location) }}" class="form-input" placeholder="City, Country">
                </div>
            </div>

            <div>
                <label class="form-label">Short bio</label>
                <textarea name="bio" rows="3" class="form-input resize-none" maxlength="500">{{ old('bio', $user->bio) }}</textarea>
            </div>

            <div class="pt-3 border-t border-gray-100 grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Timezone</label>
                    <select name="timezone" class="form-input">
                        @foreach(\App\Services\UserDisplayNameService::timezones() as $tz)
                            <option value="{{ $tz }}" @selected(old('timezone', $user->timezone ?? 'Asia/Manila') === $tz)>{{ $tz }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Language</label>
                    <select name="language" class="form-input">
                        <option value="en" @selected(($user->language ?? 'en') === 'en')>English</option>
                        <option value="fil" @selected(($user->language ?? '') === 'fil')>Filipino</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('platform.dashboard') }}" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </div>
    </form>

</div>
@endsection
