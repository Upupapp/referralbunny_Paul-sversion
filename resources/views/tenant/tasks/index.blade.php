@extends('layouts.app')
@section('title', 'Tasks & Follow-Ups')

@section('nav')
    @include('tenant._nav')
@endsection

@section('topbar-actions')
    <button class="btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        <span class="hidden sm:inline">New Task</span>
    </button>
@endsection

@section('content')
<div class="space-y-5">

    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-[#1E1B4B] font-bold text-lg">Tasks & Follow-Ups</h2>
                <p class="text-gray-400 text-sm mt-0.5">Track tasks and follow-ups for your deals</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <div class="search-group flex-1 sm:w-64">
                    <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" placeholder="Search tasks…" class="search-input">
                </div>
                <select class="form-input text-sm py-2 px-3 w-auto">
                    <option value="">All Statuses</option>
                    <option value="open">Open</option>
                    <option value="in_progress">In Progress</option>
                    <option value="done">Done</option>
                </select>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="card text-center">
            <p class="text-3xl font-bold text-[#1E1B4B]">0</p>
            <p class="text-sm text-gray-400 mt-1">Open</p>
        </div>
        <div class="card text-center">
            <p class="text-3xl font-bold text-orange-500">0</p>
            <p class="text-sm text-gray-400 mt-1">Due Today</p>
        </div>
        <div class="card text-center">
            <p class="text-3xl font-bold text-red-500">0</p>
            <p class="text-sm text-gray-400 mt-1">Overdue</p>
        </div>
    </div>

    <div class="card flex flex-col items-center justify-center py-20 text-center">
        <div class="w-16 h-16 rounded-2xl bg-[#EDE9FE] flex items-center justify-center mb-4">
            <svg class="w-8 h-8 text-[#7B61FF]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <h3 class="text-[#1E1B4B] font-semibold text-base">No tasks yet</h3>
        <p class="text-gray-400 text-sm mt-1 max-w-xs">Create tasks and follow-ups to stay on top of your pipeline activities.</p>
        <button class="btn-primary mt-5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Task
        </button>
    </div>

</div>
@endsection

