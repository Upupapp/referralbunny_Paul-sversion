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
                'user_id'   => $performedBy,
                'action'    => $action,
                'entity'    => 'referral_program_setup',
                'entity_id' => $metadata['draft_id'] ?? null,
                'metadata'  => $metadata,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Referral program audit log failed', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }
}
