{{--
    R Bunny Onboarding Bot — lower-left floating assistant
    Usage: <x-brand.onboarding-bot />
    Included once per authenticated layout.
    Separate from the messaging reminder bot (bottom-right).
    Shows onboarding progress, next task suggestion, and task list.
--}}
@php $onboardingTenantId = isset($tenant) ? $tenant->id : null; @endphp
<div
    x-data="onboardingBot(@json($onboardingTenantId))"
    x-init="init()"
    @onboarding-walkthrough-done.window="onWalkthroughDone()"
    class="fixed bottom-5 left-5 z-[140]"
    style="pointer-events:none"
    role="complementary"
    aria-label="R Bunny onboarding assistant"
>
    {{-- ── Expanded panel ──────────────────────────────────────── --}}
    <div
        x-show="open && !fullyReady"
        x-transition:enter="transition ease-out duration-250"
        x-transition:enter-start="opacity-0 translate-y-3 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-2 scale-95"
        x-cloak
        class="bg-white rounded-2xl shadow-2xl border border-gray-100 w-[300px] sm:w-[320px] overflow-hidden mb-2"
        style="pointer-events:all"
        role="dialog"
        aria-label="R Bunny onboarding suggestions"
    >
        {{-- Header --}}
        <div class="bg-gradient-to-r from-[#1E1B4B] to-[#4C3FA0] px-4 py-3 flex items-center gap-3">
            <img :src="`/images/mascots/r-bunny-${nextTask?.mascot || 'helper-question'}.webp`"
                 alt="R Bunny"
                 class="w-8 h-8 object-contain shrink-0"
                 loading="lazy">
            <div class="flex-1 min-w-0">
                <p class="text-white text-xs font-bold leading-none">R Bunny Onboarding</p>
                <p class="text-white/50 text-[10px] mt-0.5"
                   x-text="`${progressDone} of ${progressTotal} steps complete`"></p>
            </div>
            <button @click="open = false"
                    class="text-white/50 hover:text-white transition-colors shrink-0"
                    aria-label="Collapse R Bunny">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
        </div>

        {{-- Progress bar --}}
        <div class="h-1.5 bg-gray-100">
            <div class="h-1.5 bg-gradient-to-r from-[#7B61FF] to-[#EC4899] transition-all duration-500 rounded-r-full"
                 :style="`width: ${progressPercent}%`"
                 role="progressbar"
                 :aria-valuenow="progressPercent"
                 aria-valuemin="0"
                 aria-valuemax="100"
                 :aria-label="`Onboarding ${progressPercent}% complete`">
            </div>
        </div>

        <div class="p-4 space-y-3">

            {{-- Next task suggestion --}}
            <template x-if="nextTask && !snoozed">
                <div class="bg-[#F8F7FF] rounded-xl p-3.5 border border-purple-100">
                    <p class="text-[10px] font-bold text-[#7B61FF] uppercase tracking-widest mb-1">Next Step</p>
                    <p class="text-sm text-gray-700 leading-snug font-medium" x-text="nextTask.label"></p>
                    <p class="text-xs text-gray-500 mt-1 leading-relaxed" x-text="nextTask.message"></p>
                    <div class="flex flex-wrap gap-2 mt-3">
                        <template x-if="nextTask.action">
                            <a :href="nextTask.action"
                               @click="completeTask(nextTask.key)"
                               class="px-3 py-1.5 bg-[#7B61FF] hover:bg-[#6D4FE8] text-white text-xs font-semibold rounded-lg transition-colors"
                               x-text="nextTask.action ? 'Go there →' : 'Mark done'">
                            </a>
                        </template>
                        <template x-if="!nextTask.action">
                            <button @click="completeTask(nextTask.key)"
                                    class="px-3 py-1.5 bg-[#7B61FF] hover:bg-[#6D4FE8] text-white text-xs font-semibold rounded-lg transition-colors">
                                Mark as done ✓
                            </button>
                        </template>
                        <button @click="dismissTask(nextTask.key)"
                                class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-500 text-xs font-medium rounded-lg transition-colors">
                            Not now
                        </button>
                    </div>
                </div>
            </template>

            {{-- Snoozed state --}}
            <template x-if="snoozed">
                <div class="bg-gray-50 rounded-xl p-3.5 text-center border border-gray-100">
                    <img src="/images/mascots/r-bunny-sleeping.webp" alt="R Bunny resting"
                         class="w-10 h-10 object-contain mx-auto mb-2" loading="lazy">
                    <p class="text-xs text-gray-400">Reminders snoozed. I'll be back soon!</p>
                    <button @click="wakeUp()"
                            class="mt-2 text-xs text-[#7B61FF] hover:text-purple-700 font-medium">
                        Resume now
                    </button>
                </div>
            </template>

            {{-- Task list --}}
            <div>
                <button @click="showTasks = !showTasks"
                        class="flex items-center justify-between w-full text-[10px] font-bold text-gray-400 uppercase tracking-widest py-1"
                        :aria-expanded="showTasks">
                    <span>All steps</span>
                    <svg class="w-3 h-3 transition-transform duration-200" :class="showTasks ? 'rotate-180' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div x-show="showTasks"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 -translate-y-1"
                     class="space-y-1.5 pt-1">
                    <template x-for="task in tasks" :key="task.key">
                        <div class="flex items-center gap-2.5 py-1.5">
                            <div class="shrink-0 w-4 h-4 rounded-full flex items-center justify-center"
                                 :class="task.completed ? 'bg-emerald-500' : 'border-2 border-gray-200'">
                                <template x-if="task.completed">
                                    <svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </template>
                            </div>
                            <span class="text-xs flex-1 leading-tight"
                                  :class="task.completed ? 'text-gray-400 line-through' : 'text-gray-700'"
                                  x-text="task.label"></span>
                            <span x-show="task.required && !task.completed"
                                  class="text-[9px] font-semibold text-amber-500 uppercase tracking-wide shrink-0">Required</span>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Snooze / profile progress footer --}}
            <div class="pt-1 flex items-center justify-between border-t border-gray-50">
                <div class="text-[10px] text-gray-400">
                    Profile:
                    <span class="font-semibold text-[#7B61FF]" x-text="profilePercent + '%'"></span>
                    complete
                </div>
                <button @click="snooze()"
                        class="text-[10px] text-gray-400 hover:text-gray-600 transition-colors">
                    Snooze 24h
                </button>
            </div>
        </div>
    </div>

    {{-- ── Fully ready celebration panel ───────────────────────── --}}
    <div
        x-show="open && fullyReady && !celebrationDismissed"
        x-transition
        x-cloak
        class="bg-white rounded-2xl shadow-2xl border border-emerald-100 w-[300px] sm:w-[320px] overflow-hidden mb-2"
        style="pointer-events:all"
    >
        <div class="p-5 text-center">
            <img src="/images/mascots/r-bunny-celebration.webp"
                 alt="R Bunny celebrating" class="w-16 h-16 object-contain mx-auto mb-3" loading="lazy">
            <p class="text-[#1E1B4B] font-bold text-sm leading-snug">
                I think you're fully ready now! 🎉
            </p>
            <p class="text-gray-500 text-xs mt-2 leading-relaxed">
                You've completed the important first steps. Come back here anytime if you have
                questions or need bunny-ful assistance. Happy referring!
            </p>
            <button
                @click="celebrationDismissed = true; open = false"
                class="mt-4 w-full bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-semibold py-2.5 rounded-xl transition-colors"
            >
                Thanks, R Bunny! ✓
            </button>
        </div>
    </div>

    {{-- ── Collapsed toggle button ───────────────────────────────── --}}
    <button
        x-show="!open || (fullyReady && celebrationDismissed)"
        @click="open = true; celebrationDismissed = false"
        class="flex items-center gap-2 bg-white rounded-full shadow-lg border border-gray-100 pl-2.5 pr-4 py-2 hover:shadow-xl transition-all"
        style="pointer-events:all"
        aria-label="Open R Bunny onboarding assistant"
    >
        <img src="/images/mascots/r-bunny-waving.webp"
             alt="R Bunny"
             class="w-6 h-6 object-contain shrink-0"
             loading="lazy">
        <span class="text-xs font-semibold"
              :class="fullyReady ? 'text-emerald-600' : 'text-[#1E1B4B]'"
              x-text="fullyReady ? 'Ready! 🎉' : `Setup · ${progressPercent}%`">
        </span>
        <span x-show="!fullyReady"
              class="w-2 h-2 rounded-full bg-[#7B61FF] animate-pulse shrink-0"
              aria-hidden="true"></span>
    </button>
</div>

@push('scripts')
<script>
function onboardingBot(tenantId) {
    const CSRF    = () => document.querySelector('meta[name=csrf-token]')?.content ?? '';
    const apiBase = tenantId ? `/api/onboarding/status?tenant_id=${encodeURIComponent(tenantId)}` : '/api/onboarding/status';
    const hdrs    = () => ({ 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' });
    const pHdrs   = () => ({ ...hdrs(), 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF() });
    const post    = (url, body = {}) => fetch(url, { method: 'POST', headers: pHdrs(), body: JSON.stringify(tenantId ? { ...body, tenant_id: tenantId } : body) });

    return {
        open:                false,
        showTasks:           false,
        tasks:               [],
        nextTask:            null,
        progressDone:        0,
        progressTotal:       0,
        progressPercent:     0,
        profilePercent:      0,
        fullyReady:          false,
        snoozed:             false,
        celebrationDismissed: false,
        _loaded:             false,

        async init() {
            await this.load();
        },

        async load() {
            try {
                const res  = await fetch(apiBase, { headers: hdrs() });
                const data = await res.json();
                if (!data.enabled) return;

                this.tasks           = data.tasks           ?? [];
                this.nextTask        = data.next_task        ?? null;
                this.progressDone    = data.progress?.done   ?? 0;
                this.progressTotal   = data.progress?.total  ?? 0;
                this.progressPercent = data.progress?.percent ?? 0;
                this.profilePercent  = data.profile_progress ?? 0;
                this.fullyReady      = data.progress?.fully_ready ?? false;
                this.snoozed         = data.is_snoozed       ?? false;
                this._loaded         = true;

                // Show celebration once if just became ready
                if (this.fullyReady && !this.celebrationDismissed) {
                    setTimeout(() => { this.open = true; }, 1500);
                }
            } catch(e) {}
        },

        async onWalkthroughDone() {
            await this.load();
            setTimeout(() => { this.open = true; }, 500);
        },

        async completeTask(key) {
            try {
                const res  = await post('/api/onboarding/task/complete', { task_key: key });
                const data = await res.json();
                this._applyStatus(data);
                this.$dispatch('show-toast', { type: 'success', message: 'Nice hop! Task marked as done. ✓' });
            } catch(e) {}
        },

        async dismissTask(key) {
            try {
                const res  = await post('/api/onboarding/task/dismiss', { task_key: key });
                const data = await res.json();
                this._applyStatus(data);
            } catch(e) {}
        },

        async snooze() {
            try {
                const res  = await post('/api/onboarding/snooze', { hours: 24 });
                const data = await res.json();
                this._applyStatus(data);
                this.open  = false;
                this.$dispatch('show-toast', { type: 'info', message: "R Bunny will rest for 24 hours. You can always wake me up." });
            } catch(e) {}
        },

        async wakeUp() {
            try {
                const res  = await post('/api/onboarding/wake-up');
                const data = await res.json();
                this._applyStatus(data);
            } catch(e) {}
        },

        _applyStatus(data) {
            if (!data.enabled) return;
            this.tasks           = data.tasks           ?? [];
            this.nextTask        = data.next_task        ?? null;
            this.progressDone    = data.progress?.done   ?? 0;
            this.progressTotal   = data.progress?.total  ?? 0;
            this.progressPercent = data.progress?.percent ?? 0;
            this.profilePercent  = data.profile_progress ?? 0;
            this.fullyReady      = data.progress?.fully_ready ?? false;
            this.snoozed         = data.is_snoozed ?? false;
        },
    };
}
</script>
@endpush
