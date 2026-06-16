@extends('layouts.partner')
@section('title', 'Calendar')
@section('nav') @include('partner._nav') @endsection

@section('content')
<div x-data="partnerCalendar()" x-init="init()" class="flex flex-col h-full -mx-4 sm:-mx-6 -mt-4">

    {{-- Toolbar --}}
    <div class="flex items-center justify-between gap-3 px-4 sm:px-6 py-3 bg-white border-b border-gray-200 shrink-0 flex-wrap gap-y-2">
        <div class="flex items-center gap-2">
            <button type="button" @click="prevMonth()" :disabled="loading"
                    class="w-8 h-8 rounded-full border border-gray-300 flex items-center justify-center text-gray-600 hover:bg-gray-50 transition-colors disabled:opacity-40">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <button type="button" @click="nextMonth()" :disabled="loading"
                    class="w-8 h-8 rounded-full border border-gray-300 flex items-center justify-center text-gray-600 hover:bg-gray-50 transition-colors disabled:opacity-40">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
            <button type="button" @click="goToToday()" :disabled="loading"
                    class="px-4 py-1.5 rounded-full border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors disabled:opacity-40">
                Today
            </button>
            <h1 class="text-lg font-semibold text-gray-800 ml-1" x-text="monthLabel"></h1>
            <svg x-show="loading" class="w-4 h-4 animate-spin text-gray-400 ml-1" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
        </div>
        <span class="text-xs text-gray-400 flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full bg-orange-400 inline-block"></span>Deal expiry dates from your associated deals
        </span>
    </div>

    {{-- Error --}}
    <div x-show="fetchError" class="mx-4 mt-3 flex items-center gap-3 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700 shrink-0">
        <span>Couldn't load events.</span>
        <button type="button" @click="fetchEvents()" class="underline font-semibold ml-1">Retry</button>
    </div>

    {{-- Calendar --}}
    <div class="flex flex-1 min-h-0 overflow-hidden">
        <div class="flex-1 flex flex-col overflow-hidden">

            {{-- Day headers --}}
            <div class="bg-white border-b border-gray-200 shrink-0"
                 style="display:grid; grid-template-columns:repeat(7,1fr)">
                @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d)
                <div class="py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ $d }}</div>
                @endforeach
            </div>

            {{-- Grid --}}
            <div class="flex-1 overflow-y-auto bg-white"
                 style="display:grid; grid-template-columns:repeat(7,1fr); grid-auto-rows:minmax(90px,1fr); align-content:start">
                <template x-for="cell in cells" :key="cell.key">
                    <div @click="selectDay(cell)"
                         :class="{ 'bg-gray-50/60': !cell.inMonth, 'cursor-pointer hover:bg-orange-50/30': true }"
                         class="border-r border-b border-gray-200"
                         style="min-height:90px">
                        <div class="flex justify-end px-1.5 pt-1 pb-0.5">
                            <span :class="{
                                'w-7 h-7 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold': cell.isToday,
                                'text-xs font-medium text-gray-800': cell.inMonth && !cell.isToday,
                                'text-xs text-gray-300': !cell.inMonth,
                            }" x-text="cell.day"></span>
                        </div>
                        <div class="px-1 pb-1 space-y-0.5">
                            <template x-for="evt in cell.visibleEvents" :key="evt.id">
                                <button type="button" @click.stop="selectDay(cell)"
                                        :title="evt.title"
                                        :class="pillClass(evt)"
                                        class="flex items-center gap-1 px-1.5 py-0.5 rounded text-[11px] font-medium leading-snug truncate w-full text-left hover:opacity-80 transition-opacity">
                                    <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="dotClass(evt)"></span>
                                    <span class="truncate" x-text="evt.title"></span>
                                </button>
                            </template>
                            <button type="button" x-show="cell.moreCount > 0" @click.stop="selectDay(cell)"
                                    class="text-[11px] text-orange-600 font-medium px-1.5 w-full text-left"
                                    x-text="'+' + cell.moreCount + ' more'"></button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Day detail panel --}}
        <div x-show="selectedDay" x-cloak
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 translate-x-2"
             x-transition:enter-end="opacity-100 translate-x-0"
             class="w-64 xl:w-72 border-l border-gray-200 bg-white flex flex-col shrink-0 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                <div>
                    <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide" x-text="selectedDayWeekday"></p>
                    <p class="text-2xl font-bold text-gray-800 leading-none mt-0.5" x-text="selectedDayNum"
                       :class="selectedDayIsToday ? 'text-blue-600' : ''"></p>
                </div>
                <button type="button" @click="selectedDay = null"
                        class="w-7 h-7 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 hover:bg-gray-200">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div x-show="selectedDayEvents.length === 0" class="flex-1 flex flex-col items-center justify-center px-4 py-10 text-center">
                <p class="text-sm text-gray-400">Nothing scheduled</p>
            </div>
            <div class="flex-1 overflow-y-auto">
                <template x-for="evt in selectedDayEvents" :key="evt.id">
                    <a :href="evt.url" class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 transition-colors border-b border-gray-50 group">
                        <div class="w-3 h-3 rounded-full mt-1 shrink-0" :class="dotClass(evt)"></div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-gray-800 group-hover:text-orange-600 leading-snug" x-text="evt.title"></p>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full bg-orange-100 text-orange-700" x-text="evt.label"></span>
                                <span class="text-[10px] text-gray-400" x-text="evt.days_left + 'd left'"></span>
                            </div>
                        </div>
                        <svg class="w-3 h-3 text-gray-300 group-hover:text-orange-400 shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </template>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function partnerCalendar() {
    return {
        today: '', current: null, events: [], grouped: {}, cells: [], loading: false, fetchError: false,
        selectedDay: null, selectedDayNum: '', selectedDayWeekday: '', selectedDayIsToday: false, selectedDayEvents: [],

        get monthLabel() {
            if (!this.current) return '';
            return new Date(this.current.year, this.current.month - 1, 1).toLocaleString('default', { month: 'long', year: 'numeric' });
        },

        init() {
            const now = new Date();
            this.today   = this.fmt(now);
            this.current = { year: now.getFullYear(), month: now.getMonth() + 1 };
            this.fetchEvents();
        },

        fmt(d) { return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`; },

        prevMonth() { if (this.loading) return; let {year, month} = this.current; if (--month < 1) { month=12; year--; } this.current={year,month}; this.selectedDay=null; this.fetchEvents(); },
        nextMonth() { if (this.loading) return; let {year, month} = this.current; if (++month > 12) { month=1; year++; } this.current={year,month}; this.selectedDay=null; this.fetchEvents(); },
        goToToday()  { if (this.loading) return; const n=new Date(); this.current={year:n.getFullYear(),month:n.getMonth()+1}; this.selectedDay=null; this.fetchEvents(); },

        async fetchEvents() {
            this.loading=true; this.fetchError=false;
            const {year, month} = this.current;
            const from = `${year}-${String(month).padStart(2,'0')}-01`;
            const last = new Date(year, month, 0).getDate();
            const to   = `${year}-${String(month).padStart(2,'0')}-${String(last).padStart(2,'0')}`;
            try {
                const res = await fetch(`/partner/calendar/events?from=${from}&to=${to}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin',
                });
                if (!res.ok) throw new Error(res.status);
                const d = await res.json();
                this.events = d.events || []; this.grouped = d.grouped || {};
            } catch { this.fetchError=true; this.events=[]; this.grouped={}; }
            finally { this.loading=false; this.$nextTick(() => this.buildCells()); }
        },

        buildCells() {
            const {year, month} = this.current;
            const firstDow = new Date(year, month-1, 1).getDay();
            const daysInMonth = new Date(year, month, 0).getDate();
            const prevTotal   = new Date(year, month-1, 0).getDate();
            const MAX = 3; const cells = [];
            for (let i=firstDow-1; i>=0; i--) cells.push(this.mkCell(year, month-1, prevTotal-i, false, MAX));
            for (let d=1; d<=daysInMonth; d++) cells.push(this.mkCell(year, month, d, true, MAX));
            let t=1; while (cells.length % 7 !== 0 || cells.length < 35) cells.push(this.mkCell(year, month+1, t++, false, MAX));
            this.cells = cells;
        },

        mkCell(year, month, day, inMonth, max) {
            let y=year, m=month;
            if (m<1){m=12;y--;} if (m>12){m=1;y++;}
            const key = `${y}-${String(m).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
            const all = this.grouped[key] || [];
            return { key, day, inMonth, isToday: key===this.today, visibleEvents: all.slice(0,max), moreCount: Math.max(0, all.length-max) };
        },

        selectDay(cell) {
            if (!cell.inMonth) return;
            this.selectedDay = cell.key;
            const d = new Date(cell.key + 'T12:00:00');
            this.selectedDayNum = d.getDate();
            this.selectedDayWeekday = d.toLocaleString('default', { weekday: 'long' });
            this.selectedDayIsToday = cell.key === this.today;
            this.selectedDayEvents = this.grouped[cell.key] || [];
            this.buildCells();
        },

        pillClass(evt) {
            const m = { red:'bg-red-100 text-red-700', orange:'bg-orange-100 text-orange-700', yellow:'bg-yellow-100 text-yellow-800', gray:'bg-gray-100 text-gray-400' };
            return m[evt.color] || 'bg-orange-100 text-orange-700';
        },
        dotClass(evt) {
            const m = { red:'bg-red-500', orange:'bg-orange-400', yellow:'bg-yellow-400', gray:'bg-gray-300' };
            return m[evt.color] || 'bg-orange-400';
        },
    };
}
</script>
@endpush
