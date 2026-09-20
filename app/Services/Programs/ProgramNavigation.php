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

        // Explicit mode wins; old programs retain their connection-based behavior.
        // Aggregate pages cannot yet assign manual entries to a specific program.
        return !Program::forTenant($tenantId)->visible()->where(function ($query) use ($tenantId) {
            $query->where('operating_mode', 'automated')
                ->orWhere(function ($legacy) use ($tenantId) {
                    $legacy->whereNull('operating_mode')->whereIn('id',
                        ProgramConnection::where('tenant_id', $tenantId)->select('program_id'));
                });
        })->exists();
    }
}
