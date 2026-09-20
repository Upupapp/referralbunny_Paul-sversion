@extends('layouts.reseller')
@section('nav') @include('reseller._nav') @endsection

@section('title', 'Programs')
@section('stitch_page', 'referrer-programs-index')

@section('content')
<div class="p-6 max-w-4xl mx-auto space-y-6">

    <div>
        <h1 class="text-2xl font-bold text-heading">Programs</h1>
        <p class="text-sm text-gray-500 mt-1">View the programs you are enrolled in or open programs you can join.</p>
    </div>

    {{-- ── Enrolled programs ─────────────────────────────────────────────── --}}
    @if($memberships->isNotEmpty())
    <section>
        <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-3">Enrolled programs</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach($memberships as $membership)
            @php $prog = $membership->program; @endphp
            <a href="{{ route('reseller.programs.show', [$tenant->id, $prog->id]) }}"
               class="block rounded-xl border border-gray-200 bg-white p-5 shadow-sm hover:shadow-md transition-all">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-base font-semibold text-heading">{{ $prog->name }}</h3>
                    <span class="shrink-0 text-xs font-medium px-2 py-0.5 rounded-full
                        {{ $membership->status === 'active'  ? 'bg-green-100 text-green-700' : '' }}
                        {{ $membership->status === 'invited' ? 'bg-blue-100 text-blue-700'   : '' }}
                        {{ $membership->status !== 'active' && $membership->status !== 'invited' ? 'bg-gray-100 text-gray-600' : '' }}
                    ">{{ ucfirst($membership->status) }}</span>
                </div>
                @if($prog->short_description)
                <p class="mt-2 text-sm text-gray-500 line-clamp-2">{{ $prog->short_description }}</p>
                @endif
            </a>
            @endforeach
        </div>
    </section>
    @endif

    {{-- ── Open programs ─────────────────────────────────────────────────── --}}
    @if($openPrograms->isNotEmpty())
    <section>
        <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-3">Open Programs</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach($openPrograms as $program)
            <a href="{{ route('reseller.programs.show', [$tenant->id, $program->id]) }}"
               class="block rounded-xl border border-gray-200 bg-white p-5 shadow-sm hover:shadow-md transition-all">
                <h3 class="text-base font-semibold text-heading">{{ $program->name }}</h3>
                @if($program->short_description)
                <p class="mt-2 text-sm text-gray-500 line-clamp-2">{{ $program->short_description }}</p>
                @endif
            </a>
            @endforeach
        </div>
    </section>
    @endif

    @if($memberships->isEmpty() && $openPrograms->isEmpty())
    <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 py-16 text-center">
        <p class="text-sm text-gray-500">No programs available yet. Check back later.</p>
    </div>
    @endif
</div>
@endsection
