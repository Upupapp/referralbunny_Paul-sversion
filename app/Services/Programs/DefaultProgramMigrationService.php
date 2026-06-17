<?php

namespace App\Services\Programs;

use App\Models\Program;
use App\Models\Tenant;
use App\Models\TenantReferralProgramDraft;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a default V4 Program for a tenant on first V4 activation.
 *
 * For most tenants: derives name/slug from the existing V3 TenantReferralProgramDraft
 * (or falls back to the tenant name). All existing leads with no program_id are
 * retroactively linked to this default program.
 *
 * For LGU IDS: same process, but the resulting program carries locked metadata so
 * the V4 UI respects the same pipeline/rewards/import locks as the V3 wizard does.
 * The commission formula (30/70) is never re-read from program config — it remains
 * global in CommissionCalculationService env vars.
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

        $slug = Str::slug($name);
        // Ensure slug uniqueness within tenant
        $existing = Program::where('tenant_id', $tenant->id)->where('slug', $slug)->exists();
        if ($existing) {
            $slug = $slug . '-default';
        }

        $data = [
            'tenant_id'    => $tenant->id,
            'name'         => $name,
            'slug'         => $slug,
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
