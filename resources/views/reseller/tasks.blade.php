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
            <p class="text-sm text-gray-400 mt-0.5">Tasks assigned to you by {{ $tenant->name }}.</p>
        </div>
        @if($openCount > 0)
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-white shrink-0"
              style="background:linear-gradient(135deg,#7B61FF,#5b4cdb)">
            {{ $openCount }} open {{ $openCount === 1 ? 'task' : 'tasks' }}
        </span>
        @endif
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

        init() {},

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
