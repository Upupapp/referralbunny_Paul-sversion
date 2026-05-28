@extends('layouts.app')
@section('title', 'Tasks')
@section('nav') @include('tenant._nav') @endsection

@section('content')
{{-- Kanban / Responses data in a script block avoids large JSON in HTML attributes (Alpine x-data parsing issues) --}}
<script>
    window.__rbKanban = @json($kanbanColumns);
</script>
<div class="space-y-4"
     x-data="tasksPage(
         '{{ $tenant->id }}',
         '{{ $actorId }}',
         {{ json_encode($actorName, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) }},
         '{{ $view }}',
         {{ $completionEmailEnabled ? 'true' : 'false' }},
         {{ $isAdmin ? 'true' : 'false' }}
     )"
     x-init="init()">

    {{-- ── PAGE HEADER ──────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[#1E1B4B]">Tasks</h1>
            <p class="text-sm text-gray-400 mt-0.5">Manage requests, assignments, and action items.</p>
        </div>
        <div class="flex items-center gap-2.5 flex-wrap">

            {{-- View Switcher (hidden on Responses tab) --}}
            <div x-show="{{ $tab !== 'responses' ? 'true' : 'false' }}"
                 class="flex items-center bg-gray-100 rounded-xl p-1 gap-0.5"
                 role="group" aria-label="View options">
                <button @click="switchView('list')"
                        :aria-pressed="view === 'list' ? 'true' : 'false'"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all"
                        :class="view === 'list' ? 'bg-white shadow-sm text-[#1E1B4B]' : 'text-gray-500 hover:text-gray-700'">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                    </svg>
                    List
                </button>
                <button @click="switchView('kanban')"
                        :aria-pressed="view === 'kanban' ? 'true' : 'false'"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all"
                        :class="view === 'kanban' ? 'bg-white shadow-sm text-[#1E1B4B]' : 'text-gray-500 hover:text-gray-700'">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                    </svg>
                    Kanban
                </button>
            </div>

            {{-- Create Task button --}}
            @if($isAdmin)
            <button @click="createOpen = true; if(assignees.length === 0) fetchAssignees()"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white transition-all"
                    style="background:linear-gradient(135deg,#7B61FF,#5b4cdb);box-shadow:0 4px 14px rgba(123,97,255,.25)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                New Task
            </button>
            @endif
        </div>
    </div>

    {{-- ── TABS ─────────────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex gap-1.5 flex-wrap">
            @foreach([
                ['mine',      'My Tasks'],
                ['all',       'All Tasks'],
                ['overdue',   'Overdue'],
                ['completed', 'Completed'],
                ['responses', 'View Responses'],
            ] as [$key, $label])
            <a href="{{ request()->url() }}?tab={{ $key }}&view={{ $view }}"
               class="px-3.5 py-1.5 text-xs font-semibold rounded-full transition-all no-underline"
               style="{{ $tab === $key
                    ? 'background:#7B61FF;color:white;box-shadow:0 4px 12px rgba(123,97,255,.3)'
                    : 'background:white;color:#9ca3af;border:1.5px solid #e5e7eb' }}">
                {{ $label }}
            </a>
            @endforeach
        </div>

    </div>

    {{-- ── FILTERS (All Tasks tab only, admin only) ────────────────────────── --}}
    @if($isAdmin && $tab === 'all' && $tab !== 'responses')
    <form method="GET" action="{{ request()->url() }}" class="flex flex-wrap items-center gap-2"
          x-data="{}"
          @submit.prevent="
            const f = $el;
            // Remove empty inputs before submit so URL stays clean
            [...f.querySelectorAll('input,select')].forEach(el => { if (el.name && el.value === '') el.disabled = true; });
            f.submit();
          ">
        <input type="hidden" name="tab" value="all">
        <input type="hidden" name="view" value="{{ $view }}">

        {{-- Search --}}
        <div class="relative flex items-center">
            <svg class="absolute left-3 w-3.5 h-3.5 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" name="q" value="{{ $search }}" placeholder="Search tasks…"
                   class="pl-8 pr-3 py-1.5 text-xs rounded-xl border border-gray-200 bg-white text-gray-700 outline-none focus:border-purple-300 w-44"
                   aria-label="Search tasks by title">
        </div>

        {{-- Assignee --}}
        @if(!empty($assigneeOptions))
        <select name="assignee" onchange="this.form.submit()" class="text-xs rounded-xl border border-gray-200 bg-white text-gray-700 px-3 py-1.5 outline-none cursor-pointer" aria-label="Filter by assignee">
            <option value="all">All Assignees</option>
            @foreach($assigneeOptions as $u)
            <option value="{{ $u->id }}" {{ $assigneeFilter === $u->id ? 'selected' : '' }}>{{ trim($u->name) ?: $u->id }}</option>
            @endforeach
        </select>
        @endif

        {{-- Priority --}}
        <select name="priority" onchange="this.form.submit()" class="text-xs rounded-xl border border-gray-200 bg-white text-gray-700 px-3 py-1.5 outline-none cursor-pointer" aria-label="Filter by priority">
            <option value="">All Priorities</option>
            @foreach(['urgent' => 'Urgent','high' => 'High','medium' => 'Medium','low' => 'Low'] as $pv => $pl)
            <option value="{{ $pv }}" {{ $priority === $pv ? 'selected' : '' }}>{{ $pl }}</option>
            @endforeach
        </select>

        {{-- Status --}}
        <select name="status" onchange="this.form.submit()" class="text-xs rounded-xl border border-gray-200 bg-white text-gray-700 px-3 py-1.5 outline-none cursor-pointer" aria-label="Filter by status">
            <option value="">All Statuses</option>
            <option value="open"        {{ $status === 'open'        ? 'selected' : '' }}>Open</option>
            <option value="in_progress" {{ $status === 'in_progress' ? 'selected' : '' }}>In Progress / Waiting</option>
            <option value="completed"   {{ $status === 'completed'   ? 'selected' : '' }}>Completed</option>
        </select>

        {{-- Date filter --}}
        <select name="date" onchange="this.form.submit()" class="text-xs rounded-xl border border-gray-200 bg-white text-gray-700 px-3 py-1.5 outline-none cursor-pointer" aria-label="Filter by due date">
            <option value="">All Dates</option>
            <option value="overdue" {{ $dateFilter === 'overdue' ? 'selected' : '' }}>Overdue</option>
            <option value="today"   {{ $dateFilter === 'today'   ? 'selected' : '' }}>Due Today</option>
            <option value="week"    {{ $dateFilter === 'week'    ? 'selected' : '' }}>Due This Week</option>
        </select>

        {{-- Sort --}}
        <select name="sort" onchange="this.form.submit()" class="text-xs rounded-xl border border-gray-200 bg-white text-gray-700 px-3 py-1.5 outline-none cursor-pointer" aria-label="Sort tasks">
            <option value="">Default Sort</option>
            <option value="priority_asc" {{ ($sort ?? '') === 'priority_asc' ? 'selected' : '' }}>Priority ↑</option>
            <option value="due_asc"      {{ ($sort ?? '') === 'due_asc'      ? 'selected' : '' }}>Due Date ↑</option>
            <option value="created_desc" {{ ($sort ?? '') === 'created_desc' ? 'selected' : '' }}>Newest First</option>
        </select>

        {{-- Apply + Clear --}}
        <button type="submit" class="px-3 py-1.5 rounded-xl text-xs font-semibold text-white transition-all" style="background:#7B61FF">Apply</button>
        @if($search || $priority || $status || $dateFilter || ($assigneeFilter && $assigneeFilter !== 'all') || $sort)
        <a href="{{ request()->url() }}?tab=all&view={{ $view }}" class="px-3 py-1.5 rounded-xl text-xs font-semibold text-gray-500 bg-gray-100 hover:bg-gray-200 transition-all no-underline">Clear</a>
        @endif
    </form>
    @endif

    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    {{-- VIEW RESPONSES TAB — Request Form Submissions Kanban                   --}}
    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    @if($tab === 'responses')
    <div>
        <p class="text-xs text-gray-400 mb-4">
            Request form submissions grouped by processing status.
            @if(!$isAdmin) Only submissions with tasks assigned to you are shown. @endif
        </p>

        {{-- Responses Kanban --}}
        <div class="flex gap-4 overflow-x-auto pb-4 items-start" style="min-height:360px; scrollbar-width:thin">
            @foreach($responsesColumns as $rcol)
            @php
                $rDotColor = match($rcol['key']) {
                    'new'       => '#7B61FF',
                    'processing'=> '#2563eb',
                    'completed' => '#16a34a',
                    default     => '#9ca3af',
                };
                $rColBg = match($rcol['key']) {
                    'new'       => '#fafafa',
                    'processing'=> '#eff6ff',
                    'completed' => '#f0fdf4',
                    default     => '#f9fafb',
                };
            @endphp
            <div class="flex flex-col gap-3 flex-shrink-0" style="min-width:280px; max-width:320px; width:100%">

                {{-- Column header --}}
                <div class="flex items-center gap-2 px-1">
                    <span class="w-2.5 h-2.5 rounded-full shrink-0"
                          style="background:{{ $rDotColor }}" aria-hidden="true"></span>
                    <span class="text-sm font-bold text-[#1E1B4B]">{{ $rcol['label'] }}</span>
                    <span class="text-xs font-semibold text-gray-400 bg-gray-100 rounded-full px-2 py-0.5">{{ $rcol['count'] }}</span>
                </div>

                {{-- Cards --}}
                <div class="flex flex-col gap-2.5 rounded-2xl p-2 min-h-[80px]"
                     style="background:{{ $rColBg }}">

                    @if(count($rcol['items']) === 0)
                    <div class="flex flex-col items-center justify-center py-10 text-center">
                        <svg class="w-8 h-8 text-gray-200 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        <p class="text-xs text-gray-400 font-medium">No {{ strtolower($rcol['label']) }}</p>
                    </div>
                    @else
                    @foreach($rcol['items'] as $item)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3.5 hover:shadow-md transition-all">
                        {{-- Form title --}}
                        <p class="text-xs font-bold text-[#7B61FF] mb-1 truncate">{{ $item['form_title'] }}</p>

                        {{-- Submission title / submitter --}}
                        <p class="text-sm font-semibold text-[#1E1B4B] leading-snug mb-2">
                            {{ $item['submitter_name'] }}
                        </p>
                        @if($item['submitter_email'])
                        <p class="text-[10px] text-gray-400 truncate mb-2">{{ $item['submitter_email'] }}</p>
                        @endif

                        {{-- Task badge --}}
                        @if($item['has_task'])
                        @php
                            $taskBadgeStyle = match($item['task_status'] ?? '') {
                                'completed'   => 'background:#dcfce7;color:#15803d',
                                'in_progress' => 'background:#dbeafe;color:#2563eb',
                                'waiting'     => 'background:#fff7ed;color:#ea580c',
                                default       => 'background:#ede9fe;color:#7B61FF',
                            };
                        @endphp
                        <div class="flex items-center gap-1.5 mb-2.5">
                            <span class="text-[9px] font-bold px-2 py-0.5 rounded-full"
                                  style="{{ $taskBadgeStyle }}">
                                Task: {{ ucwords(str_replace('_', ' ', $item['task_status'] ?? 'open')) }}
                            </span>
                        </div>
                        @else
                        <div class="mb-2.5">
                            <span class="text-[9px] font-bold px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">No task yet</span>
                        </div>
                        @endif

                        {{-- Footer --}}
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] text-gray-400">{{ $item['submitted_ago'] }}</span>
                            @if($item['task_url'])
                            <a href="{{ $item['task_url'] }}"
                               class="text-[10px] font-semibold text-[#7B61FF] hover:text-purple-800 transition-colors">
                                View Task →
                            </a>
                            @endif
                        </div>
                    </div>
                    @endforeach
                    @endif

                </div>
            </div>
            @endforeach
        </div>
    </div>
    @else

    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    {{-- LIST VIEW                                                               --}}
    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    @if($view === 'list')
    <div>
        <div class="card" style="padding:0;overflow:hidden">
            @if($tasks->isEmpty())
            <div class="flex flex-col items-center justify-center py-16 text-center px-6">
                <svg class="w-10 h-10 text-gray-200 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm font-semibold text-[#1E1B4B] mb-1">No tasks here</p>
                <p class="text-xs text-gray-400">
                    @if($tab === 'mine') Tasks assigned to you will appear here.
                    @elseif($tab === 'overdue') No overdue tasks. Great work!
                    @else No tasks found for the selected filter.
                    @endif
                </p>
                @if($isAdmin)
                <button @click="createOpen = true; if(assignees.length === 0) fetchAssignees()"
                        class="mt-4 px-4 py-2 rounded-xl text-xs font-semibold text-[#7B61FF] bg-purple-50 hover:bg-purple-100 transition-colors">
                    + Create your first task
                </button>
                @endif
            </div>
            @else
            @foreach($tasks as $task)
            @php
                $priorityStyle = match($task->priority) {
                    'urgent' => 'background:#fef2f2;color:#dc2626',
                    'high'   => 'background:#fff7ed;color:#ea580c',
                    'medium' => 'background:#fffbeb;color:#d97706',
                    default  => 'background:#f3f4f6;color:#6b7280',
                };
                $priorityDot = match($task->priority) {
                    'urgent' => '#dc2626', 'high' => '#ea580c',
                    'medium' => '#d97706', default => '#9ca3af',
                };
                $statusStyle = match($task->status) {
                    'completed'   => 'background:#dcfce7;color:#15803d',
                    'in_progress' => 'background:#dbeafe;color:#2563eb',
                    'waiting'     => 'background:#fff7ed;color:#ea580c',
                    'cancelled'   => 'background:#fee2e2;color:#dc2626',
                    default       => 'background:#ede9fe;color:#7B61FF',
                };
                $displayStatus = $task->isOverdue() ? 'Overdue' : str_replace('_', ' ', ucfirst($task->status));
            @endphp
            <a href="{{ route('tenant.tasks.show', [$tenant->id, $task->id]) }}"
               class="flex items-start gap-3.5 px-4 py-3.5 border-b border-gray-50 hover:bg-gray-50/80 transition-colors no-underline block">
                <div class="w-2 h-2 rounded-full shrink-0 mt-1.5" style="background:{{ $priorityDot }}" aria-hidden="true"></div>
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-start gap-2 mb-1">
                        <p class="text-sm font-semibold text-[#1E1B4B] truncate">{{ $task->title }}</p>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full shrink-0" style="{{ $statusStyle }}">{{ $displayStatus }}</span>
                        @if($task->category === 'request_form')
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 shrink-0">Request Form</span>
                        @elseif($task->category === 'manual')
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-purple-50 text-[#7B61FF] shrink-0">Manual</span>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-center gap-3 text-xs text-gray-400">
                        @if($task->requestor_name)<span>From: {{ $task->requestor_name }}</span>@endif
                        @if($task->due_at)
                        <span class="{{ $task->isOverdue() ? 'text-red-500 font-semibold' : '' }}">
                            Due {{ $task->due_at->format('M j, Y') }}{{ $task->isOverdue() ? ' — overdue' : '' }}
                        </span>
                        @endif
                        <span>{{ $task->created_at->diffForHumans() }}</span>
                    </div>
                </div>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full shrink-0" style="{{ $priorityStyle }}">{{ ucfirst($task->priority) }}</span>
            </a>
            @endforeach
            @if($tasks->hasPages())
            <div class="px-4 py-3 border-t border-gray-100 bg-gray-50/50">{{ $tasks->links() }}</div>
            @endif
            @endif
        </div>
    </div>
    @endif

    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    {{-- KANBAN VIEW — 3 columns: New Tasks / Processing Tasks / Completed Tasks --}}
    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    @if($view === 'kanban')
    <div>

        @if(empty($kanbanColumns))
        {{-- Kanban data not loaded — redirect via JS --}}
        <div class="card text-center py-12 text-gray-400 text-sm">
            Loading board…
            <script>
                if (!window.location.search.includes('view=kanban')) {
                    var u = new URL(window.location.href);
                    u.searchParams.set('view','kanban');
                    window.location.href = u.toString();
                }
            </script>
        </div>
        @else

        {{-- Board: always horizontal-scrolling, all columns always visible --}}
        <div class="flex gap-4 overflow-x-auto pb-4 items-start" style="min-height:480px; scrollbar-width:thin; -webkit-overflow-scrolling:touch">

            <template x-for="col in columns" :key="col.key">
                <div class="flex flex-col gap-3 flex-shrink-0"
                     style="width:300px"
                     @dragover.prevent="dragOver = col.key"
                     @dragleave.self="dragOver = null"
                     @drop.prevent="onDrop($event, col.key)">

                    {{-- Column header --}}
                    <div class="flex items-center justify-between px-1 py-0.5">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0"
                                  :style="colDotStyle(col.key)" aria-hidden="true"></span>
                            <span class="text-sm font-bold text-[#1E1B4B]" x-text="col.label"></span>
                            <span class="text-xs font-semibold text-gray-400 bg-gray-100 rounded-full px-2 py-0.5"
                                  x-text="col.count"></span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span x-show="col.tasks.filter(t=>t.is_overdue).length > 0"
                                  class="text-[10px] font-bold text-red-500 bg-red-50 rounded-full px-2 py-0.5"
                                  x-text="col.tasks.filter(t=>t.is_overdue).length + ' overdue'"
                                  aria-live="polite"></span>
                            {{-- + New Task button on first column --}}
                            <template x-if="col.key === 'new_tasks' && isAdmin">
                                <button @click="createOpen = true; if(assignees.length === 0) fetchAssignees()"
                                        class="w-6 h-6 rounded-lg bg-purple-100 text-[#7B61FF] flex items-center justify-center hover:bg-purple-200 transition-colors"
                                        title="Create new task" aria-label="Create new task">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                </button>
                            </template>
                        </div>
                    </div>

                    {{-- Drop zone --}}
                    <div class="flex flex-col gap-2.5 rounded-2xl p-2 transition-colors min-h-[100px]"
                         :class="dragOver === col.key ? 'bg-purple-50 border-2 border-dashed border-purple-300' : 'bg-gray-100/60'"
                         :style="colBgStyle(col.key)"
                         role="group"
                         :aria-label="col.label + ' column'">

                        {{-- Empty state --}}
                        <template x-if="col.tasks.length === 0">
                            <div class="flex flex-col items-center justify-center py-10 text-center">
                                <svg class="w-8 h-8 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="text-xs text-gray-400 font-medium">
                                    <span x-text="col.key === 'new_tasks' ? 'No new tasks' : col.key === 'processing' ? 'No tasks in progress' : 'No completed tasks'"></span>
                                </p>
                                <template x-if="col.key === 'new_tasks' && isAdmin">
                                    <button @click="createOpen = true; if(assignees.length === 0) fetchAssignees()"
                                            class="mt-3 text-xs font-semibold text-[#7B61FF] hover:text-purple-800 transition-colors">
                                        + Create task
                                    </button>
                                </template>
                            </div>
                        </template>

                        {{-- Task cards --}}
                        <template x-for="task in col.tasks" :key="task.id">
                            <div class="bg-white rounded-xl shadow-sm border border-gray-100 transition-all group"
                                 :class="{
                                     'opacity-40 pointer-events-none scale-95': movingTaskId === task.id,
                                     'ring-2 ring-purple-300 shadow-md opacity-70 rotate-1': dragTaskId === task.id,
                                     'hover:shadow-md hover:-translate-y-0.5': movingTaskId !== task.id,
                                 }"
                                 :draggable="task.can_update_status ? 'true' : 'false'"
                                 @dragstart="task.can_update_status && onDragStart($event, task, col.key)"
                                 @dragend="onDragEnd()"
                                 tabindex="0"
                                 :aria-label="'Task: ' + task.title + ', ' + task.priority + ' priority'">

                                {{-- Card body → task detail --}}
                                <a :href="task.url" class="block p-3.5 no-underline" @click.stop>
                                    <p class="text-sm font-semibold text-[#1E1B4B] leading-snug mb-2.5 line-clamp-2" x-text="task.title"></p>

                                    {{-- Badge row --}}
                                    <div class="flex flex-wrap gap-1.5 mb-2.5">
                                        <span class="text-[9px] font-bold px-2 py-0.5 rounded-full"
                                              :style="task.category === 'request_form'
                                                  ? 'background:#f0fdf4;color:#16a34a'
                                                  : task.category === 'manual'
                                                      ? 'background:#f5f3ff;color:#7B61FF'
                                                      : 'background:#f3f4f6;color:#6b7280'"
                                              x-text="task.category === 'request_form' ? 'Request Form' : task.category === 'manual' ? 'Manual' : 'System'"></span>

                                        <span class="text-[9px] font-bold px-2 py-0.5 rounded-full"
                                              :style="priorityStyle(task.priority)"
                                              x-text="task.priority.charAt(0).toUpperCase() + task.priority.slice(1)"></span>

                                        <span x-show="task.is_overdue"
                                              class="text-[9px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-600">Overdue</span>
                                    </div>

                                    {{-- Assignee + due --}}
                                    <div class="flex items-center justify-between gap-2 text-[10px] text-gray-400">
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            <div x-show="task.assignee_name"
                                                 class="w-5 h-5 rounded-full flex items-center justify-center text-[8px] font-bold text-white shrink-0"
                                                 style="background:linear-gradient(135deg,#7B61FF,#5b4cdb)"
                                                 :aria-label="'Assigned to ' + task.assignee_name"
                                                 x-text="task.assignee_initials"></div>
                                            <span class="truncate" x-text="task.assignee_name || 'Unassigned'"></span>
                                        </div>
                                        <span x-show="task.due_at" class="shrink-0"
                                              :class="task.is_overdue ? 'text-red-500 font-semibold' : ''"
                                              x-text="task.due_at"></span>
                                    </div>

                                    <div x-show="task.requestor_name" class="mt-1.5 text-[10px] text-gray-400 truncate">
                                        From: <span x-text="task.requestor_name"></span>
                                    </div>
                                </a>

                                {{-- Quick status footer --}}
                                <div x-show="task.can_update_status"
                                     class="border-t border-gray-50 px-3.5 py-2 flex items-center gap-1.5 flex-wrap"
                                     @click.stop>

                                    {{-- Move to New Tasks --}}
                                    <template x-if="col.key !== 'new_tasks' && task.can_update_status">
                                        <button @click="quickMove(task, col.key, 'new_tasks')"
                                                :disabled="movingTaskId === task.id"
                                                class="text-[10px] font-semibold px-2 py-1 rounded-lg bg-purple-50 text-[#7B61FF] hover:bg-purple-100 transition-colors disabled:opacity-40"
                                                title="Move to New Tasks">
                                            ← New
                                        </button>
                                    </template>

                                    {{-- Move to Processing --}}
                                    <template x-if="col.key !== 'processing' && task.can_update_status && task.status !== 'completed'">
                                        <button @click="quickMove(task, col.key, 'processing')"
                                                :disabled="movingTaskId === task.id"
                                                class="text-[10px] font-semibold px-2 py-1 rounded-lg bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors disabled:opacity-40"
                                                title="Move to Processing">
                                            → Processing
                                        </button>
                                    </template>

                                    {{-- Mark Done --}}
                                    <template x-if="task.can_complete && col.key !== 'completed'">
                                        <button @click="initiateComplete(task, col.key)"
                                                :disabled="movingTaskId === task.id"
                                                class="text-[10px] font-semibold px-2 py-1 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition-colors disabled:opacity-40"
                                                title="Mark as Done">
                                            ✓ Done
                                        </button>
                                    </template>

                                    {{-- Loading spinner --}}
                                    <template x-if="movingTaskId === task.id">
                                        <svg class="w-3.5 h-3.5 animate-spin text-[#7B61FF]" fill="none" viewBox="0 0 24 24" aria-label="Updating">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                        </svg>
                                    </template>
                                </div>
                            </div>
                        </template>

                        {{-- View All button — shown when column has more than 10 tasks --}}
                        <template x-if="col.has_more">
                            <a :href="viewAllUrl(col)"
                               class="flex items-center justify-center gap-1.5 w-full py-2 mt-1 rounded-xl text-xs font-semibold text-[#7B61FF] bg-purple-50 hover:bg-purple-100 transition-colors no-underline">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h8"/></svg>
                                View all <span x-text="col.count"></span> tasks →
                            </a>
                        </template>

                    </div>{{-- drop zone --}}
                </div>{{-- column --}}
            </template>

        </div>{{-- board --}}
        @endif
    </div>{{-- kanban view --}}
    @endif {{-- kanban view --}}

    @endif {{-- responses vs tasks tabs --}}

    {{-- ── TOASTS ───────────────────────────────────────────────────────────── --}}
    <div class="fixed bottom-5 right-5 z-[200] flex flex-col gap-2 items-end pointer-events-none"
         aria-live="polite" style="max-width:360px">
        <template x-for="t in toasts" :key="t.id">
            <div class="pointer-events-auto flex items-center gap-3 px-4 py-3.5 rounded-2xl shadow-2xl text-sm font-medium w-full"
                 role="alert"
                 :class="{'bg-emerald-600 text-white': t.type==='success', 'bg-red-600 text-white': t.type==='error', 'bg-[#1E1B4B] text-white': t.type==='info'}"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0">
                <svg x-show="t.type==='success'" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                <svg x-show="t.type==='error'"   class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                <span class="flex-1 leading-snug" x-text="t.msg"></span>
                <button @click="toasts = toasts.filter(x => x.id !== t.id)" class="shrink-0 opacity-60 hover:opacity-100" aria-label="Dismiss">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </template>
    </div>

    {{-- ── COMPLETION MODAL ─────────────────────────────────────────────────── --}}
    <template x-teleport="body">
    <div x-show="completionOpen"
         class="fixed inset-0 z-[9999] flex items-end sm:items-center justify-center p-4"
         style="background:rgba(0,0,0,.5);backdrop-filter:blur(4px)"
         @keydown.escape.window="cancelCompletion()"
         x-cloak>
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto"
             @click.stop
             role="dialog" aria-modal="true" aria-labelledby="completion-title">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div>
                    <h2 id="completion-title" class="text-base font-bold text-[#1E1B4B]">Complete Task</h2>
                    <p class="text-xs text-gray-400 mt-0.5 line-clamp-1" x-text="completionTask?.title"></p>
                </div>
                <button @click="cancelCompletion()" class="w-8 h-8 rounded-xl bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors" aria-label="Close">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-5 space-y-4">

                {{-- ── TASK DONE SUCCESS SCREEN ─────────────────────────────────── --}}
                <div x-show="completionDone" class="text-center py-6 space-y-5">
                    <div class="w-20 h-20 mx-auto rounded-full bg-emerald-100 flex items-center justify-center ring-4 ring-emerald-50">
                        <svg class="w-10 h-10 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <div class="space-y-1.5">
                        <h3 class="text-xl font-bold text-[#1E1B4B]">Task Done!</h3>
                        <p class="text-sm font-medium text-gray-600 line-clamp-2" x-text="completionTask?.title"></p>
                        <p class="text-xs text-emerald-600" x-text="completionSuccess || 'Task marked as completed.'"></p>
                    </div>
                    <button @click="cancelCompletion()"
                            class="inline-flex items-center gap-2 px-7 py-2.5 rounded-xl text-sm font-semibold text-white transition-all hover:opacity-90 active:scale-[0.98]"
                            style="background:linear-gradient(135deg,#10b981,#059669)">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        Excellent!
                    </button>
                </div>

                {{-- ── COMPLETION FORM (hidden after done) ──────────────────────── --}}
                <div x-show="!completionDone" class="space-y-4">
                <div x-show="completionError" class="flex items-center gap-2.5 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700" role="alert">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span x-text="completionError"></span>
                </div>

                <template x-if="completionTask?.is_request_form_task && completionEmailEnabled">
                    <div class="space-y-3">
                        <label class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-colors"
                               :class="sendEmail ? 'border-purple-300 bg-purple-50' : 'border-gray-200 hover:border-purple-200'">
                            <input type="checkbox" x-model="sendEmail" class="mt-0.5 w-4 h-4 cursor-pointer accent-[#7B61FF]">
                            <div>
                                <p class="text-sm font-semibold text-[#1E1B4B]">Send reply to requester</p>
                                <p class="text-xs text-gray-400 mt-0.5">Notify the person who submitted this request.</p>
                            </div>
                        </label>
                        <template x-if="sendEmail">
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1.5" for="cs-subject">Subject *</label>
                                    <input id="cs-subject" type="text" x-model="completionSubject" maxlength="150"
                                           class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm bg-gray-50 outline-none focus:border-[#7B61FF] focus:ring-2 focus:ring-purple-100 transition-all"
                                           placeholder="Your request has been processed">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1.5" for="cs-body">Message *</label>
                                    <textarea id="cs-body" x-model="completionBody" rows="4" maxlength="5000"
                                              class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm bg-gray-50 outline-none focus:border-[#7B61FF] focus:ring-2 focus:ring-purple-100 transition-all resize-none"
                                              placeholder="Write your reply…"></textarea>
                                </div>
                                {{-- File attachments --}}
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Attachments <span class="text-gray-400 font-normal">(optional, max 5 files · 50 MB each)</span></label>
                                    <label class="flex items-center gap-2.5 px-3.5 py-2.5 rounded-xl border border-dashed border-gray-200 bg-gray-50 hover:border-purple-300 hover:bg-purple-50 cursor-pointer transition-colors">
                                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                        <span class="text-xs text-gray-400" x-text="completionFiles.length ? completionFiles.length + ' file(s) selected' : 'Click to attach files'"></span>
                                        <input type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png,.webp,.txt"
                                               class="sr-only"
                                               @change="completionFiles = Array.from($event.target.files).slice(0, 5)"
                                               :disabled="completionSubmitting">
                                    </label>
                                    <template x-if="completionFiles.length > 0">
                                        <ul class="mt-1.5 space-y-1">
                                            <template x-for="(f,i) in completionFiles" :key="i">
                                                <li class="flex items-center gap-2 text-[11px] text-gray-500">
                                                    <svg class="w-3 h-3 shrink-0 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                                    <span x-text="f.name" class="truncate max-w-[200px]"></span>
                                                    <button type="button" @click="completionFiles = completionFiles.filter((_,j) => j !== i)" class="text-gray-300 hover:text-red-400 ml-auto shrink-0">✕</button>
                                                </li>
                                            </template>
                                        </ul>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <template x-if="!completionTask?.is_request_form_task || !completionEmailEnabled">
                    <p class="text-sm text-gray-500">Mark this task as <strong class="text-[#1E1B4B]">Completed</strong>. This cannot be undone without admin access.</p>
                </template>

                <div class="flex gap-2.5 justify-end pt-1">
                    <button @click="cancelCompletion()" :disabled="completionSubmitting"
                            class="px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors disabled:opacity-50">
                        Cancel
                    </button>
                    <template x-if="completionTask?.is_request_form_task && completionEmailEnabled && sendEmail">
                        <button @click="submitCompletion(true)"
                                :disabled="completionSubmitting || !completionSubject.trim() || !completionBody.trim()"
                                class="px-4 py-2.5 rounded-xl text-sm font-semibold text-white transition-all disabled:opacity-50 flex items-center gap-2"
                                style="background:linear-gradient(135deg,#7B61FF,#5b4cdb)">
                            <svg x-show="completionSubmitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <span x-text="completionSubmitting ? 'Sending…' : 'Send Reply & Mark Done'"></span>
                        </button>
                    </template>
                    <template x-if="!(completionTask?.is_request_form_task && completionEmailEnabled && sendEmail)">
                        <button @click="submitCompletion(false)" :disabled="completionSubmitting"
                                class="px-4 py-2.5 rounded-xl text-sm font-semibold text-white transition-all disabled:opacity-50 flex items-center gap-2"
                                style="background:linear-gradient(135deg,#10b981,#059669)">
                            <svg x-show="completionSubmitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <svg x-show="!completionSubmitting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            <span x-text="completionSubmitting ? 'Marking done…' : 'Mark as Done'"></span>
                        </button>
                    </template>
                </div>
                </div>{{-- end !completionDone wrapper --}}
            </div>
        </div>
    </div>
    </template>

    {{-- ── CREATE TASK MODAL ────────────────────────────────────────────────── --}}
    @if($isAdmin)
    <template x-teleport="body">
    <div x-show="createOpen"
         class="fixed inset-0 z-[9998] flex items-start justify-center p-4 pt-8 overflow-y-auto"
         style="background:rgba(0,0,0,.45);backdrop-filter:blur(2px)"
         @keydown.escape.window="createOpen = false"
         @click.self="createOpen = false"
         x-cloak>
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[540px] my-auto"
             @click.stop role="dialog" aria-modal="true" aria-labelledby="create-task-title">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <h2 id="create-task-title" class="text-base font-bold text-[#1E1B4B]">New Task</h2>
                <button @click="createOpen = false" class="w-7 h-7 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors" aria-label="Close">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-5 space-y-4">
                <div x-show="createSuccess" class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-sm font-semibold text-emerald-700" x-text="createSuccess" role="status"></div>
                <div x-show="createError"   class="p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700" x-text="createError" role="alert"></div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5" for="task-title">Title *</label>
                    <input id="task-title" type="text" x-model="createForm.title" maxlength="200"
                           class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm bg-gray-50 outline-none focus:border-[#7B61FF] focus:ring-2 focus:ring-purple-100 transition-all"
                           placeholder="What needs to be done?">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5" for="task-desc">Description</label>
                    <textarea id="task-desc" x-model="createForm.description" maxlength="2000" rows="3"
                              class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm bg-gray-50 outline-none focus:border-[#7B61FF] focus:ring-2 focus:ring-purple-100 transition-all resize-none"
                              placeholder="Add details or context…"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5" for="task-priority">Priority *</label>
                        <select id="task-priority" x-model="createForm.priority"
                                class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm bg-gray-50 outline-none focus:border-[#7B61FF] transition-all">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5" for="task-due">Due Date</label>
                        <input id="task-due" type="date" x-model="createForm.due_at"
                               class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm bg-gray-50 outline-none focus:border-[#7B61FF] transition-all">
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-xs font-semibold text-gray-700">Assign To *</label>
                        <button type="button" @click="toggleAssignToMe()"
                                class="text-xs font-bold px-3 py-1 rounded-full border transition-all"
                                :class="createForm.assignee_ids.includes(currentUserId) ? 'bg-[#7B61FF] text-white border-[#7B61FF]' : 'bg-purple-50 text-[#7B61FF] border-purple-200'"
                                x-text="createForm.assignee_ids.includes(currentUserId) ? '✓ Me' : 'Assign to me'">
                        </button>
                    </div>
                    <div x-show="loadingAssignees" class="text-xs text-gray-400 py-2">Loading team members…</div>
                    <div x-show="!loadingAssignees" class="space-y-1 max-h-52 overflow-y-auto pr-0.5">

                        {{-- Team members --}}
                        <template x-if="teamMembers.length > 0">
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wide px-1 mb-1">Team</p>
                                <template x-for="m in teamMembers" :key="m.id">
                                    <label class="flex items-center gap-3 p-2.5 rounded-xl border cursor-pointer transition-all"
                                           :class="createForm.assignee_ids.includes(m.id) ? 'border-purple-300 bg-purple-50' : 'border-transparent bg-gray-50 hover:border-gray-200'">
                                        <input type="checkbox" :value="m.id" x-model="createForm.assignee_ids" class="w-4 h-4 cursor-pointer accent-[#7B61FF]">
                                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-bold text-white shrink-0"
                                             :style="m.is_me ? 'background:#7B61FF' : 'background:linear-gradient(135deg,#c4b5fd,#7B61FF)'"
                                             x-text="(m.name||'?').slice(0,2).toUpperCase()"></div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-semibold text-[#1E1B4B] truncate" x-text="m.is_me ? m.name + ' (you)' : m.name"></p>
                                            <p class="text-[10px] text-gray-400 truncate" x-text="m.email + ' · ' + (m.role ? m.role.charAt(0).toUpperCase()+m.role.slice(1) : '')"></p>
                                        </div>
                                    </label>
                                </template>
                            </div>
                        </template>

                        {{-- Referrers (assigned via reseller portal) --}}
                        <template x-if="referrerAssignees.length > 0">
                            <div :class="teamMembers.length > 0 ? 'mt-2' : ''">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wide px-1 mb-1">Referrers</p>
                                <template x-for="m in referrerAssignees" :key="m.id">
                                    <label class="flex items-center gap-3 p-2.5 rounded-xl border cursor-pointer transition-all"
                                           :class="createForm.assignee_ids.includes(m.id) ? 'border-purple-300 bg-purple-50' : 'border-transparent bg-gray-50 hover:border-gray-200'">
                                        <input type="checkbox" :value="m.id" x-model="createForm.assignee_ids" class="w-4 h-4 cursor-pointer accent-[#7B61FF]">
                                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-bold text-white shrink-0"
                                             style="background:linear-gradient(135deg,#14b8a6,#0d9488)"
                                             x-text="(m.name||'?').slice(0,2).toUpperCase()"></div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-semibold text-[#1E1B4B] truncate" x-text="m.name"></p>
                                            <p class="text-[10px] text-gray-400 truncate" x-text="m.email + ' · Referrer'"></p>
                                        </div>
                                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-teal-50 text-teal-700 shrink-0">Referrer</span>
                                    </label>
                                </template>
                            </div>
                        </template>

                        <template x-if="!loadingAssignees && assignees.length === 0">
                            <p class="text-xs text-amber-600 p-2">No eligible team members found.</p>
                        </template>
                    </div>
                    <p x-show="createForm.assignee_ids.length > 1" class="text-[11px] text-[#7B61FF] mt-1.5">
                        One task per assignee — <span x-text="createForm.assignee_ids.length"></span> tasks will be created.
                    </p>
                </div>

                <div class="flex gap-2.5 justify-end pt-1">
                    <button @click="createOpen = false"
                            class="px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button @click="submitTask()"
                            :disabled="createSubmitting || !createForm.title.trim() || createForm.assignee_ids.length === 0"
                            class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white transition-all disabled:opacity-50 flex items-center gap-2"
                            style="background:linear-gradient(135deg,#7B61FF,#5b4cdb);box-shadow:0 4px 14px rgba(123,97,255,.25)">
                        <svg x-show="createSubmitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-text="createSubmitting ? 'Creating…' : 'Create Task'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    </template>
    @endif

</div>

@push('scripts')
<script>
function tasksPage(tenantId, currentUserId, currentUserName, initialView, completionEmailEnabled, isAdmin) {
    const kanbanData = window.__rbKanban || [];
    const CSRF   = () => document.querySelector('meta[name=csrf-token]')?.content ?? '';
    const JSON_H = () => ({ 'Content-Type':'application/json','X-CSRF-TOKEN':CSRF(),'Accept':'application/json','X-Requested-With':'XMLHttpRequest' });

    return {
        view:                   initialView || 'list',
        isAdmin:                isAdmin,
        completionEmailEnabled: completionEmailEnabled,
        currentUserId:          currentUserId,

        // Kanban
        columns:   kanbanData,
        dragTaskId: null, dragSourceCol: null, dragTask: null, dragOver: null,
        movingTaskId: null,

        // Completion modal
        completionOpen: false, completionTask: null, completionSourceCol: null,
        sendEmail: false, completionSubject: '', completionBody: '',
        completionFiles: [],
        completionSubmitting: false, completionSuccess: '', completionError: '', completionDone: false,

        // Create modal
        createOpen: false, loadingAssignees: false, createSubmitting: false,
        assignees: [], createSuccess: '', createError: '',
        createForm: { title:'', description:'', priority:'medium', due_at:'', assignee_ids:[], source_type:'', source_id:'' },

        // Toasts
        toasts: [],

        get teamMembers() {
            return this.assignees.filter(m => m.type !== 'reseller');
        },
        get referrerAssignees() {
            return this.assignees.filter(m => m.type === 'reseller');
        },
        get sortedAssignees() {
            // kept for backwards-compat; use teamMembers / referrerAssignees for grouped display
            return [...this.assignees.filter(m => m.id === currentUserId),
                    ...this.assignees.filter(m => m.id !== currentUserId)];
        },

        init() {
            const saved = localStorage.getItem('rb_tasks_view_' + tenantId);
            if (!initialView && saved && ['list','kanban'].includes(saved)) this.view = saved;
        },

        // ── View switcher ───────────────────────────────────────────────────────
        switchView(v) {
            if (this.view === v) return;
            localStorage.setItem('rb_tasks_view_' + tenantId, v);
            const p = new URLSearchParams();
            p.set('tab',  '{{ $tab }}');
            p.set('view', v);
            @if($assigneeFilter && $assigneeFilter !== 'all')
            p.set('assignee', '{{ $assigneeFilter }}');
            @endif
            window.location.href = window.location.pathname + '?' + p.toString();
        },

        // ── View All URL — links from kanban column to filtered list view ────────
        viewAllUrl(col) {
            const base = window.location.pathname;
            if (col.key === 'completed') return `${base}?tab=completed&view=list`;
            return `${base}?tab={{ $tab }}&view=list&status=${col.view_all_status}`;
        },

        // ── Style helpers ────────────────────────────────────────────────────────
        colDotStyle(key) {
            return { new_tasks:'background:#7B61FF', processing:'background:#2563eb', completed:'background:#16a34a' }[key] || 'background:#9ca3af';
        },
        colBgStyle(key) {
            if (this.dragOver === key) return '';  // handled by :class
            return { new_tasks:'background:#f5f3ff', processing:'background:#eff6ff', completed:'background:#f0fdf4' }[key] || 'background:#f9fafb';
        },
        priorityStyle(p) {
            return { urgent:'background:#fef2f2;color:#dc2626', high:'background:#fff7ed;color:#ea580c', medium:'background:#fffbeb;color:#d97706', low:'background:#f3f4f6;color:#6b7280' }[p] || 'background:#f3f4f6;color:#6b7280';
        },

        // ── Toast ────────────────────────────────────────────────────────────────
        toast(msg, type = 'info', dur = 4000) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, msg, type });
            setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, dur);
        },

        // ── Column helpers ───────────────────────────────────────────────────────
        findColumn(key)  { return this.columns.find(c => c.key === key); },
        removeTaskFromCol(taskId, colKey) {
            const col = this.findColumn(colKey);
            if (!col) return null;
            const idx = col.tasks.findIndex(t => t.id === taskId);
            if (idx === -1) return null;
            return col.tasks.splice(idx, 1)[0];
        },
        addTaskToCol(task, colKey) {
            const col = this.findColumn(colKey);
            if (col) { task.status = col.status_for_drop || colKey; col.tasks.unshift(task); }
        },

        // ── Drag & Drop ────────────────────────────────────────────────────────
        onDragStart(e, task, sourceColKey) {
            this.dragTask = task; this.dragTaskId = task.id; this.dragSourceCol = sourceColKey;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', task.id);
        },
        onDragEnd() { this.dragOver = null; this.dragTaskId = null; },
        onDrop(e, targetColKey) {
            this.dragOver = null;
            if (!this.dragTask || targetColKey === this.dragSourceCol) { this.dragTask = null; return; }
            const task = this.dragTask; this.dragTask = null;
            if (targetColKey === 'completed') { this.initiateComplete(task, this.dragSourceCol); return; }
            this.performMove(task, this.dragSourceCol, targetColKey);
        },

        // ── Quick move ────────────────────────────────────────────────────────
        async quickMove(task, sourceColKey, targetColKey) {
            if (!task.can_update_status || task.status === (this.findColumn(targetColKey)?.status_for_drop)) return;
            if (targetColKey === 'completed') { this.initiateComplete(task, sourceColKey); return; }
            await this.performMove(task, sourceColKey, targetColKey);
        },

        // ── Optimistic move ───────────────────────────────────────────────────
        async performMove(task, sourceColKey, targetColKey) {
            if (this.movingTaskId === task.id) return;
            this.movingTaskId = task.id;
            const removed = this.removeTaskFromCol(task.id, sourceColKey);
            const targetStatus = this.findColumn(targetColKey)?.status_for_drop || targetColKey;
            try {
                const res = await fetch(`/tenant/${tenantId}/tasks/${task.id}/status`, {
                    method:'PATCH', headers:JSON_H(), body:JSON.stringify({ status: targetStatus }),
                });
                const data = await res.json();
                if (!res.ok) {
                    if (removed) this.addTaskToCol(removed, sourceColKey);
                    this.toast(data.error || 'Unable to move task.', 'error');
                    return;
                }
                this.addTaskToCol({ ...(data.card || removed) }, targetColKey);
                this.toast(data.message || 'Task moved.', 'success');
            } catch(e) {
                if (removed) this.addTaskToCol(removed, sourceColKey);
                this.toast('Network error. Please try again.', 'error');
            } finally { this.movingTaskId = null; }
        },

        // ── Completion ────────────────────────────────────────────────────────
        initiateComplete(task, sourceColKey) {
            if (!task.can_complete) { this.toast('You cannot complete this task.', 'error'); return; }
            this.completionTask = task; this.completionSourceCol = sourceColKey;
            this.sendEmail = false; this.completionSubject = ''; this.completionBody = '';
            this.completionFiles = [];
            this.completionSuccess = ''; this.completionError = ''; this.completionDone = false;
            this.completionOpen = true;
        },
        cancelCompletion() {
            this.completionOpen = false; this.completionTask = null;
            this.completionError = ''; this.completionSuccess = ''; this.completionDone = false;
            this.completionFiles = [];
        },
        async submitCompletion(withEmail) {
            if (this.completionSubmitting) return;
            const task = this.completionTask;
            if (!task) return;
            if (withEmail && (!this.completionSubject.trim() || !this.completionBody.trim())) {
                this.completionError = 'Subject and message are required.'; return;
            }
            this.completionSubmitting = true; this.completionError = ''; this.completionSuccess = '';
            const removed = this.removeTaskFromCol(task.id, this.completionSourceCol);
            try {
                let res;
                if (withEmail) {
                    // Use FormData (multipart) so file attachments can be included
                    const fd = new FormData();
                    fd.append('status', 'completed');
                    fd.append('send_email', '1');
                    fd.append('subject', this.completionSubject.trim());
                    fd.append('body', this.completionBody.trim());
                    fd.append('client_request_id', 'cr_' + Date.now() + '_' + Math.random().toString(36).slice(2));
                    this.completionFiles.forEach((f, i) => fd.append(`attachments[${i}]`, f));
                    res = await fetch(`/tenant/${tenantId}/tasks/${task.id}/status`, {
                        method: 'PATCH',
                        headers: { 'X-CSRF-TOKEN': CSRF(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: fd,
                    });
                } else {
                    res = await fetch(`/tenant/${tenantId}/tasks/${task.id}/status`, {
                        method: 'PATCH', headers: JSON_H(), body: JSON.stringify({ status: 'completed', send_email: false }),
                    });
                }
                const data = await res.json();
                if (!res.ok) {
                    // Restore card fully on error
                    if (removed) this.addTaskToCol({ ...removed }, this.completionSourceCol);
                    this.completionError = data.error || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Could not complete task.');
                    return;
                }
                this.addTaskToCol({ ...(data.card || removed), status: 'completed' }, 'completed');
                this.completionSuccess = data.message || 'Task marked as done.';
                this.completionDone = true;
                this.toast(data.message || 'Task completed!', 'success');
            } catch(e) {
                if (removed) this.addTaskToCol({ ...removed }, this.completionSourceCol);
                this.completionError = 'Network error. Please try again.';
            } finally { this.completionSubmitting = false; }
        },

        // ── Create task ───────────────────────────────────────────────────────
        toggleAssignToMe() {
            const me = this.currentUserId || currentUserId;
            const idx = this.createForm.assignee_ids.indexOf(me);
            if (idx === -1) this.createForm.assignee_ids.push(me);
            else this.createForm.assignee_ids.splice(idx, 1);
        },
        async fetchAssignees() {
            this.loadingAssignees = true;
            try {
                const r = await fetch(`/tenant/${tenantId}/tasks/eligible-assignees`, { headers:{ 'X-Requested-With':'XMLHttpRequest','Accept':'application/json' } });
                const d = await r.json();
                this.assignees = Array.isArray(d) ? d : (d.data || []);
            } catch(e) { this.assignees = []; } finally { this.loadingAssignees = false; }
        },
        async submitTask() {
            if (!this.createForm.title.trim() || this.createForm.assignee_ids.length === 0) return;
            this.createSubmitting = true; this.createSuccess = ''; this.createError = '';
            const payload = { ...this.createForm };
            if (!payload.source_type) delete payload.source_type;
            if (!payload.source_id) delete payload.source_id;
            try {
                const r = await fetch(`/tenant/${tenantId}/tasks`, { method:'POST', headers:JSON_H(), body:JSON.stringify(payload) });
                const d = await r.json();
                if (!r.ok) { this.createError = d.message || d.error || 'Failed to create task.'; return; }
                this.createSuccess = d.self_assigned ? 'Task created and assigned to you.' : (d.message || 'Task created.');
                setTimeout(() => { this.createOpen = false; window.location.reload(); }, 1200);
            } catch(e) { this.createError = 'Network error. Please try again.'; }
            finally { this.createSubmitting = false; }
        },
    };
}
</script>
@endpush
@endsection
