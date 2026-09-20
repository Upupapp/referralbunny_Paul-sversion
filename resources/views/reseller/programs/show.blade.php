@extends('layouts.reseller')
@section('nav') @include('reseller._nav') @endsection

@section('title', $program->name)
@section('stitch_page', 'referrer-program-show')

@section('content')
<div class="p-6 max-w-3xl mx-auto space-y-6">

    <div>
        <a href="{{ route('reseller.programs.index', $tenant->id) }}"
           class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 mb-3">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Programs
        </a>
        <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-heading">{{ $program->name }}</h1>
            @if($membership)
            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium
                {{ $membership->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                {{ ucfirst($membership->status) }}
            </span>
            @endif
        </div>
    </div>

    @if($program->short_description)
    <p class="text-gray-600">{{ $program->short_description }}</p>
    @endif

    @if($program->full_description)
    <div class="rounded-xl border border-gray-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-2">About this program</h2>
        <div class="prose prose-sm text-gray-600 max-w-none">
            {!! nl2br(e($program->full_description)) !!}
        </div>
    </div>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white divide-y divide-gray-100">
        <div class="px-5 py-4">
            <h2 class="text-sm font-semibold text-gray-700">Program details</h2>
        </div>
        <dl class="divide-y divide-gray-100 text-sm">
            <div class="flex justify-between px-5 py-3">
                <dt class="text-gray-500">Type</dt>
                <dd class="font-medium text-gray-800">{{ ucfirst(str_replace('_', ' ', $program->program_type)) }}</dd>
            </div>
            <div class="flex justify-between px-5 py-3">
                <dt class="text-gray-500">Status</dt>
                <dd class="font-medium text-gray-800">{{ ucfirst($program->status) }}</dd>
            </div>
            @if($program->launched_at)
            <div class="flex justify-between px-5 py-3">
                <dt class="text-gray-500">Launched</dt>
                <dd class="font-medium text-gray-800">{{ $program->launched_at->format('M j, Y') }}</dd>
            </div>
            @endif
        </dl>
    </div>
</div>
@endsection
