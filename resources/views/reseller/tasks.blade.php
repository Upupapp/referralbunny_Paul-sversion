@extends('layouts.reseller')
@section('title', 'My Tasks')
@section('nav') @include('reseller._nav') @endsection

@section('content')
<div class="max-w-3xl mx-auto space-y-4"
     x-data="referrerTasks('{{ $tenant->id }}')"
     x-init="init()">

    {{-- ── HEADER ──────────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[#1E1B4B]">My Tasks</h1>
            <p class="text-sm text-gray-400 mt-0.5">Tasks assigned to you by {{ $tenant->name }} or created by you.</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @if($openCount > 0)
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-white shrink-0"
                  style="background:linear-gradient(135deg,#7B61FF,#5b4cdb)">
                {{ $openCount }} open {{ $openCount === 1 ? 'task' : 'tasks' }}
            </span>
            @endif
            {{-- Add Task button --}}
            <button @click="createOpen = true"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white transition-all"
                    style="background:linear-gradient(135deg,#7B61FF,#5b4cdb);box-shadow:0 4px 14px rgba(123,97,255,.25)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Task
            </button>
        </div>
    </div>

    {{-- ── TABS ────────────────────────────────────────────────────────────── --}}
    <div class="flex gap-1.5">
        <a href="{{ request()->url() }}?tab=open"
           class="px-3.5 py-1.5 text-xs font-semibold rounded-full transition-all no-underline"
           style="{{ $tab === 'open' || $tab === ''
                ? 'background:#7B61FF;color:white;box-shadow:0 4px 12px rgba(123,97,255,.3)'
                : 'background:white;color:#9ca3af;border:1.5px solid #e5e7eb' }}">
            Open Tasks @if($openCount > 0)<span class="ml-1 opacity-80">({{ $openCount }})</span>@endif
        </a>
        <a href="{{ request()->url() }}?tab=completed"
           class="px-3.5 py-1.5 text-xs font-semibold rounded-full transition-all no-underline"
           style="{{ $tab === 'completed'
                ? 'background:#7B61FF;color:white;box-shadow:0 4px 12px rgba(123,97,255,.3)'
                : 'background:white;color:#9ca3af;border:1.5px solid #e5e7eb' }}">
            Completed
        </a>
    </div>

    {{-- ── TASK LIST ───────────────────────────────────────────────────────── --}}
    @if($tasks->isEmpty())
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 flex flex-col items-center justify-center py-16 text-center px-6">
        <div class="w-14 h-14 rounded-2xl flex items-center justify-center mb-4" style="background:#EDE9FE">
            <svg class="w-7 h-7" style="color:#7B61FF" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <p class="text-sm font-semibold text-[#1E1B4B]">
            {{ $tab === 'completed' ? 'No completed tasks yet' : 'No open tasks' }}
        </p>
        <p class="text-xs text-gray-400 mt-1 max-w-xs">
            {{ $tab === 'completed'
                ? 'Tasks you complete will appear here.'
                : 'When ' . $tenant->name . ' assigns tasks to you, they will appear here.' }}
        </p>
    </div>

    @else
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden divide-y divide-gray-50">
        @foreach($tasks as $task)
        @php
            $isOverdue   = $task->due_at && $task->due_at->isPast() && $task->status !== 'completed';
            $priorityDot = match($task->priority) {
                'urgent' => '#dc2626', 'high' => '#ea580c',
                'medium' => '#d97706', default => '#9ca3af',
            };
            $priorityBg = match($task->priority) {
                'urgent' => 'background:#fef2f2;color:#dc2626',
                'high'   => 'background:#fff7ed;color:#ea580c',
                'medium' => 'background:#fffbeb;color:#d97706',
                default  => 'background:#f3f4f6;color:#6b7280',
            };
            $statusBg = match($task->status) {
                'completed'   => 'background:#dcfce7;color:#15803d',
                'in_progress' => 'background:#dbeafe;color:#2563eb',
                'waiting'     => 'background:#fff7ed;color:#ea580c',
                default       => 'background:#ede9fe;color:#7B61FF',
            };
            $statusLabel = $isOverdue ? 'Overdue' : match($task->status) {
                'in_progress' => 'In Progress',
                'waiting'     => 'Waiting',
                'completed'   => 'Completed',
                default       => 'Open',
            };
        @endphp

        <div class="px-4 py-4 sm:px-5"
             x-data="{
                 taskId:     '{{ $task->id }}',
                 status:     '{{ $task->status }}',
                 submitting: false,
                 error:      '',
                 success:    '',
             }">

            {{-- Top row: dot + title + badges --}}
            <div class="flex flex-wrap items-start gap-2 mb-2">
                <span class="w-2 h-2 rounded-full shrink-0 mt-1.5" style="background:{{ $priorityDot }}" aria-hidden="true"></span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-[#1E1B4B] break-words">{{ $task->title }}</p>
                </div>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full shrink-0" style="{{ $priorityBg }}">{{ ucfirst($task->priority) }}</span>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full shrink-0"
                      :style="statusStyle(status)"
                      x-text="statusLabel(status, {{ $isOverdue ? 'true' : 'false' }})">{{ $statusLabel }}</span>
            </div>

            {{-- Description (if any) --}}
            @if($task->description)
            <p class="text-xs text-gray-500 mb-2 ml-4 leading-relaxed line-clamp-2">{{ $task->description }}</p>
            @endif

            {{-- Meta row --}}
            <div class="flex flex-wrap items-center gap-3 text-[11px] text-gray-400 ml-4 mb-3">
                @if($task->requestor_name)
                <span>From: {{ $task->requestor_name }}</span>
                @endif
                @if($task->due_at)
                <span class="{{ $isOverdue ? 'text-red-500 font-semibold' : '' }}">
                    Due {{ $task->due_at->format('M j, Y') }}{{ $isOverdue ? ' — overdue' : '' }}
                </span>
                @endif
                <span>{{ $task->created_at->diffForHumans() }}</span>
                @if($task->status === 'completed' && $task->completed_at)
                <span class="text-emerald-600">Completed {{ $task->completed_at->diffForHumans() }}</span>
                @endif
            </div>

            {{-- Feedback --}}
            <div x-show="error" class="ml-4 mb-2 text-xs text-red-600 font-medium" x-text="error" role="alert"></div>
            <div x-show="success" class="ml-4 mb-2 text-xs text-emerald-600 font-medium" x-text="success" role="status"></div>

            {{-- Actions (only for non-completed tasks) --}}
            @if($task->status !== 'completed' && $task->status !== 'cancelled' && $task->status !== 'archived')
            <div class="flex flex-wrap items-center gap-2 ml-4">

                {{-- Start Working (open → in_progress) --}}
                <template x-if="status === 'open'">
                    <button @click="updateStatus('in_progress')"
                            :disabled="submitting"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors disabled:opacity-50">
                        <svg x-show="submitting" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <svg x-show="!submitting" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Start Working
                    </button>
                </template>

                {{-- Pause (in_progress → waiting) --}}
                <template x-if="status === 'in_progress'">
                    <button @click="updateStatus('waiting')"
                            :disabled="submitting"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-orange-50 text-orange-700 hover:bg-orange-100 transition-colors disabled:opacity-50">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Pause
                    </button>
                </template>

                {{-- Resume (waiting → in_progress) --}}
                <template x-if="status === 'waiting'">
                    <button @click="updateStatus('in_progress')"
                            :disabled="submitting"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors disabled:opacity-50">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Resume
                    </button>
                </template>

                {{-- Mark Complete (always shown for non-terminal tasks) --}}
                <button @click="complete()"
                        :disabled="submitting"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-white transition-all disabled:opacity-50"
                        style="background:linear-gradient(135deg,#10b981,#059669);box-shadow:0 2px 8px rgba(16,185,129,.2)">
                    <svg x-show="submitting" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <svg x-show="!submitting" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    Mark Complete
                </button>

            </div>
            @endif

        </div>
        @endforeach
    </div>

    {{-- Pagination --}}
    @if($tasks->hasPages())
    <div class="flex justify-center pt-2">{{ $tasks->links() }}</div>
    @endif
    @endif

    {{-- ── CREATE TASK MODAL ──────────────────────────────────────────────── --}}
    <template x-teleport="body">
    <div x-show="createOpen" x-cloak
         class="fixed inset-0 z-[9999] flex items-end sm:items-center justify-center p-4"
         style="background:rgba(0,0,0,.5);backdrop-filter:blur(4px)"
         @keydown.escape.window="if(!createSubmitting) { createOpen = false; resetCreate(); }"
         @click.self="if(!createSubmitting) { createOpen = false; resetCreate(); }">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md" @click.stop
             role="dialog" aria-modal="true" aria-labelledby="create-task-title">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <h3 id="create-task-title" class="text-base font-bold text-[#1E1B4B]">Add Task</h3>
                <button @click="createOpen = false; resetCreate()"
                        :disabled="createSubmitting"
                        class="w-8 h-8 rounded-xl bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors" aria-label="Close">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-5 space-y-4">
                <div x-show="createError" class="flex items-center gap-2.5 p-3 bg-red-50 border border-red-200 rounded-xl text-xs text-red-700" role="alert">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span x-text="createError"></span>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Task Title <span class="text-red-500">*</span></label>
                    <input type="text" x-model="createForm.title" maxlength="200" placeholder="What needs to be done?"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-purple-300 focus:ring-1 focus:ring-purple-100"
                           @keydown.enter.prevent="if(createForm.title.trim()) submitCreate()">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Description <span class="text-gray-400 font-normal">(optional)</span></label>
                    <textarea x-model="createForm.description" rows="3" maxlength="2000" placeholder="Add details or notes…"
                              class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm resize-none focus:outline-none focus:border-purple-300 focus:ring-1 focus:ring-purple-100"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Priority</label>
                        <select x-model="createForm.priority"
                                class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-purple-300 cursor-pointer">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Due Date <span class="text-gray-400 font-normal">(optional)</span></label>
                        <input type="date" x-model="createForm.due_at"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-purple-300 cursor-pointer">
                    </div>
                </div>

                <p class="text-[10px] text-gray-400">This task will be assigned to you. Admins and managers can also see and manage it.</p>
            </div>
            <div class="flex justify-end gap-2.5 px-5 py-4 border-t border-gray-100">
                <button @click="createOpen = false; resetCreate()" :disabled="createSubmitting"
                        class="px-4 py-2 rounded-xl text-xs font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200 transition-colors">Cancel</button>
                <button @click="submitCreate()"
                        :disabled="createSubmitting || !createForm.title.trim()"
                        class="flex items-center gap-2 px-5 py-2 rounded-xl text-xs font-semibold text-white transition-all disabled:opacity-50"
                        style="background:linear-gradient(135deg,#7B61FF,#5b4cdb)">
                    <svg x-show="createSubmitting" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="createSubmitting ? 'Creating…' : 'Create Task'"></span>
                </button>
            </div>
        </div>
    </div>
    </template>

    {{-- Toast --}}
    <div class="fixed bottom-5 right-5 z-[200] flex flex-col gap-2 items-end pointer-events-none" aria-live="polite" style="max-width:320px">
        <template x-for="t in toasts" :key="t.id">
            <div class="pointer-events-auto flex items-center gap-3 px-4 py-3 rounded-2xl shadow-2xl text-xs font-semibold w-full"
                 role="alert"
                 :class="{'bg-emerald-600 text-white': t.type==='success', 'bg-red-600 text-white': t.type==='error', 'bg-[#1E1B4B] text-white': t.type==='info'}"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0">
                <span class="flex-1" x-text="t.msg"></span>
                <button @click="toasts = toasts.filter(x => x.id !== t.id)" class="opacity-60 hover:opacity-100" aria-label="Dismiss">✕</button>
            </div>
        </template>
    </div>

</div>

@push('scripts')
<script>
function referrerTasks(tenantId) {
    const CSRF   = () => document.querySelector('meta[name=csrf-token]')?.content ?? '';
    const JSON_H = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

    return {
        toasts: [],

        // Create task modal
        createOpen:       false,
        createSubmitting: false,
        createError:      '',
        createForm:       { title: '', description: '', priority: 'medium', due_at: '' },

        init() {},

        resetCreate() {
            this.createForm = { title: '', description: '', priority: 'medium', due_at: '' };
            this.createError = '';
        },

        async submitCreate() {
            if (!this.createForm.title.trim()) return;
            this.createSubmitting = true; this.createError = '';
            try {
                const res = await fetch(`/reseller/${tenantId}/tasks`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(this.createForm),
                });
                const json = await res.json();
                if (!res.ok) { this.createError = json.message || json.error || 'Could not create task.'; return; }
                this.createOpen = false;
                this.resetCreate();
                this.toast('Task created!', 'success');
                setTimeout(() => window.location.reload(), 1000);
            } catch (e) {
                this.createError = 'Network error. Please try again.';
            } finally {
                this.createSubmitting = false;
            }
        },

        statusStyle(s) {
            return {
                completed:   'background:#dcfce7;color:#15803d',
                in_progress: 'background:#dbeafe;color:#2563eb',
                waiting:     'background:#fff7ed;color:#ea580c',
                open:        'background:#ede9fe;color:#7B61FF',
            }[s] || 'background:#f3f4f6;color:#6b7280';
        },

        statusLabel(s, overdue) {
            if (overdue && s !== 'completed') return 'Overdue';
            return { completed: 'Completed', in_progress: 'In Progress', waiting: 'Waiting', open: 'Open' }[s] || s;
        },

        toast(msg, type = 'info', dur = 4000) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, msg, type });
            setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, dur);
        },

        // Called on the task-level x-data instance, not the page-level one
        async updateStatus(newStatus) {
            this.submitting = true; this.error = ''; this.success = '';
            try {
                const res = await fetch(`/reseller/${tenantId}/tasks/${this.taskId}/status`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ status: newStatus }),
                });
                const json = await res.json();
                if (!res.ok) { this.error = json.error || 'Could not update status.'; return; }
                this.status = json.status;
                this.success = { in_progress: 'Started!', waiting: 'Paused.', open: 'Moved back to open.' }[newStatus] || 'Updated.';
                setTimeout(() => { this.success = ''; }, 3000);
            } catch (e) {
                this.error = 'Network error. Please try again.';
            } finally {
                this.submitting = false;
            }
        },

        async complete() {
            this.submitting = true; this.error = ''; this.success = '';
            try {
                const res = await fetch(`/reseller/${tenantId}/tasks/${this.taskId}/complete`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({}),
                });
                const json = await res.json();
                if (!res.ok) { this.error = json.error || 'Could not complete task.'; return; }
                this.status = 'completed';
                this.success = 'Task completed!';
                // Refresh the page after a moment so the task moves to Completed tab
                setTimeout(() => window.location.reload(), 1500);
            } catch (e) {
                this.error = 'Network error. Please try again.';
            } finally {
                this.submitting = false;
            }
        },
    };
}
</script>
@endpush

@endsection
