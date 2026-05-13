<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Mail\ManualTaskAssignedMail;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Services\TaskCompletionService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class TaskController extends Controller
{
    // ── Eligible Assignees (JSON API for modal) ───────────────────────────────

    public function eligibleAssignees(Request $request, string $tenantId): \Illuminate\Http\JsonResponse
    {
        $this->authorizeAdmin($tenantId);

        [$actorType, $actorId] = $this->resolveActor();

        $members = DB::table('tenant_users as tu')
            ->join('tenant_memberships as tm', 'tm.tenant_user_id', '=', 'tu.id')
            ->where('tm.tenant_id', $tenantId)
            ->where('tm.status', 'active')
            ->selectRaw("tu.id, TRIM(CONCAT(COALESCE(tu.first_name,''), ' ', COALESCE(tu.last_name,''))) as name, tu.email, tm.role")
            ->orderBy('tm.role')
            ->orderBy('tu.first_name')
            ->get()
            ->map(fn ($m) => array_merge((array) $m, [
                'is_me' => ($m->id === $actorId),
                'type'  => 'tenant_user',
            ]));

        // Active referrers for this tenant — prefixed with 'reseller:' so store() can distinguish them
        $referrers = DB::table('resellers')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get()
            ->map(fn ($r) => [
                'id'    => 'reseller:' . $r->id,
                'name'  => $r->name ?: $r->email,
                'email' => $r->email,
                'role'  => 'referrer',
                'type'  => 'reseller',
                'is_me' => false,
            ]);

        return response()->json($members->concat($referrers)->values());
    }

    // ── Store (manual task creation) ──────────────────────────────────────────

    public function store(Request $request, string $tenantId): \Illuminate\Http\JsonResponse
    {
        $this->authorizeAdmin($tenantId);

        $data = $request->validate([
            'title'           => 'required|string|max:200',
            'description'     => 'nullable|string|max:2000',
            'priority'        => 'required|in:low,medium,high,urgent',
            'due_at'          => 'nullable|date',
            'assignee_ids'    => 'required|array|min:1|max:10',
            'assignee_ids.*'  => 'required|string|max:64',
            'source_type'     => 'nullable|in:deal,lead,contact,referrer,partner',
            'source_id'       => 'nullable|string|uuid',
        ]);

        [$actorType, $actorId, $actorName] = $this->resolveActorFull();

        // Resolve 'me' to the authenticated user's ID (server-side — never trust frontend user ID alone)
        $rawIds = collect($data['assignee_ids'])->map(fn ($id) => $id === 'me' ? $actorId : $id)->unique()->filter();

        // Split into tenant_user IDs and reseller IDs (resellers use 'reseller:UUID' prefix from eligibleAssignees API)
        $resellerRawIds  = $rawIds->filter(fn ($id) => str_starts_with((string) $id, 'reseller:'))
                                  ->map(fn ($id) => substr($id, strlen('reseller:')));
        $tenantUserIds   = $rawIds->reject(fn ($id) => str_starts_with((string) $id, 'reseller:'));

        // Validate tenant_user assignees — must be active members of this tenant
        $validAssignees = $tenantUserIds->isNotEmpty()
            ? DB::table('tenant_users as tu')
                ->join('tenant_memberships as tm', 'tm.tenant_user_id', '=', 'tu.id')
                ->where('tm.tenant_id', $tenantId)
                ->where('tm.status', 'active')
                ->whereIn('tu.id', $tenantUserIds->toArray())
                ->selectRaw("tu.id, TRIM(CONCAT(COALESCE(tu.first_name,''), ' ', COALESCE(tu.last_name,''))) as name, tu.email, 'tenant_user' as assignee_type")
                ->get()->keyBy('id')
            : collect();

        // Validate reseller assignees — must be active resellers for this tenant
        $validResellers = $resellerRawIds->isNotEmpty()
            ? \App\Models\Reseller::where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->whereIn('id', $resellerRawIds->toArray())
                ->get()->keyBy('id')
            : collect();

        if ($validAssignees->isEmpty() && $validResellers->isEmpty()) {
            return response()->json(['error' => 'No valid assignees found.'], 422);
        }

        $createdTasks   = [];
        $isSelfAssign   = $tenantUserIds->count() === 1 && $resellerRawIds->isEmpty() && $tenantUserIds->first() === $actorId;

        DB::transaction(function () use (
            $data, $tenantId, $actorType, $actorId, $actorName,
            $tenantUserIds, $validAssignees, $resellerRawIds, $validResellers,
            $isSelfAssign, &$createdTasks
        ) {
            // ── Tenant user tasks ──────────────────────────────────────────────
            foreach ($tenantUserIds as $assigneeId) {
                $assignee = $validAssignees->get($assigneeId);
                if (!$assignee) continue;

                $task = Task::create([
                    'tenant_id'         => $tenantId,
                    'title'             => $data['title'],
                    'description'       => $data['description'] ?? null,
                    'status'            => 'open',
                    'priority'          => $data['priority'],
                    'category'          => 'manual',
                    'assigned_to_type'  => 'tenant_user',
                    'assigned_to_id'    => $assigneeId,
                    'assigned_by_type'  => $actorType,
                    'assigned_by_id'    => $actorId,
                    'created_by_type'   => $actorType,
                    'created_by_id'     => $actorId,
                    'source_type'       => $data['source_type'] ?? null,
                    'source_id'         => $data['source_id'] ?? null,
                    'due_at'            => !empty($data['due_at']) ? $data['due_at'] : null,
                    'visibility'        => 'tenant',
                ]);

                TaskActivity::create([
                    'tenant_id'   => $tenantId,
                    'task_id'     => $task->id,
                    'actor_type'  => $actorType,
                    'actor_id'    => $actorId,
                    'actor_name'  => $actorName,
                    'action_type' => ($assigneeId === $actorId) ? 'task_created_self_assigned' : 'task_created',
                    'new_values'  => [
                        'title'       => $task->title,
                        'priority'    => $task->priority,
                        'assignee'    => $assignee->name,
                        'self_assign' => ($assigneeId === $actorId),
                        'due_at'      => $task->due_at?->toDateString(),
                    ],
                ]);

                $createdTasks[] = ['task' => $task, 'assignee_type' => 'tenant_user', 'assignee' => $assignee];
            }

            // ── Reseller tasks ─────────────────────────────────────────────────
            foreach ($resellerRawIds as $resellerId) {
                $reseller = $validResellers->get($resellerId);
                if (!$reseller) continue;

                $task = Task::create([
                    'tenant_id'         => $tenantId,
                    'title'             => $data['title'],
                    'description'       => $data['description'] ?? null,
                    'status'            => 'open',
                    'priority'          => $data['priority'],
                    'category'          => 'manual',
                    'assigned_to_type'  => 'reseller',
                    'assigned_to_id'    => (string) $resellerId,
                    'assigned_by_type'  => $actorType,
                    'assigned_by_id'    => $actorId,
                    'created_by_type'   => $actorType,
                    'created_by_id'     => $actorId,
                    'source_type'       => $data['source_type'] ?? null,
                    'source_id'         => $data['source_id'] ?? null,
                    'due_at'            => !empty($data['due_at']) ? $data['due_at'] : null,
                    'visibility'        => 'tenant',
                ]);

                TaskActivity::create([
                    'tenant_id'   => $tenantId,
                    'task_id'     => $task->id,
                    'actor_type'  => $actorType,
                    'actor_id'    => $actorId,
                    'actor_name'  => $actorName,
                    'action_type' => 'task_created',
                    'new_values'  => [
                        'title'        => $task->title,
                        'priority'     => $task->priority,
                        'assignee'     => $reseller->name ?? $reseller->email,
                        'assignee_type'=> 'reseller',
                        'due_at'       => $task->due_at?->toDateString(),
                    ],
                ]);

                $createdTasks[] = ['task' => $task, 'assignee_type' => 'reseller', 'assignee' => $reseller];
            }
        });

        // After commit: notifications (never inside a transaction)
        $notifSvc = app(\App\Services\NotificationDispatchService::class);

        foreach ($createdTasks as $entry) {
            $task         = $entry['task'];
            $assigneeType = $entry['assignee_type'];
            $assignee     = $entry['assignee'];

            // Skip self-assign notification
            if ($assigneeType === 'tenant_user' && $task->assigned_to_id === $actorId) continue;

            $notifPriority = $task->priority === 'urgent' ? 'urgent' : ($task->priority === 'high' ? 'high' : 'normal');
            $notifBody     = "Assigned by {$actorName}." . ($task->due_at ? " Due {$task->due_at->format('M j, Y')}." : '');

            if ($assigneeType === 'tenant_user') {
                // In-app notification to tenant user
                try {
                    $notifSvc->dispatch(
                        category:         'task',
                        priority:         $notifPriority,
                        title:            'New Task: ' . $task->title,
                        body:             $notifBody,
                        notifiableType:   'tenant_user',
                        notifiableId:     $task->assigned_to_id,
                        tenantId:         $tenantId,
                        actionUrl:        "/tenant/{$tenantId}/tasks/{$task->id}",
                        actionLabel:      'View Task',
                        deduplicationKey: "task_assigned_{$task->id}",
                    );
                } catch (\Throwable) {}

                // Email to tenant user
                if ($assignee->email) {
                    try {
                        Mail::to($assignee->email)->queue(new ManualTaskAssignedMail(
                            assigneeName: $assignee->name,
                            senderName:   $actorName,
                            taskTitle:    $task->title,
                            taskPriority: $task->priority,
                            dueAt:        $task->due_at?->format('M j, Y'),
                            taskUrl:      url("/tenant/{$tenantId}/tasks/{$task->id}"),
                        ));
                    } catch (\Throwable) {}
                }
            } elseif ($assigneeType === 'reseller') {
                // In-app notification to referrer portal
                try {
                    $notifSvc->dispatchToReseller(
                        resellerId:       (string) $assignee->id,
                        tenantId:         $tenantId,
                        category:         'task',
                        priority:         $notifPriority,
                        title:            'New Task: ' . $task->title,
                        body:             $notifBody,
                        actionUrl:        "/reseller/{$tenantId}/tasks",
                        actionLabel:      'View Task',
                        deduplicationKey: "task_assigned_{$task->id}",
                    );
                } catch (\Throwable) {}

                // Email to referrer
                if ($assignee->email) {
                    try {
                        Mail::to($assignee->email)->queue(new ManualTaskAssignedMail(
                            assigneeName: $assignee->name ?? $assignee->email,
                            senderName:   $actorName,
                            taskTitle:    $task->title,
                            taskPriority: $task->priority,
                            dueAt:        $task->due_at?->format('M j, Y'),
                            taskUrl:      url("/reseller/{$tenantId}/tasks"),
                        ));
                    } catch (\Throwable) {}
                }
            }
        }

        $selfMsg  = $isSelfAssign ? 'Task created and assigned to you.' : null;
        $total    = count($createdTasks);
        $multiMsg = $total === 1 ? 'Task created and assignee notified.' : "{$total} tasks created and assignees notified.";

        return response()->json([
            'created'      => $total,
            'task_ids'     => collect($createdTasks)->pluck('task.id'),
            'self_assigned'=> $isSelfAssign,
            'message'      => $selfMsg ?? $multiMsg,
        ]);
    }

    // ── Assign task to self (claim / reassign-to-me) ──────────────────────────

    public function assignToSelf(string $tenantId, string $taskId): \Illuminate\Http\JsonResponse
    {
        // Resolve current user — never trust frontend user ID
        [$actorType, $actorId, $actorName] = $this->resolveActorFull();

        if (!$actorId) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        // Tenant-scoped task lookup
        $task = Task::where('tenant_id', $tenantId)->whereNull('deleted_at')->find($taskId);
        if (!$task) {
            return response()->json(['error' => 'Task not found.'], 404);
        }

        // Tenant isolation — belt + suspenders
        if ($task->tenant_id !== $tenantId) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        // Task must not be in a terminal state
        if (in_array($task->status, ['completed', 'cancelled', 'archived'])) {
            return response()->json(['error' => 'This task is no longer available for assignment.'], 422);
        }

        // Verify actor is an active member of this tenant (super admin web guard is always allowed)
        if (!Auth::guard('web')->check()) {
            $isMember = DB::table('tenant_memberships')
                ->where('tenant_id', $tenantId)
                ->where('tenant_user_id', $actorId)
                ->where('status', 'active')
                ->exists();

            if (!$isMember) {
                return response()->json(['error' => 'You do not have access to this tenant.'], 403);
            }
        }

        // Verify actor can view this task (admin or current assignee)
        $isAdmin = Auth::guard('web')->check()
            || in_array(TenantContext::role(), ['admin', 'owner', 'manager']);
        $isCurrentAssignee = $task->assigned_to_type === $actorType
            && $task->assigned_to_id === $actorId;

        if (!$isAdmin && !$isCurrentAssignee && $task->assigned_to_id) {
            // Non-admins can only claim unassigned tasks they can see
            return response()->json(['error' => 'You do not have permission to assign this task to yourself.'], 403);
        }

        // Idempotent — already assigned to self
        if ($task->assigned_to_type === $actorType && $task->assigned_to_id === $actorId) {
            return response()->json(['message' => 'Task is already assigned to you.', 'already_assigned' => true]);
        }

        $oldAssigneeType = $task->assigned_to_type;
        $oldAssigneeId   = $task->assigned_to_id;
        $oldAssigneeName = $oldAssigneeId
            ? $this->resolveUserName($oldAssigneeType, $oldAssigneeId)
            : null;

        $actionType = $oldAssigneeId ? 'task_reassigned_to_self' : 'task_claimed';

        DB::transaction(function () use (
            $task, $actorType, $actorId, $actorName,
            $tenantId, $oldAssigneeType, $oldAssigneeId, $oldAssigneeName, $actionType
        ) {
            $task->update([
                'assigned_to_type' => $actorType,
                'assigned_to_id'   => $actorId,
                'assigned_by_type' => $actorType,
                'assigned_by_id'   => $actorId,
            ]);

            TaskActivity::create([
                'tenant_id'   => $tenantId,
                'task_id'     => $task->id,
                'actor_type'  => $actorType,
                'actor_id'    => $actorId,
                'actor_name'  => $actorName,
                'action_type' => $actionType,
                'old_values'  => [
                    'assigned_to_id'   => $oldAssigneeId,
                    'assigned_to_name' => $oldAssigneeName,
                ],
                'new_values'  => [
                    'assigned_to_id'   => $actorId,
                    'assigned_to_name' => $actorName,
                ],
            ]);
        });

        // Notify old assignee (only if reassigned from someone else)
        if ($oldAssigneeId && $oldAssigneeId !== $actorId) {
            try {
                app(\App\Services\NotificationDispatchService::class)->dispatch(
                    category:         'task',
                    priority:         'normal',
                    title:            'Task reassigned',
                    body:             "{$actorName} reassigned \"{$task->title}\" to themselves.",
                    notifiableType:   $oldAssigneeType,
                    notifiableId:     $oldAssigneeId,
                    tenantId:         $tenantId,
                    actionUrl:        "/tenant/{$tenantId}/tasks/{$task->id}",
                    actionLabel:      'View Task',
                    deduplicationKey: "task_reassigned_{$task->id}_" . now()->format('YmdH'),
                );
            } catch (\Throwable) {}
        }

        $msg = $actionType === 'task_claimed'
            ? 'Task assigned to you.'
            : 'Task reassigned to you.';

        return response()->json([
            'message'          => $msg,
            'action'           => $actionType,
            'assigned_to_id'   => $actorId,
            'assigned_to_name' => $actorName,
        ]);
    }

    // ── List / Kanban index ───────────────────────────────────────────────────

    public function index(Request $request, string $tenantId): \Illuminate\View\View
    {
        $tenant = Tenant::findOrFail($tenantId);
        [$actorType, $actorId, $actorName] = $this->resolveActorFull();

        $tab            = $request->query('tab', 'mine');
        $status         = $request->query('status');
        $assigneeFilter = $request->query('assignee');
        $view           = in_array($request->query('view'), ['list','kanban']) ? $request->query('view') : 'list';
        $isAdmin        = Auth::guard('web')->check()
            || in_array(TenantContext::role(), ['admin', 'owner', 'manager']);
        $query          = Task::where('tenant_id', $tenantId)->whereNull('deleted_at');

        if ($tab === 'mine') {
            $query->where('assigned_to_type', $actorType)->where('assigned_to_id', $actorId);
        } elseif ($tab === 'assigned_by_me') {
            $query->where('assigned_by_type', $actorType)->where('assigned_by_id', $actorId);
        } elseif ($tab === 'completed') {
            $query->where('status', 'completed');
            if (!$isAdmin) $query->where('assigned_to_type', $actorType)->where('assigned_to_id', $actorId);
        } elseif ($tab === 'overdue') {
            $query->whereNotIn('status', ['completed','cancelled','archived'])->where('due_at', '<', now());
            if (!$isAdmin) $query->where('assigned_to_type', $actorType)->where('assigned_to_id', $actorId);
        } elseif ($tab === 'all') {
            if (!$isAdmin) $query->where('assigned_to_type', $actorType)->where('assigned_to_id', $actorId);
        }

        if ($status) $query->where('status', $status);
        if ($isAdmin && $assigneeFilter && $assigneeFilter !== 'all') {
            $query->where('assigned_to_id', $assigneeFilter)->where('assigned_to_type', 'tenant_user');
        }

        $tasks = $query->orderByRaw("CASE WHEN status='open' THEN 0 WHEN status='in_progress' THEN 1 ELSE 2 END")
                       ->orderByRaw("CASE WHEN priority='urgent' THEN 0 WHEN priority='high' THEN 1 WHEN priority='medium' THEN 2 ELSE 3 END")
                       ->orderBy('due_at')->orderByDesc('created_at')
                       ->paginate(30)->appends(request()->only(['tab', 'view', 'status', 'assignee']));

        $assigneeOptions = [];
        if ($isAdmin) {
            $assigneeOptions = DB::table('tenant_users as tu')
                ->join('tenant_memberships as tm', 'tm.tenant_user_id', '=', 'tu.id')
                ->where('tm.tenant_id', $tenantId)->where('tm.status', 'active')
                ->selectRaw("tu.id, TRIM(CONCAT(COALESCE(tu.first_name,''), ' ', COALESCE(tu.last_name,''))) as name, tm.role")
                ->orderBy('tu.first_name')->get()->toArray();
        }

        // Build kanban data (passed as JSON for Alpine when view=kanban)
        $kanbanColumns          = [];
        $responsesColumns       = [];
        $completionEmailEnabled = false;

        if ($view === 'kanban') {
            try {
                $kanbanColumns = $this->buildKanbanData($tenantId, $actorType, $actorId, $isAdmin, $tab, $assigneeFilter);
            } catch (\Throwable $e) {
                \Log::error('TaskController: buildKanbanData failed: ' . $e->getMessage());
                $kanbanColumns = [];
            }
            $completionEmailEnabled = $this->tenantCompletionEmailEnabled($tenantId);
        }

        // Responses tab always loads regardless of view mode
        if ($tab === 'responses') {
            $responsesColumns = $this->buildResponsesData($tenantId, $actorType, $actorId, $isAdmin);
        }

        return view('tenant.tasks.index', compact(
            'tenant', 'tasks', 'tab', 'actorId', 'actorName',
            'isAdmin', 'assigneeOptions', 'assigneeFilter',
            'view', 'kanbanColumns', 'responsesColumns', 'completionEmailEnabled'
        ));
    }

    // ── Update Status (Kanban drag-and-drop + quick actions) ─────────────────

    public function updateStatus(Request $request, string $tenantId, string $taskId, TaskCompletionService $svc): \Illuminate\Http\JsonResponse
    {
        $task = Task::where('tenant_id', $tenantId)->whereNull('deleted_at')->findOrFail($taskId);
        $this->authorizeComplete($task, $tenantId);

        $data = $request->validate([
            'status'            => ['required', 'in:open,in_progress,waiting,completed'],
            'note'              => 'nullable|string|max:1000',
            'send_email'        => 'nullable|boolean',
            'subject'           => 'nullable|string|max:150',
            'body'              => 'nullable|string|max:20000',
            'attachments'       => 'nullable|array|max:5',
            'attachments.*'     => 'file|max:51200|mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png,webp,txt',
            'client_request_id' => 'nullable|string|max:64',
        ]);

        $newStatus = $data['status'];
        $oldStatus = $task->status;

        if ($newStatus === $oldStatus) {
            return response()->json(['message' => 'Status unchanged.', 'status' => $oldStatus]);
        }

        [$actorType, $actorId, $actorName] = $this->resolveActorFull();
        $isAdmin = Auth::guard('web')->check() || in_array(TenantContext::role(), ['admin', 'owner', 'manager']);

        // Block reopening completed/cancelled/archived for non-admins
        if (in_array($oldStatus, ['completed', 'cancelled', 'archived']) && !$isAdmin) {
            return response()->json(['error' => 'You cannot reopen a completed or closed task.'], 403);
        }

        // Completion path — delegate to TaskCompletionService
        if ($newStatus === 'completed') {
            if (!$task->isCompletable()) {
                return response()->json(['error' => 'Task is already completed or cannot be completed.'], 422);
            }

            $wantsEmail     = filter_var($data['send_email'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $requestorEmail = $task->resolveRequestorEmail();
            if ($requestorEmail && !filter_var($requestorEmail, FILTER_VALIDATE_EMAIL)) {
                $requestorEmail = null;
            }
            $sendEmail = $wantsEmail && !empty($requestorEmail);

            if ($sendEmail) {
                if (empty(trim($data['subject'] ?? ''))) {
                    return response()->json(['error' => 'Email subject is required when sending a reply.'], 422);
                }
                if (empty(trim($data['body'] ?? ''))) {
                    return response()->json(['error' => 'Email body is required when sending a reply.'], 422);
                }
            }

            $attachmentPaths = !empty($data['attachments'])
                ? $svc->storeAttachments($tenantId, $taskId, $data['attachments'])
                : [];

            $result = $svc->completeWithResponse(
                task:            $task,
                actorType:       $actorType,
                actorId:         $actorId,
                actorName:       $actorName,
                subject:         $data['subject'] ?? null,
                body:            $data['body'] ?? null,
                attachmentPaths: $attachmentPaths,
                clientRequestId: $data['client_request_id'] ?? null,
                sendEmail:       $sendEmail,
            );

            $msg = match($result['email_status'] ?? 'skipped') {
                'sent'    => 'Task completed and reply sent.',
                'skipped' => 'Task marked as done.',
                default   => 'Task completed. Email could not be sent.',
            };

            return response()->json([
                'status'       => 'completed',
                'email_status' => $result['email_status'] ?? 'skipped',
                'message'      => $msg,
                'card'         => $this->buildTaskCard($task->fresh(), $tenantId, $actorType, $actorId),
            ]);
        }

        // Non-completion status change (open / in_progress / waiting)
        DB::transaction(function () use ($task, $newStatus, $oldStatus, $actorType, $actorId, $actorName, $tenantId, $data) {
            $updateData = ['status' => $newStatus];
            if ($newStatus === 'in_progress' && !$task->started_at) {
                $updateData['started_at'] = now();
            }
            $task->update($updateData);

            TaskActivity::create([
                'tenant_id'   => $tenantId,
                'task_id'     => $task->id,
                'actor_type'  => $actorType,
                'actor_id'    => $actorId,
                'actor_name'  => $actorName,
                'action_type' => 'task_status_changed',
                'old_values'  => ['status' => $oldStatus],
                'new_values'  => ['status' => $newStatus, 'note' => $data['note'] ?? null],
                'metadata'    => ['source' => 'kanban'],
            ]);
        });

        $statusLabel = ucwords(str_replace('_', ' ', $newStatus));

        // Notify assignee if they didn't move it themselves
        $isAssignee = $task->assigned_to_type === $actorType && $task->assigned_to_id === $actorId;
        if (!$isAssignee && $task->assigned_to_type === 'tenant_user' && $task->assigned_to_id) {
            try {
                app(\App\Services\NotificationDispatchService::class)->dispatch(
                    category:         'task',
                    priority:         'normal',
                    title:            "Task status updated",
                    body:             "{$actorName} moved \"{$task->title}\" to {$statusLabel}.",
                    notifiableType:   'tenant_user',
                    notifiableId:     $task->assigned_to_id,
                    tenantId:         $tenantId,
                    actionUrl:        "/tenant/{$tenantId}/tasks/{$task->id}",
                    actionLabel:      'View Task',
                    deduplicationKey: "task_status_{$task->id}_" . now()->format('YmdH'),
                );
            } catch (\Throwable) {}
        }

        return response()->json([
            'status'  => $newStatus,
            'message' => "Task moved to {$statusLabel}.",
            'card'    => $this->buildTaskCard($task->fresh(), $tenantId, $actorType, $actorId),
        ]);
    }

    // ── Build Kanban column data ───────────────────────────────────────────────

    private function buildKanbanData(string $tenantId, string $actorType, string $actorId, bool $isAdmin, string $tab, ?string $assigneeFilter): array
    {
        $query = Task::where('tenant_id', $tenantId)->whereNull('deleted_at');

        if ($tab === 'mine' || (!$isAdmin)) {
            $query->where('assigned_to_type', $actorType)->where('assigned_to_id', $actorId);
        } elseif ($tab === 'assigned_by_me') {
            $query->where('assigned_by_type', $actorType)->where('assigned_by_id', $actorId);
        }

        if ($isAdmin && $assigneeFilter && $assigneeFilter !== 'all') {
            $query->where('assigned_to_id', $assigneeFilter)->where('assigned_to_type', 'tenant_user');
        }

        $query->whereNotIn('status', ['cancelled', 'archived']);

        $tasks = $query
            ->orderByRaw("CASE WHEN priority='urgent' THEN 0 WHEN priority='high' THEN 1 WHEN priority='medium' THEN 2 ELSE 3 END")
            ->orderBy('due_at')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $tuIds = $tasks->filter(fn($t) => $t->assigned_to_type === 'tenant_user' && $t->assigned_to_id)
            ->pluck('assigned_to_id')->unique()->filter()->values()->toArray();
        $rsIds = $tasks->filter(fn($t) => $t->assigned_to_type === 'reseller' && $t->assigned_to_id)
            ->pluck('assigned_to_id')->unique()->filter()->values()->toArray();

        $assigneeNames = array_merge(
            !empty($tuIds)
                ? TenantUser::whereIn('id', $tuIds)->get()
                    ->mapWithKeys(fn($u) => [$u->id => $u->full_name])->all()
                : [],
            !empty($rsIds)
                ? \App\Models\Reseller::whereIn('id', $rsIds)->get()
                    ->mapWithKeys(fn($r) => [$r->id => ($r->name ?: $r->email)])->all()
                : [],
        );

        // Three columns: new_tasks (open), processing (in_progress + waiting), completed
        $groups = ['new_tasks' => [], 'processing' => [], 'completed' => []];

        foreach ($tasks as $task) {
            $key = match($task->status) {
                'open'                 => 'new_tasks',
                'in_progress','waiting'=> 'processing',
                'completed'            => 'completed',
                default                => null,
            };
            if (!$key) continue;
            if ($key === 'completed' && count($groups['completed']) >= 50) continue;

            $assigneeName     = $task->assigned_to_id ? ($assigneeNames[$task->assigned_to_id] ?? null) : null;
            $parts            = $assigneeName ? explode(' ', trim($assigneeName)) : [];
            $assigneeInitials = strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1)) ?: '--';
            $isAssignee       = $task->assigned_to_type === $actorType && $task->assigned_to_id === $actorId;

            $groups[$key][] = [
                'id'                   => $task->id,
                'title'                => $task->title,
                'status'               => $task->status,
                'priority'             => $task->priority,
                'category'             => $task->category ?? 'manual',
                'source_type'          => $task->source_type,
                'requestor_name'       => $task->requestor_name,
                'assignee_name'        => $assigneeName,
                'assignee_initials'    => $assigneeInitials,
                'due_at'               => $task->due_at?->format('M j, Y'),
                'due_at_raw'           => $task->due_at?->toDateString(),
                'is_overdue'           => $task->isOverdue(),
                'created_ago'          => $task->created_at->diffForHumans(),
                'is_request_form_task' => $task->category === 'request_form',
                'url'                  => "/tenant/{$tenantId}/tasks/{$task->id}",
                'can_update_status'    => $isAdmin || $isAssignee,
                'can_complete'         => ($isAdmin || $isAssignee) && $task->isCompletable(),
            ];
        }

        return [
            ['key' => 'new_tasks',  'label' => 'New Tasks',        'status_for_drop' => 'open',        'tasks' => $groups['new_tasks'],  'count' => count($groups['new_tasks'])],
            ['key' => 'processing', 'label' => 'Processing Tasks',  'status_for_drop' => 'in_progress', 'tasks' => $groups['processing'], 'count' => count($groups['processing'])],
            ['key' => 'completed',  'label' => 'Completed Tasks',   'status_for_drop' => 'completed',   'tasks' => $groups['completed'],  'count' => count($groups['completed'])],
        ];
    }

    private function buildResponsesData(string $tenantId, string $actorType, string $actorId, bool $isAdmin): array
    {
        $query = \App\Models\RequestFormSubmission::where('tenant_id', $tenantId)
            ->with(['form:id,title', 'tasks' => fn($q) => $q->whereNull('deleted_at')->orderByDesc('created_at')->limit(1)])
            ->orderByDesc('submitted_at');

        if (!$isAdmin) {
            // Non-admins only see submissions where a linked task is assigned to them
            $myTaskSourceIds = Task::where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where('assigned_to_type', $actorType)
                ->where('assigned_to_id', $actorId)
                ->where('source_type', 'request_form_submission')
                ->pluck('source_id')
                ->toArray();
            $query->whereIn('id', $myTaskSourceIds);
        }

        $submissions = $query->limit(150)->get();

        $groups = ['new' => [], 'processing' => [], 'completed' => []];

        foreach ($submissions as $sub) {
            $task       = $sub->tasks->first();
            $taskStatus = $task?->status;
            $col = match(true) {
                $taskStatus === 'completed'                       => 'completed',
                in_array($taskStatus, ['in_progress','waiting'])  => 'processing',
                $taskStatus === 'open'                            => 'processing',
                default                                           => 'new',
            };

            $groups[$col][] = [
                'id'             => $sub->id,
                'form_title'     => $sub->form?->title ?? 'Untitled Form',
                'submitter_name' => $sub->submitter_name ?: ($sub->submitter_email ?: 'Anonymous'),
                'submitter_email'=> $sub->submitter_email,
                'submitted_ago'  => $sub->submitted_at?->diffForHumans() ?? $sub->created_at->diffForHumans(),
                'status'         => $sub->status ?? 'pending',
                'task_id'        => $task?->id,
                'task_title'     => $task?->title,
                'task_status'    => $taskStatus,
                'task_url'       => $task ? "/tenant/{$tenantId}/tasks/{$task->id}" : null,
                'has_task'       => (bool) $task,
            ];
        }

        return [
            ['key' => 'new',        'label' => 'New Requests',     'items' => $groups['new'],        'count' => count($groups['new'])],
            ['key' => 'processing', 'label' => 'In Progress',       'items' => $groups['processing'], 'count' => count($groups['processing'])],
            ['key' => 'completed',  'label' => 'Resolved',          'items' => $groups['completed'],  'count' => count($groups['completed'])],
        ];
    }

    private function buildTaskCard(Task $task, string $tenantId, string $actorType, string $actorId): array
    {
        $isAdmin    = Auth::guard('web')->check() || in_array(TenantContext::role(), ['admin','owner','manager']);
        $isAssignee = $task->assigned_to_type === $actorType && $task->assigned_to_id === $actorId;

        $assigneeName     = null;
        $assigneeInitials = '--';
        if ($task->assigned_to_id && $task->assigned_to_type === 'tenant_user') {
            $u = TenantUser::find($task->assigned_to_id);
            $assigneeName = $u?->full_name;
            if ($assigneeName) {
                $parts = explode(' ', trim($assigneeName));
                $assigneeInitials = strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1)) ?: '--';
            }
        }

        return [
            'id'                   => $task->id,
            'title'                => $task->title,
            'status'               => $task->status,
            'priority'             => $task->priority,
            'category'             => $task->category ?? 'manual',
            'source_type'          => $task->source_type,
            'requestor_name'       => $task->requestor_name,
            'assignee_name'        => $assigneeName,
            'assignee_initials'    => $assigneeInitials,
            'due_at'               => $task->due_at?->format('M j, Y'),
            'due_at_raw'           => $task->due_at?->toDateString(),
            'is_overdue'           => $task->isOverdue(),
            'created_ago'          => $task->created_at->diffForHumans(),
            'is_request_form_task' => $task->category === 'request_form',
            'url'                  => "/tenant/{$tenantId}/tasks/{$task->id}",
            'can_update_status'    => $isAdmin || $isAssignee,
            'can_complete'         => ($isAdmin || $isAssignee) && $task->isCompletable(),
        ];
    }

    // ── Detail ────────────────────────────────────────────────────────────────

    public function show(string $tenantId, string $taskId): \Illuminate\View\View
    {
        $tenant = Tenant::findOrFail($tenantId);
        $task   = Task::where('tenant_id', $tenantId)->whereNull('deleted_at')->with(['activities','completionResponses'])->findOrFail($taskId);

        $this->authorizeView($task, $tenantId);

        $source = null;
        if ($task->source_type === 'request_form_submission' && $task->source_id) {
            try {
                // Cast uuid PK to text to compare with varchar source_id — avoids
                // "operator does not exist: uuid = character varying" in PostgreSQL.
                $source = \App\Models\RequestFormSubmission::with('form')
                    ->where('tenant_id', $tenantId)
                    ->whereRaw('"id"::text = ?', [$task->source_id])
                    ->first();
            } catch (\Throwable) {
                $source = null;
            }
        }

        $canComplete            = $this->canComplete($task, $tenantId);
        $completionEmailEnabled = $this->tenantCompletionEmailEnabled($tenantId);

        // Resolve current actor for self-assignment UI
        [$actorType, $actorId, $actorName] = $this->resolveActorFull();

        // Resolve current assignee display info
        $assigneeName = null;
        if ($task->assigned_to_id && $task->assigned_to_type === 'tenant_user') {
            $assigneeUser = TenantUser::find($task->assigned_to_id);
            $assigneeName = $assigneeUser?->full_name ?? 'Unknown';
        }

        // Self-assignment eligibility:
        // Admins can always assign to self. Non-admins can claim only if task is unassigned.
        $isAdmin           = Auth::guard('web')->check()
            || in_array(TenantContext::role(), ['admin', 'owner', 'manager']);
        $isCurrentAssignee = $task->assigned_to_type === $actorType
            && $task->assigned_to_id === $actorId;
        $isTerminal        = in_array($task->status, ['completed', 'cancelled', 'archived']);

        $canAssignToSelf = !$isTerminal && (
            $isAdmin
            || !$task->assigned_to_id  // unassigned — anyone who can view can claim
        );

        // Pre-resolve assignee role (avoids a Blade-level DB query)
        $assigneeRole = null;
        if ($task->assigned_to_id && $task->assigned_to_type === 'tenant_user') {
            $assigneeRole = \Illuminate\Support\Facades\DB::table('tenant_memberships')
                ->where('tenant_id', $tenantId)
                ->where('tenant_user_id', $task->assigned_to_id)
                ->value('role');
        }

        // Resolve requester email/name server-side (prevents UUID display from raw field)
        $resolvedRequesterEmail = $task->resolveRequestorEmail();
        $resolvedRequesterName  = $task->resolveRequestorName();
        // Validate it looks like an email — if not (e.g. a UUID was stored), treat as missing
        if ($resolvedRequesterEmail && !filter_var($resolvedRequesterEmail, FILTER_VALIDATE_EMAIL)) {
            $resolvedRequesterEmail = null;
        }

        return view('tenant.tasks.show', compact(
            'tenant', 'task', 'source', 'canComplete', 'completionEmailEnabled',
            'actorId', 'actorName', 'actorType', 'assigneeName', 'assigneeRole',
            'canAssignToSelf', 'isCurrentAssignee', 'isAdmin',
            'resolvedRequesterEmail', 'resolvedRequesterName'
        ));
    }

    // ── Complete (no email) ───────────────────────────────────────────────────

    public function complete(string $tenantId, string $taskId, TaskCompletionService $svc): \Illuminate\Http\JsonResponse
    {
        $task = Task::where('tenant_id', $tenantId)->whereNull('deleted_at')->findOrFail($taskId);
        $this->authorizeComplete($task, $tenantId);

        if (!$task->isCompletable()) {
            return response()->json(['error' => 'Task is already completed or cannot be completed.'], 422);
        }

        [$actorType, $actorId, $actorName] = $this->resolveActorFull();
        $svc->complete($task, $actorType, $actorId, $actorName);

        return response()->json(['status' => 'completed', 'message' => 'Task completed.']);
    }

    // ── Complete with response email ──────────────────────────────────────────

    public function completeWithResponse(Request $request, string $tenantId, string $taskId, TaskCompletionService $svc): \Illuminate\Http\JsonResponse
    {
        $task = Task::where('tenant_id', $tenantId)->whereNull('deleted_at')->findOrFail($taskId);
        $this->authorizeComplete($task, $tenantId);

        if (!$task->isCompletable()) {
            return response()->json(['error' => 'Task is already completed.'], 422);
        }

        $data = $request->validate([
            'subject'           => 'nullable|string|max:150',
            'body'              => 'nullable|string|max:20000',
            'send_email'        => 'nullable|boolean',
            'attachments'       => 'nullable|array|max:5',
            'attachments.*'     => 'file|max:51200|mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png,webp,txt',
            'client_request_id' => 'nullable|string|max:64',
        ]);

        $wantsEmail     = filter_var($data['send_email'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $requestorEmail = $task->resolveRequestorEmail();

        // Validate email format server-side — never trust a UUID stored in requestor_email
        if ($requestorEmail && !filter_var($requestorEmail, FILTER_VALIDATE_EMAIL)) {
            $requestorEmail = null;
        }

        $sendEmail = $wantsEmail && !empty($requestorEmail);

        // When sending email, require subject and body
        if ($sendEmail) {
            if (empty(trim($data['subject'] ?? ''))) {
                return response()->json(['error' => 'Email subject is required when sending a reply.'], 422);
            }
            if (empty(trim($data['body'] ?? ''))) {
                return response()->json(['error' => 'Email body is required when sending a reply.'], 422);
            }
        }

        [$actorType, $actorId, $actorName] = $this->resolveActorFull();

        $attachmentPaths = [];
        if (!empty($data['attachments'])) {
            $attachmentPaths = $svc->storeAttachments($tenantId, $taskId, $data['attachments']);
        }

        $result = $svc->completeWithResponse(
            task:            $task,
            actorType:       $actorType,
            actorId:         $actorId,
            actorName:       $actorName,
            subject:         $data['subject'],
            body:            $data['body'],
            attachmentPaths: $attachmentPaths,
            clientRequestId: $data['client_request_id'] ?? null,
            sendEmail:       $sendEmail,
        );

        $msg = match ($result['email_status']) {
            'sent'    => 'Task completed and response sent to requestor.',
            'skipped' => 'Task completed. Response saved.',
            default   => 'Task completed. Response saved — email could not be sent.',
        };

        return response()->json([
            'status'       => 'completed',
            'email_status' => $result['email_status'],
            'message'      => $msg,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function authorizeAdmin(string $tenantId): void
    {
        $ctxId = TenantContext::id();
        if ($ctxId && $ctxId !== $tenantId) abort(403);
        $isAdmin = Auth::guard('web')->check()
            || in_array(TenantContext::role(), ['admin', 'owner', 'manager']);
        if (!$isAdmin) abort(403);
    }

    private function resolveActor(): array
    {
        if (Auth::guard('tenant')->check()) {
            $u = Auth::guard('tenant')->user();
            return ['tenant_user', (string) $u->id];
        }
        if (Auth::guard('web')->check()) {
            $u = Auth::guard('web')->user();
            return ['admin_user', (string) $u->id];
        }
        return ['system', 'system'];
    }

    private function resolveActorFull(): array
    {
        if (Auth::guard('tenant')->check()) {
            $u = Auth::guard('tenant')->user();
            $name = method_exists($u, 'getFullNameAttribute')
                ? $u->full_name
                : ($u->first_name ?? $u->email ?? 'Admin');
            return ['tenant_user', (string) $u->id, $name];
        }
        if (Auth::guard('web')->check()) {
            $u = Auth::guard('web')->user();
            return ['admin_user', (string) $u->id, $u->name ?? $u->email ?? 'Super Admin'];
        }
        return ['system', 'system', 'System'];
    }

    private function resolveUserName(string $type, string $id): string
    {
        try {
            if ($type === 'tenant_user') {
                $u = TenantUser::find($id);
                return $u ? $u->full_name : 'Unknown';
            }
        } catch (\Throwable) {}
        return 'Unknown';
    }

    private function authorizeView(Task $task, string $tenantId): void
    {
        if ($task->tenant_id !== $tenantId) abort(403);
        [$actorType, $actorId] = $this->resolveActor();
        $isAdmin = Auth::guard('web')->check()
            || in_array(TenantContext::role(), ['admin', 'owner', 'manager']);
        $isAssignee = $task->assigned_to_type === $actorType
            && $task->assigned_to_id === $actorId;
        if (!$isAdmin && !$isAssignee) abort(403);
    }

    private function authorizeComplete(Task $task, string $tenantId): void
    {
        if ($task->tenant_id !== $tenantId) abort(403);
        [$actorType, $actorId] = $this->resolveActor();
        $isAdmin    = Auth::guard('web')->check()
            || in_array(TenantContext::role(), ['admin', 'owner', 'manager']);
        $isAssignee = $task->assigned_to_type === $actorType && $task->assigned_to_id === $actorId;
        if (!$isAdmin && !$isAssignee) abort(403, 'You cannot complete this task.');
    }

    private function canComplete(Task $task, string $tenantId): bool
    {
        if (!$task->isCompletable()) return false;
        [$actorType, $actorId] = $this->resolveActor();
        $isAdmin    = Auth::guard('web')->check() || in_array(TenantContext::role(), ['admin','owner','manager']);
        $isAssignee = $task->assigned_to_type === $actorType && $task->assigned_to_id === $actorId;
        return $isAdmin || $isAssignee;
    }

    private function tenantCompletionEmailEnabled(string $tenantId): bool
    {
        try {
            $config = DB::table('tenant_configs')->where('tenant_id', $tenantId)->first();
            if (!$config) return false;
            if (isset($config->task_completion_email_enabled)) {
                return (bool) $config->task_completion_email_enabled;
            }
            foreach (['settings', 'commission', 'fields'] as $jsonField) {
                if (isset($config->{$jsonField})) {
                    $decoded = is_string($config->{$jsonField})
                        ? json_decode($config->{$jsonField}, true)
                        : (array) $config->{$jsonField};
                    if (isset($decoded['task_completion_email_enabled'])) {
                        return (bool) $decoded['task_completion_email_enabled'];
                    }
                }
            }
        } catch (\Throwable) {}
        return false;
    }
}
