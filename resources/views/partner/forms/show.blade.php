@extends('layouts.partner')
@section('title', $form->title)
@section('nav') @include('partner._nav') @endsection

@section('content')
<div class="space-y-5 max-w-2xl" x-data="{ sub: false }" @submit.capture="sub=true">

    {{-- Breadcrumb --}}
    <div class="flex items-center gap-2 text-sm text-gray-400">
        <a href="{{ route('partner.forms') }}" class="hover:text-gray-600 transition-colors">Forms</a>
        <span>›</span>
        <span class="text-[#1E1B4B] font-medium truncate">{{ $form->title }}</span>
    </div>

    {{-- Form card --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100"
             style="background:linear-gradient(135deg,#faf5ff,#ede9fe)">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl flex items-center justify-center shrink-0"
                     style="background:linear-gradient(135deg,#7B61FF,#6d28d9)">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-base font-bold text-[#1E1B4B]">{{ $form->title }}</h1>
                    @if($form->description)
                    <p class="text-xs text-gray-500 mt-0.5">{{ $form->description }}</p>
                    @endif
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('partner.forms.submit', $form->public_token) }}" class="p-5 space-y-5">
            @csrf

            @if ($errors->any())
            <div class="flex items-start gap-2 p-3 bg-red-50 border border-red-200 rounded-xl">
                <svg class="w-4 h-4 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    @foreach ($errors->all() as $error)
                    <p class="text-xs text-red-700">{{ $error }}</p>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Dynamic fields --}}
            @foreach($form->fields as $field)
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    {{ $field->label }}
                    @if($field->is_required) <span class="text-red-400">*</span> @endif
                </label>

                @if(($field->field_type ?? 'text') === 'textarea')
                <textarea name="fields[{{ $field->id }}]"
                          rows="4"
                          {{ $field->is_required ? 'required' : '' }}
                          placeholder="{{ $field->placeholder ?? '' }}"
                          class="w-full border border-gray-200 bg-gray-50 rounded-xl px-3.5 py-2.5 text-sm text-gray-800 outline-none focus:ring-2 focus:ring-[#7B61FF]/20 focus:border-[#7B61FF] transition-all resize-none @error('fields.'.$field->id) border-red-400 @enderror">{{ old('fields.'.$field->id) }}</textarea>

                @elseif(($field->field_type ?? 'text') === 'select' && !empty($field->options))
                <select name="fields[{{ $field->id }}]"
                        {{ $field->is_required ? 'required' : '' }}
                        class="w-full border border-gray-200 bg-gray-50 rounded-xl px-3.5 py-2.5 text-sm text-gray-800 outline-none focus:ring-2 focus:ring-[#7B61FF]/20 focus:border-[#7B61FF] transition-all @error('fields.'.$field->id) border-red-400 @enderror">
                    <option value="">Choose…</option>
                    @foreach((array) $field->options as $opt)
                    <option value="{{ $opt }}" {{ old('fields.'.$field->id) === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                </select>

                @else
                <input type="{{ $field->field_type ?? 'text' }}"
                       name="fields[{{ $field->id }}]"
                       value="{{ old('fields.'.$field->id) }}"
                       {{ $field->is_required ? 'required' : '' }}
                       placeholder="{{ $field->placeholder ?? '' }}"
                       class="w-full border border-gray-200 bg-gray-50 rounded-xl px-3.5 py-2.5 text-sm text-gray-800 outline-none focus:ring-2 focus:ring-[#7B61FF]/20 focus:border-[#7B61FF] transition-all @error('fields.'.$field->id) border-red-400 @enderror">
                @endif

                @if($field->help_text ?? false)
                <p class="text-xs text-gray-400 mt-1">{{ $field->help_text }}</p>
                @endif
            </div>
            @endforeach

            {{-- Notes --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Additional Notes</label>
                <textarea name="notes" rows="3" placeholder="Any other details you'd like to share…"
                          class="w-full border border-gray-200 bg-gray-50 rounded-xl px-3.5 py-2.5 text-sm text-gray-800 outline-none focus:ring-2 focus:ring-[#7B61FF]/20 focus:border-[#7B61FF] transition-all resize-none">{{ old('notes') }}</textarea>
            </div>

            <div class="flex gap-3 pt-1">
                <button type="submit"
                        :disabled="sub"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white transition-all active:scale-95 disabled:opacity-60"
                        style="background:linear-gradient(135deg,#7B61FF,#6d28d9)">
                    <svg x-show="!sub" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                    <svg x-show="sub" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4z"/>
                    </svg>
                    <span x-text="sub ? 'Submitting…' : 'Submit Request'">Submit Request</span>
                </button>
                <a href="{{ route('partner.forms') }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors">
                    Cancel
                </a>
            </div>
        </form>
    </div>

</div>
@endsection
