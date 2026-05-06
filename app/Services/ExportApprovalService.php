<?php

namespace App\Services;

use App\Mail\ExportApprovalNeeded;
use App\Mail\ExportApproved;
use App\Mail\ExportExpired;
use App\Mail\ExportReady;
use App\Mail\ExportRejected;
use App\Mail\ExportRequestSubmitted;
use App\Models\ActivityLog;
use App\Models\ExportRequest;
use App\Models\TenantConfig;
use App\Models\TenantUser;
use App\Models\Reseller;
use App\Services\EmailLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExportApprovalService
{
    public function __construct(
        private ExportPermissionService    $permissions,
        private NotificationDispatchService $notifications,
    ) {}

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Create an export request.
     *
     * When $requiresApproval is false the request enters the direct path
     * (status = direct_pending) and GenerateExportJob is dispatched immediately.
     * When $requiresApproval is true the request waits for admin approval.
     *
     * @param  string      $tenantId
     * @param  string      $requesterType   'tenant_user' | 'reseller'
     * @param  string      $requesterId     UUID
     * @param  string      $requesterRole
     * @param  string      $exportType
     * @param  string      $format          'csv' | 'xlsx'
     * @param  array       $scope           Filters / date range etc.
     * @param  array       $fields          Selected field keys
     * @param  string|null $reason
     * @param  bool        $requiresApproval
     * @param  bool        $isSensitive
     * @return ExportRequest
     */
    public function createRequest(
        string  $tenantId,
        string  $requesterType,
        string  $requesterId,
        string  $requesterRole,
        string  $exportType,
        string  $format,
        array   $scope,
        array   $fields,
        ?string $reason,
        bool    $requiresApproval,
        bool    $isSensitive,
    ): ExportRequest {
        $status = $requiresApproval
            ? ExportRequest::STATUS_PENDING
            : ExportRequest::STATUS_DIRECT_PENDING;

        $request = ExportRequest::create([
            'id'               => (string) Str::uuid(),
            'tenant_id'        => $tenantId,
            'requester_type'   => $requesterType,
            'requester_id'     => $requesterId,
            'requester_role'   => $requesterRole,
            'export_type'      => $exportType,
            'export_format'    => $format,
            'export_scope'     => $scope,
            'export_fields'    => $fields,
            'is_sensitive'     => $isSensitive,
            'reason'           => $reason,
            'status'           => $status,
            'records_estimate' => 0,
            'download_count'   => 0,
            'retry_count'      => 0,
        ]);

        $this->auditLog(
            tenantId: $tenantId,
            action:   'export_requested',
            request:  $request,
            actorId:  $requesterType === 'tenant_user' ? $requesterId : null,
            extra:    [
                'export_type'      => $exportType,
                'format'           => $format,
                'requires_approval'=> $requiresApproval,
                'is_sensitive'     => $isSensitive,
                'requester_type'   => $requesterType,
                'requester_role'   => $requesterRole,
            ],
        );

        if ($requiresApproval) {
            // Notify admins that a request is awaiting review (in-app)
            $settings = $this->permissions->getSettings($tenantId);
            if ((bool) ($settings['notify_admin_on_request'] ?? true)) {
                $this->notifications->dispatchToTenantAdmins(
                    tenantId:    $tenantId,
                    category:    'import_export',
                    priority:    'normal',
                    title:       'Export Request Pending',
                    body:        ucfirst($requesterRole) . ' requested a ' . $exportType . ' export. Please review.',
                    actionUrl:   "/tenant/{$tenantId}/exports",
                    actionLabel: 'Review Requests',
                    dedupeSuffix: 'export_pending:' . $request->id,
                    metadata:    [
                        'export_request_id' => $request->id,
                        'export_type'       => $exportType,
                        'requester_type'    => $requesterType,
                        'requester_id'      => $requesterId,
                    ],
                );
            }

            // Send "submitted" confirmation email to the requester
            $this->sendEmail($request, 'submitted');

            // Send "approval needed" emails to all tenant admins
            $this->sendApprovalNeededEmails($request);
        } else {
            // Direct path – dispatch generation job immediately (Phase 2)
            $this->dispatchGenerateJob($request->id);
        }

        return $request->fresh();
    }

    /**
     * Approve a pending export request.
     * Dispatches GenerateExportJob to generate the file asynchronously.
     *
     * @param  ExportRequest $request
     * @param  string        $approverUserId  UUID of the TenantUser approving
     * @return ExportRequest
     */
    public function approve(ExportRequest $request, string $approverUserId): ExportRequest
    {
        $request->update([
            'status'      => ExportRequest::STATUS_APPROVED,
            'approved_by' => $approverUserId,
            'approved_at' => now(),
        ]);

        $this->auditLog(
            tenantId: $request->tenant_id,
            action:   'export_approved',
            request:  $request,
            actorId:  $approverUserId,
            extra:    ['previous_status' => ExportRequest::STATUS_PENDING],
        );

        // Dispatch generation – job will call markProcessing() then markReady() / markFailed()
        $this->dispatchGenerateJob($request->id);

        $this->notifyStatusChange($request, 'approved');

        return $request->fresh();
    }

    /**
     * Reject a pending export request.
     *
     * @param  ExportRequest $request
     * @param  string        $rejectorUserId  UUID of the TenantUser rejecting
     * @param  string        $reason
     * @return ExportRequest
     */
    public function reject(ExportRequest $request, string $rejectorUserId, string $reason): ExportRequest
    {
        $request->update([
            'status'           => ExportRequest::STATUS_REJECTED,
            'rejected_by'      => $rejectorUserId,
            'rejected_at'      => now(),
            'rejection_reason' => $reason,
        ]);

        $this->auditLog(
            tenantId: $request->tenant_id,
            action:   'export_rejected',
            request:  $request,
            actorId:  $rejectorUserId,
            extra:    ['rejection_reason' => $reason],
        );

        $this->notifyStatusChange($request, 'rejected');

        return $request->fresh();
    }

    /**
     * Cancel an export request (called by the requester or an admin).
     *
     * @param  ExportRequest $request
     * @return ExportRequest
     */
    public function cancel(ExportRequest $request): ExportRequest
    {
        $request->update([
            'status' => ExportRequest::STATUS_CANCELLED,
        ]);

        $this->auditLog(
            tenantId: $request->tenant_id,
            action:   'export_cancelled',
            request:  $request,
            actorId:  $request->requester_type === 'tenant_user' ? (string) $request->requester_id : null,
            extra:    ['previous_status' => $request->getOriginal('status')],
        );

        return $request->fresh();
    }

    /**
     * Mark the export as currently generating (called by GenerateExportJob on start).
     *
     * @param  ExportRequest $request
     * @return ExportRequest
     */
    public function markProcessing(ExportRequest $request): ExportRequest
    {
        $request->update([
            'status' => ExportRequest::STATUS_PROCESSING,
        ]);

        return $request->fresh();
    }

    /**
     * Mark the export as ready with file metadata (called by GenerateExportJob on success).
     *
     * @param  ExportRequest $request
     * @param  string        $filePath    Storage path of the generated file
     * @param  string        $fileName    Friendly filename for download
     * @param  int           $fileSize    Size in bytes
     * @param  int           $expiryDays  Days until the file is expired and removed
     * @return ExportRequest
     */
    public function markReady(
        ExportRequest $request,
        string        $filePath,
        string        $fileName,
        int           $fileSize,
        int           $expiryDays,
    ): ExportRequest {
        // Determine terminal ready status based on whether approval was required
        $status = in_array($request->status, [ExportRequest::STATUS_DIRECT_PENDING, ExportRequest::STATUS_PROCESSING], true)
            && $request->approved_by === null
                ? ExportRequest::STATUS_DIRECT_READY
                : ExportRequest::STATUS_READY;

        $request->update([
            'status'          => $status,
            'file_path'       => $filePath,
            'file_name'       => $fileName,
            'file_size'       => $fileSize,
            'file_expires_at' => now()->addDays($expiryDays),
        ]);

        $this->auditLog(
            tenantId: $request->tenant_id,
            action:   'export_ready',
            request:  $request,
            extra:    [
                'file_name'    => $fileName,
                'file_size'    => $fileSize,
                'expires_days' => $expiryDays,
            ],
        );

        $this->notifyStatusChange($request, 'ready');

        return $request->fresh();
    }

    /**
     * Mark the export as failed (called by GenerateExportJob on error).
     *
     * @param  ExportRequest $request
     * @param  string        $errorMessage
     * @return ExportRequest
     */
    public function markFailed(ExportRequest $request, string $errorMessage): ExportRequest
    {
        $request->update([
            'status'        => ExportRequest::STATUS_FAILED,
            'error_message' => $errorMessage,
            'retry_count'   => $request->retry_count + 1,
        ]);

        $this->auditLog(
            tenantId: $request->tenant_id,
            action:   'export_failed',
            request:  $request,
            extra:    [
                'error_message' => $errorMessage,
                'retry_count'   => $request->retry_count,
            ],
        );

        $this->notifyStatusChange($request, 'failed');

        return $request->fresh();
    }

    /**
     * Record a file download event against the request.
     *
     * @param  ExportRequest $request
     * @return void
     */
    public function recordDownload(ExportRequest $request): void
    {
        $request->update([
            'downloaded_at' => $request->downloaded_at ?? now(),
            'download_count'=> $request->download_count + 1,
        ]);

        $this->auditLog(
            tenantId: $request->tenant_id,
            action:   'export_downloaded',
            request:  $request,
            actorId:  $request->requester_type === 'tenant_user' ? (string) $request->requester_id : null,
            extra:    [
                'download_count' => $request->download_count,
                'file_name'      => $request->file_name,
            ],
        );
    }

    /**
     * Expire old requests whose file_expires_at has passed and that are still in
     * a ready state. Called by a scheduled cleanup command.
     *
     * @return int  Number of requests expired
     */
    public function expireOldRequests(): int
    {
        $query = ExportRequest::whereIn('status', [
            ExportRequest::STATUS_READY,
            ExportRequest::STATUS_DIRECT_READY,
        ])->where('file_expires_at', '<', now());

        $requests = $query->get();
        $count    = 0;

        foreach ($requests as $request) {
            $request->update(['status' => ExportRequest::STATUS_EXPIRED]);

            $this->auditLog(
                tenantId: $request->tenant_id,
                action:   'export_expired',
                request:  $request,
                extra:    ['file_name' => $request->file_name],
            );

            // Notify the requester that their file has expired
            $this->sendEmail($request, 'expired');

            $count++;
        }

        return $count;
    }

    /**
     * Retrieve the merged export settings for a tenant.
     *
     * @param  string $tenantId
     * @return array
     */
    public function getSettings(string $tenantId): array
    {
        return $this->permissions->getSettings($tenantId);
    }

    /**
     * Persist overrides to the tenant's export_settings column.
     * Only the keys provided are updated; everything else keeps its current value.
     *
     * @param  string $tenantId
     * @param  array  $settings  Partial or full settings map
     * @return array             The fully merged settings after save
     */
    public function updateSettings(string $tenantId, array $settings): array
    {
        $config = TenantConfig::where('tenant_id', $tenantId)->firstOrFail();

        // Retrieve current stored value safely
        $raw     = $config->getAttributes()['export_settings'] ?? null;
        $current = [];
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $current = is_array($decoded) ? $decoded : [];
        } elseif (is_array($raw)) {
            $current = $raw;
        }

        $merged = array_merge($current, $settings);

        $config->update(['export_settings' => $merged]);

        return array_merge($this->permissions->defaultSettings(), $merged);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Write an audit log entry for an export lifecycle event.
     */
    private function auditLog(
        string        $tenantId,
        string        $action,
        ExportRequest $request,
        ?string       $actorId = null,
        array         $extra   = [],
    ): void {
        ActivityLog::create([
            'tenant_id' => $tenantId,
            'user_id'   => $actorId,
            'action'    => $action,
            'entity'    => 'export_request',
            'entity_id' => $request->id,
            'metadata'  => array_merge([
                'export_request_id' => $request->id,
                'export_type'       => $request->export_type,
                'export_format'     => $request->export_format,
                'requester_type'    => $request->requester_type,
                'requester_id'      => (string) $request->requester_id,
                'requester_role'    => $request->requester_role,
                'status'            => $request->status,
            ], $extra),
        ]);
    }

    /**
     * Dispatch in-app notifications based on a status-change event.
     * Covers: approved, rejected, ready, failed.
     */
    private function notifyStatusChange(ExportRequest $request, string $event): void
    {
        $settings = $this->permissions->getSettings($request->tenant_id);

        match ($event) {
            'approved' => $this->notifyRequester(
                request:     $request,
                title:       'Export Request Approved',
                body:        'Your ' . $request->export_type . ' export has been approved and is now being generated.',
                actionUrl:   "/tenant/{$request->tenant_id}/exports/{$request->id}",
                actionLabel: 'View Export',
                dedupeSuffix: 'export_approved:' . $request->id,
                settings:    $settings,
            ),

            'rejected' => $this->notifyRequester(
                request:     $request,
                title:       'Export Request Rejected',
                body:        'Your ' . $request->export_type . ' export request was rejected.'
                    . ($request->rejection_reason ? ' Reason: ' . $request->rejection_reason : ''),
                actionUrl:   "/tenant/{$request->tenant_id}/exports/{$request->id}",
                actionLabel: 'View Details',
                dedupeSuffix: 'export_rejected:' . $request->id,
                settings:    $settings,
            ),

            'ready' => $this->notifyRequester(
                request:     $request,
                title:       'Export Ready to Download',
                body:        'Your ' . $request->export_type . ' export file is ready.'
                    . ($request->file_expires_at ? ' It expires on ' . $request->file_expires_at->format('M j, Y') . '.' : ''),
                actionUrl:   "/tenant/{$request->tenant_id}/exports/{$request->id}",
                actionLabel: 'Download Now',
                dedupeSuffix: 'export_ready:' . $request->id,
                settings:    $settings,
            ),

            'failed' => $this->handleFailedNotification($request, $settings),

            default => null,
        };

        // Also send emails where applicable
        $this->sendEmail($request, $event);
    }

    /**
     * Dispatch an in-app notification to the requester (tenant_user or reseller).
     */
    private function notifyRequester(
        ExportRequest $request,
        string        $title,
        string        $body,
        string        $actionUrl,
        string        $actionLabel,
        string        $dedupeSuffix,
        array         $settings,
    ): void {
        if (!(bool) ($settings['notify_requester_on_decision'] ?? true)) {
            return;
        }

        $metadata = [
            'export_request_id' => $request->id,
            'export_type'       => $request->export_type,
        ];

        if ($request->requester_type === 'tenant_user') {
            $this->notifications->dispatch(
                category:         'import_export',
                priority:         'normal',
                title:            $title,
                body:             $body,
                notifiableType:   'tenant_admin',
                notifiableId:     (string) $request->requester_id,
                tenantId:         $request->tenant_id,
                actionUrl:        $actionUrl,
                actionLabel:      $actionLabel,
                deduplicationKey: "import_export:{$request->requester_id}:{$dedupeSuffix}",
                metadata:         $metadata,
            );
        } elseif ($request->requester_type === 'reseller') {
            $this->notifications->dispatchToReseller(
                resellerId:   (string) $request->requester_id,
                tenantId:     $request->tenant_id,
                category:     'import_export',
                priority:     'normal',
                title:        $title,
                body:         $body,
                actionUrl:    $actionUrl,
                actionLabel:  $actionLabel,
                dedupeSuffix: $dedupeSuffix,
                metadata:     $metadata,
            );
        }
    }

    /**
     * On failure, notify both the requester and tenant admins.
     */
    private function handleFailedNotification(ExportRequest $request, array $settings): void
    {
        // Notify requester
        $this->notifyRequester(
            request:     $request,
            title:       'Export Generation Failed',
            body:        'Your ' . $request->export_type . ' export could not be generated.'
                . ($request->error_message ? ' Details: ' . $request->error_message : ' Please try again or contact support.'),
            actionUrl:   "/tenant/{$request->tenant_id}/exports/{$request->id}",
            actionLabel: 'View Details',
            dedupeSuffix: 'export_failed:' . $request->id,
            settings:    $settings,
        );

        // Notify admins so they can investigate
        $this->notifications->dispatchToTenantAdmins(
            tenantId:    $request->tenant_id,
            category:    'import_export',
            priority:    'high',
            title:       'Export Generation Failed',
            body:        'An export request for ' . $request->export_type . ' failed to generate. Request ID: ' . $request->id,
            actionUrl:   "/tenant/{$request->tenant_id}/exports/{$request->id}",
            actionLabel: 'View Request',
            dedupeSuffix: 'export_failed_admin:' . $request->id,
            metadata:    [
                'export_request_id' => $request->id,
                'export_type'       => $request->export_type,
                'error_message'     => $request->error_message,
            ],
        );
    }

    /**
     * Send transactional emails for export lifecycle events.
     *
     * Supported events: submitted, approved, rejected, ready, expired
     */
    private function sendEmail(ExportRequest $request, string $event): void
    {
        $settings = $this->permissions->getSettings($request->tenant_id);

        if (!(bool) ($settings['notify_requester_on_decision'] ?? true)) {
            return;
        }

        // Resolve requester name and email
        [$requesterName, $requesterEmail] = $this->resolveRequesterContact($request);

        if (!$requesterEmail) {
            return;
        }

        $tenantName  = DB::table('tenants')->where('id', $request->tenant_id)->value('name') ?? 'your tenant';
        $exportLabel = ucfirst(str_replace('_', ' ', $request->export_type));

        match ($event) {

            'submitted' => EmailLogger::send(
                mailable:       new ExportRequestSubmitted(
                    requesterName:    $requesterName,
                    exportType:       $exportLabel,
                    tenantName:       $tenantName,
                    exportRequestUrl: url("/tenant/{$request->tenant_id}/exports/{$request->id}"),
                ),
                recipientEmail: $requesterEmail,
                recipientType:  $request->requester_type,
                recipientId:    (string) $request->requester_id,
                emailKey:       "export_submitted:{$request->id}",
                subject:        'Your export request was submitted — ReferralBunny.ai',
                tenantId:       $request->tenant_id,
            ),

            'approved' => EmailLogger::send(
                mailable:       new ExportApproved(
                    requesterName: $requesterName,
                    exportType:    $exportLabel,
                    tenantName:    $tenantName,
                    dashboardUrl:  url("/tenant/{$request->tenant_id}/exports"),
                ),
                recipientEmail: $requesterEmail,
                recipientType:  $request->requester_type,
                recipientId:    (string) $request->requester_id,
                emailKey:       "export_approved:{$request->id}",
                subject:        'Your export request was approved — ReferralBunny.ai',
                tenantId:       $request->tenant_id,
            ),

            'rejected' => EmailLogger::send(
                mailable:       new ExportRejected(
                    requesterName:   $requesterName,
                    exportType:      $exportLabel,
                    rejectionReason: $request->rejection_reason,
                    tenantName:      $tenantName,
                ),
                recipientEmail: $requesterEmail,
                recipientType:  $request->requester_type,
                recipientId:    (string) $request->requester_id,
                emailKey:       "export_rejected:{$request->id}",
                subject:        'Your export request was not approved — ReferralBunny.ai',
                tenantId:       $request->tenant_id,
            ),

            'ready' => EmailLogger::send(
                mailable:       new ExportReady(
                    requesterName: $requesterName,
                    exportType:    $exportLabel,
                    tenantName:    $tenantName,
                    downloadUrl:   url("/tenant/{$request->tenant_id}/exports/{$request->id}/download"),
                    expiresAt:     $request->file_expires_at
                        ? $request->file_expires_at->format('F j, Y')
                        : 'soon',
                ),
                recipientEmail: $requesterEmail,
                recipientType:  $request->requester_type,
                recipientId:    (string) $request->requester_id,
                emailKey:       "export_ready:{$request->id}",
                subject:        'Your export file is ready — ReferralBunny.ai',
                tenantId:       $request->tenant_id,
            ),

            'expired' => EmailLogger::send(
                mailable:       new ExportExpired(
                    requesterName: $requesterName,
                    exportType:    $exportLabel,
                    tenantName:    $tenantName,
                ),
                recipientEmail: $requesterEmail,
                recipientType:  $request->requester_type,
                recipientId:    (string) $request->requester_id,
                emailKey:       "export_expired:{$request->id}",
                subject:        'Your export file has expired — ReferralBunny.ai',
                tenantId:       $request->tenant_id,
            ),

            default => null,
        };
    }

    /**
     * Send "approval needed" email to all active tenant admins for a new pending request.
     */
    private function sendApprovalNeededEmails(ExportRequest $request): void
    {
        $settings = $this->permissions->getSettings($request->tenant_id);

        if (!(bool) ($settings['notify_admin_on_request'] ?? true)) {
            return;
        }

        $tenantName  = DB::table('tenants')->where('id', $request->tenant_id)->value('name') ?? 'your tenant';
        $exportLabel = ucfirst(str_replace('_', ' ', $request->export_type));
        $reviewUrl   = url("/tenant/{$request->tenant_id}/exports/{$request->id}");

        [$requesterName] = $this->resolveRequesterContact($request);

        $admins = DB::table('tenant_memberships as tm')
            ->join('tenant_users as u', 'tm.tenant_user_id', '=', 'u.id')
            ->where('tm.tenant_id', $request->tenant_id)
            ->where('tm.status', 'active')
            ->whereIn('tm.role', ['owner', 'admin'])
            ->whereNotNull('u.email')
            ->select('u.id', 'u.first_name', 'u.last_name', 'u.email')
            ->get();

        foreach ($admins as $admin) {
            $adminName = trim("{$admin->first_name} {$admin->last_name}") ?: $admin->email;

            EmailLogger::send(
                mailable:       new ExportApprovalNeeded(
                    adminName:     $adminName,
                    requesterName: $requesterName,
                    requesterRole: $request->requester_role,
                    exportType:    $exportLabel,
                    reason:        $request->reason ?? '',
                    tenantName:    $tenantName,
                    reviewUrl:     $reviewUrl,
                ),
                recipientEmail: $admin->email,
                recipientType:  'tenant_user',
                recipientId:    (string) $admin->id,
                emailKey:       "export_approval_needed:{$request->id}:{$admin->id}",
                subject:        "Export request needs your approval — {$tenantName}",
                tenantId:       $request->tenant_id,
            );
        }
    }

    /**
     * Resolve the requester's display name and email from either tenant_users or resellers.
     *
     * @return array{string, string|null}  [name, email]
     */
    private function resolveRequesterContact(ExportRequest $request): array
    {
        if ($request->requester_type === 'tenant_user') {
            $user = TenantUser::find($request->requester_id);
            if (!$user) return ['User', null];

            $name  = trim("{$user->first_name} {$user->last_name}") ?: $user->email;
            return [$name, $user->email];
        }

        if ($request->requester_type === 'reseller') {
            $reseller = Reseller::find($request->requester_id);
            if (!$reseller) return ['Referrer', null];

            return [$reseller->name ?? 'Referrer', $reseller->email];
        }

        return ['User', null];
    }

    /**
     * Dispatch the GenerateExportJob when it exists (Phase 2).
     * Uses a class_exists guard so Phase 1 code works in isolation before
     * the job class is created.
     */
    private function dispatchGenerateJob(string $requestId): void
    {
        $jobClass = 'App\\Jobs\\GenerateExportJob';

        if (class_exists($jobClass)) {
            $jobClass::dispatch($requestId);
        }
        // If the job does not exist yet the request stays in direct_pending
        // status until Phase 2 wires up the job and processes it.
    }
}
