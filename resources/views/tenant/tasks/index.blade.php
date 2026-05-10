@extends('layouts.app')
@section('title', 'Tasks')
@section('nav') @include('tenant._nav') @endsection

@section('content')
<div class="space-y-5" x-data="createTaskModal('{{ $tenant->id }}', '{{ $actorId }}', '{{ addslashes($actorName) }}')" x-init="init()">

    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div>
            <h1 style="font-size:20px;font-weight:700;color:#1E1B4B">Tasks</h1>
            <p style="font-size:13px;color:#9ca3af;margin-top:2px">Manage requests, assignments, and action items.</p>
        </div>
        <button @click="openModal()"
                style="display:flex;align-items:center;gap:7px;padding:10px 18px;border-radius:12px;background:linear-gradient(135deg,#7B61FF,#5b4cdb);color:white;border:none;font-size:13px;font-weight:600;cursor:pointer;box-shadow:0 4px 14px rgba(123,97,255,0.25)">
            <svg style="width:15px;height:15px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Create Task
        </button>
    </div>

    {{-- Tab filters --}}
    <div style="display:flex;gap:6px;flex-wrap:wrap">
        @foreach([['mine','My Tasks'],['all','All Tasks'],['overdue','Overdue'],['completed','Completed']] as [$key,$label])
        <a href="{{ request()->fullUrlWithQuery(['tab' => $key]) }}"
           style="padding:7px 16px;font-size:13px;font-weight:600;border-radius:9999px;text-decoration:none;transition:all .15s;
                  {{ $tab === $key ? 'background:#7B61FF;color:white;box-shadow:0 4px 12px rgba(123,97,255,0.3)' : 'background:white;color:#9ca3af;border:1.5px solid #e5e7eb' }}">
            {{ $label }}
        </a>
        @endforeach
    </div>

    {{-- Task list --}}
    <div class="card" style="padding:0;overflow:hidden">
        @if($tasks->isEmpty())
        <div style="text-align:center;padding:48px 24px">
            <svg style="width:40px;height:40px;color:#d1d5db;margin:0 auto 12px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p style="font-size:14px;font-weight:600;color:#1E1B4B;margin-bottom:4px">No tasks here</p>
            <p style="font-size:13px;color:#9ca3af">
                @if($tab === 'mine') Tasks assigned to you will appear here.
                @elseif($tab === 'overdue') No overdue tasks. Great work!
                @else No tasks found.
                @endif
            </p>
            <button @click="openModal()"
                    style="margin-top:16px;padding:8px 20px;border-radius:10px;background:#ede9fe;color:#7B61FF;border:none;font-size:13px;font-weight:600;cursor:pointer">
                + Create your first task
            </button>
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
        $statusStyle = match($task->status) {
            'completed'  => 'background:#dcfce7;color:#15803d',
            'in_progress'=> 'background:#dbeafe;color:#2563eb',
            'cancelled'  => 'background:#fee2e2;color:#dc2626',
            default      => 'background:#ede9fe;color:#7B61FF',
        };
        @endphp
        <a href="{{ route('tenant.tasks.show', [$tenant->id, $task->id]) }}"
           style="display:flex;align-items:flex-start;gap:14px;padding:14px 16px;border-bottom:1px solid #f9fafb;text-decoration:none;transition:background .1s;background:white"
           onmouseover="this.style.background='#fafafa'" onmouseout="this.style.background='white'">
            <div style="width:8px;height:8px;border-radius:9999px;flex-shrink:0;margin-top:6px;
                        {{ $task->priority === 'urgent' ? 'background:#dc2626' : ($task->priority === 'high' ? 'background:#ea580c' : ($task->priority === 'medium' ? 'background:#d97706' : 'background:#9ca3af')) }}">
            </div>
            <div style="flex:1;min-width:0">
                <div style="display:flex;align-items:flex-start;gap:8px;flex-wrap:wrap;margin-bottom:4px">
                    <p style="font-size:14px;font-weight:600;color:#1E1B4B">{{ $task->title }}</p>
                    <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:9999px;flex-shrink:0;{{ $statusStyle }}">
                        {{ str_replace('_', ' ', ucfirst($task->status)) }}
                    </span>
                    @if($task->category === 'request_form')
                    <span style="font-size:10px;font-weight:600;padding:2px 8px;border-radius:9999px;background:#f0fdf4;color:#16a34a;flex-shrink:0">Request Form</span>
                    @elseif($task->category === 'manual')
                    <span style="font-size:10px;font-weight:600;padding:2px 8px;border-radius:9999px;background:#f5f3ff;color:#7B61FF;flex-shrink:0">Manual</span>
                    @endif
                </div>
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;font-size:12px;color:#9ca3af">
                    @if($task->requestor_name) <span>From: {{ $task->requestor_name }}</span> @endif
                    @if($task->due_at)
                    <span style="{{ $task->isOverdue() ? 'color:#dc2626;font-weight:600' : '' }}">
                        Due {{ $task->due_at->format('M j, Y') }}{{ $task->isOverdue() ? ' (overdue)' : '' }}
                    </span>
                    @endif
                    <span>{{ $task->created_at->diffForHumans() }}</span>
                </div>
            </div>
            <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:9999px;flex-shrink:0;{{ $priorityStyle }}">{{ ucfirst($task->priority) }}</span>
        </a>
        @endforeach
        <div style="padding:12px 16px">{{ $tasks->links() }}</div>
        @endif
    </div>

    {{-- Create Task Modal --}}
    <template x-teleport="body">
    <div x-show="open" style="display:none" @keydown.escape.window="open = false">
        <div style="position:fixed;inset:0;z-index:9998;background:rgba(0,0,0,0.45);backdrop-filter:blur(2px)"
             @click="open = false"></div>
        <div style="position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;padding:16px;pointer-events:none">

        {{-- Panel --}}
        <div style="position:relative;background:white;border-radius:20px;box-shadow:0 24px 80px rgba(0,0,0,0.18);width:100%;max-width:540px;max-height:90vh;overflow-y:auto;pointer-events:all"
             @click.stop>

            {{-- Header --}}
            <div style="display:flex;align-items:center;justify-content:space-between;padding:20px 24px 0">
                <h2 style="font-size:17px;font-weight:700;color:#1E1B4B">Create Task</h2>
                <button @click="open = false" style="width:28px;height:28px;border-radius:9999px;background:#f3f4f6;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center">
                    <svg style="width:14px;height:14px;color:#6b7280" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Body --}}
            <div style="padding:20px 24px 24px" class="space-y-4">

                {{-- Success --}}
                <div x-show="success" style="padding:10px 14px;background:#dcfce7;border-radius:10px;font-size:13px;font-weight:600;color:#15803d" x-text="success"></div>
                {{-- Error --}}
                <div x-show="error" style="padding:10px 14px;background:#fef2f2;border-radius:10px;font-size:13px;color:#dc2626" x-text="error"></div>

                {{-- Title --}}
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:4px">Task Title *</label>
                    <input type="text" x-model="form.title" maxlength="200"
                           style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:14px;color:#1E1B4B;background:white;outline:none;box-sizing:border-box"
                           placeholder="e.g. Prepare LGU proposal for Quezon City" @focus="$el.style.borderColor='#7B61FF'" @blur="$el.style.borderColor='#e5e7eb'">
                </div>

                {{-- Description --}}
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:4px">Description</label>
                    <textarea x-model="form.description" maxlength="2000" rows="3"
                              style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;color:#1E1B4B;background:white;outline:none;resize:none;box-sizing:border-box"
                              placeholder="Optional — add details, context, or requirements"
                              @focus="$el.style.borderColor='#7B61FF'" @blur="$el.style.borderColor='#e5e7eb'"></textarea>
                </div>

                {{-- Priority + Due Date row --}}
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:4px">Priority *</label>
                        <select x-model="form.priority"
                                style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;color:#1E1B4B;background:white;outline:none;box-sizing:border-box">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:4px">Due Date</label>
                        <input type="date" x-model="form.due_at"
                               style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;color:#1E1B4B;background:white;outline:none;box-sizing:border-box"
                               @focus="$el.style.borderColor='#7B61FF'" @blur="$el.style.borderColor='#e5e7eb'">
                    </div>
                </div>

                {{-- Assignees --}}
                <div>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
                        <label style="font-size:12px;font-weight:600;color:#374151">Assign To *</label>
                        {{-- Assign to me quick chip --}}
                        <button type="button" @click="assignToMe()"
                                style="display:flex;align-items:center;gap:5px;padding:4px 12px;border-radius:9999px;font-size:11px;font-weight:700;cursor:pointer;border:1.5px solid;transition:all .12s"
                                :style="form.assignee_ids.includes(currentUserId)
                                    ? 'background:#7B61FF;color:white;border-color:#7B61FF'
                                    : 'background:#ede9fe;color:#7B61FF;border-color:#c4b5fd'">
                            <svg style="width:11px;height:11px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            <span x-text="form.assignee_ids.includes(currentUserId) ? '✓ Assigned to me' : 'Assign to me'"></span>
                        </button>
                    </div>

                    {{-- Loading assignees --}}
                    <div x-show="loadingAssignees" style="padding:8px;font-size:12px;color:#9ca3af">Loading team members…</div>

                    {{-- Assignee list — current user pinned to top --}}
                    <div x-show="!loadingAssignees" class="space-y-2" style="max-height:180px;overflow-y:auto">
                        <template x-for="m in sortedAssignees" :key="m.id">
                            <label style="display:flex;align-items:center;gap:10px;padding:9px 12px;background:#f9fafb;border-radius:10px;cursor:pointer;border:1.5px solid transparent"
                                   :style="form.assignee_ids.includes(m.id) ? 'border-color:#c4b5fd;background:#f5f3ff' : ''">
                                <input type="checkbox" :value="m.id" x-model="form.assignee_ids" style="width:15px;height:15px;cursor:pointer;accent-color:#7B61FF">
                                <div style="width:28px;height:28px;border-radius:9999px;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;flex-shrink:0"
                                     :style="m.is_me ? 'background:#7B61FF;color:white' : 'background:#ede9fe;color:#7B61FF'"
                                     x-text="(m.name||'?').slice(0,2).toUpperCase()"></div>
                                <div style="flex:1;min-width:0">
                                    <div style="display:flex;align-items:center;gap:6px">
                                        <p style="font-size:13px;font-weight:600;color:#1E1B4B" x-text="m.is_me ? m.name + ' (you)' : m.name"></p>
                                    </div>
                                    <p style="font-size:11px;color:#9ca3af" x-text="m.email + ' · ' + (m.role ? m.role.charAt(0).toUpperCase() + m.role.slice(1) : '')"></p>
                                </div>
                            </label>
                        </template>
                        <template x-if="!loadingAssignees && assignees.length === 0">
                            <p style="font-size:12px;color:#d97706;padding:8px">No eligible team members found.</p>
                        </template>
                    </div>
                    <p x-show="form.assignee_ids.length > 1" style="font-size:11px;color:#7B61FF;margin-top:6px">
                        One task will be created per assignee (<span x-text="form.assignee_ids.length"></span> total).
                    </p>
                </div>

                {{-- Actions --}}
                <div style="display:flex;gap:10px;justify-content:flex-end;padding-top:4px">
                    <button type="button" @click="open = false"
                            style="padding:9px 20px;border-radius:10px;border:1.5px solid #e5e7eb;background:white;color:#374151;font-size:13px;font-weight:600;cursor:pointer">
                        Cancel
                    </button>
                    <button type="button" @click="submitTask()" :disabled="submitting || !form.title.trim() || form.assignee_ids.length === 0"
                            style="padding:9px 24px;border-radius:10px;background:linear-gradient(135deg,#7B61FF,#5b4cdb);color:white;border:none;font-size:13px;font-weight:600;cursor:pointer;box-shadow:0 4px 14px rgba(123,97,255,0.25)"
                            :style="(submitting || !form.title.trim() || form.assignee_ids.length === 0) ? 'opacity:0.5;cursor:not-allowed' : ''">
                        <span x-show="!submitting">Create Task</span>
                        <span x-show="submitting">Creating…</span>
                    </button>
                </div>
            </div>
        </div>
        </div>{{-- flex wrapper --}}
    </div>{{-- x-show --}}
    </template>{{-- x-teleport --}}

</div>

@push('scripts')
<script>
function createTaskModal(tenantId, currentUserId, currentUserName) {
    return {
        open: false,
        loadingAssignees: false,
        submitting: false,
        assignees: [],
        currentUserId: currentUserId || '',
        currentUserName: currentUserName || '',
        success: '',
        error: '',
        form: {
            title: '',
            description: '',
            priority: 'medium',
            due_at: '',
            assignee_ids: [],
            source_type: '',
            source_id: '',
        },

        get sortedAssignees() {
            // Pin current user to top of list
            const me    = this.assignees.filter(m => m.id === this.currentUserId);
            const others = this.assignees.filter(m => m.id !== this.currentUserId);
            return [...me, ...others];
        },

        init() {
            const params = new URLSearchParams(window.location.search);
            if (params.get('create') === '1' || params.get('source_type')) {
                this.$nextTick(() => {
                    this.form.source_type = params.get('source_type') || '';
                    this.form.source_id   = params.get('source_id') || '';
                    this.openModal();
                });
            }
        },

        openModal() {
            this.open = true;
            this.success = '';
            this.error = '';
            if (this.assignees.length === 0) this.fetchAssignees();
        },

        assignToMe() {
            if (!this.currentUserId) return;
            const idx = this.form.assignee_ids.indexOf(this.currentUserId);
            if (idx === -1) {
                this.form.assignee_ids.push(this.currentUserId);
            } else {
                this.form.assignee_ids.splice(idx, 1);
            }
        },

        fetchAssignees() {
            this.loadingAssignees = true;
            fetch(`/tenant/${tenantId}/tasks/eligible-assignees`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(data => {
                this.assignees = Array.isArray(data) ? data : (data.data || []);
            })
            .catch(() => { this.assignees = []; })
            .finally(() => { this.loadingAssignees = false; });
        },

        submitTask() {
            if (!this.form.title.trim() || this.form.assignee_ids.length === 0) return;
            this.submitting = true;
            this.success = '';
            this.error = '';

            const payload = { ...this.form };
            if (!payload.source_type) delete payload.source_type;
            if (!payload.source_id)   delete payload.source_id;

            const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
            fetch(`/tenant/${tenantId}/tasks`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            })
            .then(async r => {
                const data = await r.json();
                if (!r.ok) {
                    this.error = data.message || data.error || 'Failed to create task.';
                    return;
                }
                this.success = data.self_assigned
                    ? 'Task created and assigned to you.'
                    : (data.message || 'Task created successfully.');
                setTimeout(() => {
                    this.open = false;
                    window.location.reload();
                }, 1200);
            })
            .catch(() => { this.error = 'Network error. Please try again.'; })
            .finally(() => { this.submitting = false; });
        },
    };
}
</script>
@endpush
@endsection
