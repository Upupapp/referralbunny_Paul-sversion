<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\ImportSnapshot;
use Illuminate\Support\Facades\DB;

/**
 * Generates a dry-run preview of what an import rollback will do.
 * Does NOT modify any data — read-only.
 */
class ImportRollbackPreviewService
{
    // Rollback window: batches older than this cannot be rolled back
    const ROLLBACK_WINDOW_DAYS = 30;

    /**
     * Check whether a batch is eligible for rollback and why/why not.
     *
     * @return array{eligible: bool, reason: string|null, summary: array}
     */
    public function check(ImportBatch $batch, string $tenantId): array
    {
        // Must belong to this tenant
        if ($batch->tenant_id !== $tenantId) {
            return ['eligible' => false, 'reason' => 'Access denied.', 'summary' => []];
        }

        // Must be completed
        if (!in_array($batch->status, ['completed', 'completed_with_warnings'])) {
            $map = [
                'processing' => 'This import is still processing.',
                'failed'     => 'This import failed before creating records.',
                'previewed'  => 'This import has not been executed yet.',
            ];
            return [
                'eligible' => false,
                'reason'   => $map[$batch->status] ?? "This import cannot be undone (status: {$batch->status}).",
                'summary'  => [],
            ];
        }

        // Must not already be rolled back
        if ($batch->isAlreadyRolledBack()) {
            return [
                'eligible' => false,
                'reason'   => 'This import has already been undone.',
                'summary'  => [],
            ];
        }

        // Must not be currently rolling back
        if (in_array($batch->rollback_status, ['processing'])) {
            return [
                'eligible' => false,
                'reason'   => 'This import is currently being undone. Please wait.',
                'summary'  => [],
            ];
        }

        // Must be within rollback window
        if ($batch->completed_at && $batch->completed_at->lt(now()->subDays(self::ROLLBACK_WINDOW_DAYS))) {
            return [
                'eligible' => false,
                'reason'   => 'This import is outside the ' . self::ROLLBACK_WINDOW_DAYS . '-day rollback window.',
                'summary'  => [],
            ];
        }

        // Must have snapshots
        $snapshotCount = ImportSnapshot::where('import_batch_id', $batch->id)
            ->whereIn('operation_type', ['created', 'updated', 'merged', 'overwritten'])
            ->count();

        if ($snapshotCount === 0) {
            return [
                'eligible' => false,
                'reason'   => 'This import cannot be undone because rollback data is unavailable.',
                'summary'  => [],
            ];
        }

        // Build preview summary
        $summary = $this->buildSummary($batch, $tenantId);

        return [
            'eligible' => true,
            'reason'   => null,
            'summary'  => $summary,
        ];
    }

    /**
     * Build a detailed summary of what will be rolled back.
     */
    public function buildSummary(ImportBatch $batch, string $tenantId): array
    {
        $snapshots = ImportSnapshot::where('import_batch_id', $batch->id)
            ->whereIn('rollback_status', ['pending'])
            ->get();

        $toRemove  = [];
        $toRestore = [];
        $conflicts = [];

        foreach ($snapshots as $snap) {
            if ($snap->operation_type === 'created') {
                $conflict = $this->detectCreatedConflict($snap, $tenantId);
                if ($conflict) {
                    $conflicts[] = [
                        'entity_type' => $snap->entity_type,
                        'entity_id'   => $snap->entity_id,
                        'reason'      => $conflict,
                        'operation'   => 'created',
                    ];
                } else {
                    $toRemove[] = [
                        'entity_type' => $snap->entity_type,
                        'entity_id'   => $snap->entity_id,
                        'label'       => $this->entityLabel($snap),
                    ];
                }
            } elseif (in_array($snap->operation_type, ['updated', 'merged', 'overwritten'])) {
                $conflict = $this->detectUpdatedConflict($snap, $tenantId);
                if ($conflict) {
                    $conflicts[] = [
                        'entity_type' => $snap->entity_type,
                        'entity_id'   => $snap->entity_id,
                        'reason'      => $conflict,
                        'operation'   => $snap->operation_type,
                    ];
                } else {
                    $toRestore[] = [
                        'entity_type'    => $snap->entity_type,
                        'entity_id'      => $snap->entity_id,
                        'label'          => $this->entityLabel($snap),
                        'changed_fields' => $snap->changed_fields ?? [],
                    ];
                }
            }
        }

        return [
            'batch_id'        => $batch->id,
            'file_name'       => $batch->file_name,
            'import_type'     => $batch->import_type,
            'imported_at'     => $batch->completed_at?->toIso8601String(),
            'total_snapshots' => $snapshots->count(),
            'to_remove'       => $toRemove,
            'to_restore'      => $toRestore,
            'conflicts'       => $conflicts,
            'remove_count'    => count($toRemove),
            'restore_count'   => count($toRestore),
            'conflict_count'  => count($conflicts),
        ];
    }

    // ── Conflict Detection ─────────────────────────────────────────

    private function detectCreatedConflict(ImportSnapshot $snap, string $tenantId): ?string
    {
        return match ($snap->entity_type) {
            'lead'    => $this->detectDealConflict($snap->entity_id, $tenantId, $snap->created_at),
            'contact' => $this->detectContactConflict($snap->entity_id, $tenantId, $snap->created_at),
            default   => null,
        };
    }

    private function detectUpdatedConflict(ImportSnapshot $snap, string $tenantId): ?string
    {
        if (!$snap->before_data) {
            return 'Rollback data is incomplete (no before-state recorded).';
        }

        return match ($snap->entity_type) {
            'lead'    => $this->detectDealModifiedAfterImport($snap->entity_id, $tenantId, $snap->created_at),
            'contact' => $this->detectContactModifiedAfterImport($snap->entity_id, $tenantId, $snap->created_at),
            default   => null,
        };
    }

    private function detectDealConflict(string $dealId, string $tenantId, $importedAt): ?string
    {
        $deal = DB::table('leads')->where('id', $dealId)->where('tenant_id', $tenantId)->first();
        if (!$deal) return null; // Already deleted — will be skipped

        if (in_array($deal->commission_status, ['locked', 'paid'])) {
            return 'Deal commission is locked or paid — cannot automatically undo.';
        }
        if (in_array($deal->stage, ['signed', 'paid'])) {
            return 'Deal has progressed to ' . ucfirst($deal->stage) . ' stage — cannot automatically undo.';
        }
        return null;
    }

    private function detectDealModifiedAfterImport(string $dealId, string $tenantId, $importedAt): ?string
    {
        $deal = DB::table('leads')->where('id', $dealId)->where('tenant_id', $tenantId)->first();
        if (!$deal) return 'Record no longer exists.';

        if ($importedAt && isset($deal->updated_at) && $deal->updated_at >= $importedAt) {
            return 'Record was modified after the import.';
        }
        return null;
    }

    private function detectContactConflict(string $contactId, string $tenantId, $importedAt): ?string
    {
        $contact = DB::table('contacts')->where('id', $contactId)->where('tenant_id', $tenantId)->first();
        if (!$contact) return null;

        // If contact was manually modified after import
        if ($importedAt && isset($contact->updated_at) && $contact->updated_at >= $importedAt) {
            return 'Contact was modified after the import.';
        }
        return null;
    }

    private function detectContactModifiedAfterImport(string $contactId, string $tenantId, $importedAt): ?string
    {
        $contact = DB::table('contacts')->where('id', $contactId)->where('tenant_id', $tenantId)->first();
        if (!$contact) return 'Contact no longer exists.';

        if ($importedAt && isset($contact->updated_at) && $contact->updated_at >= $importedAt) {
            return 'Contact was modified after the import.';
        }
        return null;
    }

    private function entityLabel(ImportSnapshot $snap): string
    {
        $data = $snap->after_data ?? $snap->before_data ?? [];
        return match ($snap->entity_type) {
            'lead'    => $data['name'] ?? "Deal {$snap->entity_id}",
            'contact' => trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')) ?: ($data['email'] ?? "Contact {$snap->entity_id}"),
            default   => ucfirst($snap->entity_type) . " {$snap->entity_id}",
        };
    }
}
