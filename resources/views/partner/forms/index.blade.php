@extends('layouts.partner')
@section('title', 'Request Forms')
@section('nav') @include('partner._nav') @endsection

@section('content')
<div class="space-y-5 max-w-3xl">

    <div>
        <h1 class="text-xl font-bold text-[#1E1B4B]">Request Forms</h1>
        <p class="text-sm text-gray-400 mt-0.5">Submit a request to your workspace team.</p>
    </div>

    @if(session('success'))
    <div class="flex items-start gap-3 px-4 py-3 bg-green-50 border border-green-200 rounded-2xl">
        <svg class="w-4 h-4 text-green-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <p class="text-sm text-green-700">{{ session('success') }}</p>
    </div>
    @endif

    @forelse($forms as $form)
    <a href="{{ route('partner.forms.show', $form->public_token) }}"
       class="block bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:border-[#7B61FF]/30 hover:shadow-md transition-all group">
        <div class="flex items-start justify-between gap-3">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1">
                    <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0"
                         style="background:linear-gradient(135deg,#ede9fe,#ddd6fe)">
                        <svg class="w-4 h-4 text-[#7B61FF]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-sm font-bold text-[#1E1B4B] group-hover:text-[#7B61FF] transition-colors">{{ $form->title }}</h3>
                </div>
                @if($form->description)
                <p class="text-xs text-gray-400 ml-10 line-clamp-2">{{ $form->description }}</p>
                @endif
            </div>
            <svg class="w-4 h-4 text-gray-300 group-hover:text-[#7B61FF] transition-colors shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </div>
    </a>
    @empty
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center">
        <div class="w-14 h-14 rounded-2xl bg-gray-50 flex items-center justify-center mx-auto mb-3">
            <svg class="w-7 h-7 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
        </div>
        <p class="text-sm font-semibold text-[#1E1B4B] mb-1">No forms available</p>
        <p class="text-xs text-gray-400">Your workspace hasn't published any request forms yet.</p>
    </div>
    @endforelse

</div>
@endsection
