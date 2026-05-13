@extends('layouts.app')
@section('title', 'Tasks')
@section('nav') @include('tenant._nav') @endsection

@section('content')
<div class="space-y-4"
     x-data="tasksPage(
         '{{ $tenant->id }}',
         '{{ $actorId }}',
         '{{ addslashes($actorName) }}',
         '{{ $view }}',
         @json($kanbanColumns),
         {{ $completionEmailEnabled ? 'true' : 'false' }}
     )"
     x-init="init()">

    {{-- ── PAGE HEADER ──────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[#1E1B4B]">Tasks</h1>
            <p class="text-sm text-gray-400 mt-0.5">Manage requests, assignments, and action items.</p>
        </div>
        <div class="flex items-center gap-2.5 flex-wrap">

            {{-- View Switcher --}}
            <div class="flex items-center bg-gray-100 rounded-xl p-1 gap-0.5" role="group" aria-label="View options">
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

            {{-- Create Task --}}
            @if($isAdmin)
            <button @click="createOpen = true; if(assignees.length === 0) fetchAssignees()"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white transition-all"
                    style="background:linear-gradient(135deg,#7B61FF,#5b4cdb);box-shadow:0 4px 14px rgba(123,97,255,.25)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <span class="hidden sm:inline">Create Task</span>
            </button>
            @endif
        </div>
    </div>

    {{-- ── SHARED FILTERS: tabs + assignee ─────────────────────────────────── --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex gap-1.5 flex-wrap">
            @foreach([['mine','My Tasks'],['all','All Tasks'],['overdue','Overdue'],['completed','Completed']] as [$key,$label])
            <a href="{{ request()->fullUrlWithQuery(['tab' => $key, 'assignee' => null, 'view' => $view]) }}"
               class="px-3.5 py-1.5 text-xs font-semibold rounded-full transition-all no-underline"
               style="{{ $tab === $key
                    ? 'background:#7B61FF;color:white;box-shadow:0 4px 12px rgba(123,97,255,.3)'
                    : 'background:white;color:#9ca3af;border:1.5px solid #e5e7eb' }}">
                {{ $label }}
            </a>
            @endforeach
        </div>

        @if(!empty($isAdmin) && !empty($assigneeOptions))
        <form method="GET" action="{{ request()->url() }}" class="flex items-center gap-2">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="hidden" name="view" value="{{ $view }}">
            <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            <select name="assignee" onchange="this.form.submit()"
                    class="text-xs font-medium rounded-xl border border-gray-200 bg-white text-gray-700 px-3 py-1.5 outline-none cursor-pointer max-w-[180px]"
                    aria-label="Filter by assignee">
                <option value="all" {{ empty($assigneeFilter) || $assigneeFilter === 'all' ? 'selected' : '' }}>All Assignees</option>
                @foreach($assigneeOptions as $u)
                <option value="{{ $u->id }}" {{ $assigneeFilter === $u->id ? 'selected' : '' }}>
                    {{ trim($u->name) ?: $u->id }} ({{ ucfirst($u->role) }})
                </option>
                @endforeach
            </select>
        </form>
        @endif
    </div>

    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    {{-- LIST VIEW                                                               --}}
    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    <div x-show="view === 'list'" x-cloak>
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
                <div class="w-2 h-2 rounded-full shrink-0 mt-1.5"
                     style="background:{{ $priorityDot }}" aria-hidden="true"></div>
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-start gap-2 mb-1">
                        <p class="text-sm font-semibold text-[#1E1B4B] truncate">{{ $task->title }}</p>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full shrink-0"
                              style="{{ $statusStyle }}">{{ $displayStatus }}</span>
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
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full shrink-0"
                      style="{{ $priorityStyle }}">{{ ucfirst($task->priority) }}</span>
            </a>
            @endforeach
            @if($tasks->hasPages())
            <div class="px-4 py-3 border-t border-gray-100 bg-gray-50/50">{{ $tasks->links() }}</div>
            @endif
            @endif
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    {{-- KANBAN VIEW                                                             --}}
    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    <div x-show="view === 'kanban'" x-cloak>

        {{-- Mobile: status tab switcher (one column at a time) --}}
        <div class="flex lg:hidden gap-1 overflow-x-auto pb-1 scrollbar-none mb-3"
             role="tablist" aria-label="Kanban columns">
            <template x-for="col in columns" :key="col.key">
                <button @click="mobileCol = col.key"
                        role="tab"
                        :aria-selected="mobileCol === col.key ? 'true' : 'false'"
                        :class="mobileCol === col.key
                            ? 'text-white shadow-sm'
                            : 'bg-white text-gray-500 border border-gray-200'"
                        :style="mobileCol === col.key ? 'background:linear-gradient(135deg,#7B61FF,#5b4cdb)' : ''"
                        class="px-3.5 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition-all shrink-0">
                    <span x-text="col.label"></span>
                    <span class="ml-1 opacity-70" x-text="'(' + col.tasks.length + ')'"></span>
                </button>
            </template>
        </div>

        {{-- Desktop: horizontal columns / Mobile: single column --}}
        <div class="flex gap-4 overflow-x-auto pb-4 lg:pb-2 items-start"
             style="min-height:400px">

            <template x-for="col in columns" :key="col.key">
                {{-- Column wrapper — hidden on mobile unless selected --}}
                <div :class="{'hidden lg:flex': mobileCol !== col.key, 'flex': mobileCol === col.key}"
                     class="flex-col gap-3 lg:flex"
                     style="min-width:280px; max-width:320px; width:100%; flex-shrink:0"
                     @dragover.prevent="dragOver = col.key"
                     @dragleave.self="dragOver = null"
                     @drop.prevent="onDrop($event, col.key)">

                    {{-- Column header --}}
                    <div class="flex items-center justify-between px-1">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full"
                                  :style="colDotStyle(col.key)" aria-hidden="true"></span>
                            <span class="text-sm font-bold text-[#1E1B4B]" x-text="col.label"></span>
                            <span class="text-xs font-semibold text-gray-400 bg-gray-100 rounded-full px-2 py-0.5"
                                  x-text="col.tasks.length"></span>
                        </div>
                        <span x-show="col.tasks.filter(t=>t.is_overdue).length > 0"
                              class="text-[10px] font-bold text-red-500 bg-red-50 rounded-full px-2 py-0.5"
                              x-text="col.tasks.filter(t=>t.is_overdue).length + ' overdue'"
                              aria-live="polite"></span>
                    </div>

                    {{-- Drop zone highlight --}}
                    <div class="flex flex-col gap-2.5 rounded-2xl p-2 transition-colors min-h-[80px]"
                         :class="dragOver === col.key ? 'bg-purple-50 border-2 border-dashed border-purple-300' : 'bg-gray-50/70'"
                         role="group"
                         :aria-label="col.label + ' tasks'">

                        {{-- Empty state --}}
                        <template x-if="col.tasks.length === 0">
                            <div class="flex flex-col items-center justify-center py-10 text-center">
                                <svg class="w-8 h-8 text-gray-200 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="text-xs text-gray-400 font-medium" x-text="'No ' + col.label.toLowerCase() + ' tasks'"></p>
                            </div>
                        </template>

                        {{-- Task cards --}}
                        <template x-for="task in col.tasks" :key="task.id">
                            <div class="bg-white rounded-xl shadow-sm border border-gray-100 transition-all cursor-pointer group"
                                 :class="{
                                     'opacity-50 pointer-events-none': movingTaskId === task.id,
                                     'ring-2 ring-purple-300 shadow-md': dragTaskId === task.id,
                                     'hover:shadow-md hover:-translate-y-px': true,
                                 }"
                                 :draggable="task.can_update_status ? 'true' : 'false'"
                                 @dragstart="task.can_update_status && onDragStart($event, task, col.key)"
                                 @dragend="onDragEnd()"
                                 tabindex="0"
                                 :aria-label="'Task: ' + task.title + ', ' + task.priority + ' priority, ' + col.label">

                                {{-- Card body (click → task detail) --}}
                                <a :href="task.url"
                                   class="block p-3.5 no-underline"
                                   @click.stop>

                                    {{-- Title --}}
                                    <p class="text-sm font-semibold text-[#1E1B4B] leading-snug mb-2 line-clamp-2"
                                       x-text="task.title"></p>

                                    {{-- Badges row --}}
                                    <div class="flex flex-wrap gap-1.5 mb-2.5">
                                        {{-- Source badge --}}
                                        <span class="text-[9px] font-bold px-2 py-0.5 rounded-full"
                                              :style="task.category === 'request_form'
                                                  ? 'background:#f0fdf4;color:#16a34a'
                                                  : task.category === 'manual'
                                                      ? 'background:#f5f3ff;color:#7B61FF'
                                                      : 'background:#f3f4f6;color:#6b7280'"
                                              x-text="task.category === 'request_form' ? 'Request Form' : task.category === 'manual' ? 'Manual' : 'System'"></span>

                                        {{-- Priority badge --}}
                                        <span class="text-[9px] font-bold px-2 py-0.5 rounded-full"
                                              :style="priorityStyle(task.priority)"
                                              x-text="task.priority.charAt(0).toUpperCase() + task.priority.slice(1)"></span>

                                        {{-- Overdue badge --}}
                                        <span x-show="task.is_overdue"
                                              class="text-[9px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-600">
                                            Overdue
                                        </span>
                                    </div>

                                    {{-- Meta row --}}
                                    <div class="flex items-center justify-between gap-2 text-[10px] text-gray-400">
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            {{-- Assignee avatar --}}
                                            <div x-show="task.assignee_name"
                                                 class="w-5 h-5 rounded-full flex items-center justify-center font-bold text-[8px] shrink-0 text-white"
                                                 style="background:linear-gradient(135deg,#7B61FF,#5b4cdb)"
                                                 :aria-label="'Assigned to ' + (task.assignee_name || '')"
                                                 x-text="task.assignee_initials"></div>
                                            <span class="truncate" x-text="task.assignee_name || 'Unassigned'"></span>
                                        </div>
                                        <span class="shrink-0" x-show="task.due_at"
                                              :class="task.is_overdue ? 'text-red-500 font-semibold' : ''"
                                              x-text="task.due_at"></span>
                                    </div>

                                    {{-- Requestor name (if request form task) --}}
                                    <div x-show="task.requestor_name"
                                         class="mt-1.5 text-[10px] text-gray-400 truncate">
                                        From: <span x-text="task.requestor_name"></span>
                                    </div>
                                </a>

                                {{-- Card footer: quick status actions --}}
                                <div x-show="task.can_update_status"
                                     class="border-t border-gray-50 px-3.5 py-2 flex items-center gap-1.5 flex-wrap"
                                     @click.stop>

                                    {{-- Non-completion moves --}}
                                    <template x-if="task.status !== 'open' && task.can_update_status">
                                        <button @click="quickMove(task, col.key, 'open')"
                                                :disabled="movingTaskId === task.id"
                                                class="text-[10px] font-semibold px-2 py-1 rounded-lg bg-purple-50 text-[#7B61FF] hover:bg-purple-100 transition-colors disabled:opacity-50">
                                            → Open
                                        </button>
                                    </template>
                                    <template x-if="task.status !== 'in_progress' && task.can_update_status && !['completed','cancelled','archived'].includes(task.status)">
                                        <button @click="quickMove(task, col.key, 'in_progress')"
                                                :disabled="movingTaskId === task.id"
                                                class="text-[10px] font-semibold px-2 py-1 rounded-lg bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors disabled:opacity-50">
                                            → In Progress
                                        </button>
                                    </template>
                                    <template x-if="task.status !== 'waiting' && task.can_update_status && !['completed','cancelled','archived'].includes(task.status)">
                                        <button @click="quickMove(task, col.key, 'waiting')"
                                                :disabled="movingTaskId === task.id"
                                                class="text-[10px] font-semibold px-2 py-1 rounded-lg bg-amber-50 text-amber-700 hover:bg-amber-100 transition-colors disabled:opacity-50">
                                            → Waiting
                                        </button>
                                    </template>
                                    <template x-if="task.can_complete && task.status !== 'completed'">
                                        <button @click="initiateComplete(task, col.key)"
                                                :disabled="movingTaskId === task.id"
                                                class="text-[10px] font-semibold px-2 py-1 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition-colors disabled:opacity-50">
                                            ✓ Done
                                        </button>
                                    </template>

                                    {{-- Moving spinner --}}
                                    <template x-if="movingTaskId === task.id">
                                        <svg class="w-3.5 h-3.5 animate-spin text-[#7B61FF]" fill="none" viewBox="0 0 24 24" aria-label="Updating task">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                        </svg>
                                    </template>
                                </div>
                            </div>
                        </template>

                    </div>{{-- drop zone --}}
                </div>{{-- column --}}
            </template>
        </div>{{-- board --}}
    </div>{{-- kanban view --}}

    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    {{-- TOASTS                                                                  --}}
    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    <div class="fixed bottom-5 right-5 z-[200] flex flex-col gap-2 items-end pointer-events-none"
         aria-live="polite" aria-atomic="true"
         style="max-width:360px">
        <template x-for="t in toasts" :key="t.id">
            <div class="pointer-events-auto flex items-center gap-3 px-4 py-3.5 rounded-2xl shadow-2xl text-sm font-medium w-full"
                 role="alert"
                 :class="{
                     'bg-emerald-600 text-white': t.type === 'success',
                     'bg-red-600 text-white':     t.type === 'error',
                     'bg-[#1E1B4B] text-white':   t.type === 'info',
                 }"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0">
                <svg x-show="t.type==='success'" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                <svg x-show="t.type==='error'"   class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                <span class="flex-1 leading-snug" x-text="t.msg"></span>
                <button @click="toasts = toasts.filter(x => x.id !== t.id)"
                        class="shrink-0 opacity-60 hover:opacity-100 ml-1"
                        aria-label="Dismiss">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </template>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    {{-- COMPLETION MODAL (Kanban — request-form tasks)                         --}}
    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    <template x-teleport="body">
    <div x-show="completionOpen"
         class="fixed inset-0 z-[9999] flex items-end sm:items-center justify-center p-4"
         style="background:rgba(0,0,0,.5);backdrop-filter:blur(4px)"
         @keydown.escape.window="cancelCompletion()"
         x-cloak>
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto"
             @click.stop
             x-trap.noscroll="completionOpen"
             role="dialog"
             aria-modal="true"
             aria-labelledby="completion-modal-title">

            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div>
                    <h2 id="completion-modal-title" class="text-base font-bold text-[#1E1B4B]">Complete Task</h2>
                    <p class="text-xs text-gray-400 mt-0.5 line-clamp-1" x-text="completionTask?.title"></p>
                </div>
                <button @click="cancelCompletion()"
                        class="w-8 h-8 rounded-xl bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors"
                        aria-label="Close">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="p-5 space-y-4">

                {{-- Success --}}
                <div x-show="completionSuccess"
                     class="flex items-center gap-2.5 p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-sm text-emerald-700"
                     role="status">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span x-text="completionSuccess"></span>
                </div>

                {{-- Error --}}
                <div x-show="completionError"
                     class="flex items-center gap-2.5 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700"
                     role="alert">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span x-text="completionError"></span>
                </div>

                {{-- Request form task: offer reply option --}}
                <template x-if="completionTask?.is_request_form_task && completionEmailEnabled">
                    <div class="space-y-3">
                        {{-- Toggle: send reply --}}
                        <label class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 cursor-pointer hover:border-purple-300 transition-colors"
                               :class="sendEmail ? 'border-purple-300 bg-purple-50' : ''">
                            <input type="checkbox" x-model="sendEmail"
                                   class="mt-0.5 w-4 h-4 cursor-pointer accent-[#7B61FF]"
                                   aria-label="Send reply to requester">
                            <div>
                                <p class="text-sm font-semibold text-[#1E1B4B]">Send reply to requester</p>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    Notify the person who submitted this request.
                                </p>
                            </div>
                        </label>

                        <template x-if="sendEmail">
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1.5" for="completion-subject">Subject *</label>
                                    <input id="completion-subject" type="text" x-model="completionSubject" maxlength="150"
                                           class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm text-gray-900 bg-gray-50 outline-none focus:border-[#7B61FF] focus:ring-2 focus:ring-purple-100 transition-all"
                                           placeholder="e.g. Your request has been processed">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1.5" for="completion-body">Message *</label>
                                    <textarea id="completion-body" x-model="completionBody" rows="4" maxlength="5000"
                                              class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm text-gray-900 bg-gray-50 outline-none focus:border-[#7B61FF] focus:ring-2 focus:ring-purple-100 transition-all resize-none"
                                              placeholder="Write your reply to the requester…"></textarea>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- Non-request-form task: simple completion --}}
                <template x-if="!completionTask?.is_request_form_task || !completionEmailEnabled">
                    <p class="text-sm text-gray-500">
                        This will mark the task as <strong class="text-[#1E1B4B]">Completed</strong>.
                        This action cannot be undone without admin access.
                    </p>
                </template>

                {{-- Actions --}}
                <div class="flex gap-2.5 justify-end pt-1">
                    <button @click="cancelCompletion()"
                            :disabled="completionSubmitting"
                            class="px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors disabled:opacity-50">
                        Cancel
                    </button>
                    <template x-if="completionTask?.is_request_form_task && completionEmailEnabled && sendEmail">
                        <button @click="submitCompletion(true)"
                                :disabled="completionSubmitting || !completionSubject.trim() || !completionBody.trim()"
                                class="px-4 py-2.5 rounded-xl text-sm font-semibold text-white transition-all disabled:opacity-50 flex items-center gap-2"
                                style="background:linear-gradient(135deg,#7B61FF,#5b4cdb)">
                            <svg x-show="completionSubmitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <span x-text="completionSubmitting ? 'Sending…' : 'Send Reply & Mark Done'"></span>
                        </button>
                    </template>
                    <template x-if="!(completionTask?.is_request_form_task && completionEmailEnabled && sendEmail)">
                        <button @click="submitCompletion(false)"
                                :disabled="completionSubmitting"
                                class="px-4 py-2.5 rounded-xl text-sm font-semibold text-white transition-all disabled:opacity-50 flex items-center gap-2"
                                style="background:linear-gradient(135deg,#10b981,#059669)">
                            <svg x-show="completionSubmitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <svg x-show="!completionSubmitting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            <span x-text="completionSubmitting ? 'Marking done…' : 'Mark as Done'"></span>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>
    </template>

    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    {{-- CREATE TASK MODAL                                                       --}}
    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    @if($isAdmin)
    <template x-teleport="body">
    <div x-show="createOpen"
         class="fixed inset-0 z-[9998] flex items-center justify-center p-4"
         style="background:rgba(0,0,0,.45);backdrop-filter:blur(2px)"
         @keydown.escape.window="createOpen = false"
         x-cloak>
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[540px] max-h-[90vh] overflow-y-auto"
             @click.stop
             role="dialog" aria-modal="true" aria-labelledby="create-task-title">

            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <h2 id="create-task-title" class="text-base font-bold text-[#1E1B4B]">Create Task</h2>
                <button @click="createOpen = false"
                        class="w-7 h-7 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors"
                        aria-label="Close">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="p-5 space-y-4">
                <div x-show="createSuccess"
                     class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-sm font-semibold text-emerald-700"
                     x-text="createSuccess" role="status"></div>
                <div x-show="createError"
                     class="p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700"
                     x-text="createError" role="alert"></div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5" for="task-title">Task Title *</label>
                    <input id="task-title" type="text" x-model="createForm.title" maxlength="200"
                           class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm bg-gray-50 outline-none focus:border-[#7B61FF] focus:ring-2 focus:ring-purple-100 transition-all"
                           placeholder="e.g. Prepare LGU proposal for Quezon City">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5" for="task-desc">Description</label>
                    <textarea id="task-desc" x-model="createForm.description" maxlength="2000" rows="3"
                              class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm bg-gray-50 outline-none focus:border-[#7B61FF] focus:ring-2 focus:ring-purple-100 transition-all resize-none"
                              placeholder="Optional — add details, context, or requirements"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5" for="task-priority">Priority *</label>
                        <select id="task-priority" x-model="createForm.priority"
                                class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm bg-gray-50 outline-none focus:border-[#7B61FF] focus:ring-2 focus:ring-purple-100 transition-all">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5" for="task-due">Due Date</label>
                        <input id="task-due" type="date" x-model="createForm.due_at"
                               class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm bg-gray-50 outline-none focus:border-[#7B61FF] focus:ring-2 focus:ring-purple-100 transition-all">
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-xs font-semibold text-gray-700">Assign To *</label>
                        <button type="button" @click="toggleAssignToMe()"
                                class="text-xs font-bold px-3 py-1 rounded-full border transition-all"
                                :class="createForm.assignee_ids.includes(currentUserId)
                                    ? 'bg-[#7B61FF] text-white border-[#7B61FF]'
                                    : 'bg-purple-50 text-[#7B61FF] border-purple-200'">
                            <span x-text="createForm.assignee_ids.includes(currentUserId) ? '✓ Me' : 'Assign to me'"></span>
                        </button>
                    </div>

                    <div x-show="loadingAssignees" class="text-xs text-gray-400 py-2">Loading team members…</div>
                    <div x-show="!loadingAssignees" class="space-y-1.5 max-h-44 overflow-y-auto">
                        <template x-for="m in sortedAssignees" :key="m.id">
                            <label class="flex items-center gap-3 p-2.5 rounded-xl border cursor-pointer transition-all"
                                   :class="createForm.assignee_ids.includes(m.id)
                                       ? 'border-purple-300 bg-purple-50'
                                       : 'border-transparent bg-gray-50 hover:border-gray-200'">
                                <input type="checkbox" :value="m.id" x-model="createForm.assignee_ids"
                                       class="w-4 h-4 cursor-pointer accent-[#7B61FF]">
                                <div class="w-7 h-7 rounded-full flex items-center justify-center font-bold text-[10px] shrink-0 text-white"
                                     :style="m.is_me ? 'background:#7B61FF' : 'background:linear-gradient(135deg,#c4b5fd,#7B61FF)'"
                                     x-text="(m.name||'?').slice(0,2).toUpperCase()" aria-hidden="true"></div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-[#1E1B4B] truncate"
                                       x-text="m.is_me ? m.name + ' (you)' : m.name"></p>
                                    <p class="text-[10px] text-gray-400 truncate"
                                       x-text="m.email + ' · ' + (m.role ? m.role.charAt(0).toUpperCase()+m.role.slice(1) : '')"></p>
                                </div>
                            </label>
                        </template>
                        <template x-if="!loadingAssignees && assignees.length === 0">
                            <p class="text-xs text-amber-600 p-2">No eligible team members found.</p>
                        </template>
                    </div>
                    <p x-show="createForm.assignee_ids.length > 1"
                       class="text-[11px] text-[#7B61FF] mt-1.5">
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
                        <svg x-show="createSubmitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <span x-text="createSubmitting ? 'Creating…' : 'Create Task'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    </template>
    @endif

</div>{{-- tasksPage component --}}

@push('scripts')
<script>
function tasksPage(tenantId, currentUserId, currentUserName, initialView, kanbanData, completionEmailEnabled) {
    const CSRF   = () => document.querySelector('meta[name=csrf-token]')?.content ?? '';
    const JSON_H = () => ({ 'Content-Type':'application/json','X-CSRF-TOKEN':CSRF(),'Accept':'application/json','X-Requested-With':'XMLHttpRequest' });

    return {
        // ── View state ──────────────────────────────────────────────────────────
        view: initialView || 'list',

        // ── Kanban state ────────────────────────────────────────────────────────
        columns: kanbanData || [],
        mobileCol: 'open',

        // Drag state
        dragTaskId: null,
        dragSourceCol: null,
        dragTask: null,
        dragOver: null,

        // In-flight task id (shows spinner on card)
        movingTaskId: null,

        // ── Completion modal state ───────────────────────────────────────────────
        completionOpen: false,
        completionTask: null,
        completionSourceCol: null,
        sendEmail: false,
        completionSubject: '',
        completionBody: '',
        completionSubmitting: false,
        completionSuccess: '',
        completionError: '',

        // ── Create task modal state ──────────────────────────────────────────────
        createOpen: false,
        loadingAssignees: false,
        createSubmitting: false,
        assignees: [],
        createSuccess: '',
        createError: '',
        createForm: {
            title: '',
            description: '',
            priority: 'medium',
            due_at: '',
            assignee_ids: [],
            source_type: '',
            source_id: '',
        },

        // ── Toasts ──────────────────────────────────────────────────────────────
        toasts: [],

        // ── Computed ────────────────────────────────────────────────────────────
        get sortedAssignees() {
            const me     = this.assignees.filter(m => m.id === currentUserId);
            const others = this.assignees.filter(m => m.id !== currentUserId);
            return [...me, ...others];
        },

        // ── Init ────────────────────────────────────────────────────────────────
        init() {
            // Load saved view preference if not set by server
            const savedView = localStorage.getItem('rb_tasks_view_' + tenantId);
            if (!initialView && savedView && ['list','kanban'].includes(savedView)) {
                this.view = savedView;
            }

            // Auto-open create modal from URL param
            const params = new URLSearchParams(window.location.search);
            if (params.get('create') === '1') {
                this.$nextTick(() => {
                    this.createOpen = true;
                    this.fetchAssignees();
                });
            }
        },

        // ── View switching ───────────────────────────────────────────────────────
        switchView(v) {
            if (this.view === v) return;
            localStorage.setItem('rb_tasks_view_' + tenantId, v);
            // Navigate to preserve server-rendered data
            const url = new URL(window.location.href);
            url.searchParams.set('view', v);
            window.location.href = url.toString();
        },

        // ── Style helpers ────────────────────────────────────────────────────────
        colDotStyle(key) {
            const m = { open:'background:#7B61FF', in_progress:'background:#2563eb', waiting:'background:#d97706', completed:'background:#16a34a' };
            return m[key] || 'background:#9ca3af';
        },
        priorityStyle(p) {
            const m = {
                urgent: 'background:#fef2f2;color:#dc2626',
                high:   'background:#fff7ed;color:#ea580c',
                medium: 'background:#fffbeb;color:#d97706',
                low:    'background:#f3f4f6;color:#6b7280',
            };
            return m[p] || m.low;
        },

        // ── Toast ────────────────────────────────────────────────────────────────
        toast(msg, type = 'info', duration = 4000) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, msg, type });
            setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, duration);
        },

        // ── Column helpers ───────────────────────────────────────────────────────
        findColumn(key) { return this.columns.find(c => c.key === key); },
        findTask(taskId) {
            for (const col of this.columns) {
                const t = col.tasks.find(t => t.id === taskId);
                if (t) return { task: t, col };
            }
            return null;
        },
        removeTaskFromCol(taskId, colKey) {
            const col = this.findColumn(colKey);
            if (!col) return null;
            const idx = col.tasks.findIndex(t => t.id === taskId);
            if (idx === -1) return null;
            return col.tasks.splice(idx, 1)[0];
        },
        addTaskToCol(task, colKey) {
            const col = this.findColumn(colKey);
            if (!col) return;
            task.status = colKey;
            col.tasks.unshift(task);
        },

        // ── Drag & Drop ───────────────────────────────────────────────────────────
        onDragStart(event, task, sourceColKey) {
            if (!task.can_update_status) return;
            this.dragTask      = task;
            this.dragTaskId    = task.id;
            this.dragSourceCol = sourceColKey;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', task.id);
        },
        onDragEnd() {
            this.dragOver   = null;
            this.dragTaskId = null;
        },
        onDrop(event, targetColKey) {
            this.dragOver = null;
            if (!this.dragTask) return;
            if (targetColKey === this.dragSourceCol) { this.dragTask = null; return; }

            const task = this.dragTask;
            this.dragTask = null;

            // Completion: show modal if applicable
            if (targetColKey === 'completed') {
                this.initiateComplete(task, this.dragSourceCol);
                return;
            }

            this.performMove(task, this.dragSourceCol, targetColKey);
        },

        // ── Non-drag quick move ───────────────────────────────────────────────────
        async quickMove(task, sourceColKey, targetStatus) {
            if (!task.can_update_status) return;
            if (task.status === targetStatus) return;
            if (targetStatus === 'completed') {
                this.initiateComplete(task, sourceColKey);
                return;
            }
            await this.performMove(task, sourceColKey, targetStatus);
        },

        // ── Optimistic move ───────────────────────────────────────────────────────
        async performMove(task, sourceColKey, targetColKey) {
            if (this.movingTaskId === task.id) return;
            this.movingTaskId = task.id;

            // Optimistic UI
            const removed = this.removeTaskFromCol(task.id, sourceColKey);

            try {
                const res = await fetch(`/tenant/${tenantId}/tasks/${task.id}/status`, {
                    method:  'PATCH',
                    headers: JSON_H(),
                    body:    JSON.stringify({ status: targetColKey }),
                });
                const data = await res.json();

                if (!res.ok) {
                    // Revert
                    if (removed) this.addTaskToCol(removed, sourceColKey);
                    this.toast(data.error || 'Unable to move task. Please try again.', 'error');
                    return;
                }

                // Add updated card to target column
                const card = data.card || removed;
                this.addTaskToCol({ ...card, status: targetColKey }, targetColKey);
                this.toast(data.message || 'Task moved.', 'success');
            } catch (e) {
                if (removed) this.addTaskToCol(removed, sourceColKey);
                this.toast('Network error. Please try again.', 'error');
            } finally {
                this.movingTaskId = null;
            }
        },

        // ── Completion flow ────────────────────────────────────────────────────────
        initiateComplete(task, sourceColKey) {
            if (!task.can_complete) {
                this.toast('You cannot complete this task.', 'error');
                return;
            }
            this.completionTask      = task;
            this.completionSourceCol = sourceColKey;
            this.sendEmail           = false;
            this.completionSubject   = '';
            this.completionBody      = '';
            this.completionSuccess   = '';
            this.completionError     = '';
            this.completionOpen      = true;
        },
        cancelCompletion() {
            this.completionOpen    = false;
            this.completionTask    = null;
            this.completionError   = '';
            this.completionSuccess = '';
        },
        async submitCompletion(withEmail) {
            if (this.completionSubmitting) return;
            const task = this.completionTask;
            if (!task) return;

            if (withEmail && (!this.completionSubject.trim() || !this.completionBody.trim())) {
                this.completionError = 'Subject and message are required when sending a reply.';
                return;
            }

            this.completionSubmitting = true;
            this.completionError      = '';
            this.completionSuccess    = '';

            const payload = { status: 'completed', send_email: withEmail };
            if (withEmail) {
                payload.subject = this.completionSubject.trim();
                payload.body    = this.completionBody.trim();
            }

            // Optimistic move
            const removed = this.removeTaskFromCol(task.id, this.completionSourceCol);

            try {
                const res = await fetch(`/tenant/${tenantId}/tasks/${task.id}/status`, {
                    method:  'PATCH',
                    headers: JSON_H(),
                    body:    JSON.stringify(payload),
                });
                const data = await res.json();

                if (!res.ok) {
                    if (removed) this.addTaskToCol(removed, this.completionSourceCol);
                    this.completionError = data.error || 'Could not complete task.';
                    return;
                }

                const card = data.card || removed;
                this.addTaskToCol({ ...card, status: 'completed' }, 'completed');
                this.completionSuccess = data.message || 'Task marked as done.';
                setTimeout(() => { this.cancelCompletion(); }, 1500);
                this.toast(data.message || 'Task completed.', 'success');
            } catch (e) {
                if (removed) this.addTaskToCol(removed, this.completionSourceCol);
                this.completionError = 'Network error. Please try again.';
            } finally {
                this.completionSubmitting = false;
            }
        },

        // ── Create task ────────────────────────────────────────────────────────────
        toggleAssignToMe() {
            const idx = this.createForm.assignee_ids.indexOf(currentUserId);
            if (idx === -1) this.createForm.assignee_ids.push(currentUserId);
            else this.createForm.assignee_ids.splice(idx, 1);
        },
        async fetchAssignees() {
            this.loadingAssignees = true;
            try {
                const r = await fetch(`/tenant/${tenantId}/tasks/eligible-assignees`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                });
                const data = await r.json();
                this.assignees = Array.isArray(data) ? data : (data.data || []);
            } catch (e) {
                this.assignees = [];
            } finally {
                this.loadingAssignees = false;
            }
        },
        async submitTask() {
            if (!this.createForm.title.trim() || this.createForm.assignee_ids.length === 0) return;
            this.createSubmitting = true;
            this.createSuccess = '';
            this.createError   = '';

            const payload = { ...this.createForm };
            if (!payload.source_type) delete payload.source_type;
            if (!payload.source_id)   delete payload.source_id;

            try {
                const r = await fetch(`/tenant/${tenantId}/tasks`, {
                    method:  'POST',
                    headers: JSON_H(),
                    body:    JSON.stringify(payload),
                });
                const data = await r.json();
                if (!r.ok) {
                    this.createError = data.message || data.error || 'Failed to create task.';
                    return;
                }
                this.createSuccess = data.self_assigned ? 'Task created and assigned to you.' : (data.message || 'Task created.');
                setTimeout(() => {
                    this.createOpen = false;
                    window.location.reload();
                }, 1200);
            } catch (e) {
                this.createError = 'Network error. Please try again.';
            } finally {
                this.createSubmitting = false;
            }
        },
    };
}
</script>
@endpush
@endsection
