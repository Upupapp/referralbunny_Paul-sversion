@extends('layouts.app')
@section('title', 'Imports & Exports')

@section('nav')
    @include('tenant._nav')
@endsection

@section('topbar-actions')
    <a href="{{ route('platform.import') }}" class="btn-secondary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
        </svg>
        <span class="hidden sm:inline">Platform Import</span>
    </a>
@endsection

@section('content')
<div class="space-y-5">

    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-[#1E1B4B] font-bold text-lg">Imports & Exports</h2>
                <p class="text-gray-400 text-sm mt-0.5">Bulk import deals, contacts, and referrers</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="card hover:shadow-md transition-shadow cursor-pointer group">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-[#EDE9FE] flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-[#7B61FF]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-[#1E1B4B]">Import Deals</h3>
            </div>
            <p class="text-sm text-gray-400">Upload a CSV or Excel file to bulk import deals into your pipeline.</p>
            <div class="mt-4 flex items-center text-[#7B61FF] text-sm font-medium group-hover:gap-2 gap-1 transition-all">
                <span>Get started</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </div>
        </div>

        <div class="card hover:shadow-md transition-shadow cursor-pointer group">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-[#1E1B4B]">Import Contacts</h3>
            </div>
            <p class="text-sm text-gray-400">Bulk import contact information from a spreadsheet.</p>
            <div class="mt-4 flex items-center text-blue-500 text-sm font-medium group-hover:gap-2 gap-1 transition-all">
                <span>Get started</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </div>
        </div>

        <div class="card hover:shadow-md transition-shadow cursor-pointer group">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-pink-50 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-[#FF6CAB]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-[#1E1B4B]">Import Referrers</h3>
            </div>
            <p class="text-sm text-gray-400">Upload your referrer list with commission profiles and groups.</p>
            <div class="mt-4 flex items-center text-[#FF6CAB] text-sm font-medium group-hover:gap-2 gap-1 transition-all">
                <span>Get started</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </div>
        </div>
    </div>

    <div class="card flex flex-col items-center justify-center py-16 text-center">
        <div class="w-16 h-16 rounded-2xl bg-[#EDE9FE] flex items-center justify-center mb-4">
            <svg class="w-8 h-8 text-[#7B61FF]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
        </div>
        <h3 class="text-[#1E1B4B] font-semibold text-base">No import history</h3>
        <p class="text-gray-400 text-sm mt-1 max-w-xs">Your import jobs will appear here once you start importing data.</p>
        <a href="{{ route('platform.import') }}" class="btn-primary mt-5">
            Go to Platform Import Center
        </a>
    </div>

</div>
@endsection

