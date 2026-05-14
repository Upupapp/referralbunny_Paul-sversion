<?php

namespace App\Services;

use App\Mail\TaskCompletionResponseMail;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskCompletionResponse;
use App\Services\EmailContentFormatter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TaskCompletionService
{
    /**
     * Complete a task without sending a response email.
     */
    public function complete(Task $task, string $actorType, string $actorId, string $actorName): Task
    {
        DB::transaction(function () use ($task, $actorType, $actorId, $actorName) {
            $task->update([
                'status'           => 'completed',
                'completed_at'     => now(),
                'completed_by_type'=> $actorType,
                'completed_by_id'  => $actorId,
            ]);

            TaskActivity::create([
                'tenant_id'  => $task->tenant_id,
                'task_id'    => $task->id,
                'actor_type' => $actorType,
                'actor_id'   => $actorId,
                'actor_name' => $actorName,
                'action_type'=> 'task_completed',
                'new_values' => ['status' => 'completed'],
            ]);
        });

        return $task->fresh();
    }

    /**
     * Complete a task AND queue a completion response email to the requestor.
     * Task completion happens even if email queueing fails.
     */
    public function completeWithResponse(
        Task   $task,
        string $actorType,
        string $actorId,
        string $actorName,
        ?string $subject = null,
        ?string $body = null,
        array   $attachmentPaths = [],
        ?string $clientRequestId = null,
        bool   $sendEmail = true,
    ): array {
        // Idempotency: prevent duplicate sends
        if ($clientRequestId) {
            $existing = TaskCompletionResponse::where('client_request_id', $clientRequestId)->first();
            if ($existing) {
                return ['task' => $task->fresh(), 'response' => $existing, 'email_status' => $existing->status];
            }
        }

        $requestorEmail = $task->resolveRequestorEmail();
        $requestorName  = $task->resolveRequestorName();

        $response = null;
        $emailQueued = false;

        DB::transaction(function () use (
            $task, $actorType, $actorId, $actorName,
            $subject, $body, $attachmentPaths,
            $requestorEmail, $requestorName, $clientRequestId,
            &$response
        ) {
            // Complete the task
            $task->update([
                'status'            => 'completed',
                'completed_at'      => now(),
                'completed_by_type' => $actorType,
                'completed_by_id'   => $actorId,
            ]);

            // Create completion response record (store plain text body; HTML rendered at send time)
            $response = TaskCompletionResponse::create([
                'tenant_id'                  => $task->tenant_id,
                'task_id'                    => $task->id,
                'request_form_submission_id' => $task->source_type === 'request_form_submission'
                    ? $task->source_id : null,
                'sender_type'    => $actorType,
                'sender_id'      => $actorId,
                'sender_name'    => $actorName,
                'recipient_email'=> $requestorEmail,
                'recipient_name' => $requestorName,
                'subject'        => $subject ?? '',
                'body'           => EmailContentFormatter::sanitizePlainText($body ?? ''),
                'status'         => 'queued',
                'attachment_paths' => $attachmentPaths ?: null,
                'client_request_id' => $clientRequestId,
            ]);

            // Log activities
            TaskActivity::create([
                'tenant_id'  => $task->tenant_id,
                'task_id'    => $task->id,
                'actor_type' => $actorType,
                'actor_id'   => $actorId,
                'actor_name' => $actorName,
                'action_type'=> 'task_completed',
                'new_values' => ['status' => 'completed'],
            ]);

            TaskActivity::create([
                'tenant_id'  => $task->tenant_id,
                'task_id'    => $task->id,
                'actor_type' => $actorType,
                'actor_id'   => $actorId,
                'actor_name' => $actorName,
                'action_type'=> 'completion_response_queued',
                'new_values' => [
                    'response_id'     => $response->id,
                    'recipient_email' => $requestorEmail,
                    'subject'         => $subject,
                ],
            ]);
        });

        // After commit: send email only if requested and an address is available.
        if ($sendEmail && $requestorEmail) {
            try {
                $bodyHtml = EmailContentFormatter::renderTaskResponseEmailBody($body);
                Mail::to($requestorEmail)
                    ->queue(new TaskCompletionResponseMail(
                        recipientName: $requestorName ?? $requestorEmail,
                        senderName:    $actorName,
                        emailSubject:  $subject,
                        bodyPlain:     EmailContentFormatter::sanitizePlainText($body),
                        bodyHtml:      $bodyHtml,
                        attachments:   $attachmentPaths,
                        taskTitle:     $task->title,
                        tenantId:      $task->tenant_id,
                    ));

                $response->update(['status' => 'sent', 'sent_at' => now()]);
                $emailQueued = true;

                TaskActivity::create([
                    'tenant_id'  => $task->tenant_id,
                    'task_id'    => $task->id,
                    'actor_type' => 'system',
                    'actor_id'   => 'system',
                    'actor_name' => 'System',
                    'action_type'=> 'completion_response_sent',
                    'new_values' => ['response_id' => $response->id, 'status' => 'sent'],
                ]);
            } catch (\Throwable $e) {
                $response->update([
                    'status'         => 'failed',
                    'failed_at'      => now(),
                    'failure_reason' => $e->getMessage(),
                ]);

                TaskActivity::create([
                    'tenant_id'  => $task->tenant_id,
                    'task_id'    => $task->id,
                    'actor_type' => 'system',
                    'actor_id'   => 'system',
                    'actor_name' => 'System',
                    'action_type'=> 'completion_response_failed',
                    'new_values' => ['response_id' => $response->id, 'error' => $e->getMessage()],
                ]);
            }
        } else {
            // No email requested or no address — response saved, mark as skipped.
            $response->update(['status' => 'skipped']);
        }

        return [
            'task'         => $task->fresh(),
            'response'     => $response->fresh(),
            'email_status' => $emailQueued ? 'sent' : ($sendEmail && $requestorEmail ? 'failed' : 'skipped'),
        ];
    }

    /**
     * Store uploaded attachments for a completion response.
     * Returns array of attachment metadata.
     */
    public function storeAttachments(string $tenantId, string $taskId, array $files): array
    {
        $stored = [];
        foreach ($files as $file) {
            try {
                $original = $file->getClientOriginalName();
                // Reject filenames with path separators or excessive length (path traversal guard)
                if (str_contains($original, '/') || str_contains($original, '\\') || strlen($original) > 255) {
                    continue;
                }
                $sanitized   = preg_replace('/[^a-zA-Z0-9._-]/', '_', $original);
                $stored_name = Str::uuid() . '_' . $sanitized;
                $path  = "tenants/{$tenantId}/tasks/{$taskId}/responses/{$stored_name}";

                Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

                $stored[] = [
                    'disk'     => 'local',
                    'path'     => $path,
                    'filename' => $original,
                    'size'     => $file->getSize(),
                    'mime'     => $file->getMimeType(),
                ];
            } catch (\Throwable) {
                // Skip files that fail to upload
            }
        }
        return $stored;
    }
}
