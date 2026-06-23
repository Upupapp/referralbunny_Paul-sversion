@extends('layouts.partner')

@section('title', 'Programs')
@section('stitch_page', 'partner-programs-index')

@section('content')
<div class="p-6 max-w-4xl mx-auto space-y-6">

    <div>
        <h1 class="text-2xl font-bold text-heading">Programs</h1>
        <p class="text-sm text-gray-500 mt-1">Programs you are enrolled in.</p>
    </div>

    @if($memberships->isEmpty())
    <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 py-16 text-center">
        <p class="text-sm text-gray-500">You are not enrolled in any programs yet.</p>
    </div>
    @else
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach($memberships as $membership)
        @php $prog = $membership->program; @endphp
        <a href="{{ route('partner.programs.show', $prog->id) }}"
           class="block rounded-xl border border-gray-200 bg-white p-5 shadow-sm hover:shadow-md transition-all">
            <div class="flex items-center justify-between gap-2">
                <h3 class="text-base font-semibold text-heading">{{ $prog->name }}</h3>
                <span class="shrink-0 text-xs font-medium px-2 py-0.5 rounded-full
                    {{ $membership->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                    {{ ucfirst($membership->status) }}
                </span>
            </div>
            @if($prog->short_description)
            <p class="mt-2 text-sm text-gray-500 line-clamp-2">{{ $prog->short_description }}</p>
            @endif
        </a>
        @endforeach
    </div>
    @endif
</div>
@endsection
