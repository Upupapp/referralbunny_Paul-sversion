@extends('layouts.partner')
@section('title', 'Dashboard')
@section('nav') @include('partner._nav') @endsection

@section('content')
<div class="space-y-5">

    {{-- Welcome --}}
    <div>
        <h2 class="text-xl font-bold" style="color:#1E1B4B">
            Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }},
            {{ $partner->first_name ?? 'Partner' }}!
        </h2>
        <p class="text-sm text-gray-400 mt-0.5">{{ now()->format('l, F j, Y') }}</p>
    </div>

    {{-- 3 KPI Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-4">

        {{-- Active Deals --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-3 sm:p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-medium text-gray-500">My Deals</span>
                <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg flex items-center justify-center shrink-0"
                     style="background:#DBEAFE;color:#2563EB">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
            </div>
            <p class="text-xl sm:text-2xl font-bold" style="color:#1E1B4B">{{ $dealCount }}</p>
            <p class="text-xs text-gray-400 mt-0.5">Active associations</p>
        </div>

        {{-- Messages --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-3 sm:p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-medium text-gray-500">Messages</span>
                <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg flex items-center justify-center shrink-0"
                     style="background:#EDE9FE;color:#7B61FF">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                    </svg>
                </div>
            </div>
            <p class="text-xl sm:text-2xl font-bold" style="color:#1E1B4B">{{ $unreadCount }}</p>
            <p class="text-xs text-gray-400 mt-0.5">Unread messages</p>
        </div>

        {{-- Profile completion --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-3 sm:p-4 col-span-2 lg:col-span-1">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-medium text-gray-500">Profile</span>
                <span class="text-xs font-bold" style="color:#2563EB">{{ $completion }}%</span>
            </div>
            <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden mt-1 mb-2">
                <div class="h-full rounded-full" style="width:{{ $completion }}%;background:#2563EB"></div>
            </div>
            <a href="{{ route('partner.profile') }}" class="text-xs text-blue-600 hover:text-blue-700 font-medium">
                {{ $completion < 100 ? 'Complete your profile →' : 'View profile →' }}
            </a>
        </div>
    </div>

    {{-- Needs Attention panel --}}
    @if($unreadCount > 0 || $expiringDeals->isNotEmpty())
    <div class="bg-white rounded-2xl shadow-sm border border-amber-100">
        <div x-data="{ seen: false }" class="flex items-center justify-between px-4 py-3 border-b border-amber-100">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <p class="text-sm font-semibold text-amber-800">Needs Attention</p>
            </div>
            <button x-show="!seen"
                    @click="fetch('{{ route('partner.actions.mark-all-read') }}', { method:'POST', credentials:'same-origin', headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,'Accept':'application/json','X-Requested-With':'XMLHttpRequest'} }).then(r=>{ if(r.ok) seen=true; }).catch(()=>{})"
                    class="text-[10px] text-amber-600 hover:text-emerald-600 underline underline-offset-2 transition-colors">
                Mark all seen
            </button>
            <span x-show="seen" x-cloak class="text-[10px] text-emerald-600 font-medium">All seen ✓</span>
        </div>
        <div class="divide-y divide-gray-50 px-4">
            @if($unreadCount > 0)
            <div class="flex items-center justify-between py-3">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="w-2 h-2 rounded-full bg-blue-500 shrink-0"></span>
                    <p class="text-sm text-[#1E1B4B] font-medium truncate">
                        {{ $unreadCount }} unread {{ Str::plural('message', $unreadCount) }} waiting for you
                    </p>
                </div>
                <a href="{{ route('partner.messages') }}"
                   class="ml-3 text-xs font-semibold text-blue-600 hover:text-blue-700 whitespace-nowrap shrink-0">
                    View Messages →
                </a>
            </div>
            @endif
            @foreach($expiringDeals as $deal)
            <div class="flex items-center justify-between py-3">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="w-2 h-2 rounded-full {{ ($deal->days_left ?? 21) <= 2 ? 'bg-red-500' : 'bg-orange-400' }} shrink-0"></span>
                    <div class="min-w-0">
                        <p class="text-sm text-[#1E1B4B] font-medium truncate">{{ $deal->name }}</p>
                        <p class="text-xs text-gray-400">Expiring in {{ $deal->days_left ?? 0 }} {{ Str::plural('day', $deal->days_left ?? 0) }}</p>
                    </div>
                </div>
                <a href="{{ route('partner.deals.show', $deal->id) }}"
                   class="ml-3 text-xs font-semibold text-[#7B61FF] hover:text-purple-700 whitespace-nowrap shrink-0">
                    View Deal →
                </a>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Notice: read-only role --}}
    <div class="flex items-center gap-3 px-4 py-3 rounded-2xl border bg-blue-50 border-blue-100">
        <svg class="w-4 h-4 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-xs text-blue-700">You can view deals you've been added to and message the Referrer associated with each deal.</p>
    </div>

    {{-- Recent Deals --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
            <p class="text-sm font-semibold" style="color:#1E1B4B">My Deals</p>
            <a href="{{ route('partner.deals') }}" class="text-xs font-semibold" style="color:#2563EB">View all →</a>
        </div>

        @if($recentDeals->isEmpty())
        <div class="flex flex-col items-center justify-center py-12 text-center">
            <img src="/images/mascots/r-bunny-sleeping.webp" alt="" aria-hidden="true" class="w-14 h-14 object-contain mb-3 opacity-50">
            <p class="text-sm font-medium text-gray-500">No deals yet</p>
            <p class="text-xs text-gray-400 mt-1">Your manager will add you to deals. Check back soon.</p>
        </div>
        @else
        <div class="divide-y divide-gray-50">
            @foreach($recentDeals as $deal)
            @php
                $stageColors = ['introduction'=>'#9CA3AF','presentation'=>'#3B82F6','contract_sent'=>'#F59E0B','signed'=>'#8B5CF6','paid'=>'#10B981'];
                $sc = $stageColors[$deal->stage] ?? '#9CA3AF';
            @endphp
            <a href="{{ route('partner.deals.show', $deal->id) }}"
               class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 transition-colors block">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white text-xs font-bold shrink-0"
                     style="background:{{ $sc }}">
                    {{ strtoupper(substr($deal->name, 0, 2)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold truncate" style="color:#1E1B4B">{{ $deal->name }}</p>
                    <p class="text-xs text-gray-400 truncate mt-0.5">{{ ucfirst(str_replace('_', ' ', $deal->stage)) }}</p>
                </div>
                <div class="shrink-0">
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                          style="background:{{ $deal->status === 'active' ? '#D1FAE5' : ($deal->status === 'expiring' ? '#FEF3C7' : '#FEE2E2') }};color:{{ $deal->status === 'active' ? '#065F46' : ($deal->status === 'expiring' ? '#D97706' : '#DC2626') }}">
                        {{ $deal->status }}
                    </span>
                </div>
            </a>
            @endforeach
        </div>
        @endif
    </div>

</div>
@endsection
