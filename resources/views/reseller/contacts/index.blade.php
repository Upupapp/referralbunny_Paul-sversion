@extends('layouts.reseller')
@section('title', 'My Contacts')

@section('nav')
    @include('reseller._nav')
@endsection

@section('content')
<div class="space-y-5">

    {{-- Header --}}
    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 bg-teal-100">
                        <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-teal-100 text-teal-700">Private</span>
                </div>
                <h1 class="text-[#1E1B4B] font-bold text-xl">My Contacts</h1>
                <p class="text-gray-400 text-sm mt-0.5">Your private contact list — only you and the platform admin can see these.</p>
            </div>
            <a href="{{ route('reseller.contacts.imports', $tenant->id) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white transition-colors bg-teal-600 hover:bg-teal-700 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Import Contacts
            </a>
        </div>
    </div>

    {{-- Search --}}
    <form method="GET" action="{{ route('reseller.contacts', $tenant->id) }}" class="flex items-center gap-2">
        <div class="relative flex-1 max-w-sm">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text"
                   name="q"
                   value="{{ $search }}"
                   placeholder="Search by name, email or phone…"
                   class="w-full pl-9 pr-4 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent bg-white">
        </div>
        <button type="submit" class="btn-secondary">Search</button>
        @if($search)
        <a href="{{ route('reseller.contacts', $tenant->id) }}" class="text-sm text-gray-400 hover:text-gray-600 transition-colors">Clear</a>
        @endif
    </form>

    {{-- Contacts table --}}
    <div class="card">

        @if($contacts->total() > 0)
        <div class="overflow-x-auto -mx-5 sm:mx-0">
            <table class="w-full min-w-[600px]">
                <thead>
                    <tr class="table-head">
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Type</th>
                        <th>Added</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($contacts as $contact)
                    <tr class="table-row">
                        <td>
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-full bg-teal-100 flex items-center justify-center shrink-0 text-xs font-bold text-teal-700">
                                    {{ strtoupper(substr($contact->first_name ?? $contact->full_name ?? '?', 0, 1)) }}
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-[#1E1B4B]">
                                        {{ $contact->full_name ?: trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? '')) ?: '—' }}
                                    </p>
                                    @if($contact->job_title)
                                    <p class="text-xs text-gray-400">{{ $contact->job_title }}</p>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="text-sm text-gray-600">{{ $contact->email ?: '—' }}</td>
                        <td class="text-sm text-gray-600">{{ $contact->phone ?: '—' }}</td>
                        <td>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 text-gray-500">
                                {{ ucwords(str_replace('_', ' ', $contact->contact_type ?? 'contact')) }}
                            </span>
                        </td>
                        <td class="text-xs text-gray-400 whitespace-nowrap">
                            {{ \Carbon\Carbon::parse($contact->created_at)->format('M d, Y') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($contacts->hasPages())
        <div class="mt-4 px-1">
            {{ $contacts->links() }}
        </div>
        @endif

        @elseif($search)
        <div class="flex flex-col items-center justify-center py-14 text-center">
            <svg class="w-10 h-10 text-gray-200 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <p class="text-sm font-semibold text-[#1E1B4B]">No results for "{{ $search }}"</p>
            <p class="text-xs text-gray-400 mt-1">Try a different name, email, or phone number.</p>
            <a href="{{ route('reseller.contacts', $tenant->id) }}" class="mt-3 text-sm text-teal-600 hover:underline">Clear search</a>
        </div>

        @else
        <div class="flex flex-col items-center justify-center py-16 text-center">
            <x-r-bunny variant="sleeping" size="md" :decorative="true" class="mb-5 opacity-80" />
            <h3 class="text-[#1E1B4B] font-semibold text-base">No contacts yet</h3>
            <p class="text-gray-400 text-sm mt-1 max-w-xs">Import a contacts file to build your private contact list.</p>
            <a href="{{ route('reseller.contacts.imports', $tenant->id) }}"
               class="mt-5 inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white transition-colors bg-teal-600 hover:bg-teal-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Import Your First Contacts
            </a>
        </div>
        @endif

    </div>

</div>
@endsection
