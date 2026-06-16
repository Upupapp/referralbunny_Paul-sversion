{{--
    First Sign-In Onboarding Walkthrough Modal
    Usage: <x-brand.onboarding-walkthrough />
    Included once in each authenticated layout. Shows automatically on first sign-in.
    Max 5 steps, role-based, skip-friendly, resumable.
--}}
@php $onboardingTenantId = isset($tenant) ? $tenant->id : null; @endphp
<div
    x-data="onboardingWalkthrough(@json($onboardingTenantId))"
    x-init="init()"
    @keydown.escape.window="if(visible) skip()"
    style="pointer-events:none"
>
    {{-- ── Backdrop ──────────────────────────────────────────────── --}}
    <div
        x-show="visible"
        x-transition:enter="transition-opacity duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-cloak
        class="fixed inset-0 bg-black/50 z-[160] backdrop-blur-sm"
        style="pointer-events:all"
        aria-hidden="true"
    ></div>

    {{-- ── Modal ─────────────────────────────────────────────────── --}}
    <div
        x-show="visible"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95 translate-y-4"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 translate-y-4"
        x-cloak
        class="fixed inset-0 z-[170] flex items-center justify-center p-4"
        style="pointer-events:all"
        role="dialog"
        aria-modal="true"
        aria-label="Welcome walkthrough"
    >
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden" @click.stop>

            {{-- Purple gradient header --}}
            <div class="relative overflow-hidden bg-gradient-to-br from-[#1E1B4B] to-[#4C3FA0] px-6 pt-8 pb-6 text-center">
                {{-- Decorative glows --}}
                <div class="absolute w-40 h-40 rounded-full top-[-40px] right-[-30px] opacity-20"
                     style="background:radial-gradient(circle,#7B61FF,transparent 70%)"></div>
                <div class="absolute w-28 h-28 rounded-full bottom-[-20px] left-[-20px] opacity-15"
                     style="background:radial-gradient(circle,#EC4899,transparent 70%)"></div>

                {{-- Step indicator dots --}}
                <div class="relative z-10 flex justify-center gap-1.5 mb-5">
                    <template x-for="(s, i) in steps" :key="i">
                        <div class="rounded-full transition-all duration-300"
                             :class="i === currentStep
                                 ? 'w-5 h-1.5 bg-white'
                                 : i < currentStep
                                     ? 'w-1.5 h-1.5 bg-white/60'
                                     : 'w-1.5 h-1.5 bg-white/25'">
                        </div>
                    </template>
                </div>

                {{-- R Bunny mascot --}}
                <div class="relative z-10 flex justify-center mb-3">
                    <template x-if="currentStepData">
                        <img :src="`/images/mascots/r-bunny-${currentStepData.mascot}.webp`"
                             :alt="'R Bunny ' + currentStepData.mascot"
                             class="w-20 h-20 object-contain drop-shadow-xl"
                             loading="lazy">
                    </template>
                </div>

                {{-- Step label --}}
                <p class="relative z-10 text-white/50 text-[10px] font-semibold uppercase tracking-widest mb-1"
                   x-text="`Step ${currentStep + 1} of ${steps.length}`"></p>

                {{-- Title --}}
                <h2 class="relative z-10 text-white font-bold text-xl leading-snug"
                    x-text="currentStepData?.title"></h2>

                {{-- Skip link --}}
                <button type="button"
                    @click="skip()"
                    class="absolute top-4 right-4 z-20 text-white/40 hover:text-white/70 transition-colors text-xs flex items-center gap-1"
                    aria-label="Skip walkthrough"
                >
                    <span>Skip</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="px-6 py-5">
                <p class="text-gray-600 text-sm leading-relaxed text-center" x-text="currentStepData?.body"></p>

                {{-- CTA buttons --}}
                <div class="flex gap-2.5 mt-5">
                    <button type="button"
                        x-show="currentStep > 0"
                        @click="prev()"
                        class="px-4 py-2.5 rounded-xl border border-gray-200 text-gray-500 text-sm font-medium hover:bg-gray-50 transition-colors"
                        aria-label="Go back"
                    >← Back</button>

                    <template x-if="currentStep < steps.length - 1">
                        <button type="button"
                            @click="next()"
                            class="flex-1 bg-[#7B61FF] hover:bg-[#6D4FE8] text-white font-semibold text-sm py-2.5 px-4 rounded-xl transition-colors"
                        >
                            Next →
                        </button>
                    </template>
                    <template x-if="currentStep === steps.length - 1">
                        <button type="button"
                            @click="finish()"
                            class="flex-1 bg-gradient-to-r from-[#7B61FF] to-[#EC4899] text-white font-semibold text-sm py-2.5 px-4 rounded-xl transition-colors hover:opacity-90"
                        >
                            🎉 Let's go!
                        </button>
                    </template>
                </div>

                {{-- R Bunny AI label --}}
                <p class="text-center text-[10px] text-gray-300 mt-3 font-medium">
                    R Bunny AI · Powered by ReferralBunny.ai
                </p>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function onboardingWalkthrough(tenantId) {
    const CSRF    = () => document.querySelector('meta[name=csrf-token]')?.content ?? '';
    const apiBase = tenantId ? `/api/onboarding/status?tenant_id=${encodeURIComponent(tenantId)}` : '/api/onboarding/status';
    const post    = (url, body = {}) => fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF(), 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(tenantId ? { ...body, tenant_id: tenantId } : body),
    });

    return {
        visible:     false,
        steps:       [],
        currentStep: 0,
        _status:     null,

        get currentStepData() { return this.steps[this.currentStep] ?? null; },

        async init() {
            try {
                const res = await fetch(apiBase, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                if (!data.enabled) return;

                this._status = data;
                this.steps   = data.walkthrough?.steps ?? [];

                // Show automatically only if not yet completed/skipped
                const status = data.walkthrough?.status;
                if (status === 'not_started' || status === 'in_progress') {
                    this.currentStep = data.walkthrough?.step ?? 0;
                    // Small delay — let the page settle
                    setTimeout(() => {
                        this.visible = true;
                        post('/api/onboarding/start');
                    }, 800);
                }
            } catch(e) {}
        },

        async next() {
            if (this.currentStep < this.steps.length - 1) {
                this.currentStep++;
                try { await post('/api/onboarding/advance', { step: this.currentStep }); } catch(e) {}
            }
        },

        prev() {
            if (this.currentStep > 0) this.currentStep--;
        },

        async finish() {
            try { await post('/api/onboarding/complete'); } catch(e) {}
            this.visible = false;
            this.$dispatch('show-toast', { type: 'success', message: "🎉 Tour complete! R Bunny is ready to guide your next steps." });
            // Trigger bot to open
            this.$dispatch('onboarding-walkthrough-done');
        },

        async skip() {
            try { await post('/api/onboarding/skip'); } catch(e) {}
            this.visible = false;
            this.$dispatch('show-toast', { type: 'info', message: "No problem — R Bunny will be at the bottom-left whenever you're ready." });
        },
    };
}
</script>
@endpush
