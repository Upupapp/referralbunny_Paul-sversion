@extends('layouts.app')
@section('title', 'Contacts')

@section('nav')
    @include('tenant._nav')
@endsection

@section('topbar-actions')
    <button class="btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        <span class="hidden sm:inline">Add Contact</span>
    </button>
@endsection

@section('content')
<div class="space-y-5">

    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-[#1E1B4B] font-bold text-lg">Contacts</h2>
                <p class="text-gray-400 text-sm mt-0.5">Manage people associated with your deals</p>
            </div>
            <div class="flex gap-2">
                <div class="search-group flex-1 sm:w-64">
                    <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" placeholder="Search contacts…" class="search-input">
                </div>
            </div>
        </div>
    </div>

    <div class="card flex flex-col items-center justify-center py-20 text-center">
        <div class="w-16 h-16 rounded-2xl bg-[#EDE9FE] flex items-center justify-center mb-4">
            <svg class="w-8 h-8 text-[#7B61FF]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
        </div>
        <h3 class="text-[#1E1B4B] font-semibold text-base">No contacts yet</h3>
        <p class="text-gray-400 text-sm mt-1 max-w-xs">Add people associated with your deals, such as decision-makers and stakeholders.</p>
        <button class="btn-primary mt-5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add Contact
        </button>
    </div>

</div>
@endsection

