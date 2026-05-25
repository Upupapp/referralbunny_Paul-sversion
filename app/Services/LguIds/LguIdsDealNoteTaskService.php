<?php

namespace App\Services\LguIds;

use App\Models\EmailLog;
use App\Models\Lead;
use App\Models\Reseller;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\Tenant;
use App\Services\NotificationDispatchService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * LGU IDS only — auto-creates and auto-completes referrer "add a note" tasks
 * for deals in Presentation, Contract Stage, or Paid stages with zero notes.
 *
 * One open task per deal per referrer at a time (idempotent).
 * Auto-completed when any note (DealComment or LeadNote) is added.
 */
class LguIdsDealNoteTaskService
{
    const TARGET_STAGES = ['presentation', 'contract_sent', 'paid'];
    const SOURCE_TYPE   = 'lgu_ids_deal_note_task';

    public function __construct(
        private NotificationDispatchService $notifications,
    ) {}

    // ── Public API ─────────────────────────────────────────────────────────────

    /**
     * Create note tasks for all referrers assigned to this deal (if it qualifies).
     * Safe to call multiple times — idempotent per referrer.
     * Returns count of tasks actually created.
     */
    public function createForDeal(Lead $lead, string $trigger = 'manual', bool $notify = true, bool $sendEmail = true): int
    {
        if (! $this->dealQualifies($lead)) {
            return 0;
        }

        $tenant = $this->getLguIdsTenant();
        if (! $tenant || $tenant->id !== $lead->tenant_id) {
            return 0;
        }

        $referrers = $this->getReferrersForDeal($lead);
        $created   = 0;

        foreach ($referrers as $referrer) {
            try {
                $task = $this->createForReferrer($lead, $referrer, $tenant, $trigger, $notify, $sendEmail);
                if ($task !== null) {
                    $created++;
                }
            } catch (\Throwable $e) {
                Log::error('[LguIdsDealNoteTask] Failed to create task for referrer', [
                    'deal_id'     => $lead->id,
                    'referrer_id' => $referrer->id,
                    'trigger'     => $trigger,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        return $created;
    }

    /**
     * Create a task for one referrer on one deal.
     * Returns the new Task, or null if skipped (already exists or not eligible).
     */
    public function createForReferrer(Lead $lead, Reseller $referrer, Tenant $tenant, string $trigger = 'manual', bool $notify = true, bool $sendEmail = true): ?Task
    {
        if ($this->hasOpenTask($lead->id, $referrer->id)) {
            return null;
        }

        $stageLabel  = $this->stageLabel($lead->stage);
        $priority    = $lead->stage === 'presentation' ? 'medium' : 'high';
        $description = $this->taskDescription($lead->stage);
        $dueAt       = now()->addDays(3);

        $task = Task::create([
            'tenant_id'         => $tenant->id,
            'title'             => 'Add a note to "' . $lead->name . '"',
            'description'       => $description,
            'status'            => 'open',
            'priority'          => $priority,
            'category'          => 'deal_note_reminder',
            'assigned_to_type'  => 'reseller',
            'assigned_to_id'    => $referrer->id,
            'assigned_by_type'  => 'system',
            'assigned_by_id'    => 'system',
            'created_by_type'   => 'system',
            'created_by_id'     => 'system',
            'taskable_type'     => 'lead',
            'taskable_id'       => $lead->id,
            'source_type'       => self::SOURCE_TYPE,
            'source_id'         => $lead->id,
            'due_at'            => $dueAt,
            'visibility'        => 'assigned_only',
            'metadata'          => [
                'deal_id'      => $lead->id,
                'deal_name'    => $lead->name,
                'referrer_id'  => $referrer->id,
                'stage'        => $lead->stage,
                'stage_label'  => $stageLabel,
                'trigger'      => $trigger,
                'tenant_slug'  => 'lgu-ids',
            ],
        ]);

        TaskActivity::create([
            'tenant_id'   => $tenant->id,
            'task_id'     => $task->id,
            'actor_type'  => 'system',
            'actor_id'    => 'system',
            'actor_name'  => 'System',
            'action_type' => 'task_created',
            'new_values'  => [
                'status'  => 'open',
                'trigger' => $trigger,
                'stage'   => $lead->stage,
            ],
        ]);

        if ($notify) {
            $this->dispatchInAppNotification($tenant, $referrer, $lead, $task);
        }

        if ($sendEmail && $referrer->email) {
            $this->queueEmail($tenant, $referrer, $lead, $task);
        }

        return $task;
    }

    /**
     * Auto-complete all open note tasks for this deal when a note is added.
     * Returns the number of tasks completed.
     */
    public function autocompleteForDeal(string $tenantId, string $dealId, Reseller $actor): int
    {
        $openTasks = Task::where('tenant_id', $tenantId)
            ->where('source_type', self::SOURCE_TYPE)
            ->where('taskable_type', 'lead')
            ->where('taskable_id', $dealId)
            ->whereIn('status', ['open', 'in_progress', 'waiting'])
            ->whereNull('deleted_at')
            ->get();

        $completed = 0;
        foreach ($openTasks as $task) {
            try {
                $task->update([
                    'status'            => 'completed',
                    'completed_at'      => now(),
                    'completed_by_type' => 'reseller',
                    'completed_by_id'   => $actor->id,
                ]);

                TaskActivity::create([
                    'tenant_id'   => $tenantId,
                    'task_id'     => $task->id,
                    'actor_type'  => 'reseller',
                    'actor_id'    => $actor->id,
                    'actor_name'  => $actor->name,
                    'action_type' => 'task_completed',
                    'new_values'  => [
                        'status'  => 'completed',
                        'trigger' => 'note_added',
                    ],
                ]);

                $completed++;
            } catch (\Throwable $e) {
                Log::warning('[LguIdsDealNoteTask] Autocomplete failed for task', [
                    'task_id' => $task->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        return $completed;
    }

    // ── Eligibility ────────────────────────────────────────────────────────────

    /**
     * True if the deal is in a target stage, active/expiring, not deleted, and has no notes.
     */
    public function dealQualifies(Lead $lead): bool
    {
        if ($lead->deleted_at !== null) {
            return false;
        }
        if (! in_array($lead->stage, self::TARGET_STAGES)) {
            return false;
        }
        if (! in_array($lead->status ?? 'active', ['active', 'expiring'])) {
            return false;
        }
        return $this->dealHasNoNotes($lead->id);
    }

    /**
     * True if this deal has zero notes (DealComment or LeadNote).
     */
    public function dealHasNoNotes(string $dealId): bool
    {
        $hasComment = DB::table('deal_comments')
            ->where('deal_id', $dealId)
            ->where('visibility', 'shared')
            ->whereNull('deleted_at')
            ->whereNull('parent_comment_id')
            ->exists();

        if ($hasComment) {
            return false;
        }

        return ! DB::table('lead_notes')
            ->where('lead_id', $dealId)
            ->exists();
    }

    /**
     * True if an open (not completed/cancelled/archived) system note task already
     * exists for this deal+referrer combination.
     */
    public function hasOpenTask(string $dealId, string $resellerId): bool
    {
        return Task::where('source_type', self::SOURCE_TYPE)
            ->where('source_id', $dealId)
            ->where('taskable_type', 'lead')
            ->where('taskable_id', $dealId)
            ->where('assigned_to_type', 'reseller')
            ->where('assigned_to_id', $resellerId)
            ->whereIn('status', ['open', 'in_progress', 'waiting'])
            ->whereNull('deleted_at')
            ->exists();
    }

    // ── Referrer resolution ────────────────────────────────────────────────────

    /**
     * Returns active Reseller models assigned to this deal (primary + co-referrers).
     */
    public function getReferrersForDeal(Lead $lead): Collection
    {
        if (empty($lead->reseller_name)) {
            return collect();
        }

        $names = collect([$lead->reseller_name]);

        $coReferrerNames = DB::table('commission_splits')
            ->where('lead_id', $lead->id)
            ->pluck('reseller_name');

        $allNames = $names->merge($coReferrerNames)
            ->map(fn ($n) => strtolower(trim($n)))
            ->unique()
            ->filter()
            ->all();

        if (empty($allNames)) {
            return collect();
        }

        return Reseller::where('tenant_id', $lead->tenant_id)
            ->whereNull('deleted_at')
            ->whereIn('status', ['active', 'nda_signed'])
            ->where(function ($q) use ($allNames) {
                foreach ($allNames as $n) {
                    $q->orWhereRaw('LOWER(name) = ?', [$n]);
                }
            })
            ->select('id', 'name', 'email', 'tenant_id')
            ->get();
    }

    // ── In-app notification ────────────────────────────────────────────────────

    public function dispatchInAppNotification(Tenant $tenant, Reseller $referrer, Lead $lead, Task $task): void
    {
        try {
            $stageLabel = $this->stageLabel($lead->stage);
            $dedupSuffix = "lgu_ids_note_task:{$lead->id}:{$referrer->id}";

            $this->notifications->dispatchToReseller(
                resellerId:  $referrer->id,
                tenantId:    $tenant->id,
                category:    'deal_pipeline',
                priority:    $lead->stage === 'presentation' ? 'medium' : 'high',
                title:       'Add a note to "' . $lead->name . '"',
                body:        'Your deal "' . $lead->name . '" has reached ' . $stageLabel . ' but has no notes yet. Add a note to keep your team informed and close faster.',
                actionUrl:   url("/reseller/{$tenant->id}/deals/{$lead->id}") . '#rb-notes',
                actionLabel: 'Add Note',
                dedupeSuffix: $dedupSuffix,
                metadata:    ['task_id' => $task->id, 'deal_id' => $lead->id],
            );
        } catch (\Throwable $e) {
            Log::warning('[LguIdsDealNoteTask] In-app notification failed', [
                'deal_id'     => $lead->id,
                'referrer_id' => $referrer->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    // ── Email notification ─────────────────────────────────────────────────────

    public function queueEmail(Tenant $tenant, Reseller $referrer, Lead $lead, Task $task): void
    {
        if (! $referrer->email) {
            return;
        }

        $emailKey = "lgu_ids_deal_note_task_email:{$tenant->id}:{$referrer->id}:{$lead->id}";

        if (EmailLog::where('email_key', $emailKey)->exists()) {
            return;
        }

        try {
            $log = EmailLog::create([
                'email_key'       => $emailKey,
                'recipient_email' => $referrer->email,
                'recipient_type'  => 'reseller',
                'recipient_id'    => $referrer->id,
                'tenant_id'       => $tenant->id,
                'subject'         => 'Action needed: Add a note to "' . $lead->name . '"',
                'status'          => 'queued',
                'metadata'        => [
                    'type'       => 'lgu_ids_deal_note_task',
                    'deal_id'    => $lead->id,
                    'task_id'    => $task->id,
                    'stage'      => $lead->stage,
                ],
            ]);

            Mail::to($referrer->email)->queue(
                new \App\Mail\LguIdsDealNoteTaskMail(
                    referrerName: $referrer->name,
                    dealName:     $lead->name,
                    stageLabel:   $this->stageLabel($lead->stage),
                    dealUrl:      url("/reseller/{$tenant->id}/deals/{$lead->id}") . '#rb-notes',
                    tenantName:   $tenant->name,
                )
            );

            $log->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('[LguIdsDealNoteTask] Email queue failed', [
                'deal_id'     => $lead->id,
                'referrer_id' => $referrer->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    // ── Tenant resolution ──────────────────────────────────────────────────────

    public function getLguIdsTenant(): ?Tenant
    {
        return Tenant::where('slug', 'lgu-ids')->first();
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function stageLabel(string $stage): string
    {
        return match ($stage) {
            'presentation'  => 'Presentation',
            'contract_sent' => 'Contract Stage',
            'paid'          => 'Paid',
            default         => ucfirst(str_replace('_', ' ', $stage)),
        };
    }

    private function taskDescription(string $stage): string
    {
        return match ($stage) {
            'presentation'  => 'This deal is in the Presentation stage with no notes. Add a note to keep your team updated on presentation outcomes and next steps.',
            'contract_sent' => 'This deal is in the Contract Stage with no notes. Add a note documenting the contract discussion, outstanding items, or expected signing date.',
            'paid'          => 'This deal is marked as Paid with no notes. Add a final note to summarise the outcome and complete the deal record.',
            default         => 'Add a note to keep your team informed and close this deal faster.',
        };
    }
}
