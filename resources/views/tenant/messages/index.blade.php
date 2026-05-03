@extends('layouts.app')
@section('title', 'Messages')

@section('nav')
    @include('tenant._nav')
@endsection

@section('topbar-actions')
    <button class="btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        <span class="hidden sm:inline">New Message</span>
    </button>
@endsection

@section('content')
<div class="space-y-5">

    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-[#1E1B4B] font-bold text-lg">Messages</h2>
                <p class="text-gray-400 text-sm mt-0.5">Send messages to referrers and team members</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <select class="form-input text-sm py-2 px-3 w-auto">
                    <option value="">All Channels</option>
                    <option value="email">Email</option>
                    <option value="sms">SMS</option>
                    <option value="in_app">In-App</option>
                </select>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="card">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-[#1E1B4B]">0</p>
                    <p class="text-xs text-gray-400">Emails sent</p>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-[#1E1B4B]">0</p>
                    <p class="text-xs text-gray-400">SMS sent</p>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-50 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-[#7B61FF]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-[#1E1B4B]">0</p>
                    <p class="text-xs text-gray-400">In-app sent</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card flex flex-col items-center justify-center py-20 text-center">
        <div class="w-16 h-16 rounded-2xl bg-[#EDE9FE] flex items-center justify-center mb-4">
            <svg class="w-8 h-8 text-[#7B61FF]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
            </svg>
        </div>
        <h3 class="text-[#1E1B4B] font-semibold text-base">No messages yet</h3>
        <p class="text-gray-400 text-sm mt-1 max-w-xs">Send your first message to a referrer or team member to get started.</p>
        <button class="btn-primary mt-5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Message
        </button>
    </div>

</div>
@endsection
