{{--
    R Bunny AI Dialog — Smart messaging reminder assistant
    Usage: <x-brand.r-bunny-ai-dialog />
    Included once in the main app layout.
--}}
<div
    x-data="rBunnyDialog()"
    x-init="init()"
    class="fixed bottom-5 right-5 z-[140] w-full max-w-[340px]"
    style="pointer-events:none"
    role="complementary"
    aria-label="R Bunny AI messaging assistant"
>
    {{-- ── Main dialog card ──────────────────────────────────── --}}
    <div
        x-show="visible && activeReminder"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-3 scale-95"
        x-cloak
        style="pointer-events:all"
        class="bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden"
        role="dialog"
        :aria-label="activeReminder?.title"
        @keydown.escape.window="dismissAll()"
    >
        {{-- Priority colour bar --}}
        <div class="h-1" :class="{
            'bg-red-500':    ['urgent','critical'].includes(activeReminder?.priority),
            'bg-orange-400': activeReminder?.priority === 'high',
            'bg-[#7B61FF]':  activeReminder?.priority === 'normal',
            'bg-gray-300':   activeReminder?.priority === 'low',
        }"></div>

        <div class="p-4">
            {{-- Header row --}}
            <div class="flex items-start gap-3">
                {{-- Mascot --}}
                <div class="shrink-0 w-10 h-10">
                    <template x-if="['urgent','high'].includes(activeReminder?.priority)">
                        <x-r-bunny variant="warning" size="xs" :decorative="true" style="width:40px;height:40px;object-fit:contain" />
                    </template>
                    <template x-if="!['urgent','high'].includes(activeReminder?.priority)">
                        <x-r-bunny variant="helper" size="xs" :decorative="true" style="width:40px;height:40px;object-fit:contain" />
                    </template>
                </div>

                {{-- Text --}}
                <div class="flex-1 min-w-0 pt-0.5">
                    <p class="text-[10px] font-bold text-[#7B61FF] uppercase tracking-widest mb-0.5">R Bunny AI</p>
                    <p class="text-sm font-semibold text-[#1E1B4B] leading-snug" x-text="activeReminder?.title"></p>
                    <p class="text-xs text-gray-500 mt-1 leading-relaxed" x-text="activeReminder?.body"></p>
                </div>

                {{-- Close --}}
                <button
                    @click="dismiss()"
                    class="shrink-0 p-1 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors"
                    aria-label="Dismiss reminder"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Suggested reply (if available) --}}
            <template x-if="suggestions.length > 0 && showSuggestions">
                <div class="mt-3 bg-[#F8F7FF] rounded-xl p-3 border border-purple-100">
                    <p class="text-[10px] font-semibold text-[#7B61FF] uppercase tracking-wide mb-1.5">Suggested reply</p>
                    <p class="text-xs text-gray-600 leading-relaxed italic" x-text="suggestions[0]"></p>
                    <div class="flex gap-2 mt-2">
                        <button @click="useSuggestion(suggestions[0])"
                                class="text-[10px] font-medium text-[#7B61FF] hover:text-purple-800 transition-colors">
                            Use this →
                        </button>
                        <button @click="showSuggestions = false"
                                class="text-[10px] text-gray-400 hover:text-gray-600 transition-colors">
                            Hide
                        </button>
                    </div>
                </div>
            </template>

            {{-- Action buttons --}}
            <div class="mt-3 flex flex-wrap gap-2">
                {{-- Primary action --}}
                <button
                    @click="handlePrimary()"
                    :class="['urgent','high'].includes(activeReminder?.priority)
                        ? 'bg-orange-500 hover:bg-orange-600 text-white'
                        : 'bg-[#7B61FF] hover:bg-purple-700 text-white'"
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors"
                    x-text="primaryLabel"
                ></button>

                {{-- Suggest reply button --}}
                <button
                    x-show="activeReminder?.action_url && !showSuggestions"
                    @click="loadSuggestions()"
                    class="px-3 py-1.5 rounded-lg text-xs font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors"
                >
                    Suggest reply
                </button>

                {{-- No reply needed --}}
                <button
                    @click="resolve()"
                    class="px-3 py-1.5 rounded-lg text-xs font-medium bg-gray-100 text-gray-500 hover:bg-gray-200 transition-colors"
                >
                    No reply needed
                </button>

                {{-- Snooze dropdown --}}
                <div x-data="{ snoozeOpen: false }" class="relative">
                    <button
                        @click="snoozeOpen = !snoozeOpen"
                        class="px-3 py-1.5 rounded-lg text-xs font-medium bg-gray-100 text-gray-500 hover:bg-gray-200 transition-colors"
                        aria-haspopup="true"
                        :aria-expanded="snoozeOpen"
                    >
                        Snooze ▾
                    </button>
                    <div
                        x-show="snoozeOpen"
                        @click.outside="snoozeOpen = false"
                        x-cloak
                        class="absolute bottom-full right-0 mb-1 bg-white rounded-xl shadow-lg border border-gray-100 py-1 w-36 z-10"
                        role="menu"
                    >
                        <button @click="snooze(30); snoozeOpen=false"   role="menuitem" class="w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-gray-50">30 minutes</button>
                        <button @click="snooze(120); snoozeOpen=false"  role="menuitem" class="w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-gray-50">2 hours</button>
                        <button @click="snooze(480); snoozeOpen=false"  role="menuitem" class="w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-gray-50">8 hours</button>
                        <button @click="snooze(1440); snoozeOpen=false" role="menuitem" class="w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-gray-50">Tomorrow</button>
                    </div>
                </div>
            </div>

            {{-- Multi-reminder footer --}}
            <div x-show="reminders.length > 1" class="mt-3 pt-2.5 border-t border-gray-100 flex items-center justify-between">
                <span class="text-[10px] text-gray-400"
                      x-text="`${reminders.length - 1} more item${reminders.length > 2 ? 's' : ''} need${reminders.length === 2 ? 's' : ''} attention`">
                </span>
                <button @click="next()" class="text-[10px] font-medium text-[#7B61FF] hover:text-purple-800 transition-colors">
                    View next →
                </button>
            </div>
        </div>
    </div>

    {{-- ── Collapsed badge (when minimised) ─────────────────── --}}
    <div
        x-show="!visible && reminders.length > 0"
        x-transition
        x-cloak
        style="pointer-events:all"
        class="flex justify-end"
    >
        <button
            @click="visible = true"
            class="flex items-center gap-2 bg-white rounded-full shadow-lg border border-gray-100 pl-3 pr-4 py-2 hover:shadow-xl transition-all"
            aria-label="Open R Bunny AI messaging reminders"
        >
            <x-r-bunny variant="helper" size="xs" :decorative="true" style="width:22px;height:22px;object-fit:contain" />
            <span class="text-xs font-semibold text-[#1E1B4B]" x-text="`${reminders.length} reminder${reminders.length > 1 ? 's' : ''}`"></span>
            <span class="w-2 h-2 rounded-full bg-[#7B61FF] animate-pulse"></span>
        </button>
    </div>
</div>

@push('scripts')
<script>
function rBunnyDialog() {
    const CSRF = () => document.querySelector('meta[name=csrf-token]')?.content ?? '';
    const hdrs = (extra = {}) => ({
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...extra,
    });
    const postHdrs = () => ({ ...hdrs(), 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF() });

    return {
        visible:         false,
        reminders:       [],
        activeIndex:     0,
        suggestions:     [],
        showSuggestions: false,
        _sessionShown:   false,

        get activeReminder() { return this.reminders[this.activeIndex] ?? null; },

        get primaryLabel() {
            const type = this.activeReminder?.reminder_type;
            if (type === 'deal_deadline') return 'Reply & Update Deal';
            if (type === 'admin_request') return 'Respond Now';
            return 'Reply Now';
        },

        async init() {
            // Wait 4 seconds after load — don't popup immediately
            setTimeout(async () => {
                await this.load();
                if (this.reminders.length > 0 && !this._sessionShown) {
                    this.visible = true;
                    this._sessionShown = true;
                }
            }, 4000);
        },

        async load() {
            try {
                const r    = await fetch('/api/message-reminders/active', { headers: hdrs() });
                const data = await r.json();
                this.reminders = Array.isArray(data.reminders) ? data.reminders : [];
                if (this.activeIndex >= this.reminders.length) this.activeIndex = 0;
            } catch(e) { this.reminders = []; }
        },

        async handlePrimary() {
            const url = this.activeReminder?.action_url;
            if (url) {
                await this.resolve();
                window.location.href = url;
            } else {
                await this.resolve();
            }
        },

        async resolve() {
            if (!this.activeReminder) return;
            try {
                await fetch('/api/message-reminders/resolve', {
                    method: 'POST', headers: postHdrs(),
                    body: JSON.stringify({ deduplication_key: this.activeReminder.deduplication_key }),
                });
            } catch(e) {}
            this._removeAndAdvance();
        },

        async dismiss() {
            if (!this.activeReminder) return;
            // Dismiss hides dialog but keeps other reminders for later
            this.visible = false;
            try {
                await fetch('/api/message-reminders/dismiss', {
                    method: 'POST', headers: postHdrs(),
                    body: JSON.stringify({ deduplication_key: this.activeReminder.deduplication_key }),
                });
            } catch(e) {}
            this._removeAndAdvance();
        },

        dismissAll() {
            this.visible = false;
        },

        async snooze(minutes) {
            if (!this.activeReminder) return;
            try {
                await fetch('/api/message-reminders/snooze', {
                    method: 'POST', headers: postHdrs(),
                    body: JSON.stringify({
                        deduplication_key: this.activeReminder.deduplication_key,
                        minutes,
                    }),
                });
            } catch(e) {}
            this._removeAndAdvance();
        },

        async loadSuggestions() {
            this.showSuggestions = true;
            try {
                const type = this.activeReminder?.reminder_type ?? 'unread_message';
                const r    = await fetch(`/api/message-reminders/suggestions?reminder_type=${type}`, { headers: hdrs() });
                const data = await r.json();
                this.suggestions = data.suggestions ?? [];
            } catch(e) { this.suggestions = []; }
        },

        useSuggestion(text) {
            // Copy to clipboard and navigate to conversation
            navigator.clipboard?.writeText(text).catch(() => {});
            const url = this.activeReminder?.action_url;
            if (url) window.location.href = url;
        },

        next() {
            this.suggestions     = [];
            this.showSuggestions = false;
            this.activeIndex     = (this.activeIndex + 1) % this.reminders.length;
        },

        _removeAndAdvance() {
            this.suggestions     = [];
            this.showSuggestions = false;
            this.reminders.splice(this.activeIndex, 1);
            if (this.activeIndex >= this.reminders.length) this.activeIndex = 0;
            if (this.reminders.length === 0) this.visible = false;
        },
    };
}
</script>
@endpush
