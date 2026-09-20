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

    public function selectedForDashboard(string $tenantId, \Illuminate\Http\Request $request): ?Program
    {
        if (ProtectedTenants::isProtected($tenantId) || !config('programs.enabled')) return null;
        $key = 'program_dashboard.'.$tenantId;
        $query = Program::forTenant($tenantId)->visible();
        if ($request->query->has('program_id')) {
            $id = $request->query('program_id');
            abort_unless(is_string($id), 422);
            $program = (clone $query)->whereKey($id)->firstOrFail();
        } else {
            $program = (clone $query)->whereKey($request->session()->get($key))->first()
                ?? $query->orderByDesc('is_default')->orderBy('name')->first();
        }
        if ($program) $request->session()->put($key, $program->id);
        else $request->session()->forget($key);
        return $program;
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
