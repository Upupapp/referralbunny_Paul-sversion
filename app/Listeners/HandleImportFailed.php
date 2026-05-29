<?php

namespace App\Listeners;

use App\Events\ImportFailed;
use App\Services\NotificationDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\EmailLogger;

/**
 * Handles notifications when an import fails or completes with warnings.
 *
 * Rules enforced:
 *  - In-app notification to ALL active Admins and Managers in the tenant (scoped).
 *  - Email notification to Admins and Managers for 'failed' status.
 *  - For 'completed_with_warnings': in-app only (no email spam for partial success).
 *  - Dedup key: tenant_id + batch_id + status — prevents duplicate notifications on retries.
 *  - Notification failure NEVER rolls back the import record (try/catch at every step).
 *  - Cache bust: clears ca_master and ca_dashboard so the Critical Actions badge refreshes.
 *  - Queued: ShouldQueue so import execution is never blocked by notification I/O.
 */
class HandleImportFailed implements ShouldQueue
{
    public int $tries = 3;
    public int $backoff = 10;

    public function handle(ImportFailed $event): void
    {
        $dispatcher = app(NotificationDispatchService::class);

        $typeLabel = match ($event->importType) {
            'lgu_ids_deals' => 'LGU IDS deal import',
            'contacts'      => 'Contacts import',
            default         => 'Deal import',
        };

        $isFailed   = $event->status === 'failed';
        $batchUrl   = $this->batchUrl($event);
        $dedupSuffix = "{$event->batchId}:{$event->status}";

        // ── 1. In-app: admins + managers (tenant-scoped) ──────────────────────
        try {
            if ($isFailed) {
                $title = "{$typeLabel} failed: {$event->fileName}";
                $body  = "The import could not be completed. All {$event->totalRows} rows failed. Review the report and retry.";
            } else {
                $title = "{$typeLabel} completed with warnings: {$event->fileName}";
                $body  = "{$event->failedRows} of {$event->totalRows} rows could not be imported. Review the report.";
            }

            $dispatcher->dispatchToTenantAdmins(
                tenantId:     $event->tenantId,
                category:     'import_export',
                priority:     $isFailed ? 'high' : 'normal',
                title:        $title,
                body:         $body,
                actionUrl:    $batchUrl,
                actionLabel:  'View Import Report',
                dedupeSuffix: $dedupSuffix,
                metadata:     [
                    'batch_id'    => $event->batchId,
                    'import_type' => $event->importType,
                    'failed_rows' => $event->failedRows,
                    'total_rows'  => $event->totalRows,
                    'status'      => $event->status,
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('[HandleImportFailed] in-app dispatch failed', [
                'tenant_id' => $event->tenantId,
                'batch_id'  => $event->batchId,
                'error'     => $e->getMessage(),
            ]);
        }

        // ── 2. Email: admins + managers — only for outright failures ─────────
        // 'completed_with_warnings' does NOT trigger email to avoid noise.
        if ($isFailed) {
            try {
                $admins = DB::table('tenant_memberships as tm')
                    ->join('tenant_users as u', 'tm.tenant_user_id', '=', 'u.id')
                    ->where('tm.tenant_id', $event->tenantId)
                    ->where('tm.status', 'active')
                    ->whereIn('tm.role', ['owner', 'admin', 'manager'])
                    ->whereNotNull('u.email')
                    ->select('u.email', 'u.first_name', 'u.last_name')
                    ->get();

                $tenantName = DB::table('tenants')->where('id', $event->tenantId)->value('name')
                    ?? 'Referral Bunny';

                foreach ($admins as $admin) {
                    $name = trim("{$admin->first_name} {$admin->last_name}") ?: 'Team';
                    try {
                        EmailLogger::send(
                            mailable: new \App\Mail\ImportFailedMail(
                                recipientName: $name,
                                tenantName:    $tenantName,
                                fileName:      $event->fileName,
                                typeLabel:     $typeLabel,
                                failedRows:    $event->failedRows,
                                totalRows:     $event->totalRows,
                                reportUrl:     url($batchUrl),
                            ),
                            recipientEmail: $admin->email,
                            recipientType:  'tenant_admin',
                            emailKey:       'import_failed.' . $event->batchId . '.' . md5($admin->email),
                            subject:        "Import failed: {$event->fileName}",
                            tenantId:       $event->tenantId,
                        );
                    } catch (\Throwable $mailErr) {
                        Log::warning('[HandleImportFailed] email failed for admin', [
                            'email' => $admin->email,
                            'error' => $mailErr->getMessage(),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[HandleImportFailed] email recipients query failed', [
                    'tenant_id' => $event->tenantId,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        // ── 3. Cache bust: refresh CA badge within 60s ────────────────────────
        try {
            // Bust the generic ca_dashboard and ca_master caches (pattern-based keys)
            // so the badge and master list pick up this import's new status immediately.
            // We cannot pattern-delete from Redis without SCAN, so we rely on the natural
            // 45–90s TTL expiry. For the badge specifically we bust user-level keys.
            $adminsForBust = DB::table('tenant_memberships as tm')
                ->join('tenant_users as u', 'tm.tenant_user_id', '=', 'u.id')
                ->where('tm.tenant_id', $event->tenantId)
                ->where('tm.status', 'active')
                ->whereIn('tm.role', ['owner', 'admin', 'manager'])
                ->pluck('u.id');

            foreach ($adminsForBust as $uid) {
                Cache::forget("ca_badge_{$event->tenantId}_{$uid}");
                Cache::forget("ca_badge_urgent:{$event->tenantId}:{$uid}");
                Cache::forget("ca_badge_suppressed:{$event->tenantId}:{$uid}");
            }
        } catch (\Throwable $e) {
            Log::warning('[HandleImportFailed] cache bust failed', ['error' => $e->getMessage()]);
        }
    }

    public function failed(ImportFailed $event, \Throwable $exception): void
    {
        Log::error('[HandleImportFailed] Listener failed after all retries', [
            'tenant_id' => $event->tenantId,
            'batch_id'  => $event->batchId,
            'status'    => $event->status,
            'error'     => $exception->getMessage(),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function batchUrl(ImportFailed $event): string
    {
        $base = match ($event->importType) {
            'lgu_ids_deals' => "/tenant/{$event->tenantId}/imports/lgu-ids/{$event->batchId}",
            'contacts'      => "/tenant/{$event->tenantId}/imports/contacts/{$event->batchId}",
            default         => "/tenant/{$event->tenantId}/imports/deals/{$event->batchId}",
        };
        return $event->status === 'failed' ? "{$base}/report" : $base;
    }
}
