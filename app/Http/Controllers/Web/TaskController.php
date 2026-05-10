<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\Tenant;
use App\Services\TaskCompletionService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
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
