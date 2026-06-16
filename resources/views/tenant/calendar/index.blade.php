@extends('layouts.app')
@section('title', 'Calendar')
@section('nav') @include('tenant._nav') @endsection

@section('content')
<div x-data="rbCalendar('{{ $tenantId }}')" x-init="init()" class="flex flex-col h-full -mx-4 sm:-mx-6 lg:-mx-8 -mt-4">

    {{-- ── Toolbar ──────────────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between gap-3 px-4 sm:px-6 py-3 bg-white border-b border-gray-200 shrink-0 flex-wrap gap-y-2">

        <div class="flex items-center gap-2">
            {{-- Prev/Today/Next --}}
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

        {{-- Filter chips --}}
        <div class="flex items-center gap-2">
            <button type="button" @click="setFilter('all')"
                    :class="filter==='all' ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
                    class="px-3 py-1 rounded-full border text-xs font-medium transition-all">All</button>
            <button type="button" @click="setFilter('task')"
                    :class="filter==='task' ? 'bg-purple-600 text-white border-purple-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
                    class="inline-flex items-center gap-1 px-3 py-1 rounded-full border text-xs font-medium transition-all">
                <span class="w-2 h-2 rounded-full bg-current opacity-70"></span>Tasks
            </button>
            <button type="button" @click="setFilter('deal')"
                    :class="filter==='deal' ? 'bg-orange-500 text-white border-orange-500' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
                    class="inline-flex items-center gap-1 px-3 py-1 rounded-full border text-xs font-medium transition-all">
                <span class="w-2 h-2 rounded-full bg-orange-400"></span>Deal Expiry
            </button>
        </div>
    </div>

    {{-- ── Error banner ─────────────────────────────────────────────────────── --}}
    <div x-show="fetchError" class="mx-4 sm:mx-6 mt-3 flex items-center gap-3 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700 shrink-0">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>Couldn't load events.</span>
        <button type="button" @click="fetchEvents()" class="underline font-semibold ml-1">Retry</button>
    </div>

    {{-- ── Calendar + Detail panel ──────────────────────────────────────────── --}}
    <div class="flex flex-1 min-h-0 overflow-hidden">

        {{-- Month grid --}}
        <div class="flex-1 flex flex-col overflow-hidden">

            {{-- Day-of-week header --}}
            <div class="bg-white border-b border-gray-200 shrink-0"
                 style="display:grid; grid-template-columns:repeat(7,1fr)">
                @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d)
                <div class="py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ $d }}</div>
                @endforeach
            </div>

            {{-- Grid cells --}}
            <div class="flex-1 overflow-y-auto bg-white"
                 style="display:grid; grid-template-columns:repeat(7,1fr); grid-auto-rows:minmax(90px,1fr); align-content:start">
                <template x-for="cell in cells" :key="cell.key">
                    <div @click="selectDay(cell)"
                         :class="{
                             'bg-gray-50/60': !cell.inMonth,
                             'cursor-pointer': true,
                         }"
                         class="border-r border-b border-gray-200 relative group transition-colors hover:bg-blue-50/30"
                         style="min-height:90px">

                        {{-- Date number --}}
                        <div class="flex justify-end px-1.5 pt-1 pb-0.5">
                            <span :class="{
                                      'w-7 h-7 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold': cell.isToday,
                                      'text-xs font-medium text-gray-800': cell.inMonth && !cell.isToday,
                                      'text-xs text-gray-300': !cell.inMonth,
                                  }" x-text="cell.day"></span>
                        </div>

                        {{-- Event pills — click opens day panel; navigate from panel --}}
                        <div class="px-1 pb-1 space-y-0.5">
                            <template x-for="evt in cell.visibleEvents" :key="evt.id">
                                <button type="button" @click.stop="selectDay(cell)"
                                        :class="pillClass(evt)"
                                        :title="evt.title"
                                        class="flex items-center gap-1 px-1.5 py-0.5 rounded text-[11px] font-medium leading-snug truncate w-full text-left hover:opacity-80 transition-opacity">
                                    <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="dotClass(evt)"></span>
                                    <span class="truncate" x-text="evt.title"></span>
                                </button>
                            </template>
                            <button type="button" x-show="cell.moreCount > 0"
                                    @click.stop="selectDay(cell)"
                                    class="text-[11px] text-blue-600 hover:text-blue-800 font-medium px-1.5 w-full text-left"
                                    x-text="'+' + cell.moreCount + ' more'"></button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Day detail sidebar --}}
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
                <div class="flex items-center gap-1.5">
                    {{-- Add Task button --}}
                    <button type="button" @click="openNewTask()"
                            title="Add task on this day"
                            class="w-7 h-7 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 hover:bg-purple-200 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                    </button>
                    <button type="button" @click="selectedDay = null"
                            class="w-7 h-7 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 hover:bg-gray-200 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            {{-- Quick create task inline form --}}
            <div x-show="newTaskOpen" x-cloak class="px-3 pt-3 pb-2 border-b border-gray-100 space-y-2 bg-purple-50/50">
                <p class="text-[10px] font-semibold text-purple-600 uppercase tracking-wide">New Task — <span x-text="selectedDayLabel"></span></p>
                <input x-model="newTaskTitle" type="text" placeholder="Task title *" maxlength="200"
                       class="w-full border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs bg-white outline-none focus:border-purple-400 focus:ring-1 focus:ring-purple-100"
                       @keydown.enter="submitNewTask()" @keydown.escape="newTaskOpen=false">
                <select x-model="newTaskPriority"
                        class="w-full border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs bg-white outline-none focus:border-purple-400">
                    <option value="medium">Medium priority</option>
                    <option value="high">High priority</option>
                    <option value="urgent">Urgent</option>
                    <option value="low">Low priority</option>
                </select>
                <p x-show="newTaskError" class="text-[10px] text-red-500" x-text="newTaskError"></p>
                <div class="flex gap-1.5">
                    <button type="button" @click="newTaskOpen = false; newTaskError = ''"
                            class="flex-1 py-1.5 rounded-lg border border-gray-200 text-xs font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="button" @click="submitNewTask()" :disabled="newTaskSaving"
                            class="flex-1 py-1.5 rounded-lg text-xs font-semibold text-white transition-all disabled:opacity-60"
                            style="background:linear-gradient(135deg,#7B61FF,#5b4cdb)"
                            x-text="newTaskSaving ? 'Adding…' : 'Add Task'">
                    </button>
                </div>
            </div>

            <div x-show="selectedDayEvents.length === 0 && !newTaskOpen" class="flex-1 flex flex-col items-center justify-center px-4 py-10 text-center">
                <svg class="w-8 h-8 text-gray-200 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <p class="text-sm text-gray-400 mb-2">Nothing scheduled</p>
                <button type="button" @click="openNewTask()"
                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-semibold text-purple-600 bg-purple-50 hover:bg-purple-100 transition-colors">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                    Add a task
                </button>
            </div>

            <div class="flex-1 overflow-y-auto">
                <template x-for="evt in selectedDayEvents" :key="evt.id">
                    <a :href="evt.url"
                       class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 transition-colors border-b border-gray-50 group">
                        <div class="w-3 h-3 rounded-full mt-1 shrink-0" :class="dotClass(evt)"></div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-gray-800 group-hover:text-blue-600 transition-colors leading-snug"
                               :class="{ 'line-through text-gray-400': evt.done }"
                               x-text="evt.title"></p>
                            <div class="flex items-center gap-2 mt-1 flex-wrap">
                                <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full" :class="labelClass(evt)" x-text="evt.label"></span>
                                <span x-show="evt.type==='deal' && evt.days_left !== undefined"
                                      class="text-[10px] text-gray-400"
                                      x-text="evt.days_left + 'd left'"></span>
                                <span x-show="evt.type==='task' && !evt.done"
                                      class="text-[10px] text-gray-400 capitalize" x-text="evt.priority"></span>
                            </div>
                            <p x-show="evt.referrer" class="text-[10px] text-gray-400 mt-0.5 truncate" x-text="evt.referrer"></p>
                        </div>
                        <svg class="w-3 h-3 text-gray-300 group-hover:text-blue-400 shrink-0 mt-1 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </template>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function rbCalendar(tenantId) {
    return {
        tenantId,
        today:      '',
        current:    null,
        events:     [],
        grouped:    {},
        cells:      [],
        loading:    false,
        fetchError: false,
        filter:     'all',

        selectedDay:        null,
        selectedDayNum:     '',
        selectedDayWeekday: '',
        selectedDayLabel:   '',
        selectedDayIsToday: false,
        selectedDayEvents:  [],

        // Quick-create task
        newTaskOpen:     false,
        newTaskTitle:    '',
        newTaskPriority: 'medium',
        newTaskSaving:   false,
        newTaskError:    '',

        get monthLabel() {
            if (!this.current) return '';
            return new Date(this.current.year, this.current.month - 1, 1)
                .toLocaleString('default', { month: 'long', year: 'numeric' });
        },

        init() {
            const now = new Date();
            this.today   = this.fmt(now);
            this.current = { year: now.getFullYear(), month: now.getMonth() + 1 };
            this.fetchEvents();
        },

        fmt(d) {
            return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
        },

        setFilter(f) {
            this.filter = f;
            this.buildCells();
            if (this.selectedDay) this.refreshPanel();
        },

        prevMonth() {
            if (this.loading) return;
            let { year, month } = this.current;
            if (--month < 1) { month = 12; year--; }
            this.current = { year, month };
            this.selectedDay = null;
            this.fetchEvents();
        },

        nextMonth() {
            if (this.loading) return;
            let { year, month } = this.current;
            if (++month > 12) { month = 1; year++; }
            this.current = { year, month };
            this.selectedDay = null;
            this.fetchEvents();
        },

        goToToday() {
            if (this.loading) return;
            const now = new Date();
            this.current = { year: now.getFullYear(), month: now.getMonth() + 1 };
            this.selectedDay = null;
            this.fetchEvents();
        },

        async fetchEvents() {
            this.loading = true; this.fetchError = false;
            const { year, month } = this.current;
            const from = `${year}-${String(month).padStart(2,'0')}-01`;
            const last = new Date(year, month, 0).getDate();
            const to   = `${year}-${String(month).padStart(2,'0')}-${String(last).padStart(2,'0')}`;
            try {
                const res = await fetch(`/tenant/${tenantId}/calendar/events?from=${from}&to=${to}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!res.ok) throw new Error(res.status);
                const d = await res.json();
                this.events  = d.events  || [];
                this.grouped = d.grouped || {};
            } catch {
                this.fetchError = true;
                this.events = []; this.grouped = {};
            } finally {
                this.loading = false;
                this.$nextTick(() => this.buildCells());
            }
        },

        buildCells() {
            const { year, month } = this.current;
            const firstDow   = new Date(year, month - 1, 1).getDay();
            const daysInMonth = new Date(year, month, 0).getDate();
            const prevTotal  = new Date(year, month - 1, 0).getDate();
            const MAX = 3;
            const cells = [];

            for (let i = firstDow - 1; i >= 0; i--)
                cells.push(this.mkCell(year, month - 1, prevTotal - i, false, MAX));
            for (let d = 1; d <= daysInMonth; d++)
                cells.push(this.mkCell(year, month, d, true, MAX));
            let t = 1;
            while (cells.length % 7 !== 0 || cells.length < 35)
                cells.push(this.mkCell(year, month + 1, t++, false, MAX));

            this.cells = cells;
        },

        mkCell(year, month, day, inMonth, max) {
            let y = year, m = month;
            if (m < 1)  { m = 12; y--; }
            if (m > 12) { m = 1;  y++; }
            const key  = `${y}-${String(m).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
            const all  = this.eventsForDate(key);
            return {
                key, day, inMonth,
                isToday:       key === this.today,
                isSelected:    key === this.selectedDay,
                visibleEvents: all.slice(0, max),
                moreCount:     Math.max(0, all.length - max),
            };
        },

        eventsForDate(key) {
            const all = this.grouped[key] || [];
            return this.filter === 'all' ? all : all.filter(e => e.type === this.filter);
        },

        selectDay(cell) {
            if (!cell.inMonth) return;
            this.selectedDay = cell.key;
            const d = new Date(cell.key + 'T12:00:00');
            this.selectedDayNum     = d.getDate();
            this.selectedDayWeekday = d.toLocaleString('default', { weekday: 'long' });
            this.selectedDayLabel   = d.toLocaleDateString('default', { month: 'short', day: 'numeric', year: 'numeric' });
            this.selectedDayIsToday = cell.key === this.today;
            this.newTaskOpen        = false;
            this.newTaskTitle       = '';
            this.newTaskError       = '';
            this.refreshPanel();
            this.buildCells();
        },

        refreshPanel() {
            this.selectedDayEvents = this.eventsForDate(this.selectedDay);
        },

        openNewTask() {
            this.newTaskOpen  = true;
            this.newTaskTitle = '';
            this.newTaskError = '';
            this.$nextTick(() => this.$el.querySelector('input[x-model="newTaskTitle"]')?.focus());
        },

        async submitNewTask() {
            if (!this.newTaskTitle.trim()) { this.newTaskError = 'Title is required.'; return; }
            this.newTaskSaving = true; this.newTaskError = '';
            const csrf = document.querySelector('meta[name=csrf-token]')?.content ?? '';
            try {
                const res = await fetch(`/tenant/${tenantId}/tasks`, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest' },
                    body: JSON.stringify({
                        title:        this.newTaskTitle.trim(),
                        priority:     this.newTaskPriority,
                        due_at:       this.selectedDay,
                        assignee_ids: ['me'],  // 'me' triggers self-assign in TaskController
                    }),
                });
                if (!res.ok) {
                    const d = await res.json().catch(() => ({}));
                    this.newTaskError = d.message || d.error || 'Could not create task.';
                    return;
                }
                this.newTaskOpen  = false;
                this.newTaskTitle = '';
                // Re-fetch events so the new task appears on the calendar
                await this.fetchEvents();
                this.$dispatch('show-toast', { type: 'success', message: 'Task added to calendar.' });
            } catch {
                this.newTaskError = 'Network error. Please try again.';
            } finally { this.newTaskSaving = false; }
        },

        // ── Styling helpers ────────────────────────────────────────────────────
        pillClass(evt) {
            if (evt.done) return 'bg-gray-100 text-gray-400';
            const m = { red:'bg-red-100 text-red-700', orange:'bg-orange-100 text-orange-700', yellow:'bg-yellow-100 text-yellow-800', purple:'bg-purple-100 text-purple-700', blue:'bg-blue-100 text-blue-700', gray:'bg-gray-100 text-gray-400' };
            return m[evt.color] || 'bg-gray-100 text-gray-500';
        },
        dotClass(evt) {
            const m = { red:'bg-red-500', orange:'bg-orange-400', yellow:'bg-yellow-400', purple:'bg-purple-500', blue:'bg-blue-500', gray:'bg-gray-300' };
            return m[evt.color] || 'bg-gray-300';
        },
        labelClass(evt) {
            return evt.type === 'deal' ? 'bg-orange-100 text-orange-700' : 'bg-purple-100 text-purple-700';
        },
    };
}
</script>
@endpush
