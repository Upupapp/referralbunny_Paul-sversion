<?php

namespace App\Jobs\Emails;

use App\Mail\LguIdsTaskDueReminderMail;
use App\Services\EmailLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Runs daily: sends ONE separate email per task that is due today.
 * Each email goes to all lgu-ids admin/manager/owner users.
 */
class SendLguIdsTaskDueReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const TENANT_ID = 'lgu-ids';

    public function handle(): void
    {
        $tz      = 'Asia/Manila';
        $pending = ['open', 'in_progress', 'waiting'];

        // UTC range for today in Asia/Manila — allows the (tenant_id, due_at) index to be used
        // instead of a per-row DATE(expr AT TIME ZONE ...) which forces a full scan
        $startOfDayMnl = now()->setTimezone($tz)->startOfDay()->utc();
        $endOfDayMnl   = $startOfDayMnl->copy()->addDay();

        // Tasks with due_at = today (Manila) and still pending
        try {
            $dueTasks = DB::table('tasks')
                ->where('tenant_id', self::TENANT_ID)
                ->whereIn('status', $pending)
                ->whereNull('deleted_at')
                ->where('due_at', '>=', $startOfDayMnl)
                ->where('due_at', '<',  $endOfDayMnl)
                ->get(['id', 'title', 'description', 'priority', 'status', 'due_at', 'assigned_to_type', 'assigned_to_id']);
        } catch (\Throwable $e) {
            Log::error('[LguIdsTaskDue] Failed to fetch due tasks', ['error' => $e->getMessage()]);
            return;
        }

        if ($dueTasks->isEmpty()) {
            Log::info('[LguIdsTaskDue] No tasks due today.');
            return;
        }

        // Resolve assignee names
        $tenantUserIds = $dueTasks->where('assigned_to_type', 'tenant_user')->pluck('assigned_to_id')->unique()->filter();
        $resellerIds   = $dueTasks->where('assigned_to_type', 'reseller')->pluck('assigned_to_id')->unique()->filter();

        $tenantUserNames = $tenantUserIds->isNotEmpty()
            ? DB::table('tenant_users')->whereIn('id', $tenantUserIds)->get(['id', 'first_name', 'last_name'])
                ->mapWithKeys(fn($u) => [$u->id => trim("{$u->first_name} {$u->last_name}")])
            : collect();

        $resellerNames = $resellerIds->isNotEmpty()
            ? DB::table('resellers')->whereIn('id', $resellerIds)->pluck('name', 'id')
            : collect();

        $tenant = DB::table('tenants')->where('id', self::TENANT_ID)->first();
        if (!$tenant) return;

        $admins = DB::table('tenant_memberships as tm')
            ->join('tenant_users as u', 'tm.tenant_user_id', '=', 'u.id')
            ->where('tm.tenant_id', self::TENANT_ID)
            ->whereIn('tm.role', ['owner', 'admin', 'manager'])
            ->where('tm.status', 'active')
            ->whereNull('tm.deleted_at')
            ->select('u.email', 'u.first_name', 'u.last_name')
            ->get();

        foreach ($dueTasks as $task) {
            $assignedTo = null;
            if ($task->assigned_to_type === 'tenant_user') {
                $assignedTo = $tenantUserNames->get($task->assigned_to_id);
            } elseif ($task->assigned_to_type === 'reseller') {
                $assignedTo = $resellerNames->get($task->assigned_to_id);
            }

            $dueAt    = \Carbon\Carbon::parse($task->due_at)->setTimezone($tz);
            $dueLabel = $dueAt->format('M j, Y');
            $taskUrl  = url("/tenant/" . self::TENANT_ID . "/tasks");

            foreach ($admins as $admin) {
                $emailKey = "lgu_ids.task_due.{$task->id}.{$admin->email}";

                EmailLogger::send(
                    mailable: new LguIdsTaskDueReminderMail(
                        adminName:       trim("{$admin->first_name} {$admin->last_name}"),
                        taskTitle:       $task->title,
                        taskDescription: $task->description,
                        taskPriority:    $task->priority ?? 'medium',
                        taskStatus:      $task->status,
                        dueLabel:        $dueLabel,
                        assignedTo:      $assignedTo,
                        taskUrl:         $taskUrl,
                        tenantName:      $tenant->name,
                    ),
                    recipientEmail: $admin->email,
                    recipientType:  'tenant_admin',
                    emailKey:       $emailKey,
                    subject:        "⏰ Task Due Today: {$task->title}",
                    tenantId:       self::TENANT_ID,
                    dailyDedup:     true,
                );
            }
        }

        Log::info('[LguIdsTaskDue] Sent due reminders', ['tasks' => $dueTasks->count()]);
    }
}
