@extends('layouts.app')
@section('title', 'Referral Program Setup')

@section('nav')
    @include('tenant._nav')
@endsection

@section('content')
<div class="max-w-lg mx-auto text-center py-12">
    <div class="flex justify-center mb-4">
        <x-r-bunny variant="warning" size="lg" :decorative="true" />
    </div>
    <h2 class="text-lg font-bold text-[#1E1B4B] mb-2">You don't have access to this yet</h2>
    <p class="text-sm text-gray-500 mb-6">
        Setting up the referral program is managed by your workspace's Owner or Admin.
        Ask them to give you access if you need to make changes here.
    </p>
    <a href="{{ route('tenant.dashboard', $tenantId) }}" class="btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1h3a1 1 0 001-1V10"/>
        </svg>
        Back to Dashboard
    </a>
</div>
@endsection
