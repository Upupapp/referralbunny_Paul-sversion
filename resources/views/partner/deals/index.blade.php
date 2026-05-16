@extends('layouts.partner')
@section('title', 'My Deals')
@section('nav') @include('partner._nav') @endsection

@section('content')
<div class="space-y-5">

    <div>
        <h1 class="text-xl font-bold text-[#1E1B4B]">My Deals</h1>
        <p class="text-sm text-gray-400 mt-0.5">Deals you've been added to as a Partner. Read-only view.</p>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
    <div class="flex items-center gap-3 px-4 py-3 rounded-2xl bg-green-50 border border-green-100">
        <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <p class="text-sm text-green-700">{{ session('success') }}</p>
    </div>
    @endif

    {{-- Search/filter (server-side — works across all pages) --}}
    <form method="GET" action="{{ route('partner.deals') }}" class="space-y-3">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-3 flex flex-col sm:flex-row gap-2">
            <div class="flex items-center gap-2 flex-1 bg-gray-50 border border-gray-200 rounded-xl px-3 py-2">
                <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search deals…"
                       class="flex-1 bg-transparent text-sm outline-none text-gray-700 placeholder-gray-400"
                       aria-label="Search deals">
                @if($search)
                <a href="{{ route('partner.deals', ['stage' => $stage]) }}" class="text-gray-400 hover:text-gray-600 shrink-0" aria-label="Clear search">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </a>
                @endif
            </div>
            {{-- Stage filter pill --}}
            <div class="flex items-center gap-2 flex-wrap">
                <label class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-medium border transition-colors cursor-pointer {{ $stage ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-gray-200 bg-gray-50 text-gray-600' }}">
                    <span class="sr-only">Filter by stage</span>
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    <select name="stage" onchange="this.form.submit()" class="bg-transparent outline-none cursor-pointer">
                        <option value="">All Stages</option>
                        <option value="introduction" {{ $stage === 'introduction' ? 'selected' : '' }}>Introduction</option>
                        <option value="presentation" {{ $stage === 'presentation' ? 'selected' : '' }}>Presentation</option>
                        <option value="contract_sent" {{ $stage === 'contract_sent' ? 'selected' : '' }}>Contract Sent</option>
                        <option value="signed" {{ $stage === 'signed' ? 'selected' : '' }}>Signed</option>
                        <option value="paid" {{ $stage === 'paid' ? 'selected' : '' }}>Paid</option>
                    </select>
                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </label>
                @if($stage)
                <a href="{{ route('partner.deals', ['search' => $search]) }}"
                   class="text-xs text-blue-600 hover:text-blue-700 font-medium">Clear</a>
                @endif
            </div>
            <button type="submit" class="text-xs font-medium px-3 py-1.5 rounded-xl bg-blue-600 text-white hover:bg-blue-700 transition-colors">Search</button>
        </div>
    </form>

        {{-- Table --}}
        @if($deals->isEmpty())
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 flex flex-col items-center py-16 text-center">
            <img src="/images/mascots/r-bunny-sleeping.webp" alt="" aria-hidden="true" class="w-14 h-14 object-contain mb-3 opacity-50">
            <p class="text-sm text-gray-500 font-medium">No deals assigned yet</p>
            <p class="text-xs text-gray-400 mt-1">Your Referrer or workspace admin will add you to deals.</p>
        </div>
        @else
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/50">
                            <th scope="col" class="text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider px-5 py-3">Deal</th>
                            <th scope="col" class="text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider px-3 py-3">Stage</th>
                            <th scope="col" class="text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider px-3 py-3 hidden sm:table-cell">Value</th>
                            <th scope="col" class="text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider px-3 py-3">Status</th>
                            <th scope="col" class="text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider px-3 py-3 hidden sm:table-cell">Referrer</th>
                            <th scope="col" class="text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider px-3 py-3">Days Left</th>
                            <th scope="col" class="px-3 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($deals as $deal)
                        @php
                            $stageBadgeMap = [
                                'introduction'  => ['bg-gray-100 text-gray-600',    'Intro'],
                                'presentation'  => ['bg-blue-100 text-blue-700',    'Presentation'],
                                'contract_sent' => ['bg-amber-100 text-amber-700',  'Contract'],
                                'signed'        => ['bg-purple-100 text-purple-700','Signed'],
                                'paid'          => ['bg-green-100 text-green-700',  'Paid'],
                            ];
                            [$stageBadge, $stageLabel] = $stageBadgeMap[$deal->stage] ?? ['bg-gray-100 text-gray-600', ucfirst($deal->stage)];
                            $statusBadge = $deal->status === 'active'
                                ? 'bg-green-100 text-green-700'
                                : ($deal->status === 'expiring' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700');
                            $daysLeft  = $deal->days_left ?? null;
                            $daysBadge = $daysLeft === null || $deal->stage === 'paid'
                                ? null
                                : ($daysLeft <= 3
                                    ? 'bg-red-100 text-red-700'
                                    : ($daysLeft <= 7 ? 'bg-amber-100 text-amber-700' : 'bg-blue-50 text-blue-600'));
                            $daysLabel = $daysLeft !== null && $deal->stage !== 'paid'
                                ? ($daysLeft <= 0 ? 'Overdue' : $daysLeft . 'd')
                                : null;
                        @endphp
                        <tr class="hover:bg-gray-50/60 transition-colors">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-xs font-bold shrink-0"
                                         style="background:#2563EB">
                                        {{ strtoupper(substr($deal->name, 0, 2)) }}
                                    </div>
                                    <p class="font-medium text-[#1E1B4B] truncate text-sm max-w-[140px] sm:max-w-none">{{ $deal->name }}</p>
                                </div>
                            </td>
                            <td class="px-3 py-3.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $stageBadge }}">
                                    {{ $stageLabel }}
                                </span>
                            </td>
                            <td class="px-3 py-3.5 hidden sm:table-cell font-semibold text-[#1E1B4B] text-sm">
                                ₱{{ number_format($deal->deal_value ?? 0) }}
                            </td>
                            <td class="px-3 py-3.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $statusBadge }}">
                                    {{ ucfirst($deal->status) }}
                                </span>
                            </td>
                            <td class="px-3 py-3.5 hidden sm:table-cell text-gray-500 text-sm">
                                {{ $deal->reseller_name ?? '—' }}
                            </td>
                            <td class="px-3 py-3.5">
                                @if($daysLabel)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold {{ $daysBadge }}">
                                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    {{ $daysLabel }}
                                </span>
                                @else
                                <span class="text-xs text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-3.5">
                                <a href="{{ route('partner.deals.show', $deal->id) }}"
                                   aria-label="View deal {{ $deal->name }}"
                                   class="px-2.5 py-1.5 rounded-lg text-xs font-medium bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors whitespace-nowrap">
                                    View →
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($deals->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">
                    {{ $deals->links() }}
                </div>
            @endif
        </div>
        @endif

</div>
@endsection
