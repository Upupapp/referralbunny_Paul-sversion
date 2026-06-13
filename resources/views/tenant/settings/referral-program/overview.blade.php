@extends('layouts.app')
@section('title', 'Referral Program Setup')

@section('nav')
    @include('tenant._nav')
@endsection

@section('content')
<div class="space-y-5">

    @if(session('success'))
    <div class="flex items-center gap-2 px-4 py-3 rounded-xl bg-green-50 border border-green-200 text-sm text-green-700">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div class="flex items-center gap-2 px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ session('error') }}
    </div>
    @endif

    @if($protected)
    <div class="flex gap-3 p-4 rounded-xl bg-amber-50 border border-amber-200">
        <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86l-8.18 14.14A1 1 0 003 19.5h18a1 1 0 00.89-1.5L13.71 3.86a1 1 0 00-1.72 0z"/>
        </svg>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-amber-800 mb-0.5">Protected Workspace</p>
            <p class="text-xs text-amber-700 leading-relaxed">
                This workspace's pipeline stages, commission rules, and import templates are centrally managed and locked.
                You can still use this wizard for program basics, participants, documents, forms, notifications, and dashboard preferences —
                locked sections will be shown as read-only.
            </p>
        </div>
    </div>
    @endif

    {{-- Header --}}
    <div class="card flex flex-col sm:flex-row items-center gap-4 text-center sm:text-left">
        <x-r-bunny variant="rocket" size="md" :decorative="true" class="shrink-0" />
        <div class="flex-1">
            <h2 class="text-lg font-bold text-[#1E1B4B]">Referral Program Setup</h2>
            <p class="text-sm text-gray-500 mt-1">
                Set up how referrals flow through {{ $tenant->name }} — who can refer, what gets tracked, and how rewards work.
            </p>
        </div>
    </div>

    @if($draft)
        {{-- Continue draft --}}
        <div class="card">
            <div class="flex items-start justify-between gap-4 mb-4">
                <div>
                    <h3 class="font-semibold text-[#1E1B4B]">Continue your setup</h3>
                    <p class="text-xs text-gray-400 mt-0.5">You have an unfinished referral program setup.</p>
                </div>
                <span class="badge badge-purple shrink-0">{{ $health['score'] }}% complete</span>
            </div>

            <div class="w-full h-2 rounded-full bg-gray-100 overflow-hidden mb-4">
                <div class="h-full bg-[#7B61FF] rounded-full transition-all" style="width: {{ $health['score'] }}%"></div>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('tenant.settings.referral-program.wizard', $tenantId) }}" class="btn-primary">
                    Continue Setup
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </a>
                <form method="POST" action="{{ route('tenant.settings.referral-program.wizard.discard', $tenantId) }}"
                      onsubmit="return confirm('Discard this draft? All unsaved setup progress will be lost.');">
                    @csrf
                    <button type="submit" class="btn-secondary text-red-600 border-red-100 hover:bg-red-50">Discard Draft</button>
                </form>
            </div>
        </div>
    @else
        {{-- Empty state --}}
        <div class="card text-center py-10">
            <div class="flex justify-center mb-4">
                <x-r-bunny variant="helper" size="lg" :decorative="true" />
            </div>
            <h3 class="text-base font-semibold text-[#1E1B4B] mb-1">No referral program set up yet</h3>
            <p class="text-sm text-gray-500 max-w-md mx-auto mb-6">
                Answer a few quick questions and we'll recommend a referral program that fits your business —
                or start from scratch and configure everything yourself.
            </p>
            <div class="flex flex-wrap justify-center gap-3">
                <a href="{{ route('tenant.settings.referral-program.wizard', ['tenantId' => $tenantId, 'mode' => 'quick']) }}" class="btn-primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Set It Up for Me
                </a>
                <a href="{{ route('tenant.settings.referral-program.wizard', ['tenantId' => $tenantId, 'mode' => 'custom']) }}" class="btn-secondary">
                    Start from Scratch
                </a>
            </div>
        </div>
    @endif

    {{-- R Bunny tips --}}
    <div class="flex gap-3 p-4 rounded-xl bg-[#F0EFFA] border border-purple-100">
        <x-r-bunny variant="helper" size="sm" :decorative="true" class="shrink-0 mt-0.5" />
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-[#1E1B4B] mb-0.5">R Bunny's Setup Tips</p>
            <ul class="space-y-1">
                @foreach([
                    'Your progress is saved automatically as you go — pick up where you left off anytime.',
                    "Choose \"Set It Up for Me\" and we'll recommend a program type based on your industry.",
                    'Nothing changes for your team until you publish your setup.',
                ] as $tip)
                <li class="flex items-start gap-1.5 text-xs text-gray-500">
                    <svg class="w-3.5 h-3.5 text-purple-400 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    {{ $tip }}
                </li>
                @endforeach
            </ul>
        </div>
    </div>

</div>
@endsection
