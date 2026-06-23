@extends('layouts.app')

@section('title', 'Programs')
@section('stitch_page', 'tenant-programs-index')

@section('nav')
    @include('tenant._nav', ['tenant' => $tenant])
@endsection

@section('content')
<div class="p-6 max-w-6xl mx-auto space-y-6">

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-heading">Programs</h1>
            <p class="text-sm text-gray-500 mt-1">Manage your referral, affiliate, and partner programs.</p>
        </div>
        @can('create', \App\Models\Program::class)
        <a href="{{ route('tenant.programs.create', $tenant->id) }}"
           class="btn btn-primary">
            + New Program
        </a>
        @endcan
    </div>

    {{-- ── Flash messages ──────────────────────────────────────────────────── --}}
    @if(session('success'))
    <div class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
        {{ session('success') }}
    </div>
    @endif

    {{-- ── Programs list ───────────────────────────────────────────────────── --}}
    @if($programs->isEmpty())
    <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 py-16 text-center">
        <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
        </svg>
        <h3 class="mt-3 text-base font-semibold text-gray-700">No programs yet</h3>
        <p class="mt-1 text-sm text-gray-500">Create your first program to start enrolling referrers and partners.</p>
        @can('create', \App\Models\Program::class)
        <a href="{{ route('tenant.programs.create', $tenant->id) }}" class="mt-4 inline-block btn btn-primary">
            Create Program
        </a>
        @endcan
    </div>
    @else
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($programs as $program)
        <a href="{{ route('tenant.programs.workspace', [$tenant->id, $program->id]) }}"
           class="block rounded-xl border border-gray-200 bg-white p-5 shadow-sm hover:shadow-md hover:border-rb-blue/30 transition-all">

            <div class="flex items-start justify-between gap-2">
                <h2 class="text-base font-semibold text-heading leading-tight">{{ $program->name }}</h2>
                <span class="shrink-0 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                    {{ $program->status === 'active'    ? 'bg-green-100 text-green-700'   : '' }}
                    {{ $program->status === 'draft'     ? 'bg-gray-100 text-gray-600'     : '' }}
                    {{ $program->status === 'paused'    ? 'bg-amber-100 text-amber-700'   : '' }}
                    {{ $program->status === 'scheduled' ? 'bg-blue-100 text-blue-700'     : '' }}
                    {{ $program->status === 'ended'     ? 'bg-red-100 text-red-700'       : '' }}
                    {{ $program->status === 'archived'  ? 'bg-gray-100 text-gray-400'     : '' }}
                ">
                    {{ ucfirst($program->status) }}
                </span>
            </div>

            @if($program->short_description)
            <p class="mt-2 text-sm text-gray-500 line-clamp-2">{{ $program->short_description }}</p>
            @endif

            <div class="mt-3 flex items-center gap-3 text-xs text-gray-400">
                <span>{{ ucfirst(str_replace('_', ' ', $program->program_type)) }}</span>
                @if($program->is_default)
                <span class="inline-flex items-center gap-1">
                    <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                    Default
                </span>
                @endif
                @if($program->launched_at)
                <span class="inline-flex items-center gap-1">
                    <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                    Launched {{ $program->launched_at->diffForHumans() }}
                </span>
                @endif
            </div>
        </a>
        @endforeach
    </div>
    @endif
</div>
@endsection
