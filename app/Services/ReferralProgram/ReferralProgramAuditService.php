<?php

namespace App\Services\ReferralProgram;

use App\Models\ActivityLog;

class ReferralProgramAuditService
{
    public function log(string $tenantId, string $action, ?string $performedBy, array $metadata = []): void
    {
        try {
            ActivityLog::create([
                'tenant_id' => $tenantId,
                // activity_logs.user_id is a bigint FK to the staff `users` table;
                // tenant-side actors are tenant_users UUIDs, so record those in
                // metadata instead rather than violating the FK on every call.
                'user_id'   => null,
                'action'    => $action,
                'entity'    => 'referral_program_setup',
                'entity_id' => $metadata['draft_id'] ?? null,
                'metadata'  => array_merge($metadata, ['tenant_user_id' => $performedBy]),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Referral program audit log failed', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }
}
