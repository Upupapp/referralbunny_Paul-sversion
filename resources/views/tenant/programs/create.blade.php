@extends('layouts.app')

@section('title', 'New Program')
@section('stitch_page', 'tenant-programs-create')

@section('nav')
    @include('tenant._nav', ['tenant' => $tenant])
@endsection

@section('content')
<div class="p-6 max-w-2xl mx-auto space-y-6">

    <div>
        <a href="{{ route('tenant.programs.index', $tenant->id) }}"
           class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 mb-3">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Programs
        </a>
        <h1 class="text-2xl font-bold text-heading">New Program</h1>
        <p class="text-sm text-gray-500 mt-1">Start by naming your program. You can configure everything else in the workspace.</p>
    </div>

    @if($errors->any())
    <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800 space-y-1">
        @foreach($errors->all() as $error)
        <p>{{ $error }}</p>
        @endforeach
    </div>
    @endif

    <form method="POST" action="{{ route('tenant.programs.store', $tenant->id) }}"
          x-data="{ submitting: false }" @submit="submitting = true"
          class="rounded-xl border border-gray-200 bg-white shadow-sm divide-y divide-gray-100">

        @csrf

        <div class="p-6 space-y-5">

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Program name <span class="text-red-500">*</span></label>
                <input type="text" id="name" name="name" value="{{ old('name') }}"
                       class="input w-full" placeholder="e.g. Partner Referral Program" required maxlength="120" autofocus>
                @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            @include('tenant.programs._operating-mode')

            <div>
                <label for="program_type" class="block text-sm font-medium text-gray-700 mb-1">Program type <span class="text-red-500">*</span></label>
                <select id="program_type" name="program_type" class="input w-full">
                    @foreach($types as $type)
                    <option value="{{ $type }}" {{ old('program_type', 'referral') === $type ? 'selected' : '' }}>
                        {{ ucfirst(str_replace('_', ' ', $type)) }}
                    </option>
                    @endforeach
                </select>
                @error('program_type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="short_description" class="block text-sm font-medium text-gray-700 mb-1">Short description</label>
                <textarea id="short_description" name="short_description" rows="2"
                          class="input w-full resize-none" maxlength="500"
                          placeholder="One or two sentences about this program.">{{ old('short_description') }}</textarea>
                @error('short_description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="public_visibility" class="block text-sm font-medium text-gray-700 mb-1">Visibility</label>
                <select id="public_visibility" name="public_visibility" class="input w-full">
                    <option value="private" {{ old('public_visibility', 'private') === 'private' ? 'selected' : '' }}>Private — invite only, no public page</option>
                    <option value="unlisted" {{ old('public_visibility') === 'unlisted' ? 'selected' : '' }}>Unlisted — public page, link required</option>
                    <option value="public" {{ old('public_visibility') === 'public' ? 'selected' : '' }}>Public — discoverable by anyone</option>
                </select>
            </div>

        </div>

        <div class="px-6 py-4 bg-gray-50 rounded-b-xl flex items-center justify-end gap-3">
            <a href="{{ route('tenant.programs.index', $tenant->id) }}" class="btn btn-ghost">Cancel</a>
            <button type="submit" class="btn btn-primary" :disabled="submitting" x-text="submitting ? 'Creating…' : 'Create Program'"></button>
        </div>

    </form>
</div>
@endsection
