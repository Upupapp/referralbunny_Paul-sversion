@props(['text', 'position' => 'top', 'variant' => 'default'])

{{--
    Usage:
      <x-info-tip text="Explains what this data means." />
      <x-info-tip text="..." position="right" />
      <x-info-tip text="..." variant="light" />   ← for dark/gradient backgrounds

    Behaviour:
      - Click ⓘ to open. Click ✕ or click outside to close.
      - Auto-closes after 8 seconds.
      - Opening one tip closes all others (via tip:close-all event).
      - @click.stop on both the wrapper AND the button prevents propagation
        to any parent <a> or clickable container.
--}}

<div x-data="{
        tipOpen: false,
        fixedPos: {x:0, y:0},
        _timer: null,
        openTip() {
            if (!this.tipOpen) window.dispatchEvent(new CustomEvent('tip:close-all'));
            const btn = this.$el.querySelector('button');
            if (btn) {
                const rect = btn.getBoundingClientRect();
                this.fixedPos = { x: rect.left + rect.width / 2, y: rect.top + rect.height / 2 };
            }
            this.tipOpen = !this.tipOpen;
            if (this.tipOpen) {
                clearTimeout(this._timer);
                this._timer = setTimeout(() => { this.tipOpen = false; }, 8000);
            } else {
                clearTimeout(this._timer);
            }
        },
        closeTip() {
            this.tipOpen = false;
            clearTimeout(this._timer);
        }
    }"
    class="relative inline-flex items-center"
    @tip:close-all.window="closeTip()"
    @click.stop>

    {{-- Info icon button --}}
    <button
        @click.stop="openTip()"
        type="button"
        aria-label="More information"
        @class([
            'ml-1 transition-colors focus:outline-none rounded-full flex-shrink-0',
            'text-gray-300 hover:text-[#7B61FF]' => $variant === 'default',
            'text-white/30 hover:text-white/70'   => $variant === 'light',
        ])>
        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
        </svg>
    </button>

    {{-- Tooltip panel --}}
    <div
        x-show="tipOpen"
        @click.outside="closeTip()"
        @click.stop
        x-cloak
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="w-64 p-3 bg-gray-900 text-white text-xs rounded-xl shadow-2xl leading-relaxed font-normal"
        :style="
            '{{ $position }}' === 'top'    ? 'position:fixed;z-index:9999;left:' + fixedPos.x + 'px;top:' + fixedPos.y + 'px;transform:translateX(-50%) translateY(calc(-100% - 8px))' :
            '{{ $position }}' === 'bottom' ? 'position:fixed;z-index:9999;left:' + fixedPos.x + 'px;top:' + (fixedPos.y + 20) + 'px;transform:translateX(-50%)' :
            '{{ $position }}' === 'right'  ? 'position:fixed;z-index:9999;left:' + (fixedPos.x + 12) + 'px;top:' + fixedPos.y + 'px;transform:translateY(-50%)' :
                                             'position:fixed;z-index:9999;left:' + (fixedPos.x - 12) + 'px;top:' + fixedPos.y + 'px;transform:translateX(-100%) translateY(-50%)'
        ">

        {{-- Content + close button --}}
        <div class="flex items-start gap-2">
            <p class="flex-1">{{ $text }}</p>
            <button
                @click.stop="closeTip()"
                type="button"
                aria-label="Close"
                class="text-white/40 hover:text-white transition-colors shrink-0 mt-0.5 rounded">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Auto-close progress bar --}}
        <div class="mt-2.5 h-px bg-white/15 rounded-full overflow-hidden">
            <div x-show="tipOpen"
                 class="h-full bg-white/40 rounded-full"
                 style="animation: tip-shrink 8s linear forwards;"></div>
        </div>

        {{-- Caret arrow --}}
        @if($position === 'top')
            <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
        @elseif($position === 'bottom')
            <div class="absolute bottom-full left-1/2 -translate-x-1/2 border-4 border-transparent border-b-gray-900"></div>
        @elseif($position === 'right')
            <div class="absolute right-full top-1/2 -translate-y-1/2 border-4 border-transparent border-r-gray-900"></div>
        @else
            <div class="absolute left-full top-1/2 -translate-y-1/2 border-4 border-transparent border-l-gray-900"></div>
        @endif
    </div>
</div>
