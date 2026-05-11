<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Review Agreements — {{ $tenant->name ?? 'ReferralBunny.ai' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { background: #F7F6FD; }
        .agreement-text { font-family: 'Courier New', Courier, monospace; font-size: 0.78rem; line-height: 1.6; }
    </style>
</head>
<body class="min-h-full flex flex-col" x-data="acceptanceFlow()" x-init="init()">

    {{-- Header bar --}}
    <div class="sticky top-0 z-30 bg-white border-b border-gray-100 shadow-sm">
        <div class="max-w-2xl mx-auto px-4 py-3 flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white text-sm font-bold shrink-0"
                 style="background-color: {{ $tenant->accent_color ?? '#7B61FF' }}">
                {{ strtoupper(substr($tenant->name ?? 'R', 0, 2)) }}
            </div>
            <div>
                <p class="font-semibold text-[#1E1B4B] text-sm leading-tight">{{ $tenant->name }}</p>
                <p class="text-xs text-gray-400">Legal Agreements</p>
            </div>
            <div class="ml-auto text-xs text-gray-400">
                <span x-text="agreedIds.length"></span> of {{ count($agreements) }} accepted
            </div>
        </div>
    </div>

    <div class="flex-1 max-w-2xl w-full mx-auto px-4 py-8 space-y-6">

        {{-- Intro --}}
        <div class="text-center">
            <div class="w-14 h-14 rounded-2xl bg-[#EDE9FE] flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-[#7B61FF]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <h1 class="text-xl font-bold text-[#1E1B4B]">Review & Accept Agreements</h1>
            <p class="text-gray-500 text-sm mt-1.5 max-w-md mx-auto">
                Before accessing <strong>{{ $tenant->name }}</strong>, please read and accept
                {{ count($agreements) === 1 ? 'the following agreement' : 'each of the following agreements' }}.
                Scroll to the bottom of each document to enable the acceptance button.
            </p>
        </div>

        {{-- Progress bar --}}
        @if(count($agreements) > 1)
        <div class="flex items-center gap-2 px-4 py-3 rounded-xl bg-white border border-gray-100 shadow-sm">
            <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-2 bg-[#7B61FF] rounded-full transition-all duration-300"
                     :style="'width: ' + ((agreedIds.length / totalCount) * 100) + '%'"></div>
            </div>
            <span class="text-xs text-gray-500 shrink-0 tabular-nums">
                <span x-text="agreedIds.length"></span>/{{ count($agreements) }} accepted
            </span>
        </div>
        @endif

        {{-- Individual agreements --}}
        @foreach($agreements as $i => $agreement)
        @php
            $typeLabels = ['nda' => 'NDA', 'non_compete' => 'Non-Compete', 'confidentiality' => 'Confidentiality Agreement', 'custom' => 'Agreement'];
            $typeColors = ['nda' => 'bg-purple-100 text-purple-700', 'non_compete' => 'bg-red-100 text-red-700', 'confidentiality' => 'bg-blue-100 text-blue-700', 'custom' => 'bg-gray-100 text-gray-600'];
            $typeLabel  = $typeLabels[$agreement->type] ?? 'Agreement';
            $typeColor  = $typeColors[$agreement->type] ?? 'bg-gray-100 text-gray-600';
        @endphp
        <div class="bg-white rounded-2xl border shadow-sm overflow-hidden"
             x-data="{ scrolled: false, agreed: false }"
             :class="agreed ? 'border-emerald-300' : 'border-gray-200'">

            {{-- Card header --}}
            <div class="px-5 py-4 border-b flex items-start justify-between gap-3"
                 :class="agreed ? 'bg-emerald-50 border-emerald-100' : 'bg-gray-50 border-gray-100'">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap mb-1">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $typeColor }}">{{ $typeLabel }}</span>
                        @if($agreement->version)
                        <span class="text-xs text-gray-400">v{{ $agreement->version }}</span>
                        @endif
                        @if($agreement->effective_date)
                        <span class="text-xs text-gray-400">Effective {{ $agreement->effective_date->format('M j, Y') }}</span>
                        @endif
                        <span class="text-xs font-medium {{ $agreement->is_required ? 'text-red-500' : 'text-gray-400' }}">
                            {{ $agreement->is_required ? '• Required' : '• Optional' }}
                        </span>
                    </div>
                    <p class="font-bold text-sm mt-1" style="color:#1E1B4B; font-size:0.9rem; line-height:1.4">{{ $agreement->title }}</p>
                </div>
                <div x-show="agreed" class="shrink-0">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-emerald-100 text-emerald-700 text-xs font-semibold">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Accepted
                    </span>
                </div>
            </div>

            {{-- Scrollable legal text --}}
            <div class="px-5 pt-4 pb-2">
                <div class="relative">
                    <div class="h-56 overflow-y-auto border border-gray-200 rounded-xl p-4 bg-gray-50 agreement-text text-gray-700 whitespace-pre-wrap"
                         @scroll="if ($el.scrollTop + $el.clientHeight >= $el.scrollHeight - 20) { scrolled = true }">{{ $agreement->content }}</div>
                    {{-- Fade overlay to signal there's more to scroll --}}
                    <div x-show="!scrolled" x-cloak
                         class="absolute bottom-0 left-0 right-0 h-16 rounded-b-xl pointer-events-none"
                         style="background: linear-gradient(to bottom, transparent, rgba(249,250,251,0.95))"></div>
                </div>
                <p x-show="!scrolled" class="text-center text-xs text-gray-400 mt-2">
                    <svg class="w-3 h-3 inline-block animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    Scroll to the bottom to enable the accept button
                </p>
            </div>

            {{-- Accept action --}}
            <div class="px-5 py-4 flex items-center justify-between gap-4">
                <p x-show="agreed" class="text-sm text-emerald-700 font-medium flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    You have agreed to this document.
                </p>
                <div x-show="!agreed" class="flex items-center gap-3 flex-wrap w-full">
                    <label class="flex items-center gap-2 flex-1 cursor-pointer select-none" :class="!scrolled ? 'opacity-40 cursor-not-allowed' : ''">
                        <input type="checkbox" :disabled="!scrolled" @change="if ($event.target.checked && scrolled) { agreed = true; $dispatch('agreement-accepted', { id: '{{ $agreement->id }}' }) } else { $event.target.checked = false }" class="w-4 h-4 rounded accent-[#7B61FF]">
                        <span class="text-sm text-gray-700">I have read and agree to the <strong>{{ $agreement->title }}</strong></span>
                    </label>
                </div>
            </div>
        </div>
        @endforeach

        {{-- Hidden form for batch submission --}}
        <form method="POST" action="{{ route('tenant.legal-agreements.store-accept', $tenantId) }}" id="acceptance-form">
            @csrf
            <input type="hidden" name="user_type" value="{{ $userType }}">
            <input type="hidden" name="user_id"   value="{{ $userId }}">
            <input type="hidden" name="user_role"  value="{{ $role }}">
            @foreach($agreements as $agreement)
            <input type="hidden" name="agreement_ids[]" value="{{ $agreement->id }}">
            @endforeach
        </form>

        {{-- Continue button --}}
        <div class="sticky bottom-0 pb-4 pt-2">
            <div class="bg-white rounded-2xl border border-gray-200 shadow-lg px-5 py-4 flex items-center gap-4">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-[#1E1B4B]" x-text="allAgreed ? 'All agreements accepted — you may continue.' : (totalCount - agreedIds.length) + ' agreement' + ((totalCount - agreedIds.length) === 1 ? '' : 's') + ' remaining'"></p>
                    <p x-show="!allAgreed" class="text-xs text-gray-400 mt-0.5">Please read and accept all agreements to continue.</p>
                </div>
                <button @click.prevent="submitAll()"
                        :disabled="!allAgreed || submitting"
                        class="shrink-0 inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-sm bg-[#7B61FF] text-white hover:bg-[#6B51EF] disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                    <svg x-show="submitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="submitting ? 'Saving…' : 'Continue to Dashboard'"></span>
                </button>
            </div>
        </div>

    </div>

<script>
function acceptanceFlow() {
    return {
        agreedIds:  [],
        totalCount: {{ count($agreements) }},
        submitting: false,

        get allAgreed() { return this.agreedIds.length >= this.totalCount; },

        init() {
            window.addEventListener('agreement-accepted', (e) => {
                if (!this.agreedIds.includes(e.detail.id)) {
                    this.agreedIds.push(e.detail.id);
                }
            });
        },

        submitAll() {
            if (!this.allAgreed || this.submitting) return;
            this.submitting = true;
            document.getElementById('acceptance-form').submit();
        },
    };
}
</script>
</body>
</html>
