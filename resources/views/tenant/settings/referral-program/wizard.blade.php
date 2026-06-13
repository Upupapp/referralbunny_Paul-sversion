@extends('layouts.app')
@section('title', 'Referral Program Setup — ' . ($stepLabels[$step] ?? ''))

@section('nav')
    @include('tenant._nav')
@endsection

@section('content')
@php
    $lastImplemented = $stepIndex === count($implementedSteps) - 1;
    $exitUrl = route('tenant.settings.referral-program.overview', $tenantId);
@endphp
<div class="space-y-5" x-data="referralWizardStep('{{ $tenantId }}', '{{ $step }}', @js($stepData))">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="text-lg font-bold text-[#1E1B4B]">Referral Program Setup</h2>
            <p class="text-sm text-gray-500 mt-0.5">Step {{ $stepIndex + 1 }} of {{ count($steps) }} — {{ $stepLabels[$step] }}</p>
        </div>
        <div class="flex items-center gap-2">
            @if($protected)
            <span class="badge badge-orange">Protected Workspace</span>
            @endif
            <button type="button" @click="save(true, '{{ $exitUrl }}')" :disabled="saving" class="btn-secondary text-sm">
                <span x-show="!saving">Save &amp; Exit</span>
                <span x-show="saving">Saving…</span>
            </button>
        </div>
    </div>

    {{-- Progress + stepper --}}
    <div class="card">
        <div class="flex items-center justify-between mb-2">
            <p class="text-xs font-medium text-gray-500">Setup progress</p>
            <p class="text-xs font-medium text-[#7B61FF]">{{ $health['score'] }}% complete</p>
        </div>
        <div class="w-full h-2 rounded-full bg-gray-100 overflow-hidden mb-4">
            <div class="h-full bg-[#7B61FF] rounded-full transition-all" style="width: {{ $health['score'] }}%"></div>
        </div>

        <div class="flex flex-wrap gap-2">
            @foreach($steps as $i => $s)
                @if(in_array($s, $implementedSteps, true))
                    <a href="{{ route('tenant.settings.referral-program.wizard', ['tenantId' => $tenantId, 'step' => $s]) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-colors
                              {{ $s === $step
                                    ? 'bg-[#7B61FF] text-white'
                                    : (in_array($s, $health['completed_steps'], true) ? 'bg-purple-50 text-[#7B61FF]' : 'bg-gray-50 text-gray-500 hover:bg-gray-100') }}">
                        @if($s !== $step && in_array($s, $health['completed_steps'], true))
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        @endif
                        {{ $i + 1 }}. {{ $stepLabels[$s] }}
                    </a>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-gray-50 text-gray-300 cursor-default" title="Coming soon">
                        {{ $i + 1 }}. {{ $stepLabels[$s] }}
                        <span class="text-[10px] bg-gray-100 text-gray-400 px-1.5 py-0.5 rounded-full">Soon</span>
                    </span>
                @endif
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 space-y-4">
            <div class="card">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-[#1E1B4B]">{{ $stepLabels[$step] }}</h3>
                    <span class="text-xs text-gray-400" x-show="saving">Saving…</span>
                    <span class="flex items-center gap-1 text-xs text-green-600" x-show="!saving && savedAt" x-cloak>
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Saved
                    </span>
                </div>

                @switch($step)
                    @case('program-basics')
                        @include('tenant.settings.referral-program._steps.program-basics')
                        @break
                    @case('industry-goal')
                        @include('tenant.settings.referral-program._steps.industry-goal')
                        @break
                    @case('program-type')
                        @include('tenant.settings.referral-program._steps.program-type')
                        @break
                    @case('participants')
                        @include('tenant.settings.referral-program._steps.participants')
                        @break
                    @case('pipeline')
                        @include('tenant.settings.referral-program._steps.pipeline')
                        @break
                    @case('fields')
                        @include('tenant.settings.referral-program._steps.fields')
                        @break
                    @case('rewards')
                        @include('tenant.settings.referral-program._steps.rewards')
                        @break
                    @case('partner-split')
                        @include('tenant.settings.referral-program._steps.partner-split')
                        @break
                @endswitch
            </div>

            @if($lastImplemented)
            <div class="flex gap-3 p-4 rounded-xl bg-[#F0EFFA] border border-purple-100">
                <x-r-bunny variant="celebration" size="sm" :decorative="true" class="shrink-0 mt-0.5" />
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-[#1E1B4B] mb-0.5">More steps are on the way</p>
                    <p class="text-xs text-gray-500 leading-relaxed">
                        Pipeline, rewards, documents, and the rest of the setup are coming soon. Everything you've entered so far is saved —
                        select "Save &amp; Exit" and we'll let you know when the next steps are ready.
                    </p>
                </div>
            </div>
            @endif

            {{-- Back / Next --}}
            <div class="flex items-center justify-between">
                <div>
                    @if($stepIndex > 0)
                    <button type="button" @click="goTo('{{ route('tenant.settings.referral-program.wizard', ['tenantId' => $tenantId, 'step' => $steps[$stepIndex - 1]]) }}')"
                            :disabled="saving" class="btn-secondary">Back</button>
                    @else
                    <a href="{{ $exitUrl }}" class="btn-secondary">Cancel</a>
                    @endif
                </div>
                <div>
                    @if(!$lastImplemented)
                    <button type="button" @click="goTo('{{ route('tenant.settings.referral-program.wizard', ['tenantId' => $tenantId, 'step' => $steps[$stepIndex + 1]]) }}')"
                            :disabled="saving" class="btn-primary">
                        <span x-show="!saving">Next</span>
                        <span x-show="saving">Saving…</span>
                    </button>
                    @else
                    <button type="button" @click="save(true, '{{ $exitUrl }}')" :disabled="saving" class="btn-primary">
                        <span x-show="!saving">Save &amp; Exit</span>
                        <span x-show="saving">Saving…</span>
                    </button>
                    @endif
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-4">
            <div class="flex gap-3 p-4 rounded-xl bg-[#F0EFFA] border border-purple-100">
                <x-r-bunny variant="helper" size="sm" :decorative="true" class="shrink-0 mt-0.5" />
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-[#1E1B4B] mb-0.5">R Bunny says</p>
                    <p class="text-xs text-gray-500 leading-relaxed">{{ $stepHelp[$step] ?? '' }}</p>
                </div>
            </div>

            @if($step === 'program-type' && ($recommendation['name'] ?? null))
            <div class="card">
                <h3 class="font-semibold text-[#1E1B4B] text-sm mb-1">Recommended template</h3>
                <p class="text-xs text-gray-400 mb-2">Based on the industry you chose.</p>
                <p class="text-sm font-medium text-[#1E1B4B]">{{ $recommendation['name'] }}</p>
                <p class="text-xs text-gray-500 mt-1 leading-relaxed">{{ $recommendation['description'] }}</p>
            </div>
            @endif

            <div class="card">
                <h3 class="font-semibold text-[#1E1B4B] text-sm mb-2">Discard Setup</h3>
                <p class="text-xs text-gray-400 mb-3">Remove this draft and start over from scratch.</p>
                <form method="POST" action="{{ route('tenant.settings.referral-program.wizard.discard', $tenantId) }}"
                      onsubmit="return confirm('Discard this draft? All unsaved setup progress will be lost.');">
                    @csrf
                    <button type="submit" class="btn-secondary text-red-600 border-red-100 hover:bg-red-50 w-full justify-center">Discard Draft</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function referralWizardStep(tenantId, step, initialData) {
    return {
        data: initialData,
        subIndustriesText: (initialData.sub_industries || []).join(', '),
        saving: false,
        savedAt: null,
        errors: {},
        _timer: null,

        init() {
            this.$watch('data', () => this.scheduleAutosave());

            @if($justStarted)
            window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'success', message: "Setup started! Answer a few questions and we'll get your referral program ready." } }));
            @endif
        },

        scheduleAutosave() {
            clearTimeout(this._timer);
            this._timer = setTimeout(() => this.save(false), 1200);
        },

        async save(explicit = true, redirectTo = null) {
            this.saving = true;

            try {
                const res = await fetch(`/tenant/${tenantId}/settings/referral-program/wizard/step/${step}`, {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ ...this.data, _explicit: explicit ? '1' : '0' }),
                });

                let json = {};
                try { json = await res.json(); } catch (e) {}

                if (!res.ok) {
                    this.errors = json.errors || {};
                    window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'error', message: json.message || 'Could not save. Please check the form.' } }));
                    return false;
                }

                this.errors = {};
                this.savedAt = new Date();

                if (explicit) {
                    window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'success', message: json.message || 'Saved.' } }));
                }

                if (redirectTo) {
                    window.location.href = redirectTo;
                    return true;
                }

                return true;
            } catch (e) {
                window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'error', message: 'Network error. Please try again.' } }));
                return false;
            } finally {
                this.saving = false;
            }
        },

        async goTo(url) {
            const ok = await this.save(true);
            if (ok) {
                window.location.href = url;
            }
        },
    };
}
</script>
@endsection
