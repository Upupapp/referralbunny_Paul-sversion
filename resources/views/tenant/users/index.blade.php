@extends('layouts.app')
@section('title', 'Users & Roles')

@section('nav')
    @include('tenant._nav')
@endsection

@section('topbar-actions')
    <button class="btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        <span class="hidden sm:inline">Invite User</span>
    </button>
@endsection

@section('content')
<div class="space-y-5">

    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-[#1E1B4B] font-bold text-lg">Users & Roles</h2>
                <p class="text-gray-400 text-sm mt-0.5">Manage team members and their access</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <div class="lg:col-span-2 space-y-4">
            <div class="card flex flex-col items-center justify-center py-16 text-center">
                <div class="w-16 h-16 rounded-2xl bg-[#EDE9FE] flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-[#7B61FF]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-[#1E1B4B] font-semibold text-base">No team members yet</h3>
                <p class="text-gray-400 text-sm mt-1 max-w-xs">Invite team members to collaborate on your referral program.</p>
                <button class="btn-primary mt-5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Invite User
                </button>
            </div>
        </div>

        <div class="space-y-4">
            <div class="card">
                <h3 class="font-semibold text-[#1E1B4B] text-sm mb-3">Roles</h3>
                <div class="space-y-2">
                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-[#F0EFFA]">
                        <span class="text-sm font-medium text-[#1E1B4B]">Admin</span>
                        <span class="badge badge-purple">System</span>
                    </div>
                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-[#F0EFFA]">
                        <span class="text-sm font-medium text-[#1E1B4B]">Manager</span>
                        <span class="badge badge-purple">System</span>
                    </div>
                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-[#F0EFFA]">
                        <span class="text-sm font-medium text-[#1E1B4B]">Agent</span>
                        <span class="badge badge-purple">System</span>
                    </div>
                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-[#F0EFFA]">
                        <span class="text-sm font-medium text-[#1E1B4B]">Viewer</span>
                        <span class="badge badge-purple">System</span>
                    </div>
                </div>
                <button class="btn-secondary w-full mt-3 text-sm">
                    Manage Roles
                </button>
            </div>

            <div class="card">
                <h3 class="font-semibold text-[#1E1B4B] text-sm mb-3">Pending Invites</h3>
                <p class="text-sm text-gray-400 text-center py-4">No pending invitations</p>
            </div>
        </div>

    </div>

</div>
@endsection
