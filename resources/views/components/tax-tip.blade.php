{{--
    Tax Disclaimer Tip — Philippines (LGU IDS) only
    Usage: <x-tax-tip /> — only render when $showLocation is true
    Shows an orange ⓘ that opens a fixed-position panel with PH tax obligations.
--}}
<div x-data="{
        taxOpen: false,
        pos: { x: 0, y: 0 },
        toggle() {
            if (!this.taxOpen) {
                const btn = this.$el.querySelector('button');
                if (btn) {
                    const r = btn.getBoundingClientRect();
                    this.pos = { x: r.left + r.width / 2, y: r.top };
                }
            }
            this.taxOpen = !this.taxOpen;
        },
        style() {
            return 'position:fixed;z-index:9999;left:' + this.pos.x + 'px;top:' + this.pos.y + 'px;transform:translateX(-50%) translateY(calc(-100% - 10px))';
        }
    }"
     class="relative inline-flex items-center"
     @click.stop
     @keydown.escape.window="taxOpen = false">

    <button @click.stop="toggle()"
            type="button"
            title="Philippine tax obligations on this amount"
            class="ml-1.5 flex-shrink-0 focus:outline-none rounded-full transition-colors"
            :class="taxOpen ? 'text-orange-500' : 'text-orange-400 hover:text-orange-600'">
        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
        </svg>
    </button>

    <div x-show="taxOpen"
         @click.outside="taxOpen = false"
         @click.stop
         x-cloak
         :style="style()"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="w-80 p-4 bg-gray-900 text-white rounded-xl shadow-2xl leading-relaxed">

        <div class="flex items-center justify-between gap-2 mb-3">
            <div class="flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-orange-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <p class="font-bold text-orange-300 uppercase tracking-wider text-[10px]">PH Tax Obligations</p>
            </div>
            <button @click.stop="taxOpen = false" type="button"
                    class="text-white/40 hover:text-white transition-colors shrink-0 p-0.5 rounded">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <p class="text-white/70 text-[11px] mb-3 leading-relaxed">
            Figures shown are <strong class="text-white">pre-tax estimates.</strong>
            The following taxes apply under Philippine law (NIRC, as amended by TRAIN Law — RA 10963):
        </p>

        <div class="space-y-2.5 text-[11px]">
            <div class="flex gap-2 items-start">
                <span class="text-orange-400 font-bold shrink-0">•</span>
                <p class="text-white/80 leading-relaxed">
                    <span class="text-white font-semibold">VAT — 12%</span>
                    <span class="text-white/55"> for VAT-registered entities (annual gross receipts ≥ ₱3,000,000). File BIR Form 2550M / 2550Q.</span>
                </p>
            </div>
            <div class="flex gap-2 items-start">
                <span class="text-orange-400 font-bold shrink-0">•</span>
                <p class="text-white/80 leading-relaxed">
                    <span class="text-white font-semibold">Percentage Tax — 3%</span>
                    <span class="text-white/55"> for non-VAT entities (gross receipts below ₱3,000,000 / year). File BIR Form 2551Q.</span>
                </p>
            </div>
            <div class="flex gap-2 items-start">
                <span class="text-orange-400 font-bold shrink-0">•</span>
                <p class="text-white/80 leading-relaxed">
                    <span class="text-white font-semibold">Creditable Withholding Tax (CWT) — 5% or 10%</span>
                    <span class="text-white/55"> on commission / professional fees. Rate is 5% if annual income from a single payor is below ₱720,000; 10% if ₱720,000 or more. Payor must issue BIR Form 2307.</span>
                </p>
            </div>
            <div class="flex gap-2 items-start">
                <span class="text-orange-400 font-bold shrink-0">•</span>
                <p class="text-white/80 leading-relaxed">
                    <span class="text-white font-semibold">Income Tax</span>
                    <span class="text-white/55"> — Commission income is taxable. Graduated rates 0–35% for individuals; 25% for corporations. File BIR Form 1701A / 1702RT.</span>
                </p>
            </div>
            <div class="flex gap-2 items-start">
                <span class="text-orange-400 font-bold shrink-0">•</span>
                <p class="text-white/80 leading-relaxed">
                    <span class="text-white font-semibold">Local Business Tax (LBT)</span>
                    <span class="text-white/55"> — LGUs may impose taxes on commission agents under the Local Government Code (RA 7160). Rates vary per locality.</span>
                </p>
            </div>
        </div>

        <div class="mt-3 pt-2.5 border-t border-white/10">
            <p class="text-white/35 text-[10px] leading-relaxed">
                For informational purposes only. Obligations vary by registration status and transaction type.
                Consult a CPA or the BIR (bir.gov.ph) for your specific compliance requirements.
            </p>
        </div>

        {{-- Caret --}}
        <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
    </div>
</div>
