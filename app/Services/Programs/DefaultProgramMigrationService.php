<?php

namespace App\Services\Programs;

use App\Models\Program;
use App\Models\Tenant;
use App\Models\TenantReferralProgramDraft;
use App\Support\ProtectedTenants;
use Illuminate\Support\Facades\DB;

/**
 * Creates a default V4 Program for a tenant on first V4 activation.
 *
 * Derives name/slug from the existing V3 TenantReferralProgramDraft (or falls
 * back to the tenant name). All existing leads with no program_id are
 * retroactively linked to this default program.
 *
 * ProtectedTenants (lgu-ids) are blocked entirely, same as everywhere else in
 * Programs V4 — there is no scoped exception. lgu-ids's V3 pipeline/rewards/
 * import locks and 30/70 commission formula stay exactly where they are
 * today (CommissionCalculationService env vars); they are never meant to be
 * re-expressed as a V4 Program.
 */
class DefaultProgramMigrationService
{
    /**
     * Ensure a default Program exists for the given tenant, then backfill leads.
     * Idempotent — safe to call multiple times; no-ops if default already exists.
     *
     * @return Program  the existing or newly created default program
     */
    public function ensureDefault(Tenant $tenant): Program
    {
        abort_if(ProtectedTenants::isProtected($tenant->id), 404);

        $existing = Program::where('tenant_id', $tenant->id)
            ->where('is_default', true)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($tenant) {
            $program = $this->createDefaultProgram($tenant);
            $this->backfillLeads($tenant->id, $program->id);
            return $program;
        });
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function createDefaultProgram(Tenant $tenant): Program
    {
        // Try to derive name from V3 draft
        $v3Draft = TenantReferralProgramDraft::where('tenant_id', $tenant->id)->first();
        $name    = $v3Draft?->program_name ?? $tenant->program_name ?? $tenant->name . ' Referral Program';

        // Omit 'slug' — Program::boot() handles unique slug generation per tenant.
        $data = [
            'tenant_id'    => $tenant->id,
            'name'         => $name,
            'status'       => 'active',
            'program_type' => 'referral',
            'is_default'   => true,
            'launched_at'  => now(),
        ];

        return Program::create($data);
    }

    private function backfillLeads(string $tenantId, string $programId): void
    {
        // Chunk to avoid locking the entire leads table
        DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->whereNull('program_id')
            ->orderBy('id')
            ->chunkById(500, function ($chunk) use ($programId) {
                $ids = $chunk->pluck('id')->all();
                DB::table('leads')
                    ->whereIn('id', $ids)
                    ->update(['program_id' => $programId]);
            });
    }
}
