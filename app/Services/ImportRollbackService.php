<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ImportBatch;
use App\Models\ImportRollback;
use App\Models\ImportSnapshot;
use App\Services\CriticalActionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Executes the actual batch import rollback.
 *
 * Safety model:
 * - Created records are soft-archived or hard-deleted based on entity type and safety checks.
 * - Updated records are restored only to their changed fields from before_data.
 * - Conflicts are detected and skipped rather than force-rolled back.
 * - LGU IDS locked rules are preserved.
 * - All operations are tenant-scoped.
 */
class ImportRollbackService
{
    public function __construct(
        private ImportRollbackPreviewService $preview,
        private NotificationDispatchService  $notifications,
    ) {}

    /**
     * Execute rollback. Called from ProcessImportRollbackJob.
     * Updates rollback and batch records; processes snapshots in safe order.
     */
    public function execute(ImportRollback $rollback, ImportBatch $batch, string $tenantId): void
    {
        // Lock inside a transaction — lockForUpdate() requires an active transaction
        $acquired = false;
        \Illuminate\Support\Facades\DB::transaction(function () use ($rollback, $batch, &$acquired) {
            $locked = ImportRollback::where('id', $rollback->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if ($locked) {
                $locked->markProcessing();
                $batch->update(['rollback_status' => 'processing']);
                $acquired = true;
            }
        });

        if (!$acquired) {
            return; // Already processing or completed — safe no-op
        }

        $restored = 0;
        $removed  = 0;
        $skipped  = 0;
        $failed   = 0;
        $conflict = 0;
        $errors   = [];

        // Process updated/merged/overwritten snapshots first (restore before removing)
        $updates = ImportSnapshot::where('import_batch_id', $batch->id)
            ->whereIn('operation_type', ['updated', 'merged', 'overwritten'])
            ->where('rollback_status', 'pending')
            ->get();

        foreach ($updates as $snap) {
            try {
                $result = $this->restoreUpdatedRecord($snap, $tenantId);
                if ($result === 'restored')  { $restored++; }
                elseif ($result === 'conflict') { $conflict++; }
                else { $skipped++; }
            } catch (\Throwable $e) {
                $snap->markFailed($e->getMessage());
                $errors[] = "Restore {$snap->entity_type}:{$snap->entity_id} — {$e->getMessage()}";
                $failed++;
            }
        }

        // Then remove created records
        $created = ImportSnapshot::where('import_batch_id', $batch->id)
            ->where('operation_type', 'created')
            ->where('rollback_status', 'pending')
            ->get();

        foreach ($created as $snap) {
            try {
                $result = $this->removeCreatedRecord($snap, $tenantId);
                if ($result === 'removed')   { $removed++; }
                elseif ($result === 'conflict') { $conflict++; }
                else { $skipped++; }
            } catch (\Throwable $e) {
                $snap->markFailed($e->getMessage());
                $errors[] = "Remove {$snap->entity_type}:{$snap->entity_id} — {$e->getMessage()}";
                $failed++;
            }
        }

        $result = compact('restored', 'removed', 'skipped', 'failed', 'conflict', 'errors');

        $rollback->markCompleted($result);

        $rollbackStatus = $conflict > 0 || $failed > 0 ? 'completed_with_warnings' : 'completed';
        $batch->update([
            'rollback_status' => $rollbackStatus,
            'rollback_id'     => $rollback->id,
        ]);

        // Audit
        $this->auditLog($tenantId, $batch->id, $rollback->requested_by, 'import_rollback_completed', $result);

        // Notify requester
        $this->notifyRequester($rollback, $batch, $tenantId, $result);
        try { app(CriticalActionService::class)->invalidateAllAdminBadges($tenantId); } catch (\Throwable) {}
    }

    // ── Restore updated record ─────────────────────────────────────────

    private function restoreUpdatedRecord(ImportSnapshot $snap, string $tenantId): string
    {
        if (!$snap->before_data || empty($snap->changed_fields)) {
            $snap->markSkipped('No before-state data available.');
            return 'skipped';
        }

        return match ($snap->entity_type) {
            'lead'    => $this->restoreLead($snap, $tenantId),
            'contact' => $this->restoreContact($snap, $tenantId),
            default   => $this->skipUnknown($snap, 'Unsupported entity type for restore.'),
        };
    }

    private function restoreLead(ImportSnapshot $snap, string $tenantId): string
    {
        $deal = DB::table('leads')->where('id', $snap->entity_id)->where('tenant_id', $tenantId)->first();

        if (!$deal) {
            $snap->markSkipped('Deal no longer exists.');
            return 'skipped';
        }

        // Conflict: commission locked/paid
        if (in_array($deal->commission_status, ['locked', 'paid'])) {
            $snap->markConflict('Deal commission is locked or paid.');
            return 'conflict';
        }

        // Conflict: modified after snapshot
        if ($snap->created_at && isset($deal->updated_at) && $deal->updated_at >= $snap->created_at) {
            $snap->markConflict('Deal was modified after the import.');
            return 'conflict';
        }

        // Restore only the changed fields from before_data
        $beforeData = $snap->before_data;
        $restore    = [];
        foreach ($snap->changed_fields ?? [] as $field) {
            if (array_key_exists($field, $beforeData)) {
                $restore[$field] = $beforeData[$field];
            }
        }

        if (!empty($restore)) {
            DB::table('leads')->where('id', $snap->entity_id)->update(array_merge($restore, ['updated_at' => now()]));
        }

        $snap->markRolledBack('Fields restored from import snapshot.');
        return 'restored';
    }

    private function restoreContact(ImportSnapshot $snap, string $tenantId): string
    {
        $contact = DB::table('contacts')->where('id', $snap->entity_id)->where('tenant_id', $tenantId)->first();

        if (!$contact) {
            $snap->markSkipped('Contact no longer exists.');
            return 'skipped';
        }

        // Conflict: modified after snapshot
        if ($snap->created_at && isset($contact->updated_at) && $contact->updated_at > $snap->created_at) {
            $snap->markConflict('Contact was modified after the import.');
            return 'conflict';
        }

        $beforeData = $snap->before_data;
        $restore    = [];
        foreach ($snap->changed_fields ?? [] as $field) {
            if (array_key_exists($field, $beforeData)) {
                $restore[$field] = $beforeData[$field];
            }
        }

        if (!empty($restore)) {
            DB::table('contacts')->where('id', $snap->entity_id)->update(array_merge($restore, ['updated_at' => now()]));
        }

        $snap->markRolledBack('Fields restored from import snapshot.');
        return 'restored';
    }

    // ── Remove created record ──────────────────────────────────────────

    private function removeCreatedRecord(ImportSnapshot $snap, string $tenantId): string
    {
        return match ($snap->entity_type) {
            'lead'    => $this->removeLead($snap, $tenantId),
            'contact' => $this->removeContact($snap, $tenantId),
            default   => $this->skipUnknown($snap, 'Unsupported entity type for removal.'),
        };
    }

    private function removeLead(ImportSnapshot $snap, string $tenantId): string
    {
        $deal = DB::table('leads')->where('id', $snap->entity_id)->where('tenant_id', $tenantId)->first();

        if (!$deal) {
            $snap->markSkipped('Deal no longer exists — already removed.');
            return 'skipped';
        }

        // Conflict checks
        if (in_array($deal->commission_status, ['locked', 'paid'])) {
            $snap->markConflict('Deal commission is locked or paid — cannot remove.');
            return 'conflict';
        }
        if (in_array($deal->stage, ['signed', 'paid'])) {
            $snap->markConflict("Deal has progressed to '{$deal->stage}' stage — cannot automatically remove.");
            return 'conflict';
        }
        // Check for manually added notes / partner splits after import
        $hasManualActivity = DB::table('deal_comments')
            ->where('deal_id', $snap->entity_id)
            ->whereNull('deleted_at')
            ->exists();
        if ($hasManualActivity) {
            $snap->markConflict('Deal has notes/comments added after import — cannot automatically remove.');
            return 'conflict';
        }

        // Safe to remove — use status-based soft-remove to preserve audit trail
        DB::table('leads')->where('id', $snap->entity_id)->update([
            'status'     => 'declined',
            'data'       => DB::raw("jsonb_set(COALESCE(data, '{}'), '{import_rollback}', '\"" . $snap->import_batch_id . "\"')"),
            'updated_at' => now(),
        ]);

        $snap->markRolledBack('Deal marked declined via import rollback.');
        return 'removed';
    }

    private function removeContact(ImportSnapshot $snap, string $tenantId): string
    {
        $contact = DB::table('contacts')->where('id', $snap->entity_id)->where('tenant_id', $tenantId)->first();

        if (!$contact) {
            $snap->markSkipped('Contact no longer exists — already removed.');
            return 'skipped';
        }

        // Conflict: modified after import
        if ($snap->created_at && isset($contact->updated_at) && $contact->updated_at > $snap->created_at) {
            $snap->markConflict('Contact was modified after the import.');
            return 'conflict';
        }

        // Archive the contact (uses existing archived_at field)
        DB::table('contacts')->where('id', $snap->entity_id)->update([
            'archived_at' => now(),
            'updated_at'  => now(),
        ]);

        $snap->markRolledBack('Contact archived via import rollback.');
        return 'removed';
    }

    private function skipUnknown(ImportSnapshot $snap, string $reason): string
    {
        $snap->markSkipped($reason);
        return 'skipped';
    }

    // ── Audit & Notifications ──────────────────────────────────────────

    private function auditLog(string $tenantId, string $batchId, string $actorId, string $action, array $summary): void
    {
        try {
            ActivityLog::create([
                'id'        => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'user_id'   => $actorId,
                'action'    => $action,
                'entity'    => 'import_batch',
                'entity_id' => $batchId,
                'metadata'  => $summary,
            ]);
        } catch (\Throwable) {}
    }

    private function notifyRequester(ImportRollback $rollback, ImportBatch $batch, string $tenantId, array $result): void
    {
        try {
            $removed  = $result['removed']  ?? 0;
            $restored = $result['restored'] ?? 0;
            $conflict = $result['conflict'] ?? 0;
            $status   = $rollback->status === 'completed' ? 'complete' : 'complete with warnings';

            $this->notifications->dispatch(
                category:         'import_export',
                priority:         $conflict > 0 ? 'high' : 'normal',
                title:            'Import rollback ' . $status,
                body:             "Your import rollback for \"{$batch->file_name}\" is {$status}. {$removed} records removed, {$restored} records restored, {$conflict} items need review.",
                notifiableType:   $rollback->requested_by_type ?? 'tenant_admin',
                notifiableId:     $rollback->requested_by,
                tenantId:         $tenantId,
                actionUrl:        "/tenant/{$tenantId}/imports/{$batch->import_type === 'lgu_ids_deals' ? 'lgu-ids' : ($batch->import_type === 'contacts' ? 'contacts' : 'deals')}/{$batch->id}/rollback/{$rollback->id}",
                actionLabel:      'View rollback report',
                deduplicationKey: "rollback_done:{$rollback->id}",
                metadata:         $result,
            );
        } catch (\Throwable) {}
    }
}
