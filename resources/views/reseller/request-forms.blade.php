@extends('layouts.reseller')
@section('title', 'Request Forms')
@section('nav') @include('reseller._nav') @endsection

@section('content')
<div class="max-w-2xl mx-auto space-y-4">

    {{-- Header --}}
    <div>
        <h1 class="text-xl font-bold text-[#1E1B4B]">Request Forms</h1>
        <p class="text-sm text-gray-400 mt-0.5">
            Forms made available by {{ $tenant->name }}. Click any form to open it in a new tab.
        </p>
    </div>

    {{-- List --}}
    @if($forms->isEmpty())
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 flex flex-col items-center justify-center py-16 text-center px-6">
        <div class="w-14 h-14 rounded-2xl flex items-center justify-center mb-4" style="background:#EDE9FE">
            <svg class="w-7 h-7" style="color:#7B61FF" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 17h.01"/>
            </svg>
        </div>
        <p class="text-sm font-semibold text-[#1E1B4B]">No forms available yet</p>
        <p class="text-xs text-gray-400 mt-1 max-w-xs">
            {{ $tenant->name }} hasn't published any request forms yet. Check back later.
        </p>
    </div>
    @else
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="divide-y divide-gray-50">
            @foreach($forms as $form)
            <a href="{{ $form->publicUrl() }}"
               target="_blank"
               rel="noopener noreferrer"
               class="flex items-center gap-4 px-5 py-4 hover:bg-gray-50 transition-colors group">

                {{-- Icon --}}
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background:#EDE9FE">
                    <svg class="w-5 h-5" style="color:#7B61FF" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 17h.01"/>
                    </svg>
                </div>

                {{-- Text --}}
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-[#1E1B4B] group-hover:underline truncate">
                        {{ $form->title }}
                    </p>
                    @if($form->description)
                    <p class="text-xs text-gray-400 mt-0.5 line-clamp-1">{{ $form->description }}</p>
                    @endif
                </div>

                {{-- Open icon --}}
                <svg class="w-4 h-4 text-gray-300 group-hover:text-[#7B61FF] transition-colors shrink-0"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
            </a>
            @endforeach
        </div>
    </div>

    <p class="text-[10px] text-gray-400 text-center px-4">
        Each form opens in a new tab. Your responses are submitted directly to {{ $tenant->name }}.
    </p>
    @endif

</div>
@endsection
