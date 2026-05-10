<?php

namespace App\Services;

use App\Models\ImportBatchRow;
use App\Models\ImportSnapshot;
use Illuminate\Support\Str;

/**
 * Records before/after snapshots for every record created or updated during
 * a batch import execution. These snapshots power the rollback system.
 *
 * Call recordCreated() AFTER successfully inserting a new record.
 * Call recordUpdated() BEFORE applying changes to an existing record.
 */
class ImportSnapshotService
{
    /**
     * Record a newly created entity (deal, contact, organization, referrer).
     */
    public function recordCreated(
        string       $batchId,
        string       $tenantId,
        string       $entityType,
        string       $entityId,
        array        $entityData,
        ?string      $batchRowId = null,
        ?ImportBatchRow $row = null,
    ): ImportSnapshot {
        $snapshot = ImportSnapshot::create([
            'import_batch_id'     => $batchId,
            'import_batch_row_id' => $batchRowId,
            'entity_type'         => $entityType,
            'entity_id'           => $entityId,
            'operation_type'      => 'created',
            'action'              => 'created',
            'before_data'         => null,
            'after_data'          => $entityData,
            'changed_fields'      => array_keys($entityData),
            'can_rollback'        => true,
            'rollback_status'     => 'pending',
        ]);

        if ($row) {
            $row->update(['snapshot_id' => $snapshot->id]);
        }

        return $snapshot;
    }

    /**
     * Record an update to an existing entity.
     * Call BEFORE applying changes — pass the current state as $beforeData
     * and the fields being changed as $changedFields / $afterData.
     */
    public function recordUpdated(
        string       $batchId,
        string       $tenantId,
        string       $entityType,
        string       $entityId,
        array        $beforeData,
        array        $afterData,
        array        $changedFields = [],
        string       $operationType = 'updated', // updated|merged|overwritten
        ?string      $batchRowId = null,
        ?ImportBatchRow $row = null,
    ): ImportSnapshot {
        $snapshot = ImportSnapshot::create([
            'import_batch_id'     => $batchId,
            'import_batch_row_id' => $batchRowId,
            'entity_type'         => $entityType,
            'entity_id'           => $entityId,
            'operation_type'      => $operationType,
            'action'              => 'updated',
            'before_data'         => $beforeData,
            'after_data'          => $afterData,
            'changed_fields'      => $changedFields ?: array_keys($afterData),
            'can_rollback'        => true,
            'rollback_status'     => 'pending',
        ]);

        if ($row) {
            $row->update(['snapshot_id' => $snapshot->id]);
        }

        return $snapshot;
    }

    /**
     * After all rows are processed, mark the batch as rollback-eligible
     * if at least one actionable snapshot was created.
     */
    public function markBatchEligible(string $batchId): void
    {
        $hasSnapshots = ImportSnapshot::where('import_batch_id', $batchId)
            ->whereIn('operation_type', ['created', 'updated', 'merged', 'overwritten'])
            ->exists();

        \App\Models\ImportBatch::where('id', $batchId)->update([
            'rollback_status' => $hasSnapshots ? 'eligible' : 'not_available',
        ]);
    }
}
