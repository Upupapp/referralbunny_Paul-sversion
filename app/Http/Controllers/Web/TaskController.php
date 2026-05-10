<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Mail\ManualTaskAssignedMail;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\Tenant;
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

        $members = DB::table('tenant_users as tu')
            ->join('tenant_memberships as tm', 'tm.tenant_user_id', '=', 'tu.id')
            ->where('tm.tenant_id', $tenantId)
            ->where('tm.status', 'active')
            ->selectRaw("tu.id, TRIM(CONCAT(COALESCE(tu.first_name,''), ' ', COALESCE(tu.last_name,''))) as name, tu.email, tm.role")
            ->orderBy('tm.role')
            ->orderBy('tu.first_name')
            ->get();

        return response()->json($members);
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
            'assignee_ids.*'  => 'required|string|uuid',
            'source_type'     => 'nullable|in:deal,lead,contact,referrer,partner',
            'source_id'       => 'nullable|string|uuid',
        ]);

        [$actorType, $actorId, $actorName] = $this->resolveActorFull();

        // Verify all assignees belong to this tenant
        $assigneeIds = collect($data['assignee_ids'])->unique()->values();
        $validAssignees = DB::table('tenant_users as tu')
            ->join('tenant_memberships as tm', 'tm.tenant_user_id', '=', 'tu.id')
            ->where('tm.tenant_id', $tenantId)
            ->where('tm.status', 'active')
            ->whereIn('tu.id', $assigneeIds->toArray())
            ->selectRaw("tu.id, TRIM(CONCAT(COALESCE(tu.first_name,''), ' ', COALESCE(tu.last_name,''))) as name, tu.email")
            ->get()
            ->keyBy('id');

        if ($validAssignees->isEmpty()) {
            return response()->json(['error' => 'No valid assignees found.'], 422);
        }

        $createdTasks = [];

        DB::transaction(function () use (
            $data, $tenantId, $actorType, $actorId, $actorName,
            $assigneeIds, $validAssignees, &$createdTasks
        ) {
            foreach ($assigneeIds as $assigneeId) {
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
                    'action_type' => 'task_created',
                    'new_values'  => [
                        'title'    => $task->title,
                        'priority' => $task->priority,
                        'assignee' => $assignee->name,
                        'due_at'   => $task->due_at?->toDateString(),
                    ],
                ]);

                $createdTasks[] = $task;

                // In-app notification to assignee
                try {
                    app(\App\Services\NotificationDispatchService::class)->dispatch(
                        category:         'task',
                        priority:         $task->priority === 'urgent' ? 'urgent' : ($task->priority === 'high' ? 'high' : 'normal'),
                        title:            'New Task: ' . $task->title,
                        body:             "Assigned by {$actorName}." . ($task->due_at ? " Due {$task->due_at->format('M j, Y')}." : ''),
                        notifiableType:   'tenant_user',
                        notifiableId:     $assigneeId,
                        tenantId:         $tenantId,
                        actionUrl:        "/tenant/{$tenantId}/tasks/{$task->id}",
                        actionLabel:      'View Task',
                        deduplicationKey: "task_assigned_{$task->id}",
                    );
                } catch (\Throwable) {}
            }
        });

        // After commit: send email notifications
        foreach ($createdTasks as $task) {
            $assignee = $validAssignees->get($task->assigned_to_id);
            if (!$assignee || !$assignee->email) continue;

            try {
                Mail::to($assignee->email)
                    ->queue(new ManualTaskAssignedMail(
                        assigneeName: $assignee->name,
                        senderName:   $actorName,
                        taskTitle:    $task->title,
                        taskPriority: $task->priority,
                        dueAt:        $task->due_at?->format('M j, Y'),
                        taskUrl:      url("/tenant/{$tenantId}/tasks/{$task->id}"),
                    ));
            } catch (\Throwable) {}
        }

        return response()->json([
            'created' => count($createdTasks),
            'task_ids' => collect($createdTasks)->pluck('id'),
            'message'  => count($createdTasks) === 1
                ? 'Task created and assignee notified.'
                : count($createdTasks) . ' tasks created and assignees notified.',
        ]);
    }

    // ── List ─────────────────────────────────────────────────────────────────

    public function index(Request $request, string $tenantId): \Illuminate\View\View
    {
        $tenant = Tenant::findOrFail($tenantId);
        [$actorType, $actorId] = $this->resolveActor();

        $tab      = $request->query('tab', 'mine');
        $status   = $request->query('status');
        $isAdmin  = Auth::guard('web')->check()
            || in_array(TenantContext::role(), ['admin', 'owner', 'manager']);
        $query    = Task::where('tenant_id', $tenantId)->whereNull('deleted_at');

        if ($tab === 'mine') {
            $query->where('assigned_to_type', $actorType)->where('assigned_to_id', $actorId);
        } elseif ($tab === 'assigned_by_me') {
            $query->where('assigned_by_type', $actorType)->where('assigned_by_id', $actorId);
        } elseif ($tab === 'completed') {
            $q = $query->where('status', 'completed');
            // Non-admins only see their own completed tasks
            if (!$isAdmin) $q->where('assigned_to_type', $actorType)->where('assigned_to_id', $actorId);
        } elseif ($tab === 'overdue') {
            $query->whereNotIn('status', ['completed','cancelled','archived'])
                  ->where('due_at', '<', now());
            if (!$isAdmin) $query->where('assigned_to_type', $actorType)->where('assigned_to_id', $actorId);
        } elseif ($tab === 'all') {
            // Only admins/managers can see all tasks
            if (!$isAdmin) {
                $query->where('assigned_to_type', $actorType)->where('assigned_to_id', $actorId);
            }
        }

        if ($status) $query->where('status', $status);

        $tasks = $query->orderByRaw("CASE WHEN status='open' THEN 0 WHEN status='in_progress' THEN 1 ELSE 2 END")
                       ->orderByRaw("CASE WHEN priority='urgent' THEN 0 WHEN priority='high' THEN 1 WHEN priority='medium' THEN 2 ELSE 3 END")
                       ->orderBy('due_at')
                       ->orderByDesc('created_at')
                       ->paginate(30)
                       ->withQueryString();

        return view('tenant.tasks.index', compact('tenant', 'tasks', 'tab'));
    }

    // ── Detail ────────────────────────────────────────────────────────────────

    public function show(string $tenantId, string $taskId): \Illuminate\View\View
    {
        $tenant = Tenant::findOrFail($tenantId);
        $task   = Task::where('tenant_id', $tenantId)->with(['activities','completionResponses'])->findOrFail($taskId);

        $this->authorizeView($task, $tenantId);

        $source = null;
        if ($task->source_type === 'request_form_submission' && $task->source_id) {
            $source = \App\Models\RequestFormSubmission::with('form')->find($task->source_id);
        }

        $canComplete    = $this->canComplete($task, $tenantId);
        $completionEmailEnabled = $this->tenantCompletionEmailEnabled($tenantId);

        return view('tenant.tasks.show', compact(
            'tenant', 'task', 'source', 'canComplete', 'completionEmailEnabled'
        ));
    }

    // ── Complete (no email) ───────────────────────────────────────────────────

    public function complete(string $tenantId, string $taskId, TaskCompletionService $svc): \Illuminate\Http\JsonResponse
    {
        $task = Task::where('tenant_id', $tenantId)->findOrFail($taskId);
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
        $task = Task::where('tenant_id', $tenantId)->findOrFail($taskId);
        $this->authorizeComplete($task, $tenantId);

        if (!$task->isCompletable()) {
            return response()->json(['error' => 'Task is already completed.'], 422);
        }

        if (!$this->tenantCompletionEmailEnabled($tenantId)) {
            return response()->json(['error' => 'Completion response emails are disabled for this tenant.'], 403);
        }

        $requestorEmail = $task->resolveRequestorEmail();
        if (!$requestorEmail) {
            return response()->json(['error' => 'No requestor email available for this task.'], 422);
        }

        $data = $request->validate([
            'subject'     => 'required|string|max:150',
            'body'        => 'required|string|max:10000',
            'attachments' => 'nullable|array|max:5',
            'attachments.*'=> 'file|max:10240|mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png,webp,txt',
            'client_request_id' => 'nullable|string|max:64',
        ]);

        [$actorType, $actorId, $actorName] = $this->resolveActorFull();

        // Store attachments
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
        );

        $msg = $result['email_status'] === 'sent'
            ? 'Task completed and response sent to requestor.'
            : 'Task completed. Response email could not be sent — you can retry from the task.';

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
            // TenantUser uses first_name + last_name (not a 'name' field)
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

    private function authorizeView(Task $task, string $tenantId): void
    {
        if ($task->tenant_id !== $tenantId) abort(403);
        // Admins and assignees can view; all others see 403
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
        // Admins and task assignees can complete
        [$actorType, $actorId] = $this->resolveActor();
        $isAdmin = Auth::guard('web')->check() || TenantContext::role() === 'admin' || TenantContext::role() === 'owner';
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
        // Check tenant_configs for a task_completion_email_enabled flag.
        // Default: false (opt-in per tenant). Enable by inserting a key into the config row.
        try {
            $config = DB::table('tenant_configs')->where('tenant_id', $tenantId)->first();
            if (!$config) return false;
            // Support both a dedicated column or a JSON settings field
            if (isset($config->task_completion_email_enabled)) {
                return (bool) $config->task_completion_email_enabled;
            }
            // Fall back to checking any JSON settings field
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
