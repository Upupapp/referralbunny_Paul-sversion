@extends('layouts.app')
@section('title', 'Calendar')
@section('nav') @include('tenant._nav') @endsection

@section('content')
<div x-data="rbCalendar('{{ $tenantId }}')" x-init="init()" class="h-full flex flex-col">

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between gap-3 mb-4 flex-wrap">
        <div class="flex items-center gap-3">
            <h1 class="text-xl font-bold text-[#1E1B4B]" x-text="monthLabel"></h1>
            <div class="flex items-center gap-1">
                <button @click="prevMonth()"
                        class="w-8 h-8 rounded-xl flex items-center justify-center text-gray-500 hover:bg-gray-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <button @click="goToToday()"
                        class="px-3 py-1 rounded-lg text-xs font-semibold text-[#7B61FF] hover:bg-purple-50 transition-colors border border-purple-200">
                    Today
                </button>
                <button @click="nextMonth()"
                        class="w-8 h-8 rounded-xl flex items-center justify-center text-gray-500 hover:bg-gray-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>
        </div>

        {{-- Filter chips --}}
        <div class="flex items-center gap-2">
            <button @click="filter = 'all'"
                    :class="filter === 'all' ? 'bg-[#1E1B4B] text-white' : 'bg-white text-gray-600 border border-gray-200 hover:border-[#7B61FF] hover:text-[#7B61FF]'"
                    class="px-3 py-1.5 rounded-full text-xs font-semibold transition-all">All</button>
            <button @click="filter = 'task'"
                    :class="filter === 'task' ? 'bg-purple-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:border-purple-400 hover:text-purple-600'"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition-all">
                <span class="w-2 h-2 rounded-full bg-purple-500"></span>Tasks
            </button>
            <button @click="filter = 'deal'"
                    :class="filter === 'deal' ? 'bg-orange-500 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:border-orange-400 hover:text-orange-600'"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition-all">
                <span class="w-2 h-2 rounded-full bg-orange-400"></span>Deal Expiry
            </button>
        </div>
    </div>

    {{-- ── Loading ───────────────────────────────────────────────────────────── --}}
    <div x-show="loading" class="flex items-center justify-center py-16">
        <svg class="w-6 h-6 animate-spin text-[#7B61FF]" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
    </div>

    <div x-show="!loading" class="flex gap-4 flex-1 min-h-0">

        {{-- ── Month Grid ──────────────────────────────────────────────────── --}}
        <div class="flex-1 bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden flex flex-col">
            {{-- Day-of-week headers --}}
            <div class="grid grid-cols-7 border-b border-gray-100">
                <template x-for="d in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="d">
                    <div class="py-2 text-center text-[11px] font-semibold text-gray-400 uppercase tracking-wide" x-text="d"></div>
                </template>
            </div>

            {{-- Calendar grid --}}
            <div class="grid grid-cols-7 flex-1" style="grid-auto-rows: minmax(80px, 1fr)">
                <template x-for="cell in cells" :key="cell.key">
                    <div @click="selectDay(cell)"
                         :class="{
                             'bg-gray-50 text-gray-300': !cell.inMonth,
                             'ring-2 ring-[#7B61FF] ring-inset': cell.isSelected && cell.inMonth,
                             'cursor-pointer hover:bg-[#F5F3FF]': cell.inMonth,
                         }"
                         class="border-r border-b border-gray-100 p-1.5 transition-colors relative">

                        {{-- Date number --}}
                        <div class="flex items-center justify-between mb-1">
                            <span :class="{
                                      'w-6 h-6 rounded-full bg-[#7B61FF] text-white flex items-center justify-center': cell.isToday,
                                      'text-gray-800 font-medium': cell.inMonth && !cell.isToday,
                                      'text-gray-300': !cell.inMonth,
                                  }"
                                  class="text-xs leading-none"
                                  x-text="cell.day"></span>
                            <span x-show="cell.moreCount > 0"
                                  class="text-[9px] font-bold text-[#7B61FF] bg-purple-50 px-1 rounded"
                                  x-text="'+' + cell.moreCount"></span>
                        </div>

                        {{-- Event pills (max 3) --}}
                        <div class="space-y-0.5">
                            <template x-for="evt in cell.visibleEvents" :key="evt.id">
                                <a :href="evt.url"
                                   @click.stop
                                   :title="evt.title"
                                   :class="pillClass(evt)"
                                   class="block w-full truncate rounded px-1.5 py-0.5 text-[10px] font-medium leading-tight transition-opacity hover:opacity-80">
                                    <span x-text="evt.title"></span>
                                </a>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- ── Day Detail Panel ─────────────────────────────────────────────── --}}
        <div x-show="selectedDay"
             x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-x-4"
             x-transition:enter-end="opacity-100 translate-x-0"
             class="w-72 bg-white rounded-2xl border border-gray-100 shadow-sm flex flex-col overflow-hidden shrink-0">

            {{-- Panel header --}}
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                <div>
                    <p class="text-xs text-gray-400 font-medium" x-text="selectedDayLabel"></p>
                    <p class="text-sm font-bold text-[#1E1B4B]"
                       x-text="selectedDayEvents.length + (selectedDayEvents.length === 1 ? ' event' : ' events')"></p>
                </div>
                <button @click="selectedDay = null"
                        class="w-7 h-7 rounded-xl bg-gray-100 flex items-center justify-center text-gray-400 hover:bg-gray-200 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Empty state --}}
            <div x-show="selectedDayEvents.length === 0" class="flex-1 flex flex-col items-center justify-center py-10 px-4 text-center">
                <svg class="w-10 h-10 text-gray-200 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <p class="text-sm text-gray-400">Nothing scheduled</p>
            </div>

            {{-- Event list --}}
            <div class="flex-1 overflow-y-auto divide-y divide-gray-50 px-2 py-1">
                <template x-for="evt in selectedDayEvents" :key="evt.id">
                    <a :href="evt.url"
                       class="flex items-start gap-3 px-2 py-3 rounded-xl hover:bg-gray-50 transition-colors group">
                        {{-- Color dot --}}
                        <span class="mt-0.5 w-2.5 h-2.5 rounded-full shrink-0"
                              :class="dotClass(evt)"></span>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-[#1E1B4B] group-hover:text-[#7B61FF] transition-colors leading-snug"
                               :class="{ 'line-through text-gray-400': evt.done }"
                               x-text="evt.title"></p>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full"
                                      :class="labelClass(evt)"
                                      x-text="evt.label"></span>
                                <span x-show="evt.type === 'deal'" class="text-[10px] text-gray-400"
                                      x-text="evt.days_left + ' day' + (evt.days_left === 1 ? '' : 's') + ' left'"></span>
                                <span x-show="evt.type === 'task' && evt.priority" class="text-[10px] text-gray-400 capitalize"
                                      x-text="evt.priority"></span>
                            </div>
                            <p x-show="evt.type === 'deal' && evt.referrer"
                               class="text-[10px] text-gray-400 mt-0.5 truncate"
                               x-text="'Referrer: ' + evt.referrer"></p>
                        </div>
                        <svg class="w-3.5 h-3.5 text-gray-300 group-hover:text-[#7B61FF] shrink-0 mt-0.5 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </template>
            </div>
        </div>
    </div>

    {{-- ── Legend ───────────────────────────────────────────────────────────── --}}
    <div class="flex items-center gap-5 mt-3 pt-3 border-t border-gray-100 flex-wrap">
        @foreach([
            ['color' => 'bg-red-500',    'label' => 'Urgent task / Deal expiring ≤3d'],
            ['color' => 'bg-orange-400', 'label' => 'High task / Deal expiring ≤7d'],
            ['color' => 'bg-purple-500', 'label' => 'Medium task'],
            ['color' => 'bg-blue-400',   'label' => 'Low task'],
            ['color' => 'bg-yellow-400', 'label' => 'Deal expiry'],
            ['color' => 'bg-gray-300',   'label' => 'Completed'],
        ] as $item)
        <div class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full {{ $item['color'] }}"></span>
            <span class="text-[11px] text-gray-400">{{ $item['label'] }}</span>
        </div>
        @endforeach
    </div>

</div>
@endsection

@push('scripts')
<script>
function rbCalendar(tenantId) {
    return {
        tenantId,
        today:    null,
        current:  null,   // { year, month } being displayed
        events:   [],
        grouped:  {},
        cells:    [],
        loading:  false,
        filter:   'all',

        selectedDay:       null,   // 'YYYY-MM-DD'
        selectedDayLabel:  '',
        selectedDayEvents: [],

        get monthLabel() {
            if (!this.current) return '';
            const d = new Date(this.current.year, this.current.month - 1, 1);
            return d.toLocaleString('default', { month: 'long', year: 'numeric' });
        },

        init() {
            const now  = new Date();
            this.today = this.fmt(now);
            this.current = { year: now.getFullYear(), month: now.getMonth() + 1 };
            this.fetchEvents();
        },

        fmt(d) {
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2,'0');
            const dd = String(d.getDate()).padStart(2,'0');
            return `${y}-${m}-${dd}`;
        },

        prevMonth() {
            let { year, month } = this.current;
            month--;
            if (month < 1) { month = 12; year--; }
            this.current = { year, month };
            this.selectedDay = null;
            this.fetchEvents();
        },

        nextMonth() {
            let { year, month } = this.current;
            month++;
            if (month > 12) { month = 1; year++; }
            this.current = { year, month };
            this.selectedDay = null;
            this.fetchEvents();
        },

        goToToday() {
            const now = new Date();
            this.current = { year: now.getFullYear(), month: now.getMonth() + 1 };
            this.selectedDay = null;
            this.fetchEvents();
        },

        async fetchEvents() {
            this.loading = true;
            const { year, month } = this.current;
            const from = `${year}-${String(month).padStart(2,'0')}-01`;
            const lastDay = new Date(year, month, 0).getDate();
            const to   = `${year}-${String(month).padStart(2,'0')}-${String(lastDay).padStart(2,'0')}`;
            try {
                const res  = await fetch(`/tenant/${tenantId}/calendar/events?from=${from}&to=${to}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const data = await res.json();
                this.events  = data.events  || [];
                this.grouped = data.grouped || {};
            } catch(e) {
                this.events = []; this.grouped = {};
            } finally {
                this.loading = false;
                this.$nextTick(() => this.buildCells());
            }
        },

        buildCells() {
            const { year, month } = this.current;
            const firstDay  = new Date(year, month - 1, 1).getDay(); // 0=Sun
            const daysInMonth = new Date(year, month, 0).getDate();
            const prevDays    = new Date(year, month - 1, 0).getDate();

            const cells = [];
            const MAX_VISIBLE = 3;

            // Leading days from previous month
            for (let i = firstDay - 1; i >= 0; i--) {
                cells.push(this.makeCell(year, month - 1, prevDays - i, false, MAX_VISIBLE));
            }
            // Current month
            for (let d = 1; d <= daysInMonth; d++) {
                cells.push(this.makeCell(year, month, d, true, MAX_VISIBLE));
            }
            // Trailing days to fill 6-row grid (42 cells)
            let trailing = 1;
            while (cells.length < 42) {
                cells.push(this.makeCell(year, month + 1, trailing++, false, MAX_VISIBLE));
            }
            this.cells = cells;
        },

        makeCell(year, month, day, inMonth, maxVisible) {
            // Normalise month overflow
            let y = year, m = month;
            if (m < 1)  { m = 12; y--; }
            if (m > 12) { m = 1;  y++; }
            const dateStr = `${y}-${String(m).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
            const allEvts  = this.eventsForDate(dateStr);
            const visible  = allEvts.slice(0, maxVisible);
            return {
                key:           dateStr,
                day,
                inMonth,
                isToday:       dateStr === this.today,
                isSelected:    dateStr === this.selectedDay,
                visibleEvents: visible,
                moreCount:     Math.max(0, allEvts.length - maxVisible),
            };
        },

        eventsForDate(dateStr) {
            const all = (this.grouped[dateStr] || []);
            if (this.filter === 'all') return all;
            return all.filter(e => e.type === this.filter);
        },

        selectDay(cell) {
            if (!cell.inMonth) return;
            this.selectedDay = cell.key;
            const d = new Date(cell.key + 'T00:00:00');
            this.selectedDayLabel = d.toLocaleDateString('default', { weekday: 'long', month: 'long', day: 'numeric' });
            this.selectedDayEvents = this.eventsForDate(cell.key);
            // Re-build cells to reflect selection
            this.buildCells();
        },

        pillClass(evt) {
            const map = {
                red:    'bg-red-100 text-red-700',
                orange: 'bg-orange-100 text-orange-700',
                yellow: 'bg-yellow-100 text-yellow-700',
                purple: 'bg-purple-100 text-purple-700',
                blue:   'bg-blue-100 text-blue-700',
                gray:   'bg-gray-100 text-gray-400 line-through',
            };
            return map[evt.color] || 'bg-gray-100 text-gray-500';
        },

        dotClass(evt) {
            const map = {
                red:    'bg-red-500',
                orange: 'bg-orange-400',
                yellow: 'bg-yellow-400',
                purple: 'bg-purple-500',
                blue:   'bg-blue-400',
                gray:   'bg-gray-300',
            };
            return map[evt.color] || 'bg-gray-300';
        },

        labelClass(evt) {
            if (evt.type === 'deal') return 'bg-orange-100 text-orange-700';
            if (evt.type === 'task') return 'bg-purple-100 text-purple-700';
            return 'bg-gray-100 text-gray-500';
        },
    };
}
</script>
@endpush
@endsection
