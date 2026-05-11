<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Models\ImportRollback;
use App\Services\ImportRollbackService;
use App\Services\NotificationDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued job that executes an import batch rollback.
 *
 * Uses rollback_id + tenant_id only — does NOT trust any frontend data.
 * The actual rollback logic is in ImportRollbackService.
 */
class ProcessImportRollbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes max
    public int $tries   = 1;   // Do not auto-retry; rollback must be idempotent or manually re-run

    public function __construct(
        public readonly string $rollbackId,
        public readonly string $batchId,
        public readonly string $tenantId,
    ) {}

    public function handle(ImportRollbackService $service): void
    {
        $rollback = ImportRollback::where('id', $this->rollbackId)
            ->where('import_batch_id', $this->batchId) // verify rollback ↔ batch link
            ->where('tenant_id', $this->tenantId)      // tenant isolation
            ->first();

        $batch = ImportBatch::where('id', $this->batchId)
            ->where('tenant_id', $this->tenantId)
            ->first();

        if (!$rollback || !$batch) {
            return; // Silently skip — IDs don't match or tenant mismatch
        }

        // Guard: do not execute if already completed/failed
        if (in_array($rollback->status, ['completed', 'completed_with_warnings', 'failed', 'cancelled'])) {
            return;
        }

        try {
            $service->execute($rollback, $batch, $this->tenantId);
        } catch (\Throwable $e) {
            $rollback->markFailed($e->getMessage());
            $batch->update(['rollback_status' => 'failed']);
            $this->notifyAdminsOfFailure($batch->file_name ?? 'unknown file', $e->getMessage());
        }
    }

    public function failed(\Throwable $e): void
    {
        // Mark rollback as failed if the job itself fails (e.g. timeout)
        try {
            $rollback = ImportRollback::find($this->rollbackId);
            if ($rollback && !$rollback->isCompleted()) {
                $rollback->markFailed('Job failed: ' . $e->getMessage());
                $batch = ImportBatch::where('id', $this->batchId)->first();
                if ($batch) {
                    $batch->update(['rollback_status' => 'failed']);
                    $this->notifyAdminsOfFailure($batch->file_name ?? 'unknown file', $e->getMessage());
                }
            }
        } catch (\Throwable) {}
    }

    private function notifyAdminsOfFailure(string $fileName, string $errorMessage): void
    {
        try {
            app(NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $this->tenantId,
                category:     'import_export',
                priority:     'high',
                title:        'Import rollback failed',
                body:         "Rollback of \"{$fileName}\" could not be completed. Please review the import and try again.",
                actionUrl:    "/tenant/{$this->tenantId}/imports",
                actionLabel:  'View Imports',
                dedupeSuffix: "rollback_failed:{$this->rollbackId}",
                metadata:     ['rollback_id' => $this->rollbackId, 'batch_id' => $this->batchId, 'error' => $errorMessage],
            );
        } catch (\Throwable) {}
    }
}
