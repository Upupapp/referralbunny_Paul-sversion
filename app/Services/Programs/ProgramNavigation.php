<?php

namespace App\Services\Programs;

use App\Models\Program;
use App\Models\ProgramConnection;
use App\Support\ProtectedTenants;
use Illuminate\Support\Collection;

class ProgramNavigation
{
    public function programs(string $tenantId): Collection
    {
        if (ProtectedTenants::isProtected($tenantId) || !config('programs.enabled')) {
            return collect();
        }

        return Program::forTenant($tenantId)->visible()
            ->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'status']);
    }

    public function allowsManualDeals(string $tenantId): bool
    {
        // Preserve the protected tenant and legacy manual programs exactly.
        if (ProtectedTenants::isProtected($tenantId) || !config('programs.enabled')) {
            return true;
        }

        // An online program remains automated while disconnected or awaiting setup.
        // Aggregate pages must not offer unassigned manual entry into online programs.
        return !ProgramConnection::where('tenant_id', $tenantId)
            ->whereIn('program_id', Program::forTenant($tenantId)->visible()->select('id'))
            ->exists();
    }
}
