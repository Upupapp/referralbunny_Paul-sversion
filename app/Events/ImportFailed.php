<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a successful DB commit when an import batch transitions to 'failed'
 * or 'completed_with_warnings'.
 *
 * Rule: always fire AFTER the commit — never inside a transaction. Callers
 * must wrap import execution in DB::transaction() and call dispatch() after the
 * transaction closes (or use dispatchAfterResponse()).
 */
class ImportFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string  $batchId,
        public readonly string  $tenantId,
        public readonly string  $fileName,
        public readonly string  $importType,   // 'deals', 'lgu_ids_deals', 'contacts'
        public readonly string  $status,        // 'failed' | 'completed_with_warnings'
        public readonly int     $failedRows,
        public readonly int     $totalRows,
        public readonly ?string $actorId       = null,
        public readonly ?string $actorRole     = null, // 'tenant_admin' | 'reseller' | 'super_admin'
    ) {}
}
