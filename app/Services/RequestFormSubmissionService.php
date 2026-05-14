<?php

namespace App\Services;

use App\Mail\TaskAssignedMail;
use App\Models\RequestForm;
use App\Models\RequestFormSubmission;
use App\Models\RequestFormSubmissionRecipient;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Services\NotificationDispatchService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class RequestFormSubmissionService
{
    /**
     * Process a public form submission: create submission record, create tasks per recipient,
     * and dispatch notifications. Returns the submission model.
     */
    public function process(RequestForm $form, array $validated, string $ipHash, string $uaHash): RequestFormSubmission
    {
        $tenantId       = $form->tenant_id;
        $submitterName  = $validated['name']  ?? $validated['submitter_name'] ?? '';
        $submitterEmail = $validated['email'] ?? $validated['submitter_email'] ?? '';
        $requestFor     = $validated['request_for'] ?? null;
        $notes          = $validated['notes'] ?? null;
        $recipientIds   = $validated['request_to'] ?? [];
        if (!is_array($recipientIds)) $recipientIds = [$recipientIds];

        // Fingerprint for duplicate detection (same person, same request, within 60s)
        $fingerprint = hash('sha256', implode('|', [
            $form->id,
            strtolower(trim($submitterEmail)),
            $requestFor ?? '',
            substr($notes ?? '', 0, 200),
            implode(',', $recipientIds),
            floor(time() / 60),   // 60-second window
        ]));

        // Check for duplicate
        $isDuplicate = RequestFormSubmission::where('tenant_id', $tenantId)
            ->where('duplicate_fingerprint', $fingerprint)
            ->exists();

        if ($isDuplicate) {
            // Return a fake "success" submission without creating tasks
            return new RequestFormSubmission([
                'tenant_id'         => $tenantId,
                'request_form_id'   => $form->id,
                'submitter_name'    => $submitterName,
                'submitter_email'   => $submitterEmail,
                'status'            => 'received',
                'public_submission_uuid' => (string) Str::uuid(),
                'submitted_at'      => now(),
            ]);
        }

        // Resolve allowed recipient options
        $allowedRecipients = $form->recipientOptions()
            ->whereIn('id', $recipientIds)
            ->get()
            ->keyBy('id');

        $submission = DB::transaction(function () use (
            $form, $tenantId, $submitterName, $submitterEmail,
            $requestFor, $notes, $validated, $recipientIds,
            $allowedRecipients, $fingerprint, $ipHash, $uaHash
        ) {
            $submission = RequestFormSubmission::create([
                'tenant_id'              => $tenantId,
                'request_form_id'        => $form->id,
                'submitter_name'         => $submitterName,
                'submitter_email'        => $submitterEmail,
                'request_for'            => $requestFor,
                'notes'                  => $notes,
                'payload'                => $validated,
                'selected_recipient_ids' => $recipientIds,
                'source_ip_hash'         => $ipHash,
                'user_agent_hash'        => $uaHash,
                'status'                 => 'received',
                'duplicate_fingerprint'  => $fingerprint,
                'submitted_at'           => now(),
            ]);

            $taskCount  = 0;
            $taskIds    = [];

            foreach ($allowedRecipients as $recipient) {
                $taskTitle = 'New request: ' . ($requestFor ?: 'General');

                $task = Task::create([
                    'tenant_id'       => $tenantId,
                    'title'           => $taskTitle,
                    'description'     => $this->buildTaskDescription($submission, $form, $recipient->display_name),
                    'status'          => 'open',
                    'priority'        => 'medium',
                    'category'        => 'request_form',
                    'assigned_to_type'=> 'tenant_user',
                    'assigned_to_id'  => $recipient->recipient_id,
                    'created_by_type' => 'system',
                    'created_by_id'   => 'system',
                    'source_type'     => 'request_form_submission',
                    'source_id'       => $submission->id,
                    'requestor_name'  => $submitterName,
                    'requestor_email' => $submitterEmail,
                    'visibility'      => 'tenant_team',
                    'metadata'        => [
                        'form_id'       => $form->id,
                        'form_title'    => $form->title,
                        'submission_id' => $submission->id,
                    ],
                ]);

                TaskActivity::create([
                    'tenant_id'   => $tenantId,
                    'task_id'     => $task->id,
                    'actor_type'  => 'system',
                    'actor_id'    => 'system',
                    'actor_name'  => 'System',
                    'action_type' => 'task_created',
                    'new_values'  => ['source' => 'public_request_form', 'form_title' => $form->title],
                ]);

                RequestFormSubmissionRecipient::create([
                    'tenant_id'                   => $tenantId,
                    'request_form_submission_id'  => $submission->id,
                    'recipient_type'              => $recipient->recipient_type,
                    'recipient_id'                => $recipient->recipient_id,
                    'recipient_email'             => $recipient->email,
                    'recipient_name'              => $recipient->display_name,
                    'task_id'                     => $task->id,
                    'assignment_status'           => 'task_created',
                ]);

                $taskCount++;
                $taskIds[] = $task->id;
            }

            $submission->update([
                'status' => $taskCount > 0 ? 'tasks_created' : 'failed',
            ]);

            return $submission;
        });

        // After transaction: send in-app notifications + emails (fail silently each)
        $notifService = app(NotificationDispatchService::class);

        $recipientNames  = $allowedRecipients->pluck('display_name')->implode(', ');
        $recipientCount  = $allowedRecipients->count();
        $requestLabel    = $requestFor ?: 'General';

        // Build recipient_id → task_id map so notifications can link to the specific task
        $recipientTaskIds = RequestFormSubmissionRecipient::where('request_form_submission_id', $submission->id)
            ->whereNotNull('task_id')
            ->pluck('task_id', 'recipient_id')
            ->toArray();

        // ── 1. Notify each assigned recipient (in-app + email) ──────────────
        foreach ($allowedRecipients as $recipient) {
            if ($recipient->recipient_id) {
                $taskId  = $recipientTaskIds[$recipient->recipient_id] ?? null;
                $taskUrl = $taskId
                    ? "/tenant/{$tenantId}/tasks/{$taskId}"
                    : "/tenant/{$tenantId}/tasks";
                try {
                    $notifService->dispatch(
                        category:         'request_form',
                        priority:         'urgent',
                        title:            "New request: {$requestLabel}",
                        body:             "\"{$submitterName}\" submitted a request via \"{$form->title}\". Review and respond.",
                        notifiableType:   'tenant_user',
                        notifiableId:     $recipient->recipient_id,
                        tenantId:         $tenantId,
                        actionUrl:        $taskUrl,
                        actionLabel:      'View Task',
                        deduplicationKey: "req-form-assignee:{$submission->id}:{$recipient->recipient_id}",
                    );
                } catch (\Throwable) {}
            }

            try {
                Mail::to($recipient->email)
                    ->queue(new TaskAssignedMail(
                        recipientName: $recipient->display_name,
                        submitterName: $submitterName,
                        requestFor:    $requestLabel,
                        formTitle:     $form->title,
                        notes:         $notes,
                        tenantId:      $tenantId,
                    ));
            } catch (\Throwable) {}
        }

        // ── 2. Notify tenant admins (consolidated — one notification per submission) ─
        // Skip admins who are already a recipient (they get the assignee notification above).
        $assignedRecipientIds = $allowedRecipients->pluck('recipient_id')->filter()->values()->toArray();

        $body = $recipientCount > 0
            ? "\"{$submitterName}\" submitted a {$requestLabel} request. Assigned to: {$recipientNames}."
            : "\"{$submitterName}\" submitted a {$requestLabel} request via \"{$form->title}\". No assignee was selected.";

        try {
            $notifService->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'request_form',
                priority:     'normal',
                title:        "New form response: {$form->title}",
                body:         $body,
                actionUrl:    "/tenant/{$tenantId}/request-forms/{$form->id}/submissions",
                actionLabel:  'View Responses',
                dedupeSuffix: "req-form-admin:{$submission->id}",
                metadata:     [
                    'submission_id'    => $submission->id,
                    'form_id'          => $form->id,
                    'form_title'       => $form->title,
                    'submitter_name'   => $submitterName,
                    'submitter_email'  => $submitterEmail,
                    'request_for'      => $requestLabel,
                    'recipient_count'  => $recipientCount,
                ],
            );
        } catch (\Throwable) {}

        return $submission;
    }

    private function buildTaskDescription(RequestFormSubmission $sub, RequestForm $form, string $recipientName): string
    {
        return implode("\n", [
            "From: {$sub->submitter_name} <{$sub->submitter_email}>",
            "Request For: " . ($sub->request_for ?: '—'),
            "Notes: " . ($sub->notes ?: '—'),
            "",
            "Form: {$form->title}",
            "Submitted: " . now()->format('M j, Y g:i A'),
            "Reference: {$sub->public_submission_uuid}",
        ]);
    }
}
