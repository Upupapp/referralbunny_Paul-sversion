<?php

namespace App\Jobs\Emails;

use App\Mail\LguIdsPendingTasksDigestMail;
use App\Services\EmailLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendLguIdsPendingTasksDigestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const TENANT_ID = 'lgu-ids';

    public function handle(): void
    {
        $tz   = 'Asia/Manila';
        $now  = now()->setTimezone($tz);
        $date = $now->format('l, F j, Y');

        // Fetch all non-terminal tasks for lgu-ids
        $pendingStatuses = ['open', 'in_progress', 'waiting'];

        try {
            $tasks = DB::table('tasks')
                ->where('tenant_id', self::TENANT_ID)
                ->whereIn('status', $pendingStatuses)
                ->whereNull('deleted_at')
                ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
                ->orderBy('due_at')
                ->get(['id', 'title', 'description', 'priority', 'status', 'due_at', 'assigned_to_type', 'assigned_to_id']);
        } catch (\Throwable $e) {
            Log::error('[LguIdsTaskDigest] Failed to fetch tasks', ['error' => $e->getMessage()]);
            return;
        }

        if ($tasks->isEmpty()) {
            Log::info('[LguIdsTaskDigest] No pending tasks — skipping.');
            return;
        }

        // Resolve assignee display names in bulk
        $tenantUserIds = $tasks->where('assigned_to_type', 'tenant_user')->pluck('assigned_to_id')->unique()->filter();
        $resellerIds   = $tasks->where('assigned_to_type', 'reseller')->pluck('assigned_to_id')->unique()->filter();

        $tenantUserNames = $tenantUserIds->isNotEmpty()
            ? DB::table('tenant_users')->whereIn('id', $tenantUserIds)->get(['id', 'first_name', 'last_name'])
                ->mapWithKeys(fn($u) => [$u->id => trim("{$u->first_name} {$u->last_name}")])
            : collect();

        $resellerNames = $resellerIds->isNotEmpty()
            ? DB::table('resellers')->whereIn('id', $resellerIds)->pluck('name', 'id')
            : collect();

        $taskRows = $tasks->map(function ($t) use ($tenantUserNames, $resellerNames, $tz) {
            $dueAt   = $t->due_at ? \Carbon\Carbon::parse($t->due_at)->setTimezone($tz) : null;
            $overdue = $dueAt && $dueAt->isPast() && !in_array($t->status, ['completed', 'cancelled', 'archived']);

            $assignedTo = null;
            if ($t->assigned_to_type === 'tenant_user') {
                $assignedTo = $tenantUserNames->get($t->assigned_to_id);
            } elseif ($t->assigned_to_type === 'reseller') {
                $assignedTo = $resellerNames->get($t->assigned_to_id);
            }

            return [
                'title'       => $t->title,
                'description' => $t->description,
                'priority'    => $t->priority ?? 'medium',
                'status'      => $t->status,
                'due_label'   => $dueAt ? $dueAt->format('M j, Y') : 'No due date',
                'overdue'     => $overdue,
                'assigned_to' => $assignedTo,
                'url'         => url("/tenant/" . self::TENANT_ID . "/tasks"),
            ];
        })->toArray();

        $totalCount = count($taskRows);
        $tasksUrl   = url("/tenant/" . self::TENANT_ID . "/tasks");

        // Get tenant admins + managers for lgu-ids
        $tenant = DB::table('tenants')->where('id', self::TENANT_ID)->first();
        if (!$tenant) {
            Log::warning('[LguIdsTaskDigest] Tenant not found.');
            return;
        }

        $admins = DB::table('tenant_memberships as tm')
            ->join('tenant_users as u', 'tm.tenant_user_id', '=', 'u.id')
            ->where('tm.tenant_id', self::TENANT_ID)
            ->whereIn('tm.role', ['owner', 'admin', 'manager'])
            ->where('tm.status', 'active')
            ->select('u.email', 'u.first_name', 'u.last_name')
            ->get();

        foreach ($admins as $admin) {
            $emailKey = "lgu_ids.task_digest.{$admin->email}";
            if (EmailLogger::sentToday($emailKey)) continue;

            EmailLogger::send(
                mailable: new LguIdsPendingTasksDigestMail(
                    adminName:  trim("{$admin->first_name} {$admin->last_name}"),
                    tasks:      $taskRows,
                    tenantName: $tenant->name,
                    tasksUrl:   $tasksUrl,
                    date:       $date,
                    totalCount: $totalCount,
                ),
                recipientEmail: $admin->email,
                recipientType:  'tenant_admin',
                emailKey:       $emailKey,
                subject:        "[{$now->format('M j')}] Daily Pending Tasks — {$tenant->name} ({$totalCount} task" . ($totalCount !== 1 ? 's' : '') . ')',
                tenantId:       self::TENANT_ID,
                dailyDedup:     true,
            );
        }

        Log::info('[LguIdsTaskDigest] Sent digest', ['tasks' => $totalCount, 'admins' => $admins->count()]);
    }
}
